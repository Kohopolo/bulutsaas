<?php
// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Oturum süreniz dolmuş!']);
    exit;
}

require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!hasDetailedPermission('script_yonetimi_duzenle')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF token hatası!']);
    exit;
}

$service_id = $_POST['service_id'] ?? 0;
$tracking_id = $_POST['tracking_id'] ?? '';
$position = $_POST['position'] ?? 'head';
$priority = $_POST['priority'] ?? 50;
$consent_category = $_POST['consent_category'] ?? 'analytics';

// Additional config (eğer varsa)
$additional_config = [];
if (isset($_POST['widget_id'])) $additional_config['widget_id'] = $_POST['widget_id'];
if (isset($_POST['welcome_message'])) $additional_config['welcome_message'] = $_POST['welcome_message'];

try {
    $stmt = $pdo->prepare("
        UPDATE site_script_settings
        SET tracking_id = ?,
            script_position = ?,
            priority = ?,
            consent_category = ?,
            additional_config = ?,
            updated_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $tracking_id,
        $position,
        $priority,
        $consent_category,
        json_encode($additional_config),
        $_SESSION['user_id'],
        $service_id
    ]);
    
    // Log kaydet
    $logStmt = $pdo->prepare("
        INSERT INTO script_change_logs (script_id, script_type, action, changed_by, ip_address)
        VALUES (?, 'predefined', 'updated', ?, ?)
    ");
    $logStmt->execute([$service_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ayarlar başarıyla kaydedildi!'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

