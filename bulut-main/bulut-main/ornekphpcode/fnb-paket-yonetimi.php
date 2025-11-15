<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('fnb_paket_yonetimi')) {
    $_SESSION['error_message'] = 'F&B paket yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'paket_ekle') {
            $paket_adi = sanitizeString($_POST['paket_adi']);
            $paket_aciklamasi = sanitizeString($_POST['paket_aciklamasi']);
            $paket_turu = sanitizeString($_POST['paket_turu']);
            $fiyat = floatval($_POST['fiyat']);
            $maliyet = floatval($_POST['maliyet']);
            $gecerlilik_suresi = intval($_POST['gecerlilik_suresi']);
            
            $sql = "INSERT INTO fnb_paketleri (paket_adi, paket_aciklamasi, paket_turu, fiyat, maliyet, gecerlilik_suresi) VALUES (?, ?, ?, ?, ?, ?)";
            if (executeQuery($sql, [$paket_adi, $paket_aciklamasi, $paket_turu, $fiyat, $maliyet, $gecerlilik_suresi])) {
                $success_message = 'Paket başarıyla eklendi.';
            } else {
                $error_message = 'Paket eklenirken hata oluştu.';
            }
        }
        
        if ($action == 'paket_guncelle') {
            $id = intval($_POST['id']);
            $paket_adi = sanitizeString($_POST['paket_adi']);
            $paket_aciklamasi = sanitizeString($_POST['paket_aciklamasi']);
            $paket_turu = sanitizeString($_POST['paket_turu']);
            $fiyat = floatval($_POST['fiyat']);
            $maliyet = floatval($_POST['maliyet']);
            $gecerlilik_suresi = intval($_POST['gecerlilik_suresi']);
            
            $sql = "UPDATE fnb_paketleri SET paket_adi = ?, paket_aciklamasi = ?, paket_turu = ?, fiyat = ?, maliyet = ?, gecerlilik_suresi = ? WHERE id = ?";
            if (executeQuery($sql, [$paket_adi, $paket_aciklamasi, $paket_turu, $fiyat, $maliyet, $gecerlilik_suresi, $id])) {
                $success_message = 'Paket başarıyla güncellendi.';
            } else {
                $error_message = 'Paket güncellenirken hata oluştu.';
            }
        }
        
        if ($action == 'paket_sil') {
            $id = intval($_POST['id']);
            
            $sql = "UPDATE fnb_paketleri SET aktif = 0 WHERE id = ?";
            if (executeQuery($sql, [$id])) {
                $success_message = 'Paket başarıyla silindi.';
            } else {
                $error_message = 'Paket silinirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Paketleri getir
$paketler = fetchAll("
    SELECT * FROM fnb_paketleri
    WHERE aktif = 1
    ORDER BY paket_turu, paket_adi
");

// Paket türleri
$paket_turleri = [
    'kahvalti' => 'Kahvaltı',
    'oglen_yemegi' => 'Öğlen Yemeği',
    'aksam_yemegi' => 'Akşam Yemeği',
    'snack' => 'Snack',
    'icecek_paketi' => 'İçecek Paketi',
    'ozel_paket' => 'Özel Paket'
];

error_log("F&B Paket Yönetimi - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Paket Yönetimi - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-box me-2"></i>F&B Paket Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Paket Ekleme -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>Yeni Paket Ekle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenInput(); ?>
                                    <input type="hidden" name="action" value="paket_ekle">
                                    
                                    <div class="mb-3">
                                        <label for="paket_adi" class="form-label">Paket Adı</label>
                                        <input type="text" class="form-control" id="paket_adi" name="paket_adi" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="paket_aciklamasi" class="form-label">Paket Açıklaması</label>
                                        <textarea class="form-control" id="paket_aciklamasi" name="paket_aciklamasi" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="paket_turu" class="form-label">Paket Türü</label>
                                        <select class="form-select" id="paket_turu" name="paket_turu" required>
                                            <option value="">Paket Türü Seçin</option>
                                            <?php foreach ($paket_turleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fiyat" class="form-label">Fiyat (₺)</label>
                                            <input type="number" class="form-control" id="fiyat" name="fiyat" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="maliyet" class="form-label">Maliyet (₺)</label>
                                            <input type="number" class="form-control" id="maliyet" name="maliyet" step="0.01" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="gecerlilik_suresi" class="form-label">Geçerlilik Süresi (Saat)</label>
                                        <input type="number" class="form-control" id="gecerlilik_suresi" name="gecerlilik_suresi" min="0" value="24">
                                        <small class="form-text text-muted">Paketin geçerli olacağı süre (saat cinsinden)</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Paket Ekle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Paket Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Paket Türleri:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-sun text-warning me-2"></i><strong>Kahvaltı:</strong> Sabah yemeği paketi</li>
                                    <li><i class="fas fa-sun text-info me-2"></i><strong>Öğlen Yemeği:</strong> Öğle yemeği paketi</li>
                                    <li><i class="fas fa-moon text-primary me-2"></i><strong>Akşam Yemeği:</strong> Akşam yemeği paketi</li>
                                    <li><i class="fas fa-cookie-bite text-success me-2"></i><strong>Snack:</strong> Atıştırmalık paketi</li>
                                    <li><i class="fas fa-wine-glass text-danger me-2"></i><strong>İçecek Paketi:</strong> İçecek paketi</li>
                                    <li><i class="fas fa-star text-warning me-2"></i><strong>Özel Paket:</strong> Özel durumlar için</li>
                                </ul>
                                
                                <hr>
                                
                                <h6 class="text-primary">Paket Özellikleri:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Rezervasyon ile entegre</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Otomatik fiyat hesaplama</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Maliyet takibi</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Geçerlilik süresi kontrolü</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paket Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Mevcut Paketler
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Paket Adı</th>
                                        <th>Paket Türü</th>
                                        <th>Açıklama</th>
                                        <th>Fiyat</th>
                                        <th>Maliyet</th>
                                        <th>Kar</th>
                                        <th>Geçerlilik</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paketler as $paket): ?>
                                    <?php $kar = $paket['fiyat'] - $paket['maliyet']; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($paket['paket_adi']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $paket['paket_turu'] == 'kahvalti' ? 'warning' : ($paket['paket_turu'] == 'oglen_yemegi' ? 'info' : ($paket['paket_turu'] == 'aksam_yemegi' ? 'primary' : ($paket['paket_turu'] == 'snack' ? 'success' : ($paket['paket_turu'] == 'icecek_paketi' ? 'danger' : 'secondary')))); ?>">
                                                <?php echo $paket_turleri[$paket['paket_turu']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $paket['paket_aciklamasi'] ? htmlspecialchars($paket['paket_aciklamasi']) : '-'; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($paket['fiyat'], 2); ?>₺</strong>
                                        </td>
                                        <td><?php echo number_format($paket['maliyet'], 2); ?>₺</td>
                                        <td>
                                            <span class="text-<?php echo $kar >= 0 ? 'success' : 'danger'; ?>">
                                                <strong><?php echo number_format($kar, 2); ?>₺</strong>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $paket['gecerlilik_suresi']; ?> saat
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info" title="İçerik">
                                                    <i class="fas fa-list"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Sil">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
