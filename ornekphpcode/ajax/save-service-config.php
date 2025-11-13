<?php
/**
 * Hazır Servis Ayarlarını Kaydet
 */

require_once '../../includes/session_security.php';
require_once '../../includes/functions.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';
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

$id = $data['id'] ?? 0;
$trackingId = $data['tracking_id'] ?? '';
$widgetId = $data['widget_id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE site_script_settings 
        SET tracking_id = ?,
            widget_id = ?,
            updated_at = NOW(),
            updated_by = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$trackingId, $widgetId, $_SESSION['user_id'], $id]);
    
    if ($result) {
        // Log kaydet
        $stmt = $pdo->prepare("
            INSERT INTO script_change_logs 
            (script_id, script_type, action, new_value, changed_by, ip_address, user_agent)
            VALUES (?, 'predefined', 'updated', ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id,
            json_encode(['tracking_id' => $trackingId, 'widget_id' => $widgetId]),
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Kaydetme başarısız']);
    }
    
} catch (PDOException $e) {
    error_log('Service config kaydetme hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası']);
}



