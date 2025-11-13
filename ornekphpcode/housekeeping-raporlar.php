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
if (!hasDetailedPermission('housekeeping_raporlar')) {
    $_SESSION['error_message'] = 'Housekeeping raporları görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

// Tarih aralığı
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d');

// Rapor verilerini getir
$rapor_verileri = [];

// Genel istatistikler
$rapor_verileri['genel'] = fetchOne("
    SELECT 
        COUNT(*) as toplam_gorev,
        SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as tamamlanan_gorev,
        SUM(CASE WHEN durum = 'devam_ediyor' THEN 1 ELSE 0 END) as devam_eden_gorev,
        SUM(CASE WHEN durum = 'onay_bekliyor' THEN 1 ELSE 0 END) as beklemede_gorev,
        AVG(CASE WHEN baslama_saati IS NOT NULL AND bitis_saati IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, baslama_saati, bitis_saati) ELSE NULL END) as ortalama_sure
    FROM temizlik_kayitlari 
    WHERE DATE(temizlik_tarihi) BETWEEN ? AND ?
", [$baslangic_tarihi, $bitis_tarihi]);

// Personel performansı
$rapor_verileri['personel_performansi'] = fetchAll("
    SELECT 
        k.ad as personel_adi,
        k.soyad as personel_soyadi,
        COUNT(tk.id) as toplam_gorev,
        SUM(CASE WHEN tk.durum = 'onaylandi' THEN 1 ELSE 0 END) as tamamlanan_gorev,
        AVG(CASE WHEN tk.baslama_saati IS NOT NULL AND tk.bitis_saati IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, tk.baslama_saati, tk.bitis_saati) ELSE NULL END) as ortalama_sure
    FROM kullanicilar k
    LEFT JOIN temizlik_kayitlari tk ON k.id = tk.housekeeper_id 
        AND DATE(tk.temizlik_tarihi) BETWEEN ? AND ?
    WHERE k.rol IN ('housekeeper', 'housekeeper_manager')
    GROUP BY k.id
    HAVING toplam_gorev > 0
    ORDER BY tamamlanan_gorev DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Temizlik türü bazında istatistikler
$rapor_verileri['temizlik_turu_istatistikleri'] = fetchAll("
    SELECT 
        temizlik_turu,
        COUNT(*) as gorev_sayisi,
        SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as tamamlanan,
        AVG(CASE WHEN baslama_saati IS NOT NULL AND bitis_saati IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, baslama_saati, bitis_saati) ELSE NULL END) as ortalama_sure
    FROM temizlik_kayitlari 
    WHERE DATE(temizlik_tarihi) BETWEEN ? AND ?
    GROUP BY temizlik_turu
    ORDER BY gorev_sayisi DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Günlük temizlik istatistikleri
$rapor_verileri['gunluk_istatistikler'] = fetchAll("
    SELECT 
        DATE(temizlik_tarihi) as tarih,
        COUNT(*) as toplam_gorev,
        SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as tamamlanan_gorev,
        AVG(CASE WHEN baslama_saati IS NOT NULL AND bitis_saati IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, baslama_saati, bitis_saati) ELSE NULL END) as ortalama_sure
    FROM temizlik_kayitlari 
    WHERE DATE(temizlik_tarihi) BETWEEN ? AND ?
    GROUP BY DATE(temizlik_tarihi)
    ORDER BY tarih DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Temizlik türleri
$temizlik_turleri = [
    'checkout' => 'Check-out Temizliği',
    'günlük' => 'Günlük Temizlik',
    'derin' => 'Derin Temizlik',
    'bakım' => 'Bakım Temizliği',
    'özel' => 'Özel Temizlik'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housekeeping Raporlar - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Housekeeping Raporlar</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="housekeeping-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Rapor Filtreleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" 
                                           value="<?php echo $baslangic_tarihi; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                           value="<?php echo $bitis_tarihi; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Filtrele
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Toplam Görev</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rapor_verileri['genel']['toplam_gorev']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rapor_verileri['genel']['tamamlanan_gorev']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rapor_verileri['genel']['devam_eden_gorev']; ?></div>
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
                                            Ortalama Süre</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rapor_verileri['genel']['ortalama_sure'] ? round($rapor_verileri['genel']['ortalama_sure']) . ' dk' : '-'; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-stopwatch fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Temizlik Türü Bazında Görevler</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="temizlikTuruChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Günlük Görev Trendi</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="gunlukChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personel Performansı -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users me-2"></i>Personel Performansı
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Personel</th>
                                        <th>Toplam Görev</th>
                                        <th>Tamamlanan</th>
                                        <th>Başarı Oranı</th>
                                        <th>Ortalama Süre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_verileri['personel_performansi'] as $personel): ?>
                                    <?php $basari_orani = $personel['toplam_gorev'] > 0 ? round(($personel['tamamlanan_gorev'] / $personel['toplam_gorev']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($personel['personel_adi'] . ' ' . $personel['personel_soyadi']); ?></strong>
                                        </td>
                                        <td><?php echo $personel['toplam_gorev']; ?></td>
                                        <td><?php echo $personel['tamamlanan_gorev']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $basari_orani >= 80 ? 'success' : ($basari_orani >= 60 ? 'warning' : 'danger'); ?>" 
                                                     role="progressbar" style="width: <?php echo $basari_orani; ?>%">
                                                    <?php echo $basari_orani; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $personel['ortalama_sure'] ? round($personel['ortalama_sure']) . ' dk' : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Temizlik Türü İstatistikleri -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Temizlik Türü İstatistikleri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Temizlik Türü</th>
                                        <th>Toplam Görev</th>
                                        <th>Tamamlanan</th>
                                        <th>Tamamlanma Oranı</th>
                                        <th>Ortalama Süre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_verileri['temizlik_turu_istatistikleri'] as $istatistik): ?>
                                    <?php $tamamlanma_orani = $istatistik['gorev_sayisi'] > 0 ? round(($istatistik['tamamlanan'] / $istatistik['gorev_sayisi']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $temizlik_turleri[$istatistik['temizlik_turu']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $istatistik['gorev_sayisi']; ?></td>
                                        <td><?php echo $istatistik['tamamlanan']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $tamamlanma_orani >= 80 ? 'success' : ($tamamlanma_orani >= 60 ? 'warning' : 'danger'); ?>" 
                                                     role="progressbar" style="width: <?php echo $tamamlanma_orani; ?>%">
                                                    <?php echo $tamamlanma_orani; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $istatistik['ortalama_sure'] ? round($istatistik['ortalama_sure']) . ' dk' : '-'; ?>
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
    <script>
        // Temizlik türü bazında görevler grafiği
        const temizlikTuruCtx = document.getElementById('temizlikTuruChart').getContext('2d');
        const temizlikTuruData = <?php echo json_encode($rapor_verileri['temizlik_turu_istatistikleri']); ?>;
        
        new Chart(temizlikTuruCtx, {
            type: 'doughnut',
            data: {
                labels: temizlikTuruData.map(item => {
                    const turNames = {
                        'checkout': 'Check-out Temizliği',
                        'günlük': 'Günlük Temizlik',
                        'derin': 'Derin Temizlik',
                        'bakım': 'Bakım Temizliği',
                        'özel': 'Özel Temizlik'
                    };
                    return turNames[item.temizlik_turu];
                }),
                datasets: [{
                    data: temizlikTuruData.map(item => item.gorev_sayisi),
                    backgroundColor: [
                        '#dc3545',
                        '#198754',
                        '#0dcaf0',
                        '#ffc107',
                        '#6f42c1'
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

        // Günlük görev trendi grafiği
        const gunlukCtx = document.getElementById('gunlukChart').getContext('2d');
        const gunlukData = <?php echo json_encode($rapor_verileri['gunluk_istatistikler']); ?>;
        
        new Chart(gunlukCtx, {
            type: 'line',
            data: {
                labels: gunlukData.map(item => {
                    const date = new Date(item.tarih);
                    return date.toLocaleDateString('tr-TR');
                }),
                datasets: [{
                    label: 'Toplam Görev',
                    data: gunlukData.map(item => item.toplam_gorev),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Tamamlanan Görev',
                    data: gunlukData.map(item => item.tamamlanan_gorev),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
