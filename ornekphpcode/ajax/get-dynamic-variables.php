<?php
/**
 * Dinamik değişkenleri getir
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

// CSRF kontrolü (GET veya POST)
$csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT * FROM dynamic_variables 
        WHERE is_active = 1 
        ORDER BY variable_title ASC
    ");
    
    $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'variables' => $variables
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>
