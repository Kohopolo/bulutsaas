<?php
/**
 * POS Menü Yönetimi
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('pos_yonetimi')) { $_SESSION['error_message']='POS yönetimi yetkiniz bulunmamaktadır.'; header('Location: /error/403.php'); exit; }

$success_message = '';
$error_message = '';

// Kategori ekleme
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='kategori_ekle') {
    try {
        $kategori_adi = sanitizeString($_POST['kategori_adi']);
        $kategori_kodu = sanitizeString($_POST['kategori_kodu']);
        $sira_no = intval($_POST['sira_no'] ?? 0);
        $renk_kodu = sanitizeString($_POST['renk_kodu'] ?? '#007bff');
        $ikon = sanitizeString($_POST['ikon'] ?? null);
        $sql = "INSERT INTO pos_menu_kategorileri (kategori_adi, kategori_kodu, sira_no, renk_kodu, ikon) VALUES (?, ?, ?, ?, ?)";
        if (executeQuery($sql, [$kategori_adi, $kategori_kodu, $sira_no, $renk_kodu, $ikon])) {
            $success_message = 'Kategori eklendi.';
        } else { $error_message='Kategori eklenemedi.'; }
    } catch (Exception $e) { $error_message=$e->getMessage(); }
}

// Ürün ekleme
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='urun_ekle') {
    try {
        $urun_kodu = sanitizeString($_POST['urun_kodu']);
        $urun_adi = sanitizeString($_POST['urun_adi']);
        $kategori_id = intval($_POST['kategori_id']);
        $birim_fiyat = floatval($_POST['birim_fiyat']);
        $kdv_orani = floatval($_POST['kdv_orani'] ?? 18);
        $stok_takibi = isset($_POST['stok_takibi']) ? 1 : 0;
        $mevcut_stok = $stok_takibi ? floatval($_POST['mevcut_stok'] ?? 0) : null;
        $sql = "INSERT INTO pos_menu_urunleri (urun_kodu, urun_adi, kategori_id, birim_fiyat, kdv_orani, stok_takibi, mevcut_stok) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if (executeQuery($sql, [$urun_kodu, $urun_adi, $kategori_id, $birim_fiyat, $kdv_orani, $stok_takibi, $mevcut_stok])) {
            $success_message = 'Ürün eklendi.';
        } else { $error_message='Ürün eklenemedi.'; }
    } catch (Exception $e) { $error_message=$e->getMessage(); }
}

$kategoriler = fetchAll("SELECT * FROM pos_menu_kategorileri ORDER BY sira_no, kategori_adi");
$urunler = fetchAll("SELECT pmu.*, pmk.kategori_adi FROM pos_menu_urunleri pmu LEFT JOIN pos_menu_kategorileri pmk ON pmu.kategori_id = pmk.id ORDER BY pmk.sira_no, pmu.urun_adi");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Menü Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-utensils text-primary"></i> POS Menü Yönetimi</h4>
            <a href="pos-dashboard.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Geri</a>
        </div>

        <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>Kategori Ekle</strong></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="kategori_ekle">
                            <div class="mb-2">
                                <label class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control" name="kategori_adi" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Kategori Kodu</label>
                                <input type="text" class="form-control" name="kategori_kodu" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Sıra No</label>
                                <input type="number" class="form-control" name="sira_no" value="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Renk Kodu</label>
                                <input type="text" class="form-control" name="renk_kodu" value="#007bff">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">İkon</label>
                                <input type="text" class="form-control" name="ikon" placeholder="fas fa-utensils">
                            </div>
                            <div class="text-end">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><strong>Ürün Ekle</strong></div>
                    <div class="card-body">
                        <form method="POST" class="row g-2">
                            <input type="hidden" name="action" value="urun_ekle">
                            <div class="col-md-3">
                                <label class="form-label">Ürün Kodu</label>
                                <input type="text" class="form-control" name="urun_kodu" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ürün Adı</label>
                                <input type="text" class="form-control" name="urun_adi" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="kategori_id" required>
                                    <?php foreach ($kategoriler as $k): ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['kategori_adi']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fiyat</label>
                                <input type="number" step="0.01" class="form-control" name="birim_fiyat" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KDV (%)</label>
                                <input type="number" step="0.01" class="form-control" name="kdv_orani" value="18">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="stok_takibi" name="stok_takibi">
                                    <label class="form-check-label" for="stok_takibi">Stok</label>
                                </div>
                            </div>
                            <div class="col-md-2" id="stok_miktar" style="display:none;">
                                <label class="form-label">Stok</label>
                                <input type="number" step="0.001" class="form-control" name="mevcut_stok">
                            </div>
                            <div class="col-md-12 text-end">
                                <button class="btn btn-success" type="submit"><i class="fas fa-plus"></i> Ürün Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Ürün Listesi</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kodu</th>
                                        <th>Ürün</th>
                                        <th>Kategori</th>
                                        <th>Fiyat</th>
                                        <th>Stok</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urunler as $u): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($u['urun_kodu']); ?></td>
                                        <td><?php echo htmlspecialchars($u['urun_adi']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['kategori_adi']); ?></span></td>
                                        <td><?php echo number_format($u['birim_fiyat'],2); ?> ₺</td>
                                        <td>
                                            <?php if ($u['stok_takibi']): ?>
                                                <span class="badge <?php echo ($u['mevcut_stok'] <= $u['minimum_stok'])?'bg-danger':'bg-success'; ?>"><?php echo number_format($u['mevcut_stok'],1); ?></span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const cb = document.getElementById('stok_takibi');
        if (cb) {
            cb.addEventListener('change', () => {
                document.getElementById('stok_miktar').style.display = cb.checked ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>



