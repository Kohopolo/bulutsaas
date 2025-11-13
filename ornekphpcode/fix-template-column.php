<?php
require_once 'config/database.php';

echo "🔧 Template Sütunu Düzeltiliyor...\n";

try {
    // Template sütununu NULL olanları güncelle
    $stmt = $pdo->prepare("UPDATE custom_pages SET page_template = 'custom' WHERE page_template IS NULL OR page_template = ''");
    $result = $stmt->execute();
    
    echo "✅ Template sütunu düzeltildi!\n";
    
    // Kontrol et
    $stmt = $pdo->query("SELECT page_template, COUNT(*) as count FROM custom_pages GROUP BY page_template");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTemplate dağılımı:\n";
    foreach ($templates as $template) {
        $templateName = $template['page_template'] ?: 'NULL';
        echo "- {$templateName}: {$template['count']} sayfa\n";
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

