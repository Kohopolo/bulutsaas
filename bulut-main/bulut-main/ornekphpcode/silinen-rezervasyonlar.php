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

$success_message = '';
$error_message = '';

// Sayfalama
$limit = 20;
$page = isset($_GET['sayfa']) ? max(1, intval($_GET['sayfa'])) : 1;
$offset = ($page - 1) * $limit;

// Filtreleme
$where_conditions = [];
$params = [];

if (isset($_GET['musteri_adi']) && !empty($_GET['musteri_adi'])) {
    $where_conditions[] = "(sr.musteri_adi LIKE ? OR sr.musteri_soyadi LIKE ?)";
    $params[] = '%' . $_GET['musteri_adi'] . '%';
    $params[] = '%' . $_GET['musteri_adi'] . '%';
}

if (isset($_GET['rezervasyon_kodu']) && !empty($_GET['rezervasyon_kodu'])) {
    $where_conditions[] = "sr.rezervasyon_kodu LIKE ?";
    $params[] = '%' . $_GET['rezervasyon_kodu'] . '%';
}

if (isset($_GET['tarih_baslangic']) && !empty($_GET['tarih_baslangic'])) {
    $where_conditions[] = "DATE(sr.silme_tarihi) >= ?";
    $params[] = $_GET['tarih_baslangic'];
}

if (isset($_GET['tarih_bitis']) && !empty($_GET['tarih_bitis'])) {
    $where_conditions[] = "DATE(sr.silme_tarihi) <= ?";
    $params[] = $_GET['tarih_bitis'];
}

