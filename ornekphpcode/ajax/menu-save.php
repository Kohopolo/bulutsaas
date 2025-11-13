<?php
/**
 * Menü öğesi kaydet
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? 'url';
$url = trim($_POST['url'] ?? '');
$pageId = $_POST['page_id'] ?? null;
$icon = trim($_POST['icon'] ?? '');
$order = (int)($_POST['order'] ?? 0);
$active = (int)($_POST['active'] ?? 1);
$footer = (int)($_POST['footer'] ?? 0);
$menuId = $_POST['id'] ?? null;

// Validasyon
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Menü başlığı gerekli!']);
    exit;
}

if ($type === 'url' && empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL gerekli!']);
    exit;
}

if ($type === 'page' && empty($pageId)) {
    echo json_encode(['success' => false, 'message' => 'Sayfa seçimi gerekli!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Slug oluştur
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
    $slug = trim($slug, '-');
    
    if ($menuId) {
        // Güncelleme
        $stmt = $pdo->prepare("
            UPDATE menu_items 
            SET title = ?, url = ?, slug = ?, icon = ?, menu_order = ?, is_active = ?, is_in_footer = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $url,
            $slug,
            $icon,
            $order,
            $active,
            $footer,
            $menuId
        ]);
        
        // Sayfa ilişkisini güncelle
        if ($type === 'page' && $pageId) {
            // Eski ilişkiyi sil
            $stmt = $pdo->prepare("DELETE FROM page_menu_relations WHERE menu_id = ?");
            $stmt->execute([$menuId]);
            
            // Yeni ilişkiyi ekle
            $stmt = $pdo->prepare("INSERT INTO page_menu_relations (page_id, menu_id) VALUES (?, ?)");
            $stmt->execute([$pageId, $menuId]);
        }
        
        $message = 'Menü öğesi güncellendi!';
        
    } else {
        // Yeni ekleme
        $stmt = $pdo->prepare("
            INSERT INTO menu_items 
            (title, url, slug, icon, menu_order, is_active, is_in_footer, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $title,
            $url,
            $slug,
            $icon,
            $order,
            $active,
            $footer
        ]);
        
        $menuId = $pdo->lastInsertId();
        
        // Sayfa ilişkisini ekle
        if ($type === 'page' && $pageId) {
            $stmt = $pdo->prepare("INSERT INTO page_menu_relations (page_id, menu_id) VALUES (?, ?)");
            $stmt->execute([$pageId, $menuId]);
        }
        
        $message = 'Menü öğesi eklendi!';
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'menu_id' => $menuId
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

