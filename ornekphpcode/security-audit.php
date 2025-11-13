<?php
/**
 * Güvenlik Denetimi Dashboard
 * Tüm sistemlerin güvenlik açıklarının kontrolü
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
$audit_results = [];

// Güvenlik denetimi işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'run_security_audit') {
            $audit_results = runSecurityAudit();
            $success_message = 'Güvenlik denetimi tamamlandı.';
        }
        
        if ($action == 'check_sql_injection') {
            $audit_results['sql_injection'] = checkSQLInjectionVulnerabilities();
        }
        
        if ($action == 'check_xss_vulnerabilities') {
            $audit_results['xss'] = checkXSSVulnerabilities();
        }
        
        if ($action == 'check_authentication') {
            $audit_results['authentication'] = checkAuthenticationSecurity();
        }
        
        if ($action == 'check_file_uploads') {
            $audit_results['file_uploads'] = checkFileUploadSecurity();
        }
        
        if ($action == 'check_session_security') {
            $audit_results['session'] = checkSessionSecurity();
        }
        
        if ($action == 'check_input_validation') {
            $audit_results['input_validation'] = checkInputValidation();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Kapsamlı güvenlik denetimi
 */
function runSecurityAudit()
{
    $results = [];
    
    // 1. SQL Injection kontrolü
    $results['sql_injection'] = checkSQLInjectionVulnerabilities();
    
    // 2. XSS kontrolü
    $results['xss'] = checkXSSVulnerabilities();
    
    // 3. Authentication güvenliği
    $results['authentication'] = checkAuthenticationSecurity();
    
    // 4. File upload güvenliği
    $results['file_uploads'] = checkFileUploadSecurity();
    
    // 5. Session güvenliği
    $results['session'] = checkSessionSecurity();
    
    // 6. Input validation
    $results['input_validation'] = checkInputValidation();
    
    // 7. CSRF koruması
    $results['csrf'] = checkCSRFProtection();
    
    // 8. File permissions
    $results['file_permissions'] = checkFilePermissions();
    
    // 9. Database security
    $results['database'] = checkDatabaseSecurity();
    
    // 10. API security
    $results['api'] = checkAPISecurity();
    
    return $results;
}

/**
 * SQL Injection açıklarını kontrol et
 */
function checkSQLInjectionVulnerabilities()
{
    $tests = [];
    
    // PDO kullanımını kontrol et
    $files = glob('../includes/*.php');
    $files = array_merge($files, glob('../admin/*.php'));
    $files = array_merge($files, glob('../api/controllers/*.php'));
    
    $vulnerable_files = [];
    $secure_files = [];
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            
            // Tehlikeli SQL kullanımları
            $dangerous_patterns = [
                '/mysql_query\s*\(/',
                '/mysqli_query\s*\(/',
                '/\$pdo->query\s*\(\s*["\']\s*SELECT.*\$/',  // Değişken içeren query
                '/\$pdo->query\s*\(\s*["\']\s*INSERT.*\$/',  // Değişken içeren query
                '/\$pdo->query\s*\(\s*["\']\s*UPDATE.*\$/',  // Değişken içeren query
                '/\$pdo->query\s*\(\s*["\']\s*DELETE.*\$/'   // Değişken içeren query
            ];
            
            $has_vulnerability = false;
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $has_vulnerability = true;
                    break;
                }
            }
            
            if ($has_vulnerability) {
                $vulnerable_files[] = basename($file);
            } else {
                $secure_files[] = basename($file);
            }
        }
    }
    
    $tests['vulnerable_files'] = [
        'status' => count($vulnerable_files) > 0 ? 'error' : 'success',
        'message' => count($vulnerable_files) > 0 ? 
            'SQL Injection açığı tespit edildi: ' . implode(', ', $vulnerable_files) :
            'SQL Injection koruması aktif'
    ];
    
    $tests['secure_files'] = [
        'status' => 'success',
        'message' => count($secure_files) . ' dosya güvenli'
    ];
    
    // PDO prepared statements kullanımı
    $pdo_usage = 0;
    $prepared_usage = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            $pdo_usage += substr_count($content, '$pdo->');
            $prepared_usage += substr_count($content, '$pdo->prepare');
        }
    }
    
    $tests['pdo_usage'] = [
        'status' => $prepared_usage > 0 ? 'success' : 'warning',
        'message' => "PDO kullanımı: {$pdo_usage}, Prepared statements: {$prepared_usage}"
    ];
    
    return $tests;
}

