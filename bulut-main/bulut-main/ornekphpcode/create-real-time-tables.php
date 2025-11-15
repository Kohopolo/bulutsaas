<?php
require_once '../config/database.php';

try {
    // Real-time Events Tablosu
    $sql1 = "CREATE TABLE IF NOT EXISTS `real_time_events` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `event_type` varchar(100) NOT NULL,
      `event_data` longtext NOT NULL,
      `room` varchar(100) DEFAULT 'general',
      `target_user_id` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `event_type` (`event_type`),
      KEY `room` (`room`),
      KEY `target_user_id` (`target_user_id`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql1);
    echo "✅ real_time_events tablosu oluşturuldu<br>";
    
    // Real-time Subscriptions Tablosu
    $sql2 = "CREATE TABLE IF NOT EXISTS `real_time_subscriptions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `room` varchar(100) NOT NULL,
      `event_types` text,
      `is_active` tinyint(1) DEFAULT '1',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `room` (`room`),
      KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✅ real_time_subscriptions tablosu oluşturuldu<br>";
    
    // Real-time Connections Tablosu
    $sql3 = "CREATE TABLE IF NOT EXISTS `real_time_connections` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `connection_id` varchar(100) NOT NULL,
      `user_id` int(11) DEFAULT NULL,
      `room` varchar(100) NOT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` text,
      `connected_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `is_active` tinyint(1) DEFAULT '1',
      PRIMARY KEY (`id`),
      UNIQUE KEY `connection_id` (`connection_id`),
      KEY `user_id` (`user_id`),
      KEY `room` (`room`),
      KEY `is_active` (`is_active`),
      KEY `last_activity` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql3);
    echo "✅ real_time_connections tablosu oluşturuldu<br>";
    
    echo "<br>🎉 Tüm real-time tabloları başarıyla oluşturuldu!";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>

