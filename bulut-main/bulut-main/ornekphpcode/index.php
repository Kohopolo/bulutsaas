
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('dashboard_goruntule', 'Dashboard görüntüleme yetkiniz bulunmamaktadır.');

// Dashboard istatistikleri
$bugun = date('Y-m-d');
$bu_ay_baslangic = date('Y-m-01');
$bu_ay_bitis = date('Y-m-t');

// Genel istatistikler
$toplam_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar")['sayi'];
$beklemede_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'")['sayi'];
$onaylanan_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'onaylandi'")['sayi'];
$aktif_konaklamalar = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in'")['sayi'];

// Bu ay istatistikleri
$bu_ay_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?", [$bu_ay_baslangic, $bu_ay_bitis])['sayi'];
$bu_ay_gelir = fetchOne("SELECT SUM(toplam_tutar) as toplam FROM rezervasyonlar WHERE durum NOT IN ('iptal') AND DATE(olusturma_tarihi) BETWEEN ? AND ?", [$bu_ay_baslangic, $bu_ay_bitis])['toplam'] ?? 0;

// Bugünkü işlemler
$bugun_check_in = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE giris_tarihi = ? AND durum = 'onaylandi'", [$bugun])['sayi'];
$bugun_check_out = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE cikis_tarihi = ? AND durum = 'check_in'", [$bugun])['sayi'];

// Oda doluluk oranı
$toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'aktif'")['sayi'];
$dolu_oda = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in' AND ? BETWEEN giris_tarihi AND cikis_tarihi", [$bugun])['sayi'];
$doluluk_orani = $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100) : 0;

