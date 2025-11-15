<?php
require_once 'config/database.php';

echo "📥 Template Sayfaları Aktarılıyor...\n";

$templateDirs = ['default', 'elegant-hotel', 'luxury-hotel', 'modern-hotel', 'premium-hotel'];
$importedCount = 0;

foreach ($templateDirs as $templateName) {
    $templatePath = "templates/{$templateName}/pages/";
    
    if (!is_dir($templatePath)) {
        echo "⚠️ Template bulunamadı: {$templateName} (Path: {$templatePath})\n";
        continue;
    }
    
    echo "📁 Template: {$templateName}\n";
    
    // HTML dosyalarını bul
    $files = glob($templatePath . "*.html");
    echo "  📄 Bulunan dosyalar: " . count($files) . "\n";
    
    foreach ($files as $file) {
        $filename = basename($file, '.html');
        $content = file_get_contents($file);
        
        // Slug oluştur
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $filename));
        $slug = trim($slug, '-');
        
        // Slug çakışması kontrol et
        $originalSlug = $slug;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM custom_pages WHERE page_slug = ?");
            $stmt->execute([$slug]);
            
            if (!$stmt->fetch()) {
                break; // Slug mevcut değil, kullanabiliriz
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        // Sayfayı ekle
        $stmt = $pdo->prepare("
            INSERT INTO custom_pages 
            (page_title, page_slug, page_content, page_template, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            ucwords(str_replace('-', ' ', $filename)),
            $slug,
            $content,
            $templateName
        ]);
        
        echo "  ✅ Eklendi: {$filename}\n";
        $importedCount++;
    }
}

echo "\n🎉 Toplam {$importedCount} sayfa aktarıldı!\n";
?>
