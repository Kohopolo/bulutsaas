
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
requireDetailedPermission('oda_tipleri_goruntule', 'Oda tipleri görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Önce bu oda tipine ait rezervasyon var mı kontrol et
    $rezervasyon_kontrol = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE oda_tipi_id = ?", [$id]);
    
    if ($rezervasyon_kontrol['sayi'] > 0) {
        $error_message = 'Bu oda tipine ait rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM oda_tipleri WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Oda tipi başarıyla silindi.';
        } else {
            $error_message = 'Oda tipi silinirken hata oluştu.';
        }
    }
}

// Durum değiştirme
if (isset($_GET['durum_degistir']) && is_numeric($_GET['durum_degistir'])) {
    $id = intval($_GET['durum_degistir']);
    $yeni_durum = $_GET['yeni_durum'] == 'aktif' ? 'aktif' : 'pasif';
    
    $sql = "UPDATE oda_tipleri SET durum = ? WHERE id = ?";
    if (executeQuery($sql, [$yeni_durum, $id])) {
        $success_message = 'Oda tipi durumu güncellendi.';
    } else {
        $error_message = 'Durum güncellenirken hata oluştu.';
    }
}

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT *, base_price, checkin_saati, checkout_saati FROM oda_tipleri ORDER BY sira_no ASC, id DESC");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda Tipleri - Admin Paneli</title>
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
                            <h1 class="h3 mb-0">Oda Tipleri</h1>
                            <p class="text-muted">Otel oda tiplerini yönetin</p>
                        </div>
                        <a href="oda-tipi-ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yeni Oda Tipi Ekle
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

            <!-- Oda Tipleri Tablosu -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Oda Tipleri Listesi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Sıra</th>
                                    <th>Oda Tipi Adı</th>
                                    <th>Kapasite</th>
                                    <th>Temel Fiyat</th>
                                    <th>Ortalama Fiyat</th>
                                    <th>Fiyat Tipi</th>
                                    <th>Check-in/out</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($oda_tipleri)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-bed fa-3x mb-3 d-block"></i>
                                        Henüz oda tipi eklenmemiş.
                                        <br>
                                        <a href="oda-tipi-ekle.php" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus me-1"></i>İlk Oda Tipini Ekle
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($oda_tipi['sira_no']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></strong>
                                        <?php if ($oda_tipi['kisa_aciklama']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($oda_tipi['kisa_aciklama']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-user me-1"></i><?php echo $oda_tipi['max_yetiskin']; ?> Yetişkin
                                        <?php if ($oda_tipi['max_cocuk'] > 0): ?>
                                        <br><i class="fas fa-child me-1"></i><?php echo $oda_tipi['max_cocuk']; ?> Çocuk
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo formatCurrency($oda_tipi['base_price'] ?? 0); ?></strong>
                                        <br><small class="text-muted">/ gece (temel)</small>
                                    </td>
                                    <td>
                                        <strong><?php echo formatCurrency($oda_tipi['base_price'] ?? 0); ?></strong>
                                        <br><small class="text-muted">/ gece (ortalama)</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($oda_tipi['fiyatlama_sistemi'] ?? 'sabit_fiyat') == 'kisi_carpani' ? 'info' : 'secondary'; ?>">
                                            <?php echo ($oda_tipi['fiyatlama_sistemi'] ?? 'sabit_fiyat') == 'kisi_carpani' ? 'Kişi Çarpanı' : 'Sabit Fiyat'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-sign-in-alt me-1"></i><?php echo substr($oda_tipi['checkin_saati'] ?? '14:00', 0, 5); ?>
                                            <br>
                                            <i class="fas fa-sign-out-alt me-1"></i><?php echo substr($oda_tipi['checkout_saati'] ?? '12:00', 0, 5); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $oda_tipi['durum'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                            <?php echo $oda_tipi['durum'] == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="oda-tipi-duzenle.php?id=<?php echo $oda_tipi['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($oda_tipi['durum'] == 'aktif'): ?>
                                            <a href="?durum_degistir=<?php echo $oda_tipi['id']; ?>&yeni_durum=pasif" 
                                               class="btn btn-sm btn-outline-warning" title="Pasif Yap"
                                               onclick="return confirm('Bu oda tipini pasif yapmak istediğinizden emin misiniz?')">
                                                <i class="fas fa-eye-slash"></i>
                                            </a>
                                            <?php else: ?>
                                            <a href="?durum_degistir=<?php echo $oda_tipi['id']; ?>&yeni_durum=aktif" 
                                               class="btn btn-sm btn-outline-success" title="Aktif Yap">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="?sil=<?php echo $oda_tipi['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Sil"
                                               onclick="return confirm('Bu oda tipini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
