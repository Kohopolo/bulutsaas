<?php
require_once 'config/database.php';

echo "🔍 Database Yapısı Kontrol Ediliyor...\n";

try {
    // custom_pages tablosunun yapısını kontrol et
    $stmt = $pdo->query("DESCRIBE custom_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 custom_pages Tablo Yapısı:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($columns as $column) {
        echo sprintf("%-20s | %-15s | %-5s | %-10s | %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\n" . str_repeat('-', 80) . "\n";
    
    // page_template sütununu özel kontrol et
    $stmt = $pdo->query("SELECT page_template, COUNT(*) as count FROM custom_pages GROUP BY page_template");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📊 page_template Değerleri:\n";
    foreach ($templates as $template) {
        $value = $template['page_template'] === null ? 'NULL' : "'{$template['page_template']}'";
        echo "- {$value}: {$template['count']} sayfa\n";
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>