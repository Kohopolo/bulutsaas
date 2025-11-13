<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Sadece superadmin bu sayfaya erişebilir
if ($_SESSION['user_role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('yetki_yonetimi_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// JSON header
header('Content-Type: application/json');

try {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    // Kullanıcının yetkilerini getir
    $permissions = fetchAll("
        SELECT y.id 
        FROM kullanici_yetkiler ky 
        JOIN yetkiler y ON ky.yetki_id = y.id 
        WHERE ky.kullanici_id = ?
    ", [$user_id]);
    
    $permission_ids = array_column($permissions, 'id');
    
    echo json_encode([
        'success' => true,
        'permissions' => $permission_ids
    ]);
    
} catch (Exception $e) {
    error_log("get_user_permissions.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>