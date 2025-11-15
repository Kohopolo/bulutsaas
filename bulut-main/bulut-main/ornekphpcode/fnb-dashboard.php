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

// Debug: Yetki kontrolü
error_log("F&B Dashboard - Kullanıcı ID: " . $_SESSION['user_id']);
error_log("F&B Dashboard - Yetki kontrolü başlıyor");

if (!hasDetailedPermission('fnb_dashboard')) {
    error_log("F&B Dashboard - Yetki yok, yönlendiriliyor");
    $_SESSION['error_message'] = 'F&B dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

error_log("F&B Dashboard - Yetki kontrolü başarılı");

// Bugünkü tarih
$bugun = date('Y-m-d');
$bugun_tarih = date('d.m.Y');

// İstatistikler
$stats = [];

error_log("F&B Dashboard - İstatistik hesaplama başlıyor");

// Bugünkü siparişler
error_log("F&B Dashboard - Bugünkü siparişler sorgusu başlıyor");
$bugun_siparisler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) = ?
", [$bugun]);
$stats['bugun_siparisler'] = $bugun_siparisler_result['toplam'] ?? 0;
error_log("F&B Dashboard - Bugünkü siparişler: " . $stats['bugun_siparisler']);

// Aktif siparişler
$aktif_siparisler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM fnb_siparisler 
    WHERE siparis_durumu IN ('alindi', 'hazirlaniyor', 'hazir')
");
$stats['aktif_siparisler'] = $aktif_siparisler_result['toplam'] ?? 0;

// Bugünkü ciro
$bugun_ciro_result = fetchOne("
    SELECT COALESCE(SUM(toplam_tutar), 0) as toplam 
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) = ? AND siparis_durumu != 'iptal'
", [$bugun]);
$stats['bugun_ciro'] = $bugun_ciro_result['toplam'] ?? 0;

// Stok uyarıları
$stok_uyarilari_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM fnb_stok 
    WHERE mevcut_miktar <= minimum_miktar AND aktif = 1
");
$stats['stok_uyarilari'] = $stok_uyarilari_result['toplam'] ?? 0;

// Departman bazında siparişler
error_log("F&B Dashboard - Departman siparişleri sorgusu başlıyor");
$departman_siparisler = fetchAll("
    SELECT departman, COUNT(*) as siparis_sayisi, SUM(toplam_tutar) as toplam_tutar
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) = ? AND siparis_durumu != 'iptal'
    GROUP BY departman
", [$bugun]);

// Son siparişler
$son_siparisler = fetchAll("
    SELECT fs.*, oda_numaralari.oda_numarasi, m.ad as musteri_adi, m.soyad as musteri_soyadi,
           k1.ad as siparis_alan_adi, k1.soyad as siparis_alan_soyadi
    FROM fnb_siparisler fs
    LEFT JOIN oda_numaralari ON fs.oda_id = oda_numaralari.id
    LEFT JOIN musteriler m ON fs.musteri_id = m.id
    LEFT JOIN kullanicilar k1 ON fs.siparis_alan_id = k1.id
    WHERE DATE(fs.siparis_tarihi) = ?
    ORDER BY fs.siparis_tarihi DESC
    LIMIT 10
", [$bugun]);

// Sipariş durumları
$siparis_durumlari = [
    'alindi' => 'Alındı',
    'hazirlaniyor' => 'Hazırlanıyor',
    'hazir' => 'Hazır',
    'servis_edildi' => 'Servis Edildi',
    'iptal' => 'İptal'
];

// Durum renkleri
$durum_renkleri = [
    'alindi' => 'primary',
    'hazirlaniyor' => 'warning',
    'hazir' => 'info',
    'servis_edildi' => 'success',
    'iptal' => 'danger'
];

// Departman renkleri
$departman_renkleri = [
    'mutfak' => 'danger',
    'restoran' => 'success',
    'bar' => 'info',
    'pastane' => 'warning'
];

// Departman metinleri
$departman_metinleri = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane'
];

