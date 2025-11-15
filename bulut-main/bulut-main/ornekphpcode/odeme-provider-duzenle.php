<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_provider_duzenle', 'Ödeme sağlayıcısı düzenleme yetkiniz bulunmamaktadır.');

$page_title = "Ödeme Sağlayıcısı Düzenle";
$current_page = "odeme-provider-duzenle";

$success_message = '';
$error_message = '';

// Provider ID kontrolü
$provider_id = $_GET['id'] ?? 0;
if (!$provider_id) {
    $_SESSION['error_message'] = 'Geçersiz sağlayıcı ID.';
    header('Location: odeme-yonetimi.php');
    exit;
}

// Sağlayıcı bilgilerini getir
$provider = fetchOne("SELECT * FROM odeme_providerlari WHERE id = ?", [$provider_id]);
if (!$provider) {
    $_SESSION['error_message'] = 'Sağlayıcı bulunamadı.';
    header('Location: odeme-yonetimi.php');
    exit;
}

// API ayarlarını getir
$api_ayarlari = fetchAll("SELECT * FROM odeme_ayarlari WHERE provider_id = ? ORDER BY ortam", [$provider_id]);

// Taksit seçeneklerini getir
$taksitler = fetchAll("SELECT * FROM odeme_taksitleri WHERE provider_id = ? ORDER BY taksit_sayisi", [$provider_id]);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF kontrolü
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Güvenlik hatası. Lütfen sayfayı yenileyin.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'update_provider') {
            // Sağlayıcı bilgilerini güncelle
            $provider_adi = trim($_POST['provider_adi'] ?? '');
            $provider_kodu = trim($_POST['provider_kodu'] ?? '');
            $provider_tipi = $_POST['provider_tipi'] ?? '';
            $api_url = trim($_POST['api_url'] ?? '');
            $test_api_url = trim($_POST['test_api_url'] ?? '');
            $logo_url = trim($_POST['logo_url'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            $varsayilan = isset($_POST['varsayilan']) ? 1 : 0;
            $siralama = intval($_POST['siralama'] ?? 0);
            $komisyon_orani = floatval($_POST['komisyon_orani'] ?? 0);
            $sabit_komisyon = floatval($_POST['sabit_komisyon'] ?? 0);
            $minimum_tutar = floatval($_POST['minimum_tutar'] ?? 0);
            $maksimum_tutar = floatval($_POST['maksimum_tutar'] ?? 999999.99);
            $taksit_destegi = isset($_POST['taksit_destegi']) ? 1 : 0;
            $maksimum_taksit = intval($_POST['maksimum_taksit'] ?? 12);
            $webhook_destegi = isset($_POST['webhook_destegi']) ? 1 : 0;
            $secure_3d_destegi = isset($_POST['3d_secure_destegi']) ? 1 : 0;
            $mobil_destegi = isset($_POST['mobil_destegi']) ? 1 : 0;
            $aciklama = trim($_POST['aciklama'] ?? '');

            // Validasyon
            if (empty($provider_adi)) {
                throw new Exception('Sağlayıcı adı gereklidir.');
            }
            if (empty($provider_kodu)) {
                throw new Exception('Sağlayıcı kodu gereklidir.');
            }
            if (empty($provider_tipi)) {
                throw new Exception('Sağlayıcı tipi gereklidir.');
            }

            // Sağlayıcı kodunun benzersizliğini kontrol et (kendisi hariç)
            $existing = fetchOne("SELECT id FROM odeme_providerlari WHERE provider_kodu = ? AND id != ?", [$provider_kodu, $provider_id]);
            if ($existing) {
                throw new Exception('Bu sağlayıcı kodu zaten kullanılıyor.');
            }

            // Eğer varsayılan olarak işaretleniyorsa, diğerlerini varsayılan olmaktan çıkar
            if ($varsayilan) {
                executeQuery("UPDATE odeme_providerlari SET varsayilan = 0 WHERE id != ?", [$provider_id]);
            }

            // Sağlayıcıyı güncelle
            executeQuery("
                UPDATE odeme_providerlari SET 
                    provider_adi = ?, provider_kodu = ?, provider_tipi = ?, api_url = ?, test_api_url = ?, 
                    logo_url = ?, aktif = ?, varsayilan = ?, siralama = ?, komisyon_orani = ?, 
                    sabit_komisyon = ?, minimum_tutar = ?, maksimum_tutar = ?, taksit_destegi = ?, 
                    maksimum_taksit = ?, webhook_destegi = ?, secure_3d_destegi = ?, mobil_destegi = ?, aciklama = ?
                WHERE id = ?
            ", [
                $provider_adi, $provider_kodu, $provider_tipi, $api_url, $test_api_url, 
                $logo_url, $aktif, $varsayilan, $siralama, $komisyon_orani, 
                $sabit_komisyon, $minimum_tutar, $maksimum_tutar, $taksit_destegi, 
                $maksimum_taksit, $webhook_destegi, $secure_3d_destegi, $mobil_destegi, $aciklama, $provider_id
            ]);

            // Taksit seçeneklerini güncelle
            if ($taksit_destegi) {
                // Mevcut taksitleri sil
                executeQuery("DELETE FROM odeme_taksitleri WHERE provider_id = ?", [$provider_id]);
                
                // Yeni taksit seçeneklerini ekle
                for ($i = 1; $i <= $maksimum_taksit; $i++) {
                    $taksit_komisyon = $komisyon_orani;
                    if ($i > 1) {
                        $taksit_komisyon += ($i <= 3 ? 0.50 : ($i <= 6 ? 1.00 : ($i <= 9 ? 1.50 : 2.00)));
                    }
                    
                    executeQuery("
                        INSERT INTO odeme_taksitleri (
                            provider_id, taksit_sayisi, komisyon_orani, sabit_komisyon, 
                            minimum_tutar, aktif, siralama, aciklama
                        ) VALUES (?, ?, ?, ?, ?, 1, ?, ?)
                    ", [
                        $provider_id, $i, $taksit_komisyon, $sabit_komisyon, 
                        $minimum_tutar, $i, $i . ' Taksit'
                    ]);
                }
            } else {
                // Taksit desteği kapatılıyorsa taksitleri sil
                executeQuery("DELETE FROM odeme_taksitleri WHERE provider_id = ?", [$provider_id]);
            }

            $success_message = 'Ödeme sağlayıcısı başarıyla güncellendi.';
            
            // Güncellenmiş bilgileri tekrar getir
            $provider = fetchOne("SELECT * FROM odeme_providerlari WHERE id = ?", [$provider_id]);
            $taksitler = fetchAll("SELECT * FROM odeme_taksitleri WHERE provider_id = ? ORDER BY taksit_sayisi", [$provider_id]);

        } elseif ($action === 'update_api_settings') {
            // API ayarlarını güncelle
            $ortam = $_POST['ortam'] ?? '';
            $api_key = trim($_POST['api_key'] ?? '');
            $api_secret = trim($_POST['api_secret'] ?? '');
            $merchant_id = trim($_POST['merchant_id'] ?? '');
            $terminal_id = trim($_POST['terminal_id'] ?? '');
            $store_key = trim($_POST['store_key'] ?? '');
            $webhook_url = trim($_POST['webhook_url'] ?? '');
            $success_url = trim($_POST['success_url'] ?? '');
            $fail_url = trim($_POST['fail_url'] ?? '');
            $callback_url = trim($_POST['callback_url'] ?? '');
            $timeout_suresi = intval($_POST['timeout_suresi'] ?? 30);
            $retry_sayisi = intval($_POST['retry_sayisi'] ?? 3);
            $log_seviyesi = $_POST['log_seviyesi'] ?? 'info';
            $aktif = isset($_POST['api_aktif']) ? 1 : 0;

            // Mevcut API ayarını kontrol et
            $existing_api = fetchOne("SELECT id FROM odeme_ayarlari WHERE provider_id = ? AND ortam = ?", [$provider_id, $ortam]);
            
            if ($existing_api) {
                // Güncelle
                executeQuery("
                    UPDATE odeme_ayarlari SET 
                        api_key = ?, api_secret = ?, merchant_id = ?, terminal_id = ?, store_key = ?,
                        webhook_url = ?, success_url = ?, fail_url = ?, callback_url = ?,
                        timeout_suresi = ?, retry_sayisi = ?, log_seviyesi = ?, aktif = ?
                    WHERE provider_id = ? AND ortam = ?
                ", [
                    $api_key, $api_secret, $merchant_id, $terminal_id, $store_key,
                    $webhook_url, $success_url, $fail_url, $callback_url,
                    $timeout_suresi, $retry_sayisi, $log_seviyesi, $aktif, $provider_id, $ortam
                ]);
            } else {
                // Yeni ekle
                executeQuery("
                    INSERT INTO odeme_ayarlari (
                        provider_id, ortam, api_key, api_secret, merchant_id, terminal_id, store_key,
                        webhook_url, success_url, fail_url, callback_url, timeout_suresi, retry_sayisi, log_seviyesi, aktif
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $provider_id, $ortam, $api_key, $api_secret, $merchant_id, $terminal_id, $store_key,
                    $webhook_url, $success_url, $fail_url, $callback_url, $timeout_suresi, $retry_sayisi, $log_seviyesi, $aktif
                ]);
            }

            $success_message = 'API ayarları başarıyla güncellendi.';
            
            // Güncellenmiş API ayarlarını tekrar getir
            $api_ayarlari = fetchAll("SELECT * FROM odeme_ayarlari WHERE provider_id = ? ORDER BY ortam", [$provider_id]);
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

