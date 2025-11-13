<?php
/**
 * Performans Optimizasyonu Dashboard
 * Sistem hızı ve veritabanı optimizasyonu
 */

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
if (!hasDetailedPermission('sistem_yonetimi')) {
    $_SESSION['error_message'] = 'Sistem yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';
$optimization_results = [];

// Performans optimizasyonu işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'run_performance_audit') {
            $optimization_results = runPerformanceAudit();
            $success_message = 'Performans denetimi tamamlandı.';
        }
        
        if ($action == 'optimize_database') {
            $optimization_results['database'] = optimizeDatabase();
        }
        
        if ($action == 'optimize_queries') {
            $optimization_results['queries'] = optimizeQueries();
        }
        
        if ($action == 'optimize_cache') {
            $optimization_results['cache'] = optimizeCache();
        }
        
        if ($action == 'optimize_files') {
            $optimization_results['files'] = optimizeFiles();
        }
        
        if ($action == 'optimize_images') {
            $optimization_results['images'] = optimizeImages();
        }
        
        if ($action == 'optimize_css_js') {
            $optimization_results['css_js'] = optimizeCSSJS();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Kapsamlı performans denetimi
 */
function runPerformanceAudit()
{
    $results = [];
    
    // 1. Veritabanı performansı
    $results['database'] = analyzeDatabasePerformance();
    
    // 2. Query optimizasyonu
    $results['queries'] = analyzeQueryPerformance();
    
    // 3. Cache performansı
    $results['cache'] = analyzeCachePerformance();
    
    // 4. Dosya performansı
    $results['files'] = analyzeFilePerformance();
    
    // 5. Resim optimizasyonu
    $results['images'] = analyzeImagePerformance();
    
    // 6. CSS/JS optimizasyonu
    $results['css_js'] = analyzeCSSJSPerformance();
    
    // 7. Memory kullanımı
    $results['memory'] = analyzeMemoryUsage();
    
    // 8. Server performansı
    $results['server'] = analyzeServerPerformance();
    
    return $results;
}

/**
 * Veritabanı performansını analiz et
 */
function analyzeDatabasePerformance()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Veritabanı boyutu
        $stmt = $pdo->query("
            SELECT 
                table_schema as 'Database',
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            GROUP BY table_schema
        ");
        $db_size = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['database_size'] = [
            'status' => $db_size['Size (MB)'] > 100 ? 'warning' : 'success',
            'message' => "Veritabanı boyutu: {$db_size['Size (MB)']} MB"
        ];
        
        // Tablo boyutları
        $stmt = $pdo->query("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
            LIMIT 10
        ");
        $large_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tests['large_tables'] = [
            'status' => 'info',
            'message' => 'En büyük tablolar: ' . implode(', ', array_column($large_tables, 'table_name'))
        ];
        
        // İndeks kullanımı
        $stmt = $pdo->query("
            SELECT 
                table_name,
                COUNT(*) as index_count
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE()
            GROUP BY table_name
            ORDER BY index_count DESC
            LIMIT 10
        ");
        $index_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tests['index_usage'] = [
            'status' => 'success',
            'message' => 'İndeks kullanımı analiz edildi'
        ];
        
        // Slow query log kontrolü
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
        $slow_log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['slow_query_log'] = [
            'status' => $slow_log['Value'] === 'ON' ? 'success' : 'warning',
            'message' => $slow_log['Value'] === 'ON' ? 'Slow query log aktif' : 'Slow query log pasif'
        ];
        
    } catch (Exception $e) {
        $tests['database_analysis'] = [
            'status' => 'error',
            'message' => 'Veritabanı analizi hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Query performansını analiz et
 */
function analyzeQueryPerformance()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // En yavaş sorgular
        $stmt = $pdo->query("
            SELECT 
                query_time,
                lock_time,
                rows_sent,
                rows_examined,
                sql_text
            FROM mysql.slow_log 
            WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY query_time DESC
            LIMIT 5
        ");
        $slow_queries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tests['slow_queries'] = [
            'status' => count($slow_queries) > 0 ? 'warning' : 'success',
            'message' => count($slow_queries) > 0 ? 
                count($slow_queries) . ' yavaş sorgu tespit edildi' : 
                'Yavaş sorgu tespit edilmedi'
        ];
        
        // Query cache durumu
        $stmt = $pdo->query("SHOW STATUS LIKE 'Qcache%'");
        $query_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $hit_rate = 0;
        if (isset($query_cache['Qcache_hits']) && isset($query_cache['Qcache_inserts'])) {
            $total = $query_cache['Qcache_hits'] + $query_cache['Qcache_inserts'];
            if ($total > 0) {
                $hit_rate = round(($query_cache['Qcache_hits'] / $total) * 100, 2);
            }
        }
        
        $tests['query_cache'] = [
            'status' => $hit_rate > 50 ? 'success' : 'warning',
            'message' => "Query cache hit rate: %{$hit_rate}"
        ];
        
        // Connection durumu
        $stmt = $pdo->query("SHOW STATUS LIKE 'Connections'");
        $connections = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['connections'] = [
            'status' => 'info',
            'message' => "Toplam bağlantı: {$connections['Value']}"
        ];
        
    } catch (Exception $e) {
        $tests['query_analysis'] = [
            'status' => 'error',
            'message' => 'Query analizi hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Cache performansını analiz et
 */
function analyzeCachePerformance()
{
    $tests = [];
    
    // Cache dizinleri kontrolü
    $cache_dirs = ['../cache/', '../cache/images/', '../cache/templates/'];
    $cache_size = 0;
    $cache_files = 0;
    
    foreach ($cache_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            $cache_files += count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $cache_size += filesize($file);
                }
            }
        }
    }
    
    $cache_size_mb = round($cache_size / 1024 / 1024, 2);
    
    $tests['cache_size'] = [
        'status' => $cache_size_mb > 50 ? 'warning' : 'success',
        'message' => "Cache boyutu: {$cache_size_mb} MB ({$cache_files} dosya)"
    ];
    
    // Cache dosya yaşları
    $old_cache_files = 0;
    foreach ($cache_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 86400) { // 1 gün
                    $old_cache_files++;
                }
            }
        }
    }
    
    $tests['old_cache_files'] = [
        'status' => $old_cache_files > 0 ? 'warning' : 'success',
        'message' => $old_cache_files > 0 ? 
            "{$old_cache_files} eski cache dosyası temizlenebilir" : 
            'Cache dosyaları güncel'
    ];
    
    return $tests;
}

/**
 * Dosya performansını analiz et
 */
function analyzeFilePerformance()
{
    $tests = [];
    
    // Upload dizini boyutu
    $upload_dirs = ['../uploads/', '../uploads/room-types/', '../uploads/slider/'];
    $upload_size = 0;
    $upload_files = 0;
    
    foreach ($upload_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            $upload_files += count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $upload_size += filesize($file);
                }
            }
        }
    }
    
    $upload_size_mb = round($upload_size / 1024 / 1024, 2);
    
    $tests['upload_size'] = [
        'status' => $upload_size_mb > 100 ? 'warning' : 'success',
        'message' => "Upload boyutu: {$upload_size_mb} MB ({$upload_files} dosya)"
    ];
    
    // Log dosyaları boyutu
    $log_files = glob('../logs/*.log');
    $log_size = 0;
    
    foreach ($log_files as $file) {
        if (is_file($file)) {
            $log_size += filesize($file);
        }
    }
    
    $log_size_mb = round($log_size / 1024 / 1024, 2);
    
    $tests['log_size'] = [
        'status' => $log_size_mb > 10 ? 'warning' : 'success',
        'message' => "Log boyutu: {$log_size_mb} MB"
    ];
    
    return $tests;
}

