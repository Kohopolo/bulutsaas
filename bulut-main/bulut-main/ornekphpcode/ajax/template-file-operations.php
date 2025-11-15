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
        case 'get_file_content':
            $slug = $_POST['slug'] ?? '';
            $file = $_POST['file'] ?? '';
            
            if (empty($slug) || empty($file)) {
                throw new Exception('Template slug ve dosya adı gerekli');
            }
            
            $templatesDir = dirname(__DIR__, 2) . '/templates';
            $filePath = $templatesDir . '/' . $slug . '/' . $file;
            
            // Güvenlik kontrolü - sadece template dizini içindeki dosyalara erişim
            $realPath = realpath($filePath);
            $templateDir = realpath($templatesDir . '/' . $slug);
            
            if (!$realPath || !$templateDir || strpos($realPath, $templateDir) !== 0) {
                throw new Exception('Geçersiz dosya yolu');
            }
            
            if (!file_exists($filePath)) {
                throw new Exception('Dosya bulunamadı');
            }
            
            $content = file_get_contents($filePath);
            echo json_encode(['success' => true, 'content' => $content]);
            break;
            
        case 'save_file_content':
            $slug = $_POST['slug'] ?? '';
            $file = $_POST['file'] ?? '';
            $content = $_POST['content'] ?? '';
            
            if (empty($slug) || empty($file)) {
                throw new Exception('Template slug ve dosya adı gerekli');
            }
            
            $templatesDir = dirname(__DIR__, 2) . '/templates';
            $filePath = $templatesDir . '/' . $slug . '/' . $file;
            
            // Güvenlik kontrolü
            $realPath = realpath(dirname($filePath));
            $templateDir = realpath($templatesDir . '/' . $slug);
            
            if (!$realPath || !$templateDir || strpos($realPath, $templateDir) !== 0) {
                throw new Exception('Geçersiz dosya yolu');
            }
            
            // Dizin yoksa oluştur
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $result = file_put_contents($filePath, $content);
            if ($result === false) {
                throw new Exception('Dosya kaydedilemedi');
            }
            
            // Cache'i temizle
            $templateEngine->clearCache($slug);
            
            echo json_encode(['success' => true, 'message' => 'Dosya başarıyla kaydedildi']);
            break;
            
        case 'get_file_list':
            $slug = $_POST['slug'] ?? '';
            
            if (empty($slug)) {
                throw new Exception('Template slug gerekli');
            }
            
            $templatesDir = dirname(__DIR__, 2) . '/templates';
            $templateDir = $templatesDir . '/' . $slug;
            
            if (!is_dir($templateDir)) {
                throw new Exception('Template dizini bulunamadı');
            }
            
            $files = $this->getDirectoryFiles($templateDir, $templateDir);
            echo json_encode(['success' => true, 'files' => $files]);
            break;
            
        default:
            throw new Exception('Geçersiz işlem');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Dizindeki dosyaları listele
 */
function getDirectoryFiles($dir, $baseDir) {
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);
        
        if ($file->isFile()) {
            $files[] = [
                'path' => $relativePath,
                'name' => $file->getFilename(),
                'type' => 'file',
                'size' => $file->getSize(),
                'modified' => $file->getMTime()
            ];
        } else {
            $files[] = [
                'path' => $relativePath,
                'name' => $file->getFilename(),
                'type' => 'directory'
            ];
        }
    }
    
    return $files;
}
?>