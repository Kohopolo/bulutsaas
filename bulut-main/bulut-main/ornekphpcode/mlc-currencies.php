<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/multi-language-currency.php';

// Yetki kontrolü
if (!hasDetailedPermission('multi_language_currency')) {
    header('Location: 403.php');
    exit;
}

$mlc = new MultiLanguageCurrency($pdo);

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $result = $mlc->addCurrency([
                    'kod' => $_POST['kod'],
                    'adi' => $_POST['adi'],
                    'sembol' => $_POST['sembol'],
                    'kur' => (float)$_POST['kur'],
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'varsayilan' => isset($_POST['varsayilan']) ? 1 : 0,
                    'sira' => (int)$_POST['sira']
                ]);
                $success = "Para birimi başarıyla eklendi.";
                break;
                
            case 'update':
                $result = $mlc->updateCurrency($_POST['id'], [
                    'kod' => $_POST['kod'],
                    'adi' => $_POST['adi'],
                    'sembol' => $_POST['sembol'],
                    'kur' => (float)$_POST['kur'],
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'varsayilan' => isset($_POST['varsayilan']) ? 1 : 0,
                    'sira' => (int)$_POST['sira']
                ]);
                $success = "Para birimi başarıyla güncellendi.";
                break;
                
            case 'update_rates':
                $rates = [];
                foreach ($_POST['rates'] as $currency => $rate) {
                    if ($rate > 0) {
                        $rates[$currency] = (float)$rate;
                    }
                }
                $mlc->updateExchangeRates($rates);
                $success = "Kurlar başarıyla güncellendi.";
                break;
        }
    }
}

$currencies = $mlc->getAvailableCurrencies();
$current_page = 'mlc-currencies.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Para Birimi Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .currency-symbol { font-weight: bold; color: #007bff; }
        .default-badge { background: linear-gradient(45deg, #28a745, #20c997); }
        .rate-input { max-width: 120px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-header">
                        <h1><i class="fas fa-coins"></i> Para Birimi Yönetimi</h1>
                        <p class="text-muted">Sistemde kullanılacak para birimlerini ve kurları yönetin</p>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Para Birimleri -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Para Birimleri</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCurrencyModal">
                                <i class="fas fa-plus"></i> Yeni Para Birimi Ekle
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kod</th>
                                            <th>Adı</th>
                                            <th>Sembol</th>
                                            <th>Kur (TRY)</th>
                                            <th>Sıra</th>
                                            <th>Durum</th>
                                            <th>Varsayılan</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($currencies as $currency): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($currency['kod']) ?></code></td>
                                                <td><?= htmlspecialchars($currency['adi']) ?></td>
                                                <td><span class="currency-symbol"><?= htmlspecialchars($currency['sembol']) ?></span></td>
                                                <td><?= number_format($currency['kur'], 4) ?></td>
                                                <td><?= $currency['sira'] ?></td>
                                                <td>
                                                    <?php if ($currency['aktif']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($currency['varsayilan']): ?>
                                                        <span class="badge default-badge text-white">Varsayılan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCurrency(<?= htmlspecialchars(json_encode($currency)) ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Kur Güncelleme -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Kur Güncelleme</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_rates">
                                <div class="row">
                                    <?php foreach ($currencies as $currency): ?>
                                        <?php if ($currency['kod'] !== 'TRY'): ?>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><?= htmlspecialchars($currency['kod']) ?> - <?= htmlspecialchars($currency['adi']) ?></label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control rate-input" name="rates[<?= $currency['kod'] ?>]" 
                                                           value="<?= $currency['kur'] ?>" step="0.0001" min="0">
                                                    <span class="input-group-text">TRY</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-sync-alt"></i> Kurları Güncelle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Para Birimi Ekleme Modal -->
    <div class="modal fade" id="addCurrencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Para Birimi Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Para Birimi Kodu *</label>
                            <input type="text" class="form-control" name="kod" required maxlength="3" placeholder="USD">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Para Birimi Adı *</label>
                            <input type="text" class="form-control" name="adi" required placeholder="US Dollar">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sembol *</label>
                            <input type="text" class="form-control" name="sembol" required placeholder="$">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kur (TRY karşılığı) *</label>
                            <input type="number" class="form-control" name="kur" required step="0.0001" min="0" placeholder="30.5000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sıra</label>
                            <input type="number" class="form-control" name="sira" value="0">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="aktif" checked>
                            <label class="form-check-label">Aktif</label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="varsayilan">
                            <label class="form-check-label">Varsayılan Para Birimi</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Para Birimi Düzenleme Modal -->
    <div class="modal fade" id="editCurrencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Para Birimi Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Para Birimi Kodu *</label>
                            <input type="text" class="form-control" name="kod" id="edit_kod" required maxlength="3">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Para Birimi Adı *</label>
                            <input type="text" class="form-control" name="adi" id="edit_adi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sembol *</label>
                            <input type="text" class="form-control" name="sembol" id="edit_sembol" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kur (TRY karşılığı) *</label>
                            <input type="number" class="form-control" name="kur" id="edit_kur" required step="0.0001" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sıra</label>
                            <input type="number" class="form-control" name="sira" id="edit_sira">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="aktif" id="edit_aktif">
                            <label class="form-check-label">Aktif</label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="varsayilan" id="edit_varsayilan">
                            <label class="form-check-label">Varsayılan Para Birimi</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCurrency(currency) {
            document.getElementById('edit_id').value = currency.id;
            document.getElementById('edit_kod').value = currency.kod;
            document.getElementById('edit_adi').value = currency.adi;
            document.getElementById('edit_sembol').value = currency.sembol;
            document.getElementById('edit_kur').value = currency.kur;
            document.getElementById('edit_sira').value = currency.sira;
            document.getElementById('edit_aktif').checked = currency.aktif == 1;
            document.getElementById('edit_varsayilan').checked = currency.varsayilan == 1;
            
            new bootstrap.Modal(document.getElementById('editCurrencyModal')).show();
        }
    </script>
</body>
</html>
