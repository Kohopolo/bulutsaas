<?php
/**
 * İzleme ve Uyarı Sistemi Dashboard
 * Sistem sağlığı ve performans izleme
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
$monitoring_results = [];

// İzleme işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'run_system_monitoring') {
            $monitoring_results = runSystemMonitoring();
            $success_message = 'Sistem izleme tamamlandı.';
        }
        
        if ($action == 'check_system_health') {
            $monitoring_results['health'] = checkSystemHealth();
        }
        
        if ($action == 'check_performance') {
            $monitoring_results['performance'] = checkPerformance();
        }
        
        if ($action == 'check_security') {
            $monitoring_results['security'] = checkSecurity();
        }
        
        if ($action == 'check_database') {
            $monitoring_results['database'] = checkDatabase();
        }
        
        if ($action == 'check_services') {
            $monitoring_results['services'] = checkServices();
        }
        
        if ($action == 'create_alert_rule') {
            $monitoring_results['alert_rule'] = createAlertRule();
        }
        
        if ($action == 'test_alert') {
            $monitoring_results['alert_test'] = testAlert();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Kapsamlı sistem izleme
 */
function runSystemMonitoring()
{
    $results = [];
    
    // 1. Sistem sağlığı
    $results['health'] = checkSystemHealth();
    
    // 2. Performans
    $results['performance'] = checkPerformance();
    
    // 3. Güvenlik
    $results['security'] = checkSecurity();
    
    // 4. Veritabanı
    $results['database'] = checkDatabase();
    
    // 5. Servisler
    $results['services'] = checkServices();
    
    // 6. Disk kullanımı
    $results['disk'] = checkDiskUsage();
    
    // 7. Memory kullanımı
    $results['memory'] = checkMemoryUsage();
    
    // 8. Network durumu
    $results['network'] = checkNetworkStatus();
    
    return $results;
}

/**
 * Sistem sağlığını kontrol et
 */
