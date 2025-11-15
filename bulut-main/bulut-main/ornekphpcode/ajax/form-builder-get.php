<?php
/**
 * Form Builder - Form Bilgilerini Getir
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('form_builder_view')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

$formId = $_GET['form_id'] ?? null;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID gerekli!']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM custom_forms WHERE id = ?");
    $stmt->execute([$formId]);
    
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        echo json_encode(['success' => false, 'message' => 'Form bulunamadı!']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $form
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}


