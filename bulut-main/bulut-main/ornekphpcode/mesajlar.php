
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
requireDetailedPermission('mesajlar_goruntule', 'Mesajlar görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Mesaj silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    $sql = "DELETE FROM iletisim_mesajlari WHERE id = ?";
    if (executeQuery($sql, [$id])) {
        $success_message = 'Mesaj başarıyla silindi.';
    } else {
        $error_message = 'Mesaj silinirken hata oluştu.';
    }
}

// Mesaj durumu güncelleme
if (isset($_POST['durum_guncelle']) && isset($_POST['mesaj_id'])) {
    $mesaj_id = intval($_POST['mesaj_id']);
    $yeni_durum = $_POST['yeni_durum'];
    
    $allowed_statuses = ['yeni', 'okundu', 'yanitlandi', 'arsivlendi'];
    if (in_array($yeni_durum, $allowed_statuses)) {
        $sql = "UPDATE iletisim_mesajlari SET durum = ?, okunma_tarihi = CASE WHEN durum = 'yeni' AND ? = 'okundu' THEN NOW() ELSE okunma_tarihi END WHERE id = ?";
        if (executeQuery($sql, [$yeni_durum, $yeni_durum, $mesaj_id])) {
            $success_message = 'Mesaj durumu güncellendi.';
        } else {
            $error_message = 'Durum güncellenirken hata oluştu.';
        }
    }
}

// Filtreleme parametreleri
$durum_filtre = $_GET['durum'] ?? '';
$tarih_filtre = $_GET['tarih'] ?? '';
$arama = $_GET['arama'] ?? '';

// Sayfalama
$sayfa = intval($_GET['sayfa'] ?? 1);
$limit = 20;
$offset = ($sayfa - 1) * $limit;

// Mesajları getir
$where_conditions = [];
$params = [];

if ($durum_filtre) {
    $where_conditions[] = "durum = ?";
    $params[] = $durum_filtre;
}

if ($tarih_filtre) {
    $where_conditions[] = "DATE(olusturma_tarihi) = ?";
    $params[] = $tarih_filtre;
}