/**
 * Resim performansını analiz et
 */
function analyzeImagePerformance()
{
    $tests = [];
    
    // Resim dosyaları
    $image_dirs = ['../uploads/room-types/', '../uploads/slider/', '../assets/images/'];
    $total_images = 0;
    $large_images = 0;
    $total_image_size = 0;
    
    foreach ($image_dirs as $dir) {
        if (is_dir($dir)) {
            $images = glob($dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            $total_images += count($images);
            
            foreach ($images as $image) {
                if (is_file($image)) {
                    $size = filesize($image);
                    $total_image_size += $size;
                    
                    if ($size > 1024 * 1024) { // 1MB'den büyük
                        $large_images++;
                    }
                }
            }
        }
    }
    
    $total_image_size_mb = round($total_image_size / 1024 / 1024, 2);
    
    $tests['image_count'] = [
        'status' => 'info',
        'message' => "Toplam resim: {$total_images} ({$total_image_size_mb} MB)"
    ];
    
    $tests['large_images'] = [
        'status' => $large_images > 0 ? 'warning' : 'success',
        'message' => $large_images > 0 ? 
            "{$large_images} büyük resim optimize edilebilir" : 
            'Resimler optimize edilmiş'
    ];
    
    return $tests;
}

/**
 * CSS/JS performansını analiz et
 */
function analyzeCSSJSPerformance()
{
    $tests = [];
    
    // CSS dosyaları
    $css_files = glob('../assets/css/*.css');
    $css_size = 0;
    
    foreach ($css_files as $file) {
        if (is_file($file)) {
            $css_size += filesize($file);
        }
    }
    
    $css_size_kb = round($css_size / 1024, 2);
    
    $tests['css_size'] = [
        'status' => $css_size_kb > 100 ? 'warning' : 'success',
        'message' => "CSS boyutu: {$css_size_kb} KB"
    ];
    
    // JS dosyaları
    $js_files = glob('../assets/js/*.js');
    $js_size = 0;
    
    foreach ($js_files as $file) {
        if (is_file($file)) {
            $js_size += filesize($file);
        }
    }
    
    $js_size_kb = round($js_size / 1024, 2);
    
    $tests['js_size'] = [
        'status' => $js_size_kb > 100 ? 'warning' : 'success',
        'message' => "JS boyutu: {$js_size_kb} KB"
    ];
    
    return $tests;
}

/**
 * Memory kullanımını analiz et
 */
function analyzeMemoryUsage()
{
    $tests = [];
    
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    $memory_limit = ini_get('memory_limit');
    
    $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);
    $memory_peak_mb = round($memory_peak / 1024 / 1024, 2);
    
    $tests['current_memory'] = [
        'status' => $memory_usage_mb > 50 ? 'warning' : 'success',
        'message' => "Mevcut memory: {$memory_usage_mb} MB"
    ];
    
    $tests['peak_memory'] = [
        'status' => $memory_peak_mb > 100 ? 'warning' : 'success',
        'message' => "Peak memory: {$memory_peak_mb} MB"
    ];
    
    $tests['memory_limit'] = [
        'status' => 'info',
        'message' => "Memory limit: {$memory_limit}"
    ];
    
    return $tests;
}

