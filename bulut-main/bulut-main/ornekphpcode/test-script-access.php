<?php
// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Script Yönetimi Erişim Testi</h1>";
echo "<pre>";

echo "1. SESSION Kontrolü:\n";
echo "   user_id: " . ($_SESSION['user_id'] ?? 'YOK') . "\n";
echo "   role: " . ($_SESSION['role'] ?? 'YOK') . "\n\n";

echo "2. Functions dosyası yükleniyor...\n";
require_once '../includes/detailed_permission_functions.php';
require_once '../config/database.php';
echo "   ✅ Yüklendi\n\n";

echo "3. Yetki kontrolü:\n";
if (function_exists('hasDetailedPermission')) {
    $hasPermission = hasDetailedPermission('script_yonetimi_goruntule');
    echo "   hasDetailedPermission('script_yonetimi_goruntule'): " . ($hasPermission ? 'TRUE ✅' : 'FALSE ❌') . "\n\n";
} else {
    echo "   ❌ hasDetailedPermission fonksiyonu bulunamadı!\n\n";
}

echo "4. Database kontrolü:\n";
if (isset($pdo)) {
    echo "   ✅ PDO bağlantısı var\n";
    
    // Tabloları kontrol et
    $tables = ['site_scripts', 'site_script_settings', 'script_change_logs'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "   $table: " . ($exists ? '✅' : '❌') . "\n";
    }
} else {
    echo "   ❌ PDO bağlantısı yok\n";
}

echo "\n5. Sonuç:\n";
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $hasPermission) {
    echo "   ✅ script-yonetimi.php açılabilir!\n";
    echo "\n<a href='script-yonetimi.php' style='display:inline-block; padding:10px 20px; background:#4CAF50; color:white; text-decoration:none; border-radius:5px; margin-top:10px;'>Script Yönetimine Git →</a>";
} else {
    echo "   ❌ Erişim engellendi!\n";
    echo "   Sorun: ";
    if (!isset($_SESSION['user_id'])) echo "Session yok, ";
    if (!$hasPermission) echo "Yetki yok";
}

echo "</pre>";
?>



