<?php
/**
 * AI Provider Test
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../includes/ai/AIProviderFactory.php';

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
$apiKey = $_POST['api_key'] ?? null;

if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'Provider ID gerekli!']);
    exit;
}

try {
    // Provider bilgilerini al
    $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE id = ?");
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider bulunamadı!']);
        exit;
    }
    
    // API key kullan (form'dan gelen veya db'den)
    $apiKeyToUse = $apiKey ?? $provider['api_key'];
    
    if (empty($apiKeyToUse) && $provider['provider_name'] !== 'ollama') {
        echo json_encode(['success' => false, 'message' => 'API Key gerekli!']);
        exit;
    }
    
    // AI Provider oluştur
    $aiProvider = AIProviderFactory::create($provider['provider_type'], $apiKeyToUse);
    
    // Test connection fonksiyonunu kullan
    $startTime = microtime(true);
    $testResult = $aiProvider->testConnection();
    $responseTime = round((microtime(true) - $startTime) * 1000); // ms
    
    if (!$testResult['success']) {
        throw new Exception($testResult['message']);
    }
    
    $response = $testResult['message'];
    
    // Kullanım kaydı oluştur
    $stmt = $pdo->prepare("
        INSERT INTO ai_usage_stats 
        (provider_id, request_type, prompt_tokens, completion_tokens, total_tokens, response_time_ms, success, created_at)
        VALUES (?, 'test', 0, 0, 0, ?, 1, NOW())
    ");
    $stmt->execute([$providerId, $responseTime]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test başarılı!',
        'response' => $response,
        'response_time' => $responseTime
    ]);
    
} catch (Exception $e) {
    // Hata kaydı oluştur
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ai_usage_stats 
            (provider_id, request_type, prompt_tokens, completion_tokens, total_tokens, response_time_ms, success, error_message, created_at)
            VALUES (?, 'test', 0, 0, 0, 0, 0, ?, NOW())
        ");
        $stmt->execute([$providerId, $e->getMessage()]);
    } catch (Exception $logError) {
        // Log hatası görmezden gel
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Test başarısız: ' . $e->getMessage()
    ]);
}

