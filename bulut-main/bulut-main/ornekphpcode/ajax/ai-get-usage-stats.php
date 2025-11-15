<?php
/**
 * AI Kullanım İstatistiklerini Getir
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('ai_usage_stats_view')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            us.*,
            p.provider_label
        FROM ai_usage_stats us
        LEFT JOIN ai_providers p ON us.provider_id = p.id
        ORDER BY us.created_at DESC
        LIMIT 20
    ");
    
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}



