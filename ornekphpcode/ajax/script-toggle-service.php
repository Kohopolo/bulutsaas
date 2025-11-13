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
$is_active = $_POST['is_active'] ?? 0;

try {
    $stmt = $pdo->prepare("UPDATE site_script_settings SET is_active = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$is_active, $_SESSION['user_id'], $service_id]);
    
    // Log kaydet
    $logStmt = $pdo->prepare("
        INSERT INTO script_change_logs (script_id, script_type, action, changed_by, ip_address)
        VALUES (?, 'predefined', ?, ?, ?)
    ");
    $logStmt->execute([
        $service_id,
        $is_active ? 'activated' : 'deactivated',
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $is_active ? 'Servis aktif edildi!' : 'Servis pasif edildi!'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

