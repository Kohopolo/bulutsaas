<?php
/**
 * Multi Otel - Otel Listesi
 * Tüm otelleri listeler ve yönetir
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
requireDetailedPermission('otel_goruntule', 'Otel görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Otel silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Bu otele ait rezervasyon var mı kontrol et
    $rezervasyon_kontrol = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE otel_id = ?", [$id]);
    
    if ($rezervasyon_kontrol['sayi'] > 0) {
        $error_message = 'Bu otele ait rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM oteller WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Otel başarıyla silindi.';
        } else {
            $error_message = 'Otel silinirken hata oluştu.';
        }
    }
}

// Durum değiştirme
if (isset($_GET['durum_degistir']) && is_numeric($_GET['durum_degistir'])) {
    $id = intval($_GET['durum_degistir']);
    $yeni_durum = $_GET['yeni_durum'] == 'aktif' ? 'aktif' : 'pasif';
    
    $sql = "UPDATE oteller SET durum = ? WHERE id = ?";
    if (executeQuery($sql, [$yeni_durum, $id])) {
        $success_message = 'Otel durumu güncellendi.';
    } else {
        $error_message = 'Durum güncellenirken hata oluştu.';
    }
}

// Otelleri getir
$oteller = fetchAll("SELECT *, 
    (SELECT COUNT(*) FROM rezervasyonlar WHERE otel_id = oteller.id) as rezervasyon_sayisi,
    (SELECT COUNT(*) FROM oda_tipleri WHERE otel_id = oteller.id AND durum = 'aktif') as oda_tipi_sayisi,
    (SELECT COUNT(*) FROM oda_numaralari WHERE otel_id = oteller.id AND durum = 'aktif') as oda_sayisi
    FROM oteller ORDER BY sira_no ASC, otel_adi ASC");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oteller - Multi Otel Yönetimi</title>
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
                            <h1 class="h3 mb-0">Oteller</h1>
                            <p class="text-muted">Tüm otelleri görüntüleyin ve yönetin</p>
                        </div>
                        <a href="otel-ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yeni Otel Ekle
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

            <!-- Oteller Grid -->
            <div class="row">
                <?php if (empty($oteller)): ?>
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Henüz otel eklenmemiş</h5>
                            <p class="text-muted">İlk otelinizi eklemek için yukarıdaki butonu kullanın.</p>
                            <a href="otel-ekle.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>İlk Oteli Ekle
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($oteller as $otel): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($otel['otel_adi']); ?>
                            </h6>
                            <span class="badge bg-<?php echo $otel['durum'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                <?php echo $otel['durum'] == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if ($otel['kisa_aciklama']): ?>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($otel['kisa_aciklama']); ?></p>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="h5 mb-0 text-primary"><?php echo $otel['rezervasyon_sayisi']; ?></div>
                                    <small class="text-muted">Rezervasyon</small>
                                </div>
                                <div class="col-4">
                                    <div class="h5 mb-0 text-success"><?php echo $otel['oda_tipi_sayisi']; ?></div>
                                    <small class="text-muted">Oda Tipi</small>
                                </div>
                                <div class="col-4">
                                    <div class="h5 mb-0 text-info"><?php echo $otel['oda_sayisi']; ?></div>
                                    <small class="text-muted">Oda</small>
                                </div>
                            </div>
                            
                            <?php if ($otel['telefon'] || $otel['email']): ?>
                            <div class="mb-3">
                                <?php if ($otel['telefon']): ?>
                                <div class="mb-1">
                                    <i class="fas fa-phone me-2 text-muted"></i>
                                    <small><?php echo htmlspecialchars($otel['telefon']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($otel['email']): ?>
                                <div class="mb-1">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <small><?php echo htmlspecialchars($otel['email']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <a href="otel-duzenle.php?id=<?php echo $otel['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-edit"></i> Düzenle
                                </a>
                                
                                <?php if ($otel['durum'] == 'aktif'): ?>
                                <a href="?durum_degistir=<?php echo $otel['id']; ?>&yeni_durum=pasif" 
                                   class="btn btn-outline-warning btn-sm"
                                   onclick="return confirm('Bu oteli pasif yapmak istediğinizden emin misiniz?')">
                                    <i class="fas fa-eye-slash"></i> Pasif
                                </a>
                                <?php else: ?>
                                <a href="?durum_degistir=<?php echo $otel['id']; ?>&yeni_durum=aktif" 
                                   class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-eye"></i> Aktif
                                </a>
                                <?php endif; ?>
                                
                                <a href="?sil=<?php echo $otel['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('Bu oteli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
                                    <i class="fas fa-trash"></i> Sil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
