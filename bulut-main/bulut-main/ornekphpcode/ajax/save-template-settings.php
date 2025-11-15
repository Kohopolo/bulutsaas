<?php
/**
 * Template Ayarlarını Kaydet
 */

header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$settings = $_POST['settings'] ?? '{}';
$settingsData = json_decode($settings, true);

if (!$settingsData) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ayar verisi!']);
    exit;
}

try {
    // Template ayarları tablosunu oluştur (yoksa)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS template_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Ayarları kaydet
    $stmt = $pdo->prepare("
        INSERT INTO template_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($settingsData as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    echo json_encode(['success' => true, 'message' => 'Template ayarları kaydedildi!']);

} catch (Exception $e) {
    error_log("Template settings save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Kaydetme hatası: ' . $e->getMessage()]);
}
?>

