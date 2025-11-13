<?php
/**
 * KPI Dashboard - Ana Dashboard
 * Gerçek zamanlı performans metrikleri
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/kpi-dashboard-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('kpi_dashboard')) {
    $_SESSION['error_message'] = 'KPI dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// KPI Dashboard sistemi
$kpiSystem = new KpiDashboardSystem($pdo);

// Dashboard verilerini getir
$dashboardResult = $kpiSystem->getDashboardData();
$dashboardData = $dashboardResult['success'] ? $dashboardResult['data'] : [];

// Gerçek zamanlı metrikleri güncelle
if ($_GET['action'] ?? '' == 'refresh') {
    $updateResult = $kpiSystem->updateRealTimeMetrics();
    if ($updateResult['success']) {
        $success_message = $updateResult['message'];
    } else {
        $error_message = $updateResult['message'];
    }
    // Sayfayı yenile
    header('Location: kpi-dashboard.php');
    exit;
}

// Uyarıları kontrol et
$alertResult = $kpiSystem->checkAlerts();
$activeAlerts = 0;
if ($alertResult['success']) {
    $activeAlerts = $alertResult['triggered_alerts'];
}

// Aktif uyarıları getir
$aktif_uyarilar = fetchAll("
    SELECT kug.*, km.metrik_adi, km.kategori, ku.uyari_renk
    FROM kpi_uyari_gecmisi kug
    LEFT JOIN kpi_metrikleri km ON kug.metrik_id = km.id
    LEFT JOIN kpi_uyarilari ku ON kug.uyari_id = ku.id
    WHERE kug.uyari_durumu = 'aktif'
    ORDER BY kug.olusturma_tarihi DESC
    LIMIT 10
");

// Kategoriler
$kategoriler = [
    'rezervasyon' => 'Rezervasyon',
    'gelir' => 'Gelir',
    'musteri' => 'Müşteri',
    'operasyon' => 'Operasyon',
    'stok' => 'Stok',
    'personel' => 'Personel'
];

// Kategori renkleri
$kategori_renkleri = [
    'rezervasyon' => 'primary',
    'gelir' => 'success',
    'musteri' => 'info',
    'operasyon' => 'warning',
    'stok' => 'secondary',
    'personel' => 'danger'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .kpi-card.rezervasyon { border-left-color: #007bff; }
        .kpi-card.gelir { border-left-color: #28a745; }
        .kpi-card.musteri { border-left-color: #17a2b8; }
        .kpi-card.operasyon { border-left-color: #ffc107; }
        .kpi-card.stok { border-left-color: #6c757d; }
        .kpi-card.personel { border-left-color: #dc3545; }
        
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
        
        .status-success { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-danger { color: #dc3545; }
        .status-info { color: #17a2b8; }
        .status-unknown { color: #6c757d; }
        
        .alert-card {
            border-left: 4px solid #dc3545;
            background: #f8d7da;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .metric-trend {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
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
                        <i class="fas fa-chart-line me-2"></i>KPI Dashboard
                        <small class="text-muted">Gerçek Zamanlı Performans Metrikleri</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" onclick="refreshMetrics()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                            <a href="kpi-yonetimi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog"></i> Yönetim
                            </a>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filtrele
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?category=rezervasyon">Rezervasyon</a></li>
                                <li><a class="dropdown-item" href="?category=gelir">Gelir</a></li>
                                <li><a class="dropdown-item" href="?category=musteri">Müşteri</a></li>
                                <li><a class="dropdown-item" href="?category=operasyon">Operasyon</a></li>
                                <li><a class="dropdown-item" href="?category=stok">Stok</a></li>
                                <li><a class="dropdown-item" href="?category=personel">Personel</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="kpi-dashboard.php">Tümü</a></li>
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

                <!-- Aktif Uyarılar -->
                <?php if (!empty($aktif_uyarilar)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card alert-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Aktif Uyarılar (<?php echo count($aktif_uyarilar); ?>)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($aktif_uyarilar as $uyari): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="alert alert-<?php echo $uyari['uyari_renk'] ?? 'warning'; ?> mb-0">
                                            <strong><?php echo htmlspecialchars($uyari['metrik_adi']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($uyari['uyari_mesaji']); ?></small><br>
                                            <small class="text-muted">Mevcut: <?php echo $uyari['mevcut_deger']; ?> | Eşik: <?php echo $uyari['esik_deger']; ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- KPI Metrikleri -->
                <div class="row">
                    <?php if (empty($dashboardData)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">KPI Metrikleri Yükleniyor...</h4>
                            <p class="text-muted">Lütfen bekleyin veya yenile butonuna tıklayın.</p>
                            <button type="button" class="btn btn-primary" onclick="refreshMetrics()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($dashboardData as $metricData): ?>
                    <?php 
                    $metric = $metricData['metric'];
                    $currentValue = $metricData['current_value'];
                    $changeRate = $metricData['change_rate'];
                    $status = $metricData['status'];
                    $category = $metric['kategori'];
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card kpi-card <?php echo $category; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1">
                                            <?php echo htmlspecialchars($metric['metrik_aciklamasi']); ?>
                                        </h6>
                                        <div class="metric-value status-<?php echo $status; ?>">
                                            <?php 
                                            if ($currentValue !== null) {
                                                echo formatMetricValue($currentValue, $metric['metrik_turu'], $metric['birim']);
                                            } else {
                                                echo '<i class="fas fa-question-circle"></i>';
                                            }
                                            ?>
                                        </div>
                                        <?php if ($changeRate !== null): ?>
                                        <div class="metric-change <?php echo $changeRate > 0 ? 'change-positive' : ($changeRate < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                            <i class="fas fa-arrow-<?php echo $changeRate > 0 ? 'up' : ($changeRate < 0 ? 'down' : 'right'); ?>"></i>
                                            <?php echo abs($changeRate); ?>%
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $kategori_renkleri[$category] ?? 'secondary'; ?>">
                                            <?php echo $kategoriler[$category] ?? ucfirst($category); ?>
                                        </span>
                                        <div class="mt-2">
                                            <i class="fas fa-circle status-<?php echo $status; ?>"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($metric['hedef_deger']): ?>
                                <div class="mt-3">
                                    <div class="progress" style="height: 6px;">
                                        <?php 
                                        $progress = $currentValue ? min(100, ($currentValue / $metric['hedef_deger']) * 100) : 0;
                                        $progressColor = $progress >= 100 ? 'success' : ($progress >= 80 ? 'warning' : 'danger');
                                        ?>
                                        <div class="progress-bar bg-<?php echo $progressColor; ?>" 
                                             style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        Hedef: <?php echo formatMetricValue($metric['hedef_deger'], $metric['metrik_turu'], $metric['birim']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Son güncelleme: <?php echo $metric['son_guncelleme'] ? formatTurkishDate($metric['son_guncelleme'], 'H:i') : 'Bilinmiyor'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Trend Grafikleri -->
                <div class="row mt-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-area me-2"></i>Günlük Rezervasyon Trendi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="reservationTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-chart-line me-2"></i>Günlük Ciro Trendi
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

                <!-- Performans Özeti -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tachometer-alt me-2"></i>Performans Özeti
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="border-end">
                                            <h4 class="text-success"><?php echo count(array_filter($dashboardData, function($m) { return $m['status'] == 'success'; })); ?></h4>
                                            <small class="text-muted">Hedefte</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="border-end">
                                            <h4 class="text-warning"><?php echo count(array_filter($dashboardData, function($m) { return $m['status'] == 'warning'; })); ?></h4>
                                            <small class="text-muted">Dikkat</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="border-end">
                                            <h4 class="text-danger"><?php echo count(array_filter($dashboardData, function($m) { return $m['status'] == 'danger'; })); ?></h4>
                                            <small class="text-muted">Kritik</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-info"><?php echo count(array_filter($dashboardData, function($m) { return $m['status'] == 'info'; })); ?></h4>
                                        <small class="text-muted">Bilgi</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yenile Butonu -->
    <button type="button" class="btn btn-primary refresh-btn" onclick="refreshMetrics()" title="Metrikleri Yenile">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Metrik değerini formatla
        function formatMetricValue(value, type, unit) {
            if (value === null || value === undefined) return 'N/A';
            
            switch (type) {
                case 'para':
                    return new Intl.NumberFormat('tr-TR', { 
                        style: 'currency', 
                        currency: 'TRY' 
                    }).format(value);
                case 'yuzde':
                    return value + '%';
                case 'sure':
                    return value + ' ' + (unit || 'dakika');
                default:
                    return new Intl.NumberFormat('tr-TR').format(value) + (unit ? ' ' + unit : '');
            }
        }

        // Metrikleri yenile
        function refreshMetrics() {
            window.location.href = 'kpi-dashboard.php?action=refresh';
        }

        // Grafikleri oluştur
        document.addEventListener('DOMContentLoaded', function() {
            // Rezervasyon trend grafiği
            const reservationCtx = document.getElementById('reservationTrendChart');
            if (reservationCtx) {
                new Chart(reservationCtx, {
                    type: 'line',
                    data: {
                        labels: ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'],
                        datasets: [{
                            label: 'Rezervasyon Sayısı',
                            data: [12, 19, 3, 5, 2, 3, 8],
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
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Ciro trend grafiği
            const revenueCtx = document.getElementById('revenueTrendChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'],
                        datasets: [{
                            label: 'Günlük Ciro (TL)',
                            data: [45000, 52000, 38000, 61000, 48000, 55000, 67000],
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

        // Otomatik yenileme (5 dakikada bir)
        setInterval(function() {
            // Sadece sayfa aktifse yenile
            if (!document.hidden) {
                refreshMetrics();
            }
        }, 300000); // 5 dakika
    </script>
</body>
</html>

<?php
// Yardımcı fonksiyon
function formatMetricValue($value, $type, $unit) {
    if ($value === null || $value === undefined) return 'N/A';
    
    switch ($type) {
        case 'para':
            return number_format($value, 2, ',', '.') . ' ₺';
        case 'yuzde':
            return number_format($value, 1, ',', '.') . '%';
        case 'sure':
            return number_format($value, 0, ',', '.') . ' ' . ($unit ?: 'dakika');
        default:
            return number_format($value, 0, ',', '.') . ($unit ? ' ' . $unit : '');
    }
}
?>

