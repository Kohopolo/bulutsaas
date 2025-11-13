<?php
/**
 * Multi Otel - Otel Düzenleme
 * Mevcut otel bilgilerini düzenleme formu
 */

require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once 'includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
requireDetailedPermission('otel_duzenle', 'Otel düzenleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Otel ID kontrolü
$otel_id = intval($_GET['id'] ?? 0);
if ($otel_id <= 0) {
    header('Location: oteller.php');
    exit;
}

// Otel bilgilerini getir
$otel = getOtel($otel_id);
if (!$otel) {
    header('Location: oteller.php');
    exit;
}

// Form gönderildiğinde işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_otel'])) {
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        try {
            // Form verilerini al ve temizle
            $otel_adi = sanitizeString($_POST['otel_adi'] ?? '');
            $kisa_aciklama = sanitizeString($_POST['kisa_aciklama'] ?? '');
            $adres = sanitizeString($_POST['adres'] ?? '');
            $telefon = sanitizeString($_POST['telefon'] ?? '');
            $email = sanitizeString($_POST['email'] ?? '');
            $website = sanitizeString($_POST['website'] ?? '');
            $sira_no = intval($_POST['sira_no'] ?? 1);
            $durum = $_POST['durum'] ?? 'aktif';
            
            // Validasyon
            $errors = [];
            
            if (empty($otel_adi)) {
                $errors[] = 'Otel adı gerekli';
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçerli bir email adresi girin';
            }
            
            if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
                $errors[] = 'Geçerli bir website URL\'si girin';
            }
            
            if (empty($errors)) {
                // Otel güncelle
                $sql = "UPDATE oteller SET otel_adi = ?, kisa_aciklama = ?, adres = ?, telefon = ?, email = ?, website = ?, sira_no = ?, durum = ? WHERE id = ?";
                
                if (executeQuery($sql, [$otel_adi, $kisa_aciklama, $adres, $telefon, $email, $website, $sira_no, $durum, $otel_id])) {
                    $success_message = 'Otel başarıyla güncellendi.';
                } else {
                    $error_message = 'Otel güncellenirken hata oluştu.';
                }
            } else {
                $error_message = implode('<br>', $errors);
            }
            
        } catch (Exception $e) {
            $error_message = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otel Düzenle - Multi Otel Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/multi-otel-sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Otel Düzenle</h1>
                            <p class="text-muted">Otel bilgilerini güncelleyin</p>
                        </div>
                        <a href="oteller.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Geri Dön
                        </a>
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
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Otel Düzenleme Formu -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Otel Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo generateCSRFTokenInput(); ?>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="otel_adi" class="form-label">Otel Adı *</label>
                                <input type="text" class="form-control" id="otel_adi" name="otel_adi" 
                                       value="<?php echo htmlspecialchars($otel['otel_adi']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sira_no" class="form-label">Sıra No</label>
                                <input type="number" class="form-control" id="sira_no" name="sira_no" 
                                       value="<?php echo htmlspecialchars($otel['sira_no']); ?>" min="1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kisa_aciklama" class="form-label">Kısa Açıklama</label>
                            <textarea class="form-control" id="kisa_aciklama" name="kisa_aciklama" rows="3"><?php echo htmlspecialchars($otel['kisa_aciklama']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adres" class="form-label">Adres</label>
                            <textarea class="form-control" id="adres" name="adres" rows="2"><?php echo htmlspecialchars($otel['adres']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon" 
                                       value="<?php echo htmlspecialchars($otel['telefon']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($otel['email']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="website" class="form-label">Website</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       value="<?php echo htmlspecialchars($otel['website']); ?>" 
                                       placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="aktif" <?php echo $otel['durum'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="pasif" <?php echo $otel['durum'] == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <a href="oteller.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-2"></i>İptal
                            </a>
                            <button type="submit" name="submit_otel" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