if (isset($_GET['silen_kullanici']) && !empty($_GET['silen_kullanici'])) {
    $where_conditions[] = "sr.silen_kullanici_adi LIKE ?";
    $params[] = '%' . $_GET['silen_kullanici'] . '%';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Silinen rezervasyonları getir
$sql = "SELECT sr.*, ot.oda_tipi_adi, odn.oda_numarasi, 
               m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email, m.telefon as musteri_telefon,
               COALESCE(s.ad, 'Web Site') as sales_ad, 
               COALESCE(s.soyad, '') as sales_soyad
        FROM silinen_rezervasyonlar sr 
        LEFT JOIN oda_tipleri ot ON sr.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari odn ON sr.oda_numarasi_id = odn.id 
        LEFT JOIN musteriler m ON sr.musteri_id = m.id
        LEFT JOIN kullanicilar s ON sr.satis_elemani_id = s.id AND s.rol IN ('sales', 'admin', 'superadmin', 'ekip')
        $where_clause 
        ORDER BY sr.silme_tarihi DESC 
        LIMIT $limit OFFSET $offset";

$silinen_rezervasyonlar = fetchAll($sql, $params);

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(*) as toplam FROM silinen_rezervasyonlar sr 
              LEFT JOIN musteriler m ON sr.musteri_id = m.id
              $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// İstatistikler
$bugun_silinen = fetchOne("SELECT COUNT(*) as sayi FROM silinen_rezervasyonlar WHERE DATE(silme_tarihi) = CURDATE()");
$bu_hafta_silinen = fetchOne("SELECT COUNT(*) as sayi FROM silinen_rezervasyonlar WHERE silme_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$bu_ay_silinen = fetchOne("SELECT COUNT(*) as sayi FROM silinen_rezervasyonlar WHERE silme_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$toplam_silinen = fetchOne("SELECT COUNT(*) as sayi FROM silinen_rezervasyonlar");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silinen Rezervasyonlar - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .badge-durum {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-trash-alt text-danger me-2"></i>
                        Silinen Rezervasyonlar
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rezervasyonlar.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Aktif Rezervasyonlar
                        </a>
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
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $bugun_silinen['sayi']; ?></h3>
                            <p><i class="fas fa-calendar-day me-1"></i>Bugün Silinen</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $bu_hafta_silinen['sayi']; ?></h3>
                            <p><i class="fas fa-calendar-week me-1"></i>Bu Hafta Silinen</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $bu_ay_silinen['sayi']; ?></h3>
                            <p><i class="fas fa-calendar-alt me-1"></i>Bu Ay Silinen</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $toplam_silinen['sayi']; ?></h3>
                            <p><i class="fas fa-trash me-1"></i>Toplam Silinen</p>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="filter-card">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtreler</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="musteri_adi" class="form-label">Müşteri Adı</label>
                            <input type="text" class="form-control" id="musteri_adi" name="musteri_adi" 
                                   value="<?php echo htmlspecialchars($_GET['musteri_adi'] ?? ''); ?>" 
                                   placeholder="Müşteri adı ara...">
                        </div>
                        <div class="col-md-3">
                            <label for="rezervasyon_kodu" class="form-label">Rezervasyon Kodu</label>
                            <input type="text" class="form-control" id="rezervasyon_kodu" name="rezervasyon_kodu" 
                                   value="<?php echo htmlspecialchars($_GET['rezervasyon_kodu'] ?? ''); ?>" 
                                   placeholder="Rezervasyon kodu ara...">
                        </div>
                        <div class="col-md-2">
                            <label for="tarih_baslangic" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="tarih_baslangic" name="tarih_baslangic" 
                                   value="<?php echo htmlspecialchars($_GET['tarih_baslangic'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="tarih_bitis" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="tarih_bitis" name="tarih_bitis" 
                                   value="<?php echo htmlspecialchars($_GET['tarih_bitis'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="silen_kullanici" class="form-label">Silen Kullanıcı</label>
                            <input type="text" class="form-control" id="silen_kullanici" name="silen_kullanici" 
                                   value="<?php echo htmlspecialchars($_GET['silen_kullanici'] ?? ''); ?>" 
                                   placeholder="Kullanıcı adı ara...">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filtrele
                            </button>
                            <a href="silinen-rezervasyonlar.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Temizle
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Silinen Rezervasyonlar Tablosu -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Rezervasyon Kodu</th>
                                <th>Müşteri</th>
                                <th>Oda</th>
                                <th>Tarihler</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Silme Tarihi</th>
                                <th>Silen Kullanıcı</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($silinen_rezervasyonlar)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Silinen rezervasyon bulunamadı.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($silinen_rezervasyonlar as $rezervasyon): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#<?php echo $rezervasyon['orijinal_id']; ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong>
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
                                                <strong><?php echo formatTurkishDate($rezervasyon['giris_tarihi'], 'd.m.Y'); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo formatTurkishDate($rezervasyon['cikis_tarihi'], 'd.m.Y'); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong class="text-success"><?php echo number_format($rezervasyon['toplam_tutar'], 2); ?>₺</strong>
                                                <?php if ($rezervasyon['odenen_tutar'] > 0): ?>
                                                    <br>
                                                    <small class="text-muted">Ödenen: <?php echo number_format($rezervasyon['odenen_tutar'], 2); ?>₺</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $durum_class = [
                                                'beklemede' => 'warning',
                                                'onaylandi' => 'info',
                                                'check_in' => 'primary',
                                                'check_out' => 'success',
                                                'iptal' => 'danger',
                                                'tamamlandi' => 'success'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $durum_class[$rezervasyon['durum']] ?? 'secondary'; ?> badge-durum">
                                                <?php echo ucfirst($rezervasyon['durum']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo formatTurkishDate($rezervasyon['silme_tarihi'], 'd.m.Y'); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($rezervasyon['silme_tarihi'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($rezervasyon['silen_kullanici_adi']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="showRezervasyonDetails(<?php echo $rezervasyon['id']; ?>)" 
                                                        title="Detayları Görüntüle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="showRezervasyonHistory(<?php echo $rezervasyon['orijinal_id']; ?>)" 
                                                        title="Geçmişi Görüntüle">
                                                    <i class="fas fa-history"></i>
                                                </button>
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
                    <nav aria-label="Sayfa navigasyonu" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?sayfa=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Önceki</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($toplam_sayfa, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?sayfa=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $toplam_sayfa): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?sayfa=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Sonraki</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        Toplam <?php echo $toplam_kayit; ?> silinen rezervasyon gösteriliyor
                    </small>
                </div>
            </main>
        </div>
    </div>

    <!-- Rezervasyon Detay Modal -->
    <div class="modal fade" id="rezervasyonDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Silinen Rezervasyon Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="rezervasyonDetailContent">
                    <!-- İçerik buraya yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRezervasyonDetails(id) {
            // AJAX ile rezervasyon detaylarını getir
            fetch(`ajax/get-silinen-rezervasyon-detail.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('rezervasyonDetailContent').innerHTML = data;
                    const modal = new bootstrap.Modal(document.getElementById('rezervasyonDetailModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Hata:', error);
                    alert('Rezervasyon detayları yüklenirken hata oluştu.');
                });
        }

        function showRezervasyonHistory(originalId) {
            // Rezervasyon geçmişini göster
            window.open(`rezervasyon-gecmisi.php?id=${originalId}`, '_blank');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
