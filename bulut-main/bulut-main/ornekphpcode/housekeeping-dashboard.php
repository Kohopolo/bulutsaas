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
error_log("Housekeeping Dashboard - Kullanıcı ID: " . ($_SESSION['user_id'] ?? 'NULL'));
error_log("Housekeeping Dashboard - User Role: " . ($_SESSION['user_role'] ?? 'NULL'));
error_log("Housekeeping Dashboard - Yetki kontrolü başlıyor");

// Debug: PDO bağlantısını kontrol et
if (!isset($GLOBALS['pdo'])) {
    error_log("Housekeeping Dashboard - PDO bağlantısı yok!");
    die("PDO bağlantısı bulunamadı!");
}

if (!hasDetailedPermission('housekeeping_dashboard')) {
    error_log("Housekeeping Dashboard - Yetki yok, yönlendiriliyor");
    $_SESSION['error_message'] = 'Housekeeping dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

error_log("Housekeeping Dashboard - Yetki kontrolü başarılı");

// Bugünkü tarih
$bugun = date('Y-m-d');
$bugun_tarih = date('d.m.Y');

error_log("Housekeeping Dashboard - Tarih hesaplandı: $bugun");

// İstatistikler
$stats = [];

error_log("Housekeeping Dashboard - İstatistik hesaplama başlıyor");

// Bugünkü temizlik görevleri
$bugun_temizlik_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM temizlik_kayitlari 
    WHERE DATE(temizlik_tarihi) = ?
", [$bugun]);
$stats['bugun_temizlik'] = $bugun_temizlik_result['toplam'] ?? 0;

error_log("Housekeeping Dashboard - Bugünkü temizlik: " . $stats['bugun_temizlik']);

// Devam eden temizlikler
error_log("Housekeeping Dashboard - Devam eden temizlikler sorgusu başlıyor");
$devam_eden_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM temizlik_kayitlari 
    WHERE durum = 'devam_ediyor'
");
$stats['devam_eden'] = $devam_eden_result['toplam'] ?? 0;
error_log("Housekeeping Dashboard - Devam eden temizlikler: " . $stats['devam_eden']);

// Onay bekleyen temizlikler
$onay_bekleyen_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM temizlik_kayitlari 
    WHERE durum = 'onay_bekliyor'
");
$stats['onay_bekleyen'] = $onay_bekleyen_result['toplam'] ?? 0;

// Tamamlanan temizlikler (bugün)
$tamamlanan_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM temizlik_kayitlari 
    WHERE durum = 'onaylandi' AND DATE(temizlik_tarihi) = ?
", [$bugun]);
$stats['tamamlanan'] = $tamamlanan_result['toplam'] ?? 0;

// Aktif housekeeper sayısı
$aktif_housekeeper_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM kullanicilar 
    WHERE rol = 'housekeeper' AND durum = 'aktif' AND aktif = 1
");
$stats['aktif_housekeeper'] = $aktif_housekeeper_result['toplam'] ?? 0;

// Bugünkü temizlik görevleri
$bugun_gorevler = fetchAll("
    SELECT tk.*, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, k.ad as housekeeper_adi, k.soyad as housekeeper_soyadi
    FROM temizlik_kayitlari tk
    LEFT JOIN oda_numaralari ON tk.oda_id = oda_numaralari.id
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k ON tk.housekeeper_id = k.id
    WHERE DATE(tk.temizlik_tarihi) = ?
    ORDER BY tk.baslama_saati ASC
", [$bugun]);

// Temizlik türleri
$temizlik_turleri = [
    'genel_temizlik' => 'Genel Temizlik',
    'cikis_temizligi' => 'Çıkış Temizliği',
    'bakim_temizligi' => 'Bakım Temizliği',
    'derin_temizlik' => 'Derin Temizlik'
];

// Durum renkleri
$durum_renkleri = [
    'devam_ediyor' => 'warning',
    'tamamlandi' => 'info',
    'onay_bekliyor' => 'primary',
    'onaylandi' => 'success',
    'reddedildi' => 'danger'
];

// Durum metinleri
$durum_metinleri = [
    'devam_ediyor' => 'Devam Ediyor',
    'tamamlandi' => 'Tamamlandı',
    'onay_bekliyor' => 'Onay Bekliyor',
    'onaylandi' => 'Onaylandı',
    'reddedildi' => 'Reddedildi'
];

error_log("Housekeeping Dashboard - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housekeeping Dashboard - Otel Yönetim Sistemi</title>
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
                    <h1 class="h2"><i class="fas fa-broom me-2"></i>Housekeeping Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar me-1"></i><?php echo $bugun_tarih; ?>
                            </button>
                            <a href="qr-kod-yonetimi.php" class="btn btn-sm btn-info">
                                <i class="fas fa-qrcode me-1"></i>QR Kod Yönetimi
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
                                            Bugünkü Görevler</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['bugun_temizlik']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
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
                                            Devam Eden</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['devam_eden']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Onay Bekleyen</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['onay_bekleyen']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
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
                                            Tamamlanan</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['tamamlanan']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bugünkü Görevler -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-day me-2"></i>Bugünkü Temizlik Görevleri
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bugun_gorevler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-broom fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bugün için temizlik görevi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Oda</th>
                                            <th>Temizlik Türü</th>
                                            <th>Housekeeper</th>
                                            <th>Başlama Saati</th>
                                            <th>Bitiş Saati</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bugun_gorevler as $gorev): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($gorev['oda_numarasi']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($gorev['oda_tipi']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $temizlik_turleri[$gorev['temizlik_turu']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($gorev['housekeeper_adi'] . ' ' . $gorev['housekeeper_soyadi']); ?>
                                            </td>
                                            <td><?php echo date('H:i', strtotime($gorev['baslama_saati'])); ?></td>
                                            <td>
                                                <?php echo $gorev['bitis_saati'] ? date('H:i', strtotime($gorev['bitis_saati'])) : '-'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $durum_renkleri[$gorev['durum']]; ?>">
                                                    <?php echo $durum_metinleri[$gorev['durum']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="housekeeping-gorev-detay.php?id=<?php echo $gorev['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (hasDetailedPermission('housekeeping_gorev_duzenle')): ?>
                                                    <a href="housekeeping-gorev-duzenle.php?id=<?php echo $gorev['id']; ?>" 
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
                                    <?php if (hasDetailedPermission('housekeeping_gorev_olustur')): ?>
                                    <a href="housekeeping-gorev-olustur.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Yeni Temizlik Görevi
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('housekeeping_oda_temizlik')): ?>
                                    <a href="housekeeping-oda-temizlik.php" class="btn btn-success">
                                        <i class="fas fa-broom me-2"></i>Oda Temizlik Yönetimi
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('housekeeping_kalite_kontrol')): ?>
                                    <a href="housekeeping-kalite-kontrol.php" class="btn btn-warning">
                                        <i class="fas fa-check-circle me-2"></i>Kalite Kontrol
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasDetailedPermission('housekeeping_raporlar')): ?>
                                    <a href="housekeeping-raporlar.php" class="btn btn-info">
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
                                        <i class="fas fa-users text-primary me-2"></i>
                                        <strong>Aktif Housekeeper:</strong> <?php echo $stats['aktif_housekeeper']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-calendar text-success me-2"></i>
                                        <strong>Bugün:</strong> <?php echo $bugun_tarih; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        <strong>Saat:</strong> <span id="current-time"></span>
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
