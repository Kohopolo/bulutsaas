<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'csrf_protection.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kampanya_fiyatlari_yonetimi', 'Kampanya fiyatları yönetimi yetkiniz bulunmamaktadır.');

// CSRF token oluştur
$csrf_token = generateCSRFToken();

// Başarı/hata mesajları
$success_message = '';
$error_message = '';

// Kampanya ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $oda_tipi_id = (int)$_POST['oda_tipi_id'];
        $kampanya_adi = sanitizeString($_POST['kampanya_adi']);
        $baslangic_tarihi = sanitizeString($_POST['baslangic_tarihi']);
        $bitis_tarihi = sanitizeString($_POST['bitis_tarihi']);
        $indirim_orani = !empty($_POST['indirim_orani']) ? (float)$_POST['indirim_orani'] : null;
        $sabit_fiyat = !empty($_POST['sabit_fiyat']) ? (float)$_POST['sabit_fiyat'] : null;
        $minimum_gece = (int)$_POST['minimum_gece'];
        $maksimum_gece = !empty($_POST['maksimum_gece']) ? (int)$_POST['maksimum_gece'] : null;
        $kampanya_kodu = isset($_POST['kampanya_kodu']) ? sanitizeString($_POST['kampanya_kodu']) : '';
        $aciklama = sanitizeString($_POST['aciklama']);
        $durum = sanitizeString($_POST['durum']);

        if (empty($kampanya_adi) || empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            $error_message = 'Kampanya adı, başlangıç ve bitiş tarihi zorunludur.';
        } elseif (empty($indirim_orani) && empty($sabit_fiyat)) {
            $error_message = 'İndirim oranı veya sabit fiyat belirtilmelidir.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO kampanya_fiyatlari (oda_tipi_id, kampanya_adi, baslangic_tarihi, bitis_tarihi, indirim_tipi, indirim_miktari, min_gece_sayisi, max_kullanim_sayisi, aciklama, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // İndirim tipini belirle
            $indirim_tipi = !empty($indirim_orani) ? 'yuzde' : 'sabit_tutar';
            $indirim_miktari = !empty($indirim_orani) ? $indirim_orani : $sabit_fiyat;
            $aktif_durum = ($durum == 'aktif') ? 1 : 0;
            
            if ($stmt->execute([$oda_tipi_id, $kampanya_adi, $baslangic_tarihi, $bitis_tarihi, $indirim_tipi, $indirim_miktari, $minimum_gece, $maksimum_gece, $aciklama, $aktif_durum])) {
                $success_message = 'Kampanya başarıyla eklendi.';
                logSecurityEvent(isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0, 'kampanya_eklendi', "Kampanya eklendi: $kampanya_adi");
            } else {
                $error_message = 'Kampanya eklenirken bir hata oluştu.';
            }
        }
    }
}

// Kampanya silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM kampanya_fiyatlari WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success_message = 'Kampanya başarıyla silindi.';
            logSecurityEvent(isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0, 'kampanya_silindi', "Kampanya silindi: ID $id");
        } else {
            $error_message = 'Kampanya silinirken bir hata oluştu.';
        }
    }
}

