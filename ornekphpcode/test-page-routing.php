<?php
/**
 * Page Routing Test
 */

require_once '../config/database.php';

echo "<h2>Page Routing Test</h2>";

// Test slug'ı
$testSlug = 'about-us-6';

echo "<h3>Test Slug: " . $testSlug . "</h3>";

try {
    // Sayfayı ara
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE page_slug = ?");
    $stmt->execute([$testSlug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($page) {
        echo "<div style='color: green;'>✅ Sayfa bulundu!</div>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Alan</th><th>Değer</th></tr>";
        foreach ($page as $key => $value) {
            $displayValue = $value !== null ? htmlspecialchars($value) : '<em>null</em>';
            echo "<tr><td>" . $key . "</td><td>" . $displayValue . "</td></tr>";
        }
        echo "</table>";
        
        // URL testi
        $testUrl = "http://localhost/otelonofexe/web/" . $testSlug;
        echo "<p><strong>Test URL:</strong> <a href='" . $testUrl . "' target='_blank'>" . $testUrl . "</a></p>";
        
    } else {
        echo "<div style='color: red;'>❌ Sayfa bulunamadı!</div>";
        
        // Tüm sayfaları listele
        echo "<h4>Mevcut Sayfalar:</h4>";
        $stmt = $pdo->query("SELECT page_slug, page_title, is_active FROM custom_pages ORDER BY created_at DESC");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pages)) {
            echo "<p>Hiç sayfa yok!</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Slug</th><th>Başlık</th><th>Aktif</th></tr>";
            foreach ($pages as $p) {
                $active = $p['is_active'] ? '✅' : '❌';
                echo "<tr><td>" . $p['page_slug'] . "</td><td>" . $p['page_title'] . "</td><td>" . $active . "</td></tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Hata: " . $e->getMessage() . "</div>";
}

// .htaccess testi
echo "<h3>.htaccess Test</h3>";
if (file_exists('../.htaccess')) {
    echo "<div style='color: green;'>✅ .htaccess dosyası mevcut</div>";
    
    $htaccess = file_get_contents('../.htaccess');
    if (strpos($htaccess, 'RewriteRule ^page/') !== false) {
        echo "<div style='color: green;'>✅ Page routing kuralı mevcut</div>";
    } else {
        echo "<div style='color: red;'>❌ Page routing kuralı bulunamadı</div>";
    }
} else {
    echo "<div style='color: red;'>❌ .htaccess dosyası bulunamadı</div>";
}

// page.php testi
echo "<h3>page.php Test</h3>";
if (file_exists('../page.php')) {
    echo "<div style='color: green;'>✅ page.php dosyası mevcut</div>";
} else {
    echo "<div style='color: red;'>❌ page.php dosyası bulunamadı</div>";
}
?>