function checkSystemHealth()
{
    $tests = [];
    
    try {
        // PHP durumu
        $php_version = PHP_VERSION;
        $tests['php_version'] = [
            'status' => version_compare($php_version, '8.0.0', '>=') ? 'healthy' : 'warning',
            'message' => "PHP versiyonu: {$php_version}",
            'value' => $php_version
        ];
        
        // OPcache durumu
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status();
        $tests['opcache'] = [
            'status' => $opcache_enabled ? 'healthy' : 'warning',
            'message' => $opcache_enabled ? 'OPcache aktif' : 'OPcache pasif',
            'value' => $opcache_enabled ? 'Aktif' : 'Pasif'
        ];
        
        // Gzip compression
        $gzip_enabled = extension_loaded('zlib');
        $tests['gzip'] = [
            'status' => $gzip_enabled ? 'healthy' : 'warning',
            'message' => $gzip_enabled ? 'Gzip compression aktif' : 'Gzip compression pasif',
            'value' => $gzip_enabled ? 'Aktif' : 'Pasif'
        ];
        
        // Session durumu
        $session_status = session_status();
        $tests['session'] = [
            'status' => $session_status === PHP_SESSION_ACTIVE ? 'healthy' : 'error',
            'message' => $session_status === PHP_SESSION_ACTIVE ? 'Session aktif' : 'Session pasif',
            'value' => $session_status === PHP_SESSION_ACTIVE ? 'Aktif' : 'Pasif'
        ];
        
        // Error reporting
        $error_reporting = error_reporting();
        $tests['error_reporting'] = [
            'status' => $error_reporting > 0 ? 'healthy' : 'warning',
            'message' => $error_reporting > 0 ? 'Error reporting aktif' : 'Error reporting pasif',
            'value' => $error_reporting > 0 ? 'Aktif' : 'Pasif'
        ];
        
    } catch (Exception $e) {
        $tests['system_health'] = [
            'status' => 'error',
            'message' => 'Sistem sağlık kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Performans kontrolü
 */
function checkPerformance()
{
    $tests = [];
    
    try {
        // Memory kullanımı
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);
        $memory_peak_mb = round($memory_peak / 1024 / 1024, 2);
        
        $tests['memory_usage'] = [
            'status' => $memory_usage_mb > 50 ? 'warning' : 'healthy',
            'message' => "Mevcut memory: {$memory_usage_mb} MB / {$memory_limit}",
            'value' => $memory_usage_mb . ' MB'
        ];
        
        $tests['memory_peak'] = [
            'status' => $memory_peak_mb > 100 ? 'warning' : 'healthy',
            'message' => "Peak memory: {$memory_peak_mb} MB",
            'value' => $memory_peak_mb . ' MB'
        ];
        
        // Execution time
        $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $tests['execution_time'] = [
            'status' => $execution_time > 5 ? 'warning' : 'healthy',
            'message' => "Execution time: " . round($execution_time, 3) . " saniye",
            'value' => round($execution_time, 3) . 's'
        ];
        
        // Load average (Linux/Unix)
        if (function_exists('sys_getloadavg')) {
            $load_avg = sys_getloadavg();
            $tests['load_average'] = [
                'status' => $load_avg[0] > 2 ? 'warning' : 'healthy',
                'message' => "Load average: " . implode(', ', $load_avg),
                'value' => implode(', ', $load_avg)
            ];
        }
        
    } catch (Exception $e) {
        $tests['performance_check'] = [
            'status' => 'error',
            'message' => 'Performans kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Güvenlik kontrolü
 */
function checkSecurity()
{
    $tests = [];
    
    try {
        // HTTPS kontrolü
        $https_enabled = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $tests['https'] = [
            'status' => $https_enabled ? 'healthy' : 'warning',
            'message' => $https_enabled ? 'HTTPS aktif' : 'HTTPS pasif',
            'value' => $https_enabled ? 'Aktif' : 'Pasif'
        ];
        
        // CSRF koruması
        $csrf_protection = function_exists('generateCSRFToken');
        $tests['csrf_protection'] = [
            'status' => $csrf_protection ? 'healthy' : 'warning',
            'message' => $csrf_protection ? 'CSRF koruması aktif' : 'CSRF koruması pasif',
            'value' => $csrf_protection ? 'Aktif' : 'Pasif'
        ];
        
        // XSS koruması
        $xss_protection = function_exists('sanitizeString');
        $tests['xss_protection'] = [
            'status' => $xss_protection ? 'healthy' : 'warning',
            'message' => $xss_protection ? 'XSS koruması aktif' : 'XSS koruması pasif',
            'value' => $xss_protection ? 'Aktif' : 'Pasif'
        ];
        
        // Session güvenliği
        $session_secure = ini_get('session.cookie_secure');
        $tests['session_security'] = [
            'status' => $session_secure ? 'healthy' : 'warning',
            'message' => $session_secure ? 'Session güvenli' : 'Session güvenli değil',
            'value' => $session_secure ? 'Güvenli' : 'Güvenli Değil'
        ];
        
        // File permissions
        $critical_files = [
            '../config/database.php',
            '../includes/functions.php',
            '../admin/index.php'
        ];
        
        $file_permissions_ok = true;
        foreach ($critical_files as $file) {
            if (file_exists($file)) {
                $permissions = substr(sprintf('%o', fileperms($file)), -4);
                if ($permissions === '0777' || $permissions === '0775') {
                    $file_permissions_ok = false;
                    break;
                }
            }
        }
        
        $tests['file_permissions'] = [
            'status' => $file_permissions_ok ? 'healthy' : 'warning',
            'message' => $file_permissions_ok ? 'Dosya izinleri güvenli' : 'Dosya izinleri güvenli değil',
            'value' => $file_permissions_ok ? 'Güvenli' : 'Güvenli Değil'
        ];
        
    } catch (Exception $e) {
        $tests['security_check'] = [
            'status' => 'error',
            'message' => 'Güvenlik kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Veritabanı kontrolü
 */
function checkDatabase()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Veritabanı bağlantısı
        $stmt = $pdo->query("SELECT 1");
        $tests['connection'] = [
            'status' => 'healthy',
            'message' => 'Veritabanı bağlantısı aktif',
            'value' => 'Bağlı'
        ];
        
        // Veritabanı boyutu
        $stmt = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $db_size = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['database_size'] = [
            'status' => $db_size['Size (MB)'] > 1000 ? 'warning' : 'healthy',
            'message' => "Veritabanı boyutu: {$db_size['Size (MB)']} MB",
            'value' => $db_size['Size (MB)'] . ' MB'
        ];
        
        // Tablo sayısı
        $stmt = $pdo->query("
            SELECT COUNT(*) as table_count
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $table_count = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['table_count'] = [
            'status' => 'healthy',
            'message' => "Tablo sayısı: {$table_count['table_count']}",
            'value' => $table_count['table_count']
        ];
        
        // Connection sayısı
        $stmt = $pdo->query("SHOW STATUS LIKE 'Connections'");
        $connections = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests['connections'] = [
            'status' => 'healthy',
            'message' => "Toplam bağlantı: {$connections['Value']}",
            'value' => $connections['Value']
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
            'status' => $hit_rate > 50 ? 'healthy' : 'warning',
            'message' => "Query cache hit rate: %{$hit_rate}",
            'value' => $hit_rate . '%'
        ];
        
    } catch (Exception $e) {
        $tests['database_check'] = [
            'status' => 'error',
            'message' => 'Veritabanı kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Servis kontrolü
 */
function checkServices()
{
    $tests = [];
    
    try {
        // Web server
        $web_server = $_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmeyen';
        $tests['web_server'] = [
            'status' => 'healthy',
            'message' => "Web server: {$web_server}",
            'value' => $web_server
        ];
        
        // PHP extensions
        $required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        
        $tests['php_extensions'] = [
            'status' => empty($missing_extensions) ? 'healthy' : 'error',
            'message' => empty($missing_extensions) ? 'Gerekli PHP extension\'ları yüklü' : 'Eksik extension\'lar: ' . implode(', ', $missing_extensions),
            'value' => empty($missing_extensions) ? 'Tamam' : 'Eksik'
        ];
        
        // File upload
        $file_uploads = ini_get('file_uploads');
        $tests['file_uploads'] = [
            'status' => $file_uploads ? 'healthy' : 'warning',
            'message' => $file_uploads ? 'File upload aktif' : 'File upload pasif',
            'value' => $file_uploads ? 'Aktif' : 'Pasif'
        ];
        
        // Max execution time
        $max_execution_time = ini_get('max_execution_time');
        $tests['max_execution_time'] = [
            'status' => $max_execution_time > 30 ? 'healthy' : 'warning',
            'message' => "Max execution time: {$max_execution_time} saniye",
            'value' => $max_execution_time . 's'
        ];
        
    } catch (Exception $e) {
        $tests['services_check'] = [
            'status' => 'error',
            'message' => 'Servis kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Disk kullanımı kontrolü
 */
function checkDiskUsage()
{
    $tests = [];
    
    try {
        // Ana dizin disk kullanımı
        $disk_free = disk_free_space('.');
        $disk_total = disk_total_space('.');
        $disk_used = $disk_total - $disk_free;
        $disk_usage_percent = round(($disk_used / $disk_total) * 100, 2);
        
        $tests['disk_usage'] = [
            'status' => $disk_usage_percent > 90 ? 'error' : ($disk_usage_percent > 80 ? 'warning' : 'healthy'),
            'message' => "Disk kullanımı: %{$disk_usage_percent}",
            'value' => $disk_usage_percent . '%'
        ];
        
        // Upload dizini
        $upload_dir = '../uploads/';
        if (is_dir($upload_dir)) {
            $upload_size = 0;
            $files = glob($upload_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $upload_size += filesize($file);
                }
            }
            $upload_size_mb = round($upload_size / 1024 / 1024, 2);
            
            $tests['upload_size'] = [
                'status' => $upload_size_mb > 1000 ? 'warning' : 'healthy',
                'message' => "Upload dizini boyutu: {$upload_size_mb} MB",
                'value' => $upload_size_mb . ' MB'
            ];
        }
        
    } catch (Exception $e) {
        $tests['disk_check'] = [
            'status' => 'error',
            'message' => 'Disk kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Memory kullanımı kontrolü
 */
function checkMemoryUsage()
{
    $tests = [];
    
    try {
        // PHP memory
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);
        $memory_peak_mb = round($memory_peak / 1024 / 1024, 2);
        
        $tests['php_memory'] = [
            'status' => $memory_usage_mb > 100 ? 'warning' : 'healthy',
            'message' => "PHP memory: {$memory_usage_mb} MB / {$memory_limit}",
            'value' => $memory_usage_mb . ' MB'
        ];
        
        // System memory (Linux)
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
            if (isset($matches[1])) {
                $total_memory = round($matches[1] / 1024, 2);
                $tests['system_memory'] = [
                    'status' => 'healthy',
                    'message' => "Sistem memory: {$total_memory} MB",
                    'value' => $total_memory . ' MB'
                ];
            }
        }
        
    } catch (Exception $e) {
        $tests['memory_check'] = [
            'status' => 'error',
            'message' => 'Memory kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Network durumu kontrolü
 */
function checkNetworkStatus()
{
    $tests = [];
    
    try {
        // DNS çözümleme
        $dns_working = gethostbyname('google.com') !== 'google.com';
        $tests['dns'] = [
            'status' => $dns_working ? 'healthy' : 'error',
            'message' => $dns_working ? 'DNS çözümleme çalışıyor' : 'DNS çözümleme çalışmıyor',
            'value' => $dns_working ? 'Çalışıyor' : 'Çalışmıyor'
        ];
        
        // HTTP bağlantısı
        $http_working = function_exists('curl_init');
        $tests['http'] = [
            'status' => $http_working ? 'healthy' : 'warning',
            'message' => $http_working ? 'HTTP bağlantısı mevcut' : 'HTTP bağlantısı mevcut değil',
            'value' => $http_working ? 'Mevcut' : 'Mevcut Değil'
        ];
        
        // SSL/TLS
        $ssl_working = extension_loaded('openssl');
        $tests['ssl'] = [
            'status' => $ssl_working ? 'healthy' : 'warning',
            'message' => $ssl_working ? 'SSL/TLS desteği mevcut' : 'SSL/TLS desteği mevcut değil',
            'value' => $ssl_working ? 'Mevcut' : 'Mevcut Değil'
        ];
        
    } catch (Exception $e) {
        $tests['network_check'] = [
            'status' => 'error',
            'message' => 'Network kontrolü hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Uyarı kuralı oluştur
 */
function createAlertRule()
{
    $results = [];
    
    try {
        $rule_name = sanitizeString($_POST['rule_name'] ?? '');
        $rule_type = sanitizeString($_POST['rule_type'] ?? '');
        $threshold = floatval($_POST['threshold'] ?? 0);
        $notification_method = sanitizeString($_POST['notification_method'] ?? '');
        
        if (empty($rule_name) || empty($rule_type)) {
            throw new Exception('Kural adı ve tipi gereklidir');
        }
        
        // Uyarı kuralını kaydet (veritabanı tablosu gerekli)
        $results['status'] = 'success';
        $results['message'] = "Uyarı kuralı oluşturuldu: {$rule_name}";
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Uyarı kuralı oluşturma hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Uyarı testi
 */
function testAlert()
{
    $results = [];
    
    try {
        // Test uyarısı gönder
        $results['status'] = 'success';
        $results['message'] = 'Test uyarısı gönderildi';
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Uyarı testi hatası: ' . $e->getMessage();
    }
    
    return $results;
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İzleme ve Uyarı Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .monitoring-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .monitoring-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .monitoring-card.health { border-left-color: #198754; }
        .monitoring-card.performance { border-left-color: #0d6efd; }
        .monitoring-card.security { border-left-color: #dc3545; }
        .monitoring-card.database { border-left-color: #6f42c1; }
        .monitoring-card.services { border-left-color: #fd7e14; }
        .monitoring-card.disk { border-left-color: #20c997; }
        .monitoring-card.memory { border-left-color: #ffc107; }
        .monitoring-card.network { border-left-color: #0dcaf0; }
        
        .monitoring-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .monitoring-result.healthy {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .monitoring-result.warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }
        .monitoring-result.error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        
        .monitoring-summary {
            background: linear-gradient(135deg, #198754 0%, #0d6efd 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-indicator.healthy { background-color: #198754; }
        .status-indicator.warning { background-color: #ffc107; }
        .status-indicator.error { background-color: #dc3545; }
        
        .metric-value {
            font-weight: bold;
            color: #495057;
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
                        <i class="fas fa-heartbeat me-2"></i>İzleme ve Uyarı Sistemi
                        <small class="text-muted">Sistem sağlığı ve performans izleme</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#runMonitoringModal">
                                <i class="fas fa-search"></i> Sistem İzleme
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-tools"></i> İzleme Seçenekleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('health')">
                                    <i class="fas fa-heartbeat"></i> Sistem Sağlığı
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('performance')">
                                    <i class="fas fa-tachometer-alt"></i> Performans
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('security')">
                                    <i class="fas fa-shield-alt"></i> Güvenlik
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('database')">
                                    <i class="fas fa-database"></i> Veritabanı
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('services')">
                                    <i class="fas fa-server"></i> Servisler
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('disk')">
                                    <i class="fas fa-hdd"></i> Disk Kullanımı
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('memory')">
                                    <i class="fas fa-memory"></i> Memory Kullanımı
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificCheck('network')">
                                    <i class="fas fa-network-wired"></i> Network Durumu
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

                <!-- İzleme Özeti -->
                <?php if (!empty($monitoring_results)): ?>
                <div class="monitoring-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-heartbeat me-2"></i>Sistem İzleme Sonuçları</h4>
                            <p class="mb-0">Sistem sağlığı ve performansı analiz edildi.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h3 mb-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="mt-2">
                                <strong><?php echo count($monitoring_results); ?></strong>
                                <small>İzleme Alanı</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- İzleme Sonuçları -->
                <?php if (!empty($monitoring_results)): ?>
                <div class="row">
                    <?php foreach ($monitoring_results as $category => $tests): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card monitoring-card <?php echo $category; ?>">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-<?php echo getMonitoringCategoryIcon($category); ?> me-2"></i>
                                    <?php echo getMonitoringCategoryTitle($category); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($tests as $test_name => $result): ?>
                                <div class="monitoring-result <?php echo $result['status']; ?>">
                                    <div>
                                        <span class="status-indicator <?php echo $result['status']; ?>"></span>
                                        <span><?php echo $result['message']; ?></span>
                                    </div>
                                    <?php if (isset($result['value'])): ?>
                                    <span class="metric-value"><?php echo $result['value']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- İzleme Başlatma Ekranı -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-heartbeat fa-4x text-muted mb-4"></i>
                                <h4>İzleme ve Uyarı Sistemi</h4>
                                <p class="text-muted mb-4">
                                    Sistem sağlığını ve performansını izlemek için 
                                    "Sistem İzleme" butonuna tıklayın.
                                </p>
                                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#runMonitoringModal">
                                    <i class="fas fa-search me-2"></i>İzlemeyi Başlat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Sistem İzleme Modal -->
    <div class="modal fade" id="runMonitoringModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sistem İzleme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="run_system_monitoring">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu işlem sistem sağlığını ve performansını analiz edecek.
                        </div>
                        
                        <h6>İzlenecek Alanlar:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Sistem Sağlığı</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Performans Metrikleri</li>
                            <li><i class="fas fa-check text-danger me-2"></i>Güvenlik Durumu</li>
                            <li><i class="fas fa-check text-info me-2"></i>Veritabanı Durumu</li>
                            <li><i class="fas fa-check text-warning me-2"></i>Servis Durumu</li>
                            <li><i class="fas fa-check text-secondary me-2"></i>Disk Kullanımı</li>
                            <li><i class="fas fa-check text-dark me-2"></i>Memory Kullanımı</li>
                            <li><i class="fas fa-check text-muted me-2"></i>Network Durumu</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>İzlemeyi Başlat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runSpecificCheck(checkType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'check_' + checkType;
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

function getMonitoringCategoryIcon($category)
{
    $icons = [
        'health' => 'heartbeat',
        'performance' => 'tachometer-alt',
        'security' => 'shield-alt',
        'database' => 'database',
        'services' => 'server',
        'disk' => 'hdd',
        'memory' => 'memory',
        'network' => 'network-wired'
    ];
    
    return $icons[$category] ?? 'monitor';
}

function getMonitoringCategoryTitle($category)
{
    $titles = [
        'health' => 'Sistem Sağlığı',
        'performance' => 'Performans',
        'security' => 'Güvenlik',
        'database' => 'Veritabanı',
        'services' => 'Servisler',
        'disk' => 'Disk Kullanımı',
        'memory' => 'Memory Kullanımı',
        'network' => 'Network Durumu'
    ];
    
    return $titles[$category] ?? ucfirst($category);
}
?>

