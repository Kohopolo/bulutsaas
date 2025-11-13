<?php
/**
 * Multi Otel - Rezervasyon Listesi
 * Otel bazlı rezervasyon listesi ve yönetimi
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
requireDetailedPermission('rezervasyon_goruntule', 'Rezervasyon görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Başarı mesajını session'dan al
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Multi rezervasyon başarı mesajı
if (isset($_GET['multi_success']) && $_GET['multi_success'] == '1') {
    $count = intval($_GET['count'] ?? 1);
    $success_message = "$count rezervasyon başarıyla oluşturuldu!";
}

// Rezervasyon kodlarını session'dan al
$reservation_codes = $_SESSION['reservation_codes'] ?? [];
if (!empty($reservation_codes)) {
    unset($_SESSION['reservation_codes']);
}

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
if (!$current_otel) {
    // İlk oteli seç
    $user_oteller = getUserOteller($_SESSION['user_id']);
    if (!empty($user_oteller)) {
        setCurrentOtel($user_oteller[0]['id']);
        $current_otel = getCurrentOtel();
    }
}

// Otel değiştirme
if (isset($_GET['otel_id']) && is_numeric($_GET['otel_id'])) {
    $otel_id = intval($_GET['otel_id']);
    if (setCurrentOtel($otel_id)) {
        $current_otel = getCurrentOtel();
    }
}

// Filtreleme parametreleri
$filters = [
    'durum' => $_GET['durum'] ?? '',
    'odeme_durumu' => $_GET['odeme_durumu'] ?? '',
    'tarih_baslangic' => $_GET['tarih_baslangic'] ?? '',
    'tarih_bitis' => $_GET['tarih_bitis'] ?? '',
    'arama' => $_GET['arama'] ?? ''
];

// Sayfalama
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Rezervasyonları getir
$where_conditions = ["r.otel_id = ?"];
$params = [$current_otel['id']];

// Filtreleme koşulları
if (!empty($filters['durum'])) {
    $where_conditions[] = "r.durum = ?";
    $params[] = $filters['durum'];
}

if (!empty($filters['odeme_durumu'])) {
    $where_conditions[] = "r.odeme_durumu = ?";
    $params[] = $filters['odeme_durumu'];
}

if (!empty($filters['tarih_baslangic'])) {
    $where_conditions[] = "r.giris_tarihi >= ?";
    $params[] = $filters['tarih_baslangic'];
}

if (!empty($filters['tarih_bitis'])) {
    $where_conditions[] = "r.giris_tarihi <= ?";
    $params[] = $filters['tarih_bitis'];
}

if (!empty($filters['arama'])) {
    $where_conditions[] = "(r.rezervasyon_kodu LIKE ? OR m.ad LIKE ? OR m.soyad LIKE ? OR m.email LIKE ?)";
    $search_term = '%' . $filters['arama'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Toplam kayıt sayısı
$total_count = fetchOne("
    SELECT COUNT(*) as sayi 
    FROM rezervasyonlar r 
    LEFT JOIN musteriler m ON r.musteri_id = m.id 
    WHERE $where_clause
", $params)['sayi'];

// Rezervasyonları getir
$rezervasyonlar = fetchAll("
    SELECT r.*, 
           ot.oda_tipi_adi, 
           onum.oda_numarasi,
           m.ad as musteri_adi, 
           m.soyad as musteri_soyadi, 
           m.email as musteri_email, 
           m.telefon as musteri_telefon,
           COALESCE(s.ad, 'Web Site') as sales_ad, 
           COALESCE(s.soyad, '') as sales_soyad
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id 
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN kullanicilar s ON r.satis_elemani_id = s.id
    WHERE $where_clause
    ORDER BY r.olusturma_tarihi DESC 
    LIMIT ? OFFSET ?
", array_merge($params, [$limit, $offset]));

// Kullanıcının yetkili olduğu otelleri getir
$user_oteller = getUserOteller($_SESSION['user_id']);

// İstatistikler
$stats = getOtelStats($current_otel['id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyonlar - <?php echo htmlspecialchars($current_otel['otel_adi']); ?></title>
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
                                <i class="fas fa-calendar-alt me-2"></i>Rezervasyonlar
                                <small class="text-muted">- <?php echo htmlspecialchars($current_otel['otel_adi']); ?></small>
                            </h1>
                            <p class="text-muted">Otel rezervasyonlarını görüntüleyin ve yönetin</p>
                        </div>
                        <div class="btn-group">
                            <a href="rezervasyon-ekle.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>Tek Oda
                            </a>
                            <a href="rezervasyon-ekle-multi.php" class="btn btn-primary">
                                <i class="fas fa-hotel me-2"></i>Çoklu Oda
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

            <?php if (!empty($reservation_codes)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Rezervasyon Kodları:</strong> <?php echo implode(', ', $reservation_codes); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- İstatistikler -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['toplam_rezervasyon']; ?></h4>
                                    <p class="mb-0">Toplam Rezervasyon</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['aktif_konaklama']; ?></h4>
                                    <p class="mb-0">Aktif Konaklama</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bed fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($stats['toplam_gelir'], 2); ?> ₺</h4>
                                    <p class="mb-0">Toplam Gelir</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-lira-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0">%<?php echo $stats['doluluk_orani']; ?></h4>
                                    <p class="mb-0">Doluluk Oranı</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-pie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtreler</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="durum">
                                <option value="">Tümü</option>
                                <option value="onaylandi" <?php echo $filters['durum'] == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                <option value="check_in" <?php echo $filters['durum'] == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                <option value="check_out" <?php echo $filters['durum'] == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                <option value="iptal" <?php echo $filters['durum'] == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ödeme Durumu</label>
                            <select class="form-select" name="odeme_durumu">
                                <option value="">Tümü</option>
                                <option value="tamamen_odendi" <?php echo $filters['odeme_durumu'] == 'tamamen_odendi' ? 'selected' : ''; ?>>Tamamen Ödendi</option>
                                <option value="kısmi" <?php echo $filters['odeme_durumu'] == 'kısmi' ? 'selected' : ''; ?>>Kısmi Ödeme</option>
                                <option value="odeme_bekliyor" <?php echo $filters['odeme_durumu'] == 'odeme_bekliyor' ? 'selected' : ''; ?>>Ödeme Bekliyor</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" name="tarih_baslangic" value="<?php echo htmlspecialchars($filters['tarih_baslangic']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" name="tarih_bitis" value="<?php echo htmlspecialchars($filters['tarih_bitis']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Arama</label>
                            <input type="text" class="form-control" name="arama" value="<?php echo htmlspecialchars($filters['arama']); ?>" placeholder="Kod, ad, soyad, email...">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rezervasyon Listesi -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Rezervasyon Listesi
                        <span class="badge bg-primary ms-2"><?php echo $total_count; ?></span>
                    </h5>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Yazdır
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i>Excel
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($rezervasyonlar)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Rezervasyon bulunamadı</h5>
                        <p class="text-muted">Seçilen kriterlere uygun rezervasyon bulunamadı.</p>
                        <a href="rezervasyon-ekle-multi.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yeni Rezervasyon Ekle
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kod</th>
                                    <th>Müşteri</th>
                                    <th>Oda</th>
                                    <th>Tarihler</th>
                                    <th>Misafir</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Ödeme</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($rezervasyon['musteri_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></strong>
                                            <?php if ($rezervasyon['oda_numarasi']): ?>
                                            <br>
                                            <small class="text-muted">Oda: <?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo $rezervasyon['yetiskin_sayisi']; ?> Yetişkin</strong>
                                            <?php if ($rezervasyon['cocuk_sayisi'] > 0): ?>
                                            <br>
                                            <small class="text-muted"><?php echo $rezervasyon['cocuk_sayisi']; ?> Çocuk</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo number_format($rezervasyon['toplam_tutar'], 2); ?> ₺</strong>
                                            <?php if ($rezervasyon['kalan_tutar'] > 0): ?>
                                            <br>
                                            <small class="text-warning">Kalan: <?php echo number_format($rezervasyon['kalan_tutar'], 2); ?> ₺</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $durum_class = [
                                            'onaylandi' => 'success',
                                            'check_in' => 'info',
                                            'check_out' => 'secondary',
                                            'iptal' => 'danger'
                                        ][$rezervasyon['durum']] ?? 'secondary';
                                        
                                        $durum_text = [
                                            'onaylandi' => 'Onaylandı',
                                            'check_in' => 'Check-in',
                                            'check_out' => 'Check-out',
                                            'iptal' => 'İptal'
                                        ][$rezervasyon['durum']] ?? ucfirst($rezervasyon['durum']);
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?>"><?php echo $durum_text; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $odeme_class = [
                                            'tamamen_odendi' => 'success',
                                            'kısmi' => 'warning',
                                            'odeme_bekliyor' => 'danger'
                                        ][$rezervasyon['odeme_durumu']] ?? 'secondary';
                                        
                                        $odeme_text = [
                                            'tamamen_odendi' => 'Tamamen Ödendi',
                                            'kısmi' => 'Kısmi Ödeme',
                                            'odeme_bekliyor' => 'Ödeme Bekliyor'
                                        ][$rezervasyon['odeme_durumu']] ?? ucfirst($rezervasyon['odeme_durumu']);
                                        ?>
                                        <span class="badge bg-<?php echo $odeme_class; ?>"><?php echo $odeme_text; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="rezervasyon-detay.php?id=<?php echo $rezervasyon['id']; ?>" 
                                               class="btn btn-outline-primary" title="Detay">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="rezervasyon-duzenle.php?id=<?php echo $rezervasyon['id']; ?>" 
                                               class="btn btn-outline-warning" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($rezervasyon['durum'] == 'onaylandi'): ?>
                                            <a href="rezervasyon-checkin.php?id=<?php echo $rezervasyon['id']; ?>" 
                                               class="btn btn-outline-info" title="Check-in">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sayfalama -->
                <?php if ($total_count > $limit): ?>
                <div class="card-footer">
                    <nav aria-label="Sayfa navigasyonu">
                        <ul class="pagination justify-content-center mb-0">
                            <?php
                            $total_pages = ceil($total_count / $limit);
                            $current_page = $page;
                            
                            // Önceki sayfa
                            if ($current_page > 1):
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            // Sayfa numaraları
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php
                            // Sonraki sayfa
                            if ($current_page < $total_pages):
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
    function exportToExcel() {
        // Excel export fonksiyonu
        window.location.href = 'ajax/export-reservations.php?' + new URLSearchParams(window.location.search).toString();
    }
    </script>
</body>
</html>
