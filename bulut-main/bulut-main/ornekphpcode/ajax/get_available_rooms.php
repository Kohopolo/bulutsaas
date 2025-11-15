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

// JSON input al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['room_type_id']) || !isset($input['checkin_date']) || !isset($input['checkout_date'])) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
    exit;
}

$room_type_id = intval($input['room_type_id']);
$checkin_date = $input['checkin_date'];
$checkout_date = $input['checkout_date'];

try {
    // Bu oda tipindeki müsait odaları getir
    $sql = "SELECT odn.id, odn.oda_numarasi, odn.kat 
            FROM oda_numaralari odn 
            WHERE odn.oda_tipi_id = ? 
            AND odn.durum = 'aktif'
            AND odn.id NOT IN (
                SELECT DISTINCT r.oda_numarasi_id 
                FROM rezervasyonlar r 
                WHERE r.oda_numarasi_id IS NOT NULL 
                AND r.durum IN ('onaylandi', 'check_in')
                AND (
                    (r.giris_tarihi <= ? AND r.cikis_tarihi > ?) OR
                    (r.giris_tarihi < ? AND r.cikis_tarihi >= ?) OR
                    (r.giris_tarihi >= ? AND r.cikis_tarihi <= ?)
                )
            )
            ORDER BY odn.kat ASC, odn.oda_numarasi ASC";
    
    $rooms = fetchAll($sql, [
        $room_type_id, 
        $checkin_date, $checkin_date,
        $checkout_date, $checkout_date,
        $checkin_date, $checkout_date
    ]);
    
    echo json_encode([
        'success' => true,
        'rooms' => $rooms
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>