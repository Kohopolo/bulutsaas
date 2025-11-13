<?php
/**
 * Forecast Test Verileri Oluşturucu
 * Geçmiş rezervasyon verileri ekler
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

echo "<h1>Forecast Test Verileri Oluşturucu</h1>";
echo "<pre>";

try {
    // Mevcut veri sayısını kontrol et
    $stmt = $pdo->query("SELECT COUNT(*) FROM rezervasyonlar WHERE giris_tarihi < CURDATE()");
    $existingCount = $stmt->fetchColumn();
    
    echo "Mevcut geçmiş rezervasyon sayısı: $existingCount\n\n";
    
    if ($existingCount >= 30) {
        echo "✅ Yeterli veri var! Test verisi eklemeye gerek yok.\n";
        exit;
    }
    
    echo "📊 Test verileri oluşturuluyor...\n\n";
    
    // Oda tiplerini al
    $stmt = $pdo->query("SELECT id, ad FROM oda_tipleri WHERE aktif = 1");
    $odaTipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($odaTipleri)) {
        die("❌ HATA: Aktif oda tipi bulunamadı!\n");
    }
    
    // Müşteri ID'lerini al veya test müşterisi oluştur
    $stmt = $pdo->query("SELECT id FROM musteriler LIMIT 1");
    $musteriId = $stmt->fetchColumn();
    
    if (!$musteriId) {
        // Test müşterisi oluştur
        $stmt = $pdo->prepare("INSERT INTO musteriler (ad_soyad, email, telefon, tc_kimlik_no) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Test Müşteri', 'test@test.com', '05551234567', '12345678901']);
        $musteriId = $pdo->lastInsertId();
        echo "✅ Test müşterisi oluşturuldu (ID: $musteriId)\n";
    }
    
    // Son 90 gün için rastgele rezervasyonlar oluştur
    $insertedCount = 0;
    $totalRevenue = 0;
    
    for ($i = 90; $i >= 1; $i--) {
        // Her gün için 1-3 rezervasyon
        $dailyReservations = rand(1, 3);
        
        for ($j = 0; $j < $dailyReservations; $j++) {
            $girisTarihi = date('Y-m-d', strtotime("-$i days"));
            $konaklamaGunu = rand(2, 7); // 2-7 gün konaklama
            $cikisTarihi = date('Y-m-d', strtotime($girisTarihi . " +$konaklamaGunu days"));
            
            // Rastgele oda tipi
            $odaTipi = $odaTipleri[array_rand($odaTipleri)];
            
            // Fiyat hesapla (günlük 500-2000 TL arası)
            $gunlukFiyat = rand(500, 2000);
            $toplamTutar = $gunlukFiyat * $konaklamaGunu;
            
            // Yetişkin/çocuk sayıları
            $yetiskinSayisi = rand(1, 3);
            $cocukSayisi = rand(0, 2);
            
            // Rezervasyon kodu oluştur
            $rezervasyonKodu = 'TST-' . strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Durum (geçmiş rezervasyonlar tamamlanmış veya onaylanmış)
            $durum = (rand(1, 10) > 2) ? 'tamamlandi' : 'onaylandi';
            
            // Veritabanına ekle
            $sql = "INSERT INTO rezervasyonlar (
                rezervasyon_kodu, musteri_id, oda_tipi_id, 
                giris_tarihi, cikis_tarihi, 
                yetiskin_sayisi, cocuk_sayisi,
                toplam_tutar, durum,
                odeme_durumu, olusturma_tarihi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'tamamlandi', ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $rezervasyonKodu,
                $musteriId,
                $odaTipi['id'],
                $girisTarihi,
                $cikisTarihi,
                $yetiskinSayisi,
                $cocukSayisi,
                $toplamTutar,
                $durum,
                $girisTarihi
            ]);
            
            $insertedCount++;
            $totalRevenue += $toplamTutar;
        }
    }
    
    echo "✅ BAŞARILI!\n";
    echo "   - Eklenen rezervasyon sayısı: $insertedCount\n";
    echo "   - Toplam gelir: ₺" . number_format($totalRevenue, 2) . "\n";
    echo "   - Tarih aralığı: " . date('Y-m-d', strtotime('-90 days')) . " - " . date('Y-m-d', strtotime('-1 day')) . "\n\n";
    
    // Kontrol
    $stmt = $pdo->query("SELECT COUNT(*) FROM rezervasyonlar WHERE durum IN ('onaylandi', 'tamamlandi')");
    $totalCount = $stmt->fetchColumn();
    
    echo "📊 Toplam rezervasyon sayısı (onaylı + tamamlanmış): $totalCount\n\n";
    
    if ($totalCount >= 30) {
        echo "🎉 Artık Forecast hesaplaması yapabilirsiniz!\n";
    }
    
} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<br><br>";
echo "<a href='test-forecast-simple.php' class='btn btn-primary'>Forecast Test Et</a> ";
echo "<a href='forecast-dashboard.php' class='btn btn-success'>Dashboard'a Git</a>";
?>



