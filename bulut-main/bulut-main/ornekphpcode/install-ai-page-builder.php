<?php
/**
 * AI Page Builder Kurulum Scripti
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/detailed_permission_functions.php';

echo "<h1>🎨 AI Page Builder Kurulum</h1>";
echo "<pre>";

try {
    if (!$pdo) {
        die("❌ HATA: Database bağlantısı kurulamadı.\n");
    }

    // 1. Tabloları oluştur
    echo "1. Database tabloları oluşturuluyor...\n";
    $sql = file_get_contents('../sql/create_ai_page_builder_tables.sql');
    if ($sql === false) {
        throw new Exception("SQL dosyası okunamadı!");
    }
    
    // SQL komutlarını ayır ve tek tek çalıştır
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $successCount = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                // Tablo zaten varsa hatayı görmezden gel
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }
    
    echo "   ✅ $successCount tablo/sorgu başarıyla çalıştırıldı\n\n";

    // 2. Yetkileri ekle
    echo "2. Yetkileri ekleniyor...\n";
    $permissions = [
        // AI Ayarları
        'ai_settings_view' => 'AI Ayarlarını Görüntüleme',
        'ai_settings_edit' => 'AI Ayarlarını Düzenleme',
        'ai_provider_manage' => 'AI Provider Yönetimi',
        
        // Page Builder
        'page_builder_view' => 'Page Builder\'ı Görüntüleme',
        'page_builder_create' => 'Sayfa Oluşturma',
        'page_builder_edit' => 'Sayfa Düzenleme',
        'page_builder_delete' => 'Sayfa Silme',
        'page_builder_publish' => 'Sayfa Yayınlama',
        
        // Form Builder
        'form_builder_view' => 'Form Builder\'ı Görüntüleme',
        'form_builder_create' => 'Form Oluşturma',
        'form_builder_edit' => 'Form Düzenleme',
        'form_builder_delete' => 'Form Silme',
        'form_submissions_view' => 'Form Gönderilerini Görüntüleme',
        'form_submissions_export' => 'Form Gönderilerini Dışa Aktarma',
        
        // Analytics
        'page_analytics_view' => 'Sayfa İstatistiklerini Görüntüleme',
        'ai_usage_stats_view' => 'AI Kullanım İstatistiklerini Görüntüleme'
    ];
    
    foreach ($permissions as $key => $description) {
        try {
            if (function_exists('addPermission')) {
                addPermission($key, $description);
                echo "   ✅ $key\n";
            } else {
                // Manuel olarak ekle
                $stmt = $pdo->prepare("INSERT IGNORE INTO detailed_permissions (permission_key, permission_description) VALUES (?, ?)");
                $stmt->execute([$key, $description]);
                echo "   ✅ $key\n";
            }
        } catch (Exception $e) {
            echo "   ⚠️  $key (zaten var olabilir)\n";
        }
    }
    echo "\n";

    // 3. Tablo kontrolü
    echo "3. Tablolar kontrol ediliyor...\n";
    $tables = [
        'ai_providers',
        'ai_usage_stats',
        'custom_pages',
        'page_blocks',
        'custom_forms',
        'form_submissions',
        'page_revisions',
        'page_analytics'
    ];
    
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
    echo "\n";

    // 4. AI Provider'lar kontrol
    echo "4. AI Provider'lar kontrol ediliyor...\n";
    $stmt = $pdo->query("SELECT provider_name, provider_label, is_free, is_active FROM ai_providers");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   Mevcut AI Provider'lar:\n";
    echo "   " . str_repeat("─", 70) . "\n";
    foreach ($providers as $provider) {
        $status = $provider['is_active'] ? '✅ Aktif' : '⭕ Pasif';
        $cost = $provider['is_free'] ? '🆓 Ücretsiz' : '💰 Ücretli';
        echo "   $status $cost {$provider['provider_label']}\n";
    }
    echo "   " . str_repeat("─", 70) . "\n\n";

    echo "═══════════════════════════════════════════════════\n";
    echo "✅  KURULUM BAŞARIYLA TAMAMLANDI!\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    echo "📍 Sonraki Adımlar:\n\n";
    echo "   1. <a href='ai-settings.php' style='color:#28a745;font-weight:bold;'>AI Ayarları</a> - Provider'ları yapılandır\n";
    echo "   2. <a href='page-builder.php' style='color:#28a745;font-weight:bold;'>Page Builder</a> - İlk sayfanı oluştur\n";
    echo "   3. <a href='form-builder.php' style='color:#28a745;font-weight:bold;'>Form Builder</a> - İlk formunu oluştur\n\n";
    
    echo "🤖 AI Provider Kurulum:\n\n";
    echo "   • Groq: <a href='https://console.groq.com' target='_blank'>console.groq.com</a> (ÖNERİLEN - Hızlı ve Ücretsiz)\n";
    echo "   • Hugging Face: <a href='https://huggingface.co/settings/tokens' target='_blank'>huggingface.co/settings/tokens</a>\n";
    echo "   • Google Gemini: <a href='https://makersuite.google.com/app/apikey' target='_blank'>makersuite.google.com/app/apikey</a>\n";
    echo "   • OpenAI: <a href='https://platform.openai.com/api-keys' target='_blank'>platform.openai.com/api-keys</a> (Ücretli)\n";
    echo "   • Claude: <a href='https://console.anthropic.com' target='_blank'>console.anthropic.com</a> (Ücretli)\n\n";
    
} catch (Exception $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>

<style>
body { 
    font-family: 'Segoe UI', sans-serif; 
    max-width: 1200px; 
    margin: 20px auto; 
    padding: 20px; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
h1 { 
    color: white; 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    border-bottom: 3px solid white; 
    padding-bottom: 10px; 
}
pre { 
    background: white; 
    padding: 30px; 
    border-radius: 12px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
    line-height: 1.8;
    font-size: 14px;
}
a { 
    color: #28a745; 
    text-decoration: none; 
    font-weight: bold;
    transition: all 0.3s;
}
a:hover { 
    color: #20c997;
    text-decoration: underline; 
}
</style>

