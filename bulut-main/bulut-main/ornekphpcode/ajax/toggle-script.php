<?php
/**
 * Script Aktif/Pasif Toggle
 */

require_once '../../includes/session_security.php';
require_once '../../includes/functions.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';
require_once '../csrf_protection.php';

startSecureSession();

header('Content-Type: application/json');

// Yetki kontrolü
if (!checkAdmin() || !hasDetailedPermission('script_yonetimi_aktif_pasif')) {
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

// POST verilerini al
$data = json_decode(file_get_contents('php://input'), true);

// CSRF token kontrolü
if (!isset($data['csrf_token']) || !validateCsrfToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz CSRF token']);
    exit;
}

$id = $data['id'] ?? 0;
$type = $data['type'] ?? 'custom'; // 'custom' or 'predefined'

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz ID']);
    exit;
}

try {
    require_once '../../includes/ScriptManager.php';
    $scriptManager = new ScriptManager($pdo);
    
    $result = $scriptManager->toggleScript($id, $type, $_SESSION['user_id']);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Toggle script hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Sistem hatası: ' . $e->getMessage()]);
}



