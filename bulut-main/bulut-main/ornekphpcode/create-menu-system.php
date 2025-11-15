<?php
/**
 * Menü Sistemi Tablolarını Oluştur
 */

require_once '../config/database.php';

echo "<h2>🔧 Menü Sistemi Tabloları Oluşturuluyor...</h2>";

try {
    // 1. Menü tablosu
    $createMenuTable = "
    CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        url VARCHAR(500),
        slug VARCHAR(255),
        icon VARCHAR(100),
        parent_id INT DEFAULT NULL,
        menu_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        is_in_footer TINYINT(1) DEFAULT 0,
        target VARCHAR(20) DEFAULT '_self',
        css_class VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createMenuTable);
    echo "<p>✅ menu_items tablosu oluşturuldu!</p>";
    
    // 2. Sayfa-menü ilişki tablosu
    $createPageMenuTable = "
    CREATE TABLE IF NOT EXISTS page_menu_relations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id INT NOT NULL,
        menu_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (page_id) REFERENCES custom_pages(id) ON DELETE CASCADE,
        FOREIGN KEY (menu_id) REFERENCES menu_items(id) ON DELETE CASCADE,
        UNIQUE KEY unique_page_menu (page_id, menu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createPageMenuTable);
    echo "<p>✅ page_menu_relations tablosu oluşturuldu!</p>";
    
    // 3. Örnek menü öğeleri ekle
    $exampleMenus = [
        [
            'title' => 'Ana Sayfa',
            'url' => '/',
            'slug' => 'home',
            'icon' => 'fas fa-home',
            'parent_id' => null,
            'menu_order' => 1,
            'is_active' => 1,
            'is_in_footer' => 1
        ],
        [
            'title' => 'Odalar',
            'url' => '/odalar',
            'slug' => 'rooms',
            'icon' => 'fas fa-bed',
            'parent_id' => null,
            'menu_order' => 2,
            'is_active' => 1,
            'is_in_footer' => 1
        ],
        [
            'title' => 'Rezervasyon',
            'url' => '/rezervasyon',
            'slug' => 'reservation',
            'icon' => 'fas fa-calendar-check',
            'parent_id' => null,
            'menu_order' => 3,
            'is_active' => 1,
            'is_in_footer' => 0
        ],
        [
            'title' => 'Hakkımızda',
            'url' => '/hakkimizda',
            'slug' => 'about',
            'icon' => 'fas fa-info-circle',
            'parent_id' => null,
            'menu_order' => 4,
            'is_active' => 1,
            'is_in_footer' => 1
        ],
        [
            'title' => 'İletişim',
            'url' => '/iletisim',
            'slug' => 'contact',
            'icon' => 'fas fa-envelope',
            'parent_id' => null,
            'menu_order' => 5,
            'is_active' => 1,
            'is_in_footer' => 1
        ]
    ];
    
    foreach ($exampleMenus as $menu) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO menu_items 
            (title, url, slug, icon, parent_id, menu_order, is_active, is_in_footer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $menu['title'],
            $menu['url'],
            $menu['slug'],
            $menu['icon'],
            $menu['parent_id'],
            $menu['menu_order'],
            $menu['is_active'],
            $menu['is_in_footer']
        ]);
    }
    
    echo "<p>✅ Örnek menü öğeleri eklendi!</p>";
    
    // 4. custom_pages tablosuna menü alanları ekle
    $addMenuFields = "
    ALTER TABLE custom_pages 
    ADD COLUMN IF NOT EXISTS is_in_menu TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS menu_order INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS menu_parent_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS menu_icon VARCHAR(100) DEFAULT NULL
    ";
    
    $pdo->exec($addMenuFields);
    echo "<p>✅ custom_pages tablosuna menü alanları eklendi!</p>";
    
    // 5. Tablo yapılarını göster
    echo "<h3>📋 Oluşturulan Tablolar:</h3>";
    
    $tables = ['menu_items', 'page_menu_relations', 'custom_pages'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>$table:</h4>";
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
    }
    
    echo "<h3>🎉 Menü Sistemi Başarıyla Oluşturuldu!</h3>";
    
} catch (Exception $e) {
    echo "<h2>❌ Hata:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Menü Sistemi Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Menü Sistemi Hazır!</h4>
            <p>Artık menü yönetimi ve drag-drop sıralama özelliklerini kullanabilirsiniz.</p>
            <a href="menu-manager.php" class="btn btn-primary">Menü Yöneticisi</a>
            <a href="page-builder-ultimate-v3.php" class="btn btn-secondary">Page Builder</a>
        </div>
    </div>
</body>
</html>

