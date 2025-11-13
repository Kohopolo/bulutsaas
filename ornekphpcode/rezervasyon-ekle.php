<?php
/**
 * Rezervasyon Ekleme Sayfası - Adım Adım
 * Otel Rezervasyon Sistemi - Admin Panel
 */

// Güvenlik ve gerekli dosyaları dahil et
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/price-functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once __DIR__ . '/../includes/detailed_permission_functions.php';
requireDetailedPermission('rezervasyon_ekle', 'Rezervasyon ekleme yetkiniz bulunmamaktadır.');

// CSRF token'ı başlat
initCSRFProtection();

// URL parametrelerini kontrol et (doluluk oranı sayfasından gelen oda seçimi)
$selected_room_id = $_GET['selected_room'] ?? null;
$selected_date = $_GET['selected_date'] ?? null;

// Seçilen oda bilgilerini al
$selected_room_info = null;
if ($selected_room_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT onum.id, onum.oda_numarasi, ot.id as oda_tipi_id, ot.oda_tipi_adi
            FROM oda_numaralari onum
            LEFT JOIN oda_tipleri ot ON onum.oda_tipi_id = ot.id
            WHERE onum.id = ?
        ");
        $stmt->execute([$selected_room_id]);
        $selected_room_info = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Seçilen oda bilgisi alınırken hata: " . $e->getMessage());
    }
}

// Türkçe büyük harf fonksiyonu
function turkishUppercase($text) {
    $turkishChars = [
        'ç' => 'Ç', 'ğ' => 'Ğ', 'ı' => 'I', 'ö' => 'Ö', 'ş' => 'Ş', 'ü' => 'Ü',
        'i' => 'İ'
    ];
    
    $text = strtr($text, $turkishChars);
    return strtoupper($text);
}

