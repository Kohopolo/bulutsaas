<?php
/**
 * Save Issue Debug
 */

session_start();
require_once '../config/database.php';
require_once '../includes/detailed_permission_functions.php';

echo "<h2>🔍 Save Issue Debug</h2>";

// Test POST verisi
if ($_POST) {
    echo "<h3>📥 POST Verisi:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $status = $_POST['status'] ?? 'draft';
    echo "<p><strong>Status:</strong> " . $status . "</p>";
    echo "<p><strong>Status === 'published':</strong> " . ($status === 'published' ? 'TRUE' : 'FALSE') . "</p>";
    echo "<p><strong>is_active değeri:</strong> " . (($status === 'published') ? 1 : 0) . "</p>";
    
    // Gerçek kaydetme işlemi
    if (isset($_POST['title'])) {
        $title = trim($_POST['title']);
        $html = $_POST['html'] ?? '<p>Test içerik</p>';
        $status = $_POST['status'];
        
        try {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
            $slug = trim($slug, '-');
            
            $stmt = $pdo->prepare("
                INSERT INTO custom_pages 
                (page_title, page_slug, page_content, is_active, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $isActive = ($status === 'published') ? 1 : 0;
            
            $stmt->execute([
                $title,
                $slug,
                $html,
                $isActive,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $pageId = $pdo->lastInsertId();
            
            echo "<div style='background:#d4edda; padding:15px; margin:15px 0; border-radius:5px;'>";
            echo "<h4>✅ Sayfa Kaydedildi!</h4>";
            echo "<p><strong>Page ID:</strong> " . $pageId . "</p>";
            echo "<p><strong>Başlık:</strong> " . $title . "</p>";
            echo "<p><strong>Status:</strong> " . $status . "</p>";
            echo "<p><strong>is_active:</strong> " . $isActive . "</p>";
            echo "<p><strong>Slug:</strong> " . $slug . "</p>";
            echo "</div>";
            
            // Hemen kontrol et
            $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
            $stmt->execute([$pageId]);
            $savedPage = $stmt->fetch();
            
            echo "<div style='background:#fff3cd; padding:15px; margin:15px 0; border-radius:5px;'>";
            echo "<h4>🔍 Veritabanından Kontrol:</h4>";
            echo "<p><strong>is_active:</strong> " . $savedPage['is_active'] . "</p>";
            echo "<p><strong>Durum:</strong> " . ($savedPage['is_active'] ? 'Yayında' : 'Taslak') . "</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div style='background:#f8d7da; padding:15px; margin:15px 0; border-radius:5px;'>";
            echo "<h4>❌ Hata:</h4>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
} else {
    echo "<h3>🧪 Test Formu:</h3>";
    echo '<form method="POST" style="background:#f8f9fa; padding:20px; border-radius:5px;">';
    echo '<div style="margin-bottom:15px;">';
    echo '<label><strong>Başlık:</strong></label><br>';
    echo '<input type="text" name="title" value="Debug Test" style="width:300px; padding:5px;" required>';
    echo '</div>';
    echo '<div style="margin-bottom:15px;">';
    echo '<label><strong>Durum:</strong></label><br>';
    echo '<select name="status" style="padding:5px;">';
    echo '<option value="draft">Taslak</option>';
    echo '<option value="published" selected>Yayınla</option>';
    echo '</select>';
    echo '</div>';
    echo '<div style="margin-bottom:15px;">';
    echo '<label><strong>HTML:</strong></label><br>';
    echo '<textarea name="html" style="width:100%; height:100px; padding:5px;"><p>Test içerik</p></textarea>';
    echo '</div>';
    echo '<button type="submit" style="background:#007bff; color:white; padding:10px 20px; border:none; border-radius:5px;">Test Et</button>';
    echo '</form>';
}

// Mevcut sayfaları listele
echo "<h3>📋 Mevcut Sayfalar:</h3>";
try {
    $stmt = $pdo->query("SELECT id, page_title, page_slug, is_active, created_at FROM custom_pages ORDER BY id DESC LIMIT 10");
    $pages = $stmt->fetchAll();
    
    if (empty($pages)) {
        echo "<p>Henüz sayfa yok.</p>";
    } else {
        echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>ID</th><th>Başlık</th><th>Slug</th><th>is_active</th><th>Durum</th><th>Tarih</th>";
        echo "</tr>";
        
        foreach ($pages as $page) {
            echo "<tr>";
            echo "<td>" . $page['id'] . "</td>";
            echo "<td>" . htmlspecialchars($page['page_title']) . "</td>";
            echo "<td>" . htmlspecialchars($page['page_slug']) . "</td>";
            echo "<td>" . $page['is_active'] . "</td>";
            echo "<td>" . ($page['is_active'] ? '✅ Yayında' : '⏸️ Taslak') . "</td>";
            echo "<td>" . $page['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Save Issue Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-info">
            <h4>🔍 Debug Tamamlandı!</h4>
            <p>Yukarıdaki bilgileri kontrol edin.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'a Dön</a>
            <a href="page-list.php" class="btn btn-secondary">Sayfa Listesi</a>
        </div>
    </div>
</body>
</html>

