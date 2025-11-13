<?php
/**
 * Script Yönetim Sistemi Tablo Kurulumu
 */

require_once '../config/database.php';
require_once '../includes/session_security.php';

// Sadece superadmin çalıştırabilir
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'superadmin') {
    die('Yetkisiz erişim!');
}

echo "<h2>Script Yönetim Sistemi Kurulumu</h2>";
echo "<pre>";

try {
    // SQL dosyasını oku
    $sql = file_get_contents('../sql/create_script_management_tables.sql');
    
    if ($sql === false) {
        throw new Exception('SQL dosyası okunamadı!');
    }
    
    echo "1. SQL dosyası okundu...\n";
    
    // Sorguları ayır ve çalıştır
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "2. " . count($statements) . " sorgu bulundu...\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✅ Sorgu " . ($index + 1) . " başarılı\n";
        } catch (PDOException $e) {
            // Tablo zaten varsa hatayı yoksay
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                $errorCount++;
                echo "❌ Sorgu " . ($index + 1) . " HATA: " . $e->getMessage() . "\n";
            } else {
                $successCount++;
                echo "⚠️ Sorgu " . ($index + 1) . " (zaten mevcut, atlandı)\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Başarılı: $successCount\n";
    echo "❌ Hatalı: $errorCount\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Tabloları kontrol et
    echo "3. Tablolar kontrol ediliyor...\n";
    $tables = ['site_scripts', 'site_script_settings', 'script_change_logs'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "   ✅ $table ($count kayıt)\n";
        } else {
            echo "   ❌ $table (bulunamadı!)\n";
        }
    }
    
    echo "\n✅ KURULUM TAMAMLANDI!\n";
    echo "\n<a href='script-yonetimi.php'>Script Yönetimi Sayfasına Git →</a>";
    
} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo "\nDetay: " . $e->getTraceAsString();
}

echo "</pre>";
?>



