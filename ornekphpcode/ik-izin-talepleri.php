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
if (!hasDetailedPermission('ik_izin_talepleri')) {
    $_SESSION['error_message'] = 'İK izin talepleri yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'izin_onayla') {
            $izin_id = intval($_POST['izin_id']);
            $sql = "UPDATE izin_talepleri SET durum = 'onaylandi', onaylayan_id = ?, onay_tarihi = CURDATE() WHERE id = ?";
            if (executeQuery($sql, [$_SESSION['user_id'], $izin_id])) {
                $success_message = 'İzin talebi onaylandı.';
            } else {
                $error_message = 'İzin onaylanırken hata oluştu.';
            }
        }
        
        if ($action == 'izin_reddet') {
            $izin_id = intval($_POST['izin_id']);
            $red_nedeni = sanitizeString($_POST['red_nedeni']);
            $sql = "UPDATE izin_talepleri SET durum = 'reddedildi', onaylayan_id = ?, onay_tarihi = CURDATE(), red_nedeni = ? WHERE id = ?";
            if (executeQuery($sql, [$_SESSION['user_id'], $red_nedeni, $izin_id])) {
                $success_message = 'İzin talebi reddedildi.';
            } else {
                $error_message = 'İzin reddedilirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İzin taleplerini getir
$izin_talepleri = fetchAll("
    SELECT it.*, k.ad, k.soyad, k.rol, k2.ad as onaylayan_adi, k2.soyad as onaylayan_soyadi
    FROM izin_talepleri it
    LEFT JOIN kullanicilar k ON it.personel_id = k.id
    LEFT JOIN kullanicilar k2 ON it.onaylayan_id = k2.id
    ORDER BY it.olusturma_tarihi DESC
");

// İzin türleri
$izin_turleri = [
    'yillik' => 'Yıllık İzin',
    'hastalik' => 'Hastalık İzni',
    'dogum' => 'Doğum İzni',
    'evlilik' => 'Evlilik İzni',
    'olum' => 'Ölüm İzni',
    'mazeret' => 'Mazeret İzni',
    'unpaid' => 'Ücretsiz İzin'
];

// İzin durumları
$izin_durumlari = [
    'beklemede' => 'Beklemede',
    'onaylandi' => 'Onaylandı',
    'reddedildi' => 'Reddedildi'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İK İzin Talepleri</title>
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
                    <h1 class="h2">İK İzin Talepleri</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="ik-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
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

                <!-- İzin Talepleri Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-check"></i> İzin Talepleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($izin_talepleri)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz izin talebi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th>İzin Türü</th>
                                            <th>Başlangıç</th>
                                            <th>Bitiş</th>
                                            <th>Gün Sayısı</th>
                                            <th>Durum</th>
                                            <th>Talep Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($izin_talepleri as $izin): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($izin['ad'] . ' ' . $izin['soyad']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($izin['rol']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($izin_turleri[$izin['izin_turu']] ?? $izin['izin_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d.m.Y', strtotime($izin['baslangic_tarihi'])); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($izin['bitis_tarihi'])); ?></td>
                                                <td><?php echo $izin['gun_sayisi']; ?> gün</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $izin['durum'] == 'onaylandi' ? 'success' : 
                                                            ($izin['durum'] == 'reddedildi' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($izin_durumlari[$izin['durum']] ?? $izin['durum']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d.m.Y', strtotime($izin['olusturma_tarihi'])); ?></td>
                                                <td>
                                                    <?php if ($izin['durum'] == 'beklemede'): ?>
                                                        <div class="btn-group" role="group">
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="izin_onayla">
                                                                <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('İzin talebini onaylamak istediğinizden emin misiniz?')">
                                                                    <i class="fas fa-check"></i> Onayla
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#reddetModal<?php echo $izin['id']; ?>">
                                                                <i class="fas fa-times"></i> Reddet
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php if ($izin['onaylayan_adi']): ?>
                                                                <?php echo htmlspecialchars($izin['onaylayan_adi'] . ' ' . $izin['onaylayan_soyadi']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
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

    <!-- Reddet Modal -->
    <?php foreach ($izin_talepleri as $izin): ?>
        <?php if ($izin['durum'] == 'beklemede'): ?>
            <div class="modal fade" id="reddetModal<?php echo $izin['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">İzin Talebini Reddet</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="izin_reddet">
                                <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Red Nedeni <span class="text-danger">*</span></label>
                                    <textarea name="red_nedeni" class="form-control" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                <button type="submit" class="btn btn-danger">Reddet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
