<?php
/**
 * Manuel template import
 */

require_once '../config/database.php';
require_once '../includes/detailed_permission_functions.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    echo "<h2>📥 Manuel Template Import...</h2>";
    
    // Template dizinlerini kontrol et
    $templateDirs = ['default', 'elegant-hotel', 'luxury-hotel', 'modern-hotel', 'premium-hotel'];
    $importedCount = 0;
    
    foreach ($templateDirs as $templateName) {
        $templatePath = "../templates/{$templateName}/pages/";
        
        echo "<h3>📁 Template: {$templateName}</h3>";
        echo "<p>Dizin: {$templatePath}</p>";
        
        if (!is_dir($templatePath)) {
            echo "<p>❌ Dizin bulunamadı!</p>";
            continue;
        }
        
        // HTML dosyalarını bul
        $files = glob($templatePath . "*.html");
        echo "<p>📄 Bulunan dosyalar: " . count($files) . "</p>";
        
        foreach ($files as $file) {
            $fileName = basename($file, '.html');
            echo "<p>  - {$fileName}.html</p>";
            
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
                echo "<p>    ⏭️ Zaten mevcut: {$pageTitle}</p>";
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
            echo "<p>    ✅ İçe aktarıldı: {$pageTitle}</p>";
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
    <title>Manuel Template Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Manuel Import Tamamlandı!</h4>
            <p>Template sayfaları başarıyla aktarıldı.</p>
            <a href="page-list.php" class="btn btn-primary">Page List'e Git</a>
        </div>
    </div>
</body>
</html>

