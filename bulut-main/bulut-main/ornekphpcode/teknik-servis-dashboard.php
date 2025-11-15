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
if (!hasDetailedPermission('teknik_servis_dashboard')) {
    $_SESSION['error_message'] = 'Teknik servis dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$bugun = date('Y-m-d');

// İstatistikleri hesapla
$stats = [];

// Bugünkü talepler
$bugun_talepler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM teknik_servis_talepleri 
    WHERE DATE(olusturma_tarihi) = ?
", [$bugun]);
$stats['bugun_talepler'] = $bugun_talepler_result['toplam'] ?? 0;

// Bekleyen talepler
$bekleyen_talepler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM teknik_servis_talepleri 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_talepler'] = $bekleyen_talepler_result['toplam'] ?? 0;

// Devam eden talepler
$devam_eden_talepler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM teknik_servis_talepleri 
    WHERE durum = 'devam_ediyor'
");
$stats['devam_eden_talepler'] = $devam_eden_talepler_result['toplam'] ?? 0;

// Tamamlanan talepler (bugün)
$tamamlanan_talepler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM teknik_servis_talepleri 
    WHERE durum = 'tamamlandi' AND DATE(bitis_tarihi) = ?
", [$bugun]);
$stats['tamamlanan_talepler'] = $tamamlanan_talepler_result['toplam'] ?? 0;

// Aktif teknisyenler
$aktif_teknisyenler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM kullanicilar 
    WHERE rol IN ('teknisyen', 'teknik_servis_manager') AND durum = 'aktif' AND aktif = 1
");
$stats['aktif_teknisyenler'] = $aktif_teknisyenler_result['toplam'] ?? 0;

// Kritik talepler
$kritik_talepler_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM teknik_servis_talepleri 
    WHERE acil_durum = 'kritik' AND durum != 'tamamlandi'
");
$stats['kritik_talepler'] = $kritik_talepler_result['toplam'] ?? 0;

// Bugünkü talepler
$bugun_talepler = fetchAll("
    SELECT tst.*, on.oda_numarasi, ot.oda_tipi_adi as oda_tipi,
           k1.ad as talep_eden_adi, k1.soyad as talep_eden_soyadi,
           k2.ad as teknisyen_adi, k2.soyad as teknisyen_soyadi
    FROM teknik_servis_talepleri tst
    LEFT JOIN oda_numaralari on ON tst.oda_id = on.id
    LEFT JOIN oda_tipleri ot ON on.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k1 ON tst.talep_eden_id = k1.id
    LEFT JOIN kullanicilar k2 ON tst.atanan_teknisyen_id = k2.id
    WHERE DATE(tst.olusturma_tarihi) = ?
    ORDER BY tst.oncelik DESC, tst.olusturma_tarihi DESC
", [$bugun]);

// Talep türleri
$talep_turleri = [
    'elektrik' => 'Elektrik',
    'su' => 'Su Tesisatı',
    'klima' => 'Klima',
    'internet' => 'İnternet',
    'tv' => 'TV',
    'telefon' => 'Telefon',
    'asansor' => 'Asansör',
    'güvenlik' => 'Güvenlik',
    'yangin' => 'Yangın',
    'diger' => 'Diğer'
];

// Acil durum seviyeleri
$acil_durum_seviyeleri = [
    'dusuk' => 'Düşük',
    'orta' => 'Orta',
    'yuksek' => 'Yüksek',
    'kritik' => 'Kritik'
];

// Öncelik seviyeleri
$oncelik_seviyeleri = [
    'dusuk' => 'Düşük',
    'normal' => 'Normal',
    'yuksek' => 'Yüksek',
    'acil' => 'Acil'
];

// Durum seviyeleri
$durum_seviyeleri = [
    'beklemede' => 'Beklemede',
    'atanmis' => 'Atanmış',
    'devam_ediyor' => 'Devam Ediyor',
    'tamamlandi' => 'Tamamlandı',
    'iptal' => 'İptal'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Teknik Servis Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="teknik-servis-talep-olustur.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Yeni Talep
                            </a>
                            <a href="teknik-servis-talep-listesi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Tüm Talepler
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
                                        <h6 class="card-title">Bugünkü Talepler</h6>
                                        <h3><?php echo $stats['bugun_talepler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tools fa-2x"></i>
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
                                        <h3><?php echo $stats['bekleyen_talepler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
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
                                        <h6 class="card-title">Devam Eden</h6>
                                        <h3><?php echo $stats['devam_eden_talepler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-cog fa-spin fa-2x"></i>
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
                                        <h6 class="card-title">Tamamlanan</h6>
                                        <h3><?php echo $stats['tamamlanan_talepler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
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
                                        <h6 class="card-title">Aktif Teknisyen</h6>
                                        <h3><?php echo $stats['aktif_teknisyenler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-cog fa-2x"></i>
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
                                        <h6 class="card-title">Kritik</h6>
                                        <h3><?php echo $stats['kritik_talepler']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bugünkü Talepler -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-day"></i> Bugünkü Teknik Servis Talepleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bugun_talepler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bugün henüz teknik servis talebi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Talep No</th>
                                            <th>Oda</th>
                                            <th>Tür</th>
                                            <th>Başlık</th>
                                            <th>Öncelik</th>
                                            <th>Durum</th>
                                            <th>Teknisyen</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bugun_talepler as $talep): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($talep['talep_no']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($talep['oda_numarasi']): ?>
                                                        <?php echo htmlspecialchars($talep['oda_numarasi'] . ' (' . $talep['oda_tipi'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Genel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($talep_turleri[$talep['talep_turu']] ?? $talep['talep_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($talep['baslik']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['oncelik'] == 'acil' ? 'danger' : 
                                                            ($talep['oncelik'] == 'yuksek' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($oncelik_seviyeleri[$talep['oncelik']] ?? $talep['oncelik']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['durum'] == 'tamamlandi' ? 'success' : 
                                                            ($talep['durum'] == 'devam_ediyor' ? 'info' : 
                                                            ($talep['durum'] == 'beklemede' ? 'warning' : 'secondary')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($durum_seviyeleri[$talep['durum']] ?? $talep['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($talep['teknisyen_adi']): ?>
                                                        <?php echo htmlspecialchars($talep['teknisyen_adi'] . ' ' . $talep['teknisyen_soyadi']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Atanmamış</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="teknik-servis-talep-detay.php?id=<?php echo $talep['id']; ?>" class="btn btn-sm btn-outline-primary">
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
