<?php
require_once 'config/database.php';

echo "🔧 Template ENUM Düzeltiliyor...\n";

try {
    // ENUM'u güncelle
    $sql = "ALTER TABLE custom_pages MODIFY COLUMN page_template ENUM(
        'blank', 'landing', 'blog', 'contact', 'about', 'custom',
        'default', 'elegant-hotel', 'luxury-hotel', 'modern-hotel', 'premium-hotel'
    ) DEFAULT 'custom'";
    
    $pdo->exec($sql);
    echo "✅ Template ENUM güncellendi!\n";
    
    // Boş template değerlerini güncelle
    $stmt = $pdo->prepare("UPDATE custom_pages SET page_template = 'custom' WHERE page_template = ''");
    $stmt->execute();
    echo "✅ Boş template değerleri düzeltildi!\n";
    
    // Kontrol et
    $stmt = $pdo->query("SELECT page_template, COUNT(*) as count FROM custom_pages GROUP BY page_template");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTemplate dağılımı:\n";
    foreach ($templates as $template) {
        echo "- {$template['page_template']}: {$template['count']} sayfa\n";
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

