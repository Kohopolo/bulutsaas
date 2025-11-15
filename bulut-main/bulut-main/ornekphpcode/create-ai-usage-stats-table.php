<?php
/**
 * AI Usage Stats Tablosunu Oluştur
 */

require_once '../config/database.php';

try {
    // AI Usage Stats tablosunu oluştur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_usage_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT,
            request_type VARCHAR(50),
            prompt_tokens INT DEFAULT 0,
            completion_tokens INT DEFAULT 0,
            total_tokens INT DEFAULT 0,
            response_time_ms INT DEFAULT 0,
            success TINYINT(1) DEFAULT 1,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
        )
    ");
    
    echo "✅ AI Usage Stats tablosu başarıyla oluşturuldu!\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

