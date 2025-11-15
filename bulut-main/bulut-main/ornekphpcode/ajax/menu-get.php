<?php
/**
 * Menü öğesi getir
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

$menuId = (int)($_POST['id'] ?? 0);

if ($menuId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz menü ID!']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            mi.*,
            pmr.page_id
        FROM menu_items mi
        LEFT JOIN page_menu_relations pmr ON mi.id = pmr.menu_id
        WHERE mi.id = ?
    ");
    
    $stmt->execute([$menuId]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$menu) {
        echo json_encode(['success' => false, 'message' => 'Menü öğesi bulunamadı!']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'menu' => $menu
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

