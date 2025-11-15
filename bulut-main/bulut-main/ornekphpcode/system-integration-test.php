<?php
/**
 * Sistem Entegrasyonu ve Test Dashboard
 * Tüm modüllerin birbirleriyle uyumlu çalışmasını test eder
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/system-loggers.php';

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
$test_results = [];

// Test işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'run_all_tests') {
            $test_results = runAllIntegrationTests();
            $success_message = 'Tüm entegrasyon testleri tamamlandı.';
        }
        
        if ($action == 'test_database_connections') {
            $test_results['database'] = testDatabaseConnections();
        }
        
        if ($action == 'test_api_endpoints') {
            $test_results['api'] = testApiEndpoints();
        }
        
        if ($action == 'test_module_integrations') {
            $test_results['modules'] = testModuleIntegrations();
        }
        
        if ($action == 'test_permissions') {
            $test_results['permissions'] = testPermissionSystem();
        }
        
        if ($action == 'test_notifications') {
            $test_results['notifications'] = testNotificationSystem();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Tüm entegrasyon testlerini çalıştır
 */
function runAllIntegrationTests()
{
    $results = [];
    
    // 1. Veritabanı bağlantıları
    $results['database'] = testDatabaseConnections();
    
    // 2. API endpoint'leri
    $results['api'] = testApiEndpoints();
    
    // 3. Modül entegrasyonları
    $results['modules'] = testModuleIntegrations();
    
    // 4. Yetki sistemi
    $results['permissions'] = testPermissionSystem();
    
    // 5. Bildirim sistemi
    $results['notifications'] = testNotificationSystem();
    
    // 6. Mobil uygulamalar
    $results['mobile'] = testMobileApplications();
    
    // 7. QR kod sistemleri
    $results['qr_systems'] = testQRCodeSystems();
    
    // 8. Performans metrikleri
    $results['performance'] = testPerformanceMetrics();
    
    return $results;
}

/**
 * Veritabanı bağlantılarını test et
 */
function testDatabaseConnections()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Ana veritabanı bağlantısı
        $stmt = $pdo->query("SELECT 1");
        $tests['main_connection'] = [
            'status' => 'success',
            'message' => 'Ana veritabanı bağlantısı başarılı'
        ];
    } catch (Exception $e) {
        $tests['main_connection'] = [
            'status' => 'error',
            'message' => 'Ana veritabanı bağlantı hatası: ' . $e->getMessage()
        ];
    }
    
    // Kritik tabloların varlığını kontrol et
    $critical_tables = [
        'kullanicilar', 'rezervasyonlar', 'oda_tipleri', 'musteriler',
        'tahminleme_modelleri', 'kpi_metrikleri', 'performans_metrikleri_detay',
        'qr_menu_tablolari', 'self_checkin_kiosklari', 'housekeeping_gorevleri'
    ];
    
    foreach ($critical_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt->fetchColumn();
            $tests["table_{$table}"] = [
                'status' => 'success',
                'message' => "Tablo '{$table}' mevcut ({$count} kayıt)"
            ];
        } catch (Exception $e) {
            $tests["table_{$table}"] = [
                'status' => 'error',
                'message' => "Tablo '{$table}' bulunamadı: " . $e->getMessage()
            ];
        }
    }
    
    return $tests;
}

/**
 * API endpoint'lerini test et
 */
function testApiEndpoints()
{
    $tests = [];
    $base_url = 'http://localhost/otelonofexe/web/api/index.php';
    
    // Test edilecek endpoint'ler
    $endpoints = [
        'GET' => [
            '/slider' => 'Slider API',
            '/oda-tipleri' => 'Oda Tipleri API',
            '/rezervasyonlar' => 'Rezervasyonlar API',
            '/kpi-dashboard/metrics' => 'KPI Dashboard API',
            '/performance-metrics/summary' => 'Performance Metrics API',
            '/prediction-algorithms/models' => 'Prediction Algorithms API'
        ],
        'POST' => [
            '/housekeeping-mobile/login' => 'Housekeeping Mobile API',
            '/teknik-servis-mobile/login' => 'Technical Service Mobile API',
            '/fnb-kitchen-mobile/login' => 'F&B Kitchen Mobile API',
            '/qr-room-control/scan' => 'QR Room Control API',
            '/qr-menu-system/startSession' => 'QR Menu System API',
            '/self-checkin-system/startSession' => 'Self Check-in API'
        ]
    ];
    
    foreach ($endpoints as $method => $endpoint_list) {
        foreach ($endpoint_list as $endpoint => $description) {
            $url = $base_url . $endpoint;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-API-Key: test_key'
                ]);
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $tests["{$method}_{$endpoint}"] = [
                    'status' => 'error',
                    'message' => "CURL Hatası: {$error}"
                ];
            } elseif ($http_code >= 200 && $http_code < 300) {
                $tests["{$method}_{$endpoint}"] = [
                    'status' => 'success',
                    'message' => "{$description} - HTTP {$http_code}"
                ];
            } else {
                $tests["{$method}_{$endpoint}"] = [
                    'status' => 'warning',
                    'message' => "{$description} - HTTP {$http_code}"
                ];
            }
        }
    }
    
    return $tests;
}

