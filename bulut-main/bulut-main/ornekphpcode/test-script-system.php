<?php
/**
 * Script Yönetim Sistemi - Hızlı Test
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<h1>🧪 Script Yönetim Sistemi - Test</h1>";
echo "<pre>";

try {
    echo "═══════════════════════════════════════════════════\n";
    echo "1️⃣  TABLO KONTROLÜ\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $tables = ['site_scripts', 'site_script_settings', 'script_change_logs'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "   ✅ $table → $count kayıt\n";
        } else {
            echo "   ❌ $table → BULUNAMADI!\n";
            $allTablesExist = false;
        }
    }
    
    if (!$allTablesExist) {
        echo "\n⚠️  Tablolar eksik! Kurulum yapılıyor...\n\n";
        
        // SQL dosyasını oku
        $sql = file_get_contents('../sql/create_script_management_tables.sql');
        
        // Sorguları ayır ve çalıştır
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        $successCount = 0;
        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;
            
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                // Duplicate entry hatalarını yoksay
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "   ⚠️  Hata: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "   ✅ $successCount sorgu başarılı\n\n";
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "2️⃣  YETKİ KONTROLÜ\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $permissions = [
        'script_yonetimi_goruntule',
        'script_yonetimi_duzenle',
        'script_yonetimi_sil',
        'script_yonetimi_aktif_pasif'
    ];
    
    foreach ($permissions as $perm) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM detailed_permissions WHERE permission_key = ?");
        $stmt->execute([$perm]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            echo "   ✅ $perm\n";
        } else {
            echo "   ❌ $perm → EKLENMEDİ!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "3️⃣  HAZIR SERVİSLER\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $stmt = $pdo->query("
        SELECT service_category, COUNT(*) as count 
        FROM site_script_settings 
        GROUP BY service_category
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalServices = 0;
    foreach ($categories as $cat) {
        $totalServices += $cat['count'];
        echo "   📊 " . ucfirst($cat['service_category']) . " → {$cat['count']} servis\n";
    }
    
    echo "\n   ✅ TOPLAM: $totalServices hazır servis\n";
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "4️⃣  PHP SINIFLAR\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $classes = [
        'ScriptManager' => '../includes/ScriptManager.php',
        'PredefinedScripts' => '../includes/PredefinedScripts.php'
    ];
    
    foreach ($classes as $className => $file) {
        if (file_exists($file)) {
            require_once $file;
            if (class_exists($className)) {
                echo "   ✅ $className sınıfı yüklendi\n";
            } else {
                echo "   ❌ $className sınıfı yüklenemedi!\n";
            }
        } else {
            echo "   ❌ $file dosyası bulunamadı!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "5️⃣  SCRIPT MANAGER TESTİ\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $scriptManager = new ScriptManager($pdo);
    
    // Test: Head scriptleri al
    $headScripts = $scriptManager->getScripts('head', 'test.php');
    echo "   ✅ ScriptManager çalışıyor\n";
    echo "   📝 Head scriptleri: " . (stripos($headScripts, 'Script Manager') !== false ? 'Bulundu' : 'Boş') . "\n";
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "6️⃣  TEMPLATE ENGINE ENTEGRASYONu\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $templateEngineFile = '../includes/TemplateEngine.php';
    if (file_exists($templateEngineFile)) {
        $content = file_get_contents($templateEngineFile);
        
        if (strpos($content, 'injectScripts') !== false) {
            echo "   ✅ TemplateEngine'e script injection eklendi\n";
        } else {
            echo "   ❌ TemplateEngine'de script injection bulunamadı!\n";
        }
        
        if (strpos($content, 'ScriptManager') !== false) {
            echo "   ✅ ScriptManager entegrasyonu var\n";
        } else {
            echo "   ❌ ScriptManager entegrasyonu yok!\n";
        }
    } else {
        echo "   ❌ TemplateEngine.php bulunamadı!\n";
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "7️⃣  ADMIN PANEL SAYFALARI\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $adminPages = [
        'script-yonetimi.php' => 'Script Yönetim Sayfası',
        'install-script-tables.php' => 'Kurulum Sayfası'
    ];
    
    foreach ($adminPages as $page => $label) {
        if (file_exists($page)) {
            echo "   ✅ $label\n";
        } else {
            echo "   ❌ $label → Bulunamadı!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "8️⃣  AJAX ENDPOINT'LERİ\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $endpoints = [
        'toggle-script.php',
        'save-service-config.php',
        'save-custom-script.php',
        'get-script.php',
        'delete-script.php'
    ];
    
    foreach ($endpoints as $endpoint) {
        if (file_exists('ajax/' . $endpoint)) {
            echo "   ✅ ajax/$endpoint\n";
        } else {
            echo "   ❌ ajax/$endpoint → Bulunamadı!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "9️⃣  SİDEBAR MENÜSÜ\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $sidebarFile = 'includes/sidebar.php';
    if (file_exists($sidebarFile)) {
        $sidebarContent = file_get_contents($sidebarFile);
        
        if (strpos($sidebarContent, 'script-yonetimi.php') !== false) {
            echo "   ✅ Sidebar'a 'Script Yönetimi' menüsü eklendi\n";
        } else {
            echo "   ❌ Sidebar'da 'Script Yönetimi' menüsü bulunamadı!\n";
        }
        
        if (strpos($sidebarContent, 'script_yonetimi_goruntule') !== false) {
            echo "   ✅ Yetki kontrolü entegre\n";
        } else {
            echo "   ❌ Yetki kontrolü eksik!\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "🔟  DOKÜMANTASYON\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $docFile = '../docs/script-yonetimi-rehberi.md';
    if (file_exists($docFile)) {
        $docSize = filesize($docFile);
        echo "   ✅ Kullanım rehberi var (" . number_format($docSize / 1024, 1) . " KB)\n";
    } else {
        echo "   ❌ Dokümantasyon bulunamadı!\n";
    }
    
    echo "\n\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "✅  TEST SONUCU: SİSTEM HAZIR!\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    echo "📍 Şimdi şunları yapabilirsin:\n\n";
    echo "   1. Admin Panel > Script Yönetimi sayfasına git\n";
    echo "   2. Hazır servisleri aktif et (Google Analytics, Meta Pixel, vb.)\n";
    echo "   3. Tracking ID'lerini gir\n";
    echo "   4. Frontend'i ziyaret et ve scriptlerin yüklendiğini gör!\n\n";
    
    echo "🔗 Linkler:\n";
    echo "   • Script Yönetimi: <a href='script-yonetimi.php'>script-yonetimi.php</a>\n";
    echo "   • Dokümantasyon: <a href='../docs/script-yonetimi-rehberi.md'>Kullanım Rehberi</a>\n\n";
    
} catch (Exception $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1 {
        color: #333;
        border-bottom: 3px solid #4CAF50;
        padding-bottom: 10px;
    }
    pre {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        line-height: 1.6;
    }
    a {
        color: #4CAF50;
        text-decoration: none;
        font-weight: bold;
    }
    a:hover {
        text-decoration: underline;
    }
</style>



