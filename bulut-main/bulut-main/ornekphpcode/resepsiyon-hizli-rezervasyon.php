<?php
// Production'da hata raporlamayı kapat
error_reporting(0);
ini_set('display_errors', 0);

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

// Düzenleme modu kontrolü
$edit_mode = false;
$rezervasyon_data = null;

if (isset($_GET['duzenle']) && is_numeric($_GET['duzenle'])) {
    $rezervasyon_id = intval($_GET['duzenle']);
    $edit_mode = true;
    
    // Rezervasyon verilerini getir
    $rezervasyon_data = fetchOne("
        SELECT r.*, m.ad as musteri_ad, m.soyad as musteri_soyad, m.email as musteri_email, 
               m.telefon as musteri_telefon, m.tc_kimlik as musteri_tc_kimlik, m.adres as musteri_adres
        FROM rezervasyonlar r 
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        WHERE r.id = ?
    ", [$rezervasyon_id]);
    
    if (!$rezervasyon_data) {
        $error_message = 'Rezervasyon bulunamadı.';
        $edit_mode = false;
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

// Hızlı rezervasyon oluşturma
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hizli_rezervasyon_submit'])) {
    $ad = turkishUppercase(trim($_POST['ad']));
    $soyad = turkishUppercase(trim($_POST['soyad']));
    $telefon = preg_replace('/[^0-9]/', '', trim($_POST['telefon'])); // Sadece rakamları al
    $email = trim($_POST['email']);
    $tc_kimlik = preg_replace('/[^0-9]/', '', trim($_POST['tc_kimlik'])); // Sadece rakamları al
    $adres = trim($_POST['adres']);
    $oda_tipi_id = intval($_POST['oda_tipi_id']);
    $oda_numarasi_id = intval($_POST['oda_numarasi_id']);
    $giris_tarihi = $_POST['giris_tarihi'];
    $cikis_tarihi = $_POST['cikis_tarihi'];
    $yetiskin_sayisi = intval($_POST['yetiskin_sayisi']);
    $cocuk_sayisi = intval($_POST['cocuk_sayisi']);
    $odeme_yontemi = $_POST['odeme_yontemi'];
    $odeme_miktari = floatval($_POST['odeme_miktari']);
    $notlar = trim($_POST['notlar']);
    $otomatik_checkin = isset($_POST['otomatik_checkin']) ? 1 : 0;
    
    try {
        $pdo->beginTransaction();
        
        // Veri doğrulama
        $errors = [];
        
        if (empty($ad) || strlen($ad) < 2) {
            $errors[] = 'Ad en az 2 karakter olmalıdır.';
        }
        
        if (empty($soyad) || strlen($soyad) < 2) {
            $errors[] = 'Soyad en az 2 karakter olmalıdır.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        
        if (empty($tc_kimlik)) {
            $errors[] = 'TC kimlik numarası zorunludur.';
        } elseif (strlen($tc_kimlik) !== 11) {
            $errors[] = 'TC kimlik numarası 11 haneli olmalıdır.';
        }
        
        if (empty($telefon) || strlen($telefon) < 10) {
            $errors[] = 'Geçerli bir telefon numarası girin.';
        }
        
        if (empty($giris_tarihi) || empty($cikis_tarihi)) {
            $errors[] = 'Giriş ve çıkış tarihleri zorunludur.';
        } elseif (strtotime($cikis_tarihi) <= strtotime($giris_tarihi)) {
            $errors[] = 'Çıkış tarihi giriş tarihinden sonra olmalıdır.';
        }
        
        if (empty($oda_tipi_id)) {
            $errors[] = 'Oda tipi seçimi zorunludur.';
        }
        
        if (empty($oda_numarasi_id)) {
            $errors[] = 'Oda numarası seçimi zorunludur.';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Müşteri kontrolü ve ekleme
        $musteri = fetchOne("SELECT id FROM musteriler WHERE tc_kimlik = ?", [$tc_kimlik]);
        
        if (!$musteri) {
            // Yeni müşteri ekle
            $musteri_id = insertAndGetId("
                INSERT INTO musteriler (ad, soyad, telefon, email, tc_kimlik, adres, musteri_tipi, durum, olusturma_tarihi) 
                VALUES (?, ?, ?, ?, ?, ?, 'normal', 'aktif', NOW())
            ", [$ad, $soyad, $telefon, $email, $tc_kimlik, $adres]);
        } else {
            $musteri_id = $musteri['id'];
        }
        
        // Gece sayısı hesapla
        $giris = new DateTime($giris_tarihi);
        $cikis = new DateTime($cikis_tarihi);
        $gece_sayisi = $giris->diff($cikis)->days;
        
        // Oda tipi fiyatını al
        $oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ?", [$oda_tipi_id]);
        $birim_fiyat = $oda_tipi['base_price'] ?? 0;
        $toplam_fiyat = $birim_fiyat * $gece_sayisi;
        
        // Oda tipinin check-in ve check-out saatlerini al
        $checkin_saati = $oda_tipi['checkin_saati'] ?? '14:00:00';
        $checkout_saati = $oda_tipi['checkout_saati'] ?? '12:00:00';
        
        // Tarih formatını düzenle - Veritabanından alınan saatleri kullan
        if (strlen($giris_tarihi) == 10) { // Y-m-d formatı
            $giris_tarihi = $giris_tarihi . ' ' . $checkin_saati; // Veritabanından alınan check-in saati
        }
        if (strlen($cikis_tarihi) == 10) { // Y-m-d formatı
            $cikis_tarihi = $cikis_tarihi . ' ' . $checkout_saati; // Veritabanından alınan check-out saati
        }
        
        // Rezervasyon kodu oluştur
        $rezervasyon_kodu = 'RZ' . time() . rand(100, 999);
        
        // Rezervasyon ekle
        $sql = "INSERT INTO rezervasyonlar (
                    musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi,
                    yetiskin_sayisi, cocuk_sayisi, toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu, olusturma_tarihi,
                    satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email, 
                    musteri_telefon, musteri_kimlik, notlar
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'onaylandi', ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $kalan_tutar = $toplam_fiyat - $odeme_miktari;
        $odeme_durumu = ($kalan_tutar <= 0) ? 'tamamen_odendi' : (($odeme_miktari > 0) ? 'kısmi' : 'odenmedi');
        
        if (!executeQuery($sql, [
            $musteri_id, $oda_tipi_id, $oda_numarasi_id, $giris_tarihi, $cikis_tarihi,
            $yetiskin_sayisi, $cocuk_sayisi, $toplam_fiyat, $toplam_fiyat, $odeme_miktari, $kalan_tutar, $odeme_durumu, $_SESSION['user_id'], $rezervasyon_kodu,
            $ad, $soyad, $email, $telefon, $tc_kimlik, $notlar
        ])) {
            throw new Exception('Rezervasyon oluşturulurken hata oluştu.');
        }
        
        $rezervasyon_id = $pdo->lastInsertId();
        
        // Ödeme kaydı
        if ($odeme_miktari > 0) {
            $sql = "INSERT INTO rezervasyon_odemeleri (rezervasyon_id, odeme_tutari, odeme_yontemi, aciklama, kullanici_id, durum, odeme_tarihi) 
                    VALUES (?, ?, ?, 'Hızlı rezervasyon ödemesi', ?, 'aktif', NOW())";
            
            if (!executeQuery($sql, [$rezervasyon_id, $odeme_miktari, $odeme_yontemi, $_SESSION['user_id']])) {
                throw new Exception('Ödeme kaydı eklenirken hata oluştu.');
            }
        }
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id, olusturma_tarihi) 
                VALUES (?, 'olusturuldu', 'Hızlı rezervasyon oluşturuldu (Walk-in)', ?, NOW())";
        
        executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
        
        // Otomatik check-in işlemi
        if ($otomatik_checkin && $giris_tarihi == date('Y-m-d')) {
            // Rezervasyon durumunu check-in yap
            $sql = "UPDATE rezervasyonlar SET 
                    durum = 'check_in', 
                    gercek_giris_tarihi = NOW()
                    WHERE id = ?";
            
            if (!executeQuery($sql, [$rezervasyon_id])) {
                throw new Exception('Otomatik check-in işlemi sırasında hata oluştu.');
            }
            
            // Oda durumunu dolu yap
            $sql = "UPDATE oda_numaralari SET durum = 'dolu' WHERE id = ?";
            
            if (!executeQuery($sql, [$oda_numarasi_id])) {
                throw new Exception('Oda durumu güncellenirken hata oluştu.');
            }
            
            // Check-in geçmişi kaydı
            $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id, olusturma_tarihi) 
                    VALUES (?, 'check_in', 'Otomatik check-in işlemi tamamlandı (Walk-in)', ?, NOW())";
            
            executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
            
            $success_message = 'Hızlı rezervasyon başarıyla oluşturuldu ve otomatik check-in yapıldı. Rezervasyon ID: ' . $rezervasyon_id;
        } else {
            $success_message = 'Hızlı rezervasyon başarıyla oluşturuldu. Rezervasyon ID: ' . $rezervasyon_id;
        }
        
        $pdo->commit();
        
        // Direkt check-in yapmak isteyip istemediğini sor
        if (isset($_POST['direkt_checkin']) && $_POST['direkt_checkin'] == '1') {
            header('Location: resepsiyon-checkin.php?id=' . $rezervasyon_id);
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Hızlı rezervasyon hatası: ' . $e->getMessage());
        $error_message = 'Rezervasyon oluşturulurken hata oluştu. Lütfen tekrar deneyin.';
    }
}

// Oda tipleri - güvenli sorgu
try {
    $stmt = $pdo->prepare("SELECT id, oda_tipi_adi, base_price, max_yetiskin, max_cocuk FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
    $stmt->execute();
    $oda_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Her oda tipi için varsayılan fiyatı hesapla (bugün için)
    // Gerçek fiyat tarih seçildiğinde JavaScript ile güncellenecek
    $bugun = date('Y-m-d');
    foreach ($oda_tipleri as $index => $tip) {
        $guncel_fiyat = $oda_tipleri[$index]['base_price'];
        $kampanya_indirimi = 0;
        
        try {
            // 1. Özel fiyat kontrolü (en yüksek öncelik)
            $gun_adi = strtolower(date('l', strtotime($bugun))); // monday, tuesday, etc.
            $gun_adi_tr = [
                'monday' => 'pazartesi',
                'tuesday' => 'sali', 
                'wednesday' => 'carsamba',
                'thursday' => 'persembe',
                'friday' => 'cuma',
                'saturday' => 'cumartesi',
                'sunday' => 'pazar'
            ];
            $gun_adi_tr = $gun_adi_tr[$gun_adi];
            
            $ozel_stmt = $pdo->prepare("
                SELECT {$gun_adi_tr}_temel_fiyat, {$gun_adi_tr}_sabit_fiyat, {$gun_adi_tr}_aktif, fiyat_tipi
                FROM ozel_fiyatlar 
                WHERE oda_tipi_id = ? 
                AND baslangic_tarihi <= ? 
                AND bitis_tarihi >= ? 
                AND aktif = 'aktif'
                AND {$gun_adi_tr}_aktif = 1
                ORDER BY id DESC 
                LIMIT 1
            ");
            $ozel_stmt->execute([$oda_tipleri[$index]['id'], $bugun, $bugun]);
            $ozel_fiyat = $ozel_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ozel_fiyat) {
                if ($ozel_fiyat['fiyat_tipi'] === 'temel_fiyat' && !empty($ozel_fiyat[$gun_adi_tr . '_temel_fiyat'])) {
                    $guncel_fiyat = $ozel_fiyat[$gun_adi_tr . '_temel_fiyat'];
                } elseif ($ozel_fiyat['fiyat_tipi'] === 'sabit_fiyat' && !empty($ozel_fiyat[$gun_adi_tr . '_sabit_fiyat'])) {
                    $guncel_fiyat = $ozel_fiyat[$gun_adi_tr . '_sabit_fiyat'];
                }
            }
            
            // 2. Sezonluk fiyat kontrolü (özel fiyat yoksa)
            if ($guncel_fiyat == $oda_tipleri[$index]['base_price']) {
                $sezon_stmt = $pdo->prepare("
                    SELECT temel_fiyat 
                    FROM sezonluk_fiyatlar 
                    WHERE oda_tipi_id = ? 
                    AND baslangic_tarihi <= ? 
                    AND bitis_tarihi >= ? 
                    AND aktif = 1 
                    ORDER BY oncelik DESC, id DESC 
                    LIMIT 1
                ");
                $sezon_stmt->execute([$oda_tipleri[$index]['id'], $bugun, $bugun]);
                $sezon_fiyat = $sezon_stmt->fetchColumn();
                
                if ($sezon_fiyat) {
                    $guncel_fiyat = $sezon_fiyat;
                }
            }
        } catch (Exception $e) {
            // Fiyat tabloları yoksa devam et
        }
        
        try {
            // 3. Kampanya indirimi kontrolü (son adım - indirim uygula)
            $kampanya_stmt = $pdo->prepare("
                SELECT indirim_tipi, indirim_miktari 
                FROM kampanya_fiyatlari 
                WHERE oda_tipi_id = ? 
                AND baslangic_tarihi <= ? 
                AND bitis_tarihi >= ? 
                AND aktif = 1 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $kampanya_stmt->execute([$oda_tipleri[$index]['id'], $bugun, $bugun]);
            $kampanya = $kampanya_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($kampanya && !empty($kampanya['indirim_miktari']) && $kampanya['indirim_miktari'] > 0) {
                if ($kampanya['indirim_tipi'] === 'yuzde') {
                    // Yüzde indirimi
                    $kampanya_indirimi = ($guncel_fiyat * $kampanya['indirim_miktari']) / 100;
                } elseif ($kampanya['indirim_tipi'] === 'sabit_tutar') {
                    // Sabit tutar indirimi
                    $kampanya_indirimi = $kampanya['indirim_miktari'];
                }
                
                if ($kampanya_indirimi > 0) {
                    $guncel_fiyat = max(0, $guncel_fiyat - $kampanya_indirimi);
                }
            }
        } catch (Exception $e) {
            // Kampanya tablosu yoksa devam et
        }
        
        $oda_tipleri[$index]['guncel_fiyat'] = $guncel_fiyat;
    }
    
    if (empty($oda_tipleri)) {
        $error_message = 'Aktif oda tipi bulunamadı. Lütfen önce oda tiplerini tanımlayın.';
    }
} catch (Exception $e) {
    error_log('Oda tipleri yüklenirken hata: ' . $e->getMessage());
    $error_message = 'Oda tipleri yüklenirken hata oluştu.';
    $oda_tipleri = [];
}

$page_title = 'Hızlı Rezervasyon';
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
            overflow-x: hidden;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 100vw;
        }
        
        html {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        
        * {
            box-sizing: border-box;
        }
        
        /* Tüm margin ve padding'leri sıfırla */
        .desktop-container, .desktop-header, .form-container {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .desktop-header {
            padding: 8px 12px !important;
        }
        
        .form-container {
            padding: 8px !important;
        }
        
        /* Bootstrap override */
        .container, .container-fluid, .row, .col, .col-md-6, .col-12 {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        
        .col-md-6, .col-12 {
            padding-left: 4px !important;
            padding-right: 4px !important;
        }
        
        .container-fluid {
            padding-left: 0;
            padding-right: 0;
            width: 100%;
            max-width: 100%;
        }
        
        .desktop-container {
            padding: 0;
            margin: 0;
            min-height: 100vh;
            overflow-y: auto;
            width: 100%;
            max-width: 100vw;
        }
        
        .desktop-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0;
            padding: 8px 12px;
            margin: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .quick-access-buttons {
            display: flex;
            gap: 3px;
            flex-wrap: nowrap;
            justify-content: flex-end;
            margin-bottom: 0;
            overflow-x: auto;
        }
        
        .quick-access-btn {
            padding: 3px 6px;
            font-size: 0.7rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            flex-shrink: 0;
        }
        
        .quick-access-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .quick-access-btn i {
            font-size: 0.7rem;
        }
        
        /* Geri ve Çıkış butonları için aynı stil */
        .quick-access-buttons .btn-outline-primary,
        .quick-access-buttons .btn-outline-danger {
            padding: 3px 6px;
            font-size: 0.7rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .quick-access-buttons {
                gap: 2px;
                margin-bottom: 0;
            }
            
            .quick-access-btn {
                padding: 2px 4px;
                font-size: 0.65rem;
            }
            
            .quick-access-btn i {
                font-size: 0.6rem;
            }
        }
        
        @media (max-width: 576px) {
            .quick-access-buttons {
                gap: 1px;
                margin-bottom: 0;
            }
            
            .quick-access-btn {
                padding: 2px 3px;
                font-size: 0.6rem;
            }
            
            .quick-access-btn span {
                display: none;
            }
            
            .quick-access-btn i {
                font-size: 0.55rem;
            }
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 60px);
            overflow-y: visible;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            margin-top: 0;
        }
        
        .form-section {
            background: rgba(248, 249, 250, 0.8);
            border-radius: 6px;
            padding: 4px;
            margin-bottom: 4px;
            border-left: 2px solid #667eea;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .row {
            margin-left: 0;
            margin-right: 0;
            width: 100%;
            max-width: 100%;
        }
        
        .col-md-6, .col-12 {
            padding-left: 4px;
            padding-right: 4px;
            box-sizing: border-box;
        }
        
        .section-title {
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 4px;
            color: #667eea;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 6px;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            padding: 4px 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .price-display {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border-radius: 4px;
            padding: 6px;
            text-align: center;
            margin: 6px 0;
        }
        
        .price-display h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .price-display p {
            margin: 2px 0 0 0;
            opacity: 0.9;
            font-size: 0.75rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .current-time {
            font-size: 1.1rem;
            color: #667eea;
            font-weight: 500;
        }
        
        .welcome-text {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }
        
    .tc-kimlik-container {
        max-width: 100%;
    }
        
        .tc-digit {
            width: 24px;
            height: 28px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
    .tc-digit:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
        
    .tc-digit.filled {
        background-color: #f8f9fa;
        border-color: #28a745;
    }
        
        @media (max-width: 768px) {
            .desktop-container {
                padding: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Desktop Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="welcome-text mb-0">
                        <i class="fas fa-plus me-2"></i>
                        Hızlı Rezervasyon (Walk-In)
                    </h1>
                </div>
                <div class="col-md-6 text-end">
                    <!-- Hızlı Erişim Butonları -->
                    <div class="quick-access-buttons">
                        <a href="resepsiyon-checkin.php" class="quick-access-btn btn btn-success" title="Check-in İşlemleri">
                            <i class="fas fa-sign-in-alt"></i> <span>Check-in</span>
                        </a>
                        <a href="resepsiyon-checkout.php" class="quick-access-btn btn btn-warning" title="Check-out İşlemleri">
                            <i class="fas fa-sign-out-alt"></i> <span>Check-out</span>
                        </a>
                        <a href="resepsiyon-oda-yonetimi.php" class="quick-access-btn btn btn-info" title="Oda Yönetimi">
                            <i class="fas fa-bed"></i> <span>Oda Yönetimi</span>
                        </a>
                        <a href="resepsiyon-doluluk-orani.php" class="quick-access-btn btn btn-primary" title="Doluluk Oranı">
                            <i class="fas fa-chart-bar"></i> <span>Doluluk</span>
                        </a>
                        <a href="resepsiyon-misafir-hizmetleri.php" class="quick-access-btn btn btn-secondary" title="Misafir Hizmetleri">
                            <i class="fas fa-concierge-bell"></i> <span>Misafir Hizmetleri</span>
                        </a>
                        <a href="resepsiyon-raporlar.php" class="quick-access-btn btn btn-dark" title="Raporlar">
                            <i class="fas fa-file-alt"></i> <span>Raporlar</span>
                        </a>
                        <a href="resepsiyon-ana-ekran.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Ana Ekran
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Alt Satır - Tarih ve Hoş Geldiniz -->
            <div class="row">
                <div class="col-12">
                    <p class="current-time mb-0" style="font-size: 0.8rem; color: #666;">
                        <i class="fas fa-clock me-2"></i>
                        <span id="live-time"><?= date('d.m.Y H:i:s') ?></span> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
            </div>
            
            <!-- Form Container -->
            <div class="form-container">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <div class="d-flex">
                <div><i class="fas fa-check-circle me-2"></i></div>
                <div><?= htmlspecialchars($success_message) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="d-flex">
                <div><i class="fas fa-exclamation-circle me-2"></i></div>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>
    
    <!-- Çoklu Oda Bildirim -->
    <div class="alert alert-info alert-dismissible" role="alert">
        <div class="d-flex">
            <div><i class="fas fa-info-circle me-2"></i></div>
            <div>
                <strong>Birden Fazla Oda Rezervasyonu</strong><br>
                <small>Aynı müşteri için birden fazla oda rezervasyonu oluşturmak ister misiniz?</small><br>
                <a href="rezervasyon-ekle-multi.php" class="btn btn-sm btn-primary mt-2">
                    <i class="fas fa-hotel me-1"></i>Çoklu Oda Rezervasyon Sayfasına Git
                </a>
            </div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>

                <form method="POST" id="hizliRezervasyonForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                        <!-- Ana Form Bölümleri -->
                <div class="row">
                    <!-- Müşteri Bilgileri -->
                    <div class="col-md-6">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-user"></i>
                                Müşteri Bilgileri
                            </h4>
                            
                            <div class="row">
                        <!-- TC Kimlik No -->
                                            <div class="col-12 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">TC Kimlik No <span class="text-danger">*</span></label>
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
                            <input type="hidden" name="tc_kimlik" id="tc_kimlik" required>
                                                <div class="invalid-feedback">TC kimlik numarası 11 haneli olmalıdır.</div>
                                                <div class="valid-feedback" id="tc-success-message" style="display: none;">
                                                    <i class="fas fa-check-circle me-1"></i>Müşteri bilgileri bulundu ve dolduruldu!
                                                </div>
                                                <small class="form-text text-muted">11 haneli TC kimlik numarası girildiğinde müşteri bilgileri otomatik doldurulacaktır</small>
                                            </div>
                                            
                        <!-- Ad ve Soyad -->
                                            <div class="col-md-6 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">Ad <span class="text-danger">*</span></label>
                            <input type="text" name="ad" id="musteri_ad" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">Soyad <span class="text-danger">*</span></label>
                            <input type="text" name="soyad" id="musteri_soyad" class="form-control" required>
                                            </div>
                                            
                        <!-- Telefon ve E-posta -->
                                            <div class="col-md-6 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" name="telefon" id="musteri_telefon" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">E-posta <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="musteri_email" class="form-control" required>
                                            </div>
                                            
                        <!-- Adres -->
                                            <div class="col-12 mb-0">
                                                <label class="form-label fw-bold" style="font-size: 0.8rem;">Adres</label>
                            <textarea name="adres" id="musteri_adres" class="form-control" rows="1"></textarea>
                                            </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rezervasyon Bilgileri -->
                    <div class="col-md-6">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-calendar"></i>
                                Rezervasyon Bilgileri
                            </h4>
                            
                            <div class="row">
                        <!-- Tarih Bilgileri -->
                                            <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Giriş Tarihi <span class="text-danger">*</span></label>
                            <input type="date" name="giris_tarihi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Çıkış Tarihi <span class="text-danger">*</span></label>
                            <input type="date" name="cikis_tarihi" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                        </div>
                                        
                        <!-- Oda Bilgileri -->
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Oda Tipi <span class="text-danger">*</span></label>
                                            <select name="oda_tipi_id" class="form-select" required id="oda_tipi_select">
                                <option value="">Oda tipi seçiniz...</option>
                                <?php foreach ($oda_tipleri as $tip): ?>
                                <option value="<?= $tip['id'] ?>" data-price="<?= $tip['guncel_fiyat'] ?>">
                                    <?= htmlspecialchars($tip['oda_tipi_adi']) ?> - <?= number_format($tip['guncel_fiyat'], 0, ',', '.') ?>₺/gece
                                                        </option>
                                                    <?php endforeach; ?>
                                            </select>
                                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Oda Numarası <span class="text-danger">*</span></label>
                                            <select name="oda_numarasi_id" class="form-select" required id="oda_numarasi_select">
                                                <option value="">Önce oda tipi seçiniz</option>
                                            </select>
                                        </div>
                        
                        <!-- Misafir Sayıları -->
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Yetişkin Sayısı <span class="text-danger">*</span></label>
                            <select name="yetiskin_sayisi" class="form-select" required id="yetiskin_sayisi">
                                                <option value="1">1 Yetişkin</option>
                                                <option value="2" selected>2 Yetişkin</option>
                                                <option value="3">3 Yetişkin</option>
                                                <option value="4">4 Yetişkin</option>
                                            </select>
                                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Çocuk Sayısı</label>
                            <select name="cocuk_sayisi" class="form-select" id="cocuk_sayisi">
                                                <option value="0" selected>Çocuk Yok</option>
                                                <option value="1">1 Çocuk</option>
                                                <option value="2">2 Çocuk</option>
                                                <option value="3">3 Çocuk</option>
                                            </select>
                                        </div>
                        
                        <!-- Çocuk Yaşları -->
                        <div class="col-12" id="cocuk_yaslari_container" style="display: none;">
                            <h6 class="fw-bold mb-1" style="font-size: 0.8rem;">Çocuk Yaşları <span class="text-danger">*</span></h6>
                            <div id="cocuk_yaslari_form"></div>
                        </div>
                        
                        <!-- Misafir Detayları -->
                        <div class="col-12" id="misafir_detaylari_container" style="display: none; background: rgba(248, 249, 250, 0.9); border: 1px solid #ddd; border-radius: 6px; margin-top: 8px; max-height: 200px; overflow-y: auto;">
                            <div class="p-2">
                                <h6 class="fw-bold mb-1" style="font-size: 0.8rem;">Misafir Detayları</h6>
                                <div id="misafir_detaylari_form"></div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alt Bölümler -->
                <div class="row" style="margin-top: 15px;">
                    <!-- Ödeme Bilgileri -->
                    <div class="col-md-6">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-credit-card"></i>
                                Ödeme Bilgileri
                            </h4>
                            
                            <div class="row">
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Ödeme Yöntemi <span class="text-danger">*</span></label>
                            <select name="odeme_yontemi" class="form-select" required>
                                <option value="">Ödeme yöntemi seçiniz...</option>
                                                <option value="nakit">Nakit</option>
                                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="havale">Havale</option>
                                <option value="cek">Çek</option>
                                            </select>
                                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="form-label fw-bold" style="font-size: 0.8rem;">Ödeme Miktarı (₺)</label>
                            <input type="number" name="odeme_miktari" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>

                        <!-- Fiyat Hesaplama -->
                        <div class="price-display">
                            <h3 id="toplam-fiyat">0₺</h3>
                            <p id="fiyat-detay">Gece sayısı ve oda tipi seçiniz</p>
                        </div>
                        </div>
                    </div>
                    
                    <!-- Notlar -->
                    <div class="col-md-6">
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-sticky-note"></i>
                                Notlar
                            </h4>
                            <div class="row">
                                <div class="col-12 mb-0">
                                    <textarea name="notlar" class="form-control" rows="2" placeholder="Rezervasyon ile ilgili notlar..."></textarea>
                                </div>
                            </div>
                        </div>
 <!-- Form Butonları -->
                <div class="row" style="margin-top: 8px;">
                    <div class="col-12 text-center">
			<br>
                        <button type="submit" name="hizli_rezervasyon_submit" class="btn btn-primary me-2" style="padding: 6px 12px; font-size: 0.8rem;">
                            <i class="fas fa-save"></i> Rezervasyon Oluştur
                            </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearForm()" style="padding: 6px 12px; font-size: 0.8rem;">
                            <i class="fas fa-eraser"></i> Formu Temizle
                        </button>
                    </div>
                </div>
                    </div>
                </div>
		

                <!-- Otomatik Check-in Seçeneği -->
                <div class="row" id="otomatik_checkin_container" style="display: none; margin-top: 15px;">
                    <div class="col-12">
                        <div class="form-section" style="background: rgba(40, 167, 69, 0.15); border-left: 4px solid #28a745; border-radius: 8px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);">
                            <h4 class="section-title" style="color: #28a745; font-size: 1rem;">
                                <i class="fas fa-check-circle"></i>
                                Otomatik Check-in Seçeneği
                            </h4>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="otomatik_checkin" id="otomatik_checkin" value="1" style="transform: scale(1.2);">
                                        <label class="form-check-label" for="otomatik_checkin" style="font-size: 0.9rem;">
                                            <strong>Rezervasyonu otomatik check-in yap</strong>
                                            <br><small class="text-muted">Giriş tarihi bugün olduğu için rezervasyon otomatik olarak check-in durumuna alınacak ve oda dolu olarak işaretlenecektir.</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

               
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
        // TC Kimlik numarası yönetimi
document.addEventListener('DOMContentLoaded', function() {
            const tcDigits = document.querySelectorAll('.tc-digit');
            const tcInput = document.getElementById('tc_kimlik');
            
            tcDigits.forEach((digit, index) => {
                digit.addEventListener('input', function(e) {
                    const value = e.target.value;
                    
                    // Sadece rakam kabul et
                    if (!/^\d$/.test(value)) {
                        e.target.value = '';
            return;
        }
            
                    // Sonraki input'a geç
                    if (value && index < tcDigits.length - 1) {
                        tcDigits[index + 1].focus();
                    }
                    
                    // TC kimlik numarasını güncelle
                    updateTCKimlik();
                    
                    // 11 haneli olduğunda müşteri ara
                    if (getTCKimlik().length === 11) {
                        searchCustomer();
                    }
                });
                
                digit.addEventListener('keydown', function(e) {
                    // Backspace ile önceki input'a geç
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        tcDigits[index - 1].focus();
        }
    });
});
            
            function updateTCKimlik() {
                const tc = getTCKimlik();
                tcInput.value = tc;
                
                // Validasyon
                if (tc.length === 11) {
                    tcInput.classList.remove('is-invalid');
                    tcInput.classList.add('is-valid');
                } else {
                    tcInput.classList.remove('is-valid');
                    tcInput.classList.add('is-invalid');
                }
            }
            
            function getTCKimlik() {
                return Array.from(tcDigits).map(digit => digit.value).join('');
            }
            
            function searchCustomer() {
                const tc = getTCKimlik();
                if (tc.length !== 11) return;
                
                // AJAX ile müşteri ara
                fetch('ajax/check-customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                    body: 'tc_kimlik=' + encodeURIComponent(tc)
                })
                .then(response => response.json())
            .then(data => {
                if (data.success && data.customer) {
                    fillCustomerData(data.customer);
                        document.getElementById('tc-success-message').style.display = 'block';
                } else {
                    clearCustomerFields();
                        document.getElementById('tc-success-message').style.display = 'none';
                }
            })
            .catch(error => {
                    console.error('Müşteri arama hatası:', error);
            });
        }

        function fillCustomerData(customer) {
            document.getElementById('musteri_ad').value = customer.ad || '';
            document.getElementById('musteri_soyad').value = customer.soyad || '';
            document.getElementById('musteri_email').value = customer.email || '';
            document.getElementById('musteri_telefon').value = customer.telefon || '';
        document.getElementById('musteri_adres').value = customer.adres || '';
        }

        function clearCustomerFields() {
            document.getElementById('musteri_ad').value = '';
            document.getElementById('musteri_soyad').value = '';
            document.getElementById('musteri_email').value = '';
            document.getElementById('musteri_telefon').value = '';
        document.getElementById('musteri_adres').value = '';
            }
            
            // Oda tipi değiştiğinde oda numaralarını yükle
            document.getElementById('oda_tipi_select').addEventListener('change', function() {
                const odaTipiId = this.value;
                const odaSelect = document.getElementById('oda_numarasi_select');
                
                if (odaTipiId) {
                    const girisTarihi = document.querySelector('input[name="giris_tarihi"]').value;
                    const cikisTarihi = document.querySelector('input[name="cikis_tarihi"]').value;
                    
                    if (girisTarihi && cikisTarihi) {
                        // AJAX ile oda numaralarını yükle
                        fetch('ajax/get_musait_odalar.php?t=' + Date.now(), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `oda_tipi_id=${encodeURIComponent(odaTipiId)}&giris_tarihi=${encodeURIComponent(girisTarihi)}&cikis_tarihi=${encodeURIComponent(cikisTarihi)}&csrf_token=${encodeURIComponent('<?= $_SESSION['csrf_token'] ?>')}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            odaSelect.innerHTML = '<option value="">Oda seçiniz...</option>';
                            if (data.success && data.odalar) {
                                data.odalar.forEach(oda => {
                                    const option = document.createElement('option');
                                    option.value = oda.id;
                                    option.textContent = oda.oda_numarasi;
                                    odaSelect.appendChild(option);
                                });
                            } else {
                                odaSelect.innerHTML = '<option value="">Müsait oda bulunamadı</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Oda yükleme hatası:', error);
                            odaSelect.innerHTML = '<option value="">Hata oluştu</option>';
                        });
        } else {
                        odaSelect.innerHTML = '<option value="">Önce tarihleri seçiniz</option>';
                    }
                } else {
                    odaSelect.innerHTML = '<option value="">Önce oda tipi seçiniz</option>';
                }
                
                updatePrice();
            });
            
            // Misafir sayıları değiştiğinde detay formunu güncelle ve fiyat hesapla
            document.getElementById('yetiskin_sayisi').addEventListener('change', function() {
                generateCocukYaslari();
                generateMisafirDetaylari();
                updatePrice();
            });
            document.getElementById('cocuk_sayisi').addEventListener('change', function() {
                generateCocukYaslari();
                generateMisafirDetaylari();
                updatePrice();
            });
            
            // Çocuk yaşları formunu oluştur
            function generateCocukYaslari() {
                const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
                const container = document.getElementById('cocuk_yaslari_container');
                const form = document.getElementById('cocuk_yaslari_form');
                
                if (cocukSayisi > 0) {
                    container.style.display = 'block';
                    
                    let html = '<div class="row">';
                    for (let i = 0; i < cocukSayisi; i++) {
                        html += `
                            <div class="col-md-3 mb-0">
                                <label class="form-label" style="font-size: 0.75rem;">${i + 1}. Çocuk Yaşı *</label>
                                <select class="form-select" name="cocuk_yaslari[]" required style="font-size: 0.8rem; padding: 2px 6px;" onchange="updatePrice()">
                                    <option value="">Yaş seçin</option>
                                    <option value="0">0 yaş</option>
                                    <option value="1">1 yaş</option>
                                    <option value="2">2 yaş</option>
                                    <option value="3">3 yaş</option>
                                    <option value="4">4 yaş</option>
                                    <option value="5">5 yaş</option>
                                    <option value="6">6 yaş</option>
                                    <option value="7">7 yaş</option>
                                    <option value="8">8 yaş</option>
                                    <option value="9">9 yaş</option>
                                    <option value="10">10 yaş</option>
                                    <option value="11">11 yaş</option>
                                    <option value="12">12 yaş</option>
                                    <option value="13">13 yaş</option>
                                    <option value="14">14 yaş</option>
                                    <option value="15">15 yaş</option>
                                    <option value="16">16 yaş</option>
                                    <option value="17">17 yaş</option>
                                </select>
                            </div>
                        `;
                    }
                    html += '</div>';
                    form.innerHTML = html;
                } else {
                    container.style.display = 'none';
                    form.innerHTML = '';
                }
            }
            
            function generateMisafirDetaylari() {
                const yetiskinSayisi = parseInt(document.getElementById('yetiskin_sayisi').value);
                const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
                const container = document.getElementById('misafir_detaylari_container');
                const form = document.getElementById('misafir_detaylari_form');
                
                if (yetiskinSayisi > 0 || cocukSayisi > 0) {
                    container.style.display = 'block';
                    
                    let html = '';
                    
                    // Yetişkin detayları
                    for (let i = 0; i < yetiskinSayisi; i++) {
                        html += `
                            <div class="card mb-2" style="font-size: 0.8rem;">
                                <div class="card-header p-2" style="background: #f8f9fa;">
                                    <h6 class="mb-0" style="font-size: 0.8rem;">${i + 1}. Yetişkin</h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Ad *</label>
                                            <input type="text" class="form-control" name="yetiskin_ad_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Soyad *</label>
                                            <input type="text" class="form-control" name="yetiskin_soyad_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Cinsiyet *</label>
                                            <select class="form-select" name="yetiskin_cinsiyet_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                                <option value="">Seçin</option>
                                                <option value="erkek">Erkek</option>
                                                <option value="kadın">Kadın</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">TC Kimlik No</label>
                                            <input type="text" class="form-control" name="yetiskin_tc_${i}" maxlength="11" style="font-size: 0.8rem; padding: 2px 6px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Çocuk detayları - yaşları otomatik taşı
                    const cocukYaslari = [];
                    const cocukYasSelects = document.querySelectorAll('select[name="cocuk_yaslari[]"]');
                    cocukYasSelects.forEach(select => {
                        if (select.value) {
                            cocukYaslari.push(parseInt(select.value));
                        }
                    });
                    
                    for (let i = 0; i < cocukSayisi; i++) {
                        const cocukYasi = cocukYaslari[i] || 0;
                        html += `
                            <div class="card mb-2" style="font-size: 0.8rem;">
                                <div class="card-header p-2" style="background: #f8f9fa;">
                                    <h6 class="mb-0" style="font-size: 0.8rem;">${i + 1}. Çocuk (${cocukYasi} yaş)</h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Ad *</label>
                                            <input type="text" class="form-control" name="cocuk_ad_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Soyad *</label>
                                            <input type="text" class="form-control" name="cocuk_soyad_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Yaş</label>
                                            <input type="number" class="form-control" name="cocuk_yas_${i}" value="${cocukYasi}" min="0" max="17" readonly style="font-size: 0.8rem; padding: 2px 6px; background-color: #f8f9fa;">
                                        </div>
                                        <div class="col-md-3 mb-0">
                                            <label class="form-label" style="font-size: 0.75rem;">Cinsiyet *</label>
                                            <select class="form-select" name="cocuk_cinsiyet_${i}" required style="font-size: 0.8rem; padding: 2px 6px;">
                                                <option value="">Seçin</option>
                                                <option value="erkek">Erkek</option>
                                                <option value="kız">Kız</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    form.innerHTML = html;
                    
                    // Çocuk yaş inputlarına event listener ekle
                    const cocukYasInputs = form.querySelectorAll('input[name^="cocuk_yas_"]');
                    cocukYasInputs.forEach(input => {
                        input.addEventListener('change', updatePrice);
                    });
                } else {
                    container.style.display = 'none';
                    form.innerHTML = '';
                }
            }
            
            // Çocuk yaşları değiştiğinde misafir detaylarını güncelle
            document.addEventListener('change', function(e) {
                if (e.target.name === 'cocuk_yaslari[]') {
                    generateMisafirDetaylari();
                    updatePrice();
                }
            });
            
            // Fiyat hesaplama - AJAX ile detaylı hesaplama
            function updatePrice() {
                const odaTipiSelect = document.getElementById('oda_tipi_select');
                const selectedOption = odaTipiSelect.options[odaTipiSelect.selectedIndex];
                const girisTarihi = document.querySelector('input[name="giris_tarihi"]').value;
                const cikisTarihi = document.querySelector('input[name="cikis_tarihi"]').value;
                const yetiskinSayisi = document.getElementById('yetiskin_sayisi').value;
                const cocukSayisi = document.getElementById('cocuk_sayisi').value;
                
                if (selectedOption.value && girisTarihi && cikisTarihi) {
                    // Çocuk yaşlarını topla
                    const cocukYaslari = [];
                    const cocukYasInputs = document.querySelectorAll('input[name^="cocuk_yas_"]');
                    cocukYasInputs.forEach(input => {
                        if (input.value) {
                            cocukYaslari.push(parseInt(input.value));
                        }
                    });
                    
                    // Eğer çocuk sayısı var ama yaş bilgisi yoksa, varsayılan yaş ekle
                    if (cocukSayisi > 0 && cocukYaslari.length === 0) {
                        for (let i = 0; i < cocukSayisi; i++) {
                            cocukYaslari.push(5); // Varsayılan yaş
                        }
                    }
                    
                    // AJAX ile fiyat hesapla
                    const formData = new FormData();
                    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                    formData.append('oda_tipi_id', selectedOption.value);
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
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const priceData = data.data;
                            document.getElementById('toplam-fiyat').textContent = priceData.total_price.toLocaleString('tr-TR') + '₺';
                            document.getElementById('fiyat-detay').textContent = `${priceData.nights} gece - Ortalama: ${priceData.average_price.toLocaleString('tr-TR')}₺/gece`;
                            
                            // Minimum yetişkin şartı bilgisini göster
                            if (priceData.minimum_adult_requirement && !priceData.minimum_adult_requirement_met) {
                                const warningDiv = document.createElement('div');
                                warningDiv.className = 'alert alert-warning mt-2';
                                warningDiv.innerHTML = `
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Dikkat:</strong> Bu oda tipi için minimum ${priceData.minimum_adult_requirement} yetişkin şartı vardır. 
                                    Ücretsiz çocuk hakkı kullanılamaz, tüm çocuklar ücretli olacaktır.
                                `;
                                document.querySelector('.price-display').appendChild(warningDiv);
                            }
                        } else {
                            // Hata durumunda basit hesaplama
                            const birimFiyat = parseFloat(selectedOption.dataset.price) || 0;
                            const giris = new Date(girisTarihi);
                            const cikis = new Date(cikisTarihi);
                            const geceSayisi = Math.ceil((cikis - giris) / (1000 * 60 * 60 * 24));
                            const toplamFiyat = birimFiyat * geceSayisi;
                            
                            document.getElementById('toplam-fiyat').textContent = toplamFiyat.toLocaleString('tr-TR') + '₺';
                            document.getElementById('fiyat-detay').textContent = `${geceSayisi} gece × ${birimFiyat.toLocaleString('tr-TR')}₺ (Basit hesaplama)`;
                        }
                    })
                    .catch(error => {
                        console.error('Fiyat hesaplama hatası:', error);
                        // Hata durumunda basit hesaplama
                        const birimFiyat = parseFloat(selectedOption.dataset.price) || 0;
                        const giris = new Date(girisTarihi);
                        const cikis = new Date(cikisTarihi);
                        const geceSayisi = Math.ceil((cikis - giris) / (1000 * 60 * 60 * 24));
                        const toplamFiyat = birimFiyat * geceSayisi;
                        
                        document.getElementById('toplam-fiyat').textContent = toplamFiyat.toLocaleString('tr-TR') + '₺';
                        document.getElementById('fiyat-detay').textContent = `${geceSayisi} gece × ${birimFiyat.toLocaleString('tr-TR')}₺ (Basit hesaplama)`;
                    });
                } else {
                    document.getElementById('toplam-fiyat').textContent = '0₺';
                    document.getElementById('fiyat-detay').textContent = 'Gece sayısı ve oda tipi seçiniz';
                }
            }
            
            // Tarih değişikliklerinde fiyat güncelle ve oda numaralarını yenile
            document.querySelector('input[name="giris_tarihi"]').addEventListener('change', function() {
                updateRoomTypePrices();
                updatePrice();
                updateOdaNumaralari();
                checkOtomatikCheckin();
            });
            document.querySelector('input[name="cikis_tarihi"]').addEventListener('change', function() {
                updateRoomTypePrices();
                updatePrice();
                updateOdaNumaralari();
            });
            
            // Otomatik check-in kontrolü
            function checkOtomatikCheckin() {
                const girisTarihi = document.querySelector('input[name="giris_tarihi"]').value;
                const bugun = new Date().toISOString().split('T')[0];
                const otomatikCheckinContainer = document.getElementById('otomatik_checkin_container');
                
                console.log('Giriş tarihi:', girisTarihi);
                console.log('Bugün:', bugun);
                console.log('Eşit mi?', girisTarihi === bugun);
                
                if (girisTarihi === bugun) {
                    otomatikCheckinContainer.style.display = 'block';
                    console.log('Otomatik check-in seçeneği gösteriliyor');
                } else {
                    otomatikCheckinContainer.style.display = 'none';
                    document.getElementById('otomatik_checkin').checked = false;
                    console.log('Otomatik check-in seçeneği gizleniyor');
                }
            }
            
            function updateOdaNumaralari() {
                const odaTipiId = document.getElementById('oda_tipi_select').value;
                if (odaTipiId) {
                    document.getElementById('oda_tipi_select').dispatchEvent(new Event('change'));
                }
            }
            
            // Form temizleme
            window.clearForm = function() {
                document.getElementById('hizliRezervasyonForm').reset();
                tcDigits.forEach(digit => digit.value = '');
                tcInput.value = '';
                clearCustomerFields();
                document.getElementById('tc-success-message').style.display = 'none';
                document.getElementById('cocuk_yaslari_container').style.display = 'none';
                document.getElementById('cocuk_yaslari_form').innerHTML = '';
                document.getElementById('misafir_detaylari_container').style.display = 'none';
                document.getElementById('misafir_detaylari_form').innerHTML = '';
                document.getElementById('otomatik_checkin_container').style.display = 'none';
                document.getElementById('otomatik_checkin').checked = false;
                document.getElementById('oda_numarasi_select').innerHTML = '<option value="">Önce oda tipi seçiniz</option>';
                updatePrice();
            };
            
            // Sayfa yüklendiğinde misafir detaylarını kontrol et
            const yetiskinSayisi = parseInt(document.getElementById('yetiskin_sayisi').value);
            const cocukSayisi = parseInt(document.getElementById('cocuk_sayisi').value);
            
            if (yetiskinSayisi > 0 || cocukSayisi > 0) {
                generateCocukYaslari();
                generateMisafirDetaylari();
            }
            
            // Sayfa yüklendiğinde otomatik check-in kontrolü
            checkOtomatikCheckin();
            
            // Giriş tarihi input'una da event listener ekle
            document.querySelector('input[name="giris_tarihi"]').addEventListener('input', function() {
                checkOtomatikCheckin();
            });
            
            // Canlı saat fonksiyonu
            function updateLiveTime() {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                
                const timeString = `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
                document.getElementById('live-time').textContent = timeString;
            }
            
            // Oda tipi fiyatlarını güncelle (tarih aralığına göre ortalama)
            function updateRoomTypePrices() {
                const girisTarihi = document.querySelector('input[name="giris_tarihi"]').value;
                const cikisTarihi = document.querySelector('input[name="cikis_tarihi"]').value;
                
                if (!girisTarihi || !cikisTarihi) {
                    return;
                }
                
                // AJAX ile ortalama fiyatları al
                fetch('../ajax/get-room-type-prices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `giris_tarihi=${girisTarihi}&cikis_tarihi=${cikisTarihi}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Oda tipi dropdown'ındaki fiyatları güncelle
                        const odaTipiSelect = document.getElementById('oda_tipi_select');
                        const options = odaTipiSelect.querySelectorAll('option');
                        
                        data.data.forEach(odaTipi => {
                            options.forEach(option => {
                                if (option.value == odaTipi.id) {
                                    const formattedPrice = new Intl.NumberFormat('tr-TR').format(odaTipi.ortalama_fiyat);
                                    option.textContent = `${odaTipi.oda_tipi_adi} - ${formattedPrice}₺/gece`;
                                    option.setAttribute('data-price', odaTipi.ortalama_fiyat);
                                    
                                    // Eğer farklı günlük fiyatlar varsa bilgi ekle
                                    if (odaTipi.gunluk_fiyatlar.length > 1) {
                                        const uniquePrices = [...new Set(odaTipi.gunluk_fiyatlar)];
                                        if (uniquePrices.length > 1) {
                                            const minPrice = Math.min(...uniquePrices);
                                            const maxPrice = Math.max(...uniquePrices);
                                            option.textContent += ` (${new Intl.NumberFormat('tr-TR').format(minPrice)}-${new Intl.NumberFormat('tr-TR').format(maxPrice)}₺ arası)`;
                                        }
                                    }
                                }
                            });
                        });
                    }
                })
                .catch(error => {
                    console.error('Oda tipi fiyatları güncellenirken hata:', error);
                });
            }
            
            // Canlı saati başlat
            updateLiveTime(); // İlk güncelleme
            setInterval(updateLiveTime, 1000); // Her saniye güncelle
    });
    </script>
</body>
</html>
