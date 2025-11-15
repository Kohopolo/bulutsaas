<?php
/**
 * POS Dashboard
 * Restoran, bar, spa POS sistemleri yönetim paneli
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/pos-integration.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('pos_yonetimi')) {
    $_SESSION['error_message'] = 'POS yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'terminal_ekle') {
            $terminalKodu = sanitizeString($_POST['terminal_kodu']);
            $terminalAdi = sanitizeString($_POST['terminal_adi']);
            $terminalTuru = sanitizeString($_POST['terminal_turu']);
            $lokasyon = sanitizeString($_POST['lokasyon']);
            $ipAdresi = sanitizeString($_POST['ip_adresi']);
            
            $sql = "INSERT INTO pos_terminalleri (terminal_kodu, terminal_adi, terminal_turu, lokasyon, ip_adresi, olusturan_kullanici_id) VALUES (?, ?, ?, ?, ?, ?)";
            
            if (executeQuery($sql, [$terminalKodu, $terminalAdi, $terminalTuru, $lokasyon, $ipAdresi, $_SESSION['user_id']])) {
                $success_message = 'Terminal başarıyla eklendi.';
            } else {
                $error_message = 'Terminal eklenirken hata oluştu.';
            }
        }
        
        if ($action == 'urun_ekle') {
            $urunKodu = sanitizeString($_POST['urun_kodu']);
            $urunAdi = sanitizeString($_POST['urun_adi']);
            $kategoriId = intval($_POST['kategori_id']);
            $birimFiyat = floatval($_POST['birim_fiyat']);
            $kdvOrani = floatval($_POST['kdv_orani']);
            $stokTakibi = isset($_POST['stok_takibi']) ? 1 : 0;
            $mevcutStok = $stokTakibi ? floatval($_POST['mevcut_stok']) : null;
            
            $sql = "INSERT INTO pos_menu_urunleri (urun_kodu, urun_adi, kategori_id, birim_fiyat, kdv_orani, stok_takibi, mevcut_stok) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            if (executeQuery($sql, [$urunKodu, $urunAdi, $kategoriId, $birimFiyat, $kdvOrani, $stokTakibi, $mevcutStok])) {
                $success_message = 'Ürün başarıyla eklendi.';
            } else {
                $error_message = 'Ürün eklenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İstatistikleri hesapla
$stats = [];

// Bugünkü satışlar
$bugun = date('Y-m-d');
$bugun_satis = fetchOne("
    SELECT COUNT(*) as toplam, SUM(genel_toplam) as ciro 
    FROM pos_satis_islemleri 
    WHERE DATE(satis_tarihi) = ?
", [$bugun]);
$stats['bugun_satis'] = $bugun_satis['toplam'] ?? 0;
$stats['bugun_ciro'] = $bugun_satis['ciro'] ?? 0;

// Aktif terminaller
$aktif_terminal = fetchOne("SELECT COUNT(*) as toplam FROM pos_terminalleri WHERE durum = 'aktif'");
$stats['aktif_terminal'] = $aktif_terminal['toplam'] ?? 0;

// Açık oda hesapları
$acik_hesap = fetchOne("SELECT COUNT(*) as toplam FROM oda_hesaplari WHERE durum = 'acik'");
$stats['acik_hesap'] = $acik_hesap['toplam'] ?? 0;

// Toplam ürün sayısı
$toplam_urun = fetchOne("SELECT COUNT(*) as toplam FROM pos_menu_urunleri WHERE aktif = 1");
$stats['toplam_urun'] = $toplam_urun['toplam'] ?? 0;

// Son satışlar
$son_satislar = fetchAll("
    SELECT psi.*, pt.terminal_adi, k.ad_soyad as kullanici_adi, o.oda_numarasi
    FROM pos_satis_islemleri psi
    LEFT JOIN pos_terminalleri pt ON psi.terminal_id = pt.id
    LEFT JOIN kullanicilar k ON psi.kullanici_id = k.id
    LEFT JOIN oda_numaralari o ON psi.oda_id = o.id
    ORDER BY psi.satis_tarihi DESC
    LIMIT 10
");

// Terminaller
$terminaller = fetchAll("SELECT * FROM pos_terminalleri ORDER BY terminal_adi");

// Menü kategorileri
$kategoriler = fetchAll("SELECT * FROM pos_menu_kategorileri WHERE aktif = 1 ORDER BY sira_no");

// Menü ürünleri
$urunler = fetchAll("
    SELECT pmu.*, pmk.kategori_adi 
    FROM pos_menu_urunleri pmu
    LEFT JOIN pos_menu_kategorileri pmk ON pmu.kategori_id = pmk.id
    WHERE pmu.aktif = 1
    ORDER BY pmk.sira_no, pmu.urun_adi
    LIMIT 20
");

// Açık oda hesapları
$acik_hesaplar = fetchAll("
    SELECT oh.*, o.oda_numarasi, m.ad_soyad as musteri_adi
    FROM oda_hesaplari oh
    LEFT JOIN oda_numaralari o ON oh.oda_id = o.id
    LEFT JOIN musteriler m ON oh.musteri_id = m.id
    WHERE oh.durum = 'acik'
    ORDER BY oh.acilis_tarihi DESC
");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Dashboard - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        
        .table {
            margin-bottom: 0;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-aktif { background-color: #d4edda; color: #155724; }
        .status-pasif { background-color: #f8d7da; color: #721c24; }
        .status-bakimda { background-color: #fff3cd; color: #856404; }
        .status-arizali { background-color: #f5c6cb; color: #721c24; }
        
        .terminal-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .terminal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2 class="mb-0">
                        <i class="fas fa-cash-register text-primary"></i>
                        POS Dashboard
                    </h2>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#terminalModal">
                            <i class="fas fa-plus"></i> Terminal Ekle
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#urunModal">
                            <i class="fas fa-plus"></i> Ürün Ekle
                        </button>
                        <a href="pos-satis-raporu.php" class="btn btn-warning">
                            <i class="fas fa-chart-bar"></i> Satış Raporu
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- İstatistik Kartları -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['bugun_ciro'], 2); ?> ₺</div>
                            <div class="stat-label">Bugünkü Ciro</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card info fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['bugun_satis']; ?></div>
                            <div class="stat-label">Bugünkü Satış</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card warning fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['aktif_terminal']; ?></div>
                            <div class="stat-label">Aktif Terminal</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-desktop"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card danger fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['acik_hesap']; ?></div>
                            <div class="stat-label">Açık Oda Hesabı</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-bed"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Son Satışlar -->
            <div class="col-lg-8">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt"></i>
                            Son Satışlar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fatura No</th>
                                        <th>Terminal</th>
                                        <th>Kullanıcı</th>
                                        <th>Oda</th>
                                        <th>Tutar</th>
                                        <th>Tarih</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($son_satislar as $satis): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($satis['fatura_no']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($satis['terminal_adi'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($satis['kullanici_adi'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($satis['oda_numarasi']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($satis['oda_numarasi']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($satis['genel_toplam'], 2); ?> ₺</strong>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($satis['satis_tarihi'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $satis['durum'] === 'tamamlandi' ? 'aktif' : 'pasif'; ?>">
                                                <?php echo ucfirst($satis['durum']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Açık Oda Hesapları -->
            <div class="col-lg-4">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bed"></i>
                            Açık Oda Hesapları
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($acik_hesaplar)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-bed fa-2x mb-2"></i>
                            <p>Açık oda hesabı bulunmuyor</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($acik_hesaplar as $hesap): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Oda <?php echo htmlspecialchars($hesap['oda_numarasi']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($hesap['musteri_adi']); ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo number_format($hesap['kalan_tutar'], 2); ?> ₺</strong>
                                    <br>
                                    <small class="text-muted"><?php echo date('d.m H:i', strtotime($hesap['acilis_tarihi'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Terminaller -->
            <div class="col-lg-6">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-desktop"></i>
                            POS Terminalleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($terminaller as $terminal): ?>
                        <div class="terminal-card card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($terminal['terminal_adi']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($terminal['lokasyon']); ?>
                                            <br>
                                            <i class="fas fa-tag"></i> <?php echo ucfirst($terminal['terminal_turu']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo $terminal['durum']; ?>">
                                            <?php echo ucfirst($terminal['durum']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $terminal['son_aktivite'] ? date('d.m H:i', strtotime($terminal['son_aktivite'])) : 'Hiç'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Menü Ürünleri -->
            <div class="col-lg-6">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-utensils"></i>
                            Menü Ürünleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ürün</th>
                                        <th>Kategori</th>
                                        <th>Fiyat</th>
                                        <th>Stok</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urunler as $urun): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($urun['kategori_adi']); ?></span>
                                        </td>
                                        <td><?php echo number_format($urun['birim_fiyat'], 2); ?> ₺</td>
                                        <td>
                                            <?php if ($urun['stok_takibi']): ?>
                                                <span class="badge <?php echo $urun['mevcut_stok'] <= $urun['minimum_stok'] ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo number_format($urun['mevcut_stok'], 1); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terminal Ekleme Modal -->
    <div class="modal fade" id="terminalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Terminal Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="terminal_ekle">
                        <div class="mb-3">
                            <label class="form-label">Terminal Kodu</label>
                            <input type="text" class="form-control" name="terminal_kodu" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Terminal Adı</label>
                            <input type="text" class="form-control" name="terminal_adi" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Terminal Türü</label>
                            <select class="form-select" name="terminal_turu" required>
                                <option value="restaurant">Restaurant</option>
                                <option value="bar">Bar</option>
                                <option value="cafe">Cafe</option>
                                <option value="spa">Spa</option>
                                <option value="room_service">Oda Servisi</option>
                                <option value="retail">Retail</option>
                                <option value="diger">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lokasyon</label>
                            <input type="text" class="form-control" name="lokasyon" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">IP Adresi</label>
                            <input type="text" class="form-control" name="ip_adresi">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ürün Ekleme Modal -->
    <div class="modal fade" id="urunModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Ürün Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="urun_ekle">
                        <div class="mb-3">
                            <label class="form-label">Ürün Kodu</label>
                            <input type="text" class="form-control" name="urun_kodu" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ürün Adı</label>
                            <input type="text" class="form-control" name="urun_adi" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" name="kategori_id" required>
                                <?php foreach ($kategoriler as $kategori): ?>
                                <option value="<?php echo $kategori['id']; ?>"><?php echo htmlspecialchars($kategori['kategori_adi']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Birim Fiyat</label>
                            <input type="number" step="0.01" class="form-control" name="birim_fiyat" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">KDV Oranı (%)</label>
                            <input type="number" step="0.01" class="form-control" name="kdv_orani" value="18" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="stok_takibi" id="stok_takibi">
                                <label class="form-check-label" for="stok_takibi">
                                    Stok Takibi Yap
                                </label>
                            </div>
                        </div>
                        <div class="mb-3" id="stok_miktar" style="display: none;">
                            <label class="form-label">Mevcut Stok</label>
                            <input type="number" step="0.001" class="form-control" name="mevcut_stok">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Stok takibi checkbox kontrolü
        document.getElementById('stok_takibi').addEventListener('change', function() {
            const stokMiktar = document.getElementById('stok_miktar');
            if (this.checked) {
                stokMiktar.style.display = 'block';
            } else {
                stokMiktar.style.display = 'none';
            }
        });
    </script>
</body>
</html>

