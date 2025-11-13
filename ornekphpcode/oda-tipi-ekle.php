
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
requireDetailedPermission('oda_tipleri_ekle', 'Oda tipi ekleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

if ($_POST) {
    $oda_tipi_adi = sanitizeString($_POST['oda_tipi_adi'] ?? '');
    $kisa_aciklama = sanitizeString($_POST['kisa_aciklama'] ?? '');
    $uzun_aciklama = sanitizeString($_POST['uzun_aciklama'] ?? '');
    $max_yetiskin = intval($_POST['max_yetiskin'] ?? 2);
    $max_cocuk = intval($_POST['max_cocuk'] ?? 0);
    $sira_no = intval($_POST['sira_no'] ?? 1);
    $durum = $_POST['durum'] ?? 'aktif';
    $base_price = floatval($_POST['base_price'] ?? 100);
    $fiyatlama_sistemi = $_POST['fiyatlama_sistemi'] ?? 'kisi_carpani';
    $video_url = sanitizeString($_POST['video_url'] ?? '');
    $checkin_saati = sanitizeString($_POST['checkin_saati'] ?? '14:00');
    $checkout_saati = sanitizeString($_POST['checkout_saati'] ?? '12:00');
    
    // Validasyon
    if (empty($oda_tipi_adi)) {
        $error_message = 'Oda tipi adı zorunludur.';
    } elseif ($max_yetiskin <= 0) {
        $error_message = 'Maksimum yetişkin sayısı 0\'dan büyük olmalıdır.';
    } else {
        // Aynı isimde oda tipi var mı kontrol et
        $kontrol = fetchOne("SELECT id FROM oda_tipleri WHERE oda_tipi_adi = ?", [$oda_tipi_adi]);
        
        if ($kontrol) {
            $error_message = 'Bu isimde bir oda tipi zaten mevcut.';
        } else {
            $sql = "INSERT INTO oda_tipleri (
                oda_tipi_adi, kisa_aciklama, uzun_aciklama, max_yetiskin, max_cocuk,
                base_price, fiyatlama_sistemi, video_url, youtube_url, checkin_saati, checkout_saati, sira_no, durum, olusturma_tarihi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            try {
                $id = insertAndGetId($sql, [
                    $oda_tipi_adi, $kisa_aciklama, $uzun_aciklama, $max_yetiskin, $max_cocuk,
                    $base_price, $fiyatlama_sistemi, $video_url, $youtube_url, $checkin_saati, $checkout_saati, $sira_no, $durum
                ]);
                
                if ($id) {
                    // Resim yükleme işlemi
                    if (isset($_FILES['galeri_resimleri']) && !empty($_FILES['galeri_resimleri']['name'][0])) {
                        $upload_dir = '../uploads/room-types';
                        $upload_results = uploadMultipleImages($_FILES['galeri_resimleri'], $upload_dir);
                        
                        $successful_uploads = [];
                        foreach ($upload_results as $result) {
                            if ($result['success']) {
                                $successful_uploads[] = $result['relative_path'];
                            }
                        }
                        
                        if (!empty($successful_uploads)) {
                            saveRoomTypeImages($id, $successful_uploads);
                        }
                    }
                    
                    $success_message = 'Oda tipi başarıyla eklendi.';
                    // Formu temizle
                    $_POST = [];
                } else {
                    $error_message = 'Oda tipi eklenirken hata oluştu.';
                }
            } catch (Exception $e) {
                $error_message = 'Veritabanı hatası: ' . $e->getMessage();
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
    <title>Yeni Oda Tipi Ekle - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

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
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
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
                            <h1 class="h3 mb-0">Yeni Oda Tipi Ekle</h1>
                            <p class="text-muted">Yeni bir oda tipi oluşturun</p>
                        </div>
                        <a href="oda-tipleri.php" class="btn btn-secondary">
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
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Oda Tipi Bilgileri</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <?php echo generateCSRFToken(); ?>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="oda_tipi_adi" class="form-label">Oda Tipi Adı *</label>
                                            <input type="text" class="form-control" id="oda_tipi_adi" name="oda_tipi_adi" 
                                                   value="<?php echo htmlspecialchars($_POST['oda_tipi_adi'] ?? ''); ?>" 
                                                   placeholder="Örn: Standart Oda, Deluxe Oda" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="sira_no" class="form-label">Sıra No</label>
                                            <input type="number" class="form-control" id="sira_no" name="sira_no" 
                                                   value="<?php echo htmlspecialchars($_POST['sira_no'] ?? '1'); ?>" min="1">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="kisa_aciklama" class="form-label">Kısa Açıklama</label>
                                    <input type="text" class="form-control" id="kisa_aciklama" name="kisa_aciklama" 
                                           value="<?php echo htmlspecialchars($_POST['kisa_aciklama'] ?? ''); ?>" 
                                           placeholder="Oda tipi için kısa açıklama">
                                </div>

                                <div class="mb-3">
                                    <label for="uzun_aciklama" class="form-label">Detaylı Açıklama</label>
                                    <textarea class="form-control" id="uzun_aciklama" name="uzun_aciklama" rows="4" 
                                              placeholder="Oda tipinin detaylı açıklaması, özellikler, imkanlar vb."><?php echo htmlspecialchars($_POST['uzun_aciklama'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_yetiskin" class="form-label">Maksimum Yetişkin Sayısı *</label>
                                            <input type="number" class="form-control" id="max_yetiskin" name="max_yetiskin" 
                                                   value="<?php echo htmlspecialchars($_POST['max_yetiskin'] ?? '2'); ?>" 
                                                   min="1" max="10" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_cocuk" class="form-label">Maksimum Çocuk Sayısı</label>
                                            <input type="number" class="form-control" id="max_cocuk" name="max_cocuk" 
                                                   value="<?php echo htmlspecialchars($_POST['max_cocuk'] ?? '0'); ?>" 
                                                   min="0" max="5">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="base_price" class="form-label">Temel Fiyat (TL) *</label>
                                            <input type="number" class="form-control" id="base_price" name="base_price" 
                                                   value="<?php echo htmlspecialchars($_POST['base_price'] ?? '100'); ?>" 
                                                   min="0" step="0.01" required>
                                            <small class="text-muted">Sezonluk çarpanlar için kullanılacak temel fiyat</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <h6 class="text-primary mb-3">Check-in / Check-out Saatleri</h6>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="checkin_saati" class="form-label">Check-in Saati</label>
                                            <input type="time" class="form-control" id="checkin_saati" name="checkin_saati" 
                                                   value="<?php echo htmlspecialchars($_POST['checkin_saati'] ?? '14:00'); ?>">
                                            <small class="text-muted">Misafirlerin odaya giriş yapabileceği saat</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="checkout_saati" class="form-label">Check-out Saati</label>
                                            <input type="time" class="form-control" id="checkout_saati" name="checkout_saati" 
                                                   value="<?php echo htmlspecialchars($_POST['checkout_saati'] ?? '12:00'); ?>">
                                            <small class="text-muted">Misafirlerin odayı boşaltması gereken saat</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <h6 class="text-primary mb-3">Fiyatlama Sistemi</h6>

                                <div class="mb-3">
                                    <label for="fiyatlama_sistemi" class="form-label">Fiyatlama Sistemi</label>
                                    <select class="form-select" id="fiyatlama_sistemi" name="fiyatlama_sistemi" required>
                                        <option value="kisi_carpani" <?php echo ($_POST['fiyatlama_sistemi'] ?? 'kisi_carpani') == 'kisi_carpani' ? 'selected' : ''; ?>>
                                            Kişi Çarpanı Sistemi
                                        </option>
                                        <option value="sabit_fiyat" <?php echo ($_POST['fiyatlama_sistemi'] ?? '') == 'sabit_fiyat' ? 'selected' : ''; ?>>
                                            Sabit Oda Fiyatı
                                        </option>
                                    </select>
                                    <small class="text-muted">Kişi çarpanı: Temel fiyat × kişi sayısı × sezonluk çarpan | Sabit fiyat: Oda için sabit fiyat</small>
                                </div>

                                <hr>

                                <h6 class="text-primary mb-3">Medya Ayarları</h6>

                                <div class="mb-3">
                                    <label for="galeri_resimleri" class="form-label">Oda Resimleri</label>
                                    <input type="file" class="form-control" id="galeri_resimleri" name="galeri_resimleri[]" 
                                           multiple accept="image/jpeg,image/png,image/gif">
                                    <small class="text-muted">Birden fazla resim seçebilirsiniz. Resimler otomatik olarak WebP formatına dönüştürülecektir. (Maksimum 5MB/resim)</small>
                                    <div id="image-preview" class="mt-2 row"></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_url" class="form-label">Video URL</label>
                                            <input type="url" class="form-control" id="video_url" name="video_url" 
                                                   value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>" 
                                                   placeholder="https://example.com/video.mp4">
                                            <small class="text-muted">Oda tanıtım videosu URL'si</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="youtube_url" class="form-label">YouTube URL</label>
                                            <input type="url" class="form-control" id="youtube_url" name="youtube_url" 
                                                   value="<?php echo htmlspecialchars($_POST['youtube_url'] ?? ''); ?>" 
                                                   placeholder="https://www.youtube.com/watch?v=...">
                                            <small class="text-muted">YouTube video URL'si</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label for="durum" class="form-label">Durum</label>
                                    <select class="form-select" id="durum" name="durum">
                                        <option value="aktif" <?php echo ($_POST['durum'] ?? 'aktif') == 'aktif' ? 'selected' : ''; ?>>
                                            Aktif
                                        </option>
                                        <option value="pasif" <?php echo ($_POST['durum'] ?? '') == 'pasif' ? 'selected' : ''; ?>>
                                            Pasif
                                        </option>
                                    </select>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="oda-tipleri.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>İptal
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-info">Yardım</h6>
                        </div>
                        <div class="card-body">
                            <h6>Önemli Notlar:</h6>
                            <ul class="list-unstyled">
                                <li>• Oda tipi adı benzersiz olmalıdır</li>
                                <li>• Sıra numarası görüntüleme sırasını belirler</li>
                                <li>• Fiyatlandırma artık Fiyat Yönetimi bölümünden yapılmaktadır</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Fiyat tipi değiştiğinde kişi başı ayarları göster/gizle
        // Bu kod artık gerekli değil - fiyat alanları kaldırıldı
        
        // Resim önizleme
        document.getElementById('galeri_resimleri').addEventListener('change', function(e) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            const files = Array.from(e.target.files);
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const col = document.createElement('div');
                        col.className = 'col-md-3 mb-2';
                        col.innerHTML = `
                            <div class="card">
                                <img src="${e.target.result}" class="card-img-top" style="height: 100px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <small class="text-muted">${file.name}</small>
                                </div>
                            </div>
                        `;
                        preview.appendChild(col);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        // Sayfa yüklendiğinde kontrol et - artık gerekli değil
        // Fiyat alanları kaldırıldığı için bu kod da kaldırıldı
    </script>
</body>
</html>
