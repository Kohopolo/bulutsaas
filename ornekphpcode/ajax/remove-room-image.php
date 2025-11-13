<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('oda_tipleri_duzenle')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Oda tipi düzenleme yetkiniz bulunmamaktadır']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['room_type_id']) || !isset($input['image_index'])) {
    echo json_encode(['success' => false, 'message' => 'Gerekli parametreler eksik']);
    exit;
}

$room_type_id = intval($input['room_type_id']);
$image_index = intval($input['image_index']);

try {
    // Mevcut resimleri al
    $existing_images = getRoomTypeImages($room_type_id);
    
    if (!isset($existing_images[$image_index])) {
        echo json_encode(['success' => false, 'message' => 'Resim bulunamadı']);
        exit;
    }
    
    // Resmi dosya sisteminden sil
    $image_path = $existing_images[$image_index];
    $full_path = '../' . $image_path;
    
    if (file_exists($full_path)) {
        unlink($full_path);
    }
    
    // Resmi diziden çıkar
    array_splice($existing_images, $image_index, 1);
    
    // Güncellenmiş resim listesini kaydet
    saveRoomTypeImages($room_type_id, $existing_images);
    
    echo json_encode(['success' => true, 'message' => 'Resim başarıyla silindi']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>