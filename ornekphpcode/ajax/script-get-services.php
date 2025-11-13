<?php
/**
 * Hazır servisleri getir
 */

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

// Yetki kontrolü
if (!hasDetailedPermission('script_yonetimi_goruntule')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM site_script_settings
        WHERE is_visible = 1
        ORDER BY service_category, service_label
    ");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $services,
        'count' => count($services)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database hatası: ' . $e->getMessage()
    ]);
}
?>

