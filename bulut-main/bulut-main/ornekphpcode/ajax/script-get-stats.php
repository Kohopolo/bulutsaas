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

if (!hasDetailedPermission('script_yonetimi_goruntule')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

try {
    // Aktif servisler
    $stmt = $pdo->query("SELECT COUNT(*) FROM site_script_settings WHERE is_active = 1");
    $activeServices = $stmt->fetchColumn();
    
    // Aktif özel scriptler
    $stmt = $pdo->query("SELECT COUNT(*) FROM site_scripts WHERE is_active = 1");
    $activeCustom = $stmt->fetchColumn();
    
    // Toplam özel scriptler
    $stmt = $pdo->query("SELECT COUNT(*) FROM site_scripts");
    $totalCustom = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'active_services' => $activeServices,
            'active_custom' => $activeCustom,
            'total_custom' => $totalCustom
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