include 'header.php';
?>

<div class="desktop-container">
    <div class="desktop-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title">
                    <i class="fas fa-edit me-2"></i>
                    Ödeme Sağlayıcısı Düzenle
                </h1>
                <p class="page-subtitle mb-0"><?= htmlspecialchars($provider['provider_adi']) ?> sağlayıcısını düzenleyin</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <a href="odeme-yonetimi.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                    <?php if (hasDetailedPermission('odeme_api_test')): ?>
                    <a href="odeme-api-test.php?provider_id=<?= $provider_id ?>" class="btn btn-outline-info">
                        <i class="fas fa-flask"></i> API Test
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" id="providerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                    <i class="fas fa-info-circle me-2"></i>Temel Bilgiler
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>API Ayarları
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="installments-tab" data-bs-toggle="tab" data-bs-target="#installments" type="button" role="tab">
                    <i class="fas fa-credit-card me-2"></i>Taksit Seçenekleri
                </button>
            </li>
        </ul>

        <div class="tab-content" id="providerTabsContent">
            <!-- Temel Bilgiler Tab -->
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_provider">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Temel Bilgiler
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="provider_adi" class="form-label">Sağlayıcı Adı *</label>
                                        <input type="text" class="form-control" id="provider_adi" name="provider_adi" 
                                               value="<?= htmlspecialchars($provider['provider_adi']) ?>" required>
                                        <div class="invalid-feedback">Sağlayıcı adı gereklidir.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="provider_kodu" class="form-label">Sağlayıcı Kodu *</label>
                                        <input type="text" class="form-control" id="provider_kodu" name="provider_kodu" 
                                               value="<?= htmlspecialchars($provider['provider_kodu']) ?>" required>
                                        <div class="form-text">Benzersiz bir kod girin</div>
                                        <div class="invalid-feedback">Sağlayıcı kodu gereklidir.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="provider_tipi" class="form-label">Sağlayıcı Tipi *</label>
                                        <select class="form-select" id="provider_tipi" name="provider_tipi" required>
                                            <option value="">Seçiniz</option>
                                            <option value="iyzico" <?= $provider['provider_tipi'] === 'iyzico' ? 'selected' : '' ?>>İyzico</option>
                                            <option value="paytr" <?= $provider['provider_tipi'] === 'paytr' ? 'selected' : '' ?>>PayTR</option>
                                            <option value="akbank" <?= $provider['provider_tipi'] === 'akbank' ? 'selected' : '' ?>>Akbank</option>
                                            <option value="yapikredi" <?= $provider['provider_tipi'] === 'yapikredi' ? 'selected' : '' ?>>Yapı Kredi</option>
                                            <option value="qnb" <?= $provider['provider_tipi'] === 'qnb' ? 'selected' : '' ?>>QNB Finansbank</option>
                                            <option value="garanti" <?= $provider['provider_tipi'] === 'garanti' ? 'selected' : '' ?>>Garanti BBVA</option>
                                            <option value="isbank" <?= $provider['provider_tipi'] === 'isbank' ? 'selected' : '' ?>>İş Bankası</option>
                                            <option value="ziraat" <?= $provider['provider_tipi'] === 'ziraat' ? 'selected' : '' ?>>Ziraat Bankası</option>
                                            <option value="vakifbank" <?= $provider['provider_tipi'] === 'vakifbank' ? 'selected' : '' ?>>VakıfBank</option>
                                            <option value="halkbank" <?= $provider['provider_tipi'] === 'halkbank' ? 'selected' : '' ?>>Halkbank</option>
                                        </select>
                                        <div class="invalid-feedback">Sağlayıcı tipi gereklidir.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="logo_url" class="form-label">Logo URL</label>
                                        <input type="url" class="form-control" id="logo_url" name="logo_url" 
                                               value="<?= htmlspecialchars($provider['logo_url']) ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="aciklama" class="form-label">Açıklama</label>
                                        <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= htmlspecialchars($provider['aciklama']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-cog me-2"></i>API Ayarları
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="api_url" class="form-label">Canlı API URL</label>
                                        <input type="url" class="form-control" id="api_url" name="api_url" 
                                               value="<?= htmlspecialchars($provider['api_url']) ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="test_api_url" class="form-label">Test API URL</label>
                                        <input type="url" class="form-control" id="test_api_url" name="test_api_url" 
                                               value="<?= htmlspecialchars($provider['test_api_url']) ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="siralama" class="form-label">Sıralama</label>
                                        <input type="number" class="form-control" id="siralama" name="siralama" 
                                               value="<?= htmlspecialchars($provider['siralama']) ?>" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-percentage me-2"></i>Komisyon Ayarları
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="komisyon_orani" class="form-label">Komisyon Oranı (%)</label>
                                        <input type="number" class="form-control" id="komisyon_orani" name="komisyon_orani" 
                                               value="<?= htmlspecialchars($provider['komisyon_orani']) ?>" 
                                               step="0.01" min="0" max="100">
                                    </div>

                                    <div class="mb-3">
                                        <label for="sabit_komisyon" class="form-label">Sabit Komisyon (₺)</label>
                                        <input type="number" class="form-control" id="sabit_komisyon" name="sabit_komisyon" 
                                               value="<?= htmlspecialchars($provider['sabit_komisyon']) ?>" 
                                               step="0.01" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label for="minimum_tutar" class="form-label">Minimum Tutar (₺)</label>
                                        <input type="number" class="form-control" id="minimum_tutar" name="minimum_tutar" 
                                               value="<?= htmlspecialchars($provider['minimum_tutar']) ?>" 
                                               step="0.01" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label for="maksimum_tutar" class="form-label">Maksimum Tutar (₺)</label>
                                        <input type="number" class="form-control" id="maksimum_tutar" name="maksimum_tutar" 
                                               value="<?= htmlspecialchars($provider['maksimum_tutar']) ?>" 
                                               step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-star me-2"></i>Özellikler
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="aktif" name="aktif" 
                                                       <?= $provider['aktif'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="aktif">
                                                    Aktif
                                                </label>
                                            </div>

                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="varsayilan" name="varsayilan" 
                                                       <?= $provider['varsayilan'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="varsayilan">
                                                    Varsayılan Sağlayıcı
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="taksit_destegi" name="taksit_destegi" 
                                                       <?= $provider['taksit_destegi'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="taksit_destegi">
                                                    Taksit Desteği
                                                </label>
                                            </div>

                                            <div class="mb-3" id="maksimum_taksit_div">
                                                <label for="maksimum_taksit" class="form-label">Maksimum Taksit</label>
                                                <input type="number" class="form-control" id="maksimum_taksit" name="maksimum_taksit" 
                                                       value="<?= htmlspecialchars($provider['maksimum_taksit']) ?>" 
                                                       min="1" max="24">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="webhook_destegi" name="webhook_destegi" 
                                                       <?= $provider['webhook_destegi'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="webhook_destegi">
                                                    Webhook Desteği
                                                </label>
                                            </div>

                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="3d_secure_destegi" name="3d_secure_destegi" 
                                                       <?= $provider['3d_secure_destegi'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="3d_secure_destegi">
                                                    3D Secure Desteği
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="mobil_destegi" name="mobil_destegi" 
                                                       <?= $provider['mobil_destegi'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="mobil_destegi">
                                                    Mobil Desteği
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <button type="submit" class="btn btn-primary btn-lg me-3">
                                        <i class="fas fa-save"></i> Güncelle
                                    </button>
                                    <a href="odeme-yonetimi.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> İptal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- API Ayarları Tab -->
            <div class="tab-pane fade" id="api" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-flask me-2"></i>Test Ortamı Ayarları
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $test_ayarlari = null;
                                foreach ($api_ayarlari as $ayar) {
                                    if ($ayar['ortam'] === 'test') {
                                        $test_ayarlari = $ayar;
                                        break;
                                    }
                                }
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_api_settings">
                                    <input type="hidden" name="ortam" value="test">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <input type="text" class="form-control" name="api_key" 
                                               value="<?= htmlspecialchars($test_ayarlari['api_key'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">API Secret</label>
                                        <input type="password" class="form-control" name="api_secret" 
                                               value="<?= htmlspecialchars($test_ayarlari['api_secret'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Merchant ID</label>
                                        <input type="text" class="form-control" name="merchant_id" 
                                               value="<?= htmlspecialchars($test_ayarlari['merchant_id'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Terminal ID</label>
                                        <input type="text" class="form-control" name="terminal_id" 
                                               value="<?= htmlspecialchars($test_ayarlari['terminal_id'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Store Key</label>
                                        <input type="text" class="form-control" name="store_key" 
                                               value="<?= htmlspecialchars($test_ayarlari['store_key'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Webhook URL</label>
                                        <input type="url" class="form-control" name="webhook_url" 
                                               value="<?= htmlspecialchars($test_ayarlari['webhook_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Success URL</label>
                                        <input type="url" class="form-control" name="success_url" 
                                               value="<?= htmlspecialchars($test_ayarlari['success_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Fail URL</label>
                                        <input type="url" class="form-control" name="fail_url" 
                                               value="<?= htmlspecialchars($test_ayarlari['fail_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Callback URL</label>
                                        <input type="url" class="form-control" name="callback_url" 
                                               value="<?= htmlspecialchars($test_ayarlari['callback_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Timeout Süresi (saniye)</label>
                                        <input type="number" class="form-control" name="timeout_suresi" 
                                               value="<?= htmlspecialchars($test_ayarlari['timeout_suresi'] ?? 30) ?>" min="1" max="300">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Retry Sayısı</label>
                                        <input type="number" class="form-control" name="retry_sayisi" 
                                               value="<?= htmlspecialchars($test_ayarlari['retry_sayisi'] ?? 3) ?>" min="0" max="10">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Log Seviyesi</label>
                                        <select class="form-select" name="log_seviyesi">
                                            <option value="none" <?= ($test_ayarlari['log_seviyesi'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="error" <?= ($test_ayarlari['log_seviyesi'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
                                            <option value="warning" <?= ($test_ayarlari['log_seviyesi'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning</option>
                                            <option value="info" <?= ($test_ayarlari['log_seviyesi'] ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
                                            <option value="debug" <?= ($test_ayarlari['log_seviyesi'] ?? '') === 'debug' ? 'selected' : '' ?>>Debug</option>
                                        </select>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="test_aktif" name="api_aktif" 
                                               <?= ($test_ayarlari['aktif'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="test_aktif">
                                            Test Ortamı Aktif
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Test Ayarlarını Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-globe me-2"></i>Canlı Ortam Ayarları
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $canli_ayarlari = null;
                                foreach ($api_ayarlari as $ayar) {
                                    if ($ayar['ortam'] === 'canli') {
                                        $canli_ayarlari = $ayar;
                                        break;
                                    }
                                }
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_api_settings">
                                    <input type="hidden" name="ortam" value="canli">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <input type="text" class="form-control" name="api_key" 
                                               value="<?= htmlspecialchars($canli_ayarlari['api_key'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">API Secret</label>
                                        <input type="password" class="form-control" name="api_secret" 
                                               value="<?= htmlspecialchars($canli_ayarlari['api_secret'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Merchant ID</label>
                                        <input type="text" class="form-control" name="merchant_id" 
                                               value="<?= htmlspecialchars($canli_ayarlari['merchant_id'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Terminal ID</label>
                                        <input type="text" class="form-control" name="terminal_id" 
                                               value="<?= htmlspecialchars($canli_ayarlari['terminal_id'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Store Key</label>
                                        <input type="text" class="form-control" name="store_key" 
                                               value="<?= htmlspecialchars($canli_ayarlari['store_key'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Webhook URL</label>
                                        <input type="url" class="form-control" name="webhook_url" 
                                               value="<?= htmlspecialchars($canli_ayarlari['webhook_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Success URL</label>
                                        <input type="url" class="form-control" name="success_url" 
                                               value="<?= htmlspecialchars($canli_ayarlari['success_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Fail URL</label>
                                        <input type="url" class="form-control" name="fail_url" 
                                               value="<?= htmlspecialchars($canli_ayarlari['fail_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Callback URL</label>
                                        <input type="url" class="form-control" name="callback_url" 
                                               value="<?= htmlspecialchars($canli_ayarlari['callback_url'] ?? '') ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Timeout Süresi (saniye)</label>
                                        <input type="number" class="form-control" name="timeout_suresi" 
                                               value="<?= htmlspecialchars($canli_ayarlari['timeout_suresi'] ?? 30) ?>" min="1" max="300">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Retry Sayısı</label>
                                        <input type="number" class="form-control" name="retry_sayisi" 
                                               value="<?= htmlspecialchars($canli_ayarlari['retry_sayisi'] ?? 3) ?>" min="0" max="10">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Log Seviyesi</label>
                                        <select class="form-select" name="log_seviyesi">
                                            <option value="none" <?= ($canli_ayarlari['log_seviyesi'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="error" <?= ($canli_ayarlari['log_seviyesi'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
                                            <option value="warning" <?= ($canli_ayarlari['log_seviyesi'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning</option>
                                            <option value="info" <?= ($canli_ayarlari['log_seviyesi'] ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
                                            <option value="debug" <?= ($canli_ayarlari['log_seviyesi'] ?? '') === 'debug' ? 'selected' : '' ?>>Debug</option>
                                        </select>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="canli_aktif" name="api_aktif" 
                                               <?= ($canli_ayarlari['aktif'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="canli_aktif">
                                            Canlı Ortam Aktif
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Canlı Ayarlarını Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taksit Seçenekleri Tab -->
            <div class="tab-pane fade" id="installments" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-credit-card me-2"></i>Taksit Seçenekleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($taksitler)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu sağlayıcı için henüz taksit seçeneği tanımlanmamış.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Taksit Sayısı</th>
                                        <th>Komisyon Oranı</th>
                                        <th>Sabit Komisyon</th>
                                        <th>Minimum Tutar</th>
                                        <th>Maksimum Tutar</th>
                                        <th>Durum</th>
                                        <th>Açıklama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taksitler as $taksit): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?= $taksit['taksit_sayisi'] ?> Taksit</span>
                                        </td>
                                        <td>
                                            <strong><?= number_format($taksit['komisyon_orani'], 2) ?>%</strong>
                                        </td>
                                        <td>
                                            <?= number_format($taksit['sabit_komisyon'], 2) ?>₺
                                        </td>
                                        <td>
                                            <?= number_format($taksit['minimum_tutar'], 2) ?>₺
                                        </td>
                                        <td>
                                            <?= number_format($taksit['maksimum_tutar'], 2) ?>₺
                                        </td>
                                        <td>
                                            <?php if ($taksit['aktif']): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($taksit['aciklama']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validasyonu
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Taksit desteği checkbox'ına göre maksimum taksit alanını göster/gizle
document.getElementById('taksit_destegi').addEventListener('change', function() {
    const maksimumTaksitDiv = document.getElementById('maksimum_taksit_div');
    if (this.checked) {
        maksimumTaksitDiv.style.display = 'block';
    } else {
        maksimumTaksitDiv.style.display = 'none';
    }
});

// Sayfa yüklendiğinde taksit desteği durumunu kontrol et
document.addEventListener('DOMContentLoaded', function() {
    const taksitCheckbox = document.getElementById('taksit_destegi');
    const maksimumTaksitDiv = document.getElementById('maksimum_taksit_div');
    
    if (!taksitCheckbox.checked) {
        maksimumTaksitDiv.style.display = 'none';
    }
});
</script>

<style>
.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
    background: none;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.invalid-feedback {
    display: block;
}

.was-validated .form-control:invalid {
    border-color: #dc3545;
}

.was-validated .form-control:valid {
    border-color: #198754;
}
</style>

<?php include 'footer.php'; ?>
