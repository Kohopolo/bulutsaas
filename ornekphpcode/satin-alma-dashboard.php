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
if (!hasDetailedPermission('satin_alma_dashboard')) {
    $_SESSION['error_message'] = 'Satın alma dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$bugun = date('Y-m-d');
$bu_ay = date('Y-m');

// İstatistikleri hesapla
$stats = [];

// Toplam talep sayısı
$toplam_talep_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM satin_alma_talepleri
");
$stats['toplam_talep'] = $toplam_talep_result['toplam'] ?? 0;

// Bu ayki talep sayısı
$bu_ay_talep_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM satin_alma_talepleri 
    WHERE DATE_FORMAT(talep_tarihi, '%Y-%m') = ?
", [$bu_ay]);
$stats['bu_ay_talep'] = $bu_ay_talep_result['toplam'] ?? 0;

// Bekleyen talepler
$bekleyen_talep_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM satin_alma_talepleri 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_talep'] = $bekleyen_talep_result['toplam'] ?? 0;

// Onaylanan talepler
$onaylanan_talep_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM satin_alma_talepleri 
    WHERE durum = 'onaylandi'
");
$stats['onaylanan_talep'] = $onaylanan_talep_result['toplam'] ?? 0;

// Toplam talep tutarı
$toplam_tutar_result = fetchOne("
    SELECT SUM(toplam_tutar) as toplam 
    FROM satin_alma_talepleri
");
$stats['toplam_tutar'] = $toplam_tutar_result['toplam'] ?? 0;

// Bu ayki talep tutarı
$bu_ay_tutar_result = fetchOne("
    SELECT SUM(toplam_tutar) as toplam 
    FROM satin_alma_talepleri 
    WHERE DATE_FORMAT(talep_tarihi, '%Y-%m') = ?
", [$bu_ay]);
$stats['bu_ay_tutar'] = $bu_ay_tutar_result['toplam'] ?? 0;

// Son talepler
$son_talepler = fetchAll("
    SELECT sat.*, k.ad as talep_eden_adi, k.soyad as talep_eden_soyadi
    FROM satin_alma_talepleri sat
    LEFT JOIN kullanicilar k ON sat.talep_eden_id = k.id
    ORDER BY sat.talep_tarihi DESC
    LIMIT 10
");

// Talep durumları
$talep_durumlari = [
    'beklemede' => 'Beklemede',
    'onaylandi' => 'Onaylandı',
    'reddedildi' => 'Reddedildi',
    'siparis_verildi' => 'Sipariş Verildi',
    'teslim_edildi' => 'Teslim Edildi'
];

// Acil durum seviyeleri
$acil_durum_seviyeleri = [
    'normal' => 'Normal',
    'acil' => 'Acil',
    'kritik' => 'Kritik'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satın Alma Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Satın Alma Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="satin-alma-talep-olustur.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Talep Oluştur
                            </a>
                            <a href="satin-alma-talep-listesi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Talep Listesi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Talep</h6>
                                        <h3><?php echo $stats['toplam_talep']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bu Ay</h6>
                                        <h3><?php echo $stats['bu_ay_talep']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bekleyen</h6>
                                        <h3><?php echo $stats['bekleyen_talep']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Onaylanan</h6>
                                        <h3><?php echo $stats['onaylanan_talep']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Tutar</h6>
                                        <h3><?php echo number_format($stats['toplam_tutar'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-lira-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bu Ay Tutar</h6>
                                        <h3><?php echo number_format($stats['bu_ay_tutar'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Talepler -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history"></i> Son Satın Alma Talepleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($son_talepler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz satın alma talebi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Talep No</th>
                                            <th>Departman</th>
                                            <th>Durum</th>
                                            <th>Acil Durum</th>
                                            <th>Toplam Tutar</th>
                                            <th>Talep Eden</th>
                                            <th>Talep Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($son_talepler as $talep): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($talep['talep_no']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($talep['departman']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['durum'] == 'onaylandi' ? 'success' : 
                                                            ($talep['durum'] == 'reddedildi' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($talep_durumlari[$talep['durum']] ?? $talep['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['acil_durum'] == 'kritik' ? 'danger' : 
                                                            ($talep['acil_durum'] == 'acil' ? 'warning' : 'info'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($acil_durum_seviyeleri[$talep['acil_durum']] ?? $talep['acil_durum']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($talep['toplam_tutar'], 2); ?>₺</td>
                                                <td><?php echo htmlspecialchars($talep['talep_eden_adi'] . ' ' . $talep['talep_eden_soyadi']); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($talep['talep_tarihi'])); ?></td>
                                                <td>
                                                    <a href="satin-alma-talep-detay.php?id=<?php echo $talep['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Detay
                                                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>