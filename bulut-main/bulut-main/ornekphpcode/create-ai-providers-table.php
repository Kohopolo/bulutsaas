<?php
/**
 * AI Providers Tablosunu Oluştur
 */

require_once '../config/database.php';

try {
    // AI Providers tablosunu oluştur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_name VARCHAR(50) NOT NULL,
            provider_type VARCHAR(50) NOT NULL,
            api_key TEXT,
            api_url VARCHAR(255),
            is_active TINYINT(1) DEFAULT 0,
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Varsayılan provider'ları ekle
    $providers = [
        ['OpenAI', 'openai', '', 'https://api.openai.com/v1', 0, 0],
        ['Groq', 'groq', '', 'https://api.groq.com/openai/v1', 0, 0],
        ['Hugging Face', 'huggingface', '', 'https://api-inference.huggingface.co/models', 0, 0],
        ['Google Gemini', 'gemini', '', 'https://generativelanguage.googleapis.com/v1', 0, 0],
        ['Claude AI', 'claude', '', 'https://api.anthropic.com/v1', 0, 0],
        ['Ollama', 'ollama', '', 'http://localhost:11434', 0, 0]
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO ai_providers 
        (provider_name, provider_type, api_key, api_url, is_active, is_default) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($providers as $provider) {
        $stmt->execute($provider);
    }
    
    echo "✅ AI Providers tablosu başarıyla oluşturuldu!\n";
    echo "✅ Varsayılan provider'lar eklendi!\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

