<?php
/**
 * Provider Ayarlarını Kaydet
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('ai_settings_edit')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$providerId = $_POST['provider_id'] ?? null;
$apiKey = $_POST['api_key'] ?? '';
$isActive = isset($_POST['is_active']) ? 1 : 0;
$isDefault = isset($_POST['is_default']) ? 1 : 0;

if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'Provider ID gerekli!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Eğer varsayılan yapılıyorsa, diğerlerini kapat
    if ($isDefault) {
        $stmt = $pdo->prepare("UPDATE ai_providers SET is_default = 0 WHERE id != ?");
        $stmt->execute([$providerId]);
    }
    
    // Provider'ı güncelle
    $stmt = $pdo->prepare("
        UPDATE ai_providers 
        SET api_key = ?, 
            is_active = ?, 
            is_default = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$apiKey, $isActive, $isDefault, $providerId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Provider ayarları başarıyla kaydedildi!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}



