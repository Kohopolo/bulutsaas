<?php
/**
 * Multi Otel - Oda Numaraları Yönetimi
 * Otel bazlı oda numaraları listesi ve yönetimi
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
requireDetailedPermission('oda_numarasi_goruntule', 'Oda numarası görüntüleme yetkiniz bulunmamaktadır.');

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

// Oda numarası silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Bu oda numarasına ait rezervasyon var mı kontrol et
    $rezervasyon_kontrol = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE oda_numarasi_id = ? AND otel_id = ?", [$id, $current_otel['id']]);
    
    if ($rezervasyon_kontrol['sayi'] > 0) {
        $error_message = 'Bu oda numarasına ait rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM oda_numaralari WHERE id = ? AND otel_id = ?";
        if (executeQuery($sql, [$id, $current_otel['id']])) {
            $success_message = 'Oda numarası başarıyla silindi.';
        } else {
            $error_message = 'Oda numarası silinirken hata oluştu.';
        }
    }
}

// Durum değiştirme
if (isset($_GET['durum_degistir']) && is_numeric($_GET['durum_degistir'])) {
    $id = intval($_GET['durum_degistir']);
    $yeni_durum = $_GET['yeni_durum'];
    
    $sql = "UPDATE oda_numaralari SET durum = ? WHERE id = ? AND otel_id = ?";
    if (executeQuery($sql, [$yeni_durum, $id, $current_otel['id']])) {
        $success_message = 'Oda durumu güncellendi.';
    } else {
        $error_message = 'Durum güncellenirken hata oluştu.';
    }
}

// Oda numaralarını getir
$oda_numaralari = fetchAll("SELECT onum.*, ot.oda_tipi_adi,
    (SELECT COUNT(*) FROM rezervasyonlar WHERE oda_numarasi_id = onum.id AND otel_id = ? AND durum IN ('onaylandi', 'check_in')) as aktif_rezervasyon
    FROM oda_numaralari onum
    LEFT JOIN oda_tipleri ot ON onum.oda_tipi_id = ot.id
    WHERE onum.otel_id = ? 
    ORDER BY onum.kat ASC, onum.oda_numarasi ASC", [$current_otel['id'], $current_otel['id']]);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda Numaraları - <?php echo htmlspecialchars($current_otel['otel_adi']); ?></title>
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
                                <i class="fas fa-door-open me-2"></i>Oda Numaraları
                                <small class="text-muted">- <?php echo htmlspecialchars($current_otel['otel_adi']); ?></small>
                            </h1>
                            <p class="text-muted">Otel oda numaralarını görüntüleyin ve yönetin</p>
                        </div>
                        <a href="../oda-numarasi-ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yeni Oda Numarası Ekle
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

            <!-- Oda Numaraları Listesi -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Oda Numaraları Listesi
                        <span class="badge bg-primary ms-2"><?php echo count($oda_numaralari); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($oda_numaralari)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-door-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Oda numarası bulunamadı</h5>
                        <p class="text-muted">Bu otel için henüz oda numarası eklenmemiş.</p>
                        <a href="../oda-numarasi-ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>İlk Oda Numarasını Ekle
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Oda Numarası</th>
                                    <th>Kat</th>
                                    <th>Oda Tipi</th>
                                    <th>Durum</th>
                                    <th>Aktif Rezervasyon</th>
                                    <th>Açıklama</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oda_numaralari as $oda): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($oda['oda_numarasi']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $oda['kat'] ? $oda['kat'] . '. Kat' : 'Belirtilmemiş'; ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($oda['oda_tipi_adi']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $durum_class = [
                                            'aktif' => 'success',
                                            'dolu' => 'danger',
                                            'kirli' => 'warning',
                                            'temizlik_bekliyor' => 'info',
                                            'temizlik_yapiliyor' => 'primary',
                                            'bakimda' => 'secondary',
                                            'devre_disi' => 'dark',
                                            'bakim' => 'secondary',
                                            'pasif' => 'secondary',
                                            'temiz' => 'success'
                                        ][$oda['durum']] ?? 'secondary';
                                        
                                        $durum_text = [
                                            'aktif' => 'Aktif',
                                            'dolu' => 'Dolu',
                                            'kirli' => 'Kirli',
                                            'temizlik_bekliyor' => 'Temizlik Bekliyor',
                                            'temizlik_yapiliyor' => 'Temizlik Yapılıyor',
                                            'bakimda' => 'Bakımda',
                                            'devre_disi' => 'Devre Dışı',
                                            'bakim' => 'Bakım',
                                            'pasif' => 'Pasif',
                                            'temiz' => 'Temiz'
                                        ][$oda['durum']] ?? ucfirst($oda['durum']);
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?>"><?php echo $durum_text; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($oda['aktif_rezervasyon'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $oda['aktif_rezervasyon']; ?> Rezervasyon</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Müsait</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($oda['aciklama']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($oda['aciklama']); ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../oda-numarasi-duzenle.php?id=<?php echo $oda['id']; ?>" 
                                               class="btn btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- Durum değiştirme dropdown -->
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Durum Değiştir">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=aktif">Aktif</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=dolu">Dolu</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=kirli">Kirli</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=temizlik_bekliyor">Temizlik Bekliyor</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=temizlik_yapiliyor">Temizlik Yapılıyor</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=bakimda">Bakımda</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=devre_disi">Devre Dışı</a></li>
                                                    <li><a class="dropdown-item" href="?durum_degistir=<?php echo $oda['id']; ?>&yeni_durum=pasif">Pasif</a></li>
                                                </ul>
                                            </div>
                                            
                                            <a href="?sil=<?php echo $oda['id']; ?>" 
                                               class="btn btn-outline-danger" title="Sil"
                                               onclick="return confirm('Bu oda numarasını silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
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
