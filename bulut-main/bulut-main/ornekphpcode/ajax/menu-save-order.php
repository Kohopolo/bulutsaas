<?php
/**
 * Menü sıralamasını kaydet
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

$items = $_POST['items'] ?? [];

if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz sıralama verisi!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE menu_items SET menu_order = ? WHERE id = ?");
    
    foreach ($items as $item) {
        $menuId = (int)($item['id'] ?? 0);
        $order = (int)($item['order'] ?? 0);
        
        if ($menuId > 0 && $order > 0) {
            $stmt->execute([$order, $menuId]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Menü sıralaması kaydedildi!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

