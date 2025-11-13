<?php
/**
 * Erken check-out fiyat hesaplama AJAX endpoint
 */

// Güvenlik kontrolleri
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek türü']);
    exit;
}

require_once '../../includes/functions.php';
require_once '../../includes/price-functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_duzenle')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rezervasyon düzenleme yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF koruması
if (!isset($_POST['rezervasyon_id']) || !isset($_POST['yeni_cikis_tarihi']) || !isset($_POST['yeni_cikis_saati'])) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
    exit;
}

$rezervasyon_id = intval($_POST['rezervasyon_id']);
$yeni_cikis_tarihi = sanitizeString($_POST['yeni_cikis_tarihi']);
$yeni_cikis_saati = sanitizeString($_POST['yeni_cikis_saati']);

try {
    // Rezervasyon bilgilerini al
    $rezervasyon = fetchOne("SELECT * FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
    
    if (!$rezervasyon) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı']);
        exit;
    }
    
    error_log("Erken check-out hesaplama - Rezervasyon ID: $rezervasyon_id, Yeni çıkış: $yeni_cikis_tarihi $yeni_cikis_saati");
    
    // Çocuk yaşlarını çöz
    $cocuk_yaslari = [];
    if ($rezervasyon['cocuk_yaslari']) {
        $cocuk_yaslari = json_decode($rezervasyon['cocuk_yaslari'], true) ?: [];
    }
    
    // Yeni çıkış tarihini birleştir
    $yeni_cikis_datetime = $yeni_cikis_tarihi . ' ' . $yeni_cikis_saati . ':00';
    
    // Yeni fiyat hesapla
    error_log("Hesaplama parametreleri - Giriş: {$rezervasyon['giris_tarihi']}, Çıkış: $yeni_cikis_datetime, Oda Tipi: {$rezervasyon['oda_tipi_id']}, Yetişkin: {$rezervasyon['yetiskin_sayisi']}, Çocuk: {$rezervasyon['cocuk_sayisi']}");
    
    // Basit hesaplama yap (gece sayısına göre)
    $giris = new DateTime($rezervasyon['giris_tarihi']);
    $cikis = new DateTime($yeni_cikis_datetime);
    $gece_sayisi = $giris->diff($cikis)->days;
    
    // Orijinal gece sayısını hesapla
    $orijinal_giris = new DateTime($rezervasyon['giris_tarihi']);
    $orijinal_cikis = new DateTime($rezervasyon['cikis_tarihi']);
    $orijinal_gece_sayisi = $orijinal_giris->diff($orijinal_cikis)->days;
    
    // Günlük fiyatı hesapla
    $gunluk_fiyat = $orijinal_gece_sayisi > 0 ? $rezervasyon['toplam_fiyat'] / $orijinal_gece_sayisi : 0;
    $yeni_fiyat = $gece_sayisi * $gunluk_fiyat;
    
    error_log("Basit hesaplama - Orijinal gece: $orijinal_gece_sayisi, Yeni gece: $gece_sayisi, Günlük fiyat: $gunluk_fiyat, Yeni fiyat: $yeni_fiyat");
    
    error_log("Hesaplama sonucu - Yeni fiyat: $yeni_fiyat");
    
    // İade tutarını hesapla
    $hesaplanan_iade = $rezervasyon['toplam_fiyat'] - $yeni_fiyat;
    
    // Ödeme durumuna göre iade tutarını hesapla
    $odenen_tutar = $rezervasyon['odenen_tutar'] ?? 0;
    $gercek_iade_tutari = min($hesaplanan_iade, $odenen_tutar); // Ödenen tutardan fazla iade edilemez
    
    // İade oranını hesapla
    $iade_orani = $rezervasyon['toplam_fiyat'] > 0 ? round(($hesaplanan_iade / $rezervasyon['toplam_fiyat']) * 100, 1) : 0;
    
    error_log("Hesaplama sonuçları - Orijinal: {$rezervasyon['toplam_fiyat']}, Yeni: $yeni_fiyat, İade: $hesaplanan_iade, Ödenen: $odenen_tutar, Gerçek İade: $gercek_iade_tutari, Gece: $gece_sayisi, Oran: $iade_orani%");
    
    echo json_encode([
        'success' => true,
        'yeni_toplam' => number_format($yeni_fiyat, 2),
        'hesaplanan_iade' => number_format($hesaplanan_iade, 2),
        'gercek_iade_tutari' => number_format($gercek_iade_tutari, 2),
        'odenen_tutar' => number_format($odenen_tutar, 2),
        'gece_sayisi' => $gece_sayisi,
        'iade_orani' => $iade_orani
    ]);
    
} catch (Exception $e) {
    error_log("Erken check-out hesaplama hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Hesaplama hatası oluştu']);
}
?>
