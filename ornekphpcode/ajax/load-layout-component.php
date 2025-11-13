<?php
/**
 * Layout Bileşenlerini Yükle (Header/Footer)
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

$component = $_GET['component'] ?? '';

if (!in_array($component, ['header', 'footer'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz bileşen!']);
    exit;
}

try {
    // Layout bileşenini yükle
    $stmt = $pdo->prepare("SELECT component_html, component_css FROM layout_components WHERE component_name = ?");
    $stmt->execute([$component]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'html' => $result['component_html'],
            'css' => $result['component_css']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bileşen bulunamadı!']);
    }

} catch (Exception $e) {
    error_log("Layout component load error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Yükleme hatası: ' . $e->getMessage()]);
}
?>

