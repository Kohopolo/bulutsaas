<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Türkçe tarih formatı fonksiyonu
function formatTurkishDate($date, $format = 'd.m.Y H:i') {
    if (empty($date)) return '';
    
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    
    return date($format, $timestamp);
}

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('musteri_duzenle', 'Müşteri düzenleme yetkiniz bulunmamaktadır.');

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: musteriler.php');
    exit;
}

$musteri_id = intval($_GET['id']);
$success_message = '';
$error_message = '';

// Müşteri bilgilerini getir
$musteri = fetchOne("SELECT * FROM musteriler WHERE id = ?", [$musteri_id]);

if (!$musteri) {
    header('Location: musteriler.php');
    exit;
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'Güvenlik hatası. Lütfen sayfayı yenileyin.';
    } else {
        // Form verilerini al ve temizle
        $ad = trim($_POST['ad']);
        $soyad = trim($_POST['soyad']);
        $email = trim($_POST['email']);
        $telefon = preg_replace('/[^0-9]/', '', trim($_POST['telefon'])); // Sadece rakamları al
        $tc_kimlik = preg_replace('/[^0-9]/', '', trim($_POST['tc_kimlik'] ?? '')); // Sadece rakamları al
        $adres = trim($_POST['adres'] ?? '');

        // Validasyon
        $errors = [];

        if (empty($ad)) {
            $errors[] = 'Ad alanı boş olamaz.';
        }

        if (empty($soyad)) {
            $errors[] = 'Soyad alanı boş olamaz.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi giriniz.';
        }

        if (empty($telefon)) {
            $errors[] = 'Telefon alanı boş olamaz.';
        }

        // TC Kimlik kontrolü (eğer girilmişse)
        if (!empty($tc_kimlik) && !preg_match('/^[0-9]{11}$/', $tc_kimlik)) {
            $errors[] = 'TC Kimlik numarası 11 haneli olmalıdır.';
        }

        // E-posta benzersizlik kontrolü (kendi kaydı hariç)
        $existing_email = fetchOne("SELECT id FROM musteriler WHERE email = ? AND id != ?", [$email, $musteri_id]);
        if ($existing_email) {
            $errors[] = 'Bu e-posta adresi başka bir müşteri tarafından kullanılmaktadır.';
        }

        if (empty($errors)) {
            // Güncelleme sorgusu
            $sql = "UPDATE musteriler SET 
                        ad = ?, 
                        soyad = ?, 
                        email = ?, 
                        telefon = ?, 
                        tc_kimlik = ?, 
                        adres = ?,
                        guncelleme_tarihi = NOW()
                    WHERE id = ?";
            
            $params = [$ad, $soyad, $email, $telefon, $tc_kimlik, $adres, $musteri_id];
            
            if (executeQuery($sql, $params)) {
                $success_message = 'Müşteri bilgileri başarıyla güncellendi.';
                // Güncel bilgileri tekrar çek
                $musteri = fetchOne("SELECT * FROM musteriler WHERE id = ?", [$musteri_id]);
            } else {
                $error_message = 'Müşteri güncellenirken hata oluştu.';
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

$page_title = 'Müşteri Düzenle';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #343a40;
            padding-top: 20px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .sidebar-header {
            padding: 20px;
            background: #2c3034;
            color: white;
            text-align: center;
            border-bottom: 1px solid #495057;
        }
        
        .sidebar .sidebar-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .sidebar .components {
            padding: 0;
            margin: 0;
        }
        
        .sidebar .components li {
            list-style: none;
        }
        
        .sidebar .components li a {
            display: block;
            padding: 12px 20px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s;
            border-bottom: 1px solid #495057;
        }
        
        .sidebar .components li a:hover,
        .sidebar .components li.active > a {
            background: #495057;
            color: white;
        }
        
        .sidebar .components li a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sidebar .collapse li a {
            padding-left: 40px;
            background: #2c3034;
            font-size: 0.9rem;
        }
        
        .sidebar .collapse li a:hover,
        .sidebar .collapse li.active a {
            background: #495057;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .btn-outline-primary:hover {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        .text-primary { color: #0d6efd !important; }
        .text-success { color: #198754 !important; }
        .text-info { color: #0dcaf0 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="musteri-detay.php?id=<?php echo $musteri['id']; ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-eye"></i> Detay
                            </a>
                            <a href="musteriler.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Geri Dön
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Müşteri Bilgilerini Düzenle</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo generateCSRFTokenInput(); ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="ad" class="form-label">Ad <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="ad" name="ad" 
                                                       value="<?php echo htmlspecialchars($musteri['ad']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="soyad" class="form-label">Soyad <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="soyad" name="soyad" 
                                                       value="<?php echo htmlspecialchars($musteri['soyad']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($musteri['email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="telefon" class="form-label">Telefon <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" id="telefon" name="telefon" 
                                                       placeholder="0 530 000 00 00" maxlength="13"
                                                       value="<?php echo htmlspecialchars($musteri['telefon']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
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
                                                <input type="hidden" id="tc_kimlik" name="tc_kimlik" value="<?php echo htmlspecialchars($musteri['tc_kimlik'] ?? ''); ?>">
                                                <div class="form-text">11 haneli TC kimlik numarası</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="adres" class="form-label">Adres</label>
                                        <textarea class="form-control" id="adres" name="adres" rows="3" 
                                                  placeholder="Müşteri adresi..."><?php echo htmlspecialchars($musteri['adres'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="musteriler.php" class="btn btn-secondary me-md-2">İptal</a>
                                        <button type="submit" name="update_customer" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Güncelle
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Müşteri Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Kayıt Tarihi:</strong><br>
                                   <?php echo formatTurkishDate($musteri['olusturma_tarihi'], 'd.m.Y H:i'); ?></p>
                                
                                <?php if ($musteri['guncelleme_tarihi']): ?>
                                <p><strong>Son Güncelleme:</strong><br>
                                   <?php echo formatTurkishDate($musteri['guncelleme_tarihi'], 'd.m.Y H:i'); ?></p>
                                <?php endif; ?>

                                <hr>
                                
                                <div class="d-grid">
                                    <a href="musteri-detay.php?id=<?php echo $musteri['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i> Detayları Görüntüle
                                    </a>
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
        // TC Kimlik kutuları sistemi
        setupTCKimlikBoxes();
        
        function setupTCKimlikBoxes() {
            const tcBoxes = document.querySelectorAll('.tc-digit');
            const hiddenInput = document.getElementById('tc_kimlik');
            
            // Mevcut TC kimlik değerini kutulara yükle
            const currentTC = hiddenInput.value;
            if (currentTC && currentTC.length === 11) {
                for (let i = 0; i < 11; i++) {
                    tcBoxes[i].value = currentTC[i];
                    tcBoxes[i].classList.add('filled');
                }
            }
            
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