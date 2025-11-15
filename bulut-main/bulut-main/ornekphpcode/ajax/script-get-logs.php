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
    $stmt = $pdo->query("
        SELECT 
            scl.*,
            COALESCE(ss.script_name, sss.service_label) as script_name,
            u.username
        FROM script_change_logs scl
        LEFT JOIN site_scripts ss ON scl.script_id = ss.id AND scl.script_type = 'custom'
        LEFT JOIN site_script_settings sss ON scl.script_id = sss.id AND scl.script_type = 'predefined'
        LEFT JOIN admin u ON scl.changed_by = u.id
        ORDER BY scl.changed_at DESC
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

