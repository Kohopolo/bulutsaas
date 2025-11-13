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

$success_message = '';
$error_message = '';

$rm = new RevenueManagement($pdo);

// Basit fiyat önizleme
$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+6 days'));
$roomTypeId = isset($_GET['oda_tipi_id']) ? (int)$_GET['oda_tipi_id'] : null;
$channel = $_GET['kanal'] ?? 'all';
$lead = isset($_GET['lead']) ? (int)$_GET['lead'] : 7;
$los = isset($_GET['los']) ? (int)$_GET['los'] : 1;

$prices = $rm->calculatePrices($start, $end, $roomTypeId, $channel, $lead, $los);

// Demo veri ekleme
if (($_GET['action'] ?? '') === 'add_demo') {
    try {
        // Örnek sezon
        $rm->createSeason([
            'adi' => 'Yüksek Sezon Demo',
            'baslangic_tarihi' => date('Y-m-d', strtotime('+10 days')),
            'bitis_tarihi' => date('Y-m-d', strtotime('+20 days')),
            'taban_fiyat' => 2000,
            'yuzde_ayarlama' => 10,
            'tutar_ayarlama' => 0,
            'aktif' => 1,
        ]);

        // Örnek kurallar
        $rm->createRule([
            'kural_adi' => 'Erken Rezervasyon İndirimi',
            'kanal' => 'all',
            'min_lead_days' => 14,
            'yuzde_ayarlama' => -10,
            'oncelik' => 50,
            'aktif' => 1,
        ]);
        $rm->createRule([
            'kural_adi' => 'Uzun Konaklama İndirimi',
            'kanal' => 'all',
            'min_los' => 3,
            'yuzde_ayarlama' => -5,
            'oncelik' => 60,
            'aktif' => 1,
        ]);
        $success_message = 'Demo sezon ve kurallar eklendi.';
    } catch (Exception $e) {
        $error_message = 'Demo veri eklenirken hata: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelir Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-coins text-primary"></i> Gelir Yönetimi</h4>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Başlangıç</label>
                        <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bitiş</label>
                        <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Oda Tipi ID</label>
                        <input type="number" class="form-control" name="oda_tipi_id" value="<?php echo htmlspecialchars((string)$roomTypeId); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Kanal</label>
                        <select class="form-select" name="kanal">
                            <option value="all" <?php echo $channel==='all'?'selected':''; ?>>Tümü</option>
                            <option value="direct" <?php echo $channel==='direct'?'selected':''; ?>>Direct</option>
                            <option value="ota" <?php echo $channel==='ota'?'selected':''; ?>>OTA</option>
                            <option value="corporate" <?php echo $channel==='corporate'?'selected':''; ?>>Corporate</option>
                            <option value="group" <?php echo $channel==='group'?'selected':''; ?>>Group</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Lead (gün)</label>
                        <input type="number" class="form-control" name="lead" value="<?php echo (int)$lead; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">LOS</label>
                        <input type="number" class="form-control" name="los" value="<?php echo (int)$los; ?>">
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" type="submit">Önizle</button>
                        <a class="btn btn-outline-secondary" href="revenue-rules.php">Kurallar</a>
                        <a class="btn btn-outline-secondary" href="revenue-seasons.php">Sezonlar</a>
                        <button name="action" value="add_demo" class="btn btn-outline-success" type="submit">Demo Veri Ekle</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Taban</th>
                                <th>Sezon</th>
                                <th>Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prices as $p): ?>
                                <tr>
                                    <td><?php echo $p['date']; ?></td>
                                    <td><?php echo number_format($p['base'], 2); ?> ₺</td>
                                    <td><?php echo number_format($p['season'], 2); ?> ₺</td>
                                    <td><strong><?php echo number_format($p['final'], 2); ?> ₺</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Fiyat Kuralları</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_rule">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Kural Adı</label>
                                    <input type="text" name="kural_adi" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kanal</label>
                                    <select name="kanal" class="form-select">
                                        <option value="all">Tümü</option>
                                        <option value="direct">Direct</option>
                                        <option value="ota">OTA</option>
                                        <option value="corporate">Corporate</option>
                                        <option value="group">Group</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Yüzde Ayarlama</label>
                                    <input type="number" step="0.01" name="yuzde_ayarlama" class="form-control" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tutar Ayarlama</label>
                                    <input type="number" step="0.01" name="tutar_ayarlama" class="form-control" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Öncelik</label>
                                    <input type="number" name="oncelik" class="form-control" value="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Aktif</label>
                                    <select name="aktif" class="form-select">
                                        <option value="1">Aktif</option>
                                        <option value="0">Pasif</option>
                                    </select>
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-success" type="submit">Kural Ekle</button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <?php
                        // Kural oluşturma
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
                                echo '<div class="alert alert-success">Kural eklendi.</div>';
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger">Hata: '.htmlspecialchars($e->getMessage()).'</div>';
                            }
                        }

                        // Kuralları listele
                        $rules = $rm->listRules(1, 50);
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Adı</th>
                                        <th>Kanal</th>
                                        <th>Yüzde</th>
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
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Sezon/Etkinlikler</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_season">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Adı</label>
                                    <input type="text" name="adi" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Başlangıç</label>
                                    <input type="date" name="baslangic_tarihi" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Bitiş</label>
                                    <input type="date" name="bitis_tarihi" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Taban Fiyat</label>
                                    <input type="number" step="0.01" name="taban_fiyat" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Yüzde Ayar</label>
                                    <input type="number" step="0.01" name="yuzde_ayarlama" class="form-control" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tutar Ayar</label>
                                    <input type="number" step="0.01" name="tutar_ayarlama" class="form-control" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Aktif</label>
                                    <select name="aktif" class="form-select">
                                        <option value="1">Aktif</option>
                                        <option value="0">Pasif</option>
                                    </select>
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-success" type="submit">Sezon Ekle</button>
                                </div>
                            </div>
                        </form>
                        <hr>
                        <?php
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
                                echo '<div class="alert alert-success">Sezon eklendi.</div>';
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger">Hata: '.htmlspecialchars($e->getMessage()).'</div>';
                            }
                        }

                        $seasons = $rm->listSeasons(1, 50);
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Adı</th>
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



