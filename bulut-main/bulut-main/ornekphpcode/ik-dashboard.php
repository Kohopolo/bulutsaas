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
if (!hasDetailedPermission('ik_dashboard')) {
    $_SESSION['error_message'] = 'İK dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$bugun = date('Y-m-d');

// İstatistikleri hesapla
$stats = [];

// Toplam personel
$toplam_personel_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM kullanicilar 
    WHERE rol != 'superadmin'
");
$stats['toplam_personel'] = $toplam_personel_result['toplam'] ?? 0;

// Aktif personel
$aktif_personel_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM kullanicilar 
    WHERE rol != 'superadmin' AND durum = 'aktif' AND aktif = 1
");
$stats['aktif_personel'] = $aktif_personel_result['toplam'] ?? 0;

// Bugünkü izin talepleri
$bugun_izin_talepleri_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM izin_talepleri 
    WHERE DATE(olusturma_tarihi) = ?
", [$bugun]);
$stats['bugun_izin_talepleri'] = $bugun_izin_talepleri_result['toplam'] ?? 0;

// Bekleyen izin talepleri
$bekleyen_izin_talepleri_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM izin_talepleri 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_izin_talepleri'] = $bekleyen_izin_talepleri_result['toplam'] ?? 0;

// Departman dağılımı
$departman_dagilimi = fetchAll("
    SELECT pb.departman, COUNT(*) as personel_sayisi
    FROM personel_bilgileri pb
    JOIN kullanicilar k ON pb.kullanici_id = k.id
    WHERE k.durum = 'aktif' AND k.aktif = 1
    GROUP BY pb.departman
    ORDER BY personel_sayisi DESC
");

// Son izin talepleri
$son_izin_talepleri = fetchAll("
    SELECT it.*, k.ad, k.soyad, k.rol
    FROM izin_talepleri it
    LEFT JOIN kullanicilar k ON it.personel_id = k.id
    ORDER BY it.olusturma_tarihi DESC
    LIMIT 10
");

// İzin türleri
$izin_turleri = [
    'yillik' => 'Yıllık İzin',
    'hastalik' => 'Hastalık İzni',
    'dogum' => 'Doğum İzni',
    'evlilik' => 'Evlilik İzni',
    'olum' => 'Ölüm İzni',
    'mazeret' => 'Mazeret İzni',
    'unpaid' => 'Ücretsiz İzin'
];

// İzin durumları
$izin_durumlari = [
    'beklemede' => 'Beklemede',
    'onaylandi' => 'Onaylandı',
    'reddedildi' => 'Reddedildi'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İK Dashboard</title>
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
                    <h1 class="h2">İK Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="ik-personel-ekle.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Personel Ekle
                            </a>
                            <a href="ik-personel-listesi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-users"></i> Personel Listesi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Personel</h6>
                                        <h3><?php echo $stats['toplam_personel']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Aktif Personel</h6>
                                        <h3><?php echo $stats['aktif_personel']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bugünkü İzin Talepleri</h6>
                                        <h3><?php echo $stats['bugun_izin_talepleri']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bekleyen İzin Talepleri</h6>
                                        <h3><?php echo $stats['bekleyen_izin_talepleri']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Departman Dağılımı -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie"></i> Departman Dağılımı
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($departman_dagilimi)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Departman bilgisi bulunamadı.</p>
                                    </div>
                                <?php else: ?>
                                    <canvas id="departmanChart" height="200"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Son İzin Talepleri -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-check"></i> Son İzin Talepleri
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_izin_talepleri)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Henüz izin talebi bulunmuyor.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Personel</th>
                                                    <th>İzin Türü</th>
                                                    <th>Durum</th>
                                                    <th>Tarih</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($son_izin_talepleri as $izin): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($izin['ad'] . ' ' . $izin['soyad']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($izin['rol']); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo htmlspecialchars($izin_turleri[$izin['izin_turu']] ?? $izin['izin_turu']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $izin['durum'] == 'onaylandi' ? 'success' : 
                                                                    ($izin['durum'] == 'reddedildi' ? 'danger' : 'warning'); 
                                                            ?>">
                                                                <?php echo htmlspecialchars($izin_durumlari[$izin['durum']] ?? $izin['durum']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('d.m.Y', strtotime($izin['olusturma_tarihi'])); ?></td>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($departman_dagilimi)): ?>
        const ctx = document.getElementById('departmanChart').getContext('2d');
        const departmanChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . htmlspecialchars($item['departman']) . "'"; }, $departman_dagilimi)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($departman_dagilimi, 'personel_sayisi')); ?>],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>