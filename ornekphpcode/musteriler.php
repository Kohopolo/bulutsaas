
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('musteri_goruntule', 'Müşterileri görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Müşteri silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Önce bu müşteriye ait aktif rezervasyon var mı kontrol et
    $aktif_rezervasyon = fetchOne("
        SELECT COUNT(*) as sayi 
        FROM rezervasyonlar 
        WHERE musteri_email = (SELECT email FROM musteriler WHERE id = ?) 
        AND durum IN ('beklemede', 'onaylandi', 'check_in')
    ", [$id]);
    
    if ($aktif_rezervasyon['sayi'] > 0) {
        $error_message = 'Bu müşteriye ait aktif rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM musteriler WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Müşteri başarıyla silindi.';
        } else {
            $error_message = 'Müşteri silinirken hata oluştu.';
        }
    }
}

// Filtreleme parametreleri
$arama = $_GET['arama'] ?? '';
$durum_filtre = $_GET['durum'] ?? '';

// Sayfalama
$sayfa = intval($_GET['sayfa'] ?? 1);
$limit = 20;
$offset = ($sayfa - 1) * $limit;

// Müşterileri getir
$where_conditions = [];
$params = [];

if ($arama) {
    $where_conditions[] = "(m.ad LIKE ? OR m.soyad LIKE ? OR m.email LIKE ? OR m.telefon LIKE ?)";
    $arama_param = '%' . $arama . '%';
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

if ($durum_filtre) {
    $where_conditions[] = "m.durum = ?";
    $params[] = $durum_filtre;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Müşteri listesi ve istatistikleri
$sql = "SELECT 
            m.*,
            COUNT(r.id) as toplam_rezervasyon,
            SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.toplam_tutar ELSE 0 END) as toplam_harcama,
            MAX(r.olusturma_tarihi) as son_rezervasyon
        FROM musteriler m
        LEFT JOIN rezervasyonlar r ON m.id = r.musteri_id
        $where_clause
        GROUP BY m.id
        ORDER BY m.olusturma_tarihi DESC
        LIMIT $limit OFFSET $offset";

$musteriler = fetchAll($sql, $params);

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(DISTINCT m.id) as toplam FROM musteriler m $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// Genel istatistikler
$toplam_musteri = fetchOne("SELECT COUNT(*) as sayi FROM musteriler");
$bu_ay_yeni = fetchOne("
    SELECT COUNT(*) as sayi 
    FROM musteriler 
    WHERE MONTH(olusturma_tarihi) = MONTH(CURDATE()) 
    AND YEAR(olusturma_tarihi) = YEAR(CURDATE())
");
$en_cok_harcayan = fetchOne("
    SELECT 
        m.ad, m.soyad, 
        SUM(r.toplam_tutar) as toplam_harcama
    FROM musteriler m
    LEFT JOIN rezervasyonlar r ON m.email = r.musteri_email
    WHERE r.durum NOT IN ('iptal')
    GROUP BY m.id
    ORDER BY toplam_harcama DESC
    LIMIT 1
");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteriler - Admin Paneli</title>
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
                            <h1 class="h3 mb-0">Müşteri Yönetimi</h1>
                            <p class="text-muted">Tüm müşterileri görüntüleyin ve yönetin</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" onclick="exportCustomers()">
                                <i class="fas fa-file-excel me-2"></i>Excel'e Aktar
                            </button>
                            <a href="musteri-ekle.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Yeni Müşteri
                            </a>
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
                                        Toplam Müşteri
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $toplam_musteri['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        Bu Ay Yeni
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $bu_ay_yeni['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-12 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        En Çok Harcayan Müşteri
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        <?php if ($en_cok_harcayan): ?>
                                            <?php echo htmlspecialchars($en_cok_harcayan['ad'] . ' ' . $en_cok_harcayan['soyad']); ?>
                                            <small class="text-muted">(<?php echo formatCurrency($en_cok_harcayan['toplam_harcama']); ?>)</small>
                                        <?php else: ?>
                                            Henüz veri yok
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-crown fa-2x text-gray-300"></i>
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
                        <div class="col-md-4">
                            <label for="arama" class="form-label">Arama</label>
                            <input type="text" class="form-control" id="arama" name="arama" 
                                   value="<?php echo htmlspecialchars($arama); ?>" 
                                   placeholder="Ad, soyad, e-posta veya telefon">
                        </div>
                        <div class="col-md-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tüm Durumlar</option>
                                <option value="aktif" <?php echo $durum_filtre == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="pasif" <?php echo $durum_filtre == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filtrele
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="musteriler.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Müşteriler Tablosu -->
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Müşteriler Listesi (<?php echo $toplam_kayit; ?> kayıt)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Müşteri Bilgileri</th>
                                    <th>İletişim</th>
                                    <th>Rezervasyon İstatistikleri</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($musteriler)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                        <?php if ($arama || $durum_filtre): ?>
                                            Filtrelere uygun müşteri bulunamadı.
                                        <?php else: ?>
                                            Henüz müşteri bulunmuyor.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($musteriler as $musteri): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php echo strtoupper(substr($musteri['ad'], 0, 1) . substr($musteri['soyad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?></strong>
                                                <?php if ($musteri['dogum_tarihi']): ?>
                                                <br><small class="text-muted">
                                                    <?php 
                                                    $dogum = new DateTime($musteri['dogum_tarihi']);
                                                    $bugun = new DateTime();
                                                    $yas = $bugun->diff($dogum)->y;
                                                    echo $yas . ' yaşında';
                                                    ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-envelope me-1"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($musteri['email']); ?>">
                                                <?php echo htmlspecialchars($musteri['email']); ?>
                                            </a>
                                        </div>
                                        <?php if ($musteri['telefon']): ?>
                                        <div class="mt-1">
                                            <i class="fas fa-phone me-1"></i>
                                            <a href="tel:<?php echo htmlspecialchars($musteri['telefon']); ?>">
                                                <?php echo htmlspecialchars($musteri['telefon']); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($musteri['adres']): ?>
                                        <div class="mt-1">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <small class="text-muted"><?php echo htmlspecialchars($musteri['adres']); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <strong class="d-block"><?php echo $musteri['toplam_rezervasyon']; ?></strong>
                                                    <small class="text-muted">Rezervasyon</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <strong class="d-block"><?php echo formatCurrency($musteri['toplam_harcama'] ?? 0); ?></strong>
                                                <small class="text-muted">Toplam Harcama</small>
                                            </div>
                                        </div>
                                        <?php if ($musteri['son_rezervasyon']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Son rezervasyon: <?php echo formatTurkishDate($musteri['son_rezervasyon'], 'd.m.Y'); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo formatTurkishDate($musteri['olusturma_tarihi'], 'd.m.Y'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($musteri['durum'] ?? 'aktif') == 'aktif' ? 'success' : 'secondary'; ?>">
                                            <?php echo ($musteri['durum'] ?? 'aktif') == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="musteri-detay.php?id=<?php echo $musteri['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Detay">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="musteri-duzenle.php?id=<?php echo $musteri['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="rezervasyonlar.php?arama=<?php echo urlencode($musteri['email']); ?>" 
                                               class="btn btn-sm btn-outline-info" title="Rezervasyonları">
                                                <i class="fas fa-calendar-check"></i>
                                            </a>
                                            <a href="?sil=<?php echo $musteri['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Sil"
                                               onclick="return confirm('Bu müşteriyi silmek istediğinizden emin misiniz?')">
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
                                <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&arama=<?php echo $arama; ?>&durum=<?php echo $durum_filtre; ?>">
                                    Önceki
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $sayfa - 2); $i <= min($toplam_sayfa, $sayfa + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                                <a class="page-link" href="?sayfa=<?php echo $i; ?>&arama=<?php echo $arama; ?>&durum=<?php echo $durum_filtre; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($sayfa < $toplam_sayfa): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&arama=<?php echo $arama; ?>&durum=<?php echo $durum_filtre; ?>">
                                    Sonraki
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
        function exportCustomers() {
            alert('Müşteri listesi Excel export özelliği yakında eklenecek.');
        }
    </script>
    
    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</body>
</html>