/**
 * Modül entegrasyonlarını test et
 */
function testModuleIntegrations()
{
    global $pdo;
    
    $tests = [];
    
    // 1. Rezervasyon-Muhasebe entegrasyonu
    try {
        require_once '../includes/reservation-accounting-integration.php';
        $integration = new ReservationAccountingIntegration($pdo);
        $tests['reservation_accounting'] = [
            'status' => 'success',
            'message' => 'Rezervasyon-Muhasebe entegrasyonu yüklendi'
        ];
    } catch (Exception $e) {
        $tests['reservation_accounting'] = [
            'status' => 'error',
            'message' => 'Rezervasyon-Muhasebe entegrasyon hatası: ' . $e->getMessage()
        ];
    }
    
    // 2. Stok otomasyonu
    try {
        require_once '../includes/inventory-automation.php';
        $inventory = new InventoryAutomation($pdo);
        $tests['inventory_automation'] = [
            'status' => 'success',
            'message' => 'Stok otomasyonu yüklendi'
        ];
    } catch (Exception $e) {
        $tests['inventory_automation'] = [
            'status' => 'error',
            'message' => 'Stok otomasyonu hatası: ' . $e->getMessage()
        ];
    }
    
    // 3. Bildirim sistemi
    try {
        require_once '../includes/notification-system.php';
        $notifications = new NotificationSystem($pdo);
        $tests['notification_system'] = [
            'status' => 'success',
            'message' => 'Bildirim sistemi yüklendi'
        ];
    } catch (Exception $e) {
        $tests['notification_system'] = [
            'status' => 'error',
            'message' => 'Bildirim sistemi hatası: ' . $e->getMessage()
        ];
    }
    
    // 4. QR Room Control
    try {
        require_once '../includes/qr-room-control.php';
        $qrRoom = new QrRoomControl($pdo);
        $tests['qr_room_control'] = [
            'status' => 'success',
            'message' => 'QR Room Control yüklendi'
        ];
    } catch (Exception $e) {
        $tests['qr_room_control'] = [
            'status' => 'error',
            'message' => 'QR Room Control hatası: ' . $e->getMessage()
        ];
    }
    
    // 5. QR Menu System
    try {
        require_once '../includes/qr-menu-system.php';
        $qrMenu = new QRMenuSystem($pdo);
        $tests['qr_menu_system'] = [
            'status' => 'success',
            'message' => 'QR Menu System yüklendi'
        ];
    } catch (Exception $e) {
        $tests['qr_menu_system'] = [
            'status' => 'error',
            'message' => 'QR Menu System hatası: ' . $e->getMessage()
        ];
    }
    
    // 6. Self Check-in System
    try {
        require_once '../includes/self-checkin-system.php';
        $selfCheckin = new SelfCheckinSystem($pdo);
        $tests['self_checkin_system'] = [
            'status' => 'success',
            'message' => 'Self Check-in System yüklendi'
        ];
    } catch (Exception $e) {
        $tests['self_checkin_system'] = [
            'status' => 'error',
            'message' => 'Self Check-in System hatası: ' . $e->getMessage()
        ];
    }
    
    // 7. KPI Dashboard System
    try {
        require_once '../includes/kpi-dashboard-system.php';
        $kpiDashboard = new KpiDashboardSystem($pdo);
        $tests['kpi_dashboard_system'] = [
            'status' => 'success',
            'message' => 'KPI Dashboard System yüklendi'
        ];
    } catch (Exception $e) {
        $tests['kpi_dashboard_system'] = [
            'status' => 'error',
            'message' => 'KPI Dashboard System hatası: ' . $e->getMessage()
        ];
    }
    
    // 8. Performance Metrics System
    try {
        require_once '../includes/performance-metrics-system.php';
        $performanceMetrics = new PerformanceMetricsSystem($pdo);
        $tests['performance_metrics_system'] = [
            'status' => 'success',
            'message' => 'Performance Metrics System yüklendi'
        ];
    } catch (Exception $e) {
        $tests['performance_metrics_system'] = [
            'status' => 'error',
            'message' => 'Performance Metrics System hatası: ' . $e->getMessage()
        ];
    }
    
    // 9. Prediction Algorithms System
    try {
        require_once '../includes/prediction-algorithms-system.php';
        $predictionAlgorithms = new PredictionAlgorithmsSystem($pdo);
        $tests['prediction_algorithms_system'] = [
            'status' => 'success',
            'message' => 'Prediction Algorithms System yüklendi'
        ];
    } catch (Exception $e) {
        $tests['prediction_algorithms_system'] = [
            'status' => 'error',
            'message' => 'Prediction Algorithms System hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Yetki sistemini test et
 */
function testPermissionSystem()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Yetki tablosunu kontrol et
        $stmt = $pdo->query("SELECT COUNT(*) FROM detayli_yetkiler");
        $count = $stmt->fetchColumn();
        $tests['permission_table'] = [
            'status' => 'success',
            'message' => "Detaylı yetkiler tablosu mevcut ({$count} kayıt)"
        ];
        
        // Yetki fonksiyonlarını test et
        require_once '../includes/detailed_permission_functions.php';
        
        if (function_exists('hasDetailedPermission')) {
            $tests['permission_functions'] = [
                'status' => 'success',
                'message' => 'Yetki fonksiyonları yüklendi'
            ];
        } else {
            $tests['permission_functions'] = [
                'status' => 'error',
                'message' => 'Yetki fonksiyonları bulunamadı'
            ];
        }
        
    } catch (Exception $e) {
        $tests['permission_system'] = [
            'status' => 'error',
            'message' => 'Yetki sistemi hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Bildirim sistemini test et
 */
function testNotificationSystem()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Bildirim tablolarını kontrol et
        $notification_tables = [
            'bildirimler', 'bildirim_sablonlari', 'bildirim_kuyrugu',
            'push_notification_abonelikleri', 'email_gonderim_gecmisi'
        ];
        
        foreach ($notification_tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tests["table_{$table}"] = [
                    'status' => 'success',
                    'message' => "Bildirim tablosu '{$table}' mevcut ({$count} kayıt)"
                ];
            } catch (Exception $e) {
                $tests["table_{$table}"] = [
                    'status' => 'error',
                    'message' => "Bildirim tablosu '{$table}' bulunamadı: " . $e->getMessage()
                ];
            }
        }
        
    } catch (Exception $e) {
        $tests['notification_system'] = [
            'status' => 'error',
            'message' => 'Bildirim sistemi hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Mobil uygulamaları test et
 */
function testMobileApplications()
{
    $tests = [];
    
    $mobile_apps = [
        'housekeeping' => 'Housekeeping Mobile',
        'teknik-servis' => 'Technical Service Mobile',
        'fnb-kitchen' => 'F&B Kitchen Mobile',
        'hr' => 'HR Mobile',
        'accounting' => 'Accounting Mobile',
        'procurement' => 'Procurement Mobile',
        'reception' => 'Reception Mobile',
        'channel-management' => 'Channel Management Mobile'
    ];
    
    foreach ($mobile_apps as $app => $name) {
        $app_path = "../mobile/{$app}/index.html";
        if (file_exists($app_path)) {
            $tests["mobile_{$app}"] = [
                'status' => 'success',
                'message' => "{$name} uygulaması mevcut"
            ];
        } else {
            $tests["mobile_{$app}"] = [
                'status' => 'error',
                'message' => "{$name} uygulaması bulunamadı"
            ];
        }
    }
    
    return $tests;
}

/**
 * QR kod sistemlerini test et
 */
function testQRCodeSystems()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // QR Room Control tabloları
        $qr_room_tables = [
            'oda_durumlari', 'housekeeping_gorevleri', 'qr_kod_okuma_gecmisi'
        ];
        
        foreach ($qr_room_tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tests["qr_room_{$table}"] = [
                    'status' => 'success',
                    'message' => "QR Room Control tablosu '{$table}' mevcut ({$count} kayıt)"
                ];
            } catch (Exception $e) {
                $tests["qr_room_{$table}"] = [
                    'status' => 'error',
                    'message' => "QR Room Control tablosu '{$table}' bulunamadı: " . $e->getMessage()
                ];
            }
        }
        
        // QR Menu System tabloları
        $qr_menu_tables = [
            'qr_menu_tablolari', 'qr_menu_oturumlari', 'qr_menu_siparisleri'
        ];
        
        foreach ($qr_menu_tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tests["qr_menu_{$table}"] = [
                    'status' => 'success',
                    'message' => "QR Menu System tablosu '{$table}' mevcut ({$count} kayıt)"
                ];
            } catch (Exception $e) {
                $tests["qr_menu_{$table}"] = [
                    'status' => 'error',
                    'message' => "QR Menu System tablosu '{$table}' bulunamadı: " . $e->getMessage()
                ];
            }
        }
        
    } catch (Exception $e) {
        $tests['qr_systems'] = [
            'status' => 'error',
            'message' => 'QR kod sistemleri hatası: ' . $e->getMessage()
        ];
    }
    
    return $tests;
}

