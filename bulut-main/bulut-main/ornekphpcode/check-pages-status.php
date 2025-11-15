<?php
/**
 * Sayfa durumlarını kontrol et
 */

require_once '../config/database.php';

echo "<h2>📋 Sayfa Durumları</h2>";

try {
    $stmt = $pdo->query("SELECT id, page_title, page_slug, is_active, created_at, updated_at FROM custom_pages ORDER BY id DESC");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pages)) {
        echo "<p>Henüz sayfa yok.</p>";
    } else {
        echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>ID</th><th>Başlık</th><th>Slug</th><th>Durum</th><th>Oluşturma</th><th>Güncelleme</th>";
        echo "</tr>";
        
        foreach ($pages as $page) {
            echo "<tr>";
            echo "<td>" . $page['id'] . "</td>";
            echo "<td>" . htmlspecialchars($page['page_title']) . "</td>";
            echo "<td>" . htmlspecialchars($page['page_slug']) . "</td>";
            echo "<td>" . ($page['is_active'] ? '✅ Yayında' : '⏸️ Taslak') . "</td>";
            echo "<td>" . $page['created_at'] . "</td>";
            echo "<td>" . $page['updated_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Test sayfası oluştur
    echo "<h3>🧪 Test Sayfası Oluştur</h3>";
    echo '<form method="POST">';
    echo '<input type="hidden" name="action" value="create_test">';
    echo '<input type="text" name="title" value="Test Yayın" placeholder="Başlık" required><br><br>';
    echo '<select name="status">';
    echo '<option value="draft">Taslak</option>';
    echo '<option value="published">Yayınla</option>';
    echo '</select><br><br>';
    echo '<button type="submit">Test Sayfası Oluştur</button>';
    echo '</form>';
    
    if ($_POST && $_POST['action'] === 'create_test') {
        $title = trim($_POST['title']);
        $status = $_POST['status'];
        
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        
        $stmt = $pdo->prepare("
            INSERT INTO custom_pages 
            (page_title, page_slug, page_content, is_active, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        $isActive = ($status === 'published') ? 1 : 0;
        
        $stmt->execute([
            $title,
            $slug,
            '<p>Test içerik</p>',
            $isActive
        ]);
        
        echo "<div style='background:#d4edda; padding:10px; margin:10px 0; border-radius:5px;'>";
        echo "✅ Test sayfası oluşturuldu!<br>";
        echo "Başlık: " . $title . "<br>";
        echo "Status: " . $status . "<br>";
        echo "is_active: " . $isActive . "<br>";
        echo "Slug: " . $slug . "<br>";
        echo "</div>";
        
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sayfa Durumları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-info">
            <h4>📋 Sayfa Durumları Kontrol Edildi!</h4>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'a Dön</a>
        </div>
    </div>
</body>
</html>

