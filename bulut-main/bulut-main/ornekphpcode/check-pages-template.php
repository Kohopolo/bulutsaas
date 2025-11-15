<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT page_title, page_template, page_slug FROM custom_pages ORDER BY page_template, page_title");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Mevcut Sayfalar:\n";
    echo str_repeat('-', 80) . "\n";
    
    foreach ($pages as $page) {
        $template = $page['page_template'] ?: 'custom';
        echo sprintf("%-30s | %-15s | %s\n", 
            substr($page['page_title'], 0, 30), 
            $template, 
            $page['page_slug']
        );
    }
    
    echo str_repeat('-', 80) . "\n";
    echo "Toplam: " . count($pages) . " sayfa\n";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
?>
