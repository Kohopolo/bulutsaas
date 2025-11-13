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
if (!hasDetailedPermission('dashboard_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dashboard görüntüleme yetkiniz bulunmamaktadır']);
    exit;
}

try {
    $bugun = date('Y-m-d');
    $bu_ay_baslangic = date('Y-m-01');
    $bu_ay_bitis = date('Y-m-t');
    
    // Toplam rezervasyon sayısı
    $toplam_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar")['sayi'];
    
    // Dolu oda sayısı
    $dolu_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'dolu'")['sayi'];
    
    // Aktif müşteri sayısı
    $aktif_musteri = fetchOne("SELECT COUNT(DISTINCT musteri_id) as sayi FROM rezervasyonlar WHERE durum = 'check_in'")['sayi'];
    
    // Günlük gelir
    $gunluk_gelir = fetchOne("SELECT SUM(toplam_tutar) as toplam FROM rezervasyonlar WHERE DATE(olusturma_tarihi) = ? AND durum NOT IN ('iptal')", [$bugun])['toplam'] ?? 0;
    
    // Bu ay gelir
    $bu_ay_gelir = fetchOne("SELECT SUM(toplam_tutar) as toplam FROM rezervasyonlar WHERE DATE(olusturma_tarihi) BETWEEN ? AND ? AND durum NOT IN ('iptal')", [$bu_ay_baslangic, $bu_ay_bitis])['toplam'] ?? 0;
    
    // Bugünkü check-in'ler
    $bugun_checkin = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE giris_tarihi = ? AND durum = 'onaylandi'", [$bugun])['sayi'];
    
    // Bugünkü check-out'lar
    $bugun_checkout = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE cikis_tarihi = ? AND durum = 'check_in'", [$bugun])['sayi'];
    
    // Bekleyen rezervasyonlar
    $bekleyen_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'")['sayi'];
    
    // Oda doluluk oranı
    $toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'aktif'")['sayi'];
    $doluluk_orani = $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100) : 0;
    
    // Son 7 günün rezervasyon grafiği için veri
    $son_7_gun = [];
    for ($i = 6; $i >= 0; $i--) {
        $tarih = date('Y-m-d', strtotime("-$i days"));
        $gunluk_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE DATE(olusturma_tarihi) = ?", [$tarih])['sayi'];
        $son_7_gun[] = [
            'tarih' => $tarih,
            'gun' => date('d.m', strtotime($tarih)),
            'rezervasyon' => $gunluk_rezervasyon
        ];
    }
    
    // Oda durumları
    $oda_durumlari = fetchAll("
        SELECT durum, COUNT(*) as sayi 
        FROM oda_numaralari 
        WHERE durum = 'aktif'
        GROUP BY durum
    ");
    
    $oda_durum_map = [];
    foreach ($oda_durumlari as $durum) {
        $oda_durum_map[$durum['durum']] = $durum['sayi'];
    }
    
    $response = [
        'success' => true,
        'data' => [
            'total_reservations' => $toplam_rezervasyon,
            'occupied_rooms' => $dolu_oda,
            'active_customers' => $aktif_musteri,
            'daily_revenue' => number_format($gunluk_gelir, 0, ',', '.'),
            'monthly_revenue' => number_format($bu_ay_gelir, 0, ',', '.'),
            'today_checkin' => $bugun_checkin,
            'today_checkout' => $bugun_checkout,
            'pending_reservations' => $bekleyen_rezervasyon,
            'occupancy_rate' => $doluluk_orani,
            'total_rooms' => $toplam_oda,
            'last_7_days' => $son_7_gun,
            'room_status' => $oda_durum_map
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veri yükleme hatası: ' . $e->getMessage()
    ]);
}
?>
