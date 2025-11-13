<?php
/**
 * Özel Script Detaylarını Getir
 */

require_once '../../includes/session_security.php';
require_once '../../includes/functions.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';

startSecureSession();

header('Content-Type: application/json');

// Yetki kontrolü
if (!checkAdmin() || !hasDetailedPermission('script_yonetimi_goruntule')) {
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM site_scripts WHERE id = ?");
    $stmt->execute([$id]);
    $script = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($script) {
        echo json_encode(['success' => true, 'data' => $script]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Script bulunamadı']);
    }
    
} catch (PDOException $e) {
    error_log('Script getirme hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası']);
}



