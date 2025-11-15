<?php
// Basit test dosyası - AJAX çağrısını simüle et
session_start();

// Test parametreleri
$_POST['oda_tipi_id'] = 1;
$_POST['giris_tarihi'] = '2025-10-12T14:00';
$_POST['cikis_tarihi'] = '2025-10-13T12:00';
$_POST['csrf_token'] = $_SESSION['csrf_token'] ?? 'test';
$_SESSION['csrf_token'] = 'test';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// get_musait_odalar.php dosyasının içeriğini oku ve göster
echo "<h2>get_musait_odalar.php İçeriği:</h2>";
echo "<pre>";
$content = file_get_contents(__DIR__ . '/get_musait_odalar.php');
echo htmlspecialchars($content);
echo "</pre>";

echo "<hr>";
echo "<h2>Dosya Bilgileri:</h2>";
$file = __DIR__ . '/get_musait_odalar.php';
echo "Dosya yolu: " . $file . "<br>";
echo "Dosya var mı: " . (file_exists($file) ? 'EVET' : 'HAYIR') . "<br>";
echo "Dosya boyutu: " . filesize($file) . " byte<br>";
echo "Son değiştirilme: " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";

echo "<hr>";
echo "<h2>SQL Sorgusu Kontrolü:</h2>";
if (strpos($content, 'FROM oda_numaralari on') !== false) {
    echo "<span style='color:red; font-weight:bold;'>❌ HATA: 'on' alias hala kullanılıyor!</span><br>";
} else if (strpos($content, 'FROM oda_numaralari odn') !== false) {
    echo "<span style='color:green; font-weight:bold;'>✅ DOĞRU: 'odn' alias kullanılıyor!</span><br>";
} else {
    echo "<span style='color:orange;'>⚠️ Sorgu bulunamadı</span><br>";
}
?>