/**
 * XSS açıklarını kontrol et
 */
function checkXSSVulnerabilities()
{
    $tests = [];
    
    // XSS koruma dosyasının varlığı
    if (file_exists('../includes/xss_protection.php')) {
        $tests['xss_protection_file'] = [
            'status' => 'success',
            'message' => 'XSS koruma dosyası mevcut'
        ];
    } else {
        $tests['xss_protection_file'] = [
            'status' => 'error',
            'message' => 'XSS koruma dosyası bulunamadı'
        ];
    }
    
    // HTML output kontrolü
    $files = glob('../admin/*.php');
    $vulnerable_outputs = [];
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            
            // Tehlikeli output patterns
            $dangerous_patterns = [
                '/echo\s+[\$]/',           // echo $variable
                '/print\s+[\$]/',          // print $variable
                '/<\?=\s*[\$]/',           // <?= $variable
                '/htmlspecialchars\s*\(/i' // htmlspecialchars kullanımı
            ];
            
            $has_echo = preg_match('/echo\s+[\$]/', $content);
            $has_htmlspecialchars = preg_match('/htmlspecialchars\s*\(/i', $content);
            
            if ($has_echo && !$has_htmlspecialchars) {
                $vulnerable_outputs[] = basename($file);
            }
        }
    }
    
    $tests['output_escaping'] = [
        'status' => count($vulnerable_outputs) > 0 ? 'warning' : 'success',
        'message' => count($vulnerable_outputs) > 0 ? 
            'XSS açığı riski: ' . implode(', ', $vulnerable_outputs) :
            'Output escaping koruması aktif'
    ];
    
    return $tests;
}

/**
 * Authentication güvenliğini kontrol et
 */
function checkAuthenticationSecurity()
{
    $tests = [];
    
    // Session güvenliği
    if (file_exists('../includes/session_security.php')) {
        $tests['session_security'] = [
            'status' => 'success',
            'message' => 'Session güvenlik dosyası mevcut'
        ];
    } else {
        $tests['session_security'] = [
            'status' => 'error',
            'message' => 'Session güvenlik dosyası bulunamadı'
        ];
    }
    
    // Password hashing
    $files = glob('../includes/*.php');
    $has_password_hash = false;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'password_hash') !== false || strpos($content, 'password_verify') !== false) {
                $has_password_hash = true;
                break;
            }
        }
    }
    
    $tests['password_hashing'] = [
        'status' => $has_password_hash ? 'success' : 'error',
        'message' => $has_password_hash ? 'Password hashing kullanılıyor' : 'Password hashing bulunamadı'
    ];
    
    // Brute force koruması
    $session_content = file_get_contents('../includes/session_security.php');
    $has_brute_force_protection = strpos($session_content, 'recordLoginAttempt') !== false;
    
    $tests['brute_force_protection'] = [
        'status' => $has_brute_force_protection ? 'success' : 'warning',
        'message' => $has_brute_force_protection ? 'Brute force koruması aktif' : 'Brute force koruması eksik'
    ];
    
    return $tests;
}

/**
 * File upload güvenliğini kontrol et
 */
function checkFileUploadSecurity()
{
    $tests = [];
    
    // Upload dizini kontrolü
    $upload_dirs = ['../uploads/', '../cache/'];
    $secure_uploads = true;
    
    foreach ($upload_dirs as $dir) {
        if (is_dir($dir)) {
            $htaccess_file = $dir . '.htaccess';
            if (!file_exists($htaccess_file)) {
                $secure_uploads = false;
                break;
            }
        }
    }
    
    $tests['upload_directory'] = [
        'status' => $secure_uploads ? 'success' : 'warning',
        'message' => $secure_uploads ? 'Upload dizinleri güvenli' : 'Upload dizinlerinde .htaccess eksik'
    ];
    
    // File type validation
    $files = glob('../admin/*.php');
    $has_file_validation = false;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'mime_content_type') !== false || 
                strpos($content, 'finfo_file') !== false ||
                strpos($content, 'getimagesize') !== false) {
                $has_file_validation = true;
                break;
            }
        }
    }
    
    $tests['file_validation'] = [
        'status' => $has_file_validation ? 'success' : 'warning',
        'message' => $has_file_validation ? 'File type validation mevcut' : 'File type validation eksik'
    ];
    
    return $tests;
}

/**
 * Session güvenliğini kontrol et
 */
