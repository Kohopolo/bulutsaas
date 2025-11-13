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
if (!hasDetailedPermission('teknik_servis_ekipman_yonetimi')) {
    $_SESSION['error_message'] = 'Teknik servis ekipman yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'ekipman_ekle') {
            $ekipman_adi = sanitizeString($_POST['ekipman_adi']);
            $ekipman_turu = sanitizeString($_POST['ekipman_turu']);
            $marka = sanitizeString($_POST['marka']);
            $model = sanitizeString($_POST['model']);
            $seri_no = sanitizeString($_POST['seri_no']);
            $lokasyon = sanitizeString($_POST['lokasyon']);
            $durum = sanitizeString($_POST['durum']);
            $son_bakim_tarihi = !empty($_POST['son_bakim_tarihi']) ? $_POST['son_bakim_tarihi'] : null;
            $sonraki_bakim_tarihi = !empty($_POST['sonraki_bakim_tarihi']) ? $_POST['sonraki_bakim_tarihi'] : null;
            $garanti_bitis_tarihi = !empty($_POST['garanti_bitis_tarihi']) ? $_POST['garanti_bitis_tarihi'] : null;
            $tedarikci = sanitizeString($_POST['tedarikci']);
            $maliyet = floatval($_POST['maliyet']);
            $aciklama = sanitizeString($_POST['aciklama']);
            
            $sql = "INSERT INTO teknik_servis_ekipmanlari (ekipman_adi, ekipman_turu, marka, model, seri_no, lokasyon, durum, son_bakim_tarihi, sonraki_bakim_tarihi, garanti_bitis_tarihi, tedarikci, maliyet, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if (executeQuery($sql, [$ekipman_adi, $ekipman_turu, $marka, $model, $seri_no, $lokasyon, $durum, $son_bakim_tarihi, $sonraki_bakim_tarihi, $garanti_bitis_tarihi, $tedarikci, $maliyet, $aciklama])) {
                $success_message = 'Ekipman başarıyla eklendi.';
            } else {
                $error_message = 'Ekipman eklenirken hata oluştu.';
            }
        }
        
        if ($action == 'ekipman_guncelle') {
            $ekipman_id = intval($_POST['ekipman_id']);
            $ekipman_adi = sanitizeString($_POST['ekipman_adi']);
            $ekipman_turu = sanitizeString($_POST['ekipman_turu']);
            $marka = sanitizeString($_POST['marka']);
            $model = sanitizeString($_POST['model']);
            $seri_no = sanitizeString($_POST['seri_no']);
            $lokasyon = sanitizeString($_POST['lokasyon']);
            $durum = sanitizeString($_POST['durum']);
            $son_bakim_tarihi = !empty($_POST['son_bakim_tarihi']) ? $_POST['son_bakim_tarihi'] : null;
            $sonraki_bakim_tarihi = !empty($_POST['sonraki_bakim_tarihi']) ? $_POST['sonraki_bakim_tarihi'] : null;
            $garanti_bitis_tarihi = !empty($_POST['garanti_bitis_tarihi']) ? $_POST['garanti_bitis_tarihi'] : null;
            $tedarikci = sanitizeString($_POST['tedarikci']);
            $maliyet = floatval($_POST['maliyet']);
            $aciklama = sanitizeString($_POST['aciklama']);
            
            $sql = "UPDATE teknik_servis_ekipmanlari SET ekipman_adi = ?, ekipman_turu = ?, marka = ?, model = ?, seri_no = ?, lokasyon = ?, durum = ?, son_bakim_tarihi = ?, sonraki_bakim_tarihi = ?, garanti_bitis_tarihi = ?, tedarikci = ?, maliyet = ?, aciklama = ?, guncelleme_tarihi = NOW() WHERE id = ?";
            
            if (executeQuery($sql, [$ekipman_adi, $ekipman_turu, $marka, $model, $seri_no, $lokasyon, $durum, $son_bakim_tarihi, $sonraki_bakim_tarihi, $garanti_bitis_tarihi, $tedarikci, $maliyet, $aciklama, $ekipman_id])) {
                $success_message = 'Ekipman başarıyla güncellendi.';
            } else {
                $error_message = 'Ekipman güncellenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ekipmanları getir
$ekipmanlar = fetchAll("
    SELECT * FROM teknik_servis_ekipmanlari
    ORDER BY ekipman_adi
");

// Ekipman türleri
$ekipman_turleri = [
    'elektrik' => 'Elektrik',
    'su' => 'Su Tesisatı',
    'klima' => 'Klima',
    'internet' => 'İnternet',
    'tv' => 'TV',
    'telefon' => 'Telefon',
    'asansor' => 'Asansör',
    'güvenlik' => 'Güvenlik',
    'yangin' => 'Yangın',
    'diger' => 'Diğer'
];

// Ekipman durumları
$ekipman_durumlari = [
    'calisiyor' => 'Çalışıyor',
    'arizali' => 'Arızalı',
    'bakimda' => 'Bakımda',
    'degistirildi' => 'Değiştirildi',
    'hurda' => 'Hurda'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Ekipman Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Teknik Servis Ekipman Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ekipmanEkleModal">
                            <i class="fas fa-plus"></i> Ekipman Ekle
                        </button>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Ekipman Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools"></i> Ekipman Listesi
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ekipmanlar)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz ekipman bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ekipman Adı</th>
                                            <th>Tür</th>
                                            <th>Marka/Model</th>
                                            <th>Seri No</th>
                                            <th>Lokasyon</th>
                                            <th>Durum</th>
                                            <th>Son Bakım</th>
                                            <th>Maliyet</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ekipmanlar as $ekipman): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ekipman['ekipman_adi']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($ekipman_turleri[$ekipman['ekipman_turu']] ?? $ekipman['ekipman_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($ekipman['marka'] . ' ' . $ekipman['model']); ?></td>
                                                <td><?php echo htmlspecialchars($ekipman['seri_no']); ?></td>
                                                <td><?php echo htmlspecialchars($ekipman['lokasyon']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $ekipman['durum'] == 'calisiyor' ? 'success' : 
                                                            ($ekipman['durum'] == 'arizali' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($ekipman_durumlari[$ekipman['durum']] ?? $ekipman['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($ekipman['son_bakim_tarihi']): ?>
                                                        <?php echo date('d.m.Y', strtotime($ekipman['son_bakim_tarihi'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($ekipman['maliyet'], 2); ?>₺</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editEkipman(<?php echo htmlspecialchars(json_encode($ekipman)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
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

    <!-- Ekipman Ekleme Modal -->
    <div class="modal fade" id="ekipmanEkleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ekipman Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ekipman_ekle">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ekipman Adı <span class="text-danger">*</span></label>
                                <input type="text" name="ekipman_adi" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ekipman Türü <span class="text-danger">*</span></label>
                                <select name="ekipman_turu" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($ekipman_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Marka</label>
                                <input type="text" name="marka" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Seri No</label>
                                <input type="text" name="seri_no" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lokasyon</label>
                                <input type="text" name="lokasyon" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Durum</label>
                                <select name="durum" class="form-select">
                                    <?php foreach ($ekipman_durumlari as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $key == 'calisiyor' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maliyet</label>
                                <input type="number" name="maliyet" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Son Bakım Tarihi</label>
                                <input type="date" name="son_bakim_tarihi" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sonraki Bakım Tarihi</label>
                                <input type="date" name="sonraki_bakim_tarihi" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Garanti Bitiş Tarihi</label>
                                <input type="date" name="garanti_bitis_tarihi" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tedarikçi</label>
                            <input type="text" name="tedarikci" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="aciklama" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekipman Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ekipman Düzenleme Modal -->
    <div class="modal fade" id="ekipmanDuzenleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ekipman Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ekipman_guncelle">
                        <input type="hidden" name="ekipman_id" id="edit_ekipman_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ekipman Adı <span class="text-danger">*</span></label>
                                <input type="text" name="ekipman_adi" id="edit_ekipman_adi" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ekipman Türü <span class="text-danger">*</span></label>
                                <select name="ekipman_turu" id="edit_ekipman_turu" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($ekipman_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Marka</label>
                                <input type="text" name="marka" id="edit_marka" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" id="edit_model" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Seri No</label>
                                <input type="text" name="seri_no" id="edit_seri_no" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lokasyon</label>
                                <input type="text" name="lokasyon" id="edit_lokasyon" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Durum</label>
                                <select name="durum" id="edit_durum" class="form-select">
                                    <?php foreach ($ekipman_durumlari as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maliyet</label>
                                <input type="number" name="maliyet" id="edit_maliyet" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Son Bakım Tarihi</label>
                                <input type="date" name="son_bakim_tarihi" id="edit_son_bakim_tarihi" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sonraki Bakım Tarihi</label>
                                <input type="date" name="sonraki_bakim_tarihi" id="edit_sonraki_bakim_tarihi" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Garanti Bitiş Tarihi</label>
                                <input type="date" name="garanti_bitis_tarihi" id="edit_garanti_bitis_tarihi" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tedarikçi</label>
                            <input type="text" name="tedarikci" id="edit_tedarikci" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="aciklama" id="edit_aciklama" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEkipman(ekipman) {
            document.getElementById('edit_ekipman_id').value = ekipman.id;
            document.getElementById('edit_ekipman_adi').value = ekipman.ekipman_adi;
            document.getElementById('edit_ekipman_turu').value = ekipman.ekipman_turu;
            document.getElementById('edit_marka').value = ekipman.marka || '';
            document.getElementById('edit_model').value = ekipman.model || '';
            document.getElementById('edit_seri_no').value = ekipman.seri_no || '';
            document.getElementById('edit_lokasyon').value = ekipman.lokasyon || '';
            document.getElementById('edit_durum').value = ekipman.durum;
            document.getElementById('edit_maliyet').value = ekipman.maliyet || '';
            document.getElementById('edit_son_bakim_tarihi').value = ekipman.son_bakim_tarihi || '';
            document.getElementById('edit_sonraki_bakim_tarihi').value = ekipman.sonraki_bakim_tarihi || '';
            document.getElementById('edit_garanti_bitis_tarihi').value = ekipman.garanti_bitis_tarihi || '';
            document.getElementById('edit_tedarikci').value = ekipman.tedarikci || '';
            document.getElementById('edit_aciklama').value = ekipman.aciklama || '';
            
            new bootstrap.Modal(document.getElementById('ekipmanDuzenleModal')).show();
        }
    </script>
</body>
</html>
