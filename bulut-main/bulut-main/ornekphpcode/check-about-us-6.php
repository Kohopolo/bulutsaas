<?php
/**
 * about-us-6 sayfasını kontrol et
 */

require_once '../config/database.php';

try {
    // about-us-6 sayfasını ara
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE page_slug = ?");
    $stmt->execute(['about-us-6']);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($page) {
        echo "✅ about-us-6 sayfası bulundu!\n";
        echo "ID: " . $page['id'] . "\n";
        echo "Başlık: " . $page['page_title'] . "\n";
        echo "Slug: " . $page['page_slug'] . "\n";
        echo "Template: " . $page['page_template'] . "\n";
        echo "Aktif: " . ($page['is_active'] ? 'Evet' : 'Hayır') . "\n";
        echo "Oluşturulma: " . $page['created_at'] . "\n";
    } else {
        echo "❌ about-us-6 sayfası bulunamadı!\n";
        
        // Tüm sayfaları listele
        echo "\n📋 Mevcut sayfalar:\n";
        $stmt = $pdo->query("SELECT page_slug, page_title, is_active FROM custom_pages ORDER BY created_at DESC");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pages as $p) {
            echo "- " . $p['page_slug'] . " (" . $p['page_title'] . ") - " . ($p['is_active'] ? 'Aktif' : 'Pasif') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

