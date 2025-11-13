<?php
/**
 * Multi Otel - Raporlar
 * Otel bazlı raporlar ve istatistikler
 */

require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once 'includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
requireDetailedPermission('rapor_goruntule', 'Rapor görüntüleme yetkiniz bulunmamaktadır.');

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
if (!$current_otel) {
    // İlk oteli seç
    $user_oteller = getUserOteller($_SESSION['user_id']);
    if (!empty($user_oteller)) {
        setCurrentOtel($user_oteller[0]['id']);
        $current_otel = getCurrentOtel();
    } else {
        header('Location: oteller.php');
        exit;
    }
}

// Tarih filtreleri
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01'); // Bu ayın başı
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t'); // Bu ayın sonu

// İstatistikler
$stats = getOtelStats($current_otel['id']);

// Aylık rezervasyon istatistikleri
$aylik_rezervasyonlar = fetchAll("
    SELECT 
        DATE_FORMAT(olusturma_tarihi, '%Y-%m') as ay,
        COUNT(*) as rezervasyon_sayisi,
        SUM(toplam_tutar) as toplam_gelir,
        AVG(toplam_tutar) as ortalama_fiyat
    FROM rezervasyonlar 
    WHERE otel_id = ? 
    AND olusturma_tarihi BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(olusturma_tarihi, '%Y-%m')
    ORDER BY ay DESC
    LIMIT 12
", [$current_otel['id'], $baslangic_tarihi . ' 00:00:00', $bitis_tarihi . ' 23:59:59']);

// Oda tipi bazlı istatistikler
$oda_tipi_istatistikleri = fetchAll("
    SELECT 
        ot.oda_tipi_adi,
        COUNT(r.id) as rezervasyon_sayisi,
        SUM(r.toplam_tutar) as toplam_gelir,
        AVG(r.toplam_tutar) as ortalama_fiyat
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE r.otel_id = ? 
    AND r.olusturma_tarihi BETWEEN ? AND ?
    GROUP BY ot.id, ot.oda_tipi_adi
    ORDER BY rezervasyon_sayisi DESC
", [$current_otel['id'], $baslangic_tarihi . ' 00:00:00', $bitis_tarihi . ' 23:59:59']);

// Günlük doluluk oranı
$gunluk_doluluk = fetchAll("
    SELECT 
        DATE(giris_tarihi) as tarih,
        COUNT(*) as rezervasyon_sayisi,
        (SELECT COUNT(*) FROM oda_numaralari WHERE otel_id = ? AND durum = 'aktif') as toplam_oda,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM oda_numaralari WHERE otel_id = ? AND durum = 'aktif')) * 100, 2) as doluluk_orani
    FROM rezervasyonlar 
    WHERE otel_id = ? 
    AND giris_tarihi BETWEEN ? AND ?
    AND durum IN ('onaylandi', 'check_in')
    GROUP BY DATE(giris_tarihi)
    ORDER BY tarih DESC
    LIMIT 30
", [$current_otel['id'], $current_otel['id'], $current_otel['id'], $baslangic_tarihi, $bitis_tarihi]);

