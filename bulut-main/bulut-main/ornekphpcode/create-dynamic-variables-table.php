<?php
/**
 * Dinamik değişkenler tablosunu oluştur
 */

require_once '../config/database.php';

try {
    echo "<h2>🔧 Dinamik Değişkenler Tablosu Oluşturuluyor...</h2>";
    
    // Tabloyu oluştur
    $createTable = "
    CREATE TABLE IF NOT EXISTS dynamic_variables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        variable_name VARCHAR(100) UNIQUE NOT NULL,
        variable_title VARCHAR(255) NOT NULL,
        variable_description TEXT,
        variable_type ENUM('text', 'html', 'list', 'form', 'gallery', 'custom') DEFAULT 'text',
        variable_content LONGTEXT,
        variable_settings JSON,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTable);
    echo "<p>✅ dynamic_variables tablosu oluşturuldu!</p>";
    
    // Örnek değişkenler ekle
    $exampleVariables = [
        [
            'variable_name' => 'oda_tipleri_listesi',
            'variable_title' => 'Oda Tipleri Listesi',
            'variable_description' => 'Veritabanından oda tiplerini otomatik listeler',
            'variable_type' => 'list',
            'variable_content' => 'SELECT * FROM oda_tipleri WHERE is_active = 1 ORDER BY sira ASC',
            'variable_settings' => json_encode([
                'template' => 'card',
                'columns' => 3,
                'show_price' => true,
                'show_capacity' => true
            ])
        ],
        [
            'variable_name' => 'rezervasyon_formu',
            'variable_title' => 'Rezervasyon Formu',
            'variable_description' => 'Rezervasyon için form oluşturur',
            'variable_type' => 'form',
            'variable_content' => 'rezervasyon_form_template',
            'variable_settings' => json_encode([
                'fields' => ['giris_tarihi', 'cikis_tarihi', 'yetiskin_sayisi', 'cocuk_sayisi'],
                'action' => 'rezervasyon.php',
                'method' => 'POST'
            ])
        ],
        [
            'variable_name' => 'galeri_resimleri',
            'variable_title' => 'Galeri Resimleri',
            'variable_description' => 'Galeri resimlerini otomatik listeler',
            'variable_type' => 'gallery',
            'variable_content' => 'SELECT * FROM galeri_resimleri WHERE is_active = 1 ORDER BY sira ASC LIMIT 12',
            'variable_settings' => json_encode([
                'columns' => 3,
                'lightbox' => true,
                'show_title' => true
            ])
        ]
    ];
    
    foreach ($exampleVariables as $var) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO dynamic_variables 
            (variable_name, variable_title, variable_description, variable_type, variable_content, variable_settings, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 1, 1)
        ");
        
        $stmt->execute([
            $var['variable_name'],
            $var['variable_title'],
            $var['variable_description'],
            $var['variable_type'],
            $var['variable_content'],
            $var['variable_settings']
        ]);
    }
    
    echo "<p>✅ Örnek değişkenler eklendi!</p>";
    
    // Tablo yapısını göster
    $stmt = $pdo->query("DESCRIBE dynamic_variables");
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
    
    echo "<h3>🎉 Başarıyla Tamamlandı!</h3>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dinamik Değişkenler Tablosu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Dinamik Değişkenler Tablosu Oluşturuldu!</h4>
            <p>Artık kullanıcılar kendi dinamik değişkenlerini oluşturabilir.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'ı Test Et</a>
        </div>
    </div>
</body>
</html>