function checkSessionSecurity()
{
    $tests = [];
    
    // Session ayarları
    $session_content = file_get_contents('../includes/session_security.php');
    
    $has_httponly = strpos($session_content, 'session.cookie_httponly') !== false;
    $has_secure = strpos($session_content, 'session.cookie_secure') !== false;
    $has_samesite = strpos($session_content, 'session.cookie_samesite') !== false;
    $has_regenerate = strpos($session_content, 'session_regenerate_id') !== false;
    
    $tests['cookie_httponly'] = [
        'status' => $has_httponly ? 'success' : 'error',
        'message' => $has_httponly ? 'HttpOnly cookie aktif' : 'HttpOnly cookie eksik'
    ];
    
    $tests['cookie_secure'] = [
        'status' => $has_secure ? 'success' : 'warning',
        'message' => $has_secure ? 'Secure cookie aktif' : 'Secure cookie eksik'
    ];
    
    $tests['cookie_samesite'] = [
        'status' => $has_samesite ? 'success' : 'warning',
        'message' => $has_samesite ? 'SameSite cookie aktif' : 'SameSite cookie eksik'
    ];
    
    $tests['session_regeneration'] = [
        'status' => $has_regenerate ? 'success' : 'error',
        'message' => $has_regenerate ? 'Session regeneration aktif' : 'Session regeneration eksik'
    ];
    
    return $tests;
}

/**
 * Input validation kontrolü
 */
function checkInputValidation()
{
    $tests = [];
    
    // CSRF koruması
    if (file_exists('csrf_protection.php')) {
        $tests['csrf_protection'] = [
            'status' => 'success',
            'message' => 'CSRF koruması mevcut'
        ];
    } else {
        $tests['csrf_protection'] = [
            'status' => 'error',
            'message' => 'CSRF koruması eksik'
        ];
    }
    
    // Input sanitization
    $files = glob('../includes/*.php');
    $has_sanitization = false;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'filter_var') !== false || 
                strpos($content, 'htmlspecialchars') !== false ||
                strpos($content, 'strip_tags') !== false) {
                $has_sanitization = true;
                break;
            }
        }
    }
    
    $tests['input_sanitization'] = [
        'status' => $has_sanitization ? 'success' : 'warning',
        'message' => $has_sanitization ? 'Input sanitization mevcut' : 'Input sanitization eksik'
    ];
    
    return $tests;
}

/**
 * CSRF koruması kontrolü
 */
function checkCSRFProtection()
{
    $tests = [];
    
    // CSRF token kontrolü
    $csrf_file = 'csrf_protection.php';
    if (file_exists($csrf_file)) {
        $csrf_content = file_get_contents($csrf_file);
        
        $has_generate = strpos($csrf_content, 'generateCSRFToken') !== false;
        $has_verify = strpos($csrf_content, 'verifyCSRFToken') !== false;
        
        $tests['csrf_tokens'] = [
            'status' => ($has_generate && $has_verify) ? 'success' : 'error',
            'message' => ($has_generate && $has_verify) ? 'CSRF token sistemi aktif' : 'CSRF token sistemi eksik'
        ];
    } else {
        $tests['csrf_tokens'] = [
            'status' => 'error',
            'message' => 'CSRF koruma dosyası bulunamadı'
        ];
    }
    
    return $tests;
}

/**
 * File permissions kontrolü
 */
function checkFilePermissions()
{
    $tests = [];
    
    // Kritik dosyaların izinleri
    $critical_files = [
        '../config/database.php',
        '../includes/functions.php',
        '../admin/login.php'
    ];
    
    $secure_permissions = true;
    $permission_details = [];
    
    foreach ($critical_files as $file) {
        if (file_exists($file)) {
            $perms = fileperms($file);
            $octal = substr(sprintf('%o', $perms), -4);
            
            // 644 veya daha kısıtlayıcı olmalı
            if (intval($octal) > 644) {
                $secure_permissions = false;
            }
            
            $permission_details[] = basename($file) . ': ' . $octal;
        }
    }
    
    $tests['file_permissions'] = [
        'status' => $secure_permissions ? 'success' : 'warning',
        'message' => $secure_permissions ? 'Dosya izinleri güvenli' : 'Bazı dosyaların izinleri çok açık'
    ];
    
    $tests['permission_details'] = [
        'status' => 'info',
        'message' => implode(', ', $permission_details)
    ];
    
    return $tests;
}

