<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_api_test', 'Kanal API yönetimi yetkiniz bulunmamaktadır.');

$page_title = "Kanal API Test";

$success_message = '';
$error_message = '';

// Kanal ID kontrolü
$kanal_id = intval($_GET['id'] ?? 0);
if (!$kanal_id) {
    $_SESSION['error_message'] = 'Geçersiz kanal ID.';
    header('Location: kanal-listesi.php');
    exit;
}

// Kanal bilgilerini getir
$kanal = fetchOne("SELECT * FROM kanallar WHERE id = ?", [$kanal_id]);
if (!$kanal) {
    $_SESSION['error_message'] = 'Kanal bulunamadı.';
    header('Location: kanal-listesi.php');
    exit;
}

// API test sonuçları
$test_results = [];

// API test işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api'])) {
    try {
        // CSRF kontrolü
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Güvenlik hatası. Lütfen sayfayı yenileyin.');
        }

        $test_type = $_POST['test_type'] ?? 'connection';
        
        // Kanal tipine göre API sınıfını yükle
        $api_class = null;
        switch ($kanal['kanal_kodu']) {
            case 'BOOKING':
                require_once '../includes/kanal_apis/BookingAPI.php';
                $api_class = new BookingAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'EXPEDIA':
                require_once '../includes/kanal_apis/ExpediaAPI.php';
                $api_class = new ExpediaAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'AGODA':
                require_once '../includes/kanal_apis/AgodaAPI.php';
                $api_class = new AgodaAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'HOTELS':
                require_once '../includes/kanal_apis/HotelsAPI.php';
                $api_class = new HotelsAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'ETS':
                require_once '../includes/kanal_apis/ETSAPI.php';
                $api_class = new ETSAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'TATILSEPETI':
                require_once '../includes/kanal_apis/TatilsepetiAPI.php';
                $api_class = new TatilsepetiAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'TATILCOM':
                require_once '../includes/kanal_apis/TatilcomAPI.php';
                $api_class = new TatilcomAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            case 'SETUR':
                require_once '../includes/kanal_apis/SeturAPI.php';
                $api_class = new SeturAPI($kanal['api_key'], $kanal['api_secret'], $kanal['test_modu']);
                break;
            default:
                throw new Exception('Bu kanal için API sınıfı bulunamadı.');
        }

        // Test türüne göre işlem yap
        switch ($test_type) {
            case 'connection':
                $test_results = $api_class->testConnection();
                break;
            case 'reservations':
                $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
                $end_date = $_POST['end_date'] ?? date('Y-m-d');
                $test_results = $api_class->getReservations($start_date, $end_date);
                break;
            case 'room_types':
                $test_results = $api_class->getRoomTypes();
                break;
            case 'availability':
                $room_type_id = $_POST['room_type_id'] ?? 1;
                $check_in = $_POST['check_in'] ?? date('Y-m-d', strtotime('+1 day'));
                $check_out = $_POST['check_out'] ?? date('Y-m-d', strtotime('+2 days'));
                $guests = $_POST['guests'] ?? 2;
                $test_results = $api_class->checkAvailability($room_type_id, $check_in, $check_out, $guests);
                break;
            case 'commission':
                $test_results = $api_class->getCommissionInfo();
                break;
            default:
                throw new Exception('Geçersiz test türü.');
        }

        // API durumunu güncelle
        $api_status = $test_results['success'] ? 'aktif' : 'hata';
        $api_error_message = $test_results['success'] ? null : $test_results['message'];
        
        $update_sql = "UPDATE kanallar SET api_durumu = ?, api_hata_mesaji = ?, son_senkronizasyon = NOW() WHERE id = ?";
        executeQuery($update_sql, [$api_status, $api_error_message, $kanal_id]);

        if ($test_results['success']) {
            $success_message = "API testi başarılı: " . $test_results['message'];
        } else {
            $error_message = "API testi başarısız: " . $test_results['message'];
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $test_results = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// API loglarını getir
$api_logs = fetchAll("
    SELECT * FROM kanal_api_loglari 
    WHERE kanal_id = ? 
    ORDER BY olusturma_tarihi DESC 
    LIMIT 20
", [$kanal_id]);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .desktop-container {
            padding: 20px;
            min-height: 100vh;
        }
        
        .desktop-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .test-section {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .test-result {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #28a745;
        }
        
        .test-result.error {
            border-left-color: #dc3545;
        }
        
        .test-result pre {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            overflow-x: auto;
            font-size: 0.9rem;
        }
        
        .kanal-info {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .kanal-info h5 {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            border-color: rgba(0, 0, 0, 0.1);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .status-basarili {
            background: #d4edda;
            color: #155724;
        }
        
        .status-hata {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-timeout {
            background: #fff3cd;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .desktop-container {
                padding: 10px;
            }
            
            .content-card {
                padding: 20px;
            }
            
            .test-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-plug me-3" style="color: #667eea;"></i>
                        API Test
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <i class="fas fa-clock me-2"></i>
                        <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="quick-actions">
                        <a href="kanal-listesi.php" class="quick-action-btn">
                            <i class="fas fa-list"></i>
                            Kanal Listesi
                        </a>
                        <a href="kanal-duzenle.php?id=<?= $kanal['id'] ?>" class="quick-action-btn">
                            <i class="fas fa-edit"></i>
                            Kanal Düzenle
                        </a>
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanal Bilgisi -->
        <div class="kanal-info">
            <h5>
                <i class="fas fa-info-circle me-2"></i>
                <?= htmlspecialchars($kanal['kanal_adi']) ?> API Bilgileri
            </h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <span class="info-label">Kanal Kodu:</span>
                        <span class="info-value"><?= htmlspecialchars($kanal['kanal_kodu']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">API Endpoint:</span>
                        <span class="info-value"><?= htmlspecialchars($kanal['api_endpoint']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Test Modu:</span>
                        <span class="info-value">
                            <span class="badge bg-<?= $kanal['test_modu'] ? 'info' : 'secondary' ?>">
                                <?= $kanal['test_modu'] ? 'Açık' : 'Kapalı' ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <span class="info-label">API Durumu:</span>
                        <span class="info-value">
                            <span class="badge bg-<?= $kanal['api_durumu'] === 'aktif' ? 'success' : ($kanal['api_durumu'] === 'hata' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($kanal['api_durumu']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Son Senkronizasyon:</span>
                        <span class="info-value"><?= $kanal['son_senkronizasyon'] ? date('d.m.Y H:i', strtotime($kanal['son_senkronizasyon'])) : 'Hiç' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">API Key:</span>
                        <span class="info-value"><?= $kanal['api_key'] ? '***' . substr($kanal['api_key'], -4) : 'Yok' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Test Formu -->
        <div class="content-card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="test-section">
                <h4 class="section-title">
                    <i class="fas fa-vial"></i>
                    API Test İşlemleri
                </h4>
                
                <form method="POST" id="apiTestForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="test_api" value="1">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Test Türü</label>
                            <select name="test_type" class="form-select" id="testType" required>
                                <option value="connection">Bağlantı Testi</option>
                                <option value="reservations">Rezervasyon Getir</option>
                                <option value="room_types">Oda Tipleri Getir</option>
                                <option value="availability">Müsaitlik Kontrolü</option>
                                <option value="commission">Komisyon Bilgileri</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4" id="dateRange" style="display: none;">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                        </div>
                        
                        <div class="col-md-4" id="endDate" style="display: none;">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-4" id="availabilityParams" style="display: none;">
                            <label class="form-label">Oda Tipi ID</label>
                            <input type="number" name="room_type_id" class="form-control" value="1" min="1">
                        </div>
                        
                        <div class="col-md-4" id="checkInDate" style="display: none;">
                            <label class="form-label">Giriş Tarihi</label>
                            <input type="date" name="check_in" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        
                        <div class="col-md-4" id="checkOutDate" style="display: none;">
                            <label class="form-label">Çıkış Tarihi</label>
                            <input type="date" name="check_out" class="form-control" value="<?= date('Y-m-d', strtotime('+2 days')) ?>">
                        </div>
                        
                        <div class="col-md-4" id="guestsCount" style="display: none;">
                            <label class="form-label">Misafir Sayısı</label>
                            <input type="number" name="guests" class="form-control" value="2" min="1" max="10">
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i> API Testini Çalıştır
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Test Sonuçları -->
            <?php if (!empty($test_results)): ?>
                <div class="test-result <?= $test_results['success'] ? '' : 'error' ?>">
                    <h5>
                        <i class="fas fa-<?= $test_results['success'] ? 'check-circle text-success' : 'times-circle text-danger' ?> me-2"></i>
                        Test Sonucu
                    </h5>
                    <p><strong>Durum:</strong> <?= $test_results['success'] ? 'Başarılı' : 'Başarısız' ?></p>
                    <p><strong>Mesaj:</strong> <?= htmlspecialchars($test_results['message']) ?></p>
                    
                    <?php if (isset($test_results['data'])): ?>
                        <h6>Veri:</h6>
                        <pre><?= json_encode($test_results['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- API Logları -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-history me-2"></i>
                Son API Logları
            </h5>
            
            <?php if (empty($api_logs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Henüz API log kaydı bulunmamaktadır.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Metod</th>
                                <th>Endpoint</th>
                                <th>Durum</th>
                                <th>Yanıt Süresi</th>
                                <th>Hata Mesajı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($api_logs as $log): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i:s', strtotime($log['olusturma_tarihi'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $log['api_metodu'] === 'GET' ? 'info' : ($log['api_metodu'] === 'POST' ? 'success' : 'warning') ?>">
                                            <?= $log['api_metodu'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($log['istek_url']) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $log['durum'] ?>">
                                            <?= ucfirst($log['durum']) ?>
                                        </span>
                                    </td>
                                    <td><?= $log['yanit_suresi'] ? number_format($log['yanit_suresi'], 3) . 's' : '-' ?></td>
                                    <td>
                                        <?php if ($log['hata_mesaji']): ?>
                                            <small class="text-danger"><?= htmlspecialchars($log['hata_mesaji']) ?></small>
                                        <?php else: ?>
                                            <span class="text-success">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test türüne göre form alanlarını göster/gizle
        document.getElementById('testType').addEventListener('change', function() {
            const testType = this.value;
            
            // Tüm ek alanları gizle
            document.getElementById('dateRange').style.display = 'none';
            document.getElementById('endDate').style.display = 'none';
            document.getElementById('availabilityParams').style.display = 'none';
            document.getElementById('checkInDate').style.display = 'none';
            document.getElementById('checkOutDate').style.display = 'none';
            document.getElementById('guestsCount').style.display = 'none';
            
            // Test türüne göre gerekli alanları göster
            if (testType === 'reservations') {
                document.getElementById('dateRange').style.display = 'block';
                document.getElementById('endDate').style.display = 'block';
            } else if (testType === 'availability') {
                document.getElementById('availabilityParams').style.display = 'block';
                document.getElementById('checkInDate').style.display = 'block';
                document.getElementById('checkOutDate').style.display = 'block';
                document.getElementById('guestsCount').style.display = 'block';
            }
        });

        // Canlı saat güncelleme
        function updateLiveTime() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
            const timeElement = document.querySelector('.current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateLiveTime();
        setInterval(updateLiveTime, 1000);
    </script>
</body>
</html>