// Müşteri analizi
$musteri_analizi = fetchAll("
    SELECT 
        m.ad,
        m.soyad,
        m.email,
        COUNT(r.id) as rezervasyon_sayisi,
        SUM(r.toplam_tutar) as toplam_harcama,
        MAX(r.olusturma_tarihi) as son_rezervasyon
    FROM musteriler m
    INNER JOIN rezervasyonlar r ON m.id = r.musteri_id
    WHERE r.otel_id = ? 
    AND r.olusturma_tarihi BETWEEN ? AND ?
    GROUP BY m.id, m.ad, m.soyad, m.email
    ORDER BY toplam_harcama DESC
    LIMIT 20
", [$current_otel['id'], $baslangic_tarihi . ' 00:00:00', $bitis_tarihi . ' 23:59:59']);

// Kullanıcının yetkili olduğu otelleri getir
$user_oteller = getUserOteller($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - <?php echo htmlspecialchars($current_otel['otel_adi']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/multi-otel-sidebar.php'; ?>

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
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
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
                            <h1 class="h3 mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Raporlar
                                <small class="text-muted">- <?php echo htmlspecialchars($current_otel['otel_adi']); ?></small>
                            </h1>
                            <p class="text-muted">Otel performans raporları ve istatistikler</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Excel Export
                            </button>
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Yazdır
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarih Filtresi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Tarih Filtresi</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" name="baslangic_tarihi" value="<?php echo htmlspecialchars($baslangic_tarihi); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" name="bitis_tarihi" value="<?php echo htmlspecialchars($bitis_tarihi); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filtrele
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Özet İstatistikler -->
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
                                        <?php echo $stats['toplam_rezervasyon']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                                        <?php echo number_format($stats['toplam_gelir'], 2); ?> ₺
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
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Doluluk Oranı
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        %<?php echo $stats['doluluk_orani']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
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
                                        Aktif Konaklama
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['aktif_konaklama']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bed fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafikler -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Aylık Rezervasyon Trendi</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Oda Tipi Dağılımı</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="roomTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detaylı Raporlar -->
            <div class="row">
                <!-- Oda Tipi İstatistikleri -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bed me-2"></i>Oda Tipi İstatistikleri</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Oda Tipi</th>
                                            <th>Rezervasyon</th>
                                            <th>Gelir</th>
                                            <th>Ortalama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($oda_tipi_istatistikleri as $istatistik): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($istatistik['oda_tipi_adi']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $istatistik['rezervasyon_sayisi']; ?></span></td>
                                            <td><?php echo number_format($istatistik['toplam_gelir'], 2); ?> ₺</td>
                                            <td><?php echo number_format($istatistik['ortalama_fiyat'], 2); ?> ₺</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Müşteri Analizi -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>En Çok Harcayan Müşteriler</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Müşteri</th>
                                            <th>Rezervasyon</th>
                                            <th>Toplam Harcama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($musteri_analizi as $musteri): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($musteri['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-success"><?php echo $musteri['rezervasyon_sayisi']; ?></span></td>
                                            <td><?php echo number_format($musteri['toplam_harcama'], 2); ?> ₺</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Günlük Doluluk -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Günlük Doluluk Oranı</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="occupancyChart" height="50"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
    // Aylık rezervasyon grafiği
    const monthlyData = <?php echo json_encode($aylik_rezervasyonlar); ?>;
    const monthlyLabels = monthlyData.map(item => item.ay);
    const monthlyRezervasyon = monthlyData.map(item => item.rezervasyon_sayisi);
    const monthlyGelir = monthlyData.map(item => parseFloat(item.toplam_gelir));

    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Rezervasyon Sayısı',
                data: monthlyRezervasyon,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Gelir (₺)',
                data: monthlyGelir,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    // Oda tipi dağılım grafiği
    const roomTypeData = <?php echo json_encode($oda_tipi_istatistikleri); ?>;
    const roomTypeLabels = roomTypeData.map(item => item.oda_tipi_adi);
    const roomTypeRezervasyon = roomTypeData.map(item => item.rezervasyon_sayisi);

    new Chart(document.getElementById('roomTypeChart'), {
        type: 'doughnut',
        data: {
            labels: roomTypeLabels,
            datasets: [{
                data: roomTypeRezervasyon,
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
                    position: 'bottom',
                }
            }
        }
    });

    // Günlük doluluk grafiği
    const occupancyData = <?php echo json_encode($gunluk_doluluk); ?>;
    const occupancyLabels = occupancyData.map(item => item.tarih);
    const occupancyRates = occupancyData.map(item => item.doluluk_orani);

    new Chart(document.getElementById('occupancyChart'), {
        type: 'bar',
        data: {
            labels: occupancyLabels,
            datasets: [{
                label: 'Doluluk Oranı (%)',
                data: occupancyRates,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    function exportToExcel() {
        // Excel export fonksiyonu
        window.location.href = 'ajax/export-reports.php?' + new URLSearchParams(window.location.search).toString();
    }
    </script>
</body>
</html>
