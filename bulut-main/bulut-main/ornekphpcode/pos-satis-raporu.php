<?php
/**
 * POS Satış Raporu
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/pos-integration.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('pos_yonetimi')) {
    $_SESSION['error_message'] = 'POS yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Filtreler
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d');
$satis_turu = $_GET['satis_turu'] ?? '';
$terminal_id = isset($_GET['terminal_id']) ? intval($_GET['terminal_id']) : '';

$filters = [
    'baslangic_tarihi' => $baslangic_tarihi,
    'bitis_tarihi' => $bitis_tarihi,
];
if (!empty($satis_turu)) $filters['satis_turu'] = $satis_turu;
if (!empty($terminal_id)) $filters['terminal_id'] = $terminal_id;

$pos = new POSIntegration($pdo);
$report = $pos->generateSalesReport($filters);

// Terminaller (filtre için)
$terminaller = fetchAll("SELECT id, terminal_adi FROM pos_terminalleri ORDER BY terminal_adi");

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Satış Raporu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-chart-bar text-primary"></i> POS Satış Raporu</h4>
            <a href="pos-dashboard.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Geri</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" name="baslangic_tarihi" value="<?php echo htmlspecialchars($baslangic_tarihi); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" name="bitis_tarihi" value="<?php echo htmlspecialchars($bitis_tarihi); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satış Türü</label>
                        <select class="form-select" name="satis_turu">
                            <option value="">Tümü</option>
                            <option value="nakit" <?php echo $satis_turu==='nakit'?'selected':''; ?>>Nakit</option>
                            <option value="kredi_karti" <?php echo $satis_turu==='kredi_karti'?'selected':''; ?>>Kredi Kartı</option>
                            <option value="oda_hesabi" <?php echo $satis_turu==='oda_hesabi'?'selected':''; ?>>Oda Hesabı</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Terminal</label>
                        <select class="form-select" name="terminal_id">
                            <option value="">Tümü</option>
                            <?php foreach ($terminaller as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($terminal_id==$t['id'])?'selected':''; ?>><?php echo htmlspecialchars($t['terminal_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrele</button>
                    </div>
                </form>
            </div>
        </div>

        <?php $stats = $report['stats'] ?? ['toplam_satis'=>0,'toplam_ciro'=>0,'toplam_kdv'=>0,'toplam_indirim'=>0,'ortalama_sepet'=>0]; ?>

        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Toplam Satış</div>
                        <div class="h4 mb-0"><?php echo intval($stats['toplam_satis']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Toplam Ciro</div>
                        <div class="h4 mb-0"><?php echo number_format($stats['toplam_ciro'] ?? 0, 2); ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Toplam KDV</div>
                        <div class="h4 mb-0"><?php echo number_format($stats['toplam_kdv'] ?? 0, 2); ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Ortalama Sepet</div>
                        <div class="h4 mb-0"><?php echo number_format($stats['ortalama_sepet'] ?? 0, 2); ?> ₺</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Satış Listesi</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fatura No</th>
                                <th>Tarih</th>
                                <th>Terminal</th>
                                <th>Tür</th>
                                <th>Toplam</th>
                                <th>KDV</th>
                                <th>Genel Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($report['sales'] ?? []) as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['fatura_no']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($row['satis_tarihi'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['terminal_adi'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['satis_turu']); ?></td>
                                    <td><?php echo number_format($row['toplam_tutar'], 2); ?> ₺</td>
                                    <td><?php echo number_format($row['kdv_tutari'], 2); ?> ₺</td>
                                    <td><strong><?php echo number_format($row['genel_toplam'], 2); ?> ₺</strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($report['sales'])): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Kayıt bulunamadı</td></tr>
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



