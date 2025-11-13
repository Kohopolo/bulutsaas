<?php
/**
 * Provider Detaylarını Getir
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('ai_settings_view')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

$providerId = $_GET['provider_id'] ?? null;

if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'Provider ID gerekli!']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE id = ?");
    $stmt->execute([$providerId]);
    
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider bulunamadı!']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $provider
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}



