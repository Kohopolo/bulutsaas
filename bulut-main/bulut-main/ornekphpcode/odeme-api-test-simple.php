<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/detailed_permission_functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
requireDetailedPermission('odeme_api_test', 'Ödeme API test yetkiniz bulunmamaktadır.');
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Ödeme API Test</h3>
                </div>
                <div class="card-body">
                    <p>Ödeme API test sayfası çalışıyor!</p>
                    <p>Bu sayfa test amaçlı oluşturulmuştur.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
