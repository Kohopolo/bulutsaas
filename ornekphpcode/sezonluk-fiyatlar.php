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
requireDetailedPermission('sezonluk_fiyatlar_yonetimi', 'Sezonluk fiyatlar yönetimi yetkiniz bulunmamaktadır.');

// CSRF token oluştur
$csrf_token = generateCSRFToken();

// Başarı/hata mesajları
$success_message = '';
$error_message = '';

// Sezonluk fiyat ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $oda_tipi_id = (int)$_POST['oda_tipi_id'];
        $sezon_adi = sanitizeString($_POST['sezon_adi']);
        $baslangic_tarihi = sanitizeString($_POST['baslangic_tarihi']);
        $bitis_tarihi = sanitizeString($_POST['bitis_tarihi']);
        $fiyat_tipi = sanitizeString($_POST['fiyat_tipi']); // 'temel' or 'sabit'
        $sezon_fiyati = (float)$_POST['sezon_fiyati'];
        $aciklama = sanitizeString($_POST['aciklama']);
        $aktif = ($_POST['durum'] === 'aktif') ? 1 : 0;

        if (empty($sezon_adi) || empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            $error_message = 'Sezon adı, başlangıç ve bitiş tarihi zorunludur.';
        } elseif (empty($sezon_fiyati) || $sezon_fiyati <= 0) {
            $error_message = 'Geçerli bir sezon fiyatı belirtilmelidir.';
        } else {
            // Fiyat tipine göre doğru kolona kaydet
            $temel_fiyat = ($fiyat_tipi === 'temel_fiyat') ? $sezon_fiyati : null;
            $sabit_fiyat = ($fiyat_tipi === 'sabit_fiyat') ? $sezon_fiyati : null;

            $stmt = $pdo->prepare("INSERT INTO sezonluk_fiyatlar (oda_tipi_id, baslangic_tarihi, bitis_tarihi, temel_fiyat, sabit_fiyat, fiyat_tipi, aciklama, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$oda_tipi_id, $baslangic_tarihi, $bitis_tarihi, $temel_fiyat, $sabit_fiyat, $fiyat_tipi, $sezon_adi, $aktif])) {
                $success_message = 'Sezonluk fiyat başarıyla eklendi.';
                logSecurityEvent(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0, 'sezonluk_fiyat_eklendi', "Sezonluk fiyat eklendi: $sezon_adi");
            } else {
                $error_message = 'Sezonluk fiyat eklenirken bir hata oluştu.';
            }
        }
    }
}

// Sezonluk fiyat silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM sezonluk_fiyatlar WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success_message = 'Sezonluk fiyat başarıyla silindi.';
            logSecurityEvent(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0, 'sezonluk_fiyat_silindi', "Sezonluk fiyat silindi: ID $id");
        } else {
            $error_message = 'Sezonluk fiyat silinirken bir hata oluştu.';
        }
    }
}

// Sezonluk fiyat güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $id = (int)$_POST['id'];
        $oda_tipi_id = (int)$_POST['oda_tipi_id'];
        $sezon_adi = sanitizeString($_POST['sezon_adi']);
        $baslangic_tarihi = sanitizeString($_POST['baslangic_tarihi']);
        $bitis_tarihi = sanitizeString($_POST['bitis_tarihi']);
        $fiyat_tipi = sanitizeString($_POST['fiyat_tipi']); // 'temel' or 'sabit'
        $sezon_fiyati = (float)$_POST['sezon_fiyati'];
        $aciklama = sanitizeString($_POST['aciklama']);
        $aktif = ($_POST['durum'] === 'aktif') ? 1 : 0;

        if (empty($sezon_adi) || empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            $error_message = 'Sezon adı, başlangıç ve bitiş tarihi zorunludur.';
        } elseif (empty($sezon_fiyati) || $sezon_fiyati <= 0) {
            $error_message = 'Geçerli bir sezon fiyatı belirtilmelidir.';
        } else {
            // Fiyat tipine göre doğru kolona kaydet
            $temel_fiyat = ($fiyat_tipi === 'temel_fiyat') ? $sezon_fiyati : null;
            $sabit_fiyat = ($fiyat_tipi === 'sabit_fiyat') ? $sezon_fiyati : null;

            $stmt = $pdo->prepare("UPDATE sezonluk_fiyatlar SET oda_tipi_id = ?, baslangic_tarihi = ?, bitis_tarihi = ?, temel_fiyat = ?, sabit_fiyat = ?, fiyat_tipi = ?, aciklama = ?, aktif = ? WHERE id = ?");
            
            if ($stmt->execute([$oda_tipi_id, $baslangic_tarihi, $bitis_tarihi, $temel_fiyat, $sabit_fiyat, $fiyat_tipi, $sezon_adi, $aktif, $id])) {
                $success_message = 'Sezonluk fiyat başarıyla güncellendi.';
                logSecurityEvent(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0, 'sezonluk_fiyat_guncellendi', "Sezonluk fiyat güncellendi: $sezon_adi");
            } else {
                $error_message = 'Sezonluk fiyat güncellenirken bir hata oluştu.';
            }
        }
    }
}

