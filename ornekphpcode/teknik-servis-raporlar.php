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
if (!hasDetailedPermission('teknik_servis_raporlar')) {
    $_SESSION['error_message'] = 'Teknik servis raporları görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

// Tarih filtreleri
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d');

// Rapor verilerini hesapla
$rapor_verileri = [];

// Genel istatistikler
$rapor_verileri['genel'] = fetchOne("
    SELECT 
        COUNT(*) as toplam_talep,
        SUM(CASE WHEN durum = 'tamamlandi' THEN 1 ELSE 0 END) as tamamlanan_talep,
        SUM(CASE WHEN durum = 'devam_ediyor' THEN 1 ELSE 0 END) as devam_eden_talep,
        SUM(CASE WHEN durum = 'beklemede' THEN 1 ELSE 0 END) as beklemede_talep,
        AVG(CASE WHEN baslama_tarihi IS NOT NULL AND bitis_tarihi IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, baslama_tarihi, bitis_tarihi) ELSE NULL END) as ortalama_sure
    FROM teknik_servis_talepleri 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?
", [$baslangic_tarihi, $bitis_tarihi]);

// Talep türü bazlı istatistikler
$rapor_verileri['talep_turu'] = fetchAll("
    SELECT 
        talep_turu,
        COUNT(*) as talep_sayisi,
        SUM(CASE WHEN durum = 'tamamlandi' THEN 1 ELSE 0 END) as tamamlanan,
        AVG(CASE WHEN baslama_tarihi IS NOT NULL AND bitis_tarihi IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, baslama_tarihi, bitis_tarihi) ELSE NULL END) as ortalama_sure
    FROM teknik_servis_talepleri 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?
    GROUP BY talep_turu
    ORDER BY talep_sayisi DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Teknisyen performansı
$rapor_verileri['teknisyen_performansi'] = fetchAll("
    SELECT 
        k.ad as teknisyen_adi,
        k.soyad as teknisyen_soyadi,
        COUNT(tst.id) as toplam_talep,
        SUM(CASE WHEN tst.durum = 'tamamlandi' THEN 1 ELSE 0 END) as tamamlanan_talep,
        AVG(CASE WHEN tst.baslama_tarihi IS NOT NULL AND tst.bitis_tarihi IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, tst.baslama_tarihi, tst.bitis_tarihi) ELSE NULL END) as ortalama_sure
    FROM kullanicilar k
    LEFT JOIN teknik_servis_talepleri tst ON k.id = tst.atanan_teknisyen_id 
        AND DATE(tst.olusturma_tarihi) BETWEEN ? AND ?
    WHERE k.rol IN ('teknisyen', 'teknik_servis_manager')
    GROUP BY k.id
    HAVING toplam_talep > 0
    ORDER BY tamamlanan_talep DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Günlük talep dağılımı
$rapor_verileri['gunluk_dagilim'] = fetchAll("
    SELECT 
        DATE(olusturma_tarihi) as tarih,
        COUNT(*) as talep_sayisi,
        SUM(CASE WHEN durum = 'tamamlandi' THEN 1 ELSE 0 END) as tamamlanan
    FROM teknik_servis_talepleri 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?
    GROUP BY DATE(olusturma_tarihi)
    ORDER BY tarih
", [$baslangic_tarihi, $bitis_tarihi]);

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

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Raporları</title>
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
                    <h1 class="h2">Teknik Servis Raporları</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teknik-servis-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Tarih Filtresi -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter"></i> Tarih Filtresi
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" name="baslangic_tarihi" class="form-control" value="<?php echo htmlspecialchars($baslangic_tarihi); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" name="bitis_tarihi" class="form-control" value="<?php echo htmlspecialchars($bitis_tarihi); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Talep</h6>
                                        <h3><?php echo $rapor_verileri['genel']['toplam_talep'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tools fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Tamamlanan</h6>
                                        <h3><?php echo $rapor_verileri['genel']['tamamlanan_talep'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Devam Eden</h6>
                                        <h3><?php echo $rapor_verileri['genel']['devam_eden_talep'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Ortalama Süre</h6>
                                        <h3><?php echo $rapor_verileri['genel']['ortalama_sure'] ? round($rapor_verileri['genel']['ortalama_sure']) . ' dk' : '-'; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-stopwatch fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Talep Türü Bazlı İstatistikler -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie"></i> Talep Türü Bazlı İstatistikler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($rapor_verileri['talep_turu'])): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Seçilen tarih aralığında veri bulunamadı.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Talep Türü</th>
                                                    <th>Toplam</th>
                                                    <th>Tamamlanan</th>
                                                    <th>Ortalama Süre</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_verileri['talep_turu'] as $tur): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($talep_turleri[$tur['talep_turu']] ?? $tur['talep_turu']); ?></td>
                                                        <td><?php echo $tur['talep_sayisi']; ?></td>
                                                        <td><?php echo $tur['tamamlanan']; ?></td>
                                                        <td><?php echo $tur['ortalama_sure'] ? round($tur['ortalama_sure']) . ' dk' : '-'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Teknisyen Performansı -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-cog"></i> Teknisyen Performansı
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($rapor_verileri['teknisyen_performansi'])): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-cog fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Seçilen tarih aralığında veri bulunamadı.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Teknisyen</th>
                                                    <th>Toplam</th>
                                                    <th>Tamamlanan</th>
                                                    <th>Ortalama Süre</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_verileri['teknisyen_performansi'] as $teknisyen): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($teknisyen['teknisyen_adi'] . ' ' . $teknisyen['teknisyen_soyadi']); ?></td>
                                                        <td><?php echo $teknisyen['toplam_talep']; ?></td>
                                                        <td><?php echo $teknisyen['tamamlanan_talep']; ?></td>
                                                        <td><?php echo $teknisyen['ortalama_sure'] ? round($teknisyen['ortalama_sure']) . ' dk' : '-'; ?></td>
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

                <!-- Günlük Talep Dağılımı -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line"></i> Günlük Talep Dağılımı
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rapor_verileri['gunluk_dagilim'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Seçilen tarih aralığında veri bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="gunlukDagilimChart" height="100"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($rapor_verileri['gunluk_dagilim'])): ?>
        const ctx = document.getElementById('gunlukDagilimChart').getContext('2d');
        const gunlukDagilimChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('d.m', strtotime($item['tarih'])) . "'"; }, $rapor_verileri['gunluk_dagilim'])); ?>],
                datasets: [{
                    label: 'Toplam Talep',
                    data: [<?php echo implode(',', array_column($rapor_verileri['gunluk_dagilim'], 'talep_sayisi')); ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Tamamlanan',
                    data: [<?php echo implode(',', array_column($rapor_verileri['gunluk_dagilim'], 'tamamlanan')); ?>],
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
