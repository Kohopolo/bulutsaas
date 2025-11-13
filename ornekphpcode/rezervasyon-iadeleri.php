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

// İade durumlarını otomatik güncelle
function updateIadeStatuses() {
    global $pdo;
    
    try {
        // Tüm iadeleri ve ödemelerini al
        $sql = "SELECT ri.id, ri.iade_tutari, ri.durum,
                       COALESCE(SUM(io.odeme_tutari), 0) as odenen_tutar
                FROM rezervasyon_iadeleri ri
                LEFT JOIN iade_odemeleri io ON ri.id = io.iade_id AND io.durum = 'tamamlandi'
                WHERE ri.durum IN ('aktif', 'onaylandi')
                GROUP BY ri.id";
        
        $iadeler = fetchAll($sql);
        
        foreach ($iadeler as $iade) {
            $yeni_durum = null;
            
            if ($iade['odenen_tutar'] >= $iade['iade_tutari']) {
                // Tamamen ödendi
                $yeni_durum = 'odendi';
            } elseif ($iade['odenen_tutar'] > 0) {
                // Kısmi ödeme
                $yeni_durum = 'onaylandi';
            } else {
                // Hiç ödeme yok
                $yeni_durum = 'aktif';
            }
            
            // Durum değiştiyse güncelle
            if ($yeni_durum && $yeni_durum !== $iade['durum']) {
                $update_sql = "UPDATE rezervasyon_iadeleri SET durum = ? WHERE id = ?";
                executeQuery($update_sql, [$yeni_durum, $iade['id']]);
            }
        }
    } catch (Exception $e) {
        error_log("İade durum güncelleme hatası: " . $e->getMessage());
    }
}

// Sayfa yüklendiğinde iade durumlarını güncelle
updateIadeStatuses();

// İade işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $iade_id = intval($_POST['iade_id'] ?? 0);
    
    if ($action == 'onayla' && $iade_id) {
        $sql = "UPDATE rezervasyon_iadeleri SET durum = 'onaylandi', onay_tarihi = NOW() WHERE id = ?";
        if (executeQuery($sql, [$iade_id])) {
            $success_message = 'İade onaylandı.';
        } else {
            $error_message = 'İade onaylanırken hata oluştu.';
        }
    } elseif ($action == 'odeme' && $iade_id) {
        try {
            $pdo->beginTransaction();
            
            // İade bilgilerini al
            $iade = fetchOne("SELECT * FROM rezervasyon_iadeleri WHERE id = ?", [$iade_id]);
            
            if ($iade) {
                // İade durumunu güncelle
                $sql = "UPDATE rezervasyon_iadeleri SET durum = 'odendi', odeme_tarihi = NOW() WHERE id = ?";
                executeQuery($sql, [$iade_id]);
                
                // Müşteri tablosundaki toplam iade tutarını güncelle
                $musteri_sql = "UPDATE musteriler SET toplam_iade_tutari = toplam_iade_tutari + ? WHERE id = ?";
                executeQuery($musteri_sql, [$iade['iade_tutari'], $iade['musteri_id']]);
                
                $pdo->commit();
                $success_message = 'İade ödemesi tamamlandı ve müşteri kaydı güncellendi.';
            } else {
                $pdo->rollBack();
                $error_message = 'İade kaydı bulunamadı.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'İade ödemesi kaydedilirken hata oluştu: ' . $e->getMessage();
        }
    } elseif ($action == 'iptal' && $iade_id) {
        $sql = "UPDATE rezervasyon_iadeleri SET durum = 'iptal' WHERE id = ?";
        if (executeQuery($sql, [$iade_id])) {
            $success_message = 'İade iptal edildi.';
        } else {
            $error_message = 'İade iptal edilirken hata oluştu.';
        }
    }
}

// Filtreleme
$durum_filtre = $_GET['durum'] ?? '';
$musteri_filtre = $_GET['musteri'] ?? '';

$where_conditions = [];
$params = [];

if ($durum_filtre) {
    $where_conditions[] = "ri.durum = ?";
    $params[] = $durum_filtre;
}

