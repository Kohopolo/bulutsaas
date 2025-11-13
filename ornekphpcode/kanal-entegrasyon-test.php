<?php
// Debug için hata gösterimi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
requireDetailedPermission('sistem_ayarlari_goruntule', 'Sistem ayarları görüntüleme yetkiniz bulunmamaktadır.');

// Kanal API sınıflarını yükle
require_once '../includes/kanal_apis/BookingAPI.php';
require_once '../includes/kanal_apis/AgodaAPI.php';
require_once '../includes/kanal_apis/ExpediaAPI.php';
require_once '../includes/kanal_apis/TatilcomAPI.php';
require_once '../includes/kanal_apis/TatilsepetiAPI.php';

$page_title = 'Kanal Entegrasyon Test Merkezi';
$test_results = [];
$selected_channel = $_POST['channel'] ?? 'booking';
$test_type = $_POST['test_type'] ?? '';

// Test modunda API bilgileri (gerçek API keyleri veritabanından alınmalı)
$test_credentials = [
    'booking' => [
        'api_key' => 'test_booking_api_key_12345',
        'api_secret' => 'test_booking_secret_67890',
        'test_mode' => true
    ],
    'agoda' => [
        'api_key' => 'test_agoda_api_key_12345',
        'api_secret' => 'test_agoda_secret_67890',
        'test_mode' => true
    ],
    'expedia' => [
        'api_key' => 'test_expedia_api_key_12345',
        'api_secret' => 'test_expedia_secret_67890',
        'test_mode' => true
    ],
    'tatilcom' => [
        'api_key' => 'test_tatilcom_api_key_12345',
        'api_secret' => 'test_tatilcom_secret_67890',
        'test_mode' => true
    ],
    'tatilsepeti' => [
        'api_key' => 'test_tatilsepeti_api_key_12345',
        'api_secret' => 'test_tatilsepeti_secret_67890',
        'test_mode' => true
    ]
];

// Test çalıştır
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($test_type)) {
    validateCSRFToken($_POST['csrf_token'] ?? '');
    
    $credentials = $test_credentials[$selected_channel] ?? null;
    
    if (!$credentials) {
        $test_results[] = [
            'test' => 'Kanal Seçimi',
            'status' => 'error',
            'message' => 'Geçersiz kanal seçimi',
            'duration' => 0
        ];
    } else {
        switch ($selected_channel) {
            case 'booking':
                $api = new BookingAPI(
                    $credentials['api_key'],
                    $credentials['api_secret'],
                    $credentials['test_mode']
                );
                break;
            case 'agoda':
                $api = new AgodaAPI(
                    $credentials['api_key'],
                    $credentials['api_secret'],
                    $credentials['test_mode']
                );
                break;
            case 'expedia':
                $api = new ExpediaAPI(
                    $credentials['api_key'],
                    $credentials['api_secret'],
                    $credentials['test_mode']
                );
                break;
            case 'tatilcom':
                $api = new TatilcomAPI(
                    $credentials['api_key'],
                    $credentials['api_secret'],
                    $credentials['test_mode']
                );
                break;
            case 'tatilsepeti':
                $api = new TatilsepetiAPI(
                    $credentials['api_key'],
                    $credentials['api_secret'],
                    $credentials['test_mode']
                );
                error_log("TatilSepeti API oluşturuldu - Test Mode: " . ($credentials['test_mode'] ? 'true' : 'false'));
                break;
            default:
                $api = null;
        }
        
        if ($api) {
            // Test türüne göre test çalıştır
            switch ($test_type) {
                case 'connection':
                    $test_results = runConnectionTest($api, $selected_channel);
                    break;
                case 'reservations':
                    $test_results = runReservationTests($api, $selected_channel);
                    break;
                case 'inventory':
                    $test_results = runInventoryTests($api, $selected_channel);
                    break;
                case 'rates':
                    $test_results = runRateTests($api, $selected_channel);
                    break;
                case 'full':
                    $test_results = runFullTests($api, $selected_channel);
                    break;
            }
        }
    }
}

// Test fonksiyonları
function runConnectionTest($api, $channel) {
    $results = [];
    $start = microtime(true);
    
    $response = $api->testConnection();
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'API Bağlantı Testi',
        'status' => $response['success'] ? 'success' : 'error',
        'message' => $response['message'] ?? 'Test tamamlandı',
        'duration' => $duration,
        'data' => $response['data'] ?? null
    ];
    
    return $results;
}

