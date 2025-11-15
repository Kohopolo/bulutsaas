<?php
/**
 * AI İstatistiklerini Getir
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
    // Aktif provider sayısı
    $stmt = $pdo->query("SELECT COUNT(*) FROM ai_providers WHERE is_active = 1");
    $activeProviders = $stmt->fetchColumn();
    
    // Toplam istek sayısı
    $stmt = $pdo->query("SELECT COUNT(*) FROM ai_usage_stats");
    $totalRequests = $stmt->fetchColumn();
    
    // Toplam token
    $stmt = $pdo->query("SELECT SUM(total_tokens) FROM ai_usage_stats");
    $totalTokens = $stmt->fetchColumn() ?? 0;
    
    // Ortalama yanıt süresi
    $stmt = $pdo->query("SELECT AVG(response_time_ms) FROM ai_usage_stats WHERE success = 1");
    $avgResponseTime = round($stmt->fetchColumn() ?? 0);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'active_providers' => $activeProviders,
            'total_requests' => $totalRequests,
            'total_tokens' => $totalTokens,
            'avg_response_time' => $avgResponseTime
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}


