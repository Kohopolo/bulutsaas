<?php

/**
 * POS Oda Hesapları
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('pos_yonetimi')) { $_SESSION['error_message']='POS yönetimi yetkiniz bulunmamaktadır.'; header('Location: /error/403.php'); exit; }

$hesaplar = fetchAll("SELECT oh.*, o.oda_numarasi, CONCAT_WS(' ', m.ad, m.soyad) AS musteri_adi FROM oda_hesaplari oh LEFT JOIN oda_numaralari o ON oh.oda_id = o.id LEFT JOIN musteriler m ON oh.musteri_id = m.id ORDER BY oh.acilis_tarihi DESC");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Oda Hesapları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-bed text-primary"></i> Oda Hesapları</h4>
            <a href="pos-dashboard.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Geri</a>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Hesap No</th>
                                <th>Oda</th>
                                <th>Müşteri</th>
                                <th>Durum</th>
                                <th>Toplam</th>
                                <th>Ödenen</th>
                                <th>Kalan</th>
                                <th>Açılış</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hesaplar as $h): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($h['hesap_no']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($h['oda_numarasi']); ?></span></td>
                                <td><?php echo htmlspecialchars($h['musteri_adi']); ?></td>
                                <td><span class="badge <?php echo $h['durum']==='acik'?'bg-success':'bg-secondary'; ?>"><?php echo htmlspecialchars($h['durum']); ?></span></td>
                                <td><?php echo number_format($h['toplam_tutar'],2); ?> ₺</td>
                                <td><?php echo number_format($h['odenen_tutar'],2); ?> ₺</td>
                                <td><strong><?php echo number_format($h['kalan_tutar'],2); ?> ₺</strong></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($h['acilis_tarihi'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($hesaplar)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Kayıt bulunamadı</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


