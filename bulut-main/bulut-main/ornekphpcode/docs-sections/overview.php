<?php
// C:\xampp\htdocs\otelonofexe\web\admin\docs-sections\overview.php
// API dokümantasyonu - Genel Bakış bölümü
?>

<h1>Genel Bakış</h1>

<p>Ödeme Modülü, çoklu sanal POS sağlayıcısı desteği ile güvenli ödeme işlemleri gerçekleştirmenizi sağlar. Modül, PCI DSS uyumlu güvenlik standartları ile tasarlanmıştır.</p>

<h2>Desteklenen Ödeme Sağlayıcıları</h2>

<div class="row">
    <div class="col-md-6">
        <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                İyzico
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                PayTR
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                Akbank
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                Yapı Kredi
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                QNB Finansbank
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
        </ul>
    </div>
    <div class="col-md-6">
        <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                Garanti BBVA
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                İş Bankası
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                Ziraat Bankası
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                VakıfBank
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <i class="fas fa-credit-card me-2"></i>
                Halkbank
                <span class="badge bg-primary rounded-pill">3D Secure</span>
            </li>
        </ul>
    </div>
</div>

<h2>Özellikler</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-shield-alt text-primary me-2"></i>
                    Güvenlik
                </h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>3D Secure desteği</li>
                    <li><i class="fas fa-check text-success me-2"></i>PCI DSS uyumluluğu</li>
                    <li><i class="fas fa-check text-success me-2"></i>SSL/TLS şifreleme</li>
                    <li><i class="fas fa-check text-success me-2"></i>Fraud detection</li>
                    <li><i class="fas fa-check text-success me-2"></i>Rate limiting</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-cogs text-info me-2"></i>
                    İşlevsellik
                </h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Taksit seçenekleri</li>
                    <li><i class="fas fa-check text-success me-2"></i>Komisyon hesaplama</li>
                    <li><i class="fas fa-check text-success me-2"></i>İade işlemleri</li>
                    <li><i class="fas fa-check text-success me-2"></i>Webhook desteği</li>
                    <li><i class="fas fa-check text-success me-2"></i>Çoklu dil desteği</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-chart-line text-warning me-2"></i>
                    Monitoring
                </h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Detaylı loglama</li>
                    <li><i class="fas fa-check text-success me-2"></i>Performans monitoring</li>
                    <li><i class="fas fa-check text-success me-2"></i>Hata takibi</li>
                    <li><i class="fas fa-check text-success me-2"></i>İstatistikler</li>
                    <li><i class="fas fa-check text-success me-2"></i>Raporlama</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-database text-danger me-2"></i>
                    Yönetim
                </h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Backup ve recovery</li>
                    <li><i class="fas fa-check text-success me-2"></i>Cache sistemi</li>
                    <li><i class="fas fa-check text-success me-2"></i>Mobil uyumluluk</li>
                    <li><i class="fas fa-check text-success me-2"></i>Admin paneli</li>
                    <li><i class="fas fa-check text-success me-2"></i>API dokümantasyonu</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<h2>Mimari</h2>

<div class="code-block">
    <pre><code class="language-php"><?php echo htmlspecialchars('
// Ödeme Modülü Mimari
PaymentProcessor
├── PaymentSecurity (Güvenlik)
├── PaymentCommission (Komisyon)
├── PaymentRefund (İade)
├── PaymentCache (Cache)
├── PaymentLogger (Loglama)
└── PaymentCompliance (Uyumluluk)

// Desteklenen Sağlayıcılar
├── IyzicoPayment
├── PayTRPayment
├── AkbankPayment
├── YapiKrediPayment
├── QNBFinansbankPayment
├── GarantiBBVAPayment
├── IsBankasiPayment
├── ZiraatBankasiPayment
├── VakifBankPayment
└── HalkbankPayment
'); ?></code></pre>
</div>

<h2>Hızlı Başlangıç</h2>

<div class="code-block">
    <pre><code class="language-php"><?php echo htmlspecialchars('
<?php
require_once \'includes/payment/PaymentProcessor.php\';

// PaymentProcessor\'ı başlat
$payment_processor = new PaymentProcessor($database_connection);

// Ödeme işlemi
$payment_data = [
    \'provider_id\' => 1, // İyzico
    \'amount\' => 100.00,
    \'currency\' => \'TRY\',
    \'card_number\' => \'5555555555554444\',
    \'card_holder\' => \'John Doe\',
    \'expiry_month\' => \'12\',
    \'expiry_year\' => \'2025\',
    \'cvc\' => \'123\',
    \'installment\' => 1,
    \'customer_email\' => \'customer@example.com\',
    \'customer_phone\' => \'+905551234567\'
];

$result = $payment_processor->processPayment($payment_data);

if ($result[\'success\']) {
    echo "Ödeme başarılı: " . $result[\'transaction_id\'];
} else {
    echo "Ödeme başarısız: " . $result[\'error_message\'];
}
?>'); ?></code></pre>
</div>

<h2>Gereksinimler</h2>

<div class="row">
    <div class="col-md-6">
        <h4>Sunucu Gereksinimleri</h4>
        <ul>
            <li>PHP 7.4 veya üzeri</li>
            <li>MySQL 5.7 veya üzeri</li>
            <li>cURL desteği</li>
            <li>OpenSSL desteği</li>
            <li>JSON desteği</li>
        </ul>
    </div>
    <div class="col-md-6">
        <h4>Güvenlik Gereksinimleri</h4>
        <ul>
            <li>SSL sertifikası</li>
            <li>HTTPS bağlantısı</li>
            <li>Güvenli veritabanı bağlantısı</li>
            <li>Güvenli API anahtarları</li>
            <li>Düzenli güvenlik güncellemeleri</li>
        </ul>
    </div>
</div>

<h2>Lisans</h2>

<p>Bu modül MIT lisansı altında lisanslanmıştır. Ticari ve ticari olmayan projelerde kullanılabilir.</p>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Not:</strong> Bu modül PCI DSS uyumluluğu için tasarlanmıştır. Üretim ortamında kullanmadan önce güvenlik testlerini yapın.
</div>
