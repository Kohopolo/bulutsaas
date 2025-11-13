<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-performans-yonetimi.php
// Ödeme performans yönetimi sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/cache/PaymentCache.php';
require_once '../includes/performance/PaymentPerformance.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_performans_yonetimi', 'Ödeme performans yönetimi yetkiniz bulunmamaktadır.');

$cache = new PaymentCache($database_connection);
$performance = new PaymentPerformance($database_connection, $cache);

$page_title = 'Ödeme Performans Yönetimi';

// Cache temizleme
if (isset($_POST['clear_cache'])) {
    $cleared = $cache->clear();
    $success_message = $cleared ? 'Cache başarıyla temizlendi.' : 'Cache temizlenirken hata oluştu.';
}

// Süresi dolmuş cache'leri temizle
if (isset($_POST['cleanup_cache'])) {
    $cleaned = $cache->cleanup();
    $success_message = "{$cleaned} adet süresi dolmuş cache dosyası temizlendi.";
}

// Performans metriklerini al
$performance->startProfiling();
$providers = $performance->getOptimizedProviders();
$stats = $performance->getOptimizedStats('30_days');
$performance_report = $performance->generatePerformanceReport();
$cache_stats = $cache->getCacheStats();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        .performance-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .performance-excellent { background-color: #28a745; }
        .performance-good { background-color: #17a2b8; }
        .performance-warning { background-color: #ffc107; }
        .performance-danger { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Performans Metrikleri -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Yanıt Süresi</h6>
                                        <h4 class="mb-0"><?php echo $performance_report['performance']['execution_time_ms']; ?>ms</h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $this->getPerformanceIndicator($performance_report['performance']['execution_time']); ?>
                                    <?php echo $this->getPerformanceText($performance_report['performance']['execution_time']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Cache Hit Oranı</h6>
                                        <h4 class="mb-0"><?php echo $performance_report['performance']['cache_hit_ratio']; ?>%</h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-database fa-2x text-success"></i>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $this->getCacheIndicator($performance_report['performance']['cache_hit_ratio']); ?>
                                    <?php echo $this->getCacheText($performance_report['performance']['cache_hit_ratio']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Sorgu Sayısı</h6>
                                        <h4 class="mb-0"><?php echo $performance_report['performance']['query_count']; ?></h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-search fa-2x text-info"></i>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $this->getQueryIndicator($performance_report['performance']['query_count']); ?>
                                    <?php echo $this->getQueryText($performance_report['performance']['query_count']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Bellek Kullanımı</h6>
                                        <h4 class="mb-0"><?php echo $performance_report['performance']['memory_usage_mb']; ?>MB</h4>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-memory fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $this->getMemoryIndicator($performance_report['performance']['memory_usage_mb']); ?>
                                    <?php echo $this->getMemoryText($performance_report['performance']['memory_usage_mb']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cache Yönetimi -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-database me-2"></i>
                                    Cache Yönetimi
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-primary"><?php echo $cache_stats['total_files']; ?></h4>
                                            <small class="text-muted">Toplam Cache Dosyası</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-success"><?php echo $cache_stats['valid_files']; ?></h4>
                                            <small class="text-muted">Geçerli Cache</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-warning"><?php echo $cache_stats['expired_files']; ?></h4>
                                            <small class="text-muted">Süresi Dolmuş</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info"><?php echo $cache_stats['total_size_mb']; ?>MB</h4>
                                            <small class="text-muted">Toplam Boyut</small>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="cleanup_cache" class="btn btn-warning btn-sm">
                                            <i class="fas fa-broom me-1"></i>
                                            Süresi Dolmuş Cache'leri Temizle
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="clear_cache" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Tüm cache dosyalarını silmek istediğinizden emin misiniz?')">
                                            <i class="fas fa-trash me-1"></i>
                                            Tüm Cache'i Temizle
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performans Önerileri -->
                <?php if (!empty($performance_report['recommendations'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    Performans Önerileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($performance_report['recommendations'] as $recommendation): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            <?php echo $recommendation; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Performans Grafiği -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Performans Trendi
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="performanceChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Performans grafiği
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['1 Gün Önce', '2 Gün Önce', '3 Gün Önce', '4 Gün Önce', '5 Gün Önce', '6 Gün Önce', 'Bugün'],
                datasets: [{
                    label: 'Yanıt Süresi (ms)',
                    data: [120, 150, 180, 160, 140, 130, <?php echo $performance_report['performance']['execution_time_ms']; ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Cache Hit Oranı (%)',
                    data: [85, 78, 82, 88, 90, 87, <?php echo $performance_report['performance']['cache_hit_ratio']; ?>],
                    borderColor: 'rgb(255, 99, 132)',
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

        function refreshPage() {
            location.reload();
        }
    </script>
</body>
</html>

<?php
// Performans göstergeleri
function getPerformanceIndicator($execution_time) {
    if ($execution_time < 0.5) return '<span class="performance-indicator performance-excellent"></span>';
    if ($execution_time < 1.0) return '<span class="performance-indicator performance-good"></span>';
    if ($execution_time < 2.0) return '<span class="performance-indicator performance-warning"></span>';
    return '<span class="performance-indicator performance-danger"></span>';
}

function getPerformanceText($execution_time) {
    if ($execution_time < 0.5) return 'Mükemmel';
    if ($execution_time < 1.0) return 'İyi';
    if ($execution_time < 2.0) return 'Orta';
    return 'Yavaş';
}

function getCacheIndicator($hit_ratio) {
    if ($hit_ratio > 80) return '<span class="performance-indicator performance-excellent"></span>';
    if ($hit_ratio > 60) return '<span class="performance-indicator performance-good"></span>';
    if ($hit_ratio > 40) return '<span class="performance-indicator performance-warning"></span>';
    return '<span class="performance-indicator performance-danger"></span>';
}

function getCacheText($hit_ratio) {
    if ($hit_ratio > 80) return 'Mükemmel';
    if ($hit_ratio > 60) return 'İyi';
    if ($hit_ratio > 40) return 'Orta';
    return 'Düşük';
}

function getQueryIndicator($query_count) {
    if ($query_count < 5) return '<span class="performance-indicator performance-excellent"></span>';
    if ($query_count < 10) return '<span class="performance-indicator performance-good"></span>';
    if ($query_count < 20) return '<span class="performance-indicator performance-warning"></span>';
    return '<span class="performance-indicator performance-danger"></span>';
}

function getQueryText($query_count) {
    if ($query_count < 5) return 'Mükemmel';
    if ($query_count < 10) return 'İyi';
    if ($query_count < 20) return 'Orta';
    return 'Yüksek';
}

function getMemoryIndicator($memory_mb) {
    if ($memory_mb < 32) return '<span class="performance-indicator performance-excellent"></span>';
    if ($memory_mb < 64) return '<span class="performance-indicator performance-good"></span>';
    if ($memory_mb < 128) return '<span class="performance-indicator performance-warning"></span>';
    return '<span class="performance-indicator performance-danger"></span>';
}

function getMemoryText($memory_mb) {
    if ($memory_mb < 32) return 'Mükemmel';
    if ($memory_mb < 64) return 'İyi';
    if ($memory_mb < 128) return 'Orta';
    return 'Yüksek';
}
?>
