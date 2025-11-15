<?php
/**
 * Özel Script Sil
 */

require_once '../../includes/session_security.php';
require_once '../../includes/functions.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';
require_once '../csrf_protection.php';

startSecureSession();

header('Content-Type: application/json');

// Yetki kontrolü
if (!checkAdmin() || !hasDetailedPermission('script_yonetimi_sil')) {
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

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz ID']);
    exit;
}

try {
    // Script bilgilerini al (log için)
    $stmt = $pdo->prepare("SELECT * FROM site_scripts WHERE id = ?");
    $stmt->execute([$id]);
    $script = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$script) {
        echo json_encode(['success' => false, 'error' => 'Script bulunamadı']);
        exit;
    }
    
    // Scripti sil
    $stmt = $pdo->prepare("DELETE FROM site_scripts WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        // Log kaydet
        $stmt = $pdo->prepare("
            INSERT INTO script_change_logs 
            (script_id, script_type, action, old_value, changed_by, ip_address, user_agent)
            VALUES (?, 'custom', 'deleted', ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id,
            json_encode($script),
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Silme başarısız']);
    }
    
} catch (PDOException $e) {
    error_log('Script silme hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası']);
}



