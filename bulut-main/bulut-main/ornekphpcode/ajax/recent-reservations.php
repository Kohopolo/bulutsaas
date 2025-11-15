<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rezervasyon görüntüleme yetkiniz bulunmamaktadır']);
    exit;
}

try {
    $limit = $_GET['limit'] ?? 10;
    $limit = min($limit, 50); // Maksimum 50 kayıt
    
    // Son rezervasyonları getir
    $son_rezervasyonlar = fetchAll("
        SELECT r.*, 
               ot.oda_tipi_adi,
               onr.oda_no,
               COALESCE(r.musteri_adi, '') as musteri_adi,
               COALESCE(r.musteri_soyadi, '') as musteri_soyadi,
               COALESCE(r.rezervasyon_kodu, CONCAT('RZ', r.id)) as rezervasyon_kodu,
               r.toplam_tutar,
               r.giris_tarihi,
               r.cikis_tarihi,
               r.durum,
               r.olusturma_tarihi
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        ORDER BY r.olusturma_tarihi DESC
        LIMIT ?
    ", [$limit]);
    
    // Verileri formatla
    $formatted_reservations = [];
    foreach ($son_rezervasyonlar as $rezervasyon) {
        $formatted_reservations[] = [
            'id' => $rezervasyon['id'],
            'rezervasyon_no' => $rezervasyon['rezervasyon_kodu'],
            'musteri_adi' => $rezervasyon['musteri_adi'],
            'musteri_soyadi' => $rezervasyon['musteri_soyadi'],
            'oda_no' => $rezervasyon['oda_no'] ?? 'Atanmamış',
            'oda_tipi' => $rezervasyon['oda_tipi_adi'] ?? 'Belirtilmemiş',
            'giris_tarihi' => date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])),
            'cikis_tarihi' => date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])),
            'durum' => $rezervasyon['durum'],
            'toplam_tutar' => number_format($rezervasyon['toplam_tutar'], 0, ',', '.'),
            'olusturma_tarihi' => date('d.m.Y H:i', strtotime($rezervasyon['olusturma_tarihi'])),
            'gece_sayisi' => calculateNights($rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'])
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $formatted_reservations,
        'total' => count($formatted_reservations)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Rezervasyon verileri yüklenemedi: ' . $e->getMessage()
    ]);
}

// Gece sayısını hesapla
function calculateNights($checkin, $checkout) {
    $checkin_date = new DateTime($checkin);
    $checkout_date = new DateTime($checkout);
    $diff = $checkin_date->diff($checkout_date);
    return $diff->days;
}
?>