// Kampanya güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $id = (int)$_POST['id'];
        $oda_tipi_id = (int)$_POST['oda_tipi_id'];
        $kampanya_adi = sanitizeString($_POST['kampanya_adi']);
        $baslangic_tarihi = sanitizeString($_POST['baslangic_tarihi']);
        $bitis_tarihi = sanitizeString($_POST['bitis_tarihi']);
        $indirim_orani = !empty($_POST['indirim_orani']) ? (float)$_POST['indirim_orani'] : null;
        $sabit_fiyat = !empty($_POST['sabit_fiyat']) ? (float)$_POST['sabit_fiyat'] : null;
        $minimum_gece = (int)$_POST['minimum_gece'];
        $maksimum_gece = !empty($_POST['maksimum_gece']) ? (int)$_POST['maksimum_gece'] : null;
        $kampanya_kodu = isset($_POST['kampanya_kodu']) ? sanitizeString($_POST['kampanya_kodu']) : '';
        $aciklama = sanitizeString($_POST['aciklama']);
        $durum = sanitizeString($_POST['durum']);

        if (empty($kampanya_adi) || empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            $error_message = 'Kampanya adı, başlangıç ve bitiş tarihi zorunludur.';
        } elseif (empty($indirim_orani) && empty($sabit_fiyat)) {
            $error_message = 'İndirim oranı veya sabit fiyat belirtilmelidir.';
        } else {
            $stmt = $pdo->prepare("UPDATE kampanya_fiyatlari SET oda_tipi_id = ?, kampanya_adi = ?, baslangic_tarihi = ?, bitis_tarihi = ?, indirim_tipi = ?, indirim_miktari = ?, min_gece_sayisi = ?, max_kullanim_sayisi = ?, aciklama = ?, aktif = ? WHERE id = ?");
            
            // İndirim tipini belirle
            $indirim_tipi = !empty($indirim_orani) ? 'yuzde' : 'sabit_tutar';
            $indirim_miktari = !empty($indirim_orani) ? $indirim_orani : $sabit_fiyat;
            $aktif_durum = ($durum == 'aktif') ? 1 : 0;
            
            if ($stmt->execute([$oda_tipi_id, $kampanya_adi, $baslangic_tarihi, $bitis_tarihi, $indirim_tipi, $indirim_miktari, $minimum_gece, $maksimum_gece, $aciklama, $aktif_durum, $id])) {
                $success_message = 'Kampanya başarıyla güncellendi.';
                logSecurityEvent(isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0, 'kampanya_guncellendi', "Kampanya güncellendi: $kampanya_adi");
            } else {
                $error_message = 'Kampanya güncellenirken bir hata oluştu.';
            }
        }
    }
}

