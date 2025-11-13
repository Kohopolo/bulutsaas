<?php
/**
 * Stok Yönetimi Dashboard
 * Otomatik stok takibi, sipariş oluşturma ve uyarı sistemi
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/inventory-automation.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('stok_yonetimi')) {
    $_SESSION['error_message'] = 'Stok yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'otomatik_siparis_olustur') {
            $inventory = new InventoryAutomation($pdo);
            $result = $inventory->createAutomaticOrders();
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'stok_uyarilari_kontrol') {
            $inventory = new InventoryAutomation($pdo);
            $result = $inventory->checkStockAlerts();
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İstatistikleri hesapla
$stats = [];

// Toplam ürün sayısı
$toplam_urun = fetchOne("SELECT COUNT(*) as toplam FROM stok_urunleri");
$stats['toplam_urun'] = $toplam_urun['toplam'] ?? 0;

// Minimum stok altındaki ürünler
$minimum_stok_alti = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM stok_urunleri 
    WHERE mevcut_stok <= minimum_stok AND durum = 'aktif'
");
$stats['minimum_stok_alti'] = $minimum_stok_alti['toplam'] ?? 0;

// Stok yok olan ürünler
$stok_yok = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM stok_urunleri 
    WHERE mevcut_stok <= 0 AND durum = 'aktif'
");
$stats['stok_yok'] = $stok_yok['toplam'] ?? 0;

// Toplam stok değeri
$toplam_stok_degeri = fetchOne("
    SELECT SUM(mevcut_stok * birim_fiyat) as toplam 
    FROM stok_urunleri 
    WHERE durum = 'aktif'
");
$stats['toplam_stok_degeri'] = $toplam_stok_degeri['toplam'] ?? 0;

// Aktif uyarı sayısı
$aktif_uyarilar = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM stok_uyarilari 
    WHERE durum = 'aktif'
");
$stats['aktif_uyarilar'] = $aktif_uyarilar['toplam'] ?? 0;

// Bekleyen otomatik siparişler
$bekleyen_siparisler = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM otomatik_siparisler 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_siparisler'] = $bekleyen_siparisler['toplam'] ?? 0;

// Son stok hareketleri
$son_hareketler = fetchAll("
    SELECT sh.*, su.urun_adi, su.urun_kodu, k.ad as kullanici_adi, k.soyad as kullanici_soyadi
    FROM stok_hareketleri sh
    LEFT JOIN stok_urunleri su ON sh.urun_id = su.id
    LEFT JOIN kullanicilar k ON sh.kullanici_id = k.id
    ORDER BY sh.hareket_tarihi DESC
    LIMIT 10
");

// Aktif uyarılar
$uyarilar = fetchAll("
    SELECT su.*, su.urun_adi, su.urun_kodu, su.mevcut_stok, su.minimum_stok, su.birim
    FROM stok_uyarilari su
    LEFT JOIN stok_urunleri su ON su.urun_id = su.id
    WHERE su.durum = 'aktif'
    ORDER BY su.oncelik DESC, su.olusturma_tarihi DESC
    LIMIT 10
");

// Stok kategorileri
$kategoriler = fetchAll("
    SELECT * FROM stok_kategorileri 
    WHERE durum = 'aktif' 
    ORDER BY sira_no, kategori_adi
");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .alert-item {
            border-left: 4px solid;
        }
        .alert-urgent {
            border-left-color: #dc3545;
        }
        .alert-high {
            border-left-color: #fd7e14;
        }
        .alert-normal {
            border-left-color: #ffc107;
        }
        .alert-low {
            border-left-color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Stok Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus"></i> Ürün Ekle
                            </button>
                            <button type="button" class="btn btn-success" onclick="createAutomaticOrders()">
                                <i class="fas fa-magic"></i> Otomatik Sipariş Oluştur
                            </button>
                            <button type="button" class="btn btn-warning" onclick="checkStockAlerts()">
                                <i class="fas fa-exclamation-triangle"></i> Uyarıları Kontrol Et
                            </button>
                        </div>
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

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-primary text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Ürün</h6>
                                        <h3><?php echo $stats['toplam_urun']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-warning text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Minimum Stok Altı</h6>
                                        <h3><?php echo $stats['minimum_stok_alti']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-danger text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Stok Yok</h6>
                                        <h3><?php echo $stats['stok_yok']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-success text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Stok Değeri</h6>
                                        <h3><?php echo number_format($stats['toplam_stok_degeri'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-info text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Aktif Uyarılar</h6>
                                        <h3><?php echo $stats['aktif_uyarilar']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bell fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-dark text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bekleyen Siparişler</h6>
                                        <h3><?php echo $stats['bekleyen_siparisler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Aktif Uyarılar -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Aktif Uyarılar
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($uyarilar)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>Hiç aktif uyarı bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($uyarilar as $uyari): ?>
                                    <div class="list-group-item alert-item alert-<?php echo $uyari['oncelik'] == 'acil' ? 'urgent' : ($uyari['oncelik'] == 'yuksek' ? 'high' : 'normal'); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($uyari['urun_adi']); ?></h6>
                                            <small class="text-muted"><?php echo formatTurkishDate($uyari['olusturma_tarihi'], 'd.m.Y H:i'); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($uyari['uyari_mesaji']); ?></p>
                                        <small class="text-muted"><?php echo ucfirst($uyari['oncelik']); ?> öncelik</small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Son Stok Hareketleri -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history me-2"></i>Son Stok Hareketleri
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_hareketler)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>Henüz stok hareketi bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Ürün</th>
                                                <th>Hareket</th>
                                                <th>Miktar</th>
                                                <th>Tarih</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_hareketler as $hareket): ?>
                                            <tr>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($hareket['urun_kodu']); ?></small><br>
                                                    <?php echo htmlspecialchars($hareket['urun_adi']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $hareket['hareket_tipi'] == 'giris' ? 'success' : ($hareket['hareket_tipi'] == 'cikis' ? 'danger' : 'info'); ?>">
                                                        <?php echo ucfirst($hareket['hareket_tipi']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($hareket['miktar'], 2); ?></td>
                                                <td>
                                                    <small><?php echo formatTurkishDate($hareket['hareket_tarihi'], 'd.m.Y H:i'); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stok Kategorileri -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tags me-2"></i>Stok Kategorileri
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($kategoriler as $kategori): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card border-primary">
                                            <div class="card-body text-center">
                                                <h5 class="card-title"><?php echo htmlspecialchars($kategori['kategori_adi']); ?></h5>
                                                <p class="card-text text-muted"><?php echo htmlspecialchars($kategori['kategori_kodu']); ?></p>
                                                <a href="stok-urun-listesi.php?kategori_id=<?php echo $kategori['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    Ürünleri Görüntüle
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Otomatik Sipariş Oluşturma Formu -->
    <form id="automaticOrderForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="otomatik_siparis_olustur">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    </form>

    <!-- Stok Uyarıları Kontrol Formu -->
    <form id="stockAlertForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="stok_uyarilari_kontrol">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createAutomaticOrders() {
            if (confirm('Otomatik siparişler oluşturulsun mu?')) {
                document.getElementById('automaticOrderForm').submit();
            }
        }

        function checkStockAlerts() {
            document.getElementById('stockAlertForm').submit();
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