/**
 * Database güvenliğini kontrol et
 */
function checkDatabaseSecurity()
{
    $tests = [];
    
    // Database config dosyası
    if (file_exists('../config/database.php')) {
        $db_content = file_get_contents('../config/database.php');
        
        // Hardcoded credentials kontrolü
        $has_hardcoded = strpos($db_content, 'password') !== false && 
                        strpos($db_content, 'root') !== false;
        
        $tests['database_credentials'] = [
            'status' => $has_hardcoded ? 'warning' : 'success',
            'message' => $has_hardcoded ? 'Hardcoded credentials tespit edildi' : 'Database credentials güvenli'
        ];
        
        // PDO kullanımı
        $has_pdo = strpos($db_content, 'PDO') !== false;
        $tests['pdo_usage'] = [
            'status' => $has_pdo ? 'success' : 'error',
            'message' => $has_pdo ? 'PDO kullanılıyor' : 'PDO kullanılmıyor'
        ];
    }
    
    return $tests;
}

/**
 * API güvenliğini kontrol et
 */
function checkAPISecurity()
{
    $tests = [];
    
    // API authentication
    if (file_exists('../api/config/api-config.php')) {
        $api_content = file_get_contents('../api/config/api-config.php');
        
        $has_auth = strpos($api_content, 'API_AUTH_ENABLED') !== false;
        $has_rate_limit = strpos($api_content, 'API_RATE_LIMIT_ENABLED') !== false;
        
        $tests['api_authentication'] = [
            'status' => $has_auth ? 'success' : 'warning',
            'message' => $has_auth ? 'API authentication aktif' : 'API authentication eksik'
        ];
        
        $tests['api_rate_limiting'] = [
            'status' => $has_rate_limit ? 'success' : 'warning',
            'message' => $has_rate_limit ? 'API rate limiting aktif' : 'API rate limiting eksik'
        ];
    }
    
    return $tests;
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Güvenlik Denetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .security-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .security-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .security-card.sql { border-left-color: #dc3545; }
        .security-card.xss { border-left-color: #fd7e14; }
        .security-card.auth { border-left-color: #6f42c1; }
        .security-card.upload { border-left-color: #20c997; }
        .security-card.session { border-left-color: #0dcaf0; }
        .security-card.input { border-left-color: #ffc107; }
        .security-card.csrf { border-left-color: #198754; }
        .security-card.permissions { border-left-color: #6c757d; }
        .security-card.database { border-left-color: #0d6efd; }
        .security-card.api { border-left-color: #e83e8c; }
        
        .security-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9rem;
        }
        .security-result.success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .security-result.error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        .security-result.warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }
        .security-result.info {
            background-color: #d1ecf1;
            color: #055160;
            border: 1px solid #bee5eb;
        }
        
        .security-summary {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .threat-level {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .threat-level.low { background-color: #d1e7dd; color: #0f5132; }
        .threat-level.medium { background-color: #fff3cd; color: #664d03; }
        .threat-level.high { background-color: #f8d7da; color: #842029; }
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
                        <i class="fas fa-shield-alt me-2"></i>Güvenlik Denetimi
                        <small class="text-muted">Sistem güvenlik açıklarının kontrolü</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#runSecurityAuditModal">
                                <i class="fas fa-search"></i> Güvenlik Denetimi Başlat
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-tools"></i> Denetim Seçenekleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="runSpecificAudit('sql_injection')">
                                    <i class="fas fa-database"></i> SQL Injection
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificAudit('xss_vulnerabilities')">
                                    <i class="fas fa-code"></i> XSS Açıkları
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificAudit('authentication')">
                                    <i class="fas fa-key"></i> Authentication
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificAudit('file_uploads')">
                                    <i class="fas fa-upload"></i> File Upload
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificAudit('session_security')">
                                    <i class="fas fa-user-shield"></i> Session Security
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

                <!-- Güvenlik Özeti -->
                <?php if (!empty($audit_results)): ?>
                <div class="security-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-shield-alt me-2"></i>Güvenlik Denetim Sonuçları</h4>
                            <p class="mb-0">Sistem güvenlik açıkları tespit edildi ve analiz edildi.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="threat-level <?php echo getOverallThreatLevel($audit_results); ?>">
                                <?php echo strtoupper(getOverallThreatLevel($audit_results)); ?> RİSK
                            </div>
                            <div class="mt-2">
                                <strong><?php echo countSecurityIssues($audit_results); ?></strong>
                                <small>Güvenlik Sorunu</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Denetim Sonuçları -->
                <?php if (!empty($audit_results)): ?>
                <div class="row">
                    <?php foreach ($audit_results as $category => $tests): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card security-card <?php echo $category; ?>">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-<?php echo getSecurityCategoryIcon($category); ?> me-2"></i>
                                    <?php echo getSecurityCategoryTitle($category); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($tests as $test_name => $result): ?>
                                <div class="security-result <?php echo $result['status']; ?>">
                                    <i class="fas fa-<?php echo getSecurityStatusIcon($result['status']); ?> me-2"></i>
                                    <?php echo $result['message']; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Denetim Başlatma Ekranı -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-shield-alt fa-4x text-muted mb-4"></i>
                                <h4>Güvenlik Denetimi</h4>
                                <p class="text-muted mb-4">
                                    Sistem güvenlik açıklarını tespit etmek ve analiz etmek için 
                                    "Güvenlik Denetimi Başlat" butonuna tıklayın.
                                </p>
                                <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#runSecurityAuditModal">
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

    <!-- Güvenlik Denetimi Modal -->
    <div class="modal fade" id="runSecurityAuditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Güvenlik Denetimi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="run_security_audit">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Bu işlem sistem güvenlik açıklarını tespit edecek ve 
                            detaylı bir güvenlik raporu oluşturacaktır.
                        </div>
                        
                        <h6>Denetlenecek Alanlar:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-danger me-2"></i>SQL Injection Açıkları</li>
                            <li><i class="fas fa-check text-warning me-2"></i>XSS Açıkları</li>
                            <li><i class="fas fa-check text-info me-2"></i>Authentication Güvenliği</li>
                            <li><i class="fas fa-check text-success me-2"></i>File Upload Güvenliği</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Session Güvenliği</li>
                            <li><i class="fas fa-check text-secondary me-2"></i>Input Validation</li>
                            <li><i class="fas fa-check text-dark me-2"></i>CSRF Koruması</li>
                            <li><i class="fas fa-check text-muted me-2"></i>File Permissions</li>
                            <li><i class="fas fa-check text-danger me-2"></i>Database Güvenliği</li>
                            <li><i class="fas fa-check text-warning me-2"></i>API Güvenliği</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-search me-2"></i>Denetimi Başlat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runSpecificAudit(auditType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'check_' + auditType;
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

function getOverallThreatLevel($audit_results)
{
    if (empty($audit_results)) return 'low';
    
    $error_count = 0;
    $warning_count = 0;
    
    foreach ($audit_results as $category => $tests) {
        foreach ($tests as $test_name => $result) {
            if ($result['status'] === 'error') {
                $error_count++;
            } elseif ($result['status'] === 'warning') {
                $warning_count++;
            }
        }
    }
    
    if ($error_count > 0) return 'high';
    if ($warning_count > 2) return 'medium';
    return 'low';
}

function countSecurityIssues($audit_results)
{
    if (empty($audit_results)) return 0;
    
    $issue_count = 0;
    
    foreach ($audit_results as $category => $tests) {
        foreach ($tests as $test_name => $result) {
            if ($result['status'] === 'error' || $result['status'] === 'warning') {
                $issue_count++;
            }
        }
    }
    
    return $issue_count;
}

function getSecurityCategoryIcon($category)
{
    $icons = [
        'sql_injection' => 'database',
        'xss' => 'code',
        'authentication' => 'key',
        'file_uploads' => 'upload',
        'session' => 'user-shield',
        'input_validation' => 'check-circle',
        'csrf' => 'shield-alt',
        'file_permissions' => 'lock',
        'database' => 'server',
        'api' => 'plug'
    ];
    
    return $icons[$category] ?? 'shield-alt';
}

function getSecurityCategoryTitle($category)
{
    $titles = [
        'sql_injection' => 'SQL Injection',
        'xss' => 'XSS Açıkları',
        'authentication' => 'Authentication',
        'file_uploads' => 'File Upload',
        'session' => 'Session Güvenliği',
        'input_validation' => 'Input Validation',
        'csrf' => 'CSRF Koruması',
        'file_permissions' => 'File Permissions',
        'database' => 'Database Güvenliği',
        'api' => 'API Güvenliği'
    ];
    
    return $titles[$category] ?? ucfirst($category);
}

function getSecurityStatusIcon($status)
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

