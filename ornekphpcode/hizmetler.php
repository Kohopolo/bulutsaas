
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
requireDetailedPermission('hizmetler_goruntule', 'Hizmetler görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Hizmet ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $baslik = sanitizeString($_POST['baslik']);
    $aciklama = sanitizeString($_POST['aciklama']);
    $ikon = sanitizeString($_POST['ikon']);
    $sira_no = intval($_POST['sira_no']);
    $durum = $_POST['durum'];
    
    if (empty($baslik)) {
        $error_message = 'Hizmet başlığı gereklidir.';
    } else {
        $sql = "INSERT INTO hizmetler (hizmet_adi, aciklama, ikon, sira_no, durum) VALUES (?, ?, ?, ?, ?)";
        if (executeQuery($sql, [$baslik, $aciklama, $ikon, $sira_no, $durum])) {
            $success_message = 'Hizmet başarıyla eklendi.';
        } else {
            $error_message = 'Hizmet eklenirken hata oluştu.';
        }
    }
}

// Hizmet güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $baslik = sanitizeString($_POST['baslik']);
    $aciklama = sanitizeString($_POST['aciklama']);
    $ikon = sanitizeString($_POST['ikon']);
    $sira_no = intval($_POST['sira_no']);
    $durum = $_POST['durum'];
    
    if (empty($baslik)) {
        $error_message = 'Hizmet başlığı gereklidir.';
    } else {
        $sql = "UPDATE hizmetler SET hizmet_adi = ?, aciklama = ?, ikon = ?, sira_no = ?, durum = ? WHERE id = ?";
        if (executeQuery($sql, [$baslik, $aciklama, $ikon, $sira_no, $durum, $id])) {
            $success_message = 'Hizmet başarıyla güncellendi.';
        } else {
            $error_message = 'Hizmet güncellenirken hata oluştu.';
        }
    }
}

// Hizmet silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    $sql = "DELETE FROM hizmetler WHERE id = ?";
    if (executeQuery($sql, [$id])) {
        $success_message = 'Hizmet başarıyla silindi.';
    } else {
        $error_message = 'Hizmet silinirken hata oluştu.';
    }
}

// Hizmetleri getir
$hizmetler = fetchAll("SELECT * FROM hizmetler ORDER BY sira_no ASC, id ASC");

// Düzenleme için hizmet getir
$edit_hizmet = null;
if (isset($_GET['duzenle']) && is_numeric($_GET['duzenle'])) {
    $edit_hizmet = fetchOne("SELECT * FROM hizmetler WHERE id = ?", [intval($_GET['duzenle'])]);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hizmetler - Admin Paneli</title>
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
                            <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle</a></li>
                            <li><hr class="dropdown-divider"></li>
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
                            <h1 class="h3 mb-0">Hizmetler</h1>
                            <p class="text-muted">Otel hizmetlerini yönetin</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hizmetModal">
                            <i class="fas fa-plus me-2"></i>Yeni Hizmet Ekle
                        </button>
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

            <!-- Hizmetler Listesi -->
            <div class="row">
                <?php if (empty($hizmetler)): ?>
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-concierge-bell fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Henüz hizmet eklenmemiş</h5>
                            <p class="text-muted">İlk hizmetinizi eklemek için yukarıdaki butonu kullanın.</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($hizmetler as $hizmet): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="service-icon">
                                    <i class="<?php echo htmlspecialchars($hizmet['ikon']); ?> fa-2x text-primary"></i>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="?duzenle=<?php echo $hizmet['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Düzenle
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" 
                                               href="?sil=<?php echo $hizmet['id']; ?>"
                                               onclick="return confirm('Bu hizmeti silmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-trash me-2"></i>Sil
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <h5 class="card-title"><?php echo htmlspecialchars($hizmet['hizmet_adi']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($hizmet['aciklama']); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">Sıra: <?php echo $hizmet['sira_no']; ?></small>
                                <?php if ($hizmet['durum'] == 'aktif'): ?>
                                <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hizmet Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="hizmetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?php echo $edit_hizmet ? 'Hizmet Düzenle' : 'Yeni Hizmet Ekle'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_hizmet ? 'update' : 'add'; ?>">
                        <?php if ($edit_hizmet): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_hizmet['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="hizmet_adi" class="form-label">Hizmet Başlığı *</label>
                            <input type="text" class="form-control" id="hizmet_adi" name="hizmet_adi" 
                                   value="<?php echo $edit_hizmet ? htmlspecialchars($edit_hizmet['hizmet_adi']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo $edit_hizmet ? htmlspecialchars($edit_hizmet['aciklama']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ikon" class="form-label">İkon (Font Awesome)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i id="icon-preview" class="<?php echo $edit_hizmet ? htmlspecialchars($edit_hizmet['ikon']) : 'fas fa-concierge-bell'; ?>"></i>
                                </span>
                                <input type="text" class="form-control" id="ikon" name="ikon" 
                                       value="<?php echo $edit_hizmet ? htmlspecialchars($edit_hizmet['ikon']) : 'fas fa-concierge-bell'; ?>"
                                       placeholder="Örn: fas fa-wifi">
                            </div>
                            <div class="form-text">
                                <a href="https://fontawesome.com/icons" target="_blank">Font Awesome ikonları</a> için sınıf adını girin.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sira_no" class="form-label">Sıra No</label>
                                    <input type="number" class="form-control" id="sira_no" name="sira_no" 
                                           value="<?php echo $edit_hizmet ? $edit_hizmet['sira_no'] : '1'; ?>" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="durum" class="form-label">Durum</label>
                                    <select class="form-select" id="durum" name="durum">
                                        <option value="aktif" <?php echo (!$edit_hizmet || $edit_hizmet['durum'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="pasif" <?php echo ($edit_hizmet && $edit_hizmet['durum'] == 'pasif') ? 'selected' : ''; ?>>Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_hizmet ? 'Güncelle' : 'Ekle'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // İkon önizleme
        document.getElementById('ikon').addEventListener('input', function() {
            const iconPreview = document.getElementById('icon-preview');
            const iconClass = this.value.trim();
            
            if (iconClass) {
                iconPreview.className = iconClass;
            } else {
                iconPreview.className = 'fas fa-concierge-bell';
            }
        });

        // Düzenleme modunu aç
        <?php if ($edit_hizmet): ?>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('hizmetModal')).show();
        });
        <?php endif; ?>

        // Modal kapandığında URL'yi temizle
        document.getElementById('hizmetModal').addEventListener('hidden.bs.modal', function() {
            if (window.location.search.includes('duzenle=')) {
                window.location.href = 'hizmetler.php';
            }
        });
    </script>
</body>
</html>
