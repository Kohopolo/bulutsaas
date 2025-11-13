<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/revenue-management.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('revenue_management')) { $_SESSION['error_message']='Gelir yönetimi yetkiniz bulunmamaktadır.'; header('Location: /error/403.php'); exit; }

$rm = new RevenueManagement($pdo);

if (($_POST['action'] ?? '') === 'create_season') {
    try {
        $rm->createSeason([
            'adi' => $_POST['adi'] ?? 'Sezon',
            'baslangic_tarihi' => $_POST['baslangic_tarihi'] ?? date('Y-m-d'),
            'bitis_tarihi' => $_POST['bitis_tarihi'] ?? date('Y-m-d', strtotime('+7 days')),
            'taban_fiyat' => $_POST['taban_fiyat'] !== '' ? (float)$_POST['taban_fiyat'] : null,
            'yuzde_ayarlama' => (float)($_POST['yuzde_ayarlama'] ?? 0),
            'tutar_ayarlama' => (float)($_POST['tutar_ayarlama'] ?? 0),
            'aktif' => (int)($_POST['aktif'] ?? 1),
        ]);
        $success_message = 'Sezon eklendi';
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

$seasons = $rm->listSeasons(1, 100);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sezon/Etkinlikler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-sun text-warning"></i> Sezon/Etkinlikler</h4>
            <a class="btn btn-outline-secondary btn-sm" href="revenue-management.php">Önizleme</a>
        </div>
        <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">Yeni Sezon</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_season">
                            <div class="mb-2">
                                <label class="form-label">Adı</label>
                                <input type="text" name="adi" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Başlangıç</label>
                                <input type="date" name="baslangic_tarihi" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Bitiş</label>
                                <input type="date" name="bitis_tarihi" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Taban Fiyat</label>
                                <input type="number" step="0.01" name="taban_fiyat" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Yüzde Ayar</label>
                                <input type="number" step="0.01" name="yuzde_ayarlama" class="form-control" value="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tutar Ayar</label>
                                <input type="number" step="0.01" name="tutar_ayarlama" class="form-control" value="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Aktif</label>
                                <select name="aktif" class="form-select">
                                    <option value="1">Aktif</option>
                                    <option value="0">Pasif</option>
                                </select>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-success" type="submit">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">Kayıtlı Sezonlar</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ad</th>
                                        <th>Başlangıç</th>
                                        <th>Bitiş</th>
                                        <th>Aktif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($seasons['items'] ?? []) as $s): ?>
                                        <tr>
                                            <td><?php echo (int)$s['id']; ?></td>
                                            <td><?php echo htmlspecialchars($s['adi']); ?></td>
                                            <td><?php echo htmlspecialchars($s['baslangic_tarihi']); ?></td>
                                            <td><?php echo htmlspecialchars($s['bitis_tarihi']); ?></td>
                                            <td><?php echo (int)$s['aktif'] ? 'Evet' : 'Hayır'; ?></td>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


