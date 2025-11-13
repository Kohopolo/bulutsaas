<?php
session_start();

// Basit veritabanı bağlantısı
try {
    $pdo = new PDO('mysql:host=localhost;dbname=otel_rezervasyon;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

header('Content-Type: application/json');

$response = ['success' => false, 'rooms' => [], 'occupiedRooms' => []];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Oturum bulunamadı.';
    echo json_encode($response);
    exit;
}

if (!isset($_GET['date'])) {
    $response['message'] = 'Tarih parametresi eksik.';
    echo json_encode($response);
    exit;
}

$date = $_GET['date'];

try {
    // Tüm odaları getir
    $stmt = $pdo->prepare("
        SELECT onum.id, onum.oda_numarasi, ot.oda_tipi_adi
        FROM oda_numaralari onum
        LEFT JOIN oda_tipleri ot ON onum.oda_tipi_id = ot.id
        ORDER BY onum.oda_numarasi
    ");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Bu tarihte dolu olan odaları getir - gelişmiş durum kontrolü
    $stmt = $pdo->prepare("
        SELECT r.oda_numarasi_id, r.musteri_adi, r.musteri_soyadi, r.durum, r.giris_tarihi, r.cikis_tarihi,
               CASE 
                   -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
                   WHEN r.durum = 'check_in' 
                        AND ? >= DATE(r.giris_tarihi) 
                        AND ? < DATE(r.cikis_tarihi) 
                        THEN 'dolu'
                   
                   -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
                   WHEN r.durum = 'check_in' 
                        AND ? = DATE(r.cikis_tarihi)
                        AND TIME(NOW()) < TIME(r.cikis_tarihi)
                        THEN 'checkout_oncesi_dolu'
                   
                   -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
                   WHEN r.durum = 'onaylandi' 
                        AND ? < DATE(r.cikis_tarihi)
                        THEN 'rezerve'
                   
                   -- Varsayılan: Normal durum
                   ELSE r.durum
               END as final_durum,
               CASE 
                   WHEN r.durum = 'check_in' 
                        AND ? = DATE(r.cikis_tarihi)
                        AND TIME(NOW()) < TIME(r.cikis_tarihi)
                        THEN 'Checkout saati yaklaşıyor'
                   
                   WHEN r.durum = 'onaylandi' 
                        AND ? < DATE(r.cikis_tarihi)
                        THEN 'Rezerve - Check-in bekliyor'
                   
                   ELSE NULL
               END as uyari_mesaji
        FROM rezervasyonlar r
        WHERE r.durum IN ('check_in', 'onaylandi') 
        AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
        ORDER BY r.oda_numarasi_id
    ");
    $stmt->execute([$date, $date, $date, $date, $date, $date, $date, $date]);
    $occupiedRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['rooms'] = $rooms;
    $response['occupiedRooms'] = $occupiedRooms;
    
} catch (Exception $e) {
    $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
}

echo json_encode($response);
?>
