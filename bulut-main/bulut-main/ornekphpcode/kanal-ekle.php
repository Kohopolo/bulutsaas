<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_ekle', 'Kanal ekleme yetkiniz bulunmamaktadır.');

$page_title = "Yeni Kanal Ekle";

$success_message = '';
$error_message = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF kontrolü
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Güvenlik hatası. Lütfen sayfayı yenileyin.');
        }

        // Form verilerini al
        $kanal_adi = trim($_POST['kanal_adi'] ?? '');
        $kanal_tipi = $_POST['kanal_tipi'] ?? '';
        $kanal_kodu = trim($_POST['kanal_kodu'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $api_endpoint = trim($_POST['api_endpoint'] ?? '');
        $api_key = trim($_POST['api_key'] ?? '');
        $api_secret = trim($_POST['api_secret'] ?? '');
        $komisyon_orani = floatval($_POST['komisyon_orani'] ?? 0);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $otomatik_senkronizasyon = isset($_POST['otomatik_senkronizasyon']) ? 1 : 0;
        $senkronizasyon_sikligi = $_POST['senkronizasyon_sikligi'] ?? 'daily';
        $test_modu = isset($_POST['test_modu']) ? 1 : 0;
        $maksimum_gunluk_rezervasyon = !empty($_POST['maksimum_gunluk_rezervasyon']) ? intval($_POST['maksimum_gunluk_rezervasyon']) : null;
        $minimum_rezervasyon_suresi = intval($_POST['minimum_rezervasyon_suresi'] ?? 1);
        $maksimum_rezervasyon_suresi = intval($_POST['maksimum_rezervasyon_suresi'] ?? 365);
        $iptal_politikasi = trim($_POST['iptal_politikasi'] ?? '');
        $odeme_politikasi = trim($_POST['odeme_politikasi'] ?? '');
        $ozel_notlar = trim($_POST['ozel_notlar'] ?? '');

        // Validasyon
        if (empty($kanal_adi)) {
            throw new Exception('Kanal adı gereklidir.');
        }

        if (empty($kanal_tipi)) {
            throw new Exception('Kanal tipi seçilmelidir.');
        }

        if (empty($kanal_kodu)) {
            throw new Exception('Kanal kodu gereklidir.');
        }

        // Kanal kodu benzersizlik kontrolü
        $existing_kanal = fetchOne("SELECT id FROM kanallar WHERE kanal_kodu = ?", [$kanal_kodu]);
        if ($existing_kanal) {
            throw new Exception('Bu kanal kodu zaten kullanılmaktadır.');
        }

        // Website URL validasyonu
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new Exception('Geçerli bir website URL\'si giriniz.');
        }

        // API endpoint URL validasyonu
        if (!empty($api_endpoint) && !filter_var($api_endpoint, FILTER_VALIDATE_URL)) {
            throw new Exception('Geçerli bir API endpoint URL\'si giriniz.');
        }

        // Komisyon oranı kontrolü
        if ($komisyon_orani < 0 || $komisyon_orani > 100) {
            throw new Exception('Komisyon oranı 0-100 arasında olmalıdır.');
        }

        // Veritabanına ekle
        $sql = "INSERT INTO kanallar (
            kanal_adi, kanal_tipi, kanal_kodu, website, api_endpoint, api_key, api_secret,
            komisyon_orani, aktif, otomatik_senkronizasyon, senkronizasyon_sikligi, test_modu,
            maksimum_gunluk_rezervasyon, minimum_rezervasyon_suresi, maksimum_rezervasyon_suresi,
            iptal_politikasi, odeme_politikasi, ozel_notlar, olusturan_kullanici_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $kanal_adi, $kanal_tipi, $kanal_kodu, $website, $api_endpoint, $api_key, $api_secret,
            $komisyon_orani, $aktif, $otomatik_senkronizasyon, $senkronizasyon_sikligi, $test_modu,
            $maksimum_gunluk_rezervasyon, $minimum_rezervasyon_suresi, $maksimum_rezervasyon_suresi,
            $iptal_politikasi, $odeme_politikasi, $ozel_notlar, $_SESSION['user_id']
        ];

        $kanal_id = insertAndGetId($sql, $params);

        // Başarı mesajı
        $success_message = "Kanal başarıyla eklendi. Kanal ID: $kanal_id";

        // Formu temizle
        $_POST = [];

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .desktop-container {
            padding: 20px;
            min-height: 100vh;
        }
        
        .desktop-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-check-label {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .kanal-tipi-info {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #667eea;
        }
        
        .kanal-tipi-info h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .kanal-tipi-info p {
            margin-bottom: 5px;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 768px) {
            .desktop-container {
                padding: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-plus-circle me-3" style="color: #667eea;"></i>
                        Yeni Kanal Ekle
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <i class="fas fa-clock me-2"></i>
                        <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="quick-actions">
                        <a href="kanal-listesi.php" class="quick-action-btn">
                            <i class="fas fa-list"></i>
                            Kanal Listesi
                        </a>
                        <a href="kanal-performans.php" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            Performans
                        </a>
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="kanalForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <!-- Temel Bilgiler -->
                <div class="form-section">
                    <h4 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Temel Bilgiler
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="kanal_adi" class="form-label">
                                Kanal Adı <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="kanal_adi" name="kanal_adi" 
                                   value="<?= htmlspecialchars($_POST['kanal_adi'] ?? '') ?>" required>
                            <div class="help-text">Örnek: Booking.com, ETS, Direct Booking</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="kanal_kodu" class="form-label">
                                Kanal Kodu <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="kanal_kodu" name="kanal_kodu" 
                                   value="<?= htmlspecialchars($_POST['kanal_kodu'] ?? '') ?>" 
                                   style="text-transform: uppercase;" required>
                            <div class="help-text">Benzersiz kod (örn: BOOKING, ETS, DIRECT)</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="kanal_tipi" class="form-label">
                                Kanal Tipi <span class="required">*</span>
                            </label>
                            <select class="form-select" id="kanal_tipi" name="kanal_tipi" required>
                                <option value="">Seçiniz</option>
                                <option value="ota" <?= ($_POST['kanal_tipi'] ?? '') === 'ota' ? 'selected' : '' ?>>OTA (Online Travel Agency)</option>
                                <option value="gds" <?= ($_POST['kanal_tipi'] ?? '') === 'gds' ? 'selected' : '' ?>>GDS (Global Distribution System)</option>
                                <option value="direct" <?= ($_POST['kanal_tipi'] ?? '') === 'direct' ? 'selected' : '' ?>>Direct (Doğrudan)</option>
                                <option value="corporate" <?= ($_POST['kanal_tipi'] ?? '') === 'corporate' ? 'selected' : '' ?>>Corporate (Kurumsal)</option>
                                <option value="social" <?= ($_POST['kanal_tipi'] ?? '') === 'social' ? 'selected' : '' ?>>Social Media</option>
                                <option value="other" <?= ($_POST['kanal_tipi'] ?? '') === 'other' ? 'selected' : '' ?>>Diğer</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?= htmlspecialchars($_POST['website'] ?? '') ?>" 
                                   placeholder="https://www.example.com">
                            <div class="help-text">Kanalın resmi website adresi</div>
                        </div>
                    </div>
                    
                    <!-- Kanal Tipi Açıklaması -->
                    <div id="kanal-tipi-info" class="kanal-tipi-info" style="display: none;">
                        <h6>Kanal Tipi Açıklaması</h6>
                        <p id="kanal-tipi-desc"></p>
                    </div>
                </div>

                <!-- API Ayarları -->
                <div class="form-section">
                    <h4 class="section-title">
                        <i class="fas fa-plug"></i>
                        API Ayarları
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <label for="api_endpoint" class="form-label">API Endpoint</label>
                            <input type="url" class="form-control" id="api_endpoint" name="api_endpoint" 
                                   value="<?= htmlspecialchars($_POST['api_endpoint'] ?? '') ?>" 
                                   placeholder="https://api.example.com/v1">
                            <div class="help-text">Kanalın API endpoint URL'si</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="api_key" class="form-label">API Key</label>
                            <input type="text" class="form-control" id="api_key" name="api_key" 
                                   value="<?= htmlspecialchars($_POST['api_key'] ?? '') ?>">
                            <div class="help-text">API erişim anahtarı</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="api_secret" class="form-label">API Secret</label>
                            <input type="password" class="form-control" id="api_secret" name="api_secret" 
                                   value="<?= htmlspecialchars($_POST['api_secret'] ?? '') ?>">
                            <div class="help-text">API gizli anahtarı</div>
                        </div>
                    </div>
                </div>

                <!-- Komisyon ve Ayarlar -->
                <div class="form-section">
                    <h4 class="section-title">
                        <i class="fas fa-percentage"></i>
                        Komisyon ve Ayarlar
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label for="komisyon_orani" class="form-label">Komisyon Oranı (%)</label>
                            <input type="number" class="form-control" id="komisyon_orani" name="komisyon_orani" 
                                   value="<?= htmlspecialchars($_POST['komisyon_orani'] ?? '0') ?>" 
                                   min="0" max="100" step="0.01">
                            <div class="help-text">0-100 arası komisyon oranı</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="senkronizasyon_sikligi" class="form-label">Senkronizasyon Sıklığı</label>
                            <select class="form-select" id="senkronizasyon_sikligi" name="senkronizasyon_sikligi">
                                <option value="realtime" <?= ($_POST['senkronizasyon_sikligi'] ?? 'daily') === 'realtime' ? 'selected' : '' ?>>Gerçek Zamanlı</option>
                                <option value="hourly" <?= ($_POST['senkronizasyon_sikligi'] ?? 'daily') === 'hourly' ? 'selected' : '' ?>>Saatlik</option>
                                <option value="daily" <?= ($_POST['senkronizasyon_sikligi'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Günlük</option>
                                <option value="weekly" <?= ($_POST['senkronizasyon_sikligi'] ?? 'daily') === 'weekly' ? 'selected' : '' ?>>Haftalık</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="maksimum_gunluk_rezervasyon" class="form-label">Maksimum Günlük Rezervasyon</label>
                            <input type="number" class="form-control" id="maksimum_gunluk_rezervasyon" name="maksimum_gunluk_rezervasyon" 
                                   value="<?= htmlspecialchars($_POST['maksimum_gunluk_rezervasyon'] ?? '') ?>" 
                                   min="1">
                            <div class="help-text">Boş bırakılırsa sınırsız</div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="minimum_rezervasyon_suresi" class="form-label">Minimum Rezervasyon Süresi (Gün)</label>
                            <input type="number" class="form-control" id="minimum_rezervasyon_suresi" name="minimum_rezervasyon_suresi" 
                                   value="<?= htmlspecialchars($_POST['minimum_rezervasyon_suresi'] ?? '1') ?>" 
                                   min="1" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="maksimum_rezervasyon_suresi" class="form-label">Maksimum Rezervasyon Süresi (Gün)</label>
                            <input type="number" class="form-control" id="maksimum_rezervasyon_suresi" name="maksimum_rezervasyon_suresi" 
                                   value="<?= htmlspecialchars($_POST['maksimum_rezervasyon_suresi'] ?? '365') ?>" 
                                   min="1" required>
                        </div>
                    </div>
                </div>

                <!-- Durum ve Seçenekler -->
                <div class="form-section">
                    <h4 class="section-title">
                        <i class="fas fa-cog"></i>
                        Durum ve Seçenekler
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="aktif" name="aktif" 
                                       <?= isset($_POST['aktif']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="aktif">
                                    Kanal Aktif
                                </label>
                                <div class="help-text">Kanal aktif durumda olsun</div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="otomatik_senkronizasyon" name="otomatik_senkronizasyon" 
                                       <?= isset($_POST['otomatik_senkronizasyon']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="otomatik_senkronizasyon">
                                    Otomatik Senkronizasyon
                                </label>
                                <div class="help-text">Otomatik veri senkronizasyonu yapılsın</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="test_modu" name="test_modu" 
                                       <?= isset($_POST['test_modu']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="test_modu">
                                    Test Modu
                                </label>
                                <div class="help-text">Test modunda çalışsın</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Politikalar -->
                <div class="form-section">
                    <h4 class="section-title">
                        <i class="fas fa-file-contract"></i>
                        Politikalar
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="iptal_politikasi" class="form-label">İptal Politikası</label>
                            <textarea class="form-control" id="iptal_politikasi" name="iptal_politikasi" rows="4" 
                                      placeholder="İptal politikası detayları..."><?= htmlspecialchars($_POST['iptal_politikasi'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="odeme_politikasi" class="form-label">Ödeme Politikası</label>
                            <textarea class="form-control" id="odeme_politikasi" name="odeme_politikasi" rows="4" 
                                      placeholder="Ödeme politikası detayları..."><?= htmlspecialchars($_POST['odeme_politikasi'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label for="ozel_notlar" class="form-label">Özel Notlar</label>
                            <textarea class="form-control" id="ozel_notlar" name="ozel_notlar" rows="3" 
                                      placeholder="Kanal hakkında özel notlar..."><?= htmlspecialchars($_POST['ozel_notlar'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Butonları -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="kanal-listesi.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> İptal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Kanalı Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Kanal kodu otomatik büyük harf
        document.getElementById('kanal_kodu').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Kanal tipi açıklaması
        const kanalTipiAciklamalari = {
            'ota': 'Online Travel Agency - Booking.com, Expedia gibi online seyahat acenteleri',
            'gds': 'Global Distribution System - Amadeus, Sabre gibi global dağıtım sistemleri',
            'direct': 'Doğrudan rezervasyon - Telefon, website, walk-in gibi doğrudan kanallar',
            'corporate': 'Kurumsal müşteriler - Şirket anlaşmaları ve kurumsal rezervasyonlar',
            'social': 'Sosyal medya - Facebook, Instagram gibi sosyal medya kanalları',
            'other': 'Diğer kanallar - Yukarıdaki kategorilere girmeyen kanallar'
        };

        document.getElementById('kanal_tipi').addEventListener('change', function() {
            const infoDiv = document.getElementById('kanal-tipi-info');
            const descP = document.getElementById('kanal-tipi-desc');
            
            if (this.value && kanalTipiAciklamalari[this.value]) {
                descP.textContent = kanalTipiAciklamalari[this.value];
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        });

        // Canlı saat güncelleme
        function updateLiveTime() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
            const timeElement = document.querySelector('.current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateLiveTime();
        setInterval(updateLiveTime, 1000);

        // Form validasyonu
        document.getElementById('kanalForm').addEventListener('submit', function(e) {
            const kanalAdi = document.getElementById('kanal_adi').value.trim();
            const kanalKodu = document.getElementById('kanal_kodu').value.trim();
            const kanalTipi = document.getElementById('kanal_tipi').value;
            
            if (!kanalAdi) {
                alert('Kanal adı gereklidir.');
                e.preventDefault();
                return;
            }
            
            if (!kanalKodu) {
                alert('Kanal kodu gereklidir.');
                e.preventDefault();
                return;
            }
            
            if (!kanalTipi) {
                alert('Kanal tipi seçilmelidir.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
