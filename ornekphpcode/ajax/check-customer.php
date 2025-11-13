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
if (!hasDetailedPermission('musteri_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Müşteri görüntüleme yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF token kontrolü
if (!isset($_POST['tc_kimlik'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'TC kimlik numarası gerekli']);
    exit;
}

$tc_kimlik = preg_replace('/[^0-9]/', '', trim($_POST['tc_kimlik']));

if (strlen($tc_kimlik) !== 11) {
    echo json_encode(['success' => false, 'message' => 'TC kimlik numarası 11 haneli olmalıdır']);
    exit;
}

try {
    $customer = fetchOne("
        SELECT id, ad, soyad, telefon, email, tc_kimlik, adres 
        FROM musteriler 
        WHERE tc_kimlik = ?
    ", [$tc_kimlik]);
    
    if ($customer) {
        echo json_encode([
            'success' => true,
            'customer' => $customer
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Müşteri bulunamadı'
        ]);
    }
} catch (Exception $e) {
    error_log('Müşteri arama hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Müşteri arama sırasında hata oluştu'
    ]);
}
?>
