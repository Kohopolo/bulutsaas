<?php
// Test için session başlat
session_start();

// Test kullanıcısı oluştur
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_role'] = 'superadmin';

// Dosyaları dahil et
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

echo "<h1>Auth Test</h1>";

echo "<h2>Fonksiyon Testleri</h2>";
echo "checkLogin(): " . (checkLogin() ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";
echo "checkPermission('kanal_listele'): " . (checkPermission('kanal_listele') ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";
echo "hasModulePermission('kanal_yonetimi'): " . (hasModulePermission('kanal_yonetimi') ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";
echo "hasDetailedPermission('kanal_listele'): " . (hasDetailedPermission('kanal_listele') ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";

echo "<h2>Session Bilgileri</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Yok') . "<br>";
echo "User Name: " . ($_SESSION['user_name'] ?? 'Yok') . "<br>";
echo "User Role: " . ($_SESSION['user_role'] ?? 'Yok') . "<br>";

echo "<h2>Test Tamamlandı!</h2>";
?>
