<?php
/**
 * Page Builder Sistem Test
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Page Builder Sistem Test</h1>";
echo "<pre>";

session_start();

// Test 1: Database bağlantısı
echo "1. Database Bağlantısı Testi...\n";
try {
    require_once '../config/database.php';
    if ($pdo) {
        echo "   ✅ Database bağlantısı başarılı\n\n";
    }
} catch (Exception $e) {
    die("   ❌ HATA: " . $e->getMessage() . "\n");
}

// Test 2: Tabloları kontrol et
echo "2. Tabloları Kontrol Ediliyor...\n";
$tables = [
    'custom_pages',
    'page_blocks',
    'custom_forms',
    'form_submissions',
    'page_revisions',
    'page_analytics',
    'ai_providers',
    'ai_usage_stats'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   ✅ $table → $count kayıt\n";
    } catch (Exception $e) {
        echo "   ❌ $table → HATA: " . $e->getMessage() . "\n";
    }
}

echo "\n3. Session Kontrolü...\n";
if (isset($_SESSION['user_id'])) {
    echo "   ✅ Session aktif → User ID: " . $_SESSION['user_id'] . "\n";
} else {
    echo "   ❌ Session YOK (Giriş yapmanız gerekiyor)\n";
}

echo "\n4. Yetki Fonksiyonu Kontrolü...\n";
try {
    require_once '../includes/detailed_permission_functions.php';
    echo "   ✅ Yetki fonksiyonları yüklendi\n";
    
    if (function_exists('hasDetailedPermission')) {
        echo "   ✅ hasDetailedPermission() fonksiyonu var\n";
    } else {
        echo "   ❌ hasDetailedPermission() fonksiyonu YOK\n";
    }
} catch (Exception $e) {
    echo "   ❌ HATA: " . $e->getMessage() . "\n";
}

echo "\n5. Admin User Kontrolü...\n";
try {
    $stmt = $pdo->query("SELECT * FROM admin_users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "   ✅ Admin user var → " . $user['kullanici_adi'] . "\n";
        echo "   ID: " . $user['id'] . "\n";
    } else {
        echo "   ❌ Admin user YOK\n";
    }
} catch (Exception $e) {
    echo "   ❌ HATA: " . $e->getMessage() . "\n";
}

echo "\n6. AI Provider Kontrolü...\n";
try {
    $stmt = $pdo->query("SELECT * FROM ai_providers WHERE is_active = 1 LIMIT 1");
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($provider) {
        echo "   ✅ Aktif AI Provider var → " . $provider['provider_label'] . "\n";
        echo "   API Key: " . (empty($provider['api_key']) ? 'YOK' : 'VAR') . "\n";
    } else {
        echo "   ⚠️  Aktif AI Provider YOK\n";
    }
} catch (Exception $e) {
    echo "   ❌ HATA: " . $e->getMessage() . "\n";
}

echo "\n7. Dosya Varlık Kontrolü...\n";
$files = [
    '../includes/ai/AIProviderFactory.php',
    'page-list.php',
    'page-builder.php',
    'form-builder.php',
    'ai-settings.php',
    'ajax/page-builder-save.php',
    'ajax/ai-generate-content.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file → BULUNAMADI\n";
    }
}

echo "\n8. GrapesJS CDN Test...\n";
$grapesjs_url = 'https://unpkg.com/grapesjs/dist/grapes.min.js';
$headers = @get_headers($grapesjs_url);
if ($headers && strpos($headers[0], '200')) {
    echo "   ✅ GrapesJS CDN erişilebilir\n";
} else {
    echo "   ❌ GrapesJS CDN erişilemez\n";
}

echo "\n═══════════════════════════════════════════════\n";
echo "TEST TAMAMLANDI\n";
echo "═══════════════════════════════════════════════\n";

echo "</pre>";

echo "<hr>";
echo "<h2>Hızlı Linkler</h2>";
echo "<a href='page-list.php' class='btn btn-primary'>Sayfa Listesi</a> ";
echo "<a href='page-builder.php' class='btn btn-success'>Yeni Sayfa</a> ";
echo "<a href='form-builder.php' class='btn btn-info'>Form Builder</a> ";
echo "<a href='ai-settings.php' class='btn btn-warning'>AI Ayarları</a>";
?>

<style>
.btn {
    padding: 10px 20px;
    margin: 5px;
    text-decoration: none;
    color: white;
    border-radius: 5px;
    display: inline-block;
}
.btn-primary { background: #007bff; }
.btn-success { background: #28a745; }
.btn-info { background: #17a2b8; }
.btn-warning { background: #ffc107; color: #000; }
</style>