if ($arama) {
    $where_conditions[] = "(ad LIKE ? OR email LIKE ? OR konu LIKE ? OR mesaj LIKE ?)";
    $arama_param = '%' . $arama . '%';
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT * FROM iletisim_mesajlari 
        $where_clause 
        ORDER BY olusturma_tarihi DESC 
        LIMIT $limit OFFSET $offset";

$mesajlar = fetchAll($sql, $params);

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(*) as toplam FROM iletisim_mesajlari $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// İstatistikler
$yeni_mesajlar = fetchOne("SELECT COUNT(*) as sayi FROM iletisim_mesajlari WHERE durum = 'yeni'");
$toplam_mesajlar = fetchOne("SELECT COUNT(*) as sayi FROM iletisim_mesajlari");
$bugun_mesajlar = fetchOne("SELECT COUNT(*) as sayi FROM iletisim_mesajlari WHERE DATE(olusturma_tarihi) = CURDATE()");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesajlar - Admin Paneli</title>
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
                            <h1 class="h3 mb-0">İletişim Mesajları</h1>
                            <p class="text-muted">Web sitesinden gelen mesajları yönetin</p>
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

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Yeni Mesajlar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $yeni_mesajlar['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-envelope fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Toplam Mesajlar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $toplam_mesajlar['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Bugünkü Mesajlar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $bugun_mesajlar['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Yanıt Oranı
                                    </div>
                                    <?php 
                                    $yanitlanan = fetchOne("SELECT COUNT(*) as sayi FROM iletisim_mesajlari WHERE durum = 'yanitlandi'");
                                    $yanit_orani = $toplam_mesajlar['sayi'] > 0 ? round(($yanitlanan['sayi'] / $toplam_mesajlar['sayi']) * 100, 1) : 0;
                                    ?>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        %<?php echo $yanit_orani; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-reply fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tüm Durumlar</option>
                                <option value="yeni" <?php echo $durum_filtre == 'yeni' ? 'selected' : ''; ?>>Yeni</option>
                                <option value="okundu" <?php echo $durum_filtre == 'okundu' ? 'selected' : ''; ?>>Okundu</option>
                                <option value="yanitlandi" <?php echo $durum_filtre == 'yanitlandi' ? 'selected' : ''; ?>>Yanıtlandı</option>
                                <option value="arsivlendi" <?php echo $durum_filtre == 'arsivlendi' ? 'selected' : ''; ?>>Arşivlendi</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="tarih" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="tarih" name="tarih" value="<?php echo htmlspecialchars($tarih_filtre); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="arama" class="form-label">Arama</label>
                            <input type="text" class="form-control" id="arama" name="arama" 
                                   value="<?php echo htmlspecialchars($arama); ?>" 
                                   placeholder="Ad, e-posta, konu veya mesaj içeriği">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filtrele
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mesajlar Tablosu -->
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Mesajlar Listesi (<?php echo $toplam_kayit; ?> kayıt)
                    </h6>
                    <?php if ($durum_filtre || $tarih_filtre || $arama): ?>
                    <a href="mesajlar.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Filtreleri Temizle
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Gönderen</th>
                                    <th>Konu</th>
                                    <th>Mesaj</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mesajlar)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-envelope-open fa-3x mb-3 d-block"></i>
                                        <?php if ($durum_filtre || $tarih_filtre || $arama): ?>
                                            Filtrelere uygun mesaj bulunamadı.
                                        <?php else: ?>
                                            Henüz mesaj bulunmuyor.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($mesajlar as $mesaj): ?>
                                <tr class="<?php echo $mesaj['durum'] == 'yeni' ? 'table-warning' : ''; ?>">
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($mesaj['ad']); ?></strong>
                                            <?php if ($mesaj['durum'] == 'yeni'): ?>
                                            <span class="badge bg-primary ms-1">Yeni</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope me-1"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($mesaj['email']); ?>">
                                                <?php echo htmlspecialchars($mesaj['email']); ?>
                                            </a>
                                        </small>
                                        <?php if ($mesaj['telefon']): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <a href="tel:<?php echo htmlspecialchars($mesaj['telefon']); ?>">
                                                <?php echo htmlspecialchars($mesaj['telefon']); ?>
                                            </a>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mesaj['konu']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="mesaj-ozet">
                                            <?php echo htmlspecialchars(mb_substr($mesaj['mesaj'], 0, 100, 'UTF-8')); ?>
                                            <?php if (mb_strlen($mesaj['mesaj'], 'UTF-8') > 100): ?>
                                            <span class="text-muted">...</span>
                                            <br><button class="btn btn-link btn-sm p-0" onclick="showFullMessage(<?php echo $mesaj['id']; ?>)">
                                                Tamamını Oku
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mesaj-tam d-none" id="mesaj_<?php echo $mesaj['id']; ?>">
                                            <?php echo nl2br(htmlspecialchars($mesaj['mesaj'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo formatTurkishDate($mesaj['olusturma_tarihi'], 'd.m.Y'); ?></div>
                                        <small class="text-muted"><?php echo formatTurkishDate($mesaj['olusturma_tarihi'], 'H:i'); ?></small>
                                        <?php if ($mesaj['okunma_tarihi']): ?>
                                        <br><small class="text-success">
                                            <i class="fas fa-eye me-1"></i>
                                            <?php echo formatTurkishDate($mesaj['okunma_tarihi'], 'd.m.Y H:i'); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="mesaj_id" value="<?php echo $mesaj['id']; ?>">
                                            <select name="yeni_durum" class="form-select form-select-sm" 
                                                    onchange="if(confirm('Durumu değiştirmek istediğinizden emin misiniz?')) this.form.submit();">
                                                <option value="yeni" <?php echo $mesaj['durum'] == 'yeni' ? 'selected' : ''; ?>>Yeni</option>
                                                <option value="okundu" <?php echo $mesaj['durum'] == 'okundu' ? 'selected' : ''; ?>>Okundu</option>
                                                <option value="yanitlandi" <?php echo $mesaj['durum'] == 'yanitlandi' ? 'selected' : ''; ?>>Yanıtlandı</option>
                                                <option value="arsivlendi" <?php echo $mesaj['durum'] == 'arsivlendi' ? 'selected' : ''; ?>>Arşivlendi</option>
                                            </select>
                                            <input type="hidden" name="durum_guncelle" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="mailto:<?php echo htmlspecialchars($mesaj['email']); ?>?subject=Re: <?php echo urlencode($mesaj['konu']); ?>&body=<?php echo urlencode('Merhaba ' . $mesaj['ad'] . ',\n\n'); ?>" 
                                               class="btn btn-sm btn-outline-primary" title="E-posta Gönder">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <a href="?sil=<?php echo $mesaj['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Bu mesajı silmek istediğinizden emin misiniz?')"
                                               title="Sil">
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

                    <!-- Sayfalama -->
                    <?php if ($toplam_sayfa > 1): ?>
                    <nav aria-label="Sayfa navigasyonu">
                        <ul class="pagination justify-content-center">
                            <?php if ($sayfa > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo ($sayfa - 1); ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $sayfa - 2);
                            $end = min($toplam_sayfa, $sayfa + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                                <a class="page-link" href="?sayfa=<?php echo $i; ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($sayfa < $toplam_sayfa): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo ($sayfa + 1); ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function showFullMessage(messageId) {
            const ozet = document.querySelector(`#mesaj_${messageId}`).parentElement.querySelector('.mesaj-ozet');
            const tam = document.querySelector(`#mesaj_${messageId}`);
            
            ozet.classList.add('d-none');
            tam.classList.remove('d-none');
        }
    </script>
</body>
</html>
