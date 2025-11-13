<?php
/**
 * Admin Sistemi Düzeltme
 * Mevcut veritabanı yapısını kullanarak admin sistemini düzelt
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<h1>Admin Sistemi Düzeltme</h1>";
echo "<pre>";

try {
    // 1. Mevcut kullanıcı tablosunu bul
    echo "1. Kullanıcı Tablosu Kontrolü...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    if ($stmt->rowCount() > 0) {
        $userTable = 'admins';
        echo "   ✅ Kullanıcı tablosu bulundu: admins\n\n";
    } else {
        // Admins tablosu yoksa oluştur
        echo "   ⚠️  'admins' tablosu bulunamadı, oluşturuluyor...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INT PRIMARY KEY AUTO_INCREMENT,
                kullanici_adi VARCHAR(50) UNIQUE NOT NULL,
                ad VARCHAR(50),
                soyad VARCHAR(50),
                email VARCHAR(100),
                sifre VARCHAR(255) NOT NULL,
                rol_id INT DEFAULT 1,
                aktif TINYINT(1) DEFAULT 1,
                olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
                son_giris DATETIME,
                INDEX idx_kullanici_adi (kullanici_adi),
                INDEX idx_rol (rol_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ 'admins' tablosu oluşturuldu\n\n";
        $userTable = 'admins';
    }
    
    // 2. İlk kullanıcıyı kontrol et / oluştur
    echo "2. Varsayılan Admin Kullanıcı Kontrolü...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM $userTable");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        echo "   ⚠️  Kullanıcı bulunamadı, varsayılan admin oluşturuluyor...\n";
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO $userTable (kullanici_adi, ad, soyad, email, sifre, rol_id, aktif)
            VALUES ('admin', 'Admin', 'User', 'admin@otel.com', ?, 1, 1)
        ");
        $stmt->execute([$defaultPassword]);
        echo "   ✅ Varsayılan admin oluşturuldu\n";
        echo "   📝 Kullanıcı Adı: admin\n";
        echo "   📝 Şifre: admin123\n\n";
    } else {
        echo "   ✅ " . $userCount . " kullanıcı mevcut\n";
        $stmt = $pdo->query("SELECT id, kullanici_adi, ad, soyad FROM $userTable LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   👤 Örnek: " . $user['kullanici_adi'] . " (" . $user['ad'] . " " . $user['soyad'] . ")\n\n";
    }
    
    // 3. Detailed permissions tablosunu kontrol et
    echo "3. Detaylı Yetkiler Tablosu Kontrolü...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'detailed_permissions'");
    if ($stmt->rowCount() == 0) {
        echo "   ⚠️  'detailed_permissions' tablosu bulunamadı, oluşturuluyor...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS detailed_permissions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permission_key VARCHAR(100) UNIQUE NOT NULL,
                permission_name VARCHAR(200) NOT NULL,
                module VARCHAR(50),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key (permission_key),
                INDEX idx_module (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ 'detailed_permissions' tablosu oluşturuldu\n\n";
    } else {
        echo "   ✅ 'detailed_permissions' tablosu mevcut\n\n";
    }
    
    // 4. User permissions tablosunu kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_detailed_permissions'");
    if ($stmt->rowCount() == 0) {
        echo "   ⚠️  'user_detailed_permissions' tablosu bulunamadı, oluşturuluyor...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_detailed_permissions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                permission_id INT NOT NULL,
                granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                granted_by INT,
                UNIQUE KEY unique_user_perm (user_id, permission_id),
                INDEX idx_user (user_id),
                INDEX idx_permission (permission_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ 'user_detailed_permissions' tablosu oluşturuldu\n\n";
    } else {
        echo "   ✅ 'user_detailed_permissions' tablosu mevcut\n\n";
    }
    
    // 5. AI Page Builder yetkilerini ekle
    echo "4. AI Page Builder Yetkileri Ekleniyor...\n";
    $permissions = [
        // AI Yetkileri
        ['ai_settings_view', 'AI Ayarlarını Görüntüleme', 'ai'],
        ['ai_settings_edit', 'AI Ayarlarını Düzenleme', 'ai'],
        ['ai_provider_manage', 'AI Provider Yönetimi', 'ai'],
        ['ai_usage_stats_view', 'AI Kullanım İstatistiklerini Görüntüleme', 'ai'],
        
        // Page Builder Yetkileri
        ['page_builder_view', 'Page Builder Görüntüleme', 'page_builder'],
        ['page_builder_create', 'Sayfa Oluşturma', 'page_builder'],
        ['page_builder_edit', 'Sayfa Düzenleme', 'page_builder'],
        ['page_builder_delete', 'Sayfa Silme', 'page_builder'],
        ['page_builder_publish', 'Sayfa Yayınlama', 'page_builder'],
        ['page_analytics_view', 'Sayfa Analitiklerini Görüntüleme', 'page_builder'],
        
        // Form Builder Yetkileri
        ['form_builder_view', 'Form Builder Görüntüleme', 'form_builder'],
        ['form_builder_create', 'Form Oluşturma', 'form_builder'],
        ['form_builder_edit', 'Form Düzenleme', 'form_builder'],
        ['form_builder_delete', 'Form Silme', 'form_builder'],
        ['form_submissions_view', 'Form Gönderilerini Görüntüleme', 'form_builder'],
        ['form_submissions_export', 'Form Gönderilerini Dışa Aktarma', 'form_builder'],
    ];
    
    $addedCount = 0;
    foreach ($permissions as $perm) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO detailed_permissions (permission_key, permission_name, module, description)
            VALUES (?, ?, ?, ?)
        ");
        if ($stmt->execute([$perm[0], $perm[1], $perm[2], $perm[1]])) {
            if ($stmt->rowCount() > 0) {
                $addedCount++;
                echo "   ✅ " . $perm[0] . "\n";
            }
        }
    }
    echo "   📊 " . $addedCount . " yeni yetki eklendi\n\n";
    
    // 6. İlk kullanıcıya tüm yetkileri ver
    echo "5. Admin Kullanıcıya Tüm Yetkiler Veriliyor...\n";
    $stmt = $pdo->query("SELECT id FROM $userTable ORDER BY id ASC LIMIT 1");
    $firstUserId = $stmt->fetchColumn();
    
    if ($firstUserId) {
        $stmt = $pdo->query("SELECT id FROM detailed_permissions");
        $allPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $grantedCount = 0;
        foreach ($allPermissions as $permId) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO user_detailed_permissions (user_id, permission_id)
                VALUES (?, ?)
            ");
            if ($stmt->execute([$firstUserId, $permId])) {
                if ($stmt->rowCount() > 0) {
                    $grantedCount++;
                }
            }
        }
        echo "   ✅ User ID: " . $firstUserId . " → " . $grantedCount . " yetki verildi\n\n";
    }
    
    // 7. Özet
    echo "═══════════════════════════════════════════════\n";
    echo "ÖZET\n";
    echo "═══════════════════════════════════════════════\n\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM $userTable");
    echo "✅ Toplam Kullanıcı: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM detailed_permissions");
    echo "✅ Toplam Yetki: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_detailed_permissions");
    echo "✅ Toplam Yetki Ataması: " . $stmt->fetchColumn() . "\n\n";
    
    echo "✅ Admin sistemi başarıyla düzeltildi!\n\n";
    
    echo "═══════════════════════════════════════════════\n";
    echo "GİRİŞ BİLGİLERİ\n";
    echo "═══════════════════════════════════════════════\n\n";
    echo "URL: http://localhost/otelonofexe/web/admin/login.php\n";
    echo "Kullanıcı Adı: admin\n";
    echo "Şifre: admin123\n\n";
    
} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<a href='ai-page-builder-test-suite.php' class='btn btn-primary'>Test Suite'e Git</a>";
?>

<style>
.btn {
    display: inline-block;
    padding: 10px 20px;
    margin: 10px 5px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
}
</style>


