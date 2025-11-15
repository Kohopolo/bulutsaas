<?php
require_once '../config/database.php';

try {
    // Mobile Devices Tablosu
    $sql1 = "CREATE TABLE IF NOT EXISTS `mobile_devices` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `device_token` varchar(255) NOT NULL,
      `platform` enum('android','ios','web') DEFAULT 'android',
      `device_info` text,
      `app_version` varchar(20) DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT '1',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_active` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `unregistered_at` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `device_token` (`device_token`),
      KEY `user_id` (`user_id`),
      KEY `platform` (`platform`),
      KEY `is_active` (`is_active`),
      KEY `last_active` (`last_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql1);
    echo "✅ mobile_devices tablosu oluşturuldu<br>";
    
    // Push Notifications Tablosu
    $sql2 = "CREATE TABLE IF NOT EXISTS `push_notifications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `title` varchar(255) NOT NULL,
      `body` text NOT NULL,
      `data` text,
      `type` enum('user','broadcast','system') DEFAULT 'user',
      `status` enum('pending','sent','failed') DEFAULT 'pending',
      `sent_at` timestamp NULL DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `type` (`type`),
      KEY `status` (`status`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✅ push_notifications tablosu oluşturuldu<br>";
    
    // Mobile App Settings Tablosu
    $sql3 = "CREATE TABLE IF NOT EXISTS `mobile_app_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `key_name` varchar(100) NOT NULL,
      `value` text,
      `description` text,
      `is_active` tinyint(1) DEFAULT '1',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `key_name` (`key_name`),
      KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql3);
    echo "✅ mobile_app_settings tablosu oluşturuldu<br>";
    
    // Mobile App Versions Tablosu
    $sql4 = "CREATE TABLE IF NOT EXISTS `mobile_app_versions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `version` varchar(20) NOT NULL,
      `platform` enum('android','ios') NOT NULL,
      `is_required` tinyint(1) DEFAULT '0',
      `download_url` varchar(500) DEFAULT NULL,
      `release_notes` text,
      `is_active` tinyint(1) DEFAULT '1',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `version_platform` (`version`,`platform`),
      KEY `platform` (`platform`),
      KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql4);
    echo "✅ mobile_app_versions tablosu oluşturuldu<br>";
    
    // Varsayılan mobile app ayarları ekle
    $defaultSettings = [
        ['fcm_server_key', '', 'Firebase Cloud Messaging Server Key'],
        ['app_name', 'Otel Rezervasyon', 'Uygulama adı'],
        ['app_version', '1.0.0', 'Uygulama versiyonu'],
        ['min_android_version', '1.0.0', 'Minimum Android versiyonu'],
        ['min_ios_version', '1.0.0', 'Minimum iOS versiyonu'],
        ['force_update', '0', 'Zorunlu güncelleme'],
        ['maintenance_mode', '0', 'Bakım modu']
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO mobile_app_settings (key_name, value, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute($setting);
    }
    echo "✅ Varsayılan mobile app ayarları eklendi<br>";
    
    echo "<br>🎉 Tüm mobile tabloları başarıyla oluşturuldu!";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>