// Sezonluk fiyatları listele
try {
    $stmt = $pdo->prepare("
        SELECT sf.*, ot.oda_tipi_adi as oda_tipi_adi 
        FROM sezonluk_fiyatlar sf 
        LEFT JOIN oda_tipleri ot ON sf.oda_tipi_id = ot.id 
        ORDER BY sf.baslangic_tarihi DESC
    ");
    $stmt->execute();
    $sezonluk_fiyatlar = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tablo yoksa boş array
    $sezonluk_fiyatlar = [];
}

// Oda tiplerini getir (temel fiyat ve fiyatlama sistemi ile birlikte)
$stmt = $pdo->prepare("SELECT id, oda_tipi_adi, base_price, fiyatlama_sistemi FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
$stmt->execute();
$oda_tipleri = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sezonluk Fiyatlar - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sezonluk Fiyatlar</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSeasonModal">
                        <i class="fas fa-plus"></i> Yeni Sezon
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
                        <?php if (empty($sezonluk_fiyatlar)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <h5>Henüz sezonluk fiyat tanımlanmamış</h5>
                                <p class="text-muted">İlk sezonluk fiyatınızı eklemek için "Yeni Sezon" butonuna tıklayın.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sezon Adı</th>
                                            <th>Oda Tipi</th>
                                            <th>Tarih Aralığı</th>
                                            <th>Fiyat Türü ve Tutarı</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sezonluk_fiyatlar as $sezon): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sezon['aciklama']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($sezon['oda_tipi_adi']); ?></td>
                                                <td>
                                                    <?php echo date('d.m.Y', strtotime($sezon['baslangic_tarihi'])); ?> - 
                                                    <?php echo date('d.m.Y', strtotime($sezon['bitis_tarihi'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($sezon['fiyat_tipi'] === 'temel_fiyat' && $sezon['temel_fiyat']): ?>
                                                        <span class="badge bg-primary">Temel: <?php echo number_format($sezon['temel_fiyat'], 2); ?> ₺</span>
                                                    <?php elseif ($sezon['fiyat_tipi'] === 'sabit_fiyat' && $sezon['sabit_fiyat']): ?>
                                                        <span class="badge bg-info">Sabit: <?php echo number_format($sezon['sabit_fiyat'], 2); ?> ₺</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Fiyat Belirtilmemiş</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $sezon['aktif'] == 1 ? 'success' : 'secondary'; ?>">
                                                        <?php echo $sezon['aktif'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSeason(<?php echo htmlspecialchars(json_encode($sezon)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSeason(<?php echo $sezon['id']; ?>, '<?php echo htmlspecialchars($sezon['aciklama']); ?>')">
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

    <!-- Sezon Ekleme Modal -->
    <div class="modal fade" id="addSeasonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Sezon Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sezon_adi" class="form-label">Sezon Adı *</label>
                                    <input type="text" class="form-control" id="sezon_adi" name="sezon_adi" required placeholder="Örn: Yaz Sezonu, Kış Sezonu">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="oda_tipi_id" class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required onchange="updatePriceInfo()">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?php echo $oda_tipi['id']; ?>" 
                                            data-base-price="<?php echo $oda_tipi['base_price'] ?? 100; ?>"
                                            data-pricing-system="<?php echo $oda_tipi['fiyatlama_sistemi'] ?? 'kisi_carpani'; ?>">
                                                <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="base-price-info" class="form-text text-info mt-2" style="display: none;">
                                        <i class="fas fa-info-circle"></i> 
                                        <span id="base-price-text">Temel Fiyat: 0 TL</span> | 
                                        <span id="average-price-text">Ortalama Fiyat: 0 TL</span>
                                    </div>
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fiyat_tipi" class="form-label">Fiyat Türü *</label>
                                    <select class="form-select" id="fiyat_tipi" name="fiyat_tipi" required onchange="togglePriceFields()">
                                        <option value="">Seçiniz</option>
                                        <option value="temel_fiyat">Temel Fiyat (Kişi Başına)</option>
                                        <option value="sabit_fiyat">Sabit Fiyat (Oda Başına)</option>
                                    </select>
                                    <div class="form-text">Temel fiyat: Kişi sayısına göre hesaplanır | Sabit fiyat: Oda başına sabit tutar</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3" id="price-input-field">
                                    <label for="sezon_fiyati" class="form-label">Sezon Fiyatı (₺) *</label>
                                    <input type="number" class="form-control" id="sezon_fiyati" name="sezon_fiyati" min="0" step="0.01" placeholder="Örn: 150.00" required>
                                    <div class="form-text" id="price-help-text">Bu sezon için uygulanacak fiyat</div>
                                    <div id="current-base-price" class="form-text text-info mt-1" style="display: none;">
                                        <i class="fas fa-info-circle"></i> <span id="current-price-text">Mevcut temel fiyat: 0 TL</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" placeholder="Sezon hakkında açıklama..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Sezon Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sezon Düzenleme Modal -->
    <div class="modal fade" id="editSeasonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sezon Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editSeasonForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sezon_adi" class="form-label">Sezon Adı *</label>
                                    <input type="text" class="form-control" id="edit_sezon_adi" name="sezon_adi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_oda_tipi_id" class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="edit_oda_tipi_id" name="oda_tipi_id" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?php echo $oda_tipi['id']; ?>"><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_baslangic_tarihi" class="form-label">Başlangıç Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_baslangic_tarihi" name="baslangic_tarihi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_bitis_tarihi" name="bitis_tarihi" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_fiyat_carpani" class="form-label">Fiyat Çarpanı</label>
                                    <input type="number" class="form-control" id="edit_fiyat_carpani" name="fiyat_carpani" min="0.1" max="10" step="0.1">
                                    <div class="form-text">Normal fiyatın kaç katı olacağını belirler</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sabit_fiyat" class="form-label">Sabit Fiyat (₺)</label>
                                    <input type="number" class="form-control" id="edit_sabit_fiyat" name="sabit_fiyat" min="0" step="0.01">
                                    <div class="form-text">Sabit fiyat belirlerseniz çarpan kullanılmaz</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_durum" class="form-label">Durum</label>
                            <select class="form-select" id="edit_durum" name="durum">
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="edit_aciklama" name="aciklama" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteSeasonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sezon Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu sezonu silmek istediğinizden emin misiniz?</p>
                    <p><strong id="deleteSeasonName"></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="deleteSeasonId">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Oda tipi seçildiğinde fiyat bilgilerini göster
        function updatePriceInfo() {
            const select = document.getElementById('oda_tipi_id');
            const selectedOption = select.options[select.selectedIndex];
            const basePriceInfo = document.getElementById('base-price-info');
            const basePriceText = document.getElementById('base-price-text');
            const currentBasePrice = document.getElementById('current-base-price');
            const currentPriceText = document.getElementById('current-price-text');
            
            if (selectedOption.value) {
                const basePrice = selectedOption.getAttribute('data-base-price') || '100';
                const pricingSystem = selectedOption.getAttribute('data-pricing-system') || 'temel_fiyat';
                
                basePriceText.textContent = `Temel Fiyat: ${basePrice} TL`;
                basePriceInfo.style.display = 'block';
                
                currentPriceText.textContent = `Mevcut temel fiyat: ${basePrice} TL`;
                currentBasePrice.style.display = 'block';
            } else {
                basePriceInfo.style.display = 'none';
                currentBasePrice.style.display = 'none';
            }
        }
        
        // Fiyat türü değiştiğinde yardım metnini güncelle
        function togglePriceFields() {
            const fiyatTipi = document.getElementById('fiyat_tipi').value;
            const priceHelpText = document.getElementById('price-help-text');
            
            if (fiyatTipi === 'temel_fiyat') {
                priceHelpText.textContent = 'Kişi başına uygulanacak temel fiyat (kişi sayısı ile çarpılır)';
            } else if (fiyatTipi === 'sabit_fiyat') {
                priceHelpText.textContent = 'Oda başına sabit fiyat (kişi sayısından bağımsız)';
            } else {
                priceHelpText.textContent = 'Bu sezon için uygulanacak fiyat';
            }
        }
        
        function editSeason(season) {
            document.getElementById('edit_id').value = season.id;
            document.getElementById('edit_sezon_adi').value = season.aciklama;
            document.getElementById('edit_oda_tipi_id').value = season.oda_tipi_id;
            document.getElementById('edit_baslangic_tarihi').value = season.baslangic_tarihi;
            document.getElementById('edit_bitis_tarihi').value = season.bitis_tarihi;
            document.getElementById('edit_fiyat_carpani').value = season.fiyat_carpani || '';
            document.getElementById('edit_sabit_fiyat').value = season.sabit_fiyat || '';
            document.getElementById('edit_aciklama').value = season.aciklama || '';
            document.getElementById('edit_durum').value = season.durum;
            
            new bootstrap.Modal(document.getElementById('editSeasonModal')).show();
        }

        function deleteSeason(id, name) {
            document.getElementById('deleteSeasonId').value = id;
            document.getElementById('deleteSeasonName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteSeasonModal')).show();
        }
     </script>
 </body>
 </html>