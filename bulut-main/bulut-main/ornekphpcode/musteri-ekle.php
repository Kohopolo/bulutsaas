<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('musteri_ekle', 'Müşteri ekleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['musteri_ekle'])) {
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        // Form verilerini al ve temizle
        $ad = sanitizeString($_POST['ad']);
        $soyad = sanitizeString($_POST['soyad']);
        $email = sanitizeString($_POST['email']);
        $telefon = preg_replace('/[^0-9]/', '', sanitizeString($_POST['telefon'])); // Sadece rakamları al
        $tc_kimlik = preg_replace('/[^0-9]/', '', sanitizeString($_POST['tc_kimlik'])); // Sadece rakamları al
        $dogum_tarihi = sanitizeString($_POST['dogum_tarihi']);
        $cinsiyet = sanitizeString($_POST['cinsiyet']);
        $adres = sanitizeString($_POST['adres']);
        $sehir = sanitizeString($_POST['sehir']);
        $ulke = sanitizeString($_POST['ulke']);
        $notlar = sanitizeString($_POST['notlar']);
        
        // Validasyon
        if (empty($ad) || empty($soyad) || empty($email) || empty($telefon)) {
            $error_message = 'Ad, soyad, e-posta ve telefon alanları zorunludur.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Geçerli bir e-posta adresi girin.';
        } else {
            // E-posta kontrolü
            $existing_customer = fetchOne("SELECT id FROM musteriler WHERE email = ?", [$email]);
            if ($existing_customer) {
                $error_message = 'Bu e-posta adresi ile kayıtlı bir müşteri zaten mevcut.';
            } else {
                try {
                    // Müşteriyi veritabanına ekle
                    $sql = "INSERT INTO musteriler (ad, soyad, email, telefon, tc_kimlik, dogum_tarihi, cinsiyet, adres, sehir, ulke, notlar, kayit_tarihi) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $result = executeQuery($sql, [
                        $ad, $soyad, $email, $telefon, $tc_kimlik, 
                        $dogum_tarihi, $cinsiyet, $adres, $sehir, $ulke, $notlar
                    ]);
                    
                    if ($result) {
                        $success_message = 'Müşteri başarıyla eklendi.';
                        // Formu temizle
                        $_POST = [];
                    } else {
                        $error_message = 'Müşteri eklenirken hata oluştu.';
                    }
                } catch (Exception $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Müşteri Ekle - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .tc-kimlik-container {
            max-width: 100%;
        }
        .tc-digit {
            width: 35px;
            height: 45px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        .tc-digit:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .tc-digit.filled {
            background-color: #f8f9fa;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Yeni Müşteri Ekle</h1>
                            <p class="text-muted">Sisteme yeni müşteri bilgilerini ekleyin</p>
                        </div>
                        <div>
                            <a href="musteriler.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Müşteri Listesi
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Müşteri Ekleme Formu -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-plus me-2"></i>Müşteri Bilgileri
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- Kişisel Bilgiler -->
                            <div class="col-lg-6">
                                <h6 class="text-primary mb-3">Kişisel Bilgiler</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="ad" name="ad" 
                                                   value="<?php echo htmlspecialchars($_POST['ad'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="soyad" name="soyad" 
                                                   value="<?php echo htmlspecialchars($_POST['soyad'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="telefon" name="telefon" 
                                           value="<?php echo htmlspecialchars($_POST['telefon'] ?? ''); ?>" 
                                           placeholder="0 530 000 00 00" maxlength="13" required>
                                </div>

                                <div class="mb-3">
                                    <label for="tc_kimlik" class="form-label">TC Kimlik No</label>
                                    <div class="tc-kimlik-container d-flex gap-1">
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="0" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="1" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="2" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="3" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="4" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="5" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="6" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="7" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="8" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="9" required>
                                        <input type="text" class="form-control tc-digit" maxlength="1" data-index="10" required>
                                    </div>
                                    <input type="hidden" id="tc_kimlik" name="tc_kimlik" value="<?php echo htmlspecialchars($_POST['tc_kimlik'] ?? ''); ?>">
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                            <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" 
                                                   value="<?php echo htmlspecialchars($_POST['dogum_tarihi'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cinsiyet" class="form-label">Cinsiyet</label>
                                            <select class="form-select" id="cinsiyet" name="cinsiyet">
                                                <option value="">Seçin</option>
                                                <option value="erkek" <?php echo ($_POST['cinsiyet'] ?? '') == 'erkek' ? 'selected' : ''; ?>>Erkek</option>
                                                <option value="kadin" <?php echo ($_POST['cinsiyet'] ?? '') == 'kadin' ? 'selected' : ''; ?>>Kadın</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Adres Bilgileri -->
                            <div class="col-lg-6">
                                <h6 class="text-primary mb-3">Adres Bilgileri</h6>
                                
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres</label>
                                    <textarea class="form-control" id="adres" name="adres" rows="3"><?php echo htmlspecialchars($_POST['adres'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sehir" class="form-label">Şehir</label>
                                            <input type="text" class="form-control" id="sehir" name="sehir" 
                                                   value="<?php echo htmlspecialchars($_POST['sehir'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ulke" class="form-label">Ülke</label>
                                            <input type="text" class="form-control" id="ulke" name="ulke" 
                                                   value="<?php echo htmlspecialchars($_POST['ulke'] ?? 'Türkiye'); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="notlar" class="form-label">Notlar</label>
                                    <textarea class="form-control" id="notlar" name="notlar" rows="4" 
                                              placeholder="Müşteri hakkında özel notlar..."><?php echo htmlspecialchars($_POST['notlar'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="musteriler.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>İptal
                            </a>
                            <button type="submit" name="musteri_ekle" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Müşteriyi Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    
    <script>
        // TC Kimlik kutuları sistemi
        setupTCKimlikBoxes();
        
        function setupTCKimlikBoxes() {
            const tcBoxes = document.querySelectorAll('.tc-digit');
            const hiddenInput = document.getElementById('tc_kimlik');
            
            tcBoxes.forEach((box, index) => {
                // Sadece rakam girişine izin ver
                box.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/\D/g, '');
                    e.target.value = value;
                    
                    // Dolu kutu olarak işaretle
                    if (value) {
                        e.target.classList.add('filled');
                    } else {
                        e.target.classList.remove('filled');
                    }
                    
                    // Hidden input'u güncelle
                    updateHiddenInput();
                    
                    // Sonraki kutuya geç
                    if (value && index < tcBoxes.length - 1) {
                        tcBoxes[index + 1].focus();
                    }
                });
                
                // Geri tuşu ile önceki kutuya geç
                box.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        tcBoxes[index - 1].focus();
                    }
                });
                
                // Paste işlemi
                box.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                    
                    if (pastedData.length === 11) {
                        // 11 haneli TC kimlik yapıştırıldı
                        for (let i = 0; i < 11; i++) {
                            tcBoxes[i].value = pastedData[i];
                            tcBoxes[i].classList.add('filled');
                        }
                        updateHiddenInput();
                        tcBoxes[10].focus();
                    }
                });
            });
            
            function updateHiddenInput() {
                const tcValue = Array.from(tcBoxes).map(box => box.value).join('');
                hiddenInput.value = tcValue;
            }
        }

        // Telefon formatı
        document.getElementById('telefon').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Sadece rakamları al
            
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3);
                } else if (value.length <= 8) {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6);
                } else if (value.length <= 10) {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 8) + ' ' + value.slice(8);
                } else {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 8) + ' ' + value.slice(8, 10);
                }
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>