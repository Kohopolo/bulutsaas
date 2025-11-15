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
                        logSecurityEvent($_SESSION['admin_id'], 'ozel_fiyat_eklendi', "Özel fiyat eklendi: $baslangic_tarihi - $bitis_tarihi");
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
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Özel Fiyatlar</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecialPriceModal">
                    <i class="fas fa-plus"></i> Yeni Özel Fiyat
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($ozel_fiyatlar)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                            <h5>Henüz özel fiyat tanımlanmamış</h5>
                            <p class="text-muted">İlk özel fiyatınızı eklemek için "Yeni Özel Fiyat" butonuna tıklayın.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Oda Tipi</th>
                                        <th>Tarih Aralığı</th>
                                        <th>Fiyat Türü</th>
                                        <th>Aktif Günler</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ozel_fiyatlar as $fiyat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fiyat['oda_tipi_adi']); ?></td>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($fiyat['baslangic_tarihi'])); ?> - 
                                                <?php echo date('d.m.Y', strtotime($fiyat['bitis_tarihi'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $fiyat['fiyat_tipi'] === 'temel_fiyat' ? 'info' : 'secondary'; ?>">
                                                    <?php echo $fiyat['fiyat_tipi'] === 'temel_fiyat' ? 'Temel Fiyat' : 'Sabit Fiyat'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
                                                $gun_isimleri = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
                                                $aktif_gunler = [];
                                                
                                                foreach ($gunler as $i => $gun) {
                                                    if ($fiyat[$gun . '_aktif']) {
                                                        $fiyat_degeri = $fiyat[$gun . '_temel_fiyat'] ?: $fiyat[$gun . '_sabit_fiyat'];
                                                        $aktif_gunler[] = $gun_isimleri[$i] . ' (' . formatCurrency($fiyat_degeri) . ')';
                                                    }
                                                }
                                                echo implode(', ', $aktif_gunler);
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $fiyat['aktif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $fiyat['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteSpecialPrice(<?php echo $fiyat['id']; ?>, '<?php echo htmlspecialchars($fiyat['oda_tipi_adi']); ?>')">
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Özel Fiyat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="oda_tipi_id" class="form-label">Oda Tipi *</label>
                                <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required onchange="updateRoomTypeInfo()">
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                        <option value="<?php echo $oda_tipi['id']; ?>" 
                                                data-base-price="<?php echo $oda_tipi['base_price']; ?>"
                                                data-pricing-system="<?php echo $oda_tipi['fiyatlama_sistemi']; ?>">
                                            <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="room-type-info" class="form-text text-info mt-2" style="display: none;">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="room-price-text">Mevcut temel fiyat: 0 TL</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fiyat_tipi" class="form-label">Fiyat Türü *</label>
                                <select class="form-select" id="fiyat_tipi" name="fiyat_tipi" required onchange="updatePriceLabels()">
                                    <option value="">Seçiniz</option>
                                    <option value="temel_fiyat">Temel Fiyat (Kişi başına)</option>
                                    <option value="sabit_fiyat">Sabit Fiyat (Oda başına)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi *</label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Günlük Fiyatlandırma *</label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <?php 
                                    $gunler = [
                                        'pazartesi' => 'Pazartesi',
                                        'sali' => 'Salı', 
                                        'carsamba' => 'Çarşamba',
                                        'persembe' => 'Perşembe',
                                        'cuma' => 'Cuma',
                                        'cumartesi' => 'Cumartesi',
                                        'pazar' => 'Pazar'
                                    ];
                                    
                                    foreach ($gunler as $gun_key => $gun_adi): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="<?php echo $gun_key; ?>_aktif" 
                                                       name="<?php echo $gun_key; ?>_aktif" 
                                                       onchange="toggleDayPrice('<?php echo $gun_key; ?>')">
                                                <label class="form-check-label fw-bold" for="<?php echo $gun_key; ?>_aktif">
                                                    <?php echo $gun_adi; ?>
                                                </label>
                                            </div>
                                            <div class="mt-2" id="<?php echo $gun_key; ?>_price_field" style="display: none;">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control" 
                                                           id="<?php echo $gun_key; ?>_fiyat" 
                                                           name="<?php echo $gun_key; ?>_fiyat" 
                                                           min="0" step="0.01" placeholder="0.00">
                                                    <span class="input-group-text">₺</span>
                                                </div>
                                                <small class="form-text text-muted" id="<?php echo $gun_key; ?>_help">
                                                    Fiyat türü seçin
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="1">Aktif</option>
                                    <option value="0">Pasif</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <input type="text" class="form-control" id="aciklama" name="aciklama" placeholder="Özel fiyat açıklaması...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Özel Fiyat Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Silme Onay Modal -->
<div class="modal fade" id="deleteSpecialPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Özel Fiyat Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bu özel fiyatı silmek istediğinizden emin misiniz?</p>
                <p><strong id="deleteSpecialPriceName"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id" id="deleteSpecialPriceId">
                    <button type="submit" class="btn btn-danger">Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateRoomTypeInfo() {
        const select = document.getElementById('oda_tipi_id');
        const selectedOption = select.options[select.selectedIndex];
        const roomTypeInfo = document.getElementById('room-type-info');
        const roomPriceText = document.getElementById('room-price-text');
        
        if (selectedOption.value) {
            const basePrice = selectedOption.getAttribute('data-base-price') || '0';
            const pricingSystem = selectedOption.getAttribute('data-pricing-system') || 'temel_fiyat';
            
            roomPriceText.textContent = `Mevcut temel fiyat: ${basePrice} TL (${pricingSystem === 'temel_fiyat' ? 'Kişi başına' : 'Oda başına'})`;
            roomTypeInfo.style.display = 'block';
        } else {
            roomTypeInfo.style.display = 'none';
        }
    }
    
    function updatePriceLabels() {
        const fiyatTipi = document.getElementById('fiyat_tipi').value;
        const gunler = ['pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi', 'pazar'];
        
        gunler.forEach(gun => {
            const helpText = document.getElementById(gun + '_help');
            if (fiyatTipi === 'temel_fiyat') {
                helpText.textContent = 'Kişi başına fiyat';
            } else if (fiyatTipi === 'sabit_fiyat') {
                helpText.textContent = 'Oda başına sabit fiyat';
            } else {
                helpText.textContent = 'Fiyat türü seçin';
            }
        });
    }
    
    function toggleDayPrice(gun) {
        const checkbox = document.getElementById(gun + '_aktif');
        const priceField = document.getElementById(gun + '_price_field');
        const priceInput = document.getElementById(gun + '_fiyat');
        
        if (checkbox.checked) {
            priceField.style.display = 'block';
            priceInput.required = true;
        } else {
            priceField.style.display = 'none';
            priceInput.required = false;
            priceInput.value = '';
        }
    }
    
    function deleteSpecialPrice(id, name) {
        document.getElementById('deleteSpecialPriceId').value = id;
        document.getElementById('deleteSpecialPriceName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteSpecialPriceModal')).show();
    }
</script>

</body>
</html>
<parameter name="rewrite">false