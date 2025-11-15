<?php
/**
 * Template İçeriği Yükle
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

if (!hasDetailedPermission('page_builder_edit')) {
    echo json_encode(['success' => false, 'message' => 'Sayfa düzenleme yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$template = $_POST['template'] ?? '';

if (empty($template)) {
    echo json_encode(['success' => false, 'message' => 'Template belirtilmedi!']);
    exit;
}

try {
    // Template'den örnek sayfa içeriği al
    $stmt = $pdo->prepare("
        SELECT page_content 
        FROM custom_pages 
        WHERE page_template = ? AND is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$template]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($page && !empty($page['page_content'])) {
        echo json_encode([
            'success' => true,
            'content' => $page['page_content'],
            'message' => 'Template içeriği yüklendi'
        ]);
    } else {
        // Template dosyasından içerik yükle
        $templatePath = "../../templates/{$template}/pages/index.html";
        
        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            echo json_encode([
                'success' => true,
                'content' => $content,
                'message' => 'Template dosyası yüklendi'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Template içeriği bulunamadı'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Template load error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Template yüklenirken hata oluştu: ' . $e->getMessage()]);
}
?>

