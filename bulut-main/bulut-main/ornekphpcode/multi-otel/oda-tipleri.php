<?php
/**
 * Multi Otel - Oda Tipleri Yönetimi
 * Otel bazlı oda tipleri listesi ve yönetimi
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
requireDetailedPermission('oda_tipi_goruntule', 'Oda tipi görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
if (!$current_otel) {
    // İlk oteli seç
    $user_oteller = getUserOteller($_SESSION['user_id']);
    if (!empty($user_oteller)) {
        setCurrentOtel($user_oteller[0]['id']);
        $current_otel = getCurrentOtel();
    } else {
        header('Location: oteller.php');
        exit;
    }
}

// Oda tipi silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Bu oda tipine ait rezervasyon var mı kontrol et
    $rezervasyon_kontrol = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE oda_tipi_id = ? AND otel_id = ?", [$id, $current_otel['id']]);
    
    if ($rezervasyon_kontrol['sayi'] > 0) {
        $error_message = 'Bu oda tipine ait rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM oda_tipleri WHERE id = ? AND otel_id = ?";
        if (executeQuery($sql, [$id, $current_otel['id']])) {
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
    
    $sql = "UPDATE oda_tipleri SET durum = ? WHERE id = ? AND otel_id = ?";
    if (executeQuery($sql, [$yeni_durum, $id, $current_otel['id']])) {
        $success_message = 'Oda tipi durumu güncellendi.';
    } else {
        $error_message = 'Durum güncellenirken hata oluştu.';
    }
}

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT *, 
    (SELECT COUNT(*) FROM oda_numaralari WHERE oda_tipi_id = oda_tipleri.id AND otel_id = ? AND durum = 'aktif') as oda_sayisi,
    (SELECT COUNT(*) FROM rezervasyonlar WHERE oda_tipi_id = oda_tipleri.id AND otel_id = ?) as rezervasyon_sayisi
    FROM oda_tipleri 
    WHERE otel_id = ? 
    ORDER BY sira_no ASC, oda_tipi_adi ASC", [$current_otel['id'], $current_otel['id'], $current_otel['id']]);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda Tipleri - <?php echo htmlspecialchars($current_otel['otel_adi']); ?></title>
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
                            <h1 class="h3 mb-0">
                                <i class="fas fa-bed me-2"></i>Oda Tipleri
                                <small class="text-muted">- <?php echo htmlspecialchars($current_otel['otel_adi']); ?></small>
                            </h1>
                            <p class="text-muted">Otel oda tiplerini görüntüleyin ve yönetin</p>
                        </div>
                        <a href="../oda-tipi-ekle.php" class="btn btn-primary">
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

            <!-- Oda Tipleri Listesi -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Oda Tipleri Listesi
                        <span class="badge bg-primary ms-2"><?php echo count($oda_tipleri); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($oda_tipleri)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Oda tipi bulunamadı</h5>
                        <p class="text-muted">Bu otel için henüz oda tipi eklenmemiş.</p>
                        <a href="../oda-tipi-ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>İlk Oda Tipini Ekle
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Oda Tipi</th>
                                    <th>Kapasite</th>
                                    <th>Fiyat</th>
                                    <th>Oda Sayısı</th>
                                    <th>Rezervasyon</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></strong>
                                            <?php if ($oda_tipi['kisa_aciklama']): ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($oda_tipi['kisa_aciklama']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo $oda_tipi['kapasite']; ?> Kişi</strong>
                                            <br>
                                            <small class="text-muted">Max: <?php echo $oda_tipi['max_yetiskin']; ?> Yetişkin, <?php echo $oda_tipi['max_cocuk']; ?> Çocuk</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo number_format($oda_tipi['base_price'], 2); ?> ₺</strong>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst($oda_tipi['fiyatlama_sistemi']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $oda_tipi['oda_sayisi']; ?> Oda</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $oda_tipi['rezervasyon_sayisi']; ?> Rezervasyon</span>
                                    </td>
                                    <td>
                                        <?php
                                        $durum_class = $oda_tipi['durum'] == 'aktif' ? 'success' : 'secondary';
                                        $durum_text = $oda_tipi['durum'] == 'aktif' ? 'Aktif' : 'Pasif';
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?>"><?php echo $durum_text; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../oda-tipi-duzenle.php?id=<?php echo $oda_tipi['id']; ?>" 
                                               class="btn btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($oda_tipi['durum'] == 'aktif'): ?>
                                            <a href="?durum_degistir=<?php echo $oda_tipi['id']; ?>&yeni_durum=pasif" 
                                               class="btn btn-outline-warning" title="Pasif Yap"
                                               onclick="return confirm('Bu oda tipini pasif yapmak istediğinizden emin misiniz?')">
                                                <i class="fas fa-eye-slash"></i>
                                            </a>
                                            <?php else: ?>
                                            <a href="?durum_degistir=<?php echo $oda_tipi['id']; ?>&yeni_durum=aktif" 
                                               class="btn btn-outline-success" title="Aktif Yap">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="?sil=<?php echo $oda_tipi['id']; ?>" 
                                               class="btn btn-outline-danger" title="Sil"
                                               onclick="return confirm('Bu oda tipini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
