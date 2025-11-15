<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/marketing-promotions.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('marketing_promotions')) { $_SESSION['error_message']='Pazarlama & Promosyon yetkiniz yok.'; header('Location: /error/403.php'); exit; }

$mp = new MarketingPromotions($pdo);
$success_message=''; $error_message='';

if (($_POST['action'] ?? '') === 'create_rule') {
    try { $mp->createRule($_POST); $success_message='Kural eklendi'; } catch (Exception $e) { $error_message=$e->getMessage(); }
}

$list = $mp->listRules(1, 100);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promosyon Kuralları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-sliders-h text-info"></i> Promosyon Kuralları</h4>
        </div>
        <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Yeni Kural</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_rule">
                            <div class="mb-2"><label class="form-label">Kampanya ID</label><input type="number" name="kampanya_id" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Kural Adı</label><input type="text" name="kural_adi" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Kanal</label>
                                <select name="kanal" class="form-select">
                                    <option value="all">Tümü</option>
                                    <option value="direct">Direct</option>
                                    <option value="ota">OTA</option>
                                    <option value="corporate">Corporate</option>
                                    <option value="group">Group</option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2"><label class="form-label">Min LOS</label><input type="number" name="min_los" class="form-control"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Max LOS</label><input type="number" name="max_los" class="form-control"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2"><label class="form-label">Min Lead</label><input type="number" name="min_lead_days" class="form-control"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Max Lead</label><input type="number" name="max_lead_days" class="form-control"></div>
                            </div>
                            <div class="mb-2"><label class="form-label">Oda Tipi ID</label><input type="number" name="oda_tipi_id" class="form-control"></div>
                            <div class="row">
                                <div class="col-md-6 mb-2"><label class="form-label">% İndirim</label><input type="number" step="0.01" name="yuzde_indirim" class="form-control" value="0"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Tutar İndirim</label><input type="number" step="0.01" name="tutar_indirim" class="form-control" value="0"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2"><label class="form-label">Öncelik</label><input type="number" name="oncelik" class="form-control" value="100"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Aktif</label><select name="aktif" class="form-select"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
                            </div>
                            <div class="text-end"><button class="btn btn-success" type="submit">Kaydet</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Kayıtlı Kurallar</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead><tr><th>ID</th><th>Ad</th><th>Kanal</th><th>%</th><th>Tutar</th><th>Aktif</th></tr></thead>
                                <tbody>
                                <?php foreach (($list['items'] ?? []) as $r): ?>
                                    <tr>
                                        <td><?php echo (int)$r['id']; ?></td>
                                        <td><?php echo htmlspecialchars($r['kural_adi']); ?></td>
                                        <td><?php echo htmlspecialchars($r['kanal']); ?></td>
                                        <td><?php echo number_format((float)$r['yuzde_indirim'], 2); ?></td>
                                        <td><?php echo number_format((float)$r['tutar_indirim'], 2); ?></td>
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


