<?php
/**
 * Form Builder - Form Sil
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('form_builder_delete')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$formId = $_POST['form_id'] ?? null;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID gerekli!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // İlişkili gönderileri sil
    $pdo->prepare("DELETE FROM form_submissions WHERE form_id = ?")->execute([$formId]);
    
    // Formu sil
    $stmt = $pdo->prepare("DELETE FROM custom_forms WHERE id = ?");
    $stmt->execute([$formId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Form başarıyla silindi!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}


