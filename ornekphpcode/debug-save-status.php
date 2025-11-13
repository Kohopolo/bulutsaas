<?php
/**
 * Save Status Debug
 */

session_start();
require_once '../config/database.php';
require_once '../includes/detailed_permission_functions.php';

echo "<h2>🔍 Save Status Debug</h2>";

// Session kontrolü
echo "<h3>1. Session Kontrolü:</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'YOK') . "<br>";
echo "CSRF Token: " . ($_SESSION['csrf_token'] ?? 'YOK') . "<br>";

// Yetki kontrolü
echo "<h3>2. Yetki Kontrolü:</h3>";
echo "page_builder_create: " . (hasDetailedPermission('page_builder_create') ? '✅ VAR' : '❌ YOK') . "<br>";
echo "page_builder_edit: " . (hasDetailedPermission('page_builder_edit') ? '✅ VAR' : '❌ YOK') . "<br>";
echo "page_builder_publish: " . (hasDetailedPermission('page_builder_publish') ? '✅ VAR' : '❌ YOK') . "<br>";

// Test POST verisi
echo "<h3>3. Test POST Verisi:</h3>";
if ($_POST) {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $status = $_POST['status'] ?? 'draft';
    echo "Status: " . $status . "<br>";
    echo "Status === 'published': " . ($status === 'published' ? 'TRUE' : 'FALSE') . "<br>";
    echo "is_active değeri: " . (($status === 'published') ? 1 : 0) . "<br>";
} else {
    echo "POST verisi yok. Test formu:<br>";
    echo '<form method="POST">
        <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
        <input type="text" name="title" value="Test Sayfa" placeholder="Başlık"><br><br>
        <input type="text" name="html" value="<p>Test içerik</p>" placeholder="HTML"><br><br>
        <select name="status">
            <option value="draft">Taslak</option>
            <option value="published">Yayınla</option>
        </select><br><br>
        <button type="submit">Test Et</button>
    </form>';
}

// Veritabanı testi
echo "<h3>4. Veritabanı Testi:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages");
    $result = $stmt->fetch();
    echo "Toplam sayfa sayısı: " . $result['count'] . "<br>";
    
    $stmt = $pdo->query("SELECT page_title, is_active FROM custom_pages ORDER BY id DESC LIMIT 5");
    $pages = $stmt->fetchAll();
    echo "<h4>Son 5 Sayfa:</h4>";
    echo "<table border='1'>";
    echo "<tr><th>Başlık</th><th>Durum</th></tr>";
    foreach ($pages as $page) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($page['page_title']) . "</td>";
        echo "<td>" . ($page['is_active'] ? '✅ Yayında' : '⏸️ Taslak') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}

// POST işleme
if ($_POST && isset($_POST['title'])) {
    echo "<h3>5. Kaydetme Testi:</h3>";
    
    $title = trim($_POST['title']);
    $html = $_POST['html'];
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
            $_SESSION['user_id']
        ]);
        
        $pageId = $pdo->lastInsertId();
        
        echo "✅ Sayfa kaydedildi!<br>";
        echo "Page ID: " . $pageId . "<br>";
        echo "Title: " . $title . "<br>";
        echo "Status: " . $status . "<br>";
        echo "is_active: " . $isActive . "<br>";
        echo "Slug: " . $slug . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Save Status Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-info">
            <h4>🔍 Debug Tamamlandı!</h4>
            <p>Yukarıdaki bilgileri kontrol edin.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'a Dön</a>
        </div>
    </div>
</body>
</html>

