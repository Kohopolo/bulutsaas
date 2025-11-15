
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
requireDetailedPermission('raporlar_goruntule', 'Raporlar görüntüleme yetkiniz bulunmamaktadır.');

// Tarih aralığı parametreleri
$baslangic_tarihi = $_GET['baslangic'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis'] ?? date('Y-m-d');

// Genel istatistikler
$toplam_rezervasyon = fetchOne("
    SELECT COUNT(*) as sayi 
    FROM rezervasyonlar 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ? 
    AND durum != 'iptal'
", [$baslangic_tarihi, $bitis_tarihi]);

$toplam_gelir = fetchOne("
    SELECT SUM(toplam_tutar) as toplam 
    FROM rezervasyonlar 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ? 
    AND durum NOT IN ('iptal')
", [$baslangic_tarihi, $bitis_tarihi]);

$iptal_edilen = fetchOne("
    SELECT COUNT(*) as sayi 
    FROM rezervasyonlar 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ? 
    AND durum = 'iptal'
", [$baslangic_tarihi, $bitis_tarihi]);

// Oda tipi bazında istatistikler
$oda_tipi_stats = fetchAll("
    SELECT 
        ot.oda_tipi_adi,
        COUNT(r.id) as rezervasyon_sayisi,
        SUM(r.toplam_tutar) as toplam_gelir,
        AVG(r.toplam_tutar) as ortalama_tutar
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE DATE(r.olusturma_tarihi) BETWEEN ? AND ?
    AND r.durum NOT IN ('iptal')
    GROUP BY r.oda_tipi_id, ot.oda_tipi_adi
    ORDER BY rezervasyon_sayisi DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Aylık gelir trendi (son 12 ay)
$aylik_gelir = fetchAll("
    SELECT 
        DATE_FORMAT(olusturma_tarihi, '%Y-%m') as ay,
        COUNT(*) as rezervasyon_sayisi,
        SUM(toplam_tutar) as toplam_gelir
    FROM rezervasyonlar 
    WHERE olusturma_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND durum NOT IN ('iptal')
    GROUP BY DATE_FORMAT(olusturma_tarihi, '%Y-%m')
    ORDER BY ay DESC
    LIMIT 12
");

// Durum bazında istatistikler
$durum_stats = fetchAll("
    SELECT 
        durum,
        COUNT(*) as sayi,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM rezervasyonlar WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?)) as yuzde
    FROM rezervasyonlar 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?
    GROUP BY durum
    ORDER BY sayi DESC
", [$baslangic_tarihi, $bitis_tarihi, $baslangic_tarihi, $bitis_tarihi]);

// En çok rezervasyon yapan müşteriler
$top_musteriler = fetchAll("
    SELECT 
        musteri_adi,
        musteri_soyadi,
        musteri_email,
        COUNT(*) as rezervasyon_sayisi,
        SUM(toplam_tutar) as toplam_harcama
    FROM rezervasyonlar 
    WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?
    AND durum NOT IN ('iptal')
    GROUP BY musteri_email
    ORDER BY rezervasyon_sayisi DESC, toplam_harcama DESC
    LIMIT 10
", [$baslangic_tarihi, $bitis_tarihi]);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Raporlar ve İstatistikler</h1>
                            <p class="text-muted">Otel performansını analiz edin</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Excel'e Aktar
                            </button>
                            <button class="btn btn-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-2"></i>PDF'e Aktar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarih Filtresi -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="baslangic" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic" name="baslangic" 
                                   value="<?php echo htmlspecialchars($baslangic_tarihi); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="bitis" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis" name="bitis" 
                                   value="<?php echo htmlspecialchars($bitis_tarihi); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Filtrele
                                </button>
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
                                        Toplam Rezervasyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $toplam_rezervasyon['sayi']; ?>
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
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Toplam Gelir
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($toplam_gelir['toplam'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
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
                                        İptal Edilen
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $iptal_edilen['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                                        Ortalama Rezervasyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $ortalama = $toplam_rezervasyon['sayi'] > 0 ? 
                                            ($toplam_gelir['toplam'] ?? 0) / $toplam_rezervasyon['sayi'] : 0;
                                        echo formatCurrency($ortalama); 
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Aylık Gelir Trendi -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Aylık Gelir Trendi (Son 12 Ay)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="gelirTrendiChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Durum Dağılımı -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Rezervasyon Durumları</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="durumChart" width="400" height="400"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Oda Tipi İstatistikleri -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Oda Tipi Performansı</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Oda Tipi</th>
                                            <th>Rezervasyon</th>
                                            <th>Toplam Gelir</th>
                                            <th>Ortalama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($oda_tipi_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['oda_tipi_adi']); ?></td>
                                            <td><?php echo $stat['rezervasyon_sayisi']; ?></td>
                                            <td><?php echo formatCurrency($stat['toplam_gelir']); ?></td>
                                            <td><?php echo formatCurrency($stat['ortalama_tutar']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- En İyi Müşteriler -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">En Çok Rezervasyon Yapan Müşteriler</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Müşteri</th>
                                            <th>Rezervasyon</th>
                                            <th>Toplam Harcama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_musteriler as $musteri): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($musteri['musteri_adi'] . ' ' . $musteri['musteri_soyadi']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($musteri['musteri_email']); ?></small>
                                            </td>
                                            <td><?php echo $musteri['rezervasyon_sayisi']; ?></td>
                                            <td><?php echo formatCurrency($musteri['toplam_harcama']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Chart.js yüklendiğini kontrol et
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js yüklenmedi!');
                return;
            }
            
            initializeCharts();
        });
        
        function initializeCharts() {
            // Aylık Gelir Trendi Grafiği
            const gelirCtx = document.getElementById('gelirTrendiChart');
            if (!gelirCtx) {
                console.error('Gelir trendi chart canvas bulunamadı');
                return;
            }
            
            const gelirChartCtx = gelirCtx.getContext('2d');
        const gelirData = {
            labels: [
                <?php 
                $aylar = array_reverse($aylik_gelir);
                foreach ($aylar as $ay) {
                    echo "'" . date('M Y', strtotime($ay['ay'] . '-01')) . "',";
                }
                ?>
            ],
            datasets: [{
                label: 'Aylık Gelir (₺)',
                data: [
                    <?php 
                    foreach ($aylar as $ay) {
                        echo ($ay['toplam_gelir'] ?? 0) . ',';
                    }
                    ?>
                ],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        };

        new Chart(gelirChartCtx, {
            type: 'line',
            data: gelirData,
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

        // Durum Dağılımı Grafiği
        const durumCtx = document.getElementById('durumChart');
        if (!durumCtx) {
            console.error('Durum chart canvas bulunamadı');
            return;
        }
        
        const durumChartCtx = durumCtx.getContext('2d');
        const durumData = {
            labels: [
                <?php 
                foreach ($durum_stats as $durum) {
                    $durum_adi = '';
                    switch($durum['durum']) {
                        case 'beklemede': $durum_adi = 'Beklemede'; break;
                        case 'onaylandi': $durum_adi = 'Onaylandı'; break;
                        case 'check_in': $durum_adi = 'Check-in'; break;
                        case 'check_out': $durum_adi = 'Check-out'; break;
                        case 'iptal': $durum_adi = 'İptal'; break;
                        default: $durum_adi = ucfirst($durum['durum']);
                    }
                    echo "'" . $durum_adi . "',";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($durum_stats as $durum) {
                        echo $durum['sayi'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF'
                ]
            }]
        };

        new Chart(durumChartCtx, {
            type: 'doughnut',
            data: durumData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        } // initializeCharts fonksiyonu kapanışı

        // Export fonksiyonları
        function exportToExcel() {
            alert('Excel export özelliği yakında eklenecek.');
        }

        function exportToPDF() {
            alert('PDF export özelliği yakında eklenecek.');
        }
    </script>
</body>
</html>
