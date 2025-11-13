<?php
session_start();

// Test için basit session verileri
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_role'] = 'superadmin';

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

echo "<h1>Kanal Modülü Test Sayfası</h1>";

echo "<h2>1. Veritabanı Bağlantısı</h2>";
try {
    $pdo = getConnection();
    echo "✅ Veritabanı bağlantısı başarılı<br>";
} catch (Exception $e) {
    echo "❌ Veritabanı bağlantı hatası: " . $e->getMessage() . "<br>";
}

echo "<h2>2. Auth Fonksiyonları</h2>";
echo "checkLogin(): " . (checkLogin() ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";
echo "checkPermission('kanal_listele'): " . (checkPermission('kanal_listele') ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";
echo "hasModulePermission('kanal_yonetimi'): " . (hasModulePermission('kanal_yonetimi') ? "✅ Çalışıyor" : "❌ Çalışmıyor") . "<br>";

echo "<h2>3. Kanal Tabloları</h2>";
$tables = ['kanallar', 'kanal_performans', 'kanal_komisyon', 'kanal_rezervasyon_takip'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "✅ $table tablosu: " . $result['count'] . " kayıt<br>";
    } catch (Exception $e) {
        echo "❌ $table tablosu hatası: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>4. Kanal Yetkileri</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM yetkiler WHERE yetki_adi LIKE '%kanal%'");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Kanal yetkileri: " . $result['count'] . " adet<br>";
} catch (Exception $e) {
    echo "❌ Kanal yetkileri hatası: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Kanal Sayfaları</h2>";
$pages = [
    'kanal-listesi.php',
    'kanal-ekle.php', 
    'kanal-duzenle.php',
    'kanal-performans.php',
    'kanal-analiz.php',
    'kanal-senkronizasyon.php',
    'kanal-api-test.php',
    'kanal-komisyon-yonetimi.php',
    'kanal-fiyat-senkronizasyon.php',
    'kanal-rezervasyon-takibi.php',
    'kanal-raporlama.php'
];

foreach ($pages as $page) {
    if (file_exists($page)) {
        echo "✅ $page mevcut<br>";
    } else {
        echo "❌ $page bulunamadı<br>";
    }
}

echo "<h2>6. API Sınıfları</h2>";
$api_classes = [
    'BookingAPI.php',
    'ExpediaAPI.php', 
    'AgodaAPI.php',
    'HotelsAPI.php',
    'ETSAPI.php',
    'TatilsepetiAPI.php',
    'TatilcomAPI.php',
    'SeturAPI.php'
];

foreach ($api_classes as $class) {
    $path = '../includes/kanal_apis/' . $class;
    if (file_exists($path)) {
        echo "✅ $class mevcut<br>";
    } else {
        echo "❌ $class bulunamadı<br>";
    }
}

echo "<h2>7. Cron Job'lar</h2>";
$cron_jobs = [
    'kanal_senkronizasyon.php',
    'kanal_fiyat_senkronizasyon.php',
    'kanal_stok_senkronizasyon.php'
];

foreach ($cron_jobs as $job) {
    $path = '../cron/' . $job;
    if (file_exists($path)) {
        echo "✅ $job mevcut<br>";
    } else {
        echo "❌ $job bulunamadı<br>";
    }
}

echo "<h2>Test Tamamlandı!</h2>";
echo "<p><a href='kanal-listesi.php'>Kanal Listesi</a> | <a href='kanal-komisyon-yonetimi.php'>Komisyon Yönetimi</a></p>";
?>
