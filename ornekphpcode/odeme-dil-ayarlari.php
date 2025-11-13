<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-dil-ayarlari.php
// Ödeme modülü dil ayarları sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/lang/LanguageManager.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_dil_ayarlari', 'Ödeme dil ayarları yetkiniz bulunmamaktadır.');

$lang_manager = new LanguageManager();
$current_lang = $_SESSION['language'] ?? 'tr';

// Dil değiştirme
if (isset($_POST['change_language'])) {
    $new_lang = $_POST['language'];
    if (in_array($new_lang, ['tr', 'en'])) {
        $_SESSION['language'] = $new_lang;
        $lang_manager->setLanguage($new_lang);
        $current_lang = $new_lang;
    }
}

$lang_manager->setLanguage($current_lang);
$t = $lang_manager;

$page_title = $t->get('admin.payment_management') . ' - ' . $t->get('general.language_settings');
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .language-card {
            transition: transform 0.2s;
        }
        .language-card:hover {
            transform: translateY(-5px);
        }
        .language-active {
            border-color: #0d6efd !important;
            background-color: #e7f3ff !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-language me-2"></i>
                        <?php echo $t->get('general.language_settings'); ?>
                    </h1>
                </div>

                <!-- Dil Seçimi -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-globe me-2"></i>
                                    <?php echo $t->get('general.select_language'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row">
                                    <div class="col-md-6">
                                        <div class="card language-card <?php echo $current_lang == 'tr' ? 'language-active' : ''; ?>">
                                            <div class="card-body text-center">
                                                <i class="fas fa-flag fa-3x text-primary mb-3"></i>
                                                <h5 class="card-title">Türkçe</h5>
                                                <p class="card-text">Türkiye Türkçesi</p>
                                                <input type="radio" name="language" value="tr" <?php echo $current_lang == 'tr' ? 'checked' : ''; ?> class="form-check-input">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card language-card <?php echo $current_lang == 'en' ? 'language-active' : ''; ?>">
                                            <div class="card-body text-center">
                                                <i class="fas fa-flag fa-3x text-success mb-3"></i>
                                                <h5 class="card-title">English</h5>
                                                <p class="card-text">English Language</p>
                                                <input type="radio" name="language" value="en" <?php echo $current_lang == 'en' ? 'checked' : ''; ?> class="form-check-input">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" name="change_language" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            <?php echo $t->get('general.save'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dil Önizleme -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-eye me-2"></i>
                                    <?php echo $t->get('general.language_preview'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><?php echo $t->get('payment.title'); ?></h6>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('payment.payment_method'); ?>:</strong>
                                                <?php echo $t->get('payment.secure_payment'); ?>
                                            </li>
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('payment.payment_provider'); ?>:</strong>
                                                <?php echo $t->get('providers.iyzico'); ?>
                                            </li>
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('payment.installment'); ?>:</strong>
                                                <?php echo $t->get('installments.3'); ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><?php echo $t->get('admin.payment_management'); ?></h6>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('admin.provider_management'); ?></strong>
                                            </li>
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('admin.commission_management'); ?></strong>
                                            </li>
                                            <li class="list-group-item">
                                                <strong><?php echo $t->get('admin.refund_management'); ?></strong>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dil kartlarına tıklama
        document.querySelectorAll('.language-card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Aktif sınıfı güncelle
                document.querySelectorAll('.language-card').forEach(c => c.classList.remove('language-active'));
                this.classList.add('language-active');
            });
        });
    </script>
</body>
</html>
