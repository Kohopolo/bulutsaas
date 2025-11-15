<?php
/**
 * Template sayfalarını database'e aktar
 */

require_once 'config/database.php';
require_once 'includes/detailed_permission_functions.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Yetki kontrolü
if (!hasDetailedPermission('page_builder_create')) {
    die('Yetkiniz yok!');
}

try {
    echo "<h2>📥 Template Sayfaları Aktarılıyor...</h2>";
    
    // Template dizinlerini tara
    $templateDirs = ['default', 'elegant-hotel', 'luxury-hotel', 'modern-hotel', 'premium-hotel'];
    $importedCount = 0;
    
    foreach ($templateDirs as $templateName) {
        $templatePath = "templates/{$templateName}/pages/";
        
        if (!is_dir($templatePath)) {
            echo "<p>⚠️ Template bulunamadı: {$templateName}</p>";
            continue;
        }
        
        echo "<h3>📁 Template: {$templateName}</h3>";
        
        // HTML dosyalarını bul
        $files = glob($templatePath . "*.html");
        
        foreach ($files as $file) {
            $fileName = basename($file, '.html');
            $fileContent = file_get_contents($file);
            
            // Sayfa başlığını oluştur
            $pageTitle = ucfirst(str_replace(['-', '_'], ' ', $fileName));
            
            // Slug oluştur
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $fileName));
            $slug = trim($slug, '-');
            
            // Benzersiz slug kontrolü
            $originalSlug = $slug;
            $counter = 1;
            
            while (true) {
                $stmt = $pdo->prepare("SELECT id FROM custom_pages WHERE page_slug = ?");
                $stmt->execute([$slug]);
                
                if ($stmt->rowCount() == 0) {
                    break;
                }
                
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            // Sayfa zaten var mı kontrol et
            $stmt = $pdo->prepare("SELECT id FROM custom_pages WHERE page_slug = ?");
            $stmt->execute([$slug]);
            
            if ($stmt->rowCount() > 0) {
                echo "<p>⏭️ Zaten mevcut: {$pageTitle}</p>";
                continue;
            }
            
            // Sayfayı database'e ekle
            $stmt = $pdo->prepare("
                INSERT INTO custom_pages 
                (page_title, page_slug, page_content, page_template, is_active, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $pageTitle,
                $slug,
                $fileContent,
                $templateName,
                1, // Aktif
                $_SESSION['user_id']
            ]);
            
            $importedCount++;
            echo "<p>✅ İçe aktarıldı: {$pageTitle} ({$templateName})</p>";
        }
    }
    
    echo "<h3>🎉 Tamamlandı!</h3>";
    echo "<p>Toplam {$importedCount} sayfa içe aktarıldı.</p>";
    
    // Toplam sayfa sayısını göster
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_pages");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📊 Toplam sayfa sayısı: {$result['count']}</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Template Sayfaları Aktarıldı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Template Sayfaları Başarıyla Aktarıldı!</h4>
            <p>Artık tüm template sayfaları Page Builder'da düzenlenebilir.</p>
            <div class="d-grid gap-2">
                <a href="page-list.php" class="btn btn-primary">Sayfa Listesini Görüntüle</a>
                <a href="page-builder-ultimate-v3.php" class="btn btn-success">Page Builder'ı Aç</a>
            </div>
        </div>
    </div>
</body>
</html>
