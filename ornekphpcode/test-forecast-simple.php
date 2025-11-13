<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

echo "<h1>Forecast Test - Basit</h1>";
echo "<pre>";

try {
    echo "1. Database bağlantısı...\n";
    require_once '../config/database.php';
    echo "   ✅ Başarılı\n\n";
    
    echo "2. ForecastEngine sınıfı yükleniyor...\n";
    require_once '../includes/ForecastEngine.php';
    echo "   ✅ Başarılı\n\n";
    
    echo "3. ForecastEngine oluşturuluyor...\n";
    $forecastEngine = new ForecastEngine($pdo);
    echo "   ✅ Başarılı\n\n";
    
    echo "4. Rezervasyon sayısı kontrol ediliyor...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rezervasyonlar WHERE durum IN ('onaylandi', 'tamamlandi')");
    $count = $stmt->fetchColumn();
    echo "   📊 Toplam rezervasyon: $count\n\n";
    
    if ($count < 5) {
        echo "   ⚠️  UYARI: Çok az rezervasyon var. Test verileri ekleyelim mi?\n\n";
    }
    
    echo "5. Forecast hesaplaması başlatılıyor...\n";
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+30 days'));
    echo "   Tarih aralığı: $startDate - $endDate\n";
    
    $result = $forecastEngine->calculateForecasts($startDate, $endDate, ['revenue', 'occupancy', 'adr']);
    
    echo "\n6. SONUÇ:\n";
    echo "   " . str_repeat("-", 60) . "\n";
    print_r($result);
    
    if ($result['success']) {
        echo "\n\n7. Oluşturulan tahminler:\n";
        $stmt = $pdo->query("SELECT * FROM forecast_data ORDER BY olusturma_tarihi DESC LIMIT 10");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   - {$row['forecast_type']}: {$row['target_date']} = {$row['predicted_value']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<br><a href='forecast-dashboard.php' class='btn btn-primary'>Dashboard'a Git</a>";
?>



