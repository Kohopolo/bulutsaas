<?php
/**
 * Custom Pages tablosunu kontrol et ve düzelt
 */

require_once '../config/database.php';

try {
    echo "<h2>🔍 Custom Pages Tablosu Kontrol Ediliyor...</h2>";
    
    // Tablo var mı kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'custom_pages'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ custom_pages tablosu bulunamadı! Oluşturuluyor...</p>";
        
        // Tabloyu oluştur
        $createTable = "
        CREATE TABLE custom_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            content_html LONGTEXT,
            content_css LONGTEXT,
            status ENUM('draft', 'published') DEFAULT 'draft',
            views INT DEFAULT 0,
            meta_title VARCHAR(255),
            meta_description TEXT,
            meta_keywords TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($createTable);
        echo "<p>✅ custom_pages tablosu oluşturuldu!</p>";
    } else {
        echo "<p>✅ custom_pages tablosu mevcut</p>";
    }
    
    // Mevcut kolonları kontrol et
    $stmt = $pdo->query("DESCRIBE custom_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Mevcut Kolonlar:</h3>";
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
    
    // Eksik kolonları kontrol et
    $columnNames = array_column($columns, 'Field');
    $requiredColumns = ['slug', 'views', 'meta_title', 'meta_description', 'meta_keywords'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (!empty($missingColumns)) {
        echo "<h3>⚠️ Eksik Kolonlar:</h3>";
        echo "<p>" . implode(', ', $missingColumns) . "</p>";
        
        // Eksik kolonları ekle
        foreach ($missingColumns as $col) {
            switch ($col) {
                case 'slug':
                    $pdo->exec("ALTER TABLE custom_pages ADD COLUMN slug VARCHAR(255) UNIQUE AFTER title");
                    echo "<p>✅ slug kolonu eklendi</p>";
                    break;
                case 'views':
                    $pdo->exec("ALTER TABLE custom_pages ADD COLUMN views INT DEFAULT 0 AFTER status");
                    echo "<p>✅ views kolonu eklendi</p>";
                    break;
                case 'meta_title':
                    $pdo->exec("ALTER TABLE custom_pages ADD COLUMN meta_title VARCHAR(255) AFTER views");
                    echo "<p>✅ meta_title kolonu eklendi</p>";
                    break;
                case 'meta_description':
                    $pdo->exec("ALTER TABLE custom_pages ADD COLUMN meta_description TEXT AFTER meta_title");
                    echo "<p>✅ meta_description kolonu eklendi</p>";
                    break;
                case 'meta_keywords':
                    $pdo->exec("ALTER TABLE custom_pages ADD COLUMN meta_keywords TEXT AFTER meta_description");
                    echo "<p>✅ meta_keywords kolonu eklendi</p>";
                    break;
            }
        }
    } else {
        echo "<p>✅ Tüm gerekli kolonlar mevcut!</p>";
    }
    
    // Mevcut sayfalar için slug oluştur
    $stmt = $pdo->query("SELECT id, title FROM custom_pages WHERE slug IS NULL OR slug = ''");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pages)) {
        echo "<h3>📝 Mevcut sayfalar için slug oluşturuluyor...</h3>";
        
        foreach ($pages as $page) {
            $slug = generateSlug($page['title'], $pdo, $page['id']);
            
            $updateStmt = $pdo->prepare("UPDATE custom_pages SET slug = ? WHERE id = ?");
            $updateStmt->execute([$slug, $page['id']]);
            
            echo "<p>✅ Sayfa '{$page['title']}' → slug: '{$slug}'</p>";
        }
    }
    
    // Son durumu göster
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>🎉 Tamamlandı!</h3>";
    echo "<p>Toplam sayfa sayısı: {$result['count']}</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

/**
 * URL-friendly slug oluştur
 */
function generateSlug($title, $pdo, $excludeId = null) {
    // Türkçe karakterleri dönüştür
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = str_replace(
        ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'],
        ['c', 'g', 'i', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'],
        $slug
    );
    
    // Özel karakterleri temizle
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Benzersizlik kontrolü
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $query = "SELECT id FROM custom_pages WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Custom Pages Tablosu Kontrol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Custom Pages Tablosu Kontrol Edildi!</h4>
            <p>Artık sayfa kaydetme işlemi çalışmalı.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'ı Test Et</a>
        </div>
    </div>
</body>
</html>