// Kampanyaları listele
$stmt = $pdo->prepare("
    SELECT kf.*, ot.oda_tipi_adi as oda_tipi_adi 
    FROM kampanya_fiyatlari kf 
    LEFT JOIN oda_tipleri ot ON kf.oda_tipi_id = ot.id 
    ORDER BY kf.baslangic_tarihi DESC
");
$stmt->execute();
$kampanyalar = $stmt->fetchAll();

// Oda tiplerini getir
$stmt = $pdo->prepare("SELECT id, oda_tipi_adi FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
$stmt->execute();
$oda_tipleri = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kampanya Fiyatları - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Kampanya Fiyatları</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCampaignModal">
                        <i class="fas fa-plus"></i> Yeni Kampanya
                    </button>
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

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kampanya Adı</th>
                                        <th>Oda Tipi</th>
                                        <th>Tarih Aralığı</th>
                                        <th>İndirim/Fiyat</th>
                                        <th>Min. Gece</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kampanyalar as $kampanya): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($kampanya['kampanya_adi']); ?></strong>
                                                <?php if (isset($kampanya['kampanya_kodu']) && $kampanya['kampanya_kodu']): ?>
                                                    <br><small class="text-muted">Kod: <?php echo htmlspecialchars($kampanya['kampanya_kodu']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($kampanya['oda_tipi_adi']); ?></td>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($kampanya['baslangic_tarihi'])); ?> - 
                                                <?php echo date('d.m.Y', strtotime($kampanya['bitis_tarihi'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($kampanya['indirim_tipi'] === 'yuzde'): ?>
                                                    <span class="badge bg-success">%<?php echo $kampanya['indirim_miktari']; ?> İndirim</span>
                                                <?php elseif ($kampanya['indirim_tipi'] === 'sabit_tutar'): ?>
                                                    <span class="badge bg-info"><?php echo number_format($kampanya['indirim_miktari'], 2); ?> ₺ İndirim</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $kampanya['min_gece_sayisi']; ?> gece</td>
                                            <td>
                                                <span class="badge bg-<?php echo $kampanya['aktif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $kampanya['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCampaign(<?php echo htmlspecialchars(json_encode($kampanya)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCampaign(<?php echo $kampanya['id']; ?>, '<?php echo htmlspecialchars($kampanya['kampanya_adi']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

    <!-- Kampanya Ekleme Modal -->
    <div class="modal fade" id="addCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kampanya Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kampanya_adi" class="form-label">Kampanya Adı *</label>
                                    <input type="text" class="form-control" id="kampanya_adi" name="kampanya_adi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="oda_tipi_id" class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?php echo $oda_tipi['id']; ?>"><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi *</label>
                                    <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                    <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="indirim_orani" class="form-label">İndirim Oranı (%)</label>
                                    <input type="number" class="form-control" id="indirim_orani" name="indirim_orani" min="0" max="100" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sabit_fiyat" class="form-label">Sabit Fiyat (₺)</label>
                                    <input type="number" class="form-control" id="sabit_fiyat" name="sabit_fiyat" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="minimum_gece" class="form-label">Minimum Gece</label>
                                    <input type="number" class="form-control" id="minimum_gece" name="minimum_gece" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="maksimum_gece" class="form-label">Maksimum Gece</label>
                                    <input type="number" class="form-control" id="maksimum_gece" name="maksimum_gece" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="durum" class="form-label">Durum</label>
                                    <select class="form-select" id="durum" name="durum">
                                        <option value="aktif">Aktif</option>
                                        <option value="pasif">Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kampanya_kodu" class="form-label">Kampanya Kodu</label>
                            <input type="text" class="form-control" id="kampanya_kodu" name="kampanya_kodu">
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kampanya Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kampanya Düzenleme Modal -->
    <div class="modal fade" id="editCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kampanya Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCampaignForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_kampanya_adi" class="form-label">Kampanya Adı *</label>
                                    <input type="text" class="form-control" id="edit_kampanya_adi" name="kampanya_adi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_oda_tipi_id" class="form-label">Oda Tipi *</label>
                                    <select class="form-select" id="edit_oda_tipi_id" name="oda_tipi_id" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?php echo $oda_tipi['id']; ?>"><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_baslangic_tarihi" class="form-label">Başlangıç Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_baslangic_tarihi" name="baslangic_tarihi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_bitis_tarihi" name="bitis_tarihi" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_indirim_orani" class="form-label">İndirim Oranı (%)</label>
                                    <input type="number" class="form-control" id="edit_indirim_orani" name="indirim_orani" min="0" max="100" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sabit_fiyat" class="form-label">Sabit Fiyat (₺)</label>
                                    <input type="number" class="form-control" id="edit_sabit_fiyat" name="sabit_fiyat" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_minimum_gece" class="form-label">Minimum Gece</label>
                                    <input type="number" class="form-control" id="edit_minimum_gece" name="minimum_gece" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_maksimum_gece" class="form-label">Maksimum Gece</label>
                                    <input type="number" class="form-control" id="edit_maksimum_gece" name="maksimum_gece" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_durum" class="form-label">Durum</label>
                                    <select class="form-select" id="edit_durum" name="durum">
                                        <option value="aktif">Aktif</option>
                                        <option value="pasif">Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_kampanya_kodu" class="form-label">Kampanya Kodu</label>
                            <input type="text" class="form-control" id="edit_kampanya_kodu" name="kampanya_kodu">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="edit_aciklama" name="aciklama" rows="3"></textarea>
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

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteCampaignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kampanya Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu kampanyayı silmek istediğinizden emin misiniz?</p>
                    <p><strong id="deleteCampaignName"></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="deleteCampaignId">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCampaign(campaign) {
            document.getElementById('edit_id').value = campaign.id;
            document.getElementById('edit_kampanya_adi').value = campaign.kampanya_adi;
            document.getElementById('edit_oda_tipi_id').value = campaign.oda_tipi_id;
            document.getElementById('edit_baslangic_tarihi').value = campaign.baslangic_tarihi;
            document.getElementById('edit_bitis_tarihi').value = campaign.bitis_tarihi;
            document.getElementById('edit_indirim_orani').value = campaign.indirim_miktari || '';
            document.getElementById('edit_sabit_fiyat').value = campaign.sabit_fiyat || '';
            document.getElementById('edit_minimum_gece').value = campaign.minimum_gece;
            document.getElementById('edit_maksimum_gece').value = campaign.maksimum_gece || '';
            document.getElementById('edit_kampanya_kodu').value = campaign.kampanya_kodu || '';
            document.getElementById('edit_aciklama').value = campaign.aciklama || '';
            document.getElementById('edit_durum').value = campaign.durum;
            
            new bootstrap.Modal(document.getElementById('editCampaignModal')).show();
        }

        function deleteCampaign(id, name) {
            document.getElementById('deleteCampaignId').value = id;
            document.getElementById('deleteCampaignName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteCampaignModal')).show();
        }
    </script>
</body>
</html>