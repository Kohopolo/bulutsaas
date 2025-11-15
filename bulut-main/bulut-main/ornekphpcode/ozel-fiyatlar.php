<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'csrf_protection.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('ozel_fiyatlar_yonetimi', 'Özel fiyatlar yönetimi yetkiniz bulunmamaktadır.');

// CSRF token oluştur
$csrf_token = generateCSRFToken();

// Başarı/hata mesajları
$success_message = '';
$error_message = '';

// Özel fiyat ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $oda_tipi_id = (int)$_POST['oda_tipi_id'];
        $baslangic_tarihi = sanitizeString($_POST['baslangic_tarihi']);
        $bitis_tarihi = sanitizeString($_POST['bitis_tarihi']);
        $fiyat_tipi = sanitizeString($_POST['fiyat_tipi']);
        $aciklama = sanitizeString($_POST['aciklama']);
        $durum = sanitizeString($_POST['durum']);

        // Günlük fiyatlar
        $gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
        $gun_fiyatlari = [];
        $aktif_gunler = [];

        foreach ($gunler as $gun) {
            $aktif_gunler[$gun] = isset($_POST[$gun . '_aktif']) ? 1 : 0;
            if ($aktif_gunler[$gun]) {
                $gun_fiyatlari[$gun] = (float)$_POST[$gun . '_fiyat'];
            }
        }

        if (empty($baslangic_tarihi) || empty($bitis_tarihi) || empty($fiyat_tipi)) {
            $error_message = 'Başlangıç tarihi, bitiş tarihi ve fiyat türü zorunludur.';
        } elseif (array_sum($aktif_gunler) === 0) {
            $error_message = 'En az bir gün seçilmelidir.';
        } else {
            try {
                // Çakışan tarih aralığı kontrolü
                $check_stmt = $pdo->prepare("
                    SELECT id FROM ozel_fiyatlar 
                    WHERE oda_tipi_id = ? 
                    AND (
                        (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                        (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                        (baslangic_tarihi >= ? AND bitis_tarihi <= ?)
                    )
                ");
                $check_stmt->execute([
                    $oda_tipi_id, 
                    $baslangic_tarihi, $baslangic_tarihi,
                    $bitis_tarihi, $bitis_tarihi,
                    $baslangic_tarihi, $bitis_tarihi
                ]);
                
                if ($check_stmt->fetch()) {
                    $error_message = 'Bu oda tipi için seçilen tarih aralığında zaten özel fiyat tanımlanmış.';
                } else {
                    // Özel fiyat ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO ozel_fiyatlar (
                            oda_tipi_id, baslangic_tarihi, bitis_tarihi, fiyat_tipi, aciklama, aktif,
                            pazartesi_aktif, pazartesi_temel_fiyat, pazartesi_sabit_fiyat,
                            sali_aktif, sali_temel_fiyat, sali_sabit_fiyat,
                            carsamba_aktif, carsamba_temel_fiyat, carsamba_sabit_fiyat,
                            persembe_aktif, persembe_temel_fiyat, persembe_sabit_fiyat,
                            cuma_aktif, cuma_temel_fiyat, cuma_sabit_fiyat,
                            cumartesi_aktif, cumartesi_temel_fiyat, cumartesi_sabit_fiyat,
                            pazar_aktif, pazar_temel_fiyat, pazar_sabit_fiyat
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $params = [$oda_tipi_id, $baslangic_tarihi, $bitis_tarihi, $fiyat_tipi, $aciklama, $durum];
                    
                    foreach ($gunler as $gun) {
                        $params[] = $aktif_gunler[$gun];
                        if ($fiyat_tipi === 'temel_fiyat') {
                            $params[] = $aktif_gunler[$gun] ? $gun_fiyatlari[$gun] : null;
                            $params[] = null;
                        } else {
                            $params[] = null;
                            $params[] = $aktif_gunler[$gun] ? $gun_fiyatlari[$gun] : null;
                        }
                    }
                    
                    if ($stmt->execute($params)) {
                        $success_message = 'Özel fiyat başarıyla eklendi.';
                        logSecurityEvent(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0, 'ozel_fiyat_eklendi', "Özel fiyat eklendi: $baslangic_tarihi - $bitis_tarihi");
                    } else {
                        $error_message = 'Özel fiyat eklenirken bir hata oluştu.';
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}

// Özel fiyat silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ozel_fiyatlar WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success_message = 'Özel fiyat başarıyla silindi.';
            logSecurityEvent($_SESSION['admin_id'], 'ozel_fiyat_silindi', "Özel fiyat silindi: ID $id");
        } else {
            $error_message = 'Özel fiyat silinirken bir hata oluştu.';
        }
    }
}

// Oda tiplerini getir
try {
    $stmt = $pdo->prepare("SELECT id, oda_tipi_adi, base_price, fiyatlama_sistemi FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
    $stmt->execute();
    $oda_tipleri = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Oda tipleri yüklenirken hata oluştu: ' . $e->getMessage();
    $oda_tipleri = [];
}

// Özel fiyatları getir
try {
    // Önce tabloyu oluştur
    $pdo->exec("CREATE TABLE IF NOT EXISTS ozel_fiyatlar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        oda_tipi_id INT NOT NULL,
        baslangic_tarihi DATE NOT NULL,
        bitis_tarihi DATE NOT NULL,
        fiyat_tipi ENUM('temel_fiyat', 'sabit_fiyat') NOT NULL DEFAULT 'temel_fiyat',
        aciklama TEXT,
        aktif ENUM('aktif', 'pasif') DEFAULT 'aktif',
        
        pazartesi_aktif TINYINT(1) DEFAULT 0,
        pazartesi_temel_fiyat DECIMAL(10,2) NULL,
        pazartesi_sabit_fiyat DECIMAL(10,2) NULL,
        
        sali_aktif TINYINT(1) DEFAULT 0,
        sali_temel_fiyat DECIMAL(10,2) NULL,
        sali_sabit_fiyat DECIMAL(10,2) NULL,
        
        carsamba_aktif TINYINT(1) DEFAULT 0,
        carsamba_temel_fiyat DECIMAL(10,2) NULL,
        carsamba_sabit_fiyat DECIMAL(10,2) NULL,
        
        persembe_aktif TINYINT(1) DEFAULT 0,
        persembe_temel_fiyat DECIMAL(10,2) NULL,
        persembe_sabit_fiyat DECIMAL(10,2) NULL,
        
        cuma_aktif TINYINT(1) DEFAULT 0,
        cuma_temel_fiyat DECIMAL(10,2) NULL,
        cuma_sabit_fiyat DECIMAL(10,2) NULL,
        
        cumartesi_aktif TINYINT(1) DEFAULT 0,
        cumartesi_temel_fiyat DECIMAL(10,2) NULL,
        cumartesi_sabit_fiyat DECIMAL(10,2) NULL,
        
        pazar_aktif TINYINT(1) DEFAULT 0,
        pazar_temel_fiyat DECIMAL(10,2) NULL,
        pazar_sabit_fiyat DECIMAL(10,2) NULL,
        
        olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (oda_tipi_id) REFERENCES oda_tipleri(id) ON DELETE CASCADE
    )");

    $stmt = $pdo->prepare("
        SELECT of.*, ot.oda_tipi_adi 
        FROM ozel_fiyatlar of
        JOIN oda_tipleri ot ON of.oda_tipi_id = ot.id
        ORDER BY of.baslangic_tarihi DESC, ot.oda_tipi_adi
    ");
    $stmt->execute();
    $ozel_fiyatlar = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Özel fiyatlar yüklenirken hata oluştu: ' . $e->getMessage();
    $ozel_fiyatlar = [];
}

$page_title = 'Özel Fiyatlar';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tags me-2"></i>Özel Fiyatlar
                </h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecialPriceModal">
                    <i class="fas fa-plus me-1"></i>Yeni Özel Fiyat
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($ozel_fiyatlar)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5>Henüz özel fiyat tanımlanmamış</h5>
                            <p class="text-muted">Belirli tarih aralıkları için özel fiyat tanımlamak için "Yeni Özel Fiyat" butonuna tıklayın.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tarih Aralığı</th>
                                        <th>Oda Tipi</th>
                                        <th>Fiyat Türü</th>
                                        <th>Aktif Günler</th>
                                        <th>Açıklama</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ozel_fiyatlar as $ozel_fiyat): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d.m.Y', strtotime($ozel_fiyat['baslangic_tarihi'])); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('d.m.Y', strtotime($ozel_fiyat['bitis_tarihi'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($ozel_fiyat['oda_tipi_adi']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $ozel_fiyat['fiyat_tipi'] === 'temel_fiyat' ? 'info' : 'warning'; ?>">
                                                    <?php echo $ozel_fiyat['fiyat_tipi'] === 'temel_fiyat' ? 'Temel Fiyat' : 'Sabit Fiyat'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
                                                $gun_isimleri = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
                                                $aktif_gunler = [];
                                                
                                                foreach ($gunler as $i => $gun) {
                                                    if ($ozel_fiyat[$gun . '_aktif']) {
                                                        $fiyat_kolonu = $ozel_fiyat['fiyat_tipi'] === 'temel_fiyat' ? $gun . '_temel_fiyat' : $gun . '_sabit_fiyat';
                                                        $fiyat = $ozel_fiyat[$fiyat_kolonu];
                                                        $aktif_gunler[] = $gun_isimleri[$i] . ' (' . number_format($fiyat, 0) . '₺)';
                                                    }
                                                }
                                                echo implode(', ', $aktif_gunler);
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ozel_fiyat['aciklama'] ?: '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $ozel_fiyat['aktif'] === 'aktif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($ozel_fiyat['aktif']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSpecialPrice(<?php echo $ozel_fiyat['id']; ?>, '<?php echo date('d.m.Y', strtotime($ozel_fiyat['baslangic_tarihi'])); ?> - <?php echo date('d.m.Y', strtotime($ozel_fiyat['bitis_tarihi'])); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Özel Fiyat Ekleme Modal -->
<div class="modal fade" id="addSpecialPriceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Yeni Özel Fiyat Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addSpecialPriceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="oda_tipi_id" class="form-label">Oda Tipi <span class="text-danger">*</span></label>
                                <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required>
                                    <option value="">Oda tipi seçin</option>
                                    <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                        <option value="<?php echo $oda_tipi['id']; ?>" data-base-price="<?php echo $oda_tipi['base_price']; ?>">
                                            <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?> 
                                            (<?php echo number_format($oda_tipi['base_price'], 0); ?>₺)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fiyat_tipi" class="form-label">Fiyat Türü <span class="text-danger">*</span></label>
                                <select class="form-select" id="fiyat_tipi" name="fiyat_tipi" required>
                                    <option value="temel_fiyat">Temel Fiyat (Çarpan)</option>
                                    <option value="sabit_fiyat">Sabit Fiyat</option>
                                </select>
                                <div class="form-text">
                                    <small>Temel Fiyat: Oda tipinin temel fiyatına çarpan uygulanır<br>
                                    Sabit Fiyat: Girilen fiyat direkt kullanılır</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Günlük Fiyatlar <span class="text-danger">*</span></label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <?php 
                                    $gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
                                    $gun_isimleri = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
                                    
                                    foreach ($gunler as $i => $gun): 
                                    ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="<?php echo $gun; ?>_aktif" name="<?php echo $gun; ?>_aktif" onchange="toggleDayPrice('<?php echo $gun; ?>')">
                                                <label class="form-check-label fw-bold" for="<?php echo $gun; ?>_aktif">
                                                    <?php echo $gun_isimleri[$i]; ?>
                                                </label>
                                            </div>
                                            <div class="input-group mt-2">
                                                <input type="number" class="form-control" id="<?php echo $gun; ?>_fiyat" name="<?php echo $gun; ?>_fiyat" 
                                                       step="0.01" min="0" placeholder="0.00" disabled>
                                                <span class="form-control-text" id="<?php echo $gun; ?>_unit">₺</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Temel Fiyat:</strong> Girilen değer çarpan olarak kullanılır (örn: 1.5 = %50 artış)<br>
                                    <strong>Sabit Fiyat:</strong> Girilen değer direkt fiyat olarak kullanılır
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="2" placeholder="Özel fiyat açıklaması..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="aktif">Aktif</option>
                                    <option value="pasif">Pasif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Silme Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Özel Fiyat Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bu özel fiyatı silmek istediğinizden emin misiniz?</p>
                <p><strong id="deleteItemName"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id" id="deleteItemId">
                    <button type="submit" class="btn btn-danger">Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleDayPrice(gun) {
    const checkbox = document.getElementById(gun + '_aktif');
    const input = document.getElementById(gun + '_fiyat');
    
    if (checkbox.checked) {
        input.disabled = false;
        input.focus();
    } else {
        input.disabled = true;
        input.value = '';
    }
}

function deleteSpecialPrice(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Fiyat türü değiştiğinde birim güncelle
document.getElementById('fiyat_tipi').addEventListener('change', function() {
    const fiyatTipi = this.value;
    const gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
    
    gunler.forEach(function(gun) {
        const unit = document.getElementById(gun + '_unit');
        if (fiyatTipi === 'temel_fiyat') {
            unit.textContent = 'x';
        } else {
            unit.textContent = '₺';
        }
    });
});

// Form validasyonu
document.getElementById('addSpecialPriceForm').addEventListener('submit', function(e) {
    const gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
    let aktifGunVar = false;
    
    gunler.forEach(function(gun) {
        if (document.getElementById(gun + '_aktif').checked) {
            aktifGunVar = true;
        }
    });
    
    if (!aktifGunVar) {
        e.preventDefault();
        alert('En az bir gün seçilmelidir!');
        return false;
    }
});

// Tarih validasyonu
document.getElementById('bitis_tarihi').addEventListener('change', function() {
    const baslangic = document.getElementById('baslangic_tarihi').value;
    const bitis = this.value;
    
    if (baslangic && bitis && bitis < baslangic) {
        alert('Bitiş tarihi başlangıç tarihinden önce olamaz!');
        this.value = '';
    }
});
</script>

</body>
</html>