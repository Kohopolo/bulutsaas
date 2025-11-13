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
requireDetailedPermission('pdf_arsiv_goruntule', 'PDF arşiv görüntüleme yetkiniz bulunmamaktadır.');

$rezervasyon_id = isset($_GET['rezervasyon_id']) ? intval($_GET['rezervasyon_id']) : 0;

if (!$rezervasyon_id) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Rezervasyon bilgilerini al
$rezervasyon = fetchOne("
    SELECT r.*, 
           CONCAT(r.musteri_adi, ' ', r.musteri_soyadi) as musteri_tam_adi,
           ot.oda_tipi_adi
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE r.id = ?
", [$rezervasyon_id]);

if (!$rezervasyon) {
    header('Location: rezervasyonlar.php');
    exit;
}

// PDF arşiv kayıtlarını al
$pdf_arsiv = fetchAll("
    SELECT pa.*, 
           CONCAT(u.ad, ' ', u.soyad) as olusturan_admin
    FROM pdf_arsiv pa
    LEFT JOIN users u ON pa.olusturan_admin_id = u.id
    WHERE pa.rezervasyon_id = ?
    ORDER BY pa.olusturma_tarihi DESC
", [$rezervasyon_id]);

// PDF indirme işlemi
if (isset($_GET['download']) && isset($_GET['pdf_id'])) {
    $pdf_id = intval($_GET['pdf_id']);
    $pdf = fetchOne("SELECT * FROM pdf_arsiv WHERE id = ? AND rezervasyon_id = ?", [$pdf_id, $rezervasyon_id]);
    
    if ($pdf && file_exists($pdf['dosya_yolu'])) {
        $filename = $pdf['dosya_adi'];
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf['dosya_yolu']));
        
        readfile($pdf['dosya_yolu']);
        exit;
    } else {
        $error_message = 'PDF dosyası bulunamadı.';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Arşivi - Otel Rezervasyon Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-archive me-2"></i>PDF Arşivi
        </h1>
        <a href="rezervasyonlar.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Rezervasyonlara Dön
        </a>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- Rezervasyon Bilgileri -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Rezervasyon Bilgileri</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Rezervasyon Kodu:</strong><br>
                    <?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu'] ?? 'N/A'); ?>
                </div>
                <div class="col-md-3">
                    <strong>Müşteri:</strong><br>
                    <?php echo htmlspecialchars($rezervasyon['musteri_tam_adi'] ?? 'N/A'); ?>
                </div>
                <div class="col-md-3">
                    <strong>Oda Tipi:</strong><br>
                    <?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Tarihler:</strong><br>
                    <?php echo date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])); ?> - 
                    <?php echo date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Arşivi -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Arşivlenmiş PDF Dosyaları (<?php echo count($pdf_arsiv); ?> dosya)
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($pdf_arsiv)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-file-pdf fa-3x mb-3 d-block"></i>
                Bu rezervasyon için henüz arşivlenmiş PDF dosyası bulunmuyor.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>PDF Tipi</th>
                            <th>Dosya Adı</th>
                            <th>Boyut</th>
                            <th>Oluşturma Tarihi</th>
                            <th>Güncelleme Tarihi</th>
                            <th>Oluşturan</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pdf_arsiv as $pdf): ?>
                        <tr>
                            <td>
                                <i class="fas fa-<?php echo $pdf['pdf_tipi'] == 'voucher' ? 'ticket-alt' : 'file-contract'; ?> me-2"></i>
                                <?php echo $pdf['pdf_tipi'] == 'voucher' ? 'Voucher' : 'Sözleşme'; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($pdf['dosya_adi']); ?></strong>
                                <br><small class="text-muted">
                                    Hash: <?php echo substr($pdf['hash'], 0, 8); ?>...
                                </small>
                            </td>
                            <td>
                                <?php echo formatFileSize($pdf['dosya_boyutu']); ?>
                            </td>
                            <td>
                                <?php echo date('d.m.Y H:i', strtotime($pdf['olusturma_tarihi'])); ?>
                            </td>
                            <td>
                                <?php if ($pdf['guncelleme_tarihi']): ?>
                                    <?php echo date('d.m.Y H:i', strtotime($pdf['guncelleme_tarihi'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $pdf['olusturan_admin'] ? htmlspecialchars($pdf['olusturan_admin']) : '<span class="text-muted">Bilinmiyor</span>'; ?>
                            </td>
                            <td>
                                <a href="?rezervasyon_id=<?php echo $rezervasyon_id; ?>&download=1&pdf_id=<?php echo $pdf['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" title="İndir">
                                    <i class="fas fa-download"></i> İndir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Dosya boyutu formatlama fonksiyonu
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>