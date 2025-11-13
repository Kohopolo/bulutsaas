<?php
/**
 * Performans Metrikleri Dashboard
 * Doluluk oranı, gelir analizi, müşteri memnuniyeti için gelişmiş metrikler
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/performance-metrics-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('performans_metrikleri')) {
    $_SESSION['error_message'] = 'Performans metrikleri görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Performans Metrikleri sistemi
$performanceSystem = new PerformanceMetricsSystem($pdo);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'calculate_metrics') {
            $date = sanitizeString($_POST['date'] ?? date('Y-m-d'));
            $metric_type = sanitizeString($_POST['metric_type'] ?? 'all');
            
            switch ($metric_type) {
                case 'occupancy':
                    $result = $performanceSystem->calculateOccupancyMetrics($date);
                    break;
                case 'revenue':
                    $result = $performanceSystem->calculateRevenueMetrics($date);
                    break;
                case 'customer':
                    $result = $performanceSystem->calculateCustomerSatisfactionMetrics($date);
                    break;
                case 'operational':
                    $result = $performanceSystem->calculateOperationalMetrics($date);
                    break;
                case 'all':
                default:
                    // Tüm metrikleri hesapla
                    $occupancyResult = $performanceSystem->calculateOccupancyMetrics($date);
                    $revenueResult = $performanceSystem->calculateRevenueMetrics($date);
                    $customerResult = $performanceSystem->calculateCustomerSatisfactionMetrics($date);
                    $operationalResult = $performanceSystem->calculateOperationalMetrics($date);
                    
                    $result = [
                        'success' => true,
                        'message' => 'Tüm performans metrikleri hesaplandı'
                    ];
                    break;
            }
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'generate_report') {
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            $category = sanitizeString($_POST['category'] ?? 'genel');
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $performanceSystem->generatePerformanceReport($start_date, $end_date, $category);
            
            if ($result['success']) {
                $success_message = $result['message'];
                $_SESSION['report_id'] = $result['report_id'];
            } else {
                $error_message = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Bugünkü tarih
$bugun = date('Y-m-d');
$bugun_tarih = date('d.m.Y');

// Son 30 günlük verileri getir
$son_30_gun = date('Y-m-d', strtotime('-30 days'));

// Doluluk oranı metrikleri
$doluluk_metrikleri = fetchAll("
    SELECT 
        dom.*,
        ot.oda_tipi_adi,
        ROUND((dom.dolu_oda_sayisi / dom.toplam_oda_sayisi) * 100, 2) as hesaplanan_doluluk_orani
    FROM doluluk_orani_metrikleri dom
    LEFT JOIN oda_tipleri ot ON dom.oda_tipi_id = ot.id
    WHERE dom.tarih >= ?
    ORDER BY dom.tarih DESC, dom.oda_tipi_id
    LIMIT 100
", [$son_30_gun]);

// Gelir analizi metrikleri
$gelir_metrikleri = fetchAll("
    SELECT *
    FROM gelir_analizi_metrikleri
    WHERE tarih >= ?
    ORDER BY tarih DESC, gelir_kategorisi
    LIMIT 100
", [$son_30_gun]);

// Müşteri memnuniyeti metrikleri
$musteri_metrikleri = fetchAll("
    SELECT *
    FROM musteri_memnuniyeti_metrikleri
    WHERE tarih >= ?
    ORDER BY tarih DESC, memnuniyet_kaynak
    LIMIT 100
", [$son_30_gun]);

// Operasyonel performans metrikleri
$operasyon_metrikleri = fetchAll("
    SELECT *
    FROM operasyonel_performans_metrikleri
    WHERE tarih >= ?
    ORDER BY tarih DESC, departman, metrik_adi
    LIMIT 100
", [$son_30_gun]);

// Benchmark verileri
$benchmark_verileri = fetchAll("
    SELECT *
    FROM performans_benchmark
    ORDER BY benchmark_tarihi DESC
    LIMIT 20
");

// Son raporlar
$son_raporlar = fetchAll("
    SELECT pr.*, k.ad as olusturan_adi, k.soyad as olusturan_soyadi
    FROM performans_raporlari pr
    LEFT JOIN kullanicilar k ON pr.olusturan_kullanici_id = k.id
    ORDER BY pr.olusturma_tarihi DESC
    LIMIT 10
");

// Kategoriler
$kategoriler = [
    'doluluk' => 'Doluluk Oranı',
    'gelir' => 'Gelir Analizi',
    'musteri' => 'Müşteri Memnuniyeti',
    'operasyon' => 'Operasyonel Performans',
    'finansal' => 'Finansal Performans',
    'pazar' => 'Pazar Analizi',
    'genel' => 'Genel'
];

// Rapor türleri
$rapor_turleri = [
    'gunluk' => 'Günlük',
    'haftalik' => 'Haftalık',
    'aylik' => 'Aylık',
    'yillik' => 'Yıllık',
    'ozel' => 'Özel'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performans Metrikleri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-card.doluluk { border-left-color: #007bff; }
        .metric-card.gelir { border-left-color: #28a745; }
        .metric-card.musteri { border-left-color: #17a2b8; }
        .metric-card.operasyon { border-left-color: #ffc107; }
        .metric-card.finansal { border-left-color: #6f42c1; }
        .metric-card.pazar { border-left-color: #fd7e14; }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .metric-change {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .change-positive { color: #28a745; }
        .change-negative { color: #dc3545; }
        .change-neutral { color: #6c757d; }
        
        .status-excellent { color: #28a745; }
        .status-good { color: #17a2b8; }
        .status-warning { color: #ffc107; }
        .status-danger { color: #dc3545; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .benchmark-card {
            border-left: 4px solid #6f42c1;
        }
        
        .report-card {
            border-left: 4px solid #fd7e14;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar me-2"></i>Performans Metrikleri
                        <small class="text-muted">Doluluk Oranı, Gelir Analizi, Müşteri Memnuniyeti</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#calculateMetricsModal">
                                <i class="fas fa-calculator"></i> Metrikleri Hesapla
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                                <i class="fas fa-file-alt"></i> Rapor Oluştur
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filtrele
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?category=doluluk">Doluluk Oranı</a></li>
                                <li><a class="dropdown-item" href="?category=gelir">Gelir Analizi</a></li>
                                <li><a class="dropdown-item" href="?category=musteri">Müşteri Memnuniyeti</a></li>
                                <li><a class="dropdown-item" href="?category=operasyon">Operasyonel Performans</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="performans-metrikleri.php">Tümü</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Özet Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="card metric-card doluluk">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Ortalama Doluluk Oranı</h6>
                                        <div class="metric-value status-excellent">
                                            <?php 
                                            $ortalama_doluluk = 0;
                                            if (!empty($doluluk_metrikleri)) {
                                                $toplam_doluluk = 0;
                                                $sayac = 0;
                                                foreach ($doluluk_metrikleri as $metrik) {
                                                    if ($metrik['hesaplanan_doluluk_orani'] > 0) {
                                                        $toplam_doluluk += $metrik['hesaplanan_doluluk_orani'];
                                                        $sayac++;
                                                    }
                                                }
                                                $ortalama_doluluk = $sayac > 0 ? round($toplam_doluluk / $sayac, 2) : 0;
                                            }
                                            echo $ortalama_doluluk . '%';
                                            ?>
                                        </div>
                                        <small class="text-muted">Son 30 gün</small>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-bed fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="card metric-card gelir">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Toplam Gelir</h6>
                                        <div class="metric-value status-good">
                                            <?php 
                                            $toplam_gelir = 0;
                                            if (!empty($gelir_metrikleri)) {
                                                foreach ($gelir_metrikleri as $metrik) {
                                                    $toplam_gelir += $metrik['toplam_gelir'];
                                                }
                                            }
                                            echo number_format($toplam_gelir, 0, ',', '.') . ' ₺';
                                            ?>
                                        </div>
                                        <small class="text-muted">Son 30 gün</small>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-lira-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="card metric-card musteri">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Müşteri Memnuniyeti</h6>
                                        <div class="metric-value status-good">
                                            <?php 
                                            $ortalama_memnuniyet = 0;
                                            if (!empty($musteri_metrikleri)) {
                                                $toplam_puan = 0;
                                                $sayac = 0;
                                                foreach ($musteri_metrikleri as $metrik) {
                                                    if ($metrik['ortalama_puan'] > 0) {
                                                        $toplam_puan += $metrik['ortalama_puan'];
                                                        $sayac++;
                                                    }
                                                }
                                                $ortalama_memnuniyet = $sayac > 0 ? round($toplam_puan / $sayac, 2) : 0;
                                            }
                                            echo $ortalama_memnuniyet . '/5';
                                            ?>
                                        </div>
                                        <small class="text-muted">Son 30 gün</small>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-star fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="card metric-card operasyon">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Operasyonel Performans</h6>
                                        <div class="metric-value status-warning">
                                            <?php 
                                            $ortalama_performans = 0;
                                            if (!empty($operasyon_metrikleri)) {
                                                $toplam_performans = 0;
                                                $sayac = 0;
                                                foreach ($operasyon_metrikleri as $metrik) {
                                                    if ($metrik['performans_orani'] > 0) {
                                                        $toplam_performans += $metrik['performans_orani'];
                                                        $sayac++;
                                                    }
                                                }
                                                $ortalama_performans = $sayac > 0 ? round($toplam_performans / $sayac, 2) : 0;
                                            }
                                            echo $ortalama_performans . '%';
                                            ?>
                                        </div>
                                        <small class="text-muted">Son 30 gün</small>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-cogs fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Doluluk Oranı Trendi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="occupancyTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-chart-area me-2"></i>Gelir Trendi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Benchmark Verileri -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card benchmark-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-trophy me-2"></i>Benchmark Verileri
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Metrik</th>
                                                <th>Sektör Ortalaması</th>
                                                <th>Sektör En İyisi</th>
                                                <th>Kendi Ortalamamız</th>
                                                <th>Kendi En İyimiz</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($benchmark_verileri as $benchmark): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($benchmark['metrik_adi']); ?></td>
                                                <td><?php echo number_format($benchmark['sektor_ortalamasi'], 2); ?></td>
                                                <td><?php echo number_format($benchmark['sektor_en_iyi'], 2); ?></td>
                                                <td><?php echo number_format($benchmark['kendi_ortalamamiz'], 2); ?></td>
                                                <td><?php echo number_format($benchmark['kendi_en_iyimiz'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    $durum = 'warning';
                                                    if ($benchmark['kendi_ortalamamiz'] >= $benchmark['sektor_en_iyi']) {
                                                        $durum = 'success';
                                                    } elseif ($benchmark['kendi_ortalamamiz'] >= $benchmark['sektor_ortalamasi']) {
                                                        $durum = 'info';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $durum; ?>">
                                                        <?php 
                                                        if ($durum == 'success') echo 'Mükemmel';
                                                        elseif ($durum == 'info') echo 'İyi';
                                                        else echo 'Geliştirilebilir';
                                                        ?>
                                                    </span>
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

                <!-- Son Raporlar -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card report-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-file-alt me-2"></i>Son Raporlar
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rapor Adı</th>
                                                <th>Tür</th>
                                                <th>Kategori</th>
                                                <th>Tarih Aralığı</th>
                                                <th>Oluşturan</th>
                                                <th>Oluşturma Tarihi</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_raporlar as $rapor): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rapor['rapor_adi']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $rapor_turleri[$rapor['rapor_turu']] ?? ucfirst($rapor['rapor_turu']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo $kategoriler[$rapor['rapor_kategorisi']] ?? ucfirst($rapor['rapor_kategorisi']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $rapor['baslangic_tarihi'] . ' - ' . $rapor['bitis_tarihi']; ?></td>
                                                <td><?php echo htmlspecialchars($rapor['olusturan_adi'] . ' ' . $rapor['olusturan_soyadi']); ?></td>
                                                <td><?php echo formatTurkishDate($rapor['olusturma_tarihi'], 'd.m.Y H:i'); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewReport(<?php echo $rapor['id']; ?>)">
                                                        <i class="fas fa-eye"></i> Görüntüle
                                                    </button>
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
            </main>
        </div>
    </div>

    <!-- Metrikleri Hesapla Modal -->
    <div class="modal fade" id="calculateMetricsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Performans Metriklerini Hesapla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="calculate_metrics">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $bugun; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="metric_type" class="form-label">Metrik Türü</label>
                            <select class="form-select" id="metric_type" name="metric_type" required>
                                <option value="all">Tüm Metrikler</option>
                                <option value="occupancy">Doluluk Oranı</option>
                                <option value="revenue">Gelir Analizi</option>
                                <option value="customer">Müşteri Memnuniyeti</option>
                                <option value="operational">Operasyonel Performans</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Hesapla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rapor Oluştur Modal -->
    <div class="modal fade" id="generateReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Performans Raporu Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_report">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Rapor Kategorisi</label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($kategoriler as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Rapor Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grafikleri oluştur
        document.addEventListener('DOMContentLoaded', function() {
            // Doluluk oranı trend grafiği
            const occupancyCtx = document.getElementById('occupancyTrendChart');
            if (occupancyCtx) {
                new Chart(occupancyCtx, {
                    type: 'line',
                    data: {
                        labels: ['1 Hafta Önce', '6 Gün Önce', '5 Gün Önce', '4 Gün Önce', '3 Gün Önce', '2 Gün Önce', '1 Gün Önce', 'Bugün'],
                        datasets: [{
                            label: 'Doluluk Oranı (%)',
                            data: [65, 72, 68, 75, 80, 78, 82, 85],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }

            // Gelir trend grafiği
            const revenueCtx = document.getElementById('revenueTrendChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: ['1 Hafta Önce', '6 Gün Önce', '5 Gün Önce', '4 Gün Önce', '3 Gün Önce', '2 Gün Önce', '1 Gün Önce', 'Bugün'],
                        datasets: [{
                            label: 'Günlük Gelir (TL)',
                            data: [45000, 52000, 48000, 61000, 58000, 55000, 67000, 72000],
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            borderColor: 'rgb(54, 162, 235)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('tr-TR', {
                                            style: 'currency',
                                            currency: 'TRY',
                                            minimumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        function viewReport(reportId) {
            // Rapor görüntüleme işlemi
            alert('Rapor görüntüleme özelliği geliştirilecek. Rapor ID: ' + reportId);
        }

        // Tarih aralığı için varsayılan değerler
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            document.getElementById('start_date').value = lastWeek.toISOString().split('T')[0];
            document.getElementById('end_date').value = today.toISOString().split('T')[0];
        });
    </script>
</body>
</html>

