<?php
/**
 * Stok Ürün Listesi
 * Stok ürünlerini listeler ve yönetir
 */

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
if (!hasDetailedPermission('stok_yonetimi')) {
    $_SESSION['error_message'] = 'Stok yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Filtreler
$kategori_id = $_GET['kategori_id'] ?? '';
$durum = $_GET['durum'] ?? '';
$arama = $_GET['arama'] ?? '';

// Stok kategorileri
$kategoriler = fetchAll("
    SELECT * FROM stok_kategorileri 
    WHERE durum = 'aktif' 
    ORDER BY sira_no, kategori_adi
");

// Stok ürünleri
$whereConditions = [];
$params = [];

if ($kategori_id) {
    $whereConditions[] = "su.kategori_id = ?";
    $params[] = $kategori_id;
}

if ($durum) {
    $whereConditions[] = "su.durum = ?";
    $params[] = $durum;
}

if ($arama) {
    $whereConditions[] = "(su.urun_adi LIKE ? OR su.urun_kodu LIKE ?)";
    $params[] = "%{$arama}%";
    $params[] = "%{$arama}%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

$urunler = fetchAll("
    SELECT 
        su.*,
        sk.kategori_adi,
        t.tedarikci_adi,
        CASE 
            WHEN su.mevcut_stok <= 0 THEN 'Stok Yok'
            WHEN su.mevcut_stok <= su.minimum_stok THEN 'Minimum Stok'
            WHEN su.mevcut_stok >= su.maksimum_stok THEN 'Maksimum Stok'
            ELSE 'Normal'
        END as stok_durumu
    FROM stok_urunleri su
    LEFT JOIN stok_kategorileri sk ON su.kategori_id = sk.id
    LEFT JOIN tedarikciler t ON su.tedarikci_id = t.id
    {$whereClause}
    ORDER BY su.urun_adi
", $params);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Ürün Listesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stock-status {
            font-weight: bold;
        }
        .stock-none {
            color: #dc3545;
        }
        .stock-low {
            color: #fd7e14;
        }
        .stock-high {
            color: #28a745;
        }
        .stock-normal {
            color: #6c757d;
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
                    <h1 class="h2">Stok Ürün Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="stok-urun-ekle.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ürün Ekle
                            </a>
                            <a href="stok-yonetimi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Geri
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="kategori_id" class="form-label">Kategori</label>
                                <select class="form-select" id="kategori_id" name="kategori_id">
                                    <option value="">Tüm Kategoriler</option>
                                    <?php foreach ($kategoriler as $kategori): ?>
                                    <option value="<?php echo $kategori['id']; ?>" <?php echo $kategori_id == $kategori['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kategori['kategori_adi']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="aktif" <?php echo $durum == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="pasif" <?php echo $durum == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                    <option value="stok_yok" <?php echo $durum == 'stok_yok' ? 'selected' : ''; ?>>Stok Yok</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="arama" class="form-label">Arama</label>
                                <input type="text" class="form-control" id="arama" name="arama" 
                                       value="<?php echo htmlspecialchars($arama); ?>" 
                                       placeholder="Ürün adı veya kodu...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Ürün Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-boxes me-2"></i>Stok Ürünleri (<?php echo count($urunler); ?> ürün)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($urunler)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Filtre kriterlerinize uygun ürün bulunamadı.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ürün Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th>Kategori</th>
                                        <th>Mevcut Stok</th>
                                        <th>Minimum Stok</th>
                                        <th>Birim Fiyat</th>
                                        <th>Toplam Değer</th>
                                        <th>Stok Durumu</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urunler as $urun): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo htmlspecialchars($urun['urun_kodu']); ?></code>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong>
                                            <?php if ($urun['aciklama']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($urun['aciklama'], 0, 50)); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($urun['kategori_adi'] ?? 'Belirtilmemiş'); ?></td>
                                        <td>
                                            <strong><?php echo number_format($urun['mevcut_stok'], 2); ?></strong>
                                            <small class="text-muted"><?php echo $urun['birim']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo number_format($urun['minimum_stok'], 2); ?>
                                            <small class="text-muted"><?php echo $urun['birim']; ?></small>
                                        </td>
                                        <td><?php echo number_format($urun['birim_fiyat'], 2); ?>₺</td>
                                        <td>
                                            <strong><?php echo number_format($urun['mevcut_stok'] * $urun['birim_fiyat'], 2); ?>₺</strong>
                                        </td>
                                        <td>
                                            <span class="stock-status stock-<?php echo strtolower(str_replace(' ', '-', $urun['stok_durumu'])); ?>">
                                                <?php echo $urun['stok_durumu']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $urun['durum'] == 'aktif' ? 'success' : ($urun['durum'] == 'pasif' ? 'secondary' : 'danger'); ?>">
                                                <?php echo ucfirst($urun['durum']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="stok-urun-duzenle.php?id=<?php echo $urun['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="stok-hareket-ekle.php?urun_id=<?php echo $urun['id']; ?>" 
                                                   class="btn btn-outline-success" title="Stok Hareketi">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                                <a href="stok-urun-detay.php?id=<?php echo $urun['id']; ?>" 
                                                   class="btn btn-outline-info" title="Detay">
                                                    <i class="fas fa-eye"></i>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

