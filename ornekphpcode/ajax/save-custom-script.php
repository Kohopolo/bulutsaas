<?php
/**
 * Özel Script Kaydet/Güncelle
 */

require_once '../../includes/session_security.php';
require_once '../../includes/functions.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';
require_once '../../includes/ScriptManager.php';
require_once '../csrf_protection.php';

startSecureSession();

header('Content-Type: application/json');

// Yetki kontrolü
if (!checkAdmin() || !hasDetailedPermission('script_yonetimi_duzenle')) {
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

// Gerekli alanları kontrol et
if (empty($data['script_name']) || empty($data['script_code'])) {
    echo json_encode(['success' => false, 'error' => 'Script adı ve kodu gereklidir']);
    exit;
}

try {
    $scriptManager = new ScriptManager($pdo);
    
    $scriptData = [
        'script_name' => $data['script_name'],
        'script_description' => $data['script_description'] ?? '',
        'script_type' => $data['script_type'] ?? 'other',
        'script_code' => $data['script_code'],
        'position' => $data['position'] ?? 'head',
        'load_async' => $data['load_async'] ?? 0,
        'load_defer' => $data['load_defer'] ?? 0,
        'priority' => $data['priority'] ?? 50,
        'load_on_pages' => $data['load_on_pages'] ?? '',
        'exclude_pages' => $data['exclude_pages'] ?? '',
        'requires_consent' => $data['requires_consent'] ?? 0,
        'consent_category' => $data['consent_category'] ?? 'analytics',
        'load_only_frontend' => 1
    ];
    
    // Güncelleme mi yoksa yeni kayıt mı?
    if (!empty($data['script_id'])) {
        $result = $scriptManager->updateCustomScript($data['script_id'], $scriptData, $_SESSION['user_id']);
    } else {
        $scriptData['is_active'] = 1;
        $result = $scriptManager->addCustomScript($scriptData, $_SESSION['user_id']);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Custom script kaydetme hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Sistem hatası: ' . $e->getMessage()]);
}



