<?php
/**
 * Multi Otel - Çoklu Oda Rezervasyon Ekleme
 * Otel seçimi ile çoklu oda rezervasyonu
 */

require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/price-functions.php';
require_once '../../includes/multi-reservation-functions.php';
require_once 'includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
requireDetailedPermission('rezervasyon_ekle', 'Rezervasyon ekleme yetkiniz bulunmamaktadır.');

// CSRF token'ı başlat
initCSRFProtection();

$success_message = '';
$error_message = '';

// Türkçe büyük harf fonksiyonu
function turkishUppercase($text) {
    $turkishChars = [
        'ç' => 'Ç', 'ğ' => 'Ğ', 'ı' => 'I', 'ö' => 'Ö', 'ş' => 'Ş', 'ü' => 'Ü',
        'i' => 'İ'
    ];
    
    $text = strtr($text, $turkishChars);
    return strtoupper($text);
}

// AJAX - TC Kimlik ile müşteri sorgulama
if (isset($_GET['action']) && $_GET['action'] === 'get_customer' && isset($_GET['tc_kimlik'])) {
    header('Content-Type: application/json');
    
    $tc_kimlik = preg_replace('/[^0-9]/', '', $_GET['tc_kimlik']);
    
    if (strlen($tc_kimlik) === 11) {
        $musteri = fetchOne("SELECT * FROM musteriler WHERE tc_kimlik = ?", [$tc_kimlik]);
        
        if ($musteri) {
            echo json_encode([
                'success' => true,
                'musteri' => [
                    'ad' => $musteri['ad'],
                    'soyad' => $musteri['soyad'],
                    'email' => $musteri['email'],
                    'telefon' => $musteri['telefon'],
                    'adres' => $musteri['adres'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Müşteri bulunamadı']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz TC kimlik']);
    }
    exit;
}

// AJAX - Otel bazlı oda tiplerini getir
if (isset($_GET['action']) && $_GET['action'] === 'get_room_types' && isset($_GET['otel_id'])) {
    header('Content-Type: application/json');
    
    $otel_id = intval($_GET['otel_id']);
    $oda_tipleri = getOtelOdaTipleri($otel_id);
    
    echo json_encode([
        'success' => true,
        'oda_tipleri' => $oda_tipleri
    ]);
    exit;
}

// AJAX - Otel bazlı müsait odaları getir
if (isset($_GET['action']) && $_GET['action'] === 'get_available_rooms' && isset($_GET['otel_id'])) {
    header('Content-Type: application/json');
    
    $otel_id = intval($_GET['otel_id']);
    $oda_tipi_id = intval($_GET['oda_tipi_id']);
    $giris_tarihi = $_GET['giris_tarihi'];
    $cikis_tarihi = $_GET['cikis_tarihi'];
    
    $odalar = checkOtelRoomAvailability($otel_id, $oda_tipi_id, $giris_tarihi, $cikis_tarihi);
    
    echo json_encode([
        'success' => true,
        'odalar' => $odalar
    ]);
    exit;
}

// Form gönderildiğinde işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_multi_reservation'])) {
    error_log('=== MULTI HOTEL RESERVATION POST RECEIVED ===');
    
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log('CSRF token validation failed');
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        error_log('CSRF token validated successfully');
        try {
            // Otel seçimi
            $otel_id = intval($_POST['otel_id'] ?? 0);
            if ($otel_id <= 0) {
                throw new Exception('Otel seçimi gerekli');
            }
            
            // Müşteri bilgileri
            $tc_kimlik_digits = '';
            for ($i = 0; $i < 11; $i++) {
                $tc_kimlik_digits .= $_POST["tc_digit_$i"] ?? '';
            }
            
            $customerData = [
                'ad' => turkishUppercase(trim($_POST['musteri_ad'] ?? '')),
                'soyad' => turkishUppercase(trim($_POST['musteri_soyad'] ?? '')),
                'email' => trim($_POST['musteri_email'] ?? ''),
                'telefon' => preg_replace('/[^0-9]/', '', trim($_POST['musteri_telefon'] ?? '')),
                'tc_kimlik' => $tc_kimlik_digits,
                'adres' => trim($_POST['musteri_adres'] ?? '')
            ];
            
            // Tarih seçim modu
            $date_mode = $_POST['date_mode'] ?? 'common';
            
            // Odaları parse et
            $roomsData = [];
            $roomIndex = 0;
            
            while (isset($_POST["room_{$roomIndex}_oda_tipi"])) {
                // Tarihler (ortak veya oda bazlı)
                if ($date_mode === 'common') {
                    $giris_tarihi = $_POST['giris_tarihi'] ?? '';
                    $cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
                } else {
                    $giris_tarihi = $_POST["room_{$roomIndex}_giris_tarihi"] ?? '';
                    $cikis_tarihi = $_POST["room_{$roomIndex}_cikis_tarihi"] ?? '';
                }
                
                $odaTipiId = intval($_POST["room_{$roomIndex}_oda_tipi"]);
                $odaNumarasiId = intval($_POST["room_{$roomIndex}_oda_numarasi"]);
                $yetiskinSayisi = intval($_POST["room_{$roomIndex}_yetiskin_sayisi"] ?? 1);
                $cocukSayisi = intval($_POST["room_{$roomIndex}_cocuk_sayisi"] ?? 0);
                
                // Yetişkin detayları
                $yetiskinDetaylari = [];
                for ($i = 0; $i < $yetiskinSayisi; $i++) {
                    $yetiskinDetaylari[] = [
                        'ad' => turkishUppercase(trim($_POST["room_{$roomIndex}_adult_ad_{$i}"] ?? '')),
                        'soyad' => turkishUppercase(trim($_POST["room_{$roomIndex}_adult_soyad_{$i}"] ?? '')),
                        'tc_kimlik' => preg_replace('/[^0-9]/', '', trim($_POST["room_{$roomIndex}_adult_tc_{$i}"] ?? '')),
                        'cinsiyet' => $_POST["room_{$roomIndex}_adult_cinsiyet_{$i}"] ?? ''
                    ];
                }
                
                // Çocuk detayları
                $cocukDetaylari = [];
                for ($i = 0; $i < $cocukSayisi; $i++) {
                    $cocukDetaylari[] = [
                        'ad' => turkishUppercase(trim($_POST["room_{$roomIndex}_child_ad_{$i}"] ?? '')),
                        'soyad' => turkishUppercase(trim($_POST["room_{$roomIndex}_child_soyad_{$i}"] ?? '')),
                        'yas' => intval($_POST["room_{$roomIndex}_child_yas_{$i}"] ?? 0),
                        'cinsiyet' => $_POST["room_{$roomIndex}_child_cinsiyet_{$i}"] ?? ''
                    ];
                }
                
                // Çocuk yaşlarını array'e çevir
                $cocukYaslari = [];
                if ($cocukSayisi > 0) {
                    for ($i = 0; $i < $cocukSayisi; $i++) {
                        $yas = intval($_POST["room_{$roomIndex}_child_yas_{$i}"] ?? 0);
                        if ($yas > 0) {
                            $cocukYaslari[] = $yas;
                        }
                    }
                }
                
                // Detaylı fiyat hesaplama
                error_log("Oda $roomIndex için detaylı fiyat hesaplanıyor - Otel: $otel_id, OdaTipi: $odaTipiId, Giriş: $giris_tarihi, Çıkış: $cikis_tarihi, Yetişkin: $yetiskinSayisi, Çocuk Yaşları: " . json_encode($cocukYaslari));
                
                $fiyatSonucu = calculateOtelPrice($otel_id, $odaTipiId, $giris_tarihi, $cikis_tarihi, $yetiskinSayisi, $cocukYaslari);
                
                if ($fiyatSonucu['success'] ?? false) {
                    $fiyat = $fiyatSonucu['toplam_fiyat'] ?? 0;
                    error_log("Oda $roomIndex fiyat başarıyla hesaplandı: {$fiyat} TL");
                } else {
                    // Hata durumunda fallback
                    error_log("Oda $roomIndex fiyat hatası: " . ($fiyatSonucu['message'] ?? 'Bilinmeyen hata'));
                    $odaTipi = fetchOne("SELECT base_price FROM oda_tipleri WHERE id = ? AND otel_id = ?", [$odaTipiId, $otel_id]);
                    $gunSayisi = (strtotime($cikis_tarihi) - strtotime($giris_tarihi)) / 86400;
                    $fiyat = ($odaTipi['base_price'] ?? 0) * max(1, $gunSayisi);
                    error_log("Fallback fiyat kullanıldı: {$fiyat} TL");
                }
                
                $roomsData[] = [
                    'oda_tipi_id' => $odaTipiId,
                    'oda_numarasi_id' => $odaNumarasiId,
                    'giris_tarihi' => $giris_tarihi,
                    'cikis_tarihi' => $cikis_tarihi,
                    'yetiskin_sayisi' => $yetiskinSayisi,
                    'cocuk_sayisi' => $cocukSayisi,
                    'yetiskin_detaylari' => $yetiskinDetaylari,
                    'cocuk_detaylari' => $cocukDetaylari,
                    'toplam_tutar' => $fiyat
                ];
                
                $roomIndex++;
            }
            
            // Ortak veriler
            $commonData = [
                'kanal_id' => intval($_POST['kanal_id'] ?? 1),
                'kanal_komisyon_orani' => floatval($_POST['kanal_komisyon_orani'] ?? 0),
                'kanal_komisyon_tutari' => floatval($_POST['kanal_komisyon_tutari'] ?? 0),
                'odeme_yontemi' => $_POST['odeme_yontemi'] ?? 'nakit',
                'odeme_miktari' => floatval($_POST['odeme_miktari'] ?? 0),
                'otomatik_checkin' => isset($_POST['otomatik_checkin']) ? true : false
            ];
            
            // Validasyon
            if (empty($customerData['ad'])) {
                throw new Exception('Müşteri adı zorunludur');
            }
            if (empty($customerData['soyad'])) {
                throw new Exception('Müşteri soyadı zorunludur');
            }
            if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçerli bir email adresi giriniz');
            }
            if (empty($customerData['telefon'])) {
                throw new Exception('Telefon numarası zorunludur');
            }
            if (empty($roomsData)) {
                throw new Exception('En az bir oda eklenmelidir');
            }
            
            // Multi otel rezervasyon oluştur
            error_log('Calling createMultiHotelReservation...');
            error_log('Otel ID: ' . $otel_id);
            error_log('Customer Data: ' . json_encode($customerData));
            error_log('Rooms Count: ' . count($roomsData));
            
            $result = createMultiHotelReservation($otel_id, $customerData, $roomsData, $commonData);
            
            error_log('Result: ' . json_encode($result));
            
            if ($result['success']) {
                error_log('SUCCESS! Redirecting to rezervasyonlar.php');
                
                // Başarı mesajını session'a kaydet
                $_SESSION['success_message'] = $result['message'];
                
                // Rezervasyon kodlarını ekle
                $kodlar = $result['codes'] ?? [];
                $_SESSION['reservation_codes'] = $kodlar;
                
                error_log('Reservation Codes: ' . implode(', ', $kodlar));
                
                // Rezervasyonlar sayfasına yönlendir
                header('Location: rezervasyonlar.php?multi_success=1&count=' . count($kodlar));
                exit;
            } else {
                error_log('FAILED! Errors: ' . json_encode($result['errors'] ?? []));
                $error_message = 'Rezervasyon oluşturulamadı: ' . $result['message'];
            }
            
        } catch (Exception $e) {
            error_log('EXCEPTION: ' . $e->getMessage());
            error_log('Stack Trace: ' . $e->getTraceAsString());
            $error_message = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Kullanıcının yetkili olduğu otelleri getir
$user_oteller = getUserOteller($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi Otel - Çoklu Oda Rezervasyon</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .hotel-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .hotel-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .hotel-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .add-room-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .add-room-section:hover {
            background: #e9ecef;
            border-color: #0d6efd;
        }
        .guest-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .loading-rooms {
            background: #fff3cd;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        /* TC Kimlik Stil */
        .tc-kimlik-container {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .tc-digit {
            width: 40px !important;
            height: 50px !important;
            text-align: center !important;
            font-size: 20px !important;
            font-weight: bold !important;
            border: 2px solid #ddd !important;
            border-radius: 8px !important;
            padding: 0 !important;
        }
        .tc-digit:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        .tc-digit.filled {
            background-color: #d4edda !important;
            border-color: #28a745 !important;
        }
        .date-mode-switch {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Toast Animasyonları */
        .toast {
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-radius: 10px;
            min-width: 300px;
        }
        
        .toast.showing {
            animation: slideInRight 0.3s ease-out;
        }
        
        .toast.hide {
            animation: slideOutRight 0.3s ease-in;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Form alanları valid animasyonu */
        .form-control.is-valid {
            border-color: #28a745;
            background-color: #d4edda;
        }
        
        .form-control.is-valid:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        /* Fiyat kartı */
        .price-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .price-card .price-amount {
            font-size: 24px;
            font-weight: bold;
        }
        
        .room-price-display {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/multi-otel-sidebar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-hotel me-2"></i>Multi Otel - Çoklu Oda Rezervasyon
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rezervasyonlar.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Geri Dön
                        </a>
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
                
                <form method="post" id="multiReservationForm">
                    <?php echo generateCSRFTokenInput(); ?>
                    
                    <!-- Otel Seçimi -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Otel Seçimi</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label fw-bold">Otel Seçin *</label>
                                    <select class="form-select" id="otel_id" name="otel_id" required>
                                        <option value="">Otel Seçin</option>
                                        <?php foreach ($user_oteller as $otel): ?>
                                        <option value="<?php echo $otel['id']; ?>">
                                            <?php echo htmlspecialchars($otel['otel_adi']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Otel Bilgileri</label>
                                    <div id="otel-info" class="form-control-plaintext text-muted">
                                        Önce otel seçin
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Müşteri Bilgileri -->
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Müşteri Bilgileri (Tüm odalar için ortak)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">TC Kimlik No *</label>
                                    <div class="tc-kimlik-container">
                                        <?php for ($i = 0; $i < 11; $i++): ?>
                                            <input type="text" 
                                                   class="form-control tc-digit" 
                                                   id="tc_digit_<?php echo $i; ?>" 
                                                   name="tc_digit_<?php echo $i; ?>" 
                                                   maxlength="1" 
                                                   pattern="[0-9]"
                                                   data-index="<?php echo $i; ?>" 
                                                   required>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted">TC kimlik girildikten sonra müşteri bilgileri otomatik yüklenecektir</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" id="musteri_ad" name="musteri_ad" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" id="musteri_soyad" name="musteri_soyad" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="musteri_email" name="musteri_email" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Telefon *</label>
                                    <input type="tel" class="form-control" id="musteri_telefon" name="musteri_telefon" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Adres</label>
                                    <input type="text" class="form-control" id="musteri_adres" name="musteri_adres">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tarih Seçim Modu -->
                    <div class="card mb-3">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Rezervasyon Tarihleri</h5>
                        </div>
                        <div class="card-body">
                            <div class="date-mode-switch">
                                <label class="form-label fw-bold">Tarih Seçim Modu</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="date_mode" id="date_mode_common" value="common" checked>
                                    <label class="form-check-label" for="date_mode_common">
                                        <strong>Tüm odalar için ortak tarih</strong> - Aynı giriş/çıkış tarihi
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="date_mode" id="date_mode_separate" value="separate">
                                    <label class="form-check-label" for="date_mode_separate">
                                        <strong>Her oda için farklı tarih</strong> - Oda bazında tarih seçimi
                                    </label>
                                </div>
                            </div>
                            
                            <div id="commonDatesSection" class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giriş Tarihi *</label>
                                    <input type="date" class="form-control" id="giris_tarihi" name="giris_tarihi">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Çıkış Tarihi *</label>
                                    <input type="date" class="form-control" id="cikis_tarihi" name="cikis_tarihi">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Odalar -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-bed me-2"></i>Odalar</h5>
                            <button type="button" class="btn btn-light btn-sm" id="addRoomBtn" disabled>
                                <i class="fas fa-plus me-1"></i>Yeni Oda Ekle
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="roomsContainer">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-hotel fa-3x mb-3"></i>
                                    <p>Önce otel seçin, sonra oda ekleyin</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ödeme Bilgileri -->
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Ödeme Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ödeme Yöntemi</label>
                                    <select class="form-select" name="odeme_yontemi">
                                        <option value="nakit">Nakit</option>
                                        <option value="kredi_karti">Kredi Kartı</option>
                                        <option value="banka_transferi">Banka Transferi</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ödeme Miktarı</label>
                                    <input type="number" class="form-control" name="odeme_miktari" step="0.01" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="otomatik_checkin" id="otomatik_checkin">
                                        <label class="form-check-label" for="otomatik_checkin">
                                            Otomatik Check-in
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toplam Fiyat Kartı -->
                    <div class="price-card" id="totalPriceDisplay">
                        <i class="fas fa-calculator me-2"></i>
                        <strong>TOPLAM TUTAR:</strong> 
                        <span class="price-amount">0.00 ₺</span>
                    </div>
                    
                    <!-- Kaydet Butonu -->
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" name="submit_multi_reservation" class="btn btn-primary btn-lg" disabled>
                            <i class="fas fa-save me-2"></i>Rezervasyonları Kaydet
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <!-- Toast Bildirimleri -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <!-- Başarı Toast -->
        <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        
        <!-- Bilgi Toast -->
        <div id="infoToast" class="toast align-items-center text-bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="infoMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        
        <!-- Uyarı Toast -->
        <div id="warningToast" class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="warningMessage"></span>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        
        <!-- Hata Toast -->
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-times-circle me-2"></i>
                    <span id="errorMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Global variables
    let roomCounter = 0;
    let currentOtelId = null;
    let odaTipleri = [];
    let dateMode = 'common';
    
    // Toast Fonksiyonları
    function showToast(type, message) {
        const toastId = type + 'Toast';
        const messageId = type + 'Message';
        
        $('#' + messageId).text(message);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            animation: true,
            autohide: true,
            delay: 4000
        });
        toast.show();
    }
    
    function showSuccess(message) {
        showToast('success', message);
    }
    
    function showInfo(message) {
        showToast('info', message);
    }
    
    function showWarning(message) {
        showToast('warning', message);
    }
    
    function showError(message) {
        showToast('error', message);
    }
    
    // Otel seçimi değiştiğinde
    $('#otel_id').on('change', function() {
        currentOtelId = $(this).val();
        
        if (currentOtelId) {
            // Oda ekleme butonunu aktif et
            $('#addRoomBtn').prop('disabled', false);
            
            // Otel bilgilerini göster
            const selectedOption = $(this).find('option:selected');
            $('#otel-info').text(selectedOption.text());
            
            // Oda tiplerini yükle
            loadOtelRoomTypes(currentOtelId);
            
            showInfo('Otel seçildi: ' + selectedOption.text());
        } else {
            $('#addRoomBtn').prop('disabled', true);
            $('#otel-info').text('Önce otel seçin');
            $('#roomsContainer').html('<div class="text-center text-muted py-4"><i class="fas fa-hotel fa-3x mb-3"></i><p>Önce otel seçin, sonra oda ekleyin</p></div>');
        }
    });
    
    // Otel oda tiplerini yükle
    function loadOtelRoomTypes(otelId) {
        $.ajax({
            url: 'rezervasyon-ekle-multi.php?action=get_room_types&otel_id=' + otelId,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    odaTipleri = data.oda_tipleri;
                    console.log('Oda tipleri yüklendi:', odaTipleri);
                } else {
                    showError('Oda tipleri yüklenemedi');
                }
            },
            error: function() {
                showError('Oda tipleri yüklenirken hata oluştu');
            }
        });
    }
    
    // Oda ekleme butonu
    $(document).on('click', '#addRoomBtn', function(e) {
        e.preventDefault();
        
        if (!currentOtelId) {
            showWarning('Önce otel seçin!');
            return;
        }
        
        if (odaTipleri.length === 0) {
            showWarning('Bu otelde oda tipi bulunamadı!');
            return;
        }
        
        addRoom();
    });
    
    function addRoom() {
        const roomId = roomCounter++;
        const roomNumber = roomCounter;
        
        let dateFields = '';
        if (dateMode === 'separate') {
            dateFields = `
                <div class="row room-dates-section">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Giriş Tarihi *</label>
                        <input type="date" class="form-control room-giris" name="room_${roomId}_giris_tarihi" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Çıkış Tarihi *</label>
                        <input type="date" class="form-control room-cikis" name="room_${roomId}_cikis_tarihi" required>
                    </div>
                </div>
            `;
        }
        
        const roomHtml = `
            <div class="card room-card mb-3" data-room-id="${roomId}">
                <div class="card-header text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-bed me-2"></i>Oda ${roomNumber}
                    </h6>
                    <button type="button" class="btn btn-sm btn-danger remove-room-btn" data-room-id="${roomId}">
                        <i class="fas fa-times"></i> Kaldır
                    </button>
                </div>
                <div class="card-body">
                    ${dateFields}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Oda Tipi *</label>
                            <select class="form-select room-type-select" data-room-id="${roomId}" name="room_${roomId}_oda_tipi" required>
                                <option value="">Oda Tipi Seçin</option>
                                ${odaTipleri.map(ot => `<option value="${ot.id}">${ot.oda_tipi_adi}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Oda Numarası *</label>
                            <select class="form-select room-number-select" data-room-id="${roomId}" name="room_${roomId}_oda_numarasi" required>
                                <option value="">Önce oda tipi seçin</option>
                            </select>
                            <small class="loading-rooms text-muted" style="display:none;">
                                <i class="fas fa-spinner fa-spin"></i> Müsait odalar yükleniyor...
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Yetişkin Sayısı *</label>
                            <select class="form-select adult-count" data-room-id="${roomId}" name="room_${roomId}_yetiskin_sayisi" required>
                                ${[1,2,3,4,5,6].map(i => `<option value="${i}" ${i === 1 ? 'selected' : ''}>${i}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Çocuk Sayısı</label>
                            <select class="form-select child-count" data-room-id="${roomId}" name="room_${roomId}_cocuk_sayisi">
                                ${[0,1,2,3,4].map(i => `<option value="${i}">${i}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    
                    <!-- Fiyat Gösterimi -->
                    <div class="room-price-display" style="display:none;"></div>
                    
                    <div class="guest-details-${roomId}">
                        <!-- Misafir detayları buraya gelecek -->
                    </div>
                </div>
            </div>
        `;
        
        $('#roomsContainer').html(roomHtml);
        
        // İlk misafir detaylarını oluştur
        updateGuestFields(roomId);
        
        // Fiyatı hesapla (eğer tarihler varsa)
        updateRoomPrice(roomId);
        
        // Submit butonunu aktif et
        $('button[name="submit_multi_reservation"]').prop('disabled', false);
        
        showSuccess('Oda eklendi!');
    }
    
    // Oda kaldırma
    $(document).on('click', '.remove-room-btn', function() {
        const roomId = $(this).data('room-id');
        $(`.room-card[data-room-id="${roomId}"]`).fadeOut(300, function() {
            $(this).remove();
            updateTotalPrice();
        });
    });
    
    // Oda tipi değişimi
    $(document).on('change', '.room-type-select', function() {
        const roomId = $(this).data('room-id');
        const odaTipiId = $(this).val();
        loadAvailableRooms(roomId, odaTipiId);
        updateRoomPrice(roomId);
    });
    
    // Müsait odaları yükle
    function loadAvailableRooms(roomId, odaTipiId) {
        let giris, cikis;
        
        if (dateMode === 'common') {
            giris = $('#giris_tarihi').val();
            cikis = $('#cikis_tarihi').val();
        } else {
            giris = $(`.room-card[data-room-id="${roomId}"] .room-giris`).val();
            cikis = $(`.room-card[data-room-id="${roomId}"] .room-cikis`).val();
        }
        
        if (!giris || !cikis) {
            showWarning('Lütfen önce giriş ve çıkış tarihlerini seçin!');
            return;
        }
        
        const $select = $(`.room-card[data-room-id="${roomId}"] .room-number-select`);
        const $loading = $(`.room-card[data-room-id="${roomId}"] .loading-rooms`);
        
        $loading.show();
        $select.html('<option value="">Yükleniyor...</option>').prop('disabled', true);
        
        $.ajax({
            url: `rezervasyon-ekle-multi.php?action=get_available_rooms&otel_id=${currentOtelId}&oda_tipi_id=${odaTipiId}&giris_tarihi=${giris}&cikis_tarihi=${cikis}`,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                $select.prop('disabled', false);
                
                if (data.success && data.odalar && data.odalar.length > 0) {
                    let options = '<option value="">Oda Numarası Seçin</option>';
                    data.odalar.forEach(oda => {
                        options += `<option value="${oda.id}">${oda.oda_numarasi}</option>`;
                    });
                    $select.html(options);
                    showSuccess(`${data.odalar.length} müsait oda bulundu.`);
                } else {
                    $select.html('<option value="">Müsait oda yok</option>').prop('disabled', true);
                    showWarning('Seçilen tarihler için müsait oda bulunamadı.');
                }
            },
            error: function() {
                $select.html('<option value="">Hata oluştu</option>').prop('disabled', true);
                showError('Odalar yüklenirken bir hata oluştu.');
            },
            complete: function() {
                $loading.hide();
            }
        });
    }
    
    // Misafir alanlarını güncelle
    function updateGuestFields(roomId) {
        const yetiskinSayisi = parseInt($(`.room-card[data-room-id="${roomId}"] .adult-count`).val()) || 1;
        const cocukSayisi = parseInt($(`.room-card[data-room-id="${roomId}"] .child-count`).val()) || 0;
        
        let html = '';
        
        // Yetişkinler
        html += '<div class="row"><div class="col-12"><h6 class="mt-3 mb-2 text-primary"><i class="fas fa-user me-2"></i>Yetişkin Misafirler</h6></div></div>';
        for (let i = 0; i < yetiskinSayisi; i++) {
            html += `
                <div class="row mb-3">
                    <div class="col-12 mb-2"><strong>${i + 1}. Yetişkin</strong></div>
                    <div class="col-md-3 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="room_${roomId}_adult_ad_${i}" 
                               placeholder="Ad *" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="room_${roomId}_adult_soyad_${i}" 
                               placeholder="Soyad *" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select class="form-select form-select-sm" name="room_${roomId}_adult_cinsiyet_${i}">
                            <option value="">Cinsiyet</option>
                            <option value="erkek">Erkek</option>
                            <option value="kadin">Kadın</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" 
                               class="form-control form-control-sm" 
                               name="room_${roomId}_adult_tc_${i}" 
                               placeholder="TC Kimlik No (opsiyonel)" 
                               maxlength="11" 
                               pattern="[0-9]{11}">
                        <small class="text-muted">11 haneli TC kimlik</small>
                    </div>
                </div>
            `;
        }
        
        // Çocuklar
        if (cocukSayisi > 0) {
            html += '<div class="row"><div class="col-12"><h6 class="mt-3 mb-2 text-success"><i class="fas fa-child me-2"></i>Çocuk Misafirler</h6></div></div>';
            for (let i = 0; i < cocukSayisi; i++) {
                html += `
                    <div class="row mb-3">
                        <div class="col-12 mb-2"><strong>${i + 1}. Çocuk</strong></div>
                        <div class="col-md-3 mb-2">
                            <input type="text" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_ad_${i}" 
                                   placeholder="Ad *" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_soyad_${i}" 
                                   placeholder="Soyad *" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select class="form-select form-select-sm" name="room_${roomId}_child_cinsiyet_${i}">
                                <option value="">Cinsiyet</option>
                                <option value="erkek">Erkek</option>
                                <option value="kadin">Kız</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="number" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_yas_${i}" 
                                   placeholder="Yaş *" 
                                   min="0" max="17" 
                                   required>
                        </div>
                    </div>
                `;
            }
        }
        
        $(`.guest-details-${roomId}`).html(html);
    }
    
    // Misafir sayısı değişimi
    $(document).on('change', '.adult-count, .child-count', function() {
        const roomId = $(this).data('room-id');
        updateGuestFields(roomId);
        updateRoomPrice(roomId);
    });
    
    // Tarih değişimi
    $(document).on('change', '#giris_tarihi, #cikis_tarihi, .room-giris, .room-cikis', function() {
        $('.room-card').each(function() {
            const roomId = $(this).data('room-id');
            updateRoomPrice(roomId);
        });
    });
    
    // Fiyat hesaplama (basit versiyon)
    function updateRoomPrice(roomId) {
        // Bu fonksiyon multi-room-price-calculator.js'den gelecek
        console.log('Fiyat hesaplanıyor - Room ID:', roomId);
    }
    
    function updateTotalPrice() {
        // Toplam fiyat hesaplama
        console.log('Toplam fiyat güncelleniyor');
    }
    
    // TC Kimlik işlemleri
    $(document).on('input', '.tc-digit', function() {
        const $this = $(this);
        const val = $this.val();
        
        // Sadece rakam kontrolü
        if (val && !/^[0-9]$/.test(val)) {
            $this.val('');
            return;
        }
        
        // Filled class ekle
        if (val) {
            $this.addClass('filled');
            
            // Bir sonraki input'a geç
            const index = parseInt($this.data('index'));
            if (index < 10) {
                const nextId = '#tc_digit_' + (index + 1);
                $(nextId).focus();
            } else {
                // Son digit, müşteriyi sorgula
                searchCustomerByTC();
            }
        } else {
            $this.removeClass('filled');
        }
    });
    
    // Müşteri sorgulama
    function searchCustomerByTC() {
        let tc_kimlik = '';
        for (let i = 0; i < 11; i++) {
            tc_kimlik += $('#tc_digit_' + i).val();
        }
        
        if (tc_kimlik.length === 11) {
            showInfo('Müşteri bilgileri aranıyor...');
            
            $.ajax({
                url: 'rezervasyon-ekle-multi.php?action=get_customer&tc_kimlik=' + tc_kimlik,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Form alanlarını doldur
                        $('#musteri_ad').val(response.musteri.ad).addClass('is-valid');
                        $('#musteri_soyad').val(response.musteri.soyad).addClass('is-valid');
                        $('#musteri_soyad').val(response.musteri.soyad).addClass('is-valid');
                        $('#musteri_email').val(response.musteri.email).addClass('is-valid');
                        $('#musteri_telefon').val(response.musteri.telefon).addClass('is-valid');
                        $('#musteri_adres').val(response.musteri.adres).addClass('is-valid');
                        
                        showSuccess('Müşteri bulundu! Bilgiler otomatik dolduruldu.');
                    } else {
                        showWarning('Bu TC kimlik numarasına ait müşteri kaydı bulunamadı.');
                    }
                },
                error: function() {
                    showError('Müşteri sorgulanırken bir hata oluştu.');
                }
            });
        }
    }
    
    // Tarih modu değişimi
    $(document).on('change', 'input[name="date_mode"]', function() {
        dateMode = $(this).val();
        
        if (dateMode === 'common') {
            $('#commonDatesSection').show();
            $('.room-dates-section').hide();
            $('#giris_tarihi, #cikis_tarihi').prop('required', true);
        } else {
            $('#commonDatesSection').hide();
            $('#giris_tarihi, #cikis_tarihi').prop('required', false);
            
            // Mevcut her oda kartına tarih formları ekle
            $('.room-card').each(function() {
                const roomId = $(this).data('room-id');
                const $card = $(this);
                const $existingDateSection = $card.find('.room-dates-section');
                
                if ($existingDateSection.length === 0) {
                    const dateFields = `
                        <div class="row room-dates-section">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giriş Tarihi *</label>
                                <input type="date" class="form-control room-giris" name="room_${roomId}_giris_tarihi" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Çıkış Tarihi *</label>
                                <input type="date" class="form-control room-cikis" name="room_${roomId}_cikis_tarihi" required>
                            </div>
                        </div>
                    `;
                    $card.find('.card-body').prepend(dateFields);
                } else {
                    $existingDateSection.show().find('input[type="date"]').prop('required', true);
                }
            });
        }
    });
    
    // Sayfa yüklendiğinde
    $(document).ready(function() {
        console.log('Multi Otel Rezervasyon sayfası yüklendi');
        
        // Minimum tarihleri ayarla
        const today = new Date().toISOString().split('T')[0];
        $('#giris_tarihi').attr('min', today);
        $('#cikis_tarihi').attr('min', today);
    });
    </script>
</body>
</html>
