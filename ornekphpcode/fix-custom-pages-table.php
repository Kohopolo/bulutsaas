<?php
/**
 * Custom Pages tablosunu düzelt
 */

require_once '../config/database.php';

try {
    echo "<h2>🔧 Custom Pages Tablosu Düzeltiliyor...</h2>";
    
    // Mevcut tabloyu kontrol et
    $stmt = $pdo->query("DESCRIBE custom_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Mevcut kolonlar: " . implode(', ', $columns) . "</p>";
    
    // Eksik kolonları ekle
    $alterQueries = [];
    
    if (!in_array('slug', $columns)) {
        $alterQueries[] = "ADD COLUMN slug VARCHAR(255) UNIQUE AFTER title";
        echo "<p>✅ slug kolonu eklenecek</p>";
    }
    
    if (!in_array('views', $columns)) {
        $alterQueries[] = "ADD COLUMN views INT DEFAULT 0 AFTER status";
        echo "<p>✅ views kolonu eklenecek</p>";
    }
    
    if (!in_array('meta_title', $columns)) {
        $alterQueries[] = "ADD COLUMN meta_title VARCHAR(255) AFTER views";
        echo "<p>✅ meta_title kolonu eklenecek</p>";
    }
    
    if (!in_array('meta_description', $columns)) {
        $alterQueries[] = "ADD COLUMN meta_description TEXT AFTER meta_title";
        echo "<p>✅ meta_description kolonu eklenecek</p>";
    }
    
    if (!in_array('meta_keywords', $columns)) {
        $alterQueries[] = "ADD COLUMN meta_keywords TEXT AFTER meta_description";
        echo "<p>✅ meta_keywords kolonu eklenecek</p>";
    }
    
    // Kolonları ekle
    foreach ($alterQueries as $query) {
        $sql = "ALTER TABLE custom_pages " . $query;
        $pdo->exec($sql);
        echo "<p>✅ Çalıştırıldı: " . $query . "</p>";
    }
    
    // Mevcut sayfalar için slug oluştur
    $stmt = $pdo->query("SELECT id, title FROM custom_pages WHERE slug IS NULL OR slug = ''");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pages)) {
        echo "<p>📝 Mevcut sayfalar için slug oluşturuluyor...</p>";
        
        foreach ($pages as $page) {
            $slug = generateSlug($page['title'], $pdo, $page['id']);
            
            $updateStmt = $pdo->prepare("UPDATE custom_pages SET slug = ? WHERE id = ?");
            $updateStmt->execute([$slug, $page['id']]);
            
            echo "<p>✅ Sayfa '{$page['title']}' → slug: '{$slug}'</p>";
        }
    }
    
    // Son durumu kontrol et
    $stmt = $pdo->query("DESCRIBE custom_pages");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>🎉 Tamamlandı!</h3>";
    echo "<p>Final kolonlar: " . implode(', ', $finalColumns) . "</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
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
    <title>Custom Pages Tablosu Düzeltildi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Custom Pages Tablosu Başarıyla Düzeltildi!</h4>
            <p>Artık sayfa kaydetme işlemi çalışmalı.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'ı Test Et</a>
        </div>
    </div>
</body>
</html>

