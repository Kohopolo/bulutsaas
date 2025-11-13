<?php
/**
 * about-us-6 sayfasını oluştur
 */

require_once '../config/database.php';

try {
    // about-us-6 sayfasını oluştur
    $stmt = $pdo->prepare("
        INSERT INTO custom_pages 
        (page_title, page_slug, page_content, page_template, is_active, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $pageContent = '
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="display-4 text-center mb-5">Hakkımızda</h1>
                
                <div class="row mb-5">
                    <div class="col-md-6">
                        <img src="assets/images/logo.png" alt="Otel" class="img-fluid rounded shadow">
                    </div>
                    <div class="col-md-6">
                        <h2>Misyonumuz</h2>
                        <p class="lead">Misafirlerimize unutulmaz bir konaklama deneyimi sunmak ve onların her ihtiyacını karşılamak için buradayız.</p>
                        
                        <h2>Vizyonumuz</h2>
                        <p class="lead">Türkiye\'nin en prestijli otel zincirlerinden biri olmak ve dünya standartlarında hizmet vermek.</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 text-center mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-star fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Kaliteli Hizmet</h5>
                                <p class="card-text">5 yıldızlı hizmet anlayışımızla misafirlerimizi memnun etmeyi hedefliyoruz.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                                <h5 class="card-title">Misafir Odaklı</h5>
                                <p class="card-text">Her misafirimizin ihtiyacını önceden tahmin edip, en iyi hizmeti sunuyoruz.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Güvenli Konaklama</h5>
                                <p class="card-text">24/7 güvenlik hizmetimizle misafirlerimizin güvenliğini sağlıyoruz.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5 text-center">
                    <h3>Bizimle İletişime Geçin</h3>
                    <p class="lead">Sorularınız için bize ulaşabilirsiniz.</p>
                    <a href="/iletisim" class="btn btn-primary btn-lg">İletişim</a>
                </div>
            </div>
        </div>
    </div>
    ';
    
    $stmt->execute([
        'Hakkımızda - Premium Hotel',
        'about-us-6',
        $pageContent,
        'premium-hotel',
        1, // Aktif
        1  // Admin user ID
    ]);
    
    echo "✅ about-us-6 sayfası başarıyla oluşturuldu!\n";
    echo "📄 Başlık: Hakkımızda - Premium Hotel\n";
    echo "🔗 Slug: about-us-6\n";
    echo "🎨 Template: premium-hotel\n";
    echo "✅ Durum: Aktif\n";
    echo "\n🌐 Test URL: http://localhost/otelonofexe/web/about-us-6\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "ℹ️ about-us-6 sayfası zaten mevcut!\n";
        echo "🌐 Test URL: http://localhost/otelonofexe/web/about-us-6\n";
    } else {
        echo "❌ Hata: " . $e->getMessage() . "\n";
    }
}
?>