if ($musteri_filtre) {
    $where_conditions[] = "(m.ad LIKE ? OR m.soyad LIKE ? OR m.email LIKE ?)";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Sayfalama
$limit = 20;
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(*) as toplam FROM rezervasyon_iadeleri ri 
              LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
              LEFT JOIN musteriler m ON r.musteri_id = m.id
              $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// İadeleri getir
$sql = "SELECT ri.*, 
               m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email,
               r.rezervasyon_kodu, r.giris_tarihi, r.cikis_tarihi, r.erken_checkout,
               k.ad as kullanici_adi, k.soyad as kullanici_soyadi,
               COALESCE(SUM(io.odeme_tutari), 0) as odenen_tutar
        FROM rezervasyon_iadeleri ri 
        LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN kullanicilar k ON ri.kullanici_id = k.id
        LEFT JOIN iade_odemeleri io ON ri.id = io.iade_id AND io.durum = 'tamamlandi'
        $where_clause 
        GROUP BY ri.id
        ORDER BY ri.olusturma_tarihi DESC 
        LIMIT $limit OFFSET $offset";

$iadeler = fetchAll($sql, $params);

// İstatistikler
$beklemede = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyon_iadeleri WHERE durum = 'beklemede'");
$onaylandi = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyon_iadeleri WHERE durum = 'onaylandi'");
$odendi = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyon_iadeleri WHERE durum = 'odendi'");
$toplam_iade = fetchOne("SELECT SUM(iade_tutari) as toplam FROM rezervasyon_iadeleri WHERE durum = 'odendi'");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyon İadeleri - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-money-bill-wave me-2"></i>Rezervasyon İadeleri
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Yazdır
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Bekleyen İadeler
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $beklemede['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                            Onaylanan İadeler
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $onaylandi['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                            Ödenen İadeler
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $odendi['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Toplam Ödenen
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($toplam_iade['toplam'] ?? 0, 2); ?>₺
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-filter me-2"></i>Filtreler
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="">Tümü</option>
                                    <option value="beklemede" <?php echo $durum_filtre == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                    <option value="onaylandi" <?php echo $durum_filtre == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                    <option value="odendi" <?php echo $durum_filtre == 'odendi' ? 'selected' : ''; ?>>Ödendi</option>
                                    <option value="iptal" <?php echo $durum_filtre == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="musteri" class="form-label">Müşteri</label>
                                <input type="text" class="form-control" id="musteri" name="musteri" 
                                       value="<?php echo htmlspecialchars($musteri_filtre); ?>" 
                                       placeholder="Ad, soyad veya e-posta">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- İadeler Tablosu -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>İade Listesi
                            <span class="badge bg-secondary ms-2"><?php echo $toplam_kayit; ?> kayıt</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($iadeler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">İade kaydı bulunamadı</h5>
                                <p class="text-muted">Henüz hiç iade işlemi yapılmamış.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Rezervasyon</th>
                                            <th>Müşteri</th>
                                            <th>İade Tutarı / Ödenen / Kalan</th>
                                            <th>Durum</th>
                                            <th>Oluşturma Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($iadeler as $iade): ?>
                                        <tr>
                                            <td><?php echo $iade['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($iade['rezervasyon_kodu']); ?></strong>
                                                <?php if ($iade['erken_checkout']): ?>
                                                    <span class="badge bg-warning ms-2">
                                                        <i class="fas fa-clock me-1"></i>Erken Check-out
                                                    </span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">
                                                    <?php echo formatTurkishDate($iade['giris_tarihi']); ?> - 
                                                    <?php echo formatTurkishDate($iade['cikis_tarihi']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($iade['musteri_adi'] . ' ' . $iade['musteri_soyadi']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($iade['musteri_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $iade_tutari = $iade['iade_tutari'];
                                                $odenen_tutar = $iade['odenen_tutar'] ?? 0;
                                                $kalan_tutar = $iade_tutari - $odenen_tutar;
                                                ?>
                                                <div class="small">
                                                    <div><strong class="text-primary">Toplam:</strong> <?php echo number_format($iade_tutari, 2); ?>₺</div>
                                                    <div><strong class="text-success">Ödenen:</strong> <?php echo number_format($odenen_tutar, 2); ?>₺</div>
                                                    <div><strong class="text-warning">Kalan:</strong> <?php echo number_format($kalan_tutar, 2); ?>₺</div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $durum_class = [
                                                    'aktif' => 'success',
                                                    'iptal' => 'danger',
                                                    'beklemede' => 'warning',
                                                    'onaylandi' => 'info',
                                                    'odendi' => 'success'
                                                ];
                                                $durum_text = [
                                                    'aktif' => 'Aktif',
                                                    'iptal' => 'İptal',
                                                    'beklemede' => 'Beklemede',
                                                    'onaylandi' => 'Onaylandı',
                                                    'odendi' => 'Ödendi'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $durum_class[$iade['durum'] ?: 'aktif']; ?>">
                                                    <?php echo $durum_text[$iade['durum'] ?: 'aktif']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatTurkishDate($iade['olusturma_tarihi']); ?>
                                                <br><small class="text-muted">
                                                    <?php echo $iade['kullanici_adi'] ? htmlspecialchars($iade['kullanici_adi'] . ' ' . $iade['kullanici_soyadi']) : 'Sistem'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($iade['durum'] == 'beklemede'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="onayla">
                                                            <input type="hidden" name="iade_id" value="<?php echo $iade['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" 
                                                                    onclick="return confirm('İadeyi onaylamak istediğinizden emin misiniz?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($iade['durum'] == 'onaylandi'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="odeme">
                                                            <input type="hidden" name="iade_id" value="<?php echo $iade['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-primary" 
                                                                    onclick="return confirm('İade ödemesini tamamlandı olarak işaretlemek istediğinizden emin misiniz?')">
                                                                <i class="fas fa-money-bill-wave"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($iade['durum'], ['beklemede', 'onaylandi'])): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="iptal">
                                                            <input type="hidden" name="iade_id" value="<?php echo $iade['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('İadeyi iptal etmek istediğinizden emin misiniz?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" data-bs-target="#iadeDetayModal<?php echo $iade['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- İade Detay Modal -->
                                                <div class="modal fade" id="iadeDetayModal<?php echo $iade['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">İade Detayları</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <strong>İade ID:</strong><br>
                                                                        <?php echo $iade['id']; ?>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <strong>Rezervasyon:</strong><br>
                                                                        <?php echo htmlspecialchars($iade['rezervasyon_kodu']); ?>
                                                                    </div>
                                                                </div>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <strong>Müşteri:</strong><br>
                                                                        <?php echo htmlspecialchars($iade['musteri_adi'] . ' ' . $iade['musteri_soyadi']); ?>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <strong>E-posta:</strong><br>
                                                                        <?php echo htmlspecialchars($iade['musteri_email']); ?>
                                                                    </div>
                                                                </div>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <strong>İade Tutarı:</strong><br>
                                                                        <span class="text-success h5"><?php echo number_format($iade['iade_tutari'], 2); ?>₺</span>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <strong>Durum:</strong><br>
                                                                        <span class="badge bg-<?php echo $durum_class[$iade['durum']]; ?>">
                                                                            <?php echo $durum_text[$iade['durum']]; ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <strong>Oluşturma Tarihi:</strong><br>
                                                                        <?php echo formatTurkishDate($iade['olusturma_tarihi']); ?>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <strong>Oluşturan:</strong><br>
                                                                        <?php echo $iade['kullanici_adi'] ? htmlspecialchars($iade['kullanici_adi'] . ' ' . $iade['kullanici_soyadi']) : 'Sistem'; ?>
                                                                    </div>
                                                                </div>
                                                                <?php if ($iade['iade_nedeni']): ?>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <strong>İade Nedeni:</strong><br>
                                                                        <?php echo htmlspecialchars($iade['iade_nedeni']); ?>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php if ($iade['aciklama']): ?>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <strong>Açıklama:</strong><br>
                                                                        <?php echo htmlspecialchars($iade['aciklama']); ?>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Sayfalama -->
                            <?php if ($toplam_sayfa > 1): ?>
                            <nav aria-label="Sayfa navigasyonu">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&durum=<?php echo urlencode($durum_filtre); ?>&musteri=<?php echo urlencode($musteri_filtre); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
