<?php
/**
 * Layout Bileşenlerini Kaydet (Header/Footer)
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

$component = $_POST['component'] ?? '';
$html = $_POST['html'] ?? '';
$css = $_POST['css'] ?? '';

if (!in_array($component, ['header', 'footer'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz bileşen!']);
    exit;
}

try {
    // Layout bileşenleri tablosunu oluştur (yoksa)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS layout_components (
            id INT AUTO_INCREMENT PRIMARY KEY,
            component_name VARCHAR(50) UNIQUE NOT NULL,
            component_html TEXT,
            component_css TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
        )
    ");

    // Bileşeni kaydet
    $stmt = $pdo->prepare("
        INSERT INTO layout_components (component_name, component_html, component_css, updated_by) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            component_html = VALUES(component_html),
            component_css = VALUES(component_css),
            updated_by = VALUES(updated_by)
    ");

    $stmt->execute([$component, $html, $css, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => ucfirst($component) . ' kaydedildi!']);

} catch (Exception $e) {
    error_log("Layout component save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Kaydetme hatası: ' . $e->getMessage()]);
}
?>

