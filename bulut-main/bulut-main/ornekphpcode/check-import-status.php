<?php
/**
 * Import durumunu kontrol et
 */

require_once '../config/database.php';

try {
    echo "<h2>🔍 Import Durumu Kontrol Ediliyor...</h2>";
    
    // Toplam sayfa sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📊 Toplam sayfa sayısı: {$result['count']}</p>";
    
    // Template'lere göre sayfa sayıları
    $stmt = $pdo->query("
        SELECT 
            page_template,
            COUNT(*) as count
        FROM custom_pages 
        WHERE page_template IS NOT NULL 
        GROUP BY page_template
        ORDER BY page_template
    ");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Template Sayfa Sayıları:</h3>";
    if (empty($templates)) {
        echo "<p>❌ Hiç template sayfası bulunamadı!</p>";
    } else {
        foreach ($templates as $template) {
            echo "<p>✅ {$template['page_template']}: {$template['count']} sayfa</p>";
        }
    }
    
    // Özel sayfalar
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages WHERE page_template IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📝 Özel sayfalar: {$result['count']}</p>";
    
    // Son 10 sayfayı göster
    $stmt = $pdo->query("
        SELECT page_title, page_template, created_at 
        FROM custom_pages 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📄 Son 10 Sayfa:</h3>";
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
    echo "<tr><th>Başlık</th><th>Template</th><th>Oluşturulma</th></tr>";
    foreach ($recentPages as $page) {
        $template = $page['page_template'] ?: 'Özel';
        echo "<tr>";
        echo "<td>{$page['page_title']}</td>";
        echo "<td>{$template}</td>";
        echo "<td>{$page['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Durumu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-info">
            <h4>📊 Import Durumu</h4>
            <p>Yukarıdaki bilgileri kontrol edin.</p>
            <a href="page-list.php" class="btn btn-primary">Page List'e Git</a>
        </div>
    </div>
</body>
</html>