// Form gönderildiğinde işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        try {
            // Form verilerini al ve temizle
            $musteri_ad = turkishUppercase(trim($_POST['musteri_ad'] ?? ''));
            $musteri_soyad = turkishUppercase(trim($_POST['musteri_soyad'] ?? ''));
            $musteri_email = trim($_POST['musteri_email'] ?? '');
            $musteri_telefon = preg_replace('/[^0-9]/', '', trim($_POST['musteri_telefon'] ?? '')); // Sadece rakamları al
            $musteri_tc_kimlik = preg_replace('/[^0-9]/', '', trim($_POST['musteri_tc_kimlik'] ?? '')); // Sadece rakamları al
            
            $oda_tipi_id = intval($_POST['oda_tipi_id'] ?? 0);
            $oda_numarasi_id = intval($_POST['oda_numarasi_id'] ?? 0);
            $giris_tarihi = $_POST['giris_tarihi'] ?? '';
            $cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
            $yetiskin_sayisi = intval($_POST['yetiskin_sayisi'] ?? 1);
            $cocuk_sayisi = intval($_POST['cocuk_sayisi'] ?? 0);
            $cocuk_yaslari = $_POST['cocuk_yaslari'] ?? [];
            
            $toplam_tutar = floatval($_POST['toplam_tutar'] ?? 0);
            $odenen_tutar = floatval($_POST['odenen_tutar'] ?? 0);
            
            // Yetişkin detayları
            $yetiskin_detaylari = [];
            for ($i = 0; $i < $yetiskin_sayisi; $i++) {
                $yetiskin_detaylari[] = [
                    'ad' => turkishUppercase(trim($_POST["yetiskin_ad_$i"] ?? '')),
                    'soyad' => turkishUppercase(trim($_POST["yetiskin_soyad_$i"] ?? '')),
                    'cinsiyet' => $_POST["yetiskin_cinsiyet_$i"] ?? '',
                    'tc_kimlik' => trim($_POST["yetiskin_tc_$i"] ?? '')
                ];
            }
            
            // Çocuk detayları
            $cocuk_detaylari = [];
            for ($i = 0; $i < $cocuk_sayisi; $i++) {
                $cocuk_detaylari[] = [
                    'ad' => turkishUppercase(trim($_POST["cocuk_ad_$i"] ?? '')),
                    'soyad' => turkishUppercase(trim($_POST["cocuk_soyad_$i"] ?? '')),
                    'cinsiyet' => $_POST["cocuk_cinsiyet_$i"] ?? '',
                    'yas' => intval($_POST["cocuk_yas_$i"] ?? 0)
                ];
            }
            
            // Validasyon
            $errors = [];
            
            if (empty($musteri_ad)) $errors[] = 'Müşteri adı gerekli';
            if (empty($musteri_soyad)) $errors[] = 'Müşteri soyadı gerekli';
            if (empty($musteri_email) || !filter_var($musteri_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçerli bir email adresi gerekli';
            }
            if (empty($musteri_telefon)) $errors[] = 'Telefon numarası gerekli';
            if (empty($giris_tarihi) || empty($cikis_tarihi)) $errors[] = 'Giriş ve çıkış tarihleri gerekli';
            if (strtotime($cikis_tarihi) <= strtotime($giris_tarihi)) {
                $errors[] = 'Çıkış tarihi giriş tarihinden sonra olmalı';
            }
            if ($oda_tipi_id <= 0) $errors[] = 'Oda tipi seçimi gerekli';
            if ($yetiskin_sayisi <= 0) $errors[] = 'En az 1 yetişkin gerekli';
            
            if (empty($errors)) {
                // Müşteriyi ekle veya güncelle
                $stmt = $pdo->prepare("SELECT id FROM musteriler WHERE email = ?");
                $stmt->execute([$musteri_email]);
                $musteri = $stmt->fetch();
                
                if ($musteri) {
                    $musteri_id = $musteri['id'];
                    $stmt = $pdo->prepare("UPDATE musteriler SET ad = ?, soyad = ?, telefon = ?, tc_kimlik = ? WHERE id = ?");
                    $stmt->execute([$musteri_ad, $musteri_soyad, $musteri_telefon, $musteri_tc_kimlik, $musteri_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO musteriler (ad, soyad, email, telefon, tc_kimlik) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$musteri_ad, $musteri_soyad, $musteri_email, $musteri_telefon, $musteri_tc_kimlik]);
                    $musteri_id = $pdo->lastInsertId();
                }
                
                // Oda tipinin check-in ve check-out saatlerini al
                $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$oda_tipi_id]);
                $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
                $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
                
                // Tarih formatını düzenle - Veritabanından alınan saatleri kullan
                if (strlen($giris_tarihi) == 10) { // Y-m-d formatı
                    $giris_tarihi = $giris_tarihi . ' ' . $checkin_saati; // Veritabanından alınan check-in saati
                }
                if (strlen($cikis_tarihi) == 10) { // Y-m-d formatı
                    $cikis_tarihi = $cikis_tarihi . ' ' . $checkout_saati; // Veritabanından alınan check-out saati
                }
                
                // Rezervasyonu ekle
                $kalan_tutar = $toplam_tutar - $odenen_tutar;
                $odeme_durumu = ($kalan_tutar <= 0) ? 'tamamen_odendi' : (($odenen_tutar > 0) ? 'kismen_odendi' : 'odenmedi');
                
                // Giriş yapan kullanıcıyı satış elemanı olarak ata
                $satis_elemani_id = $_SESSION['user_id'];
                
                // Rezervasyon kodu oluştur
                $rezervasyon_kodu = 'RZ' . time() . rand(100, 999);
                
                $stmt = $pdo->prepare("INSERT INTO rezervasyonlar (musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi, yetiskin_sayisi, cocuk_sayisi, cocuk_yaslari, yetiskin_detaylari, cocuk_detaylari, toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu, satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email, musteri_telefon, musteri_kimlik) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'onaylandi', ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $cocuk_yaslari_json = json_encode(array_map('intval', $cocuk_yaslari));
                $yetiskin_detaylari_json = json_encode($yetiskin_detaylari);
                $cocuk_detaylari_json = json_encode($cocuk_detaylari);
                
                $stmt->execute([$musteri_id, $oda_tipi_id, ($oda_numarasi_id > 0 ? $oda_numarasi_id : null), $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi, $cocuk_sayisi, $cocuk_yaslari_json, $yetiskin_detaylari_json, $cocuk_detaylari_json, $toplam_tutar, $toplam_tutar, $odenen_tutar, $kalan_tutar, $odeme_durumu, $satis_elemani_id, $rezervasyon_kodu, $musteri_ad, $musteri_soyad, $musteri_email, $musteri_telefon, $musteri_tc_kimlik]);
                
                $rezervasyon_id = $pdo->lastInsertId();
                
                // PDF'leri oluştur ve arşivle
                try {
                    require_once __DIR__ . '/../includes/pdf-generator.php';
                    
                    error_log("Yeni rezervasyon için PDF oluşturma başlıyor - Rezervasyon ID: " . $rezervasyon_id);
                    
                    $pdfGenerator = new PDFGenerator($pdo);
                    
                    // Voucher PDF'i oluştur
                    $voucher_result = $pdfGenerator->generateReservationVoucher($rezervasyon_id, $_SESSION['user_id']);
                    error_log("Voucher sonucu: " . json_encode($voucher_result));
                    
                    // Contract PDF'i oluştur
                    $contract_result = $pdfGenerator->generateContract($rezervasyon_id, $_SESSION['user_id']);
                    error_log("Contract sonucu: " . json_encode($contract_result));
                    
                    // PDF oluşturma sonuçlarını kontrol et
                    if ($voucher_result['success'] && $contract_result['success']) {
                        if ($voucher_result['archived'] && $contract_result['archived']) {
                            $success = 'Rezervasyon başarıyla eklendi ve PDF arşivi oluşturuldu!';
                        } else {
                            $success = 'Rezervasyon başarıyla eklendi ancak PDF arşivleme sırasında sorun oluştu.';
                        }
                    } else {
                        $success = 'Rezervasyon başarıyla eklendi ancak PDF oluşturma başarısız oldu.';
                        if (!$voucher_result['success']) {
                            error_log("Voucher PDF hatası: " . ($voucher_result['error'] ?? 'Bilinmeyen hata'));
                        }
                        if (!$contract_result['success']) {
                            error_log("Contract PDF hatası: " . ($contract_result['error'] ?? 'Bilinmeyen hata'));
                        }
                    }
                } catch (Exception $pdf_error) {
                    error_log("PDF oluşturma hatası: " . $pdf_error->getMessage());
                    $success = 'Rezervasyon başarıyla eklendi ancak PDF arşivi oluşturulamadı: ' . $pdf_error->getMessage();
                }
                
                // Başarılı rezervasyon sonrası rezervasyonlar listesine yönlendir
                header('Location: rezervasyonlar.php?success=' . urlencode($success));
                exit;
            }
            
        } catch (Exception $e) {
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Oda tiplerini getir
try {
    $stmt = $pdo->prepare("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
    $stmt->execute();
    $oda_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $oda_tipleri = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyon Ekle - Otel Yönetim Sistemi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .step-container {
            display: none;
        }
        .step-container.active {
            display: block;
        }
        .tc-kimlik-container {
            max-width: 100%;
        }
        .tc-digit {
            width: 35px !important;
            height: 45px !important;
            text-align: center !important;
            font-size: 18px !important;
            font-weight: bold !important;
            border: 2px solid #ddd !important;
            border-radius: 8px !important;
            color: #000 !important;
            background-color: #fff !important;
            -webkit-text-fill-color: #000 !important;
            -webkit-opacity: 1 !important;
            opacity: 1 !important;
            display: inline-block !important;
            margin: 0 2px !important;
            padding: 0 !important;
            line-height: 1 !important;
            vertical-align: top !important;
        }
        .tc-digit:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .tc-digit.filled {
            background-color: #e8f5e8 !important;
            border-color: #28a745 !important;
            color: #000 !important;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step.active .step-number {
            background: #007bff;
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            margin: 0 10px;
        }
        .step.completed + .step-line {
            background: #28a745;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .price-display {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .price-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #1976d2;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .loading {
            display: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
                <!-- Header -->
                <?php include 'includes/header.php'; ?>
                
                <!-- Page Content -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-plus-circle me-2"></i>Yeni Rezervasyon Ekle</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rezervasyonlar.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Geri Dön
                        </a>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Çoklu Oda Bildirim -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Birden Fazla Oda Rezervasyonu</h5>
                    <p class="mb-2">Aynı müşteri için birden fazla oda rezervasyonu oluşturmak mı istiyorsunuz?</p>
                    <hr>
                    <p class="mb-0">
                        <a href="rezervasyon-ekle-multi.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-hotel me-1"></i>Çoklu Oda Rezervasyon Sayfasına Git
                        </a>
                        <small class="text-muted ms-2">Her oda için ayrı oda numarası ve misafir bilgileri girebilirsiniz.</small>
                    </p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step-indicator-1">
                        <div class="step-number">1</div>
                        <span>Tarih & Kişi</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" id="step-indicator-2">
                        <div class="step-number">2</div>
                        <span>Oda Seçimi</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" id="step-indicator-3">
                        <div class="step-number">3</div>
                        <span>Rezervasyon Özeti</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" id="step-indicator-4">
                        <div class="step-number">4</div>
                        <span>Müşteri Bilgileri</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" id="step-indicator-5">
                        <div class="step-number">5</div>
                        <span>Kişi Detayları</span>
                    </div>
                </div>
                
                <!-- Reservation Form -->
                <form method="POST" id="reservationForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" id="toplam_tutar" name="toplam_tutar" value="0">
                    
                    <!-- Step 1: Date & Guest Selection -->
                    <div class="step-container active" id="step-1">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Adım 1: Tarih ve Kişi Sayısı</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="giris_tarihi" class="form-label">Giriş Tarihi *</label>
                                        <input type="date" class="form-control" id="giris_tarihi" name="giris_tarihi" required>
                                        <div class="invalid-feedback">Giriş tarihi gerekli</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cikis_tarihi" class="form-label">Çıkış Tarihi *</label>
                                        <input type="date" class="form-control" id="cikis_tarihi" name="cikis_tarihi" required>
                                        <div class="invalid-feedback">Çıkış tarihi gerekli</div>
                                    </div>
                                </div>
                                
                                <div id="guest-selection" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="yetiskin_sayisi" class="form-label">Yetişkin Sayısı *</label>
                                            <select class="form-select" id="yetiskin_sayisi" name="yetiskin_sayisi" required>
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                    <option value="<?= $i ?>"><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <div class="invalid-feedback">Yetişkin sayısı gerekli</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cocuk_sayisi" class="form-label">Çocuk Sayısı</label>
                                            <select class="form-select" id="cocuk_sayisi" name="cocuk_sayisi">
                                                <?php for ($i = 0; $i <= 10; $i++): ?>
                                                    <option value="<?= $i ?>"><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Child Ages -->
                                    <div id="child_ages_container" style="display: none;">
                                        <label class="form-label">Çocuk Yaşları</label>
                                        <div id="child_ages_inputs" class="row"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="button" class="btn btn-primary" id="next-to-step-2" disabled>
                                            Devam Et <i class="fas fa-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Room Selection -->
                    <div class="step-container" id="step-2">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bed me-2"></i>Adım 2: Oda Seçimi</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="oda_tipi_id" class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required>
                                        <option value="">Önce tarih ve kişi sayısını seçin</option>
                                    </select>
                                    <div class="invalid-feedback">Oda tipi seçimi gerekli</div>
                                </div>
                                
                                <div id="room-number-selection" style="display: none;">
                                    <div class="mb-3">
                                        <label for="oda_numarasi_id" class="form-label">Oda Numarası (Opsiyonel)</label>
                                        <select class="form-select" id="oda_numarasi_id" name="oda_numarasi_id">
                                            <option value="">Otomatik atama</option>
                                        </select>
                                        <small class="form-text text-muted">Boş bırakırsanız otomatik olarak uygun oda atanacaktır.</small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-outline-secondary" id="back-to-step-1">
                                        <i class="fas fa-arrow-left me-1"></i> Geri
                                    </button>
                                    <button type="button" class="btn btn-primary" id="next-to-step-3" disabled>
                                        Devam Et <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Reservation Summary -->
                    <div class="step-container" id="step-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Adım 3: Rezervasyon Özeti</h5>
                            </div>
                            <div class="card-body">
                                <div id="reservation-summary">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6>Rezervasyon Detayları</h6>
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Giriş Tarihi:</strong></td>
                                                    <td id="summary-checkin">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Çıkış Tarihi:</strong></td>
                                                    <td id="summary-checkout">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Gece Sayısı:</strong></td>
                                                    <td id="summary-nights">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Yetişkin:</strong></td>
                                                    <td id="summary-adults">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Çocuk:</strong></td>
                                                    <td id="summary-children">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Oda Tipi:</strong></td>
                                                    <td id="summary-room-type">-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Oda Numarası:</strong></td>
                                                    <td id="summary-room-number">Otomatik atama</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-4">
                                            <div id="price_loading" class="loading text-center py-3">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Hesaplanıyor...</span>
                                                </div>
                                                <div class="mt-2">Fiyat hesaplanıyor...</div>
                                            </div>
                                            
                                            <div id="price_summary" style="display: none;">
                                                <div class="price-display mb-3">
                                                    <div class="price-amount" id="total_price">0 ₺</div>
                                                    <small class="text-muted" id="price_details">Toplam tutar</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="odenen_tutar" class="form-label">Ödenen Tutar</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="odenen_tutar" name="odenen_tutar" 
                                                               min="0" step="0.01" value="0">
                                                        <span class="input-group-text">₺</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kalan Tutar</label>
                                                    <div class="form-control-plaintext fw-bold" id="remaining_amount">0 ₺</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-outline-secondary" id="back-to-step-2">
                                        <i class="fas fa-arrow-left me-1"></i> Geri
                                    </button>
                                    <button type="button" class="btn btn-primary" id="next-to-step-4" disabled>
                                        Devam Et <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Customer Information -->
                    <div class="step-container" id="step-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Adım 4: Müşteri Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <!-- Müşteri Bilgileri - Dinamik Alanlar -->
                                <div id="customer-info-fields" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="musteri_ad" class="form-label">Ad *</label>
                                            <input type="text" class="form-control" id="musteri_ad" name="musteri_ad" required>
                                            <div class="invalid-feedback">Ad gerekli</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="musteri_soyad" class="form-label">Soyad *</label>
                                            <input type="text" class="form-control" id="musteri_soyad" name="musteri_soyad" required>
                                            <div class="invalid-feedback">Soyad gerekli</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="musteri_email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="musteri_email" name="musteri_email" required>
                                            <div class="invalid-feedback">Geçerli email gerekli</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="musteri_telefon" class="form-label">Telefon *</label>
                                            <input type="tel" class="form-control" id="musteri_telefon" name="musteri_telefon" 
                                                   placeholder="0 530 000 00 00" maxlength="13" required>
                                            <div class="invalid-feedback">Telefon gerekli</div>
                                        </div>
                                    </div>
                                </div>
                                <!-- TC Kimlik No - Öncelikli Alan -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <label for="musteri_tc_kimlik" class="form-label">TC Kimlik Numarası</label>
                                        <div class="tc-kimlik-container d-flex gap-1">
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="0" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="1" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="2" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="3" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="4" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="5" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="6" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="7" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="8" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="9" required>
                                            <input type="text" class="form-control tc-digit" maxlength="1" data-index="10" required>
                                        </div>
                                        <input type="hidden" id="musteri_tc_kimlik" name="musteri_tc_kimlik">
                                        <div class="invalid-feedback">TC kimlik numarası 11 haneli olmalıdır</div>
                                        <div class="valid-feedback" id="tc-success-message" style="display: none;">
                                            <i class="fas fa-check-circle me-1"></i>Müşteri bilgileri bulundu ve dolduruldu!
                                        </div>
                                        <small class="form-text text-muted">11 haneli TC kimlik numarası girildiğinde müşteri bilgileri otomatik doldurulacaktır</small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-outline-secondary" id="back-to-step-3">
                                        <i class="fas fa-arrow-left me-1"></i> Geri
                                    </button>
                                    <button type="button" class="btn btn-primary" id="next-to-step-5">
                                        Devam Et <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 5: Guest Details -->
                    <div class="step-container" id="step-5">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Adım 5: Kişi Detayları</h5>
                            </div>
                            <div class="card-body">
                                <!-- Adult Details -->
                                <div id="adult-details">
                                    <h6 class="mb-3">Yetişkin Detayları</h6>
                                    <div id="adult-forms"></div>
                                </div>
                                
                                <!-- Child Details -->
                                <div id="child-details" style="display: none;">
                                    <h6 class="mb-3 mt-4">Çocuk Detayları</h6>
                                    <div id="child-forms"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" id="back-to-step-4">
                                        <i class="fas fa-arrow-left me-1"></i> Geri
                                    </button>
                                    <button type="submit" name="submit_reservation" class="btn btn-success" onclick="console.log('Kaydet butonu tıklandı'); return true;">
                                        <i class="fas fa-save me-2"></i>Rezervasyonu Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentStep = 1;
        let reservationData = {};
        
        // Form validation
        // Form validation kaldırıldı - doğrudan submit yapılsın
        
        // Step navigation functions
        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-container').forEach(container => {
                container.classList.remove('active');
            });
            
            // Show current step
            document.getElementById(`step-${step}`).classList.add('active');
            
            // Update step indicators
            document.querySelectorAll('.step').forEach((stepEl, index) => {
                stepEl.classList.remove('active', 'completed');
                if (index + 1 < step) {
                    stepEl.classList.add('completed');
                } else if (index + 1 === step) {
                    stepEl.classList.add('active');
                }
            });
            
            currentStep = step;
        }
        
        // Date validation and guest selection
        function validateDatesAndShowGuests() {
            const girisTarihi = document.getElementById('giris_tarihi').value;
            const cikisTarihi = document.getElementById('cikis_tarihi').value;
            
            if (girisTarihi && cikisTarihi && new Date(cikisTarihi) > new Date(girisTarihi)) {
                document.getElementById('guest-selection').style.display = 'block';
                document.getElementById('next-to-step-2').disabled = false;
                return true;
            } else {
                document.getElementById('guest-selection').style.display = 'none';
                document.getElementById('next-to-step-2').disabled = true;
                return false;
            }
        }
        
        // Child age inputs management
        function updateChildAgeInputs() {
            const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
            const container = document.getElementById('child_ages_container');
            const inputsDiv = document.getElementById('child_ages_inputs');
            
            if (cocukSayisi > 0) {
                container.style.display = 'block';
                inputsDiv.innerHTML = '';
                
                for (let i = 0; i < cocukSayisi; i++) {
                    const div = document.createElement('div');
                    div.className = 'col-md-4 mb-3';
                    div.innerHTML = `
                        <label class="form-label">${i + 1}. Çocuk Yaşı</label>
                        <select class="form-select" name="cocuk_yaslari[]" required>
                            <option value="">Yaş seçin</option>
                            ${generateAgeOptions()}
                        </select>
                    `;
                    inputsDiv.appendChild(div);
                }
            } else {
                container.style.display = 'none';
                inputsDiv.innerHTML = '';
            }
        }
        
        function generateAgeOptions() {
            let options = '';
            for (let i = 0; i <= 17; i++) {
                options += `<option value="${i}">${i} yaş</option>`;
            }
            return options;
        }
        
        // Load available room types based on capacity
        function loadAvailableRoomTypes() {
            return new Promise((resolve, reject) => {
            const girisTarihi = document.getElementById('giris_tarihi').value;
            const cikisTarihi = document.getElementById('cikis_tarihi').value;
            const yetiskinSayisi = parseInt(document.getElementById('yetiskin_sayisi').value);
            const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
            const toplamKisi = yetiskinSayisi + cocukSayisi;
            
            console.log('loadAvailableRoomTypes called with:', {
                girisTarihi, cikisTarihi, yetiskinSayisi, cocukSayisi, toplamKisi
            });
            
            const odaTipiSelect = document.getElementById('oda_tipi_id');
            odaTipiSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            // Get child ages
            const cocukYaslari = [];
            const ageSelects = document.querySelectorAll('select[name="cocuk_yaslari[]"]');
            ageSelects.forEach(select => {
                if (select.value) {
                    cocukYaslari.push(select.value);
                }
            });
            
            console.log('Child ages:', cocukYaslari);
            
            if (cocukSayisi > 0 && cocukYaslari.length !== cocukSayisi) {
                console.log('Child ages validation failed');
                odaTipiSelect.innerHTML = '<option value="">Önce tüm çocuk yaşlarını seçin</option>';
                return;
            }
            
            // AJAX request to get available room types
            const formData = new FormData();
            const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
            if (csrfTokenInput) {
                formData.append('csrf_token', csrfTokenInput.value);
                console.log('CSRF token added:', csrfTokenInput.value);
            } else {
                console.error('CSRF token input not found!');
            }
            formData.append('giris_tarihi', girisTarihi);
            formData.append('cikis_tarihi', cikisTarihi);
            formData.append('yetiskin_sayisi', yetiskinSayisi);
            formData.append('cocuk_sayisi', cocukSayisi);
            cocukYaslari.forEach(yas => formData.append('cocuk_yaslari[]', yas));
            
            console.log('Making AJAX request to ajax/get-available-room-types.php');
            
            fetch('ajax/get-available-room-types.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response received:', response.status, response.statusText);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    
                    odaTipiSelect.innerHTML = '<option value="">Oda tipi seçin</option>';
                    
                    if (data.success && data.available_room_types) {
                        console.log('Processing room types:', data.available_room_types.length);
                        let addedCount = 0;
                        data.available_room_types.forEach(roomType => {
                            console.log(`Checking room type: ${roomType.oda_tipi_adi}`, {
                                max_yetiskin: roomType.max_yetiskin,
                                max_cocuk: roomType.max_cocuk,
                                yetiskin_sayisi: yetiskinSayisi,
                                cocuk_sayisi: cocukSayisi,
                                adult_check: roomType.max_yetiskin >= yetiskinSayisi,
                                child_check: roomType.max_cocuk >= cocukSayisi,
                                total_check: (roomType.max_yetiskin + roomType.max_cocuk) >= toplamKisi
                            });
                            
                            if (roomType.max_yetiskin >= yetiskinSayisi && 
                                roomType.max_cocuk >= cocukSayisi && 
                                (roomType.max_yetiskin + roomType.max_cocuk) >= toplamKisi) {
                                const option = document.createElement('option');
                                option.value = roomType.id;
                                option.textContent = `${roomType.oda_tipi_adi} (Max: ${roomType.max_yetiskin} Yetişkin, ${roomType.max_cocuk} Çocuk)`;
                                option.dataset.maxAdult = roomType.max_yetiskin;
                                option.dataset.maxChild = roomType.max_cocuk;
                                odaTipiSelect.appendChild(option);
                                addedCount++;
                                console.log(`Added room type: ${roomType.oda_tipi_adi}`);
                            }
                        });
                        console.log(`Total room types added: ${addedCount}`);
                    } else {
                        console.log('No room types in response or success=false');
                    }
                    
                    if (odaTipiSelect.children.length === 1) {
                        console.log('No suitable rooms found, showing error message');
                        odaTipiSelect.innerHTML = '<option value="">Bu kriterlere uygun oda bulunamadı</option>';
                    }
                    
                    resolve();
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.log('Raw text that failed to parse:', text);
                    odaTipiSelect.innerHTML = '<option value="">JSON parse hatası</option>';
                    reject(e);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                odaTipiSelect.innerHTML = '<option value="">Hata oluştu</option>';
                reject(error);
            });
            });
        }
        
        // Load available room numbers
        function loadAvailableRoomNumbers() {
            return new Promise((resolve, reject) => {
            const odaTipiId = document.getElementById('oda_tipi_id').value;
            const girisTarihi = document.getElementById('giris_tarihi').value;
            const cikisTarihi = document.getElementById('cikis_tarihi').value;
            
            if (!odaTipiId) {
                document.getElementById('room-number-selection').style.display = 'none';
                return;
            }
            
            document.getElementById('room-number-selection').style.display = 'block';
            const odaNumarasiSelect = document.getElementById('oda_numarasi_id');
            odaNumarasiSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            // AJAX request to get available room numbers
            const formData = new FormData();
            const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
            if (csrfTokenInput) {
                formData.append('csrf_token', csrfTokenInput.value);
            } else {
                console.error('CSRF token input not found!');
            }
            formData.append('oda_tipi_id', odaTipiId);
            formData.append('giris_tarihi', girisTarihi);
            formData.append('cikis_tarihi', cikisTarihi);
            
            fetch('get-available-rooms.php?t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                odaNumarasiSelect.innerHTML = '<option value="">Otomatik atama</option>';
                
                if (data.success && data.rooms) {
                    data.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = `Oda ${room.oda_numarasi} (Kat ${room.kat})`;
                        odaNumarasiSelect.appendChild(option);
                    });
                }
                
                resolve();
            })
            .catch(error => {
                console.error('Error:', error);
                odaNumarasiSelect.innerHTML = '<option value="">Otomatik atama</option>';
                reject(error);
            });
            
            document.getElementById('next-to-step-3').disabled = false;
            });
        }
        
        // Calculate price and update summary
        function calculatePriceAndUpdateSummary() {
            const girisTarihi = document.getElementById('giris_tarihi').value;
            const cikisTarihi = document.getElementById('cikis_tarihi').value;
            const odaTipiId = document.getElementById('oda_tipi_id').value;
            const yetiskinSayisi = document.getElementById('yetiskin_sayisi').value;
            const cocukSayisi = document.getElementById('cocuk_sayisi').value;
            
            // Update summary display
            document.getElementById('summary-checkin').textContent = new Date(girisTarihi).toLocaleDateString('tr-TR');
            document.getElementById('summary-checkout').textContent = new Date(cikisTarihi).toLocaleDateString('tr-TR');
            
            const nights = Math.ceil((new Date(cikisTarihi) - new Date(girisTarihi)) / (1000 * 60 * 60 * 24));
            document.getElementById('summary-nights').textContent = nights + ' gece';
            document.getElementById('summary-adults').textContent = yetiskinSayisi + ' kişi';
            document.getElementById('summary-children').textContent = cocukSayisi + ' kişi';
            
            const selectedRoomType = document.getElementById('oda_tipi_id').selectedOptions[0];
            document.getElementById('summary-room-type').textContent = selectedRoomType ? selectedRoomType.textContent : '-';
            
            const selectedRoomNumber = document.getElementById('oda_numarasi_id').selectedOptions[0];
            document.getElementById('summary-room-number').textContent = selectedRoomNumber && selectedRoomNumber.value ? selectedRoomNumber.textContent : 'Otomatik atama';
            
            if (!girisTarihi || !cikisTarihi || !odaTipiId) {
                return;
            }
            
            // Get child ages
            const cocukYaslari = [];
            const ageSelects = document.querySelectorAll('select[name="cocuk_yaslari[]"]');
            ageSelects.forEach(select => {
                if (select.value) {
                    cocukYaslari.push(select.value);
                }
            });
            
            // Show loading
            document.getElementById('price_loading').style.display = 'block';
            document.getElementById('price_summary').style.display = 'none';
            
            // AJAX request for price calculation
            const formData = new FormData();
            const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
            if (csrfTokenInput) {
                formData.append('csrf_token', csrfTokenInput.value);
            } else {
                console.error('CSRF token input not found!');
            }
            formData.append('oda_tipi_id', odaTipiId);
            formData.append('giris_tarihi', girisTarihi);
            formData.append('cikis_tarihi', cikisTarihi);
            formData.append('yetiskin_sayisi', yetiskinSayisi);
            formData.append('cocuk_sayisi', cocukSayisi);
            cocukYaslari.forEach(yas => formData.append('cocuk_yaslari[]', yas));
            
            fetch('../ajax/calculate-price.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    throw new Error('Geçersiz JSON yanıtı');
                }
            })
            .then(data => {
                document.getElementById('price_loading').style.display = 'none';
                
                if (data.success) {
                    const priceData = data.data;
                    document.getElementById('total_price').textContent = priceData.total_price.toLocaleString('tr-TR') + ' ₺';
                    document.getElementById('price_details').textContent = `${priceData.nights} gece - Ortalama: ${priceData.average_price.toLocaleString('tr-TR')} ₺/gece`;
                    document.getElementById('toplam_tutar').value = priceData.total_price;
                    document.getElementById('price_summary').style.display = 'block';
                    
                    // Minimum yetişkin şartı bilgisini göster
                    if (priceData.minimum_adult_requirement && !priceData.minimum_adult_requirement_met) {
                        const warningDiv = document.createElement('div');
                        warningDiv.className = 'alert alert-warning mt-2';
                        warningDiv.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat:</strong> Bu oda tipi için minimum ${priceData.minimum_adult_requirement} yetişkin şartı vardır. 
                            Ücretsiz çocuk hakkı kullanılamaz, tüm çocuklar ücretli olacaktır.
                        `;
                        document.getElementById('price_summary').appendChild(warningDiv);
                    }
                    
                    calculateRemainingAmount();
                    document.getElementById('next-to-step-4').disabled = false;
                } else {
                    alert('Fiyat hesaplanamadı: ' + (data.error || 'Bilinmeyen hata'));
                    document.getElementById('next-to-step-4').disabled = true;
                }
            })
            .catch(error => {
                document.getElementById('price_loading').style.display = 'none';
                console.error('Error:', error);
                alert('Fiyat hesaplama sırasında bir hata oluştu');
                document.getElementById('next-to-step-4').disabled = true;
            });
        }
        
        // Calculate remaining amount
        function calculateRemainingAmount() {
            const toplamTutar = parseFloat(document.getElementById('toplam_tutar').value) || 0;
            const odenenTutar = parseFloat(document.getElementById('odenen_tutar').value) || 0;
            const kalanTutar = toplamTutar - odenenTutar;
            
            document.getElementById('remaining_amount').textContent = kalanTutar.toLocaleString('tr-TR') + ' ₺';
            
            if (kalanTutar < 0) {
                document.getElementById('remaining_amount').className = 'form-control-plaintext fw-bold text-danger';
            } else if (kalanTutar === 0) {
                document.getElementById('remaining_amount').className = 'form-control-plaintext fw-bold text-success';
            } else {
                document.getElementById('remaining_amount').className = 'form-control-plaintext fw-bold text-warning';
            }
        }
        
        // Generate guest detail forms
        function generateGuestDetailForms() {
            const yetiskinSayisi = parseInt(document.getElementById('yetiskin_sayisi').value);
            const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
            
            // Generate adult forms
            const adultFormsDiv = document.getElementById('adult-forms');
            adultFormsDiv.innerHTML = '';
            
            for (let i = 0; i < yetiskinSayisi; i++) {
                const div = document.createElement('div');
                div.className = 'card mb-3';
                div.innerHTML = `
                    <div class="card-header">
                        <h6 class="mb-0">${i + 1}. Yetişkin</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Ad *</label>
                                <input type="text" class="form-control" name="yetiskin_ad_${i}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Soyad *</label>
                                <input type="text" class="form-control" name="yetiskin_soyad_${i}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Cinsiyet *</label>
                                <select class="form-select" name="yetiskin_cinsiyet_${i}" required>
                                    <option value="">Seçin</option>
                                    <option value="erkek">Erkek</option>
                                    <option value="kadın">Kadın</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">TC Kimlik No</label>
                                <input type="text" class="form-control" name="yetiskin_tc_${i}" maxlength="11">
                            </div>
                        </div>
                    </div>
                `;
                adultFormsDiv.appendChild(div);
            }
            
            // Generate child forms
            const childFormsDiv = document.getElementById('child-forms');
            const childDetailsDiv = document.getElementById('child-details');
            
            if (cocukSayisi > 0) {
                childDetailsDiv.style.display = 'block';
                childFormsDiv.innerHTML = '';
                
                const cocukYaslari = [];
                const ageSelects = document.querySelectorAll('select[name="cocuk_yaslari[]"]');
                ageSelects.forEach(select => {
                    if (select.value) {
                        cocukYaslari.push(parseInt(select.value));
                    }
                });
                
                for (let i = 0; i < cocukSayisi; i++) {
                    const div = document.createElement('div');
                    div.className = 'card mb-3';
                    div.innerHTML = `
                        <div class="card-header">
                            <h6 class="mb-0">${i + 1}. Çocuk (${cocukYaslari[i] || '?'} yaş)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" name="cocuk_ad_${i}" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" name="cocuk_soyad_${i}" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Yaş</label>
                                    <input type="number" class="form-control" name="cocuk_yas_${i}" value="${cocukYaslari[i] || 0}" readonly>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Cinsiyet *</label>
                                    <select class="form-select" name="cocuk_cinsiyet_${i}" required>
                                        <option value="">Seçin</option>
                                        <option value="erkek">Erkek</option>
                                        <option value="kız">Kız</option>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="cocuk_yas_${i}" value="${cocukYaslari[i] || 0}">
                        </div>
                    `;
                    childFormsDiv.appendChild(div);
                }
            } else {
                childDetailsDiv.style.display = 'none';
            }
        }
        
        // Event listeners
        document.getElementById('giris_tarihi').addEventListener('change', validateDatesAndShowGuests);
        document.getElementById('cikis_tarihi').addEventListener('change', validateDatesAndShowGuests);
        document.getElementById('cocuk_sayisi').addEventListener('change', updateChildAgeInputs);
        document.getElementById('oda_tipi_id').addEventListener('change', loadAvailableRoomNumbers);
        document.getElementById('odenen_tutar').addEventListener('input', calculateRemainingAmount);
        
        // Step navigation buttons
        document.getElementById('next-to-step-2').addEventListener('click', function() {
            loadAvailableRoomTypes();
            showStep(2);
        });
        
        document.getElementById('back-to-step-1').addEventListener('click', function() {
            showStep(1);
        });
        
        document.getElementById('next-to-step-3').addEventListener('click', function() {
            calculatePriceAndUpdateSummary();
            showStep(3);
        });
        
        document.getElementById('back-to-step-2').addEventListener('click', function() {
            showStep(2);
        });
        
        document.getElementById('next-to-step-4').addEventListener('click', function() {
            showStep(4);
        });
        
        document.getElementById('back-to-step-3').addEventListener('click', function() {
            showStep(3);
        });
        
        // TC Kimlik No kontrolü ve otomatik doldurma - YENİ SİSTEM
        const tcKimlikInput = document.getElementById('musteri_tc_kimlik');
        const customerInfoFields = document.getElementById('customer-info-fields');
        let tcTimeout;

        // TC Kimlik kutuları sistemi
        setupTCKimlikBoxes();
        
        function setupTCKimlikBoxes() {
            const tcBoxes = document.querySelectorAll('.tc-digit');
            const hiddenInput = document.getElementById('musteri_tc_kimlik');
            
            tcBoxes.forEach((box, index) => {
                // Sadece rakam girişine izin ver
                box.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Sadece tek rakam al
                    if (value.length > 1) {
                        value = value.slice(-1);
                    }
                    
                    e.target.value = value;
                    
                    // Değeri zorla göster
                    e.target.style.color = '#000';
                    e.target.style.webkitTextFillColor = '#000';
                    e.target.style.opacity = '1';
                    
                    console.log('TC Box Input:', index, 'Value:', value, 'Display:', e.target.value);
                    
                    // Dolu kutu olarak işaretle
                    if (value) {
                        e.target.classList.add('filled');
                        e.target.style.backgroundColor = '#e8f5e8';
                        e.target.style.borderColor = '#28a745';
                    } else {
                        e.target.classList.remove('filled');
                        e.target.style.backgroundColor = '#fff';
                        e.target.style.borderColor = '#ddd';
                    }
                    
                    // Hidden input'u güncelle
                    updateHiddenInput();
                    console.log('Hidden Input Value:', hiddenInput.value);
                    
                    // Sonraki kutuya geç
                    if (value && index < tcBoxes.length - 1) {
                        tcBoxes[index + 1].focus();
                    }
                    
                    // 11 hane girildiyse müşteri kontrolü yap
                    if (hiddenInput.value.length === 11) {
                        console.log('11 hane girildi, müşteri kontrolü yapılıyor...');
                        clearTimeout(tcTimeout);
                        tcTimeout = setTimeout(() => {
                            checkCustomerByTC();
                        }, 500);
                    } else {
                        clearCustomerFields();
                    }
                });
                
                // Klavye olayları
                box.addEventListener('keydown', function(e) {
                    // Sadece rakam tuşlarına izin ver
                    if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Geri tuşu ile önceki kutuya geç
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        tcBoxes[index - 1].focus();
                    }
                    
                    // Rakam tuşu basıldığında
                    if (/[0-9]/.test(e.key)) {
                        e.target.value = e.key;
                        e.target.style.color = '#000';
                        e.target.style.webkitTextFillColor = '#000';
                        e.target.style.opacity = '1';
                        e.target.classList.add('filled');
                        e.target.style.backgroundColor = '#e8f5e8';
                        e.target.style.borderColor = '#28a745';
                        
                        updateHiddenInput();
                        
                        // Sonraki kutuya geç
                        if (index < tcBoxes.length - 1) {
                            tcBoxes[index + 1].focus();
                        }
                        
                        // 11 hane girildiyse müşteri kontrolü yap
                        if (hiddenInput.value.length === 11) {
                            clearTimeout(tcTimeout);
                            tcTimeout = setTimeout(() => {
                                checkCustomerByTC();
                            }, 500);
                        }
                    }
                });
                
                // Paste işlemi
                box.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                    
                    if (pastedData.length === 11) {
                        // 11 haneli TC kimlik yapıştırıldı
                        for (let i = 0; i < 11; i++) {
                            tcBoxes[i].value = pastedData[i];
                            tcBoxes[i].classList.add('filled');
                            
                            // Değeri zorla göster
                            tcBoxes[i].style.color = '#000';
                            tcBoxes[i].style.webkitTextFillColor = '#000';
                            tcBoxes[i].style.opacity = '1';
                            tcBoxes[i].style.backgroundColor = '#e8f5e8';
                            tcBoxes[i].style.borderColor = '#28a745';
                        }
                        updateHiddenInput();
                        tcBoxes[10].focus();
                        
                        console.log('TC Kimlik yapıştırıldı:', pastedData);
                        
                        // Müşteri kontrolü yap
                        clearTimeout(tcTimeout);
                        tcTimeout = setTimeout(() => {
                            checkCustomerByTC();
                        }, 500);
                    }
                });
            });
            
            function updateHiddenInput() {
                const tcValue = Array.from(tcBoxes).map(box => box.value).join('');
                hiddenInput.value = tcValue;
            }
        }


        function checkCustomerByTC() {
            const tcKimlik = document.getElementById('musteri_tc_kimlik').value;
            
            console.log('checkCustomerByTC çağrıldı, TC Kimlik:', tcKimlik);
            
            if (tcKimlik.length !== 11) {
                console.log('TC kimlik 11 hane değil, işlem iptal edildi');
                return;
            }

            // Loading durumu göster
            const tcKimlikInput = document.getElementById('musteri_tc_kimlik');
            tcKimlikInput.classList.add('is-valid');
            tcKimlikInput.classList.remove('is-invalid');

            console.log('AJAX isteği gönderiliyor...');
            
            // AJAX isteği gönder
            fetch('../ajax/check-customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tc_kimlik=${tcKimlik}&csrf_token=${document.querySelector('input[name="csrf_token"]').value}`
            })
            .then(response => {
                console.log('AJAX Response Status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('AJAX Response Data:', data);
                if (data.success && data.customer) {
                    // Müşteri bulundu - bilgileri doldur
                    console.log('Müşteri bulundu:', data.customer);
                    fillCustomerData(data.customer);
                    tcKimlikInput.classList.add('is-valid');
                    tcKimlikInput.classList.remove('is-invalid');
                    document.getElementById('tc-success-message').style.display = 'block';
                } else {
                    // Müşteri bulunamadı - alanları temizle ve düzenlenebilir yap
                    console.log('Müşteri bulunamadı, yeni müşteri formu gösteriliyor');
                    clearCustomerFields();
                    tcKimlikInput.classList.remove('is-valid', 'is-invalid');
                    document.getElementById('tc-success-message').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                tcKimlikInput.classList.add('is-invalid');
                tcKimlikInput.classList.remove('is-valid');
                clearCustomerFields();
                document.getElementById('tc-success-message').style.display = 'none';
            });
        }

        function fillCustomerData(customer) {
            // Müşteri bilgileri formunu göster
            document.getElementById('customer-info-fields').style.display = 'block';
            
            // Müşteri bilgilerini doldur
            document.getElementById('musteri_ad').value = customer.ad || '';
            document.getElementById('musteri_soyad').value = customer.soyad || '';
            document.getElementById('musteri_email').value = customer.email || '';
            document.getElementById('musteri_telefon').value = customer.telefon || '';

            // Alanları readonly yap (mevcut müşteri)
            document.getElementById('musteri_ad').readOnly = true;
            document.getElementById('musteri_soyad').readOnly = true;
            document.getElementById('musteri_email').readOnly = true;
            document.getElementById('musteri_telefon').readOnly = true;

            // Alanları valid olarak işaretle
            ['musteri_ad', 'musteri_soyad', 'musteri_email', 'musteri_telefon'].forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.classList.add('is-valid');
                field.classList.remove('is-invalid');
            });
        }

        function clearCustomerFields() {
            // Müşteri bilgileri formunu göster (yeni müşteri için)
            document.getElementById('customer-info-fields').style.display = 'block';
            
            // Alanları temizle
            document.getElementById('musteri_ad').value = '';
            document.getElementById('musteri_soyad').value = '';
            document.getElementById('musteri_email').value = '';
            document.getElementById('musteri_telefon').value = '';

            // Readonly'yi kaldır (yeni müşteri)
            document.getElementById('musteri_ad').readOnly = false;
            document.getElementById('musteri_soyad').readOnly = false;
            document.getElementById('musteri_email').readOnly = false;
            document.getElementById('musteri_telefon').readOnly = false;

            // Validation sınıflarını temizle
            ['musteri_ad', 'musteri_soyad', 'musteri_email', 'musteri_telefon'].forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.classList.remove('is-valid', 'is-invalid');
            });
            
            // Başarı mesajını gizle
            document.getElementById('tc-success-message').style.display = 'none';
        }
        
        document.getElementById('next-to-step-5').addEventListener('click', function() {
            // Müşteri bilgilerini kontrol et
            const musteriAd = document.getElementById('musteri_ad').value.trim();
            const musteriSoyad = document.getElementById('musteri_soyad').value.trim();
            const musteriEmail = document.getElementById('musteri_email').value.trim();
            const musteriTelefon = document.getElementById('musteri_telefon').value.trim();
            
            if (!musteriAd || !musteriSoyad || !musteriEmail || !musteriTelefon) {
                alert('Lütfen tüm müşteri bilgilerini doldurun!');
                return;
            }
            
            // Email formatını kontrol et
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(musteriEmail)) {
                alert('Lütfen geçerli bir email adresi girin!');
                return;
            }
            
            generateGuestDetailForms();
            showStep(5);
        });
        
        document.getElementById('back-to-step-4').addEventListener('click', function() {
            showStep(4);
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('giris_tarihi').min = today;
            document.getElementById('cikis_tarihi').min = today;
            
            // Seçilen oda bilgilerini otomatik doldur
            <?php if ($selected_room_info && $selected_date): ?>
            
            // Tarihi otomatik doldur
            document.getElementById('giris_tarihi').value = '<?= $selected_date ?>';
            
            // Çıkış tarihini bir gün sonrası yap
            const girisTarihi = new Date('<?= $selected_date ?>');
            girisTarihi.setDate(girisTarihi.getDate() + 1);
            document.getElementById('cikis_tarihi').value = girisTarihi.toISOString().split('T')[0];
            
            // Form validasyonunu tetikle
            document.getElementById('giris_tarihi').dispatchEvent(new Event('change'));
            document.getElementById('cikis_tarihi').dispatchEvent(new Event('change'));
            
            // Oda tipini otomatik seç
            setTimeout(function() {
                const odaTipiSelect = document.getElementById('oda_tipi_id');
                if (odaTipiSelect) {
                    // Önce oda tiplerini yükle
                    loadAvailableRoomTypes();
                    
                    // Oda tipleri yüklendikten sonra seçilen oda tipini seç
                    loadAvailableRoomTypes().then(function() {
                        // Seçilen oda tipini seç
                        odaTipiSelect.value = '<?= $selected_room_info['oda_tipi_id'] ?>';
                        
                        // Form validasyonunu tetikle
                        odaTipiSelect.dispatchEvent(new Event('change'));
                        
                        // Oda numaralarını yükle ve seçilen oda numarasını seç
                        loadAvailableRoomNumbers().then(function() {
                            const odaNumarasiSelect = document.getElementById('oda_numarasi_id');
                            if (odaNumarasiSelect) {
                                // Seçilen oda numarasını seç
                                odaNumarasiSelect.value = '<?= $selected_room_info['id'] ?>';
                                odaNumarasiSelect.dispatchEvent(new Event('change'));
                                
                                // Fiyat hesaplama tetikle
                                setTimeout(function() {
                                    calculatePriceAndUpdateSummary();
                                }, 500);
                            }
                        });
                    });
                }
            }, 500);
            <?php endif; ?>
            
            // Update checkout date when checkin changes
            document.getElementById('giris_tarihi').addEventListener('change', function() {
                const girisTarihi = new Date(this.value);
                girisTarihi.setDate(girisTarihi.getDate() + 1);
                document.getElementById('cikis_tarihi').min = girisTarihi.toISOString().split('T')[0];
            });
            
            // Listen for child age changes to update room types
            document.addEventListener('change', function(e) {
                if (e.target.name === 'cocuk_yaslari[]') {
                    loadAvailableRoomTypes();
                }
            });
            
            // Telefon numarası formatlaması
            const phoneInput = document.getElementById('musteri_telefon');
            phoneInput.addEventListener('input', function(event) {
                let value = event.target.value.replace(/\D/g, ''); // Sadece rakamları al
                
                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = value;
                    } else if (value.length <= 6) {
                        value = value.slice(0, 3) + ' ' + value.slice(3);
                    } else if (value.length <= 8) {
                        value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6);
                    } else if (value.length <= 10) {
                        value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 8) + ' ' + value.slice(8);
                    } else {
                        value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 8) + ' ' + value.slice(8, 10);
                    }
                }
                
                event.target.value = value;
            });
        });
    </script>
</body>
</html>