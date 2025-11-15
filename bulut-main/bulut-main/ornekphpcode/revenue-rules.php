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

if (($_POST['action'] ?? '') === 'create_rule') {
    try {
        $rm->createRule([
            'kural_adi' => $_POST['kural_adi'] ?? 'Kural',
            'kanal' => $_POST['kanal'] ?? 'all',
            'yuzde_ayarlama' => (float)($_POST['yuzde_ayarlama'] ?? 0),
            'tutar_ayarlama' => (float)($_POST['tutar_ayarlama'] ?? 0),
            'oncelik' => (int)($_POST['oncelik'] ?? 100),
            'aktif' => (int)($_POST['aktif'] ?? 1),
        ]);
        $success_message = 'Kural eklendi';
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

$rules = $rm->listRules(1, 100);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiyat Kuralları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-sliders-h text-primary"></i> Fiyat Kuralları</h4>
            <a class="btn btn-outline-secondary btn-sm" href="revenue-management.php">Önizleme</a>
        </div>
        <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">Yeni Kural</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_rule">
                            <div class="mb-2">
                                <label class="form-label">Kural Adı</label>
                                <input type="text" name="kural_adi" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Kanal</label>
                                <select name="kanal" class="form-select">
                                    <option value="all">Tümü</option>
                                    <option value="direct">Direct</option>
                                    <option value="ota">OTA</option>
                                    <option value="corporate">Corporate</option>
                                    <option value="group">Group</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Yüzde Ayarlama</label>
                                <input type="number" step="0.01" name="yuzde_ayarlama" class="form-control" value="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tutar Ayarlama</label>
                                <input type="number" step="0.01" name="tutar_ayarlama" class="form-control" value="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Öncelik</label>
                                <input type="number" name="oncelik" class="form-control" value="100">
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
                    <div class="card-header">Kayıtlı Kurallar</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ad</th>
                                        <th>Kanal</th>
                                        <th>%</th>
                                        <th>Tutar</th>
                                        <th>Öncelik</th>
                                        <th>Aktif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($rules['items'] ?? []) as $r): ?>
                                        <tr>
                                            <td><?php echo (int)$r['id']; ?></td>
                                            <td><?php echo htmlspecialchars($r['kural_adi']); ?></td>
                                            <td><?php echo htmlspecialchars($r['kanal']); ?></td>
                                            <td><?php echo number_format((float)$r['yuzde_ayarlama'], 2); ?></td>
                                            <td><?php echo number_format((float)$r['tutar_ayarlama'], 2); ?></td>
                                            <td><?php echo (int)$r['oncelik']; ?></td>
                                            <td><?php echo (int)$r['aktif'] ? 'Evet' : 'Hayır'; ?></td>
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


