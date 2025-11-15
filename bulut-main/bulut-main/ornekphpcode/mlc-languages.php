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
                $result = $mlc->addLanguage([
                    'kod' => $_POST['kod'],
                    'adi' => $_POST['adi'],
                    'yerel_adi' => $_POST['yerel_adi'],
                    'bayrak' => $_POST['bayrak'] ?? null,
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'varsayilan' => isset($_POST['varsayilan']) ? 1 : 0,
                    'sira' => (int)$_POST['sira']
                ]);
                $success = "Dil başarıyla eklendi.";
                break;
                
            case 'update':
                $result = $mlc->updateLanguage($_POST['id'], [
                    'kod' => $_POST['kod'],
                    'adi' => $_POST['adi'],
                    'yerel_adi' => $_POST['yerel_adi'],
                    'bayrak' => $_POST['bayrak'] ?? null,
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'varsayilan' => isset($_POST['varsayilan']) ? 1 : 0,
                    'sira' => (int)$_POST['sira']
                ]);
                $success = "Dil başarıyla güncellendi.";
                break;
        }
    }
}

$languages = $mlc->getAvailableLanguages();
$current_page = 'mlc-languages.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dil Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .flag-icon { font-size: 1.5em; }
        .default-badge { background: linear-gradient(45deg, #28a745, #20c997); }
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
                        <h1><i class="fas fa-language"></i> Dil Yönetimi</h1>
                        <p class="text-muted">Sistemde kullanılacak dilleri yönetin</p>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Diller</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
                                <i class="fas fa-plus"></i> Yeni Dil Ekle
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bayrak</th>
                                            <th>Kod</th>
                                            <th>Adı</th>
                                            <th>Yerel Adı</th>
                                            <th>Sıra</th>
                                            <th>Durum</th>
                                            <th>Varsayılan</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($languages as $lang): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($lang['bayrak']): ?>
                                                        <span class="flag-icon"><?= htmlspecialchars($lang['bayrak']) ?></span>
                                                    <?php else: ?>
                                                        <i class="fas fa-flag text-muted"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?= htmlspecialchars($lang['kod']) ?></code></td>
                                                <td><?= htmlspecialchars($lang['adi']) ?></td>
                                                <td><?= htmlspecialchars($lang['yerel_adi']) ?></td>
                                                <td><?= $lang['sira'] ?></td>
                                                <td>
                                                    <?php if ($lang['aktif']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($lang['varsayilan']): ?>
                                                        <span class="badge default-badge text-white">Varsayılan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editLanguage(<?= htmlspecialchars(json_encode($lang)) ?>)">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Dil Ekleme Modal -->
    <div class="modal fade" id="addLanguageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Dil Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Dil Kodu *</label>
                            <input type="text" class="form-control" name="kod" required maxlength="5" placeholder="tr, en, de">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dil Adı *</label>
                            <input type="text" class="form-control" name="adi" required placeholder="Türkçe">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Yerel Adı *</label>
                            <input type="text" class="form-control" name="yerel_adi" required placeholder="Türkçe">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Bayrak Emoji</label>
                            <input type="text" class="form-control" name="bayrak" placeholder="🇹🇷">
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
                            <label class="form-check-label">Varsayılan Dil</label>
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

    <!-- Dil Düzenleme Modal -->
    <div class="modal fade" id="editLanguageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Dil Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Dil Kodu *</label>
                            <input type="text" class="form-control" name="kod" id="edit_kod" required maxlength="5">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dil Adı *</label>
                            <input type="text" class="form-control" name="adi" id="edit_adi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Yerel Adı *</label>
                            <input type="text" class="form-control" name="yerel_adi" id="edit_yerel_adi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Bayrak Emoji</label>
                            <input type="text" class="form-control" name="bayrak" id="edit_bayrak">
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
                            <label class="form-check-label">Varsayılan Dil</label>
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
        function editLanguage(lang) {
            document.getElementById('edit_id').value = lang.id;
            document.getElementById('edit_kod').value = lang.kod;
            document.getElementById('edit_adi').value = lang.adi;
            document.getElementById('edit_yerel_adi').value = lang.yerel_adi;
            document.getElementById('edit_bayrak').value = lang.bayrak || '';
            document.getElementById('edit_sira').value = lang.sira;
            document.getElementById('edit_aktif').checked = lang.aktif == 1;
            document.getElementById('edit_varsayilan').checked = lang.varsayilan == 1;
            
            new bootstrap.Modal(document.getElementById('editLanguageModal')).show();
        }
    </script>
</body>
</html>
