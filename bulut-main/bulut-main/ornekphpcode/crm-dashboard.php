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

// Basit sayaçlar
$prefCount = (int)$pdo->query("SELECT COUNT(*) FROM crm_musteri_tercihleri")->fetchColumn();
$loyaltyCount = (int)$pdo->query("SELECT COUNT(*) FROM crm_sadakat_hesaplari")->fetchColumn();
$interactionCount = (int)$pdo->query("SELECT COUNT(*) FROM crm_musteri_etkilesimleri")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-user-friends text-primary"></i> CRM Dashboard</h4>
            <div class="btn-group">
                <a href="crm-preferences.php" class="btn btn-outline-secondary btn-sm">Tercihler</a>
                <a href="crm-loyalty.php" class="btn btn-outline-secondary btn-sm">Sadakat</a>
                <a href="crm-interactions.php" class="btn btn-outline-secondary btn-sm">Etkileşimler</a>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted">Tercihler</div>
                                <div class="h4 mb-0"><?php echo $prefCount; ?></div>
                            </div>
                            <i class="fas fa-heart fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted">Sadakat Hesapları</div>
                                <div class="h4 mb-0"><?php echo $loyaltyCount; ?></div>
                            </div>
                            <i class="fas fa-gift fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted">Etkileşimler</div>
                                <div class="h4 mb-0"><?php echo $interactionCount; ?></div>
                            </div>
                            <i class="fas fa-comments fa-2x text-info"></i>
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


