<?php
/**
 * AI ile İçerik Oluştur
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';
require_once '../../includes/ai/AIProviderFactory.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasDetailedPermission('page_builder_create')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$prompt = trim($_POST['prompt'] ?? '');
$type = $_POST['type'] ?? 'page_content';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'message' => 'Prompt gerekli!']);
    exit;
}

try {
    // Varsayılan AI provider'ı al
    $stmt = $pdo->query("
        SELECT * FROM ai_providers 
        WHERE is_active = 1 AND is_default = 1 
        LIMIT 1
    ");
    
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        // Varsayılan yoksa, ilk aktif olanı al
        $stmt = $pdo->query("
            SELECT * FROM ai_providers 
            WHERE is_active = 1 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Aktif AI provider bulunamadı! Lütfen önce AI Ayarlarından bir provider yapılandırın.']);
        exit;
    }
    
    if (empty($provider['api_key']) && $provider['provider_name'] !== 'ollama') {
        echo json_encode(['success' => false, 'message' => 'AI provider için API key tanımlı değil!']);
        exit;
    }
    
    // AI Provider oluştur
    $aiProvider = AIProviderFactory::create($provider['provider_name'], $provider['api_key']);
    
    // Prompt'u hazırla (HTML içerik üretimi için)
    $systemPrompt = "Sen bir profesyonel web tasarımcı ve içerik üreticisin. Bootstrap 5 ve modern HTML/CSS kullanarak responsive, güzel ve kullanıcı dostu web içerikleri oluşturuyorsun. Sadece HTML kodu üret, açıklama yazma. Font Awesome iconları kullanabilirsin.";
    
    $fullPrompt = $systemPrompt . "\n\nKullanıcı isteği: " . $prompt . "\n\nHTML kodunu üret:";
    
    $startTime = microtime(true);
    $response = $aiProvider->generateText($fullPrompt, [
        'max_tokens' => 2000,
        'temperature' => 0.7
    ]);
    $responseTime = round((microtime(true) - $startTime) * 1000);
    
    // HTML'i temizle (markdown code block'ları varsa)
    $html = $response;
    $html = preg_replace('/```html\s*/', '', $html);
    $html = preg_replace('/```\s*$/', '', $html);
    $html = trim($html);
    
    // Kullanım kaydı oluştur
    $stmt = $pdo->prepare("
        INSERT INTO ai_usage_stats 
        (provider_id, request_type, prompt_tokens, completion_tokens, total_tokens, response_time_ms, success, created_at)
        VALUES (?, ?, 0, 0, 0, ?, 1, NOW())
    ");
    $stmt->execute([$provider['id'], $type, $responseTime]);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'provider' => $provider['provider_label'],
        'response_time' => $responseTime
    ]);
    
} catch (Exception $e) {
    // Hata kaydı oluştur
    try {
        if (isset($provider['id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO ai_usage_stats 
                (provider_id, request_type, prompt_tokens, completion_tokens, total_tokens, response_time_ms, success, error_message, created_at)
                VALUES (?, ?, 0, 0, 0, 0, 0, ?, NOW())
            ");
            $stmt->execute([$provider['id'], $type, $e->getMessage()]);
        }
    } catch (Exception $logError) {
        // Log hatası görmezden gel
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'AI hatası: ' . $e->getMessage()
    ]);
}


