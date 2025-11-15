<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

$response = ['success' => false];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Oturum bulunamadı.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['room_id']) || !isset($input['date'])) {
    $response['message'] = 'Oda ID veya tarih eksik.';
    echo json_encode($response);
    exit;
}

$room_id = intval($input['room_id']);
$date = $input['date'];

try {
    // Seçilen oda bilgilerini session'a kaydet
    $_SESSION['selected_room_id'] = $room_id;
    $_SESSION['selected_room_date'] = $date;
    
    // Oda numarasını da al
    $stmt = $pdo->prepare("SELECT oda_numarasi FROM oda_numaralari WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if ($room) {
        $_SESSION['selected_room_number'] = $room['oda_numarasi'];
    }
    
    $response['success'] = true;
    $response['message'] = 'Oda seçimi kaydedildi.';
    
} catch (Exception $e) {
    $response['message'] = 'Oda seçimi kaydedilirken hata oluştu: ' . $e->getMessage();
}

echo json_encode($response);
?>
