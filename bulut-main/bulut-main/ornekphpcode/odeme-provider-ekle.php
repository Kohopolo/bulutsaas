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
requireDetailedPermission('odeme_provider_ekle', 'Ödeme sağlayıcısı ekleme yetkiniz bulunmamaktadır.');

$page_title = "Yeni Ödeme Sağlayıcısı Ekle";
$current_page = "odeme-provider-ekle";

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

        // Sağlayıcı kodunun benzersizliğini kontrol et
        $existing = fetchOne("SELECT id FROM odeme_providerlari WHERE provider_kodu = ?", [$provider_kodu]);
        if ($existing) {
            throw new Exception('Bu sağlayıcı kodu zaten kullanılıyor.');
        }

        // Eğer varsayılan olarak işaretleniyorsa, diğerlerini varsayılan olmaktan çıkar
        if ($varsayilan) {
            executeQuery("UPDATE odeme_providerlari SET varsayilan = 0");
        }

        // Sağlayıcıyı ekle
        $provider_id = executeQuery("
            INSERT INTO odeme_providerlari (
                provider_adi, provider_kodu, provider_tipi, api_url, test_api_url, logo_url,
                aktif, varsayilan, siralama, komisyon_orani, sabit_komisyon, minimum_tutar,
                maksimum_tutar, taksit_destegi, maksimum_taksit, webhook_destegi, secure_3d_destegi,
                mobil_destegi, aciklama
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $provider_adi, $provider_kodu, $provider_tipi, $api_url, $test_api_url, $logo_url,
            $aktif, $varsayilan, $siralama, $komisyon_orani, $sabit_komisyon, $minimum_tutar,
            $maksimum_tutar, $taksit_destegi, $maksimum_taksit, $webhook_destegi, $secure_3d_destegi,
            $mobil_destegi, $aciklama
        ]);

        // Varsayılan taksit seçeneklerini ekle
        if ($taksit_destegi) {
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
        }

        $success_message = 'Ödeme sağlayıcısı başarıyla eklendi.';
        
        // Başarılı ekleme sonrası yönlendirme
        header("Location: odeme-yonetimi.php?success=1");
        exit;

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
                    <i class="fas fa-plus-circle me-2"></i>
                    Yeni Ödeme Sağlayıcısı Ekle
                </h1>
                <p class="page-subtitle mb-0">Yeni sanal pos sağlayıcısı ekleyin</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <a href="odeme-yonetimi.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
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

        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="row">
                <!-- Temel Bilgiler -->
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
                                       value="<?= htmlspecialchars($_POST['provider_adi'] ?? '') ?>" required>
                                <div class="invalid-feedback">Sağlayıcı adı gereklidir.</div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_kodu" class="form-label">Sağlayıcı Kodu *</label>
                                <input type="text" class="form-control" id="provider_kodu" name="provider_kodu" 
                                       value="<?= htmlspecialchars($_POST['provider_kodu'] ?? '') ?>" required>
                                <div class="form-text">Benzersiz bir kod girin (örn: iyzico, paytr)</div>
                                <div class="invalid-feedback">Sağlayıcı kodu gereklidir.</div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_tipi" class="form-label">Sağlayıcı Tipi *</label>
                                <select class="form-select" id="provider_tipi" name="provider_tipi" required>
                                    <option value="">Seçiniz</option>
                                    <option value="iyzico" <?= ($_POST['provider_tipi'] ?? '') === 'iyzico' ? 'selected' : '' ?>>İyzico</option>
                                    <option value="paytr" <?= ($_POST['provider_tipi'] ?? '') === 'paytr' ? 'selected' : '' ?>>PayTR</option>
                                    <option value="akbank" <?= ($_POST['provider_tipi'] ?? '') === 'akbank' ? 'selected' : '' ?>>Akbank</option>
                                    <option value="yapikredi" <?= ($_POST['provider_tipi'] ?? '') === 'yapikredi' ? 'selected' : '' ?>>Yapı Kredi</option>
                                    <option value="qnb" <?= ($_POST['provider_tipi'] ?? '') === 'qnb' ? 'selected' : '' ?>>QNB Finansbank</option>
                                    <option value="garanti" <?= ($_POST['provider_tipi'] ?? '') === 'garanti' ? 'selected' : '' ?>>Garanti BBVA</option>
                                    <option value="isbank" <?= ($_POST['provider_tipi'] ?? '') === 'isbank' ? 'selected' : '' ?>>İş Bankası</option>
                                    <option value="ziraat" <?= ($_POST['provider_tipi'] ?? '') === 'ziraat' ? 'selected' : '' ?>>Ziraat Bankası</option>
                                    <option value="vakifbank" <?= ($_POST['provider_tipi'] ?? '') === 'vakifbank' ? 'selected' : '' ?>>VakıfBank</option>
                                    <option value="halkbank" <?= ($_POST['provider_tipi'] ?? '') === 'halkbank' ? 'selected' : '' ?>>Halkbank</option>
                                </select>
                                <div class="invalid-feedback">Sağlayıcı tipi gereklidir.</div>
                            </div>

                            <div class="mb-3">
                                <label for="logo_url" class="form-label">Logo URL</label>
                                <input type="url" class="form-control" id="logo_url" name="logo_url" 
                                       value="<?= htmlspecialchars($_POST['logo_url'] ?? '') ?>">
                                <div class="form-text">Sağlayıcının logo resminin URL'si</div>
                            </div>

                            <div class="mb-3">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= htmlspecialchars($_POST['aciklama'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Ayarları -->
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
                                       value="<?= htmlspecialchars($_POST['api_url'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="test_api_url" class="form-label">Test API URL</label>
                                <input type="url" class="form-control" id="test_api_url" name="test_api_url" 
                                       value="<?= htmlspecialchars($_POST['test_api_url'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="siralama" class="form-label">Sıralama</label>
                                <input type="number" class="form-control" id="siralama" name="siralama" 
                                       value="<?= htmlspecialchars($_POST['siralama'] ?? '0') ?>" min="0">
                                <div class="form-text">Düşük sayı önce görünür</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Komisyon Ayarları -->
                <div class="col-md-6">
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
                                       value="<?= htmlspecialchars($_POST['komisyon_orani'] ?? '2.90') ?>" 
                                       step="0.01" min="0" max="100">
                            </div>

                            <div class="mb-3">
                                <label for="sabit_komisyon" class="form-label">Sabit Komisyon (₺)</label>
                                <input type="number" class="form-control" id="sabit_komisyon" name="sabit_komisyon" 
                                       value="<?= htmlspecialchars($_POST['sabit_komisyon'] ?? '0.25') ?>" 
                                       step="0.01" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="minimum_tutar" class="form-label">Minimum Tutar (₺)</label>
                                <input type="number" class="form-control" id="minimum_tutar" name="minimum_tutar" 
                                       value="<?= htmlspecialchars($_POST['minimum_tutar'] ?? '0') ?>" 
                                       step="0.01" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="maksimum_tutar" class="form-label">Maksimum Tutar (₺)</label>
                                <input type="number" class="form-control" id="maksimum_tutar" name="maksimum_tutar" 
                                       value="<?= htmlspecialchars($_POST['maksimum_tutar'] ?? '999999.99') ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Özellikler -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-star me-2"></i>Özellikler
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="aktif" name="aktif" 
                                       <?= isset($_POST['aktif']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="aktif">
                                    Aktif
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="varsayilan" name="varsayilan" 
                                       <?= isset($_POST['varsayilan']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="varsayilan">
                                    Varsayılan Sağlayıcı
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="taksit_destegi" name="taksit_destegi" 
                                       <?= isset($_POST['taksit_destegi']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="taksit_destegi">
                                    Taksit Desteği
                                </label>
                            </div>

                            <div class="mb-3" id="maksimum_taksit_div">
                                <label for="maksimum_taksit" class="form-label">Maksimum Taksit Sayısı</label>
                                <input type="number" class="form-control" id="maksimum_taksit" name="maksimum_taksit" 
                                       value="<?= htmlspecialchars($_POST['maksimum_taksit'] ?? '12') ?>" 
                                       min="1" max="24">
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="webhook_destegi" name="webhook_destegi" 
                                       <?= isset($_POST['webhook_destegi']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="webhook_destegi">
                                    Webhook Desteği
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="3d_secure_destegi" name="3d_secure_destegi" 
                                       <?= isset($_POST['3d_secure_destegi']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="3d_secure_destegi">
                                    3D Secure Desteği
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="mobil_destegi" name="mobil_destegi" 
                                       <?= isset($_POST['mobil_destegi']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="mobil_destegi">
                                    Mobil Desteği
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Butonları -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <button type="submit" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-save"></i> Kaydet
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