/**
 * Performans metriklerini test et
 */
function testPerformanceMetrics()
{
    global $pdo;
    
    $tests = [];
    
    try {
        // Performans metrikleri tabloları
        $performance_tables = [
            'performans_metrikleri_detay', 'doluluk_orani_metrikleri',
            'gelir_analizi_metrikleri', 'musteri_memnuniyeti_metrikleri',
            'operasyonel_performans_metrikleri', 'finansal_performans_metrikleri',
            'pazar_analizi_metrikleri', 'performans_benchmark'
        ];
        
        foreach ($performance_tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tests["performance_{$table}"] = [
                    'status' => 'success',
                    'message' => "Performans tablosu '{$table}' mevcut ({$count} kayıt)"
                ];
            } catch (Exception $e) {
                $tests["performance_{$table}"] = [
                    'status' => 'error',
                    'message' => "Performans tablosu '{$table}' bulunamadı: " . $e->getMessage()
                ];
            }
        }
        
        // Tahminleme algoritmaları tabloları
        $prediction_tables = [
            'tahminleme_modelleri', 'talep_tahmini', 'fiyat_optimizasyonu',
            'gelir_tahmini', 'doluluk_tahmini', 'musteri_analizi'
        ];
        
        foreach ($prediction_tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tests["prediction_{$table}"] = [
                    'status' => 'success',
                    'message' => "Tahminleme tablosu '{$table}' mevcut ({$count} kayıt)"
                ];
            } catch (Exception $e) {
                $tests["prediction_{$table}"] = [
                    'status' => 'error',
                    'message' => "Tahminleme tablosu '{$table}' bulunamadı: " . $e->getMessage()
                ];
            }
        }
        
    } catch (Exception $e) {
        $tests['performance_metrics'] = [
            'status' => 'error',
            'message' => 'Performans metrikleri hatası: ' . $e->getMessage()
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
    <title>Sistem Entegrasyonu ve Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .test-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .test-card.database { border-left-color: #007bff; }
        .test-card.api { border-left-color: #28a745; }
        .test-card.modules { border-left-color: #17a2b8; }
        .test-card.permissions { border-left-color: #ffc107; }
        .test-card.notifications { border-left-color: #6f42c1; }
        .test-card.mobile { border-left-color: #fd7e14; }
        .test-card.qr { border-left-color: #20c997; }
        .test-card.performance { border-left-color: #e83e8c; }
        
        .test-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9rem;
        }
        .test-result.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-result.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .test-result.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .test-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .progress-ring {
            width: 60px;
            height: 60px;
        }
        
        .progress-ring circle {
            fill: transparent;
            stroke-width: 4;
            stroke-linecap: round;
        }
        
        .progress-ring .progress-ring-circle {
            stroke: #28a745;
            stroke-dasharray: 188.4;
            stroke-dashoffset: 188.4;
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
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
                        <i class="fas fa-cogs me-2"></i>Sistem Entegrasyonu ve Test
                        <small class="text-muted">Tüm modüllerin uyumlu çalışması</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#runAllTestsModal">
                                <i class="fas fa-play"></i> Tüm Testleri Çalıştır
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-tools"></i> Test Seçenekleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="runSpecificTest('database')">
                                    <i class="fas fa-database"></i> Veritabanı Testleri
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificTest('api')">
                                    <i class="fas fa-plug"></i> API Testleri
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificTest('modules')">
                                    <i class="fas fa-puzzle-piece"></i> Modül Testleri
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificTest('permissions')">
                                    <i class="fas fa-shield-alt"></i> Yetki Testleri
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="runSpecificTest('notifications')">
                                    <i class="fas fa-bell"></i> Bildirim Testleri
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

                <!-- Test Özeti -->
                <?php if (!empty($test_results)): ?>
                <div class="test-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-chart-line me-2"></i>Sistem Entegrasyon Test Sonuçları</h4>
                            <p class="mb-0">Tüm modüllerin birbirleriyle uyumlu çalışması test edildi.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="progress-ring">
                                <svg class="progress-ring" width="60" height="60">
                                    <circle class="progress-ring-circle" cx="30" cy="30" r="26" 
                                            stroke-dashoffset="<?php echo calculateOverallProgress($test_results); ?>"></circle>
                                </svg>
                            </div>
                            <div class="mt-2">
                                <strong><?php echo calculateOverallProgress($test_results); ?>%</strong>
                                <small>Başarı Oranı</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Test Sonuçları -->
                <?php if (!empty($test_results)): ?>
                <div class="row">
                    <?php foreach ($test_results as $category => $tests): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card test-card <?php echo $category; ?>">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-<?php echo getCategoryIcon($category); ?> me-2"></i>
                                    <?php echo getCategoryTitle($category); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($tests as $test_name => $result): ?>
                                <div class="test-result <?php echo $result['status']; ?>">
                                    <i class="fas fa-<?php echo getStatusIcon($result['status']); ?> me-2"></i>
                                    <?php echo $result['message']; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Test Başlatma Ekranı -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-cogs fa-4x text-muted mb-4"></i>
                                <h4>Sistem Entegrasyon Testleri</h4>
                                <p class="text-muted mb-4">
                                    Tüm modüllerin birbirleriyle uyumlu çalışmasını test etmek için 
                                    "Tüm Testleri Çalıştır" butonuna tıklayın.
                                </p>
                                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#runAllTestsModal">
                                    <i class="fas fa-play me-2"></i>Testleri Başlat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Tüm Testleri Çalıştır Modal -->
    <div class="modal fade" id="runAllTestsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sistem Entegrasyon Testleri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="run_all_tests">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu işlem tüm sistem entegrasyon testlerini çalıştıracak ve 
                            modüllerin birbirleriyle uyumlu çalışıp çalışmadığını kontrol edecektir.
                        </div>
                        
                        <h6>Test Edilecek Alanlar:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Veritabanı Bağlantıları</li>
                            <li><i class="fas fa-check text-success me-2"></i>API Endpoint'leri</li>
                            <li><i class="fas fa-check text-success me-2"></i>Modül Entegrasyonları</li>
                            <li><i class="fas fa-check text-success me-2"></i>Yetki Sistemi</li>
                            <li><i class="fas fa-check text-success me-2"></i>Bildirim Sistemi</li>
                            <li><i class="fas fa-check text-success me-2"></i>Mobil Uygulamalar</li>
                            <li><i class="fas fa-check text-success me-2"></i>QR Kod Sistemleri</li>
                            <li><i class="fas fa-check text-success me-2"></i>Performans Metrikleri</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Testleri Başlat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runSpecificTest(testType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'test_' + testType;
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

function calculateOverallProgress($test_results)
{
    if (empty($test_results)) return 0;
    
    $total_tests = 0;
    $successful_tests = 0;
    
    foreach ($test_results as $category => $tests) {
        foreach ($tests as $test_name => $result) {
            $total_tests++;
            if ($result['status'] === 'success') {
                $successful_tests++;
            }
        }
    }
    
    return $total_tests > 0 ? round(($successful_tests / $total_tests) * 100) : 0;
}

function getCategoryIcon($category)
{
    $icons = [
        'database' => 'database',
        'api' => 'plug',
        'modules' => 'puzzle-piece',
        'permissions' => 'shield-alt',
        'notifications' => 'bell',
        'mobile' => 'mobile-alt',
        'qr_systems' => 'qrcode',
        'performance' => 'chart-line'
    ];
    
    return $icons[$category] ?? 'cog';
}

function getCategoryTitle($category)
{
    $titles = [
        'database' => 'Veritabanı Bağlantıları',
        'api' => 'API Endpoint\'leri',
        'modules' => 'Modül Entegrasyonları',
        'permissions' => 'Yetki Sistemi',
        'notifications' => 'Bildirim Sistemi',
        'mobile' => 'Mobil Uygulamalar',
        'qr_systems' => 'QR Kod Sistemleri',
        'performance' => 'Performans Metrikleri'
    ];
    
    return $titles[$category] ?? ucfirst($category);
}

function getStatusIcon($status)
{
    $icons = [
        'success' => 'check-circle',
        'error' => 'times-circle',
        'warning' => 'exclamation-triangle'
    ];
    
    return $icons[$status] ?? 'question-circle';
}
?>
