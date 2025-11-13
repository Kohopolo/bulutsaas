<?php
/**
 * Menü öğesi sil
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
    // Menü öğesini sil (cascade ile alt öğeler de silinir)
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$menuId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Menü öğesi silindi!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Menü öğesi bulunamadı!'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

