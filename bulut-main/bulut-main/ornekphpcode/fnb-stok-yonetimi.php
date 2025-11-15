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
if (!hasDetailedPermission('fnb_stok_yonetimi')) {
    $_SESSION['error_message'] = 'F&B stok yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'stok_ekle') {
            $urun_adi = sanitizeString($_POST['urun_adi']);
            $kategori = sanitizeString($_POST['kategori']);
            $birim = sanitizeString($_POST['birim']);
            $mevcut_miktar = floatval($_POST['mevcut_miktar']);
            $minimum_miktar = floatval($_POST['minimum_miktar']);
            $maksimum_miktar = floatval($_POST['maksimum_miktar']);
            $birim_fiyat = floatval($_POST['birim_fiyat']);
            $departman = sanitizeString($_POST['departman']);
            $tedarikci = sanitizeString($_POST['tedarikci']);
            
            $sql = "INSERT INTO fnb_stok (urun_adi, kategori, birim, mevcut_miktar, minimum_miktar, maksimum_miktar, birim_fiyat, departman, tedarikci) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if (executeQuery($sql, [$urun_adi, $kategori, $birim, $mevcut_miktar, $minimum_miktar, $maksimum_miktar, $birim_fiyat, $departman, $tedarikci])) {
                $success_message = 'Stok ürünü başarıyla eklendi.';
            } else {
                $error_message = 'Stok ürünü eklenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Stok ürünlerini getir
$stok_urunleri = fetchAll("
    SELECT * FROM fnb_stok
    WHERE aktif = 1
    ORDER BY departman, kategori, urun_adi
");

// Stok uyarıları
$stok_uyarilari = fetchAll("
    SELECT * FROM fnb_stok
    WHERE aktif = 1 AND mevcut_miktar <= minimum_miktar
    ORDER BY (mevcut_miktar / minimum_miktar) ASC
");

// Departmanlar
$departmanlar = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane',
    'genel' => 'Genel'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Stok Yönetimi - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-boxes me-2"></i>F&B Stok Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stok Uyarıları -->
                <?php if (!empty($stok_uyarilari)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Stok Uyarıları</h6>
                    <p class="mb-0">Aşağıdaki ürünlerin stokları minimum seviyenin altında:</p>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($stok_uyarilari as $uyari): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($uyari['urun_adi']); ?></strong> - 
                            Mevcut: <?php echo $uyari['mevcut_miktar']; ?> <?php echo $uyari['birim']; ?> / 
                            Minimum: <?php echo $uyari['minimum_miktar']; ?> <?php echo $uyari['birim']; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Stok Ekleme -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>Stok Ürünü Ekle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenInput(); ?>
                                    <input type="hidden" name="action" value="stok_ekle">
                                    
                                    <div class="mb-3">
                                        <label for="urun_adi" class="form-label">Ürün Adı</label>
                                        <input type="text" class="form-control" id="urun_adi" name="urun_adi" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="kategori" class="form-label">Kategori</label>
                                            <input type="text" class="form-control" id="kategori" name="kategori">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="birim" class="form-label">Birim</label>
                                            <select class="form-select" id="birim" name="birim" required>
                                                <option value="">Birim Seçin</option>
                                                <option value="kg">Kilogram (kg)</option>
                                                <option value="gr">Gram (gr)</option>
                                                <option value="lt">Litre (lt)</option>
                                                <option value="ml">Mililitre (ml)</option>
                                                <option value="adet">Adet</option>
                                                <option value="paket">Paket</option>
                                                <option value="kutu">Kutu</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="mevcut_miktar" class="form-label">Mevcut Miktar</label>
                                            <input type="number" class="form-control" id="mevcut_miktar" name="mevcut_miktar" step="0.001" min="0" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="minimum_miktar" class="form-label">Minimum Miktar</label>
                                            <input type="number" class="form-control" id="minimum_miktar" name="minimum_miktar" step="0.001" min="0" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="maksimum_miktar" class="form-label">Maksimum Miktar</label>
                                            <input type="number" class="form-control" id="maksimum_miktar" name="maksimum_miktar" step="0.001" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="birim_fiyat" class="form-label">Birim Fiyat (₺)</label>
                                        <input type="number" class="form-control" id="birim_fiyat" name="birim_fiyat" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="departman" class="form-label">Departman</label>
                                            <select class="form-select" id="departman" name="departman" required>
                                                <option value="">Departman Seçin</option>
                                                <?php foreach ($departmanlar as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="tedarikci" class="form-label">Tedarikci</label>
                                            <input type="text" class="form-control" id="tedarikci" name="tedarikci">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Stok Ürünü Ekle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Stok İstatistikleri
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-primary"><?php echo count($stok_urunleri); ?></h4>
                                            <small class="text-muted">Toplam Ürün</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-warning"><?php echo count($stok_uyarilari); ?></h4>
                                            <small class="text-muted">Stok Uyarısı</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h6 class="text-primary">Departman Bazında:</h6>
                                <?php
                                $departman_istatistikleri = [];
                                foreach ($stok_urunleri as $urun) {
                                    $departman_istatistikleri[$urun['departman']] = ($departman_istatistikleri[$urun['departman']] ?? 0) + 1;
                                }
                                ?>
                                <?php foreach ($departman_istatistikleri as $departman => $sayi): ?>
                                <div class="d-flex justify-content-between">
                                    <span><?php echo $departmanlar[$departman]; ?></span>
                                    <span class="badge bg-primary"><?php echo $sayi; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stok Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Stok Ürünleri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Ürün Adı</th>
                                        <th>Kategori</th>
                                        <th>Departman</th>
                                        <th>Mevcut Miktar</th>
                                        <th>Minimum</th>
                                        <th>Maksimum</th>
                                        <th>Birim Fiyat</th>
                                        <th>Tedarikci</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stok_urunleri as $urun): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($urun['kategori']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $departmanlar[$urun['departman']] == 'Mutfak' ? 'danger' : ($departmanlar[$urun['departman']] == 'Restoran' ? 'success' : ($departmanlar[$urun['departman']] == 'Bar' ? 'info' : ($departmanlar[$urun['departman']] == 'Pastane' ? 'warning' : 'secondary'))); ?>">
                                                <?php echo $departmanlar[$urun['departman']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $urun['mevcut_miktar']; ?> <?php echo $urun['birim']; ?></strong>
                                        </td>
                                        <td><?php echo $urun['minimum_miktar']; ?> <?php echo $urun['birim']; ?></td>
                                        <td><?php echo $urun['maksimum_miktar']; ?> <?php echo $urun['birim']; ?></td>
                                        <td><?php echo number_format($urun['birim_fiyat'], 2); ?>₺</td>
                                        <td><?php echo htmlspecialchars($urun['tedarikci']); ?></td>
                                        <td>
                                            <?php if ($urun['mevcut_miktar'] <= $urun['minimum_miktar']): ?>
                                                <span class="badge bg-danger">Kritik</span>
                                            <?php elseif ($urun['mevcut_miktar'] <= ($urun['minimum_miktar'] * 1.5)): ?>
                                                <span class="badge bg-warning">Düşük</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Giriş">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Çıkış">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
