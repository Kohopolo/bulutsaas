<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Yetki kontrolü
if (!hasDetailedPermission('multi_language_currency')) {
    header('Location: 403.php');
    exit;
}

$current_page = 'mlc-setup.php';
$success = '';
$error = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'install_tables':
                    $sql = file_get_contents('../sql/multi_language_currency_tables.sql');
                    $statements = explode(';', $sql);
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    $success = "Veritabanı tabloları başarıyla oluşturuldu.";
                    break;
                    
                case 'add_sample_data':
                    // Örnek çeviri verileri ekle
                    $sampleTranslations = [
                        // Türkçe
                        ['welcome_message', 'tr', 'Hoş Geldiniz', 'genel'],
                        ['reservation_success', 'tr', 'Rezervasyonunuz başarıyla oluşturuldu', 'rezervasyon'],
                        ['check_in_time', 'tr', 'Giriş Saati', 'rezervasyon'],
                        ['check_out_time', 'tr', 'Çıkış Saati', 'rezervasyon'],
                        ['room_service', 'tr', 'Oda Servisi', 'hizmetler'],
                        ['spa_services', 'tr', 'Spa Hizmetleri', 'hizmetler'],
                        ['restaurant', 'tr', 'Restoran', 'hizmetler'],
                        ['pool', 'tr', 'Havuz', 'hizmetler'],
                        ['gym', 'tr', 'Spor Salonu', 'hizmetler'],
                        ['wifi', 'tr', 'Ücretsiz WiFi', 'hizmetler'],
                        
                        // İngilizce
                        ['welcome_message', 'en', 'Welcome', 'genel'],
                        ['reservation_success', 'en', 'Your reservation has been created successfully', 'rezervasyon'],
                        ['check_in_time', 'en', 'Check-in Time', 'rezervasyon'],
                        ['check_out_time', 'en', 'Check-out Time', 'rezervasyon'],
                        ['room_service', 'en', 'Room Service', 'hizmetler'],
                        ['spa_services', 'en', 'Spa Services', 'hizmetler'],
                        ['restaurant', 'en', 'Restaurant', 'hizmetler'],
                        ['pool', 'en', 'Pool', 'hizmetler'],
                        ['gym', 'en', 'Gym', 'hizmetler'],
                        ['wifi', 'en', 'Free WiFi', 'hizmetler'],
                        
                        // Almanca
                        ['welcome_message', 'de', 'Willkommen', 'genel'],
                        ['reservation_success', 'de', 'Ihre Reservierung wurde erfolgreich erstellt', 'rezervasyon'],
                        ['check_in_time', 'de', 'Eincheckzeit', 'rezervasyon'],
                        ['check_out_time', 'de', 'Auscheckzeit', 'rezervasyon'],
                        ['room_service', 'de', 'Zimmerservice', 'hizmetler'],
                        ['spa_services', 'de', 'Spa-Dienstleistungen', 'hizmetler'],
                        ['restaurant', 'de', 'Restaurant', 'hizmetler'],
                        ['pool', 'de', 'Schwimmbad', 'hizmetler'],
                        ['gym', 'de', 'Fitnessstudio', 'hizmetler'],
                        ['wifi', 'de', 'Kostenloses WiFi', 'hizmetler'],
                    ];
                    
                    $stmt = $pdo->prepare("INSERT INTO mlc_ceviriler (anahtar, dil_kodu, metin, kategori) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE metin = VALUES(metin)");
                    
                    foreach ($sampleTranslations as $translation) {
                        $stmt->execute($translation);
                    }
                    
                    $success = "Örnek çeviri verileri başarıyla eklendi.";
                    break;
                    
                case 'test_api':
                    // API test
                    $testData = [
                        'amount' => 100,
                        'from_currency' => 'TRY',
                        'to_currency' => 'USD'
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'http://localhost/otelonofexe/web/api/multi-language-currency/convertCurrency');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        $result = json_decode($response, true);
                        $success = "API test başarılı! 100 TRY = " . $result['converted_amount'] . " USD";
                    } else {
                        $error = "API test başarısız. HTTP Kodu: " . $httpCode;
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = "Hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çoklu Dil ve Para Birimi Kurulum - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-header">
                        <h1><i class="fas fa-cogs"></i> Çoklu Dil ve Para Birimi Kurulum</h1>
                        <p class="text-muted">Sistemi kurmak ve test etmek için gerekli adımları tamamlayın</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Kurulum Adımları -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Kurulum Adımları</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">1. Veritabanı Tabloları</h6>
                                                <p class="mb-0 text-muted">Dil ve para birimi tablolarını oluşturun</p>
                                            </div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="install_tables">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-database"></i> Kur
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">2. Örnek Veriler</h6>
                                                <p class="mb-0 text-muted">Örnek çeviri verilerini ekleyin</p>
                                            </div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="add_sample_data">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-plus"></i> Ekle
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">3. API Test</h6>
                                                <p class="mb-0 text-muted">API endpoint'lerini test edin</p>
                                            </div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="test_api">
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <i class="fas fa-flask"></i> Test Et
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sistem Durumu -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Sistem Durumu</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Tablo kontrolü
                                    $tables = ['mlc_diller', 'mlc_para_birimleri', 'mlc_ceviriler', 'mlc_kur_gecmisi'];
                                    $tableStatus = [];
                                    
                                    foreach ($tables as $table) {
                                        try {
                                            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                                            $tableStatus[$table] = $stmt->rowCount() > 0;
                                        } catch (Exception $e) {
                                            $tableStatus[$table] = false;
                                        }
                                    }
                                    
                                    // Veri kontrolü
                                    $dataStatus = [];
                                    foreach ($tables as $table) {
                                        if ($tableStatus[$table]) {
                                            try {
                                                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                                                $dataStatus[$table] = $stmt->fetchColumn();
                                            } catch (Exception $e) {
                                                $dataStatus[$table] = 0;
                                            }
                                        } else {
                                            $dataStatus[$table] = 0;
                                        }
                                    }
                                    ?>
                                    
                                    <div class="row">
                                        <?php foreach ($tables as $table): ?>
                                            <div class="col-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><?= ucfirst(str_replace('mlc_', '', $table)) ?></span>
                                                    <div>
                                                        <?php if ($tableStatus[$table]): ?>
                                                            <span class="badge bg-success">✓</span>
                                                            <small class="text-muted">(<?= $dataStatus[$table] ?>)</small>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">✗</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Endpoint'leri -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">API Endpoint'leri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Dil Yönetimi</h6>
                                    <ul class="list-unstyled">
                                        <li><code>GET /api/multi-language-currency/languages</code> - Dil listesi</li>
                                        <li><code>POST /api/multi-language-currency/addLanguage</code> - Dil ekle</li>
                                        <li><code>POST /api/multi-language-currency/updateLanguage/{id}</code> - Dil güncelle</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Para Birimi</h6>
                                    <ul class="list-unstyled">
                                        <li><code>GET /api/multi-language-currency/currencies</code> - Para birimi listesi</li>
                                        <li><code>POST /api/multi-language-currency/convertCurrency</code> - Para birimi dönüştür</li>
                                        <li><code>POST /api/multi-language-currency/updateExchangeRates</code> - Kurları güncelle</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6>Çeviri</h6>
                                    <ul class="list-unstyled">
                                        <li><code>GET /api/multi-language-currency/translations</code> - Çeviri listesi</li>
                                        <li><code>POST /api/multi-language-currency/addTranslation</code> - Çeviri ekle</li>
                                        <li><code>POST /api/multi-language-currency/translate</code> - Çeviri yap</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Ayarlar</h6>
                                    <ul class="list-unstyled">
                                        <li><code>GET /api/multi-language-currency/currentSettings</code> - Mevcut ayarlar</li>
                                        <li><code>POST /api/multi-language-currency/setLanguage</code> - Dil ayarla</li>
                                        <li><code>POST /api/multi-language-currency/setCurrency</code> - Para birimi ayarla</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
