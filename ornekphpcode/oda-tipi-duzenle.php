
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
requireDetailedPermission('oda_tipleri_duzenle', 'Oda tipi düzenleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: oda-tipleri.php');
    exit;
}

$id = intval($_GET['id']);

// Oda tipini getir
$oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ?", [$id]);

if (!$oda_tipi) {
    header('Location: oda-tipleri.php');
    exit;
}

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
    $youtube_url = sanitizeString($_POST['youtube_url'] ?? '');
    
    // Validasyon
    if (empty($oda_tipi_adi)) {
        $error_message = 'Oda tipi adı zorunludur.';
    } elseif ($max_yetiskin <= 0) {
        $error_message = 'Maksimum yetişkin sayısı 0\'dan büyük olmalıdır.';
    } else {
        // Aynı isimde başka oda tipi var mı kontrol et
        $kontrol = fetchOne("SELECT id FROM oda_tipleri WHERE oda_tipi_adi = ? AND id != ?", [$oda_tipi_adi, $id]);
        
        if ($kontrol) {
            $error_message = 'Bu isimde bir oda tipi zaten mevcut.';
        } else {
            $sql = "UPDATE oda_tipleri SET 
                oda_tipi_adi = ?, kisa_aciklama = ?, uzun_aciklama = ?, 
                max_yetiskin = ?, max_cocuk = ?, base_price = ?, fiyatlama_sistemi = ?, video_url = ?, youtube_url = ?, sira_no = ?, durum = ?
                WHERE id = ?";
            
            $params = [
                $oda_tipi_adi, $kisa_aciklama, $uzun_aciklama,
                $max_yetiskin, $max_cocuk, $base_price, $fiyatlama_sistemi, $video_url, $youtube_url, $sira_no, $durum, $id
            ];
            
            try {
                if (executeQuery($sql, $params)) {
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
                            // Mevcut resimleri al
                            $existing_images = getRoomTypeImages($id);
                            // Yeni resimleri ekle
                            $all_images = array_merge($existing_images, $successful_uploads);
                            saveRoomTypeImages($id, $all_images);
                        }
                    }
                    
                    $success_message = 'Oda tipi başarıyla güncellendi.';
                    // Güncel veriyi tekrar çek
                    $oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ?", [$id]);
                } else {
                    $error_message = 'Oda tipi güncellenirken hata oluştu.';
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
    <title>Oda Tipi Düzenle - Admin Paneli</title>
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
                            <h1 class="h3 mb-0">Oda Tipi Düzenle</h1>
                            <p class="text-muted"><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?> - Düzenleme</p>
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
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Temel Bilgiler</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="oda_tipi_adi" class="form-label">Oda Tipi Adı *</label>
                                            <input type="text" class="form-control" id="oda_tipi_adi" name="oda_tipi_adi" 
                                                   value="<?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="sira_no" class="form-label">Sıra No</label>
                                            <input type="number" class="form-control" id="sira_no" name="sira_no" 
                                                   value="<?php echo $oda_tipi['sira_no']; ?>" min="1">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="kisa_aciklama" class="form-label">Kısa Açıklama</label>
                                    <input type="text" class="form-control" id="kisa_aciklama" name="kisa_aciklama" 
                                           value="<?php echo htmlspecialchars($oda_tipi['kisa_aciklama']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="uzun_aciklama" class="form-label">Detaylı Açıklama</label>
                                    <textarea class="form-control" id="uzun_aciklama" name="uzun_aciklama" rows="4"><?php echo htmlspecialchars($oda_tipi['uzun_aciklama']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_yetiskin" class="form-label">Maksimum Yetişkin Sayısı *</label>
                                            <input type="number" class="form-control" id="max_yetiskin" name="max_yetiskin" 
                                                   value="<?php echo $oda_tipi['max_yetiskin']; ?>" 
                                                   min="1" max="10" required onchange="updateAdultMultipliers()">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_cocuk" class="form-label">Maksimum Çocuk Sayısı</label>
                                            <input type="number" class="form-control" id="max_cocuk" name="max_cocuk" 
                                                   value="<?php echo $oda_tipi['max_cocuk']; ?>" min="0" max="5">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="base_price" class="form-label">Temel Fiyat (TL) *</label>
                                            <input type="number" class="form-control" id="base_price" name="base_price" 
                                                   value="<?php echo $oda_tipi['base_price'] ?? 100; ?>" 
                                                   min="0" step="0.01" required>
                                            <small class="text-muted">Sezonluk çarpanlar için kullanılacak temel fiyat</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="fiyatlama_sistemi" class="form-label">Fiyatlama Sistemi</label>
                                            <select class="form-select" id="fiyatlama_sistemi" name="fiyatlama_sistemi" required>
                                                <option value="kisi_carpani" <?php echo ($oda_tipi['fiyatlama_sistemi'] ?? 'kisi_carpani') == 'kisi_carpani' ? 'selected' : ''; ?>>
                                                    Kişi Çarpanı Sistemi
                                                </option>
                                                <option value="sabit_fiyat" <?php echo ($oda_tipi['fiyatlama_sistemi'] ?? '') == 'sabit_fiyat' ? 'selected' : ''; ?>>
                                                    Sabit Oda Fiyatı
                                                </option>
                                            </select>
                                            <small class="text-muted">Kişi çarpanı: Temel fiyat × kişi sayısı × sezonluk çarpan | Sabit fiyat: Oda için sabit fiyat</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="durum" class="form-label">Durum</label>
                                            <select class="form-select" id="durum" name="durum">
                                                <option value="aktif" <?php echo $oda_tipi['durum'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                <option value="pasif" <?php echo $oda_tipi['durum'] == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medya Ayarları -->
                        <div class="card shadow mt-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Medya Ayarları</h6>
                            </div>
                            <div class="card-body">
                                <!-- Mevcut Resimler -->
                                <?php 
                                $existing_images = getRoomTypeImages($id);
                                if (!empty($existing_images)): 
                                ?>
                                <div class="mb-3">
                                    <label class="form-label">Mevcut Resimler</label>
                                    <div class="row">
                                        <?php foreach ($existing_images as $index => $image): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="position-relative">
                                                <img src="<?php echo $image; ?>" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                        onclick="removeExistingImage(<?php echo $id; ?>, <?php echo $index; ?>)" 
                                                        style="margin: 2px;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Yeni Resim Yükleme -->
                                <div class="mb-3">
                                    <label for="galeri_resimleri" class="form-label">Oda Resimleri</label>
                                    <input type="file" class="form-control" id="galeri_resimleri" name="galeri_resimleri[]" 
                                           multiple accept="image/*">
                                    <div class="form-text">
                                        Birden fazla resim seçebilirsiniz. Resimler otomatik olarak WebP formatına dönüştürülecek. 
                                        Her resim maksimum 5MB olmalıdır.
                                    </div>
                                </div>

                                <!-- Video URL -->
                                <div class="mb-3">
                                    <label for="video_url" class="form-label">Video URL</label>
                                    <input type="url" class="form-control" id="video_url" name="video_url" 
                                           value="<?php echo htmlspecialchars($oda_tipi['video_url'] ?? ''); ?>" 
                                           placeholder="https://example.com/video.mp4">
                                    <div class="form-text">Oda tanıtım videosu için doğrudan video linki</div>
                                </div>

                                <!-- YouTube URL -->
                                <div class="mb-3">
                                    <label for="youtube_url" class="form-label">YouTube URL</label>
                                    <input type="url" class="form-control" id="youtube_url" name="youtube_url" 
                                           value="<?php echo htmlspecialchars($oda_tipi['youtube_url'] ?? ''); ?>" 
                                           placeholder="https://www.youtube.com/watch?v=...">
                                    <div class="form-text">YouTube video linki</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow mt-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-info">Yardım</h6>
                            </div>
                            <div class="card-body">
                                <h6>Fiyatlandırma:</h6>
                                <ul class="list-unstyled small">
                                    <li>• Oda tipi fiyatlandırması artık "Fiyat Yönetimi" bölümünden yapılmaktadır</li>
                                    <li>• Sezonluk fiyatlar, özel fiyatlar ve kampanya fiyatları ayrı ayrı tanımlanabilir</li>
                                    <li>• Çocuk yaş politikaları ve çarpanlar fiyat yönetiminde ayarlanır</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <a href="oda-tipleri.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>İptal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Güncelle
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Mevcut resim silme fonksiyonu
        function removeExistingImage(roomTypeId, imageIndex) {
            if (confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
                fetch('ajax/remove-room-image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        room_type_id: roomTypeId,
                        image_index: imageIndex
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Sayfayı yenile
                    } else {
                        alert('Resim silinirken hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu');
                });
            }
        }
    </script>
</body>
</html>
