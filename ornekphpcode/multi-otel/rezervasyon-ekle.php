<?php
/**
 * Multi Otel - Tek Oda Rezervasyon Ekleme
 * Otel seçimi ile tek oda rezervasyonu
 */

require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/price-functions.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
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
            
            // Rezervasyon bilgileri
            $giris_tarihi = $_POST['giris_tarihi'] ?? '';
            $cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
            $oda_tipi_id = intval($_POST['oda_tipi_id'] ?? 0);
            $oda_numarasi_id = intval($_POST['oda_numarasi_id'] ?? 0);
            $yetiskin_sayisi = intval($_POST['yetiskin_sayisi'] ?? 1);
            $cocuk_sayisi = intval($_POST['cocuk_sayisi'] ?? 0);
            
            // Yetişkin detayları
            $yetiskin_detaylari = [];
            for ($i = 0; $i < $yetiskin_sayisi; $i++) {
                $yetiskin_detaylari[] = [
                    'ad' => turkishUppercase(trim($_POST["adult_ad_$i"] ?? '')),
                    'soyad' => turkishUppercase(trim($_POST["adult_soyad_$i"] ?? '')),
                    'tc_kimlik' => preg_replace('/[^0-9]/', '', trim($_POST["adult_tc_$i"] ?? '')),
                    'cinsiyet' => $_POST["adult_cinsiyet_$i"] ?? ''
                ];
            }
            
            // Çocuk detayları
            $cocuk_detaylari = [];
            for ($i = 0; $i < $cocuk_sayisi; $i++) {
                $cocuk_detaylari[] = [
                    'ad' => turkishUppercase(trim($_POST["child_ad_$i"] ?? '')),
                    'soyad' => turkishUppercase(trim($_POST["child_soyad_$i"] ?? '')),
                    'yas' => intval($_POST["child_yas_$i"] ?? 0),
                    'cinsiyet' => $_POST["child_cinsiyet_$i"] ?? ''
                ];
            }
            
            // Çocuk yaşlarını array'e çevir
            $cocuk_yaslari = [];
            if ($cocuk_sayisi > 0) {
                for ($i = 0; $i < $cocuk_sayisi; $i++) {
                    $yas = intval($_POST["child_yas_$i"] ?? 0);
                    if ($yas > 0) {
                        $cocuk_yaslari[] = $yas;
                    }
                }
            }
            
            // Fiyat hesaplama
            $fiyatSonucu = calculateOtelPrice($otel_id, $oda_tipi_id, $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi, $cocuk_yaslari);
            
            if ($fiyatSonucu['success'] ?? false) {
                $toplam_tutar = $fiyatSonucu['toplam_fiyat'] ?? 0;
            } else {
                // Fallback fiyat hesaplama
                $oda_tipi = fetchOne("SELECT base_price FROM oda_tipleri WHERE id = ? AND otel_id = ?", [$oda_tipi_id, $otel_id]);
                $gun_sayisi = (strtotime($cikis_tarihi) - strtotime($giris_tarihi)) / 86400;
                $toplam_tutar = ($oda_tipi['base_price'] ?? 0) * max(1, $gun_sayisi);
            }
            
            // Müşteriyi ekle veya güncelle
            $musteri = fetchOne("SELECT id FROM musteriler WHERE email = ?", [$customerData['email']]);
            
            if ($musteri) {
                $musteri_id = $musteri['id'];
                // Müşteri bilgilerini güncelle
                executeQuery("UPDATE musteriler SET ad = ?, soyad = ?, telefon = ?, tc_kimlik = ? WHERE id = ?", 
                    [$customerData['ad'], $customerData['soyad'], $customerData['telefon'], $customerData['tc_kimlik'], $musteri_id]);
            } else {
                // Yeni müşteri ekle
                executeQuery("INSERT INTO musteriler (ad, soyad, email, telefon, tc_kimlik, ilk_otel_id) VALUES (?, ?, ?, ?, ?, ?)", 
                    [$customerData['ad'], $customerData['soyad'], $customerData['email'], $customerData['telefon'], $customerData['tc_kimlik'], $otel_id]);
                $musteri_id = $pdo->lastInsertId();
            }
            
            // Rezervasyon kodu oluştur
            $rezervasyon_kodu = 'RZ' . $otel_id . time() . rand(100, 999);
            
            // Rezervasyon oluştur
            $sql = "INSERT INTO rezervasyonlar (
                otel_id, musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi,
                yetiskin_sayisi, cocuk_sayisi, cocuk_yaslari, yetiskin_detaylari, cocuk_detaylari,
                toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu,
                satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email, 
                musteri_telefon, musteri_kimlik
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $otel_id, $musteri_id, $oda_tipi_id, $oda_numarasi_id,
                $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi,
                $cocuk_sayisi, json_encode($cocuk_yaslari),
                json_encode($yetiskin_detaylari), json_encode($cocuk_detaylari),
                $toplam_tutar, $toplam_tutar, 0, $toplam_tutar, 'onaylandi',
                'odeme_bekliyor', $_SESSION['user_id'], $rezervasyon_kodu, $customerData['ad'], 
                $customerData['soyad'], $customerData['email'], $customerData['telefon'], 
                $customerData['tc_kimlik']
            ];
            
            if (executeQuery($sql, $params)) {
                $success_message = 'Rezervasyon başarıyla oluşturuldu. Rezervasyon Kodu: ' . $rezervasyon_kodu;
            } else {
                $error_message = 'Rezervasyon oluşturulurken hata oluştu.';
            }
            
        } catch (Exception $e) {
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
    <title>Tek Oda Rezervasyon - Multi Otel Yönetimi</title>
    
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
                        <i class="fas fa-plus me-2"></i>Tek Oda Rezervasyon
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
                
                <form method="post" id="reservationForm">
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
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Müşteri Bilgileri</h5>
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
                    
                    <!-- Rezervasyon Bilgileri -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Rezervasyon Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giriş Tarihi *</label>
                                    <input type="date" class="form-control" id="giris_tarihi" name="giris_tarihi" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Çıkış Tarihi *</label>
                                    <input type="date" class="form-control" id="cikis_tarihi" name="cikis_tarihi" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required>
                                        <option value="">Önce otel seçin</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Oda Numarası *</label>
                                    <select class="form-select" id="oda_numarasi_id" name="oda_numarasi_id" required>
                                        <option value="">Önce oda tipi seçin</option>
                                    </select>
                                    <small class="loading-rooms text-muted" style="display:none;">
                                        <i class="fas fa-spinner fa-spin"></i> Müsait odalar yükleniyor...
                                    </small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Yetişkin Sayısı *</label>
                                    <select class="form-select" id="yetiskin_sayisi" name="yetiskin_sayisi" required>
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == 1 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Çocuk Sayısı</label>
                                    <select class="form-select" id="cocuk_sayisi" name="cocuk_sayisi">
                                        <?php for ($i = 0; $i <= 4; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Misafir Detayları -->
                            <div id="guest-details">
                                <!-- Misafir detayları buraya gelecek -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kaydet Butonu -->
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" name="submit_reservation" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Rezervasyonu Kaydet
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Global variables
    let currentOtelId = null;
    let odaTipleri = [];
    
    // Otel seçimi değiştiğinde
    $('#otel_id').on('change', function() {
        currentOtelId = $(this).val();
        
        if (currentOtelId) {
            // Otel bilgilerini göster
            const selectedOption = $(this).find('option:selected');
            $('#otel-info').text(selectedOption.text());
            
            // Oda tiplerini yükle
            loadOtelRoomTypes(currentOtelId);
        } else {
            $('#otel-info').text('Önce otel seçin');
            $('#oda_tipi_id').html('<option value="">Önce otel seçin</option>');
            $('#oda_numarasi_id').html('<option value="">Önce oda tipi seçin</option>');
        }
    });
    
    // Otel oda tiplerini yükle
    function loadOtelRoomTypes(otelId) {
        $.ajax({
            url: 'rezervasyon-ekle.php?action=get_room_types&otel_id=' + otelId,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    odaTipleri = data.oda_tipleri;
                    let options = '<option value="">Oda Tipi Seçin</option>';
                    data.oda_tipleri.forEach(ot => {
                        options += `<option value="${ot.id}">${ot.oda_tipi_adi}</option>`;
                    });
                    $('#oda_tipi_id').html(options);
                } else {
                    $('#oda_tipi_id').html('<option value="">Oda tipi bulunamadı</option>');
                }
            },
            error: function() {
                $('#oda_tipi_id').html('<option value="">Hata oluştu</option>');
            }
        });
    }
    
    // Oda tipi değişimi
    $('#oda_tipi_id').on('change', function() {
        const odaTipiId = $(this).val();
        const giris = $('#giris_tarihi').val();
        const cikis = $('#cikis_tarihi').val();
        
        if (odaTipiId && giris && cikis) {
            loadAvailableRooms(odaTipiId, giris, cikis);
        }
    });
    
    // Tarih değişimi
    $('#giris_tarihi, #cikis_tarihi').on('change', function() {
        const odaTipiId = $('#oda_tipi_id').val();
        const giris = $('#giris_tarihi').val();
        const cikis = $('#cikis_tarihi').val();
        
        if (odaTipiId && giris && cikis) {
            loadAvailableRooms(odaTipiId, giris, cikis);
        }
    });
    
    // Müsait odaları yükle
    function loadAvailableRooms(odaTipiId, giris, cikis) {
        const $select = $('#oda_numarasi_id');
        const $loading = $('.loading-rooms');
        
        $loading.show();
        $select.html('<option value="">Yükleniyor...</option>').prop('disabled', true);
        
        $.ajax({
            url: `rezervasyon-ekle.php?action=get_available_rooms&otel_id=${currentOtelId}&oda_tipi_id=${odaTipiId}&giris_tarihi=${giris}&cikis_tarihi=${cikis}`,
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
                } else {
                    $select.html('<option value="">Müsait oda yok</option>').prop('disabled', true);
                }
            },
            error: function() {
                $select.html('<option value="">Hata oluştu</option>').prop('disabled', true);
            },
            complete: function() {
                $loading.hide();
            }
        });
    }
    
    // Misafir sayısı değişimi
    $('#yetiskin_sayisi, #cocuk_sayisi').on('change', function() {
        updateGuestFields();
    });
    
    // Misafir alanlarını güncelle
    function updateGuestFields() {
        const yetiskinSayisi = parseInt($('#yetiskin_sayisi').val()) || 1;
        const cocukSayisi = parseInt($('#cocuk_sayisi').val()) || 0;
        
        let html = '';
        
        // Yetişkinler
        html += '<div class="row"><div class="col-12"><h6 class="mt-3 mb-2 text-primary"><i class="fas fa-user me-2"></i>Yetişkin Misafirler</h6></div></div>';
        for (let i = 0; i < yetiskinSayisi; i++) {
            html += `
                <div class="row mb-3">
                    <div class="col-12 mb-2"><strong>${i + 1}. Yetişkin</strong></div>
                    <div class="col-md-3 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="adult_ad_${i}" 
                               placeholder="Ad *" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="adult_soyad_${i}" 
                               placeholder="Soyad *" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select class="form-select form-select-sm" name="adult_cinsiyet_${i}">
                            <option value="">Cinsiyet</option>
                            <option value="erkek">Erkek</option>
                            <option value="kadin">Kadın</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" 
                               class="form-control form-control-sm" 
                               name="adult_tc_${i}" 
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
                                   name="child_ad_${i}" 
                                   placeholder="Ad *" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" class="form-control form-control-sm" 
                                   name="child_soyad_${i}" 
                                   placeholder="Soyad *" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select class="form-select form-select-sm" name="child_cinsiyet_${i}">
                                <option value="">Cinsiyet</option>
                                <option value="erkek">Erkek</option>
                                <option value="kadin">Kız</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="number" class="form-control form-control-sm" 
                                   name="child_yas_${i}" 
                                   placeholder="Yaş *" 
                                   min="0" max="17" 
                                   required>
                        </div>
                    </div>
                `;
            }
        }
        
        $('#guest-details').html(html);
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
            $.ajax({
                url: 'rezervasyon-ekle.php?action=get_customer&tc_kimlik=' + tc_kimlik,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Form alanlarını doldur
                        $('#musteri_ad').val(response.musteri.ad).addClass('is-valid');
                        $('#musteri_soyad').val(response.musteri.soyad).addClass('is-valid');
                        $('#musteri_email').val(response.musteri.email).addClass('is-valid');
                        $('#musteri_telefon').val(response.musteri.telefon).addClass('is-valid');
                        $('#musteri_adres').val(response.musteri.adres).addClass('is-valid');
                    }
                },
                error: function() {
                    console.log('Müşteri sorgulanırken hata oluştu.');
                }
            });
        }
    }
    
    // Sayfa yüklendiğinde
    $(document).ready(function() {
        // Minimum tarihleri ayarla
        const today = new Date().toISOString().split('T')[0];
        $('#giris_tarihi').attr('min', today);
        $('#cikis_tarihi').attr('min', today);
        
        // İlk misafir detaylarını oluştur
        updateGuestFields();
    });
    </script>
</body>
</html>
