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
if (!hasDetailedPermission('fnb_menu_yonetimi')) {
    $_SESSION['error_message'] = 'F&B menü yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'kategori_ekle') {
            $kategori_adi = sanitizeString($_POST['kategori_adi']);
            $kategori_aciklamasi = sanitizeString($_POST['kategori_aciklamasi']);
            $departman = sanitizeString($_POST['departman']);
            $sira_no = intval($_POST['sira_no']);
            
            $sql = "INSERT INTO menu_kategorileri (kategori_adi, kategori_aciklamasi, departman, sira_no) VALUES (?, ?, ?, ?)";
            if (executeQuery($sql, [$kategori_adi, $kategori_aciklamasi, $departman, $sira_no])) {
                $success_message = 'Kategori başarıyla eklendi.';
            } else {
                $error_message = 'Kategori eklenirken hata oluştu.';
            }
        } elseif ($action == 'urun_ekle') {
            $kategori_id = intval($_POST['kategori_id']);
            $urun_adi = sanitizeString($_POST['urun_adi']);
            $urun_aciklamasi = sanitizeString($_POST['urun_aciklamasi']);
            $fiyat = floatval($_POST['fiyat']);
            $maliyet = floatval($_POST['maliyet']);
            $hazirlama_suresi = intval($_POST['hazirlama_suresi']);
            $vegan = isset($_POST['vegan']) ? 1 : 0;
            $vejetaryen = isset($_POST['vejetaryen']) ? 1 : 0;
            $glutensiz = isset($_POST['glutensiz']) ? 1 : 0;
            
            $sql = "INSERT INTO menu_ogeleri (kategori_id, urun_adi, urun_aciklamasi, fiyat, maliyet, hazirlama_suresi, vegan, vejetaryen, glutensiz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if (executeQuery($sql, [$kategori_id, $urun_adi, $urun_aciklamasi, $fiyat, $maliyet, $hazirlama_suresi, $vegan, $vejetaryen, $glutensiz])) {
                $success_message = 'Ürün başarıyla eklendi.';
            } else {
                $error_message = 'Ürün eklenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Kategorileri getir
$kategoriler = fetchAll("
    SELECT * FROM menu_kategorileri
    WHERE aktif = 1
    ORDER BY departman, sira_no
");

// Menü öğelerini getir
$menu_ogeleri = fetchAll("
    SELECT mo.*, mk.kategori_adi, mk.departman
    FROM menu_ogeleri mo
    LEFT JOIN menu_kategorileri mk ON mo.kategori_id = mk.id
    WHERE mo.aktif = 1
    ORDER BY mk.departman, mk.sira_no, mo.urun_adi
");

// Departmanlar
$departmanlar = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Menü Yönetimi - Otel Yönetim Sistemi</title>
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
                    <h1 class="h2"><i class="fas fa-utensils me-2"></i>F&B Menü Yönetimi</h1>
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

                <!-- Kategori Ekleme -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>Kategori Ekle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenInput(); ?>
                                    <input type="hidden" name="action" value="kategori_ekle">
                                    
                                    <div class="mb-3">
                                        <label for="kategori_adi" class="form-label">Kategori Adı</label>
                                        <input type="text" class="form-control" id="kategori_adi" name="kategori_adi" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="kategori_aciklamasi" class="form-label">Açıklama</label>
                                        <textarea class="form-control" id="kategori_aciklamasi" name="kategori_aciklamasi" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="departman" class="form-label">Departman</label>
                                        <select class="form-select" id="departman" name="departman" required>
                                            <option value="">Departman Seçin</option>
                                            <?php foreach ($departmanlar as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sira_no" class="form-label">Sıra No</label>
                                        <input type="number" class="form-control" id="sira_no" name="sira_no" value="0">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kategori Ekle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus me-2"></i>Ürün Ekle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenInput(); ?>
                                    <input type="hidden" name="action" value="urun_ekle">
                                    
                                    <div class="mb-3">
                                        <label for="kategori_id" class="form-label">Kategori</label>
                                        <select class="form-select" id="kategori_id" name="kategori_id" required>
                                            <option value="">Kategori Seçin</option>
                                            <?php foreach ($kategoriler as $kategori): ?>
                                            <option value="<?php echo $kategori['id']; ?>">
                                                <?php echo htmlspecialchars($kategori['kategori_adi'] . ' (' . $departmanlar[$kategori['departman']] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="urun_adi" class="form-label">Ürün Adı</label>
                                        <input type="text" class="form-control" id="urun_adi" name="urun_adi" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="urun_aciklamasi" class="form-label">Açıklama</label>
                                        <textarea class="form-control" id="urun_aciklamasi" name="urun_aciklamasi" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fiyat" class="form-label">Fiyat (₺)</label>
                                            <input type="number" class="form-control" id="fiyat" name="fiyat" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="maliyet" class="form-label">Maliyet (₺)</label>
                                            <input type="number" class="form-control" id="maliyet" name="maliyet" step="0.01" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="hazirlama_suresi" class="form-label">Hazırlama Süresi (Dakika)</label>
                                        <input type="number" class="form-control" id="hazirlama_suresi" name="hazirlama_suresi" min="0" value="0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="vegan" name="vegan">
                                            <label class="form-check-label" for="vegan">Vegan</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="vejetaryen" name="vejetaryen">
                                            <label class="form-check-label" for="vejetaryen">Vejetaryen</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="glutensiz" name="glutensiz">
                                            <label class="form-check-label" for="glutensiz">Glutensiz</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Ürün Ekle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menü Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Menü Öğeleri
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
                                        <th>Fiyat</th>
                                        <th>Maliyet</th>
                                        <th>Hazırlama Süresi</th>
                                        <th>Özellikler</th>
                                        <th>Stok Durumu</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menu_ogeleri as $urun): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong>
                                            <?php if ($urun['urun_aciklamasi']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($urun['urun_aciklamasi']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($urun['kategori_adi']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $departmanlar[$urun['departman']] == 'Mutfak' ? 'danger' : ($departmanlar[$urun['departman']] == 'Restoran' ? 'success' : ($departmanlar[$urun['departman']] == 'Bar' ? 'info' : 'warning')); ?>">
                                                <?php echo $departmanlar[$urun['departman']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($urun['fiyat'], 2); ?>₺</strong>
                                        </td>
                                        <td><?php echo number_format($urun['maliyet'], 2); ?>₺</td>
                                        <td><?php echo $urun['hazirlama_suresi']; ?> dk</td>
                                        <td>
                                            <?php if ($urun['vegan']): ?><span class="badge bg-success me-1">Vegan</span><?php endif; ?>
                                            <?php if ($urun['vejetaryen']): ?><span class="badge bg-info me-1">Vejetaryen</span><?php endif; ?>
                                            <?php if ($urun['glutensiz']): ?><span class="badge bg-warning me-1">Glutensiz</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $urun['stok_durumu'] == 'stokta_var' ? 'success' : ($urun['stok_durumu'] == 'stokta_yok' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $urun['stok_durumu'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Sil">
                                                    <i class="fas fa-trash"></i>
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
