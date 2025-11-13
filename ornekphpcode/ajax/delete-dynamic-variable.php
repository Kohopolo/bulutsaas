<?php
/**
 * Dinamik değişken sil
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

$variableId = (int)($_POST['variable_id'] ?? 0);

if ($variableId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz değişken ID!']);
    exit;
}

try {
    // Değişkeni sil
    $stmt = $pdo->prepare("DELETE FROM dynamic_variables WHERE id = ?");
    $stmt->execute([$variableId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Değişken başarıyla silindi!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Değişken bulunamadı!'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