/**
 * Server performansını analiz et
 */
function analyzeServerPerformance()
{
    $tests = [];
    
    // PHP versiyonu
    $php_version = PHP_VERSION;
    $tests['php_version'] = [
        'status' => version_compare($php_version, '8.0.0', '>=') ? 'success' : 'warning',
        'message' => "PHP versiyonu: {$php_version}"
    ];
    
    // OPcache durumu
    $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status();
    $tests['opcache'] = [
        'status' => $opcache_enabled ? 'success' : 'warning',
        'message' => $opcache_enabled ? 'OPcache aktif' : 'OPcache pasif'
    ];
    
    // Gzip compression
    $gzip_enabled = extension_loaded('zlib');
    $tests['gzip'] = [
        'status' => $gzip_enabled ? 'success' : 'warning',
        'message' => $gzip_enabled ? 'Gzip compression aktif' : 'Gzip compression pasif'
    ];
    
    return $tests;
}

/**
 * Veritabanını optimize et
 */
function optimizeDatabase()
{
    global $pdo;
    
    $results = [];
    
    try {
        // Tabloları optimize et
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $optimized_tables = 0;
        foreach ($tables as $table) {
            $pdo->exec("OPTIMIZE TABLE `{$table}`");
            $optimized_tables++;
        }
        
        $results['table_optimization'] = [
            'status' => 'success',
            'message' => "{$optimized_tables} tablo optimize edildi"
        ];
        
        // İndeksleri analiz et
        $pdo->exec("ANALYZE TABLE " . implode(', ', array_map(function($table) { return "`{$table}`"; }, $tables)));
        
        $results['index_analysis'] = [
            'status' => 'success',
            'message' => 'İndeks analizi tamamlandı'
        ];
        
    } catch (Exception $e) {
        $results['optimization_error'] = [
            'status' => 'error',
            'message' => 'Optimizasyon hatası: ' . $e->getMessage()
        ];
    }
    
    return $results;
}

/**
 * Cache'i optimize et
 */
function optimizeCache()
{
    $results = [];
    
    // Eski cache dosyalarını temizle
    $cache_dirs = ['../cache/', '../cache/images/', '../cache/templates/'];
    $cleaned_files = 0;
    
    foreach ($cache_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 86400) { // 1 gün
                    unlink($file);
                    $cleaned_files++;
                }
            }
        }
    }
    
    $results['cache_cleanup'] = [
        'status' => 'success',
        'message' => "{$cleaned_files} eski cache dosyası temizlendi"
    ];
    
    return $results;
}

/**
 * Dosyaları optimize et
 */
