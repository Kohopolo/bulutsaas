<?php
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/TemplateEngine.php';

// Admin kontrolü
require_once '../../includes/functions.php';
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('template_yonetimi')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Template yönetimi yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF koruması
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token hatası']);
    exit;
}

$templateEngine = new TemplateEngine($pdo);
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'activate':
            $slug = $_POST['slug'] ?? '';
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            $result = $templateEngine->setActiveTemplate($slug);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template başarıyla aktif edildi']);
            } else {
                throw new Exception('Template aktif edilemedi');
            }
            break;
            
        case 'delete':
            $slug = $_POST['slug'] ?? '';
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            // Aktif template silinmeye çalışılıyorsa engelle
            $activeTemplate = $templateEngine->getActiveTemplate();
            if ($activeTemplate && $activeTemplate['slug'] === $slug) {
                throw new Exception('Aktif template silinemez');
            }
            
            $result = $templateEngine->deleteTemplate($slug);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template başarıyla silindi']);
            } else {
                throw new Exception('Template silinemedi');
            }
            break;
            
        case 'clear_cache':
            $slug = $_POST['slug'] ?? '';
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            $result = $templateEngine->clearCache($slug);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template cache temizlendi']);
            } else {
                throw new Exception('Cache temizlenemedi');
            }
            break;
            
        case 'get_variables':
            $slug = $_POST['slug'] ?? '';
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            $variables = $templateEngine->getTemplateVariables($slug);
            echo json_encode(['success' => true, 'variables' => $variables]);
            break;
            
        case 'save_variables':
            $slug = $_POST['slug'] ?? '';
            $variables = $_POST['variables'] ?? [];
            
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            foreach ($variables as $key => $value) {
                $templateEngine->setTemplateVariable($slug, $key, $value);
            }
            
            echo json_encode(['success' => true, 'message' => 'Template değişkenleri kaydedildi']);
            break;
            
        default:
            throw new Exception('Geçersiz işlem');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>