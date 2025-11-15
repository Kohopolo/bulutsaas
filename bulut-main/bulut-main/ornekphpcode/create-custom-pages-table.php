<?php
/**
 * Custom Pages tablosunu sıfırdan oluştur
 */

require_once '../config/database.php';

try {
    echo "<h2>🔧 Custom Pages Tablosu Oluşturuluyor...</h2>";
    
    // Mevcut tabloyu sil (varsa)
    $pdo->exec("DROP TABLE IF EXISTS custom_pages");
    echo "<p>✅ Eski tablo silindi (varsa)</p>";
    
    // Yeni tabloyu oluştur
    $createTable = "
    CREATE TABLE custom_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content_html LONGTEXT,
        content_css LONGTEXT,
        status ENUM('draft', 'published') DEFAULT 'draft',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTable);
    echo "<p>✅ custom_pages tablosu oluşturuldu!</p>";
    
    // Tablo yapısını göster
    $stmt = $pdo->query("DESCRIBE custom_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Tablo Yapısı:</h3>";
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
    echo "<tr><th>Kolon</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test verisi ekle
    $stmt = $pdo->prepare("
        INSERT INTO custom_pages (title, content_html, content_css, status, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        'Test Sayfası',
        '<h1>Test Sayfası</h1><p>Bu bir test sayfasıdır.</p>',
        'h1 { color: #333; }',
        'published',
        1
    ]);
    
    echo "<p>✅ Test verisi eklendi!</p>";
    
    // Sayfa sayısını kontrol et
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📊 Toplam sayfa sayısı: {$result['count']}</p>";
    
    echo "<h3>🎉 Başarıyla Tamamlandı!</h3>";
    echo "<p>Artık Page Builder çalışmalı!</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Custom Pages Tablosu Oluşturuldu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Custom Pages Tablosu Başarıyla Oluşturuldu!</h4>
            <p>Artık sayfa kaydetme işlemi çalışmalı.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'ı Test Et</a>
        </div>
    </div>
</body>
</html>