function runReservationTests($api, $channel) {
    $results = [];
    
    // 1. Rezervasyonları getir
    $start = microtime(true);
    $response = $api->getReservations(date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Rezervasyon Listesi Getirme',
        'status' => $response['success'] ? 'success' : 'error',
        'message' => $response['message'] ?? 'Test tamamlandı',
        'duration' => $duration,
        'data' => [
            'total_reservations' => $response['total'] ?? 0,
            'sample' => isset($response['data'][0]) ? $response['data'][0] : null
        ]
    ];
    
    // 2. Test rezervasyonu oluştur (sadece test modunda)
    $test_reservation = [
        'hotel_id' => 'test_hotel_123',
        'room_type_id' => 'test_room_456',
        'check_in' => date('Y-m-d', strtotime('+7 days')),
        'check_out' => date('Y-m-d', strtotime('+10 days')),
        'adults' => 2,
        'children' => 0,
        'guest_name' => 'Test Customer',
        'guest_email' => 'test@example.com',
        'guest_phone' => '+905551234567'
    ];
    
    $start = microtime(true);
    $response = $api->createReservation($test_reservation);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Test Rezervasyonu Oluşturma',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Test rezervasyonu oluşturuldu',
        'duration' => $duration,
        'data' => $response['data'] ?? null
    ];
    
    return $results;
}

function runInventoryTests($api, $channel) {
    $results = [];
    
    // Stok güncelleme testi
    $start = microtime(true);
    $response = $api->updateInventory('test_room_123', date('Y-m-d', strtotime('+1 day')), 5);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Stok Güncelleme',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Stok güncelleme test edildi',
        'duration' => $duration,
        'data' => null
    ];
    
    // Müsaitlik kontrolü
    $start = microtime(true);
    $response = $api->checkAvailability(
        'test_room_123',
        date('Y-m-d', strtotime('+1 day')),
        date('Y-m-d', strtotime('+3 days')),
        2
    );
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Müsaitlik Kontrolü',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Müsaitlik kontrolü yapıldı',
        'duration' => $duration,
        'data' => $response['data'] ?? null
    ];
    
    return $results;
}

function runRateTests($api, $channel) {
    $results = [];
    
    // Fiyat güncelleme
    $start = microtime(true);
    $response = $api->updateRates('test_room_123', date('Y-m-d', strtotime('+1 day')), 1500.00, 5);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Fiyat Güncelleme',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Fiyat güncelleme test edildi',
        'duration' => $duration,
        'data' => null
    ];
    
    return $results;
}

function runFullTests($api, $channel) {
    $results = [];
    
    // Tüm testleri sırayla çalıştır
    $results = array_merge($results, runConnectionTest($api, $channel));
    $results = array_merge($results, runReservationTests($api, $channel));
    $results = array_merge($results, runInventoryTests($api, $channel));
    $results = array_merge($results, runRateTests($api, $channel));
    
    // Oda tipleri getirme
    $start = microtime(true);
    $response = $api->getRoomTypes();
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Oda Tipleri Getirme',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Oda tipleri getirildi',
        'duration' => $duration,
        'data' => $response['data'] ?? null
    ];
    
    // Komisyon bilgileri
    $start = microtime(true);
    $response = $api->getCommissionInfo();
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $results[] = [
        'test' => 'Komisyon Bilgileri',
        'status' => $response['success'] ? 'success' : 'warning',
        'message' => $response['message'] ?? 'Komisyon bilgileri getirildi',
        'duration' => $duration,
        'data' => $response['data'] ?? null
    ];
    
    return $results;
}

// Test sonuç istatistikleri
$total_tests = count($test_results);
$success_count = count(array_filter($test_results, fn($r) => $r['status'] === 'success'));
$error_count = count(array_filter($test_results, fn($r) => $r['status'] === 'error'));
$warning_count = count(array_filter($test_results, fn($r) => $r['status'] === 'warning'));
$total_duration = array_sum(array_column($test_results, 'duration'));

