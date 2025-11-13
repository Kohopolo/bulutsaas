<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/crm.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('crm_modulu')) { $_SESSION['error_message']='CRM yetkiniz bulunmamaktadır.'; header('Location: /error/403.php'); exit; }

$crm = new CRM($pdo);
$success_message = '';
$error_message = '';

$musteriId = (int)($_GET['musteri_id'] ?? 0);

if (($_POST['action'] ?? '') === 'add_points') {
    try {
        $crm->addLoyaltyPoints((int)($_POST['musteri_id'] ?? 0), (int)($_POST['points'] ?? 0), $_POST['aciklama'] ?? null);
        $success_message = 'Puan eklendi';
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}
if (($_POST['action'] ?? '') === 'deduct_points') {
    try {
        $crm->deductLoyaltyPoints((int)($_POST['musteri_id'] ?? 0), (int)($_POST['points'] ?? 0), $_POST['aciklama'] ?? null);
        $success_message = 'Puan düşüldü';
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

$movements = $musteriId ? $crm->listLoyaltyMovements($musteriId, 1, 50) : ['items' => [], 'balance' => 0];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Sadakat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-gift text-success"></i> Sadakat</h4>
            <a class="btn btn-outline-secondary btn-sm" href="crm-dashboard.php">CRM Dashboard</a>
        </div>
        <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="get">
                    <div class="col-md-3">
                        <label class="form-label">Müşteri ID</label>
                        <input type="number" name="musteri_id" class="form-control" value="<?php echo $musteriId ?: ''; ?>">
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button class="btn btn-primary" type="submit">Listele</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5">
                <div class="card mb-3">
                    <div class="card-header">Puan Ekle</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_points">
                            <div class="mb-2">
                                <label class="form-label">Müşteri ID</label>
                                <input type="number" name="musteri_id" class="form-control" value="<?php echo $musteriId ?: ''; ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Puan</label>
                                <input type="number" name="points" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <input type="text" name="aciklama" class="form-control">
                            </div>
                            <div class="text-end">
                                <button class="btn btn-success" type="submit">Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">Puan Düş</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="deduct_points">
                            <div class="mb-2">
                                <label class="form-label">Müşteri ID</label>
                                <input type="number" name="musteri_id" class="form-control" value="<?php echo $musteriId ?: ''; ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Puan</label>
                                <input type="number" name="points" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <input type="text" name="aciklama" class="form-control">
                            </div>
                            <div class="text-end">
                                <button class="btn btn-danger" type="submit">Düş</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">Hareketler | Bakiye: <strong><?php echo (int)($movements['balance'] ?? 0); ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Değişim</th>
                                        <th>Açıklama</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($movements['items'] ?? []) as $m): ?>
                                    <tr>
                                        <td><?php echo (int)$m['id']; ?></td>
                                        <td><?php echo (int)$m['degisim']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$m['aciklama']); ?></td>
                                        <td><?php echo htmlspecialchars($m['olusturma_tarihi']); ?></td>
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


