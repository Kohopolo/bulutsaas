<?php
require_once '../config/database.php';

try {
    // API Rate Limiting Tablosu
    $sql1 = "CREATE TABLE IF NOT EXISTS `api_rate_limits` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `identifier` varchar(255) NOT NULL,
      `endpoint` varchar(100) NOT NULL,
      `ip_address` varchar(45) NOT NULL,
      `user_agent` text,
      `created_at` int(11) NOT NULL,
      PRIMARY KEY (`id`),
      KEY `identifier` (`identifier`),
      KEY `endpoint` (`endpoint`),
      KEY `ip_address` (`ip_address`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql1);
    echo "✅ api_rate_limits tablosu oluşturuldu<br>";
    
    // API Security Logs Tablosu
    $sql2 = "CREATE TABLE IF NOT EXISTS `api_security_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ip_address` varchar(45) NOT NULL,
      `user_agent` text,
      `endpoint` varchar(255) NOT NULL,
      `method` varchar(10) NOT NULL,
      `status_code` int(3) NOT NULL,
      `response_time` decimal(10,4) DEFAULT NULL,
      `request_size` int(11) DEFAULT NULL,
      `response_size` int(11) DEFAULT NULL,
      `error_message` text,
      `user_id` int(11) DEFAULT NULL,
      `api_key` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ip_address` (`ip_address`),
      KEY `endpoint` (`endpoint`),
      KEY `status_code` (`status_code`),
      KEY `created_at` (`created_at`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✅ api_security_logs tablosu oluşturuldu<br>";
    
    // API Keys Tablosu
    $sql3 = "CREATE TABLE IF NOT EXISTS `api_keys` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `key_name` varchar(100) NOT NULL,
      `api_key` varchar(255) NOT NULL,
      `secret_key` varchar(255) DEFAULT NULL,
      `user_id` int(11) DEFAULT NULL,
      `permissions` text,
      `rate_limit` int(11) DEFAULT 1000,
      `rate_window` int(11) DEFAULT 3600,
      `is_active` tinyint(1) DEFAULT '1',
      `expires_at` timestamp NULL DEFAULT NULL,
      `last_used` timestamp NULL DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `api_key` (`api_key`),
      KEY `user_id` (`user_id`),
      KEY `is_active` (`is_active`),
      KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql3);
    echo "✅ api_keys tablosu oluşturuldu<br>";
    
    // API Security Rules Tablosu
    $sql4 = "CREATE TABLE IF NOT EXISTS `api_security_rules` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `rule_name` varchar(100) NOT NULL,
      `rule_type` enum('ip_whitelist','ip_blacklist','endpoint_restriction','rate_limit','validation') NOT NULL,
      `rule_config` text NOT NULL,
      `is_active` tinyint(1) DEFAULT '1',
      `priority` int(11) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `rule_type` (`rule_type`),
      KEY `is_active` (`is_active`),
      KEY `priority` (`priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql4);
    echo "✅ api_security_rules tablosu oluşturuldu<br>";
    
    // API Audit Logs Tablosu
    $sql5 = "CREATE TABLE IF NOT EXISTS `api_audit_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `api_key_id` int(11) DEFAULT NULL,
      `action` varchar(100) NOT NULL,
      `resource` varchar(255) NOT NULL,
      `resource_id` int(11) DEFAULT NULL,
      `old_values` text,
      `new_values` text,
      `ip_address` varchar(45) NOT NULL,
      `user_agent` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `api_key_id` (`api_key_id`),
      KEY `action` (`action`),
      KEY `resource` (`resource`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql5);
    echo "✅ api_audit_logs tablosu oluşturuldu<br>";
    
    // Varsayılan API güvenlik ayarları ekle
    $defaultSettings = [
        ['api_encryption_key', hash('sha256', 'otel_rezervasyon_api_encryption_key_' . date('Y-m-d')), 'API şifreleme anahtarı'],
        ['api_rate_limit_enabled', '1', 'API rate limiting aktif'],
        ['api_security_logging', '1', 'API güvenlik logları aktif'],
        ['api_audit_logging', '1', 'API audit logları aktif'],
        ['api_validation_enabled', '1', 'API validation aktif'],
        ['api_encryption_enabled', '1', 'API şifreleme aktif']
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sistem_ayarlari (key_name, value, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute($setting);
    }
    echo "✅ Varsayılan API güvenlik ayarları eklendi<br>";
    
    // Varsayılan güvenlik kuralları ekle
    $defaultRules = [
        ['IP Whitelist', 'ip_whitelist', '{"ips":["127.0.0.1","::1"]}', 1, 100],
        ['Rate Limit Default', 'rate_limit', '{"requests":1000,"window":3600}', 1, 50],
        ['Admin Endpoint Restriction', 'endpoint_restriction', '{"endpoints":["admins","kullanicilar","yetkiler"],"allowed_ips":["127.0.0.1"]}', 1, 90]
    ];
    
    foreach ($defaultRules as $rule) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO api_security_rules (rule_name, rule_type, rule_config, is_active, priority) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute($rule);
    }
    echo "✅ Varsayılan güvenlik kuralları eklendi<br>";
    
    echo "<br>🎉 Tüm API güvenlik tabloları başarıyla oluşturuldu!";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>