include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?= htmlspecialchars($page_title) ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Kanal Entegrasyon Test</li>
    </ol>

    <!-- Test Formu -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-vial me-1"></i>
            Test Yapılandırması
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?= generateCSRFTokenInput() ?>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="channel" class="form-label">Kanal Seçin</label>
                        <select class="form-select" id="channel" name="channel" required>
                            <optgroup label="Uluslararası Kanallar">
                                <option value="booking" <?= $selected_channel === 'booking' ? 'selected' : '' ?>>Booking.com</option>
                                <option value="agoda" <?= $selected_channel === 'agoda' ? 'selected' : '' ?>>Agoda</option>
                                <option value="expedia" <?= $selected_channel === 'expedia' ? 'selected' : '' ?>>Expedia</option>
                            </optgroup>
                            <optgroup label="Türk Kanalları 🇹🇷">
                                <option value="tatilcom" <?= $selected_channel === 'tatilcom' ? 'selected' : '' ?>>Tatil.com</option>
                                <option value="tatilsepeti" <?= $selected_channel === 'tatilsepeti' ? 'selected' : '' ?>>TatilSepeti</option>
                            </optgroup>
                        </select>
                    </div>                    
                    <div class="col-md-4">
                        <label for="test_type" class="form-label">Test Türü</label>
                        <select class="form-select" id="test_type" name="test_type" required>
                            <option value="">Test türü seçin...</option>
                            <option value="connection" <?= $test_type === 'connection' ? 'selected' : '' ?>>Bağlantı Testi</option>
                            <option value="reservations" <?= $test_type === 'reservations' ? 'selected' : '' ?>>Rezervasyon Testleri</option>
                            <option value="inventory" <?= $test_type === 'inventory' ? 'selected' : '' ?>>Stok Testleri</option>
                            <option value="rates" <?= $test_type === 'rates' ? 'selected' : '' ?>>Fiyat Testleri</option>
                            <option value="full" <?= $test_type === 'full' ? 'selected' : '' ?>>Tam Test Paketi</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-play me-1"></i> Testi Başlat
                        </button>
                    </div>
                </div>
                
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Not:</strong> Bu testler <strong>test modunda</strong> çalışır. Gerçek API istekleri göndermez.
                    Gerçek API anahtarlarını kullanmak için veritabanında kanal ayarlarını yapılandırın.
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($test_results)): ?>
    <!-- Test Sonuçları İstatistikleri -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clipboard-list fa-2x me-3"></i>
                        <div>
                            <div class="small">Toplam Test</div>
                            <div class="fs-4 fw-bold"><?= $total_tests ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <div class="small">Başarılı</div>
                            <div class="fs-4 fw-bold"><?= $success_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <div class="small">Uyarı</div>
                            <div class="fs-4 fw-bold"><?= $warning_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-times-circle fa-2x me-3"></i>
                        <div>
                            <div class="small">Hatalı</div>
                            <div class="fs-4 fw-bold"><?= $error_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Sonuçları Detayı -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-chart-bar me-1"></i>
                Test Sonuçları - <?= ucfirst($selected_channel) ?>
            </div>
            <div class="text-muted">
                <small>Toplam Süre: <?= round($total_duration, 2) ?> ms</small>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Test Adı</th>
                            <th width="15%">Durum</th>
                            <th width="10%">Süre (ms)</th>
                            <th width="30%">Mesaj</th>
                            <th width="15%">Detaylar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($test_results as $index => $result): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($result['test']) ?></td>
                            <td>
                                <?php if ($result['status'] === 'success'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i> Başarılı
                                    </span>
                                <?php elseif ($result['status'] === 'error'): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i> Hatalı
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Uyarı
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= round($result['duration'], 2) ?></td>
                            <td><?= htmlspecialchars($result['message']) ?></td>
                            <td>
                                <?php if (!empty($result['data'])): ?>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal<?= $index ?>">
                                        <i class="fas fa-eye me-1"></i> Görüntüle
                                    </button>
                                    
                                    <!-- Detail Modal -->
                                    <div class="modal fade" id="detailModal<?= $index ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= htmlspecialchars($result['test']) ?> - Detaylar</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre class="bg-light p-3 rounded"><?= htmlspecialchars(json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Entegrasyon Dokümantasyonu -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-book me-1"></i>
            Entegrasyon Rehberi
        </div>
        <div class="card-body">
            <h5>Kanal Entegrasyonu Nasıl Çalışır?</h5>
            
            <div class="accordion" id="integrationGuide">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#step1">
                            1. API Anahtarlarını Alma
                        </button>
                    </h2>
                    <div id="step1" class="accordion-collapse collapse show" data-bs-parent="#integrationGuide">
                        <div class="accordion-body">
                            <ul>
                                <li><strong>Booking.com:</strong> Extranet > Connectivity > API Settings</li>
                                <li><strong>Agoda:</strong> YCS Partner Portal > API Credentials</li>
                                <li><strong>Expedia:</strong> Partner Central > API Integration</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step2">
                            2. Sisteme API Bilgilerini Girme
                        </button>
                    </h2>
                    <div id="step2" class="accordion-collapse collapse" data-bs-parent="#integrationGuide">
                        <div class="accordion-body">
                            <p>Kanal API bilgilerinizi girmek için:</p>
                            <ol>
                                <li>Admin Panel > Ayarlar > Kanal Yönetimi</li>
                                <li>İlgili kanalı seçin</li>
                                <li>API Key ve Secret bilgilerini girin</li>
                                <li>Test modunu aktif edin</li>
                                <li>Kaydet ve test edin</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step3">
                            3. Otomatik Senkronizasyon
                        </button>
                    </h2>
                    <div id="step3" class="accordion-collapse collapse" data-bs-parent="#integrationGuide">
                        <div class="accordion-body">
                            <p>Sistem otomatik olarak şu işlemleri yapar:</p>
                            <ul>
                                <li>Her 15 dakikada bir rezervasyon senkronizasyonu</li>
                                <li>Fiyat değişikliklerinde anında güncelleme</li>
                                <li>Stok değişikliklerinde otomatik bildirim</li>
                                <li>Rezervasyon iptallerinde iki yönlü senkronizasyon</li>
                            </ul>
                            <p>Cron işleri: <code>cron/kanal_senkronizasyon.php</code></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

