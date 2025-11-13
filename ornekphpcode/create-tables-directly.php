<?php
/**
 * Script Tablolarını Direkt Oluştur
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<h1>📦 Script Tabloları Oluşturuluyor...</h1>";
echo "<pre>";

try {
    // 1. site_scripts tablosu
    echo "1. site_scripts tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_scripts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            script_name VARCHAR(100) NOT NULL,
            script_description TEXT,
            script_type ENUM('analytics', 'advertising', 'chat', 'seo', 'conversion', 'other') NOT NULL DEFAULT 'other',
            script_code TEXT NOT NULL,
            position ENUM('head', 'body_start', 'body_end') DEFAULT 'head',
            load_async TINYINT(1) DEFAULT 0,
            load_defer TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            priority INT DEFAULT 50,
            load_on_pages VARCHAR(500),
            exclude_pages VARCHAR(500),
            load_only_frontend TINYINT(1) DEFAULT 1,
            requires_consent TINYINT(1) DEFAULT 0,
            consent_category ENUM('necessary', 'analytics', 'marketing', 'preferences') DEFAULT 'analytics',
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_modified_by INT,
            INDEX idx_active (is_active),
            INDEX idx_type (script_type),
            INDEX idx_priority (priority),
            INDEX idx_position (position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ site_scripts oluşturuldu\n\n";
    
    // 2. site_script_settings tablosu
    echo "2. site_script_settings tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_script_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            service_name VARCHAR(50) NOT NULL UNIQUE,
            service_label VARCHAR(100) NOT NULL,
            service_description TEXT,
            service_icon VARCHAR(50) DEFAULT 'fa-code',
            service_category ENUM('analytics', 'advertising', 'chat', 'seo', 'conversion', 'other') NOT NULL,
            tracking_id VARCHAR(255),
            api_key VARCHAR(255),
            widget_id VARCHAR(255),
            additional_config JSON,
            script_position ENUM('head', 'body_start', 'body_end') DEFAULT 'head',
            load_async TINYINT(1) DEFAULT 0,
            load_defer TINYINT(1) DEFAULT 0,
            priority INT DEFAULT 50,
            requires_consent TINYINT(1) DEFAULT 1,
            consent_category ENUM('necessary', 'analytics', 'marketing', 'preferences') DEFAULT 'analytics',
            is_active TINYINT(1) DEFAULT 0,
            is_visible TINYINT(1) DEFAULT 1,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            INDEX idx_active (is_active),
            INDEX idx_category (service_category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ site_script_settings oluşturuldu\n\n";
    
    // 3. script_change_logs tablosu
    echo "3. script_change_logs tablosu oluşturuluyor...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS script_change_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            script_id INT,
            script_type ENUM('custom', 'predefined') NOT NULL,
            action ENUM('created', 'updated', 'deleted', 'activated', 'deactivated') NOT NULL,
            old_value TEXT,
            new_value TEXT,
            changed_by INT,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            INDEX idx_script (script_id, script_type),
            INDEX idx_date (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ script_change_logs oluşturuldu\n\n";
    
    // 4. Hazır servisleri ekle
    echo "4. Hazır servisler ekleniyor...\n";
    
    $services = [
        // Analytics
        ['google_analytics_4', 'Google Analytics 4', 'Google Analytics 4 (GA4) ile detaylı ziyaretçi analizi', 'fab fa-google', 'analytics', 'head', 1, 'analytics'],
        ['google_tag_manager', 'Google Tag Manager', 'Tüm pazarlama etiketlerini tek yerden yönetin', 'fab fa-google', 'analytics', 'head', 1, 'analytics'],
        ['meta_pixel', 'Meta Pixel (Facebook)', 'Facebook Ads ve dönüşüm takibi', 'fab fa-facebook', 'advertising', 'head', 1, 'marketing'],
        ['tiktok_pixel', 'TikTok Pixel', 'TikTok reklamları için dönüşüm takibi', 'fab fa-tiktok', 'advertising', 'head', 1, 'marketing'],
        ['hotjar', 'Hotjar', 'Heatmap, session recording ve kullanıcı davranış analizi', 'fas fa-fire', 'analytics', 'head', 1, 'analytics'],
        ['microsoft_clarity', 'Microsoft Clarity', 'Ücretsiz heatmap ve session replay', 'fab fa-microsoft', 'analytics', 'head', 1, 'analytics'],
        ['yandex_metrica', 'Yandex Metrica', 'Rus pazarı için detaylı analiz', 'fab fa-yandex', 'analytics', 'head', 1, 'analytics'],
        
        // Advertising
        ['google_ads', 'Google Ads', 'Google Ads dönüşüm takibi', 'fab fa-google', 'advertising', 'head', 1, 'marketing'],
        ['linkedin_insight', 'LinkedIn Insight Tag', 'LinkedIn reklamları için takip', 'fab fa-linkedin', 'advertising', 'head', 1, 'marketing'],
        ['twitter_pixel', 'Twitter Pixel', 'Twitter (X) reklamları dönüşüm takibi', 'fab fa-twitter', 'advertising', 'head', 1, 'marketing'],
        ['pinterest_tag', 'Pinterest Tag', 'Pinterest reklamları takibi', 'fab fa-pinterest', 'advertising', 'head', 1, 'marketing'],
        
        // Chat
        ['tawk_to', 'Tawk.to', 'Ücretsiz canlı destek chat', 'fas fa-comments', 'chat', 'body_end', 0, 'necessary'],
        ['intercom', 'Intercom', 'Profesyonel müşteri destek sistemi', 'fas fa-comment-dots', 'chat', 'body_end', 0, 'necessary'],
        ['crisp_chat', 'Crisp Chat', 'Modern canlı destek ve chatbot', 'fas fa-comment-alt', 'chat', 'body_end', 0, 'necessary'],
        ['whatsapp_chat', 'WhatsApp Chat Widget', 'WhatsApp üzerinden canlı destek', 'fab fa-whatsapp', 'chat', 'body_end', 0, 'necessary'],
        ['facebook_messenger', 'Facebook Messenger', 'Facebook Messenger entegrasyonu', 'fab fa-facebook-messenger', 'chat', 'body_end', 0, 'necessary'],
        
        // SEO
        ['google_search_console', 'Google Search Console', 'Google arama motoru doğrulama', 'fab fa-google', 'seo', 'head', 0, 'necessary'],
        ['bing_webmaster', 'Bing Webmaster Tools', 'Bing arama motoru doğrulama', 'fab fa-microsoft', 'seo', 'head', 0, 'necessary'],
        ['yandex_webmaster', 'Yandex Webmaster', 'Yandex arama motoru doğrulama', 'fab fa-yandex', 'seo', 'head', 0, 'necessary'],
        
        // Other
        ['trustpilot', 'Trustpilot Reviews', 'Trustpilot yorum widget\'ı', 'fas fa-star', 'other', 'body_end', 0, 'necessary'],
        ['google_reviews', 'Google Reviews Widget', 'Google yorumları göster', 'fab fa-google', 'other', 'body_end', 0, 'necessary'],
        ['cookie_consent', 'Cookie Consent (KVKK)', 'KVKK/GDPR uyumlu çerez onayı', 'fas fa-cookie-bite', 'other', 'body_end', 0, 'necessary']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO site_script_settings 
        (service_name, service_label, service_description, service_icon, service_category, script_position, requires_consent, consent_category, is_visible) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE service_label = VALUES(service_label)
    ");
    
    $count = 0;
    foreach ($services as $service) {
        $stmt->execute($service);
        $count++;
    }
    
    echo "   ✅ $count hazır servis eklendi\n\n";
    
    // 5. Kontrol
    echo "5. Kontrol ediliyor...\n";
    $tables = ['site_scripts', 'site_script_settings', 'script_change_logs'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "   ✅ $table → $count kayıt\n";
        } else {
            echo "   ❌ $table → BULUNAMADI!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "✅  TABLOLAR BAŞARIYLA OLUŞTURULDU!\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    echo "📍 Sonraki Adımlar:\n\n";
    echo "   1. <a href='test-script-system.php'>Sistem Testi</a>\n";
    echo "   2. <a href='script-yonetimi.php'>Script Yönetimi</a>\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "\nCode: " . $e->getCode() . "\n";
}

echo "</pre>";
?>

<style>
body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
pre { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); line-height: 1.8; }
a { color: #4CAF50; text-decoration: none; font-weight: bold; }
a:hover { text-decoration: underline; }
</style>