function optimizeFiles()
{
    $results = [];
    
    // Log dosyalarını temizle
    $log_files = glob('../logs/*.log');
    $cleaned_logs = 0;
    
    foreach ($log_files as $log_file) {
        if (is_file($log_file) && filesize($log_file) > 10 * 1024 * 1024) { // 10MB'den büyük
            file_put_contents($log_file, '');
            $cleaned_logs++;
        }
    }
    
    $results['log_cleanup'] = [
        'status' => 'success',
        'message' => "{$cleaned_logs} büyük log dosyası temizlendi"
    ];
    
    return $results;
}

/**
 * Resimleri optimize et
 */
function optimizeImages()
{
    $results = [];
    
    // Bu fonksiyon resim optimizasyonu için placeholder
    // Gerçek implementasyon için ImageMagick veya GD kullanılabilir
    
    $results['image_optimization'] = [
        'status' => 'info',
        'message' => 'Resim optimizasyonu için ImageMagick/GD gerekli'
    ];
    
    return $results;
}

/**
 * CSS/JS'i optimize et
 */
function optimizeCSSJS()
{
    $results = [];
    
    // CSS minification (basit)
    $css_files = glob('../assets/css/*.css');
    $minified_css = 0;
    
    foreach ($css_files as $css_file) {
        $content = file_get_contents($css_file);
        $minified = preg_replace('/\s+/', ' ', $content);
        $minified = str_replace(['; ', ' {', '{ ', ' }', '} '], [';', '{', '{', '}', '}'], $minified);
        
        if (strlen($minified) < strlen($content)) {
            file_put_contents($css_file, $minified);
            $minified_css++;
        }
    }
    
    $results['css_minification'] = [
        'status' => 'success',
        'message' => "{$minified_css} CSS dosyası minify edildi"
    ];
    
    return $results;
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performans Optimizasyonu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .performance-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .performance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .performance-card.database { border-left-color: #0d6efd; }
        .performance-card.queries { border-left-color: #198754; }
        .performance-card.cache { border-left-color: #ffc107; }
        .performance-card.files { border-left-color: #6f42c1; }
        .performance-card.images { border-left-color: #fd7e14; }
        .performance-card.css_js { border-left-color: #20c997; }
        .performance-card.memory { border-left-color: #dc3545; }
        .performance-card.server { border-left-color: #0dcaf0; }
        
        .performance-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9rem;
        }
        .performance-result.success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .performance-result.error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        .performance-result.warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }
        .performance-result.info {
            background-color: #d1ecf1;
            color: #055160;
            border: 1px solid #bee5eb;
        }
        
        .performance-summary {
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .performance-score {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .performance-score.excellent { background-color: #d1e7dd; color: #0f5132; }
        .performance-score.good { background-color: #d1ecf1; color: #055160; }
        .performance-score.average { background-color: #fff3cd; color: #664d03; }
        .performance-score.poor { background-color: #f8d7da; color: #842029; }
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
                        <i class="fas fa-tachometer-alt me-2"></i>Performans Optimizasyonu
                        <small class="text-muted">Sistem hızı ve veritabanı optimizasyonu</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#runPerformanceAuditModal">
                                <i class="fas fa-search"></i> Performans Denetimi
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-tools"></i> Optimizasyon Seçenekleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('database')">
                                    <i class="fas fa-database"></i> Veritabanı Optimizasyonu
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('queries')">
                                    <i class="fas fa-search"></i> Query Optimizasyonu
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('cache')">
                                    <i class="fas fa-memory"></i> Cache Optimizasyonu
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('files')">
                                    <i class="fas fa-file"></i> Dosya Optimizasyonu
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('images')">
                                    <i class="fas fa-image"></i> Resim Optimizasyonu
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificOptimization('css_js')">
                                    <i class="fas fa-code"></i> CSS/JS Optimizasyonu
                                </a></li>
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

                <!-- Performans Özeti -->
                <?php if (!empty($optimization_results)): ?>
                <div class="performance-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-tachometer-alt me-2"></i>Performans Analiz Sonuçları</h4>
                            <p class="mb-0">Sistem performansı analiz edildi ve optimizasyon önerileri hazırlandı.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="performance-score <?php echo getOverallPerformanceScore($optimization_results); ?>">
                                <?php echo getOverallPerformanceScore($optimization_results); ?>
                            </div>
                            <div class="mt-2">
                                <strong><?php echo countPerformanceIssues($optimization_results); ?></strong>
                                <small>Optimizasyon Fırsatı</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Optimizasyon Sonuçları -->
                <?php if (!empty($optimization_results)): ?>
                <div class="row">
                    <?php foreach ($optimization_results as $category => $tests): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card performance-card <?php echo $category; ?>">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-<?php echo getPerformanceCategoryIcon($category); ?> me-2"></i>
                                    <?php echo getPerformanceCategoryTitle($category); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($tests as $test_name => $result): ?>
                                <div class="performance-result <?php echo $result['status']; ?>">
                                    <i class="fas fa-<?php echo getPerformanceStatusIcon($result['status']); ?> me-2"></i>
                                    <?php echo $result['message']; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Performans Denetimi Başlatma Ekranı -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-tachometer-alt fa-4x text-muted mb-4"></i>
                                <h4>Performans Optimizasyonu</h4>
                                <p class="text-muted mb-4">
                                    Sistem performansını analiz etmek ve optimizasyon önerileri almak için 
                                    "Performans Denetimi" butonuna tıklayın.
                                </p>
                                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#runPerformanceAuditModal">
                                    <i class="fas fa-search me-2"></i>Denetimi Başlat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Performans Denetimi Modal -->
    <div class="modal fade" id="runPerformanceAuditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Performans Denetimi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="run_performance_audit">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu işlem sistem performansını analiz edecek ve 
                            detaylı optimizasyon önerileri sunacaktır.
                        </div>
                        
                        <h6>Analiz Edilecek Alanlar:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-primary me-2"></i>Veritabanı Performansı</li>
                            <li><i class="fas fa-check text-success me-2"></i>Query Optimizasyonu</li>
                            <li><i class="fas fa-check text-warning me-2"></i>Cache Performansı</li>
                            <li><i class="fas fa-check text-info me-2"></i>Dosya Performansı</li>
                            <li><i class="fas fa-check text-danger me-2"></i>Resim Optimizasyonu</li>
                            <li><i class="fas fa-check text-secondary me-2"></i>CSS/JS Optimizasyonu</li>
                            <li><i class="fas fa-check text-dark me-2"></i>Memory Kullanımı</li>
                            <li><i class="fas fa-check text-muted me-2"></i>Server Performansı</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Denetimi Başlat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runSpecificOptimization(optimizationType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'optimize_' + optimizationType;
            form.appendChild(actionInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRFToken(); ?>';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>

<?php
// Yardımcı fonksiyonlar

function getOverallPerformanceScore($optimization_results)
{
    if (empty($optimization_results)) return 'average';
    
    $total_tests = 0;
    $successful_tests = 0;
    $warning_tests = 0;
    
    foreach ($optimization_results as $category => $tests) {
        foreach ($tests as $test_name => $result) {
            $total_tests++;
            if ($result['status'] === 'success') {
                $successful_tests++;
            } elseif ($result['status'] === 'warning') {
                $warning_tests++;
            }
        }
    }
    
    $success_rate = $total_tests > 0 ? ($successful_tests / $total_tests) * 100 : 0;
    
    if ($success_rate >= 80) return 'excellent';
    if ($success_rate >= 60) return 'good';
    if ($success_rate >= 40) return 'average';
    return 'poor';
}

function countPerformanceIssues($optimization_results)
{
    if (empty($optimization_results)) return 0;
    
    $issue_count = 0;
    
    foreach ($optimization_results as $category => $tests) {
        foreach ($tests as $test_name => $result) {
            if ($result['status'] === 'warning' || $result['status'] === 'error') {
                $issue_count++;
            }
        }
    }
    
    return $issue_count;
}

function getPerformanceCategoryIcon($category)
{
    $icons = [
        'database' => 'database',
        'queries' => 'search',
        'cache' => 'memory',
        'files' => 'file',
        'images' => 'image',
        'css_js' => 'code',
        'memory' => 'memory',
        'server' => 'server'
    ];
    
    return $icons[$category] ?? 'tachometer-alt';
}

function getPerformanceCategoryTitle($category)
{
    $titles = [
        'database' => 'Veritabanı Performansı',
        'queries' => 'Query Optimizasyonu',
        'cache' => 'Cache Performansı',
        'files' => 'Dosya Performansı',
        'images' => 'Resim Optimizasyonu',
        'css_js' => 'CSS/JS Optimizasyonu',
        'memory' => 'Memory Kullanımı',
        'server' => 'Server Performansı'
    ];
    
    return $titles[$category] ?? ucfirst($category);
}

function getPerformanceStatusIcon($status)
{
    $icons = [
        'success' => 'check-circle',
        'error' => 'times-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    
    return $icons[$status] ?? 'question-circle';
}
?>

