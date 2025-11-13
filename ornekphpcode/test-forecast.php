<?php
require_once '../config/database.php';
require_once '../includes/ForecastEngine.php';

ini_set('max_execution_time', 300); // 5 dakika

echo "<h1>Forecast Test</h1>";
echo "<pre>";

try {
    echo "Forecast Engine başlatılıyor...\n";
    $forecastEngine = new ForecastEngine($pdo);
    
    echo "Hesaplama başlatılıyor...\n";
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+30 days'));
    
    echo "Tarih aralığı: $startDate - $endDate\n\n";
    
    $result = $forecastEngine->calculateForecasts($startDate, $endDate, ['revenue', 'occupancy', 'adr']);
    
    if ($result['success']) {
        echo "✅ BAŞARILI!\n";
        echo "Calculation ID: {$result['calculation_id']}\n";
        echo "İşlenen kayıt sayısı: {$result['records_processed']}\n\n";
        
        // Sonuçları göster
        $stmt = $pdo->query("
            SELECT * FROM forecast_data 
            WHERE target_date BETWEEN '$startDate' AND '$endDate'
            ORDER BY target_date ASC, forecast_type ASC 
            LIMIT 10
        ");
        
        echo "İlk 10 tahmin:\n";
        echo str_repeat("-", 100) . "\n";
        printf("%-12s %-15s %-12s %-12s %-12s\n", "Tarih", "Tip", "Tahmin", "Alt Sınır", "Üst Sınır");
        echo str_repeat("-", 100) . "\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            printf("%-12s %-15s %12.2f %12.2f %12.2f\n", 
                date('d.m.Y', strtotime($row['target_date'])),
                $row['forecast_type'],
                $row['predicted_value'],
                $row['lower_bound'],
                $row['upper_bound']
            );
        }
        
    } else {
        echo "❌ HATA: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ FATAL HATA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<br><a href='forecast-dashboard.php' class='btn btn-primary'>Forecast Dashboard'a Git</a>";
?>



