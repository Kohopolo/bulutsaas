<?php
/**
 * Sayfa Sil
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('page_builder_delete')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$pageId = $_POST['page_id'] ?? null;

if (!$pageId) {
    echo json_encode(['success' => false, 'message' => 'Sayfa ID gerekli!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Önce ilişkili kayıtları sil
    $pdo->prepare("DELETE FROM page_blocks WHERE page_id = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM page_revisions WHERE page_id = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM page_analytics WHERE page_id = ?")->execute([$pageId]);
    
    // Sayfayı sil
    $stmt = $pdo->prepare("DELETE FROM custom_pages WHERE id = ?");
    $stmt->execute([$pageId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sayfa başarıyla silindi!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}


