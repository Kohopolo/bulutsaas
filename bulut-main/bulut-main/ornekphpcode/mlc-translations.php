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
                $result = $mlc->addTranslation(
                    $_POST['anahtar'],
                    $_POST['dil_kodu'],
                    $_POST['metin'],
                    $_POST['kategori'] ?? null
                );
                $success = "Çeviri başarıyla eklendi.";
                break;
                
            case 'update':
                $result = $mlc->updateTranslation($_POST['id'], $_POST['metin']);
                $success = "Çeviri başarıyla güncellendi.";
                break;
        }
    }
}

$languages = $mlc->getAvailableLanguages();
$selectedLanguage = $_GET['language'] ?? 'tr';
$selectedCategory = $_GET['category'] ?? null;

$translations = $mlc->getTranslations($selectedLanguage, $selectedCategory);
$current_page = 'mlc-translations.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çeviri Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .translation-key { font-family: monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .translation-text { max-width: 300px; word-wrap: break-word; }
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
                        <h1><i class="fas fa-language"></i> Çeviri Yönetimi</h1>
                        <p class="text-muted">Sistem metinlerinin çevirilerini yönetin</p>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filtreler -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Dil Seçin</label>
                                    <select name="language" class="form-select" onchange="this.form.submit()">
                                        <?php foreach ($languages as $lang): ?>
                                            <option value="<?= $lang['kod'] ?>" <?= $selectedLanguage === $lang['kod'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lang['bayrak'] ?? '') ?> <?= htmlspecialchars($lang['adi']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Kategori</label>
                                    <select name="category" class="form-select" onchange="this.form.submit()">
                                        <option value="">Tüm Kategoriler</option>
                                        <option value="genel" <?= $selectedCategory === 'genel' ? 'selected' : '' ?>>Genel</option>
                                        <option value="rezervasyon" <?= $selectedCategory === 'rezervasyon' ? 'selected' : '' ?>>Rezervasyon</option>
                                        <option value="hizmetler" <?= $selectedCategory === 'hizmetler' ? 'selected' : '' ?>>Hizmetler</option>
                                        <option value="odalar" <?= $selectedCategory === 'odalar' ? 'selected' : '' ?>>Odalar</option>
                                        <option value="admin" <?= $selectedCategory === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
                                        <i class="fas fa-plus"></i> Yeni Çeviri Ekle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Çeviriler -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Çeviriler 
                                <?php if ($selectedLanguage): ?>
                                    - <?= htmlspecialchars($languages[array_search($selectedLanguage, array_column($languages, 'kod'))]['adi'] ?? $selectedLanguage) ?>
                                <?php endif; ?>
                                <?php if ($selectedCategory): ?>
                                    - <?= ucfirst($selectedCategory) ?>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Anahtar</th>
                                            <th>Çeviri</th>
                                            <th>Kategori</th>
                                            <th>Güncelleme</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($translations as $translation): ?>
                                            <tr>
                                                <td>
                                                    <span class="translation-key"><?= htmlspecialchars($translation['anahtar']) ?></span>
                                                </td>
                                                <td class="translation-text"><?= htmlspecialchars($translation['metin']) ?></td>
                                                <td>
                                                    <?php if ($translation['kategori']): ?>
                                                        <span class="badge bg-info"><?= htmlspecialchars($translation['kategori']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d.m.Y H:i', strtotime($translation['guncelleme_tarihi'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editTranslation(<?= htmlspecialchars(json_encode($translation)) ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($translations)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    <i class="fas fa-info-circle"></i> Bu dil için çeviri bulunamadı.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Çeviri Ekleme Modal -->
    <div class="modal fade" id="addTranslationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Çeviri Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Anahtar *</label>
                            <input type="text" class="form-control" name="anahtar" required placeholder="welcome_message">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dil *</label>
                            <select name="dil_kodu" class="form-select" required>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= $lang['kod'] ?>" <?= $selectedLanguage === $lang['kod'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang['bayrak'] ?? '') ?> <?= htmlspecialchars($lang['adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Çeviri Metni *</label>
                            <textarea class="form-control" name="metin" rows="3" required placeholder="Hoş geldiniz mesajı"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-select">
                                <option value="">Kategori Seçin</option>
                                <option value="genel">Genel</option>
                                <option value="rezervasyon">Rezervasyon</option>
                                <option value="hizmetler">Hizmetler</option>
                                <option value="odalar">Odalar</option>
                                <option value="admin">Admin</option>
                            </select>
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

    <!-- Çeviri Düzenleme Modal -->
    <div class="modal fade" id="editTranslationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Çeviri Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Anahtar</label>
                            <input type="text" class="form-control" id="edit_anahtar" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Çeviri Metni *</label>
                            <textarea class="form-control" name="metin" id="edit_metin" rows="3" required></textarea>
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
        function editTranslation(translation) {
            document.getElementById('edit_id').value = translation.id;
            document.getElementById('edit_anahtar').value = translation.anahtar;
            document.getElementById('edit_metin').value = translation.metin;
            
            new bootstrap.Modal(document.getElementById('editTranslationModal')).show();
        }
    </script>
</body>
</html>
