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

if (($_POST['action'] ?? '') === 'log_interaction') {
    try {
        $crm->logInteraction((int)($_POST['musteri_id'] ?? 0), $_POST['tur'] ?? 'note', $_POST['konu'] ?? '', $_POST['icerik'] ?? null);
        $success_message = 'Etkileşim kaydedildi';
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

$list = $musteriId ? $crm->listInteractions($musteriId, 1, 100) : ['items' => []];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Etkileşimler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-comments text-info"></i> Etkileşimler</h4>
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
                <div class="card">
                    <div class="card-header">Yeni Etkileşim</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="log_interaction">
                            <div class="mb-2">
                                <label class="form-label">Müşteri ID</label>
                                <input type="number" name="musteri_id" class="form-control" value="<?php echo $musteriId ?: ''; ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tür</label>
                                <select name="tur" class="form-select">
                                    <option value="note">Not</option>
                                    <option value="call">Çağrı</option>
                                    <option value="email">E-posta</option>
                                    <option value="complaint">Şikayet</option>
                                    <option value="request">Talep</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Konu</label>
                                <input type="text" name="konu" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">İçerik</label>
                                <textarea name="icerik" class="form-control" rows="3"></textarea>
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
                    <div class="card-header">Kayıtlı Etkileşimler</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tür</th>
                                        <th>Konu</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($list['items'] ?? []) as $it): ?>
                                    <tr>
                                        <td><?php echo (int)$it['id']; ?></td>
                                        <td><?php echo htmlspecialchars($it['tur']); ?></td>
                                        <td><?php echo htmlspecialchars($it['konu']); ?></td>
                                        <td><?php echo htmlspecialchars($it['olusturma_tarihi']); ?></td>
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