// Son rezervasyonlar
$son_rezervasyonlar = fetchAll("
    SELECT r.*, ot.oda_tipi_adi,
           COALESCE(r.musteri_adi, '') as musteri_adi,
           COALESCE(r.musteri_soyadi, '') as musteri_soyadi,
           COALESCE(r.rezervasyon_kodu, CONCAT('RZ', r.id)) as rezervasyon_kodu
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    ORDER BY r.olusturma_tarihi DESC 
    LIMIT 5
");

// Bugünkü check-in/check-out listesi
$bugun_islemleri = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi,
           CASE 
               WHEN r.giris_tarihi = ? THEN 'check_in'
               WHEN r.cikis_tarihi = ? THEN 'check_out'
           END as islem_tipi
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id
    WHERE (r.giris_tarihi = ? AND r.durum = 'onaylandi') 
       OR (r.cikis_tarihi = ? AND r.durum = 'check_in')
    ORDER BY r.giris_tarihi ASC
", [$bugun, $bugun, $bugun, $bugun]);

// Aylık gelir grafiği için veri
$aylik_gelir = [];
for ($i = 11; $i >= 0; $i--) {
    $ay = date('Y-m', strtotime("-$i months"));
    $ay_baslangic = $ay . '-01';
    $ay_bitis = date('Y-m-t', strtotime($ay_baslangic));
    
    $gelir = fetchOne("
        SELECT SUM(toplam_tutar) as toplam 
        FROM rezervasyonlar 
        WHERE durum NOT IN ('iptal') 
        AND DATE(olusturma_tarihi) BETWEEN ? AND ?
    ", [$ay_baslangic, $ay_bitis])['toplam'] ?? 0;
    
    $aylik_gelir[] = [
        'ay' => date('M Y', strtotime($ay_baslangic)),
        'gelir' => $gelir
    ];
}

// Oda tipi bazında rezervasyon dağılımı
$oda_tipi_dagilim = fetchAll("
    SELECT ot.oda_tipi_adi, COUNT(r.id) as rezervasyon_sayisi
    FROM oda_tipleri ot
    LEFT JOIN rezervasyonlar r ON ot.id = r.oda_tipi_id AND r.durum NOT IN ('iptal')
    WHERE ot.durum = 'aktif'
    GROUP BY ot.id, ot.oda_tipi_adi
    ORDER BY rezervasyon_sayisi DESC
");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <div class="container-fluid">
            <!-- Başlık -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <p class="text-muted">Otel yönetim paneline hoş geldiniz</p>
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
                                        Toplam Rezervasyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $toplam_rezervasyon; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
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
                                        Beklemede
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $beklemede_rezervasyon; ?>
                                    </div>
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
                                        Aktif Konaklamalar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $aktif_konaklamalar; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bed fa-2x text-gray-300"></i>
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
                                        Doluluk Oranı
                                    </div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                                %<?php echo $doluluk_orani; ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="progress progress-sm mr-2">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $doluluk_orani; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bu Ay İstatistikleri -->
            <div class="row mb-4">
                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Bu Ay Rezervasyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $bu_ay_rezervasyon; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Bu Ay Gelir
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($bu_ay_gelir); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bugünkü İşlemler -->
            <?php if (!empty($bugun_islemleri)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-calendar-day me-2"></i>Bugünkü İşlemler
                            </h6>
                            <span class="badge bg-primary"><?php echo count($bugun_islemleri); ?> işlem</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>İşlem</th>
                                            <th>Rezervasyon</th>
                                            <th>Müşteri</th>
                                            <th>Oda</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bugun_islemleri as $islem): ?>
                                        <tr>
                                            <td>
                                                <?php if ($islem['islem_tipi'] == 'check_in'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-sign-in-alt me-1"></i>Check-in
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-sign-out-alt me-1"></i>Check-out
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($islem['rezervasyon_kodu']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($islem['musteri_adi'] . ' ' . $islem['musteri_soyadi']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($islem['musteri_telefon']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($islem['oda_tipi_adi']); ?>
                                                <?php if ($islem['oda_numarasi']): ?>
                                                    <br><small class="text-success">Oda: <?php echo htmlspecialchars($islem['oda_numarasi']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $islem['durum'] == 'onaylandi' ? 'success' : 'info'; ?>">
                                                    <?php echo ucfirst($islem['durum']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="rezervasyon-detay.php?id=<?php echo $islem['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="check-in-out.php?rezervasyon_id=<?php echo $islem['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Son Rezervasyonlar -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Son Rezervasyonlar</h6>
                            <a href="rezervasyonlar.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($son_rezervasyonlar)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz rezervasyon bulunmuyor.</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($son_rezervasyonlar as $rezervasyon): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold"><?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?> - 
                                            <?php echo formatTurkishDate($rezervasyon['giris_tarihi'], 'd.m.Y'); ?>
                                        </small>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $rezervasyon['durum'] == 'beklemede' ? 'warning' : 
                                            ($rezervasyon['durum'] == 'onaylandi' ? 'success' : 
                                            ($rezervasyon['durum'] == 'check_in' ? 'info' : 'secondary')); 
                                    ?> rounded-pill">
                                        <?php echo ucfirst($rezervasyon['durum']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Aylık Gelir Grafiği -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Aylık Gelir Trendi</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="gelirGrafigi" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Oda Tipi Dağılımı -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Oda Tipi Rezervasyon Dağılımı</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="odaTipiGrafigi" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Hızlı İşlemler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="rezervasyon-ekle.php" class="btn btn-primary w-100">
                                        <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="check-in-out.php" class="btn btn-success w-100">
                                        <i class="fas fa-key me-2"></i>Check-in/out
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="musaitlik-yonetimi.php" class="btn btn-info w-100">
                                        <i class="fas fa-calendar me-2"></i>Müsaitlik
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="raporlar.php" class="btn btn-warning w-100">
                                        <i class="fas fa-chart-bar me-2"></i>Raporlar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Aylık gelir grafiği
        const gelirCtx = document.getElementById('gelirGrafigi').getContext('2d');
        const gelirGrafigi = new Chart(gelirCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($aylik_gelir, 'ay')); ?>,
                datasets: [{
                    label: 'Aylık Gelir (₺)',
                    data: <?php echo json_encode(array_column($aylik_gelir, 'gelir')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₺' + value.toLocaleString('tr-TR');
                            }
                        }
                    }
                }
            }
        });

        // Oda tipi dağılım grafiği
        const odaTipiCtx = document.getElementById('odaTipiGrafigi').getContext('2d');
        const odaTipiGrafigi = new Chart(odaTipiCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($oda_tipi_dagilim, 'oda_tipi_adi')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($oda_tipi_dagilim, 'rezervasyon_sayisi')); ?>,
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
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