error_log("F&B Dashboard - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Dashboard - Otel Yönetim Sistemi</title>
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
                    <h1 class="h2"><i class="fas fa-utensils me-2"></i>F&B Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar me-1"></i><?php echo $bugun_tarih; ?>
                            </button>
                            <a href="qr-menu-yonetimi.php" class="btn btn-sm btn-info">
                                <i class="fas fa-qrcode me-1"></i>QR Menü Yönetimi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Bugünkü Siparişler</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['bugun_siparisler']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Aktif Siparişler</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['aktif_siparisler']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Bugünkü Ciro</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['bugun_ciro'], 2); ?>₺</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Stok Uyarıları</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['stok_uyarilari']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Departman Bazında Siparişler -->
                <div class="row mb-4">
                    <?php foreach ($departman_siparisler as $departman): ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-left-<?php echo $departman_renkleri[$departman['departman']]; ?> shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-<?php echo $departman_renkleri[$departman['departman']]; ?> text-uppercase mb-1">
                                            <?php echo $departman_metinleri[$departman['departman']]; ?></div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $departman['siparis_sayisi']; ?> Sipariş
                                        </div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-600">
                                            <?php echo number_format($departman['toplam_tutar'], 2); ?>₺
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-<?php echo $departman['departman'] == 'mutfak' ? 'fire' : ($departman['departman'] == 'restoran' ? 'utensils' : ($departman['departman'] == 'bar' ? 'wine-glass' : 'birthday-cake')); ?> fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Son Siparişler -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Bugünkü Siparişler
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($son_siparisler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bugün için sipariş bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Oda</th>
                                            <th>Müşteri</th>
                                            <th>Departman</th>
                                            <th>Tutar</th>
                                            <th>Durum</th>
                                            <th>Sipariş Alan</th>
                                            <th>Saat</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($son_siparisler as $siparis): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($siparis['siparis_no']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $siparis['oda_numarasi'] ? htmlspecialchars($siparis['oda_numarasi']) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $siparis['musteri_adi'] ? htmlspecialchars($siparis['musteri_adi'] . ' ' . $siparis['musteri_soyadi']) : 'Misafir'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $departman_renkleri[$siparis['departman']]; ?>">
                                                    <?php echo $departman_metinleri[$siparis['departman']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($siparis['toplam_tutar'], 2); ?>₺</strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $durum_renkleri[$siparis['siparis_durumu']]; ?>">
                                                    <?php echo $siparis_durumlari[$siparis['siparis_durumu']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $siparis['siparis_alan_adi'] ? htmlspecialchars($siparis['siparis_alan_adi'] . ' ' . $siparis['siparis_alan_soyadi']) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo date('H:i', strtotime($siparis['siparis_tarihi'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="fnb-siparis-detay.php?id=<?php echo $siparis['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (hasDetailedPermission('fnb_siparis_yonetimi')): ?>
                                                    <a href="fnb-siparis-duzenle.php?id=<?php echo $siparis['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-plus me-2"></i>Hızlı İşlemler
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if (hasDetailedPermission('fnb_siparis_al')): ?>
                                    <a href="fnb-siparis-al.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Yeni Sipariş Al
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('fnb_menu_yonetimi')): ?>
                                    <a href="fnb-menu-yonetimi.php" class="btn btn-success">
                                        <i class="fas fa-utensils me-2"></i>Menü Yönetimi
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('fnb_stok_yonetimi')): ?>
                                    <a href="fnb-stok-yonetimi.php" class="btn btn-warning">
                                        <i class="fas fa-boxes me-2"></i>Stok Yönetimi
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('fnb_raporlar')): ?>
                                    <a href="fnb-raporlar.php" class="btn btn-info">
                                        <i class="fas fa-chart-bar me-2"></i>Raporlar
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-info-circle me-2"></i>Sistem Bilgileri
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-calendar text-primary me-2"></i>
                                        <strong>Bugün:</strong> <?php echo $bugun_tarih; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        <strong>Saat:</strong> <span id="current-time"></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-user text-success me-2"></i>
                                        <strong>Kullanıcı:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Saat güncelleme
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('tr-TR');
            document.getElementById('current-time').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    </script>
</body>
</html>
