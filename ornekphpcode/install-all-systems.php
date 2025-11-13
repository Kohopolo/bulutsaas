<?php
/**
 * Tüm Sistemlerin Toplu Kurulumu
 * Yetki sistemi + Script yönetimi
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<h1>🚀 Toplu Sistem Kurulumu</h1>";
echo "<pre>";

try {
    echo "═══════════════════════════════════════════════════\n";
    echo "📦 KURULUM BAŞLIYOR...\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    // 1. Yetki sistemini kur
    echo "1️⃣  YETKİ SİSTEMİ KURULUMU\n";
    echo "─────────────────────────────────────────────────\n";
    
    // detailed_permissions tablosu var mı kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'detailed_permissions'");
    if ($stmt->rowCount() == 0) {
        echo "   ⚠️  detailed_permissions tablosu yok, oluşturuluyor...\n";
        
        // Yetki sistemi tablolarını oluştur
        $sql = "
        CREATE TABLE IF NOT EXISTS detailed_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            permission_key VARCHAR(100) UNIQUE NOT NULL,
            permission_name VARCHAR(255) NOT NULL,
            permission_description TEXT,
            module_name VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_key (permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_permission (role_id, permission_id),
            FOREIGN KEY (permission_id) REFERENCES detailed_permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql);
        echo "   ✅ Yetki tabloları oluşturuldu\n";
    } else {
        echo "   ✅ Yetki tabloları zaten var\n";
    }
    
    // 2. Script yönetim tablolarını kur
    echo "\n2️⃣  SCRIPT YÖNETİMİ TABLOLARI\n";
    echo "─────────────────────────────────────────────────\n";
    
    $sqlFile = '../sql/create_script_management_tables.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // SQL'i statement'lara böl
        $statements = explode(';', $sql);
        $successCount = 0;
        $skipCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $skipCount++;
                } else {
                    echo "   ⚠️  Hata: " . substr($e->getMessage(), 0, 100) . "...\n";
                }
            }
        }
        
        echo "   ✅ $successCount sorgu başarılı\n";
        echo "   ⏭️  $skipCount sorgu atlandı (zaten var)\n";
    } else {
        echo "   ❌ SQL dosyası bulunamadı: $sqlFile\n";
    }
    
    // 3. Tabloları kontrol et
    echo "\n3️⃣  TABLO KONTROLÜ\n";
    echo "─────────────────────────────────────────────────\n";
    
    $tables = [
        'detailed_permissions' => 'Yetki Tablosu',
        'role_permissions' => 'Rol-Yetki İlişkisi',
        'site_scripts' => 'Özel Scriptler',
        'site_script_settings' => 'Hazır Servisler',
        'script_change_logs' => 'Değişiklik Logları'
    ];
    
    $allOk = true;
    foreach ($tables as $table => $label) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "   ✅ $label → $count kayıt\n";
        } else {
            echo "   ❌ $label → BULUNAMADI!\n";
            $allOk = false;
        }
    }
    
    // 4. Yetkileri kontrol et
    echo "\n4️⃣  YETKİ KAYITLARI\n";
    echo "─────────────────────────────────────────────────\n";
    
    $requiredPerms = [
        'script_yonetimi_goruntule',
        'script_yonetimi_duzenle',
        'script_yonetimi_sil',
        'script_yonetimi_aktif_pasif'
    ];
    
    foreach ($requiredPerms as $perm) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM detailed_permissions WHERE permission_key = ?");
        $stmt->execute([$perm]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            echo "   ✅ $perm\n";
        } else {
            echo "   ⚠️  $perm → Eksik, ekleniyor...\n";
            
            // Yetkiyi ekle
            $permLabels = [
                'script_yonetimi_goruntule' => 'Script Yönetimi Görüntüleme',
                'script_yonetimi_duzenle' => 'Script Düzenleme',
                'script_yonetimi_sil' => 'Script Silme',
                'script_yonetimi_aktif_pasif' => 'Script Aktif/Pasif'
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO detailed_permissions (permission_key, permission_name, module_name) 
                VALUES (?, ?, 'Sistem Ayarları')
                ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name)
            ");
            $stmt->execute([$perm, $permLabels[$perm]]);
            echo "      → Eklendi!\n";
        }
    }
    
    // 5. Hazır servisleri kontrol et
    echo "\n5️⃣  HAZIR SERVİSLER\n";
    echo "─────────────────────────────────────────────────\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM site_script_settings");
    $serviceCount = $stmt->fetchColumn();
    
    if ($serviceCount > 0) {
        echo "   ✅ $serviceCount hazır servis tanımlı\n";
        
        // Kategorilere göre say
        $stmt = $pdo->query("
            SELECT service_category, COUNT(*) as count 
            FROM site_script_settings 
            GROUP BY service_category
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as $cat) {
            echo "      • " . ucfirst($cat['service_category']) . ": {$cat['count']}\n";
        }
    } else {
        echo "   ⚠️  Hazır servis yok\n";
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    if ($allOk) {
        echo "✅  KURULUM BAŞARIYLA TAMAMLANDI!\n";
    } else {
        echo "⚠️  KURULUM TAMAMLANDI AMA BAZI HATALAR VAR\n";
    }
    echo "═══════════════════════════════════════════════════\n\n";
    
    echo "📍 Sonraki Adımlar:\n\n";
    echo "   1. <a href='test-script-system.php'>Sistem Testi</a> → Tüm kontrolleri yap\n";
    echo "   2. <a href='script-yonetimi.php'>Script Yönetimi</a> → Servisleri ekle\n\n";
    
} catch (Exception $e) {
    echo "\n❌ FATAL HATA: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1 {
        color: #333;
        border-bottom: 3px solid #4CAF50;
        padding-bottom: 10px;
    }
    pre {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        line-height: 1.8;
    }
    a {
        color: #4CAF50;
        text-decoration: none;
        font-weight: bold;
    }
    a:hover {
        text-decoration: underline;
    }
</style>



