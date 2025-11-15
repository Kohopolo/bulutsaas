<?php
/**
 * Menü Yöneticisi - Drag & Drop Menü Düzenleme
 */

session_start();
require_once '../config/database.php';
require_once '../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!hasDetailedPermission('page_builder_view')) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Menü Yöneticisi';

// Menü öğelerini çek
$stmt = $pdo->query("
    SELECT 
        mi.*,
        cp.page_title as page_title,
        cp.page_slug as page_slug
    FROM menu_items mi
    LEFT JOIN page_menu_relations pmr ON mi.id = pmr.menu_id
    LEFT JOIN custom_pages cp ON pmr.page_id = cp.id
    ORDER BY mi.menu_order ASC, mi.id ASC
");

$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktif sayfaları çek
$stmt = $pdo->query("
    SELECT id, page_title, page_slug 
    FROM custom_pages 
    WHERE is_active = 1 
    ORDER BY page_title ASC
");
$activePages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        #content {
            flex: 1;
            margin-left: 250px;
        }
        .main-content {
            padding: 20px;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .menu-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .menu-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
            border-color: #667eea;
        }
        .menu-item.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        .menu-item .drag-handle {
            cursor: grab;
            color: #6c757d;
            font-size: 18px;
            padding: 5px;
        }
        .menu-item .drag-handle:active {
            cursor: grabbing;
        }
        .menu-item .drag-handle:hover {
            color: #667eea;
        }
        .sortable-list {
            min-height: 100px;
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
        }
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .toast-container {
            z-index: 9999;
        }
        .badge {
            font-size: 0.75em;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php if (file_exists('includes/sidebar.php')) include 'includes/sidebar.php'; ?>
        <div id="content">
            <?php if (file_exists('includes/header.php')) include 'includes/header.php'; ?>
            <div class="main-content">
                <div class="container-fluid">
                    <!-- Başlık -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h3 mb-2"><i class="fas fa-bars"></i> Menü Yöneticisi</h1>
                                    <p class="text-muted">Menü öğelerini sürükleyip bırakarak düzenleyin</p>
                                </div>
                                <div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                                        <i class="fas fa-plus"></i> Yeni Menü Öğesi
                                    </button>
                                    <button class="btn btn-success" onclick="saveMenuOrder()">
                                        <i class="fas fa-save"></i> Sıralamayı Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menü Listesi -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> Menü Öğeleri</h5>
                                </div>
                                <div class="card-body">
                                    <div id="menuList" class="sortable-list">
                                        <?php if (empty($menuItems)): ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="fas fa-bars fa-3x mb-3"></i>
                                            <p>Henüz menü öğesi eklenmemiş.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                                                <i class="fas fa-plus"></i> İlk Menü Öğesini Ekle
                                            </button>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($menuItems as $item): ?>
                                            <div class="menu-item" data-id="<?php echo $item['id']; ?>" data-order="<?php echo $item['menu_order']; ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="drag-handle me-3">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($item['icon']): ?>
                                                            <i class="<?php echo $item['icon']; ?> me-2 text-primary"></i>
                                                            <?php endif; ?>
                                                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                            <span class="badge bg-secondary ms-2"><?php echo $item['menu_order']; ?></span>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <?php if ($item['page_title']): ?>
                                                                📄 Sayfa: <?php echo htmlspecialchars($item['page_title']); ?>
                                                            <?php else: ?>
                                                                🔗 URL: <?php echo htmlspecialchars($item['url']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="editMenuItem(<?php echo $item['id']; ?>)" title="Düzenle">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteMenuItem(<?php echo $item['id']; ?>)" title="Sil">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Menü Önizleme -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-eye"></i> Menü Önizleme</h5>
                                </div>
                                <div class="card-body">
                                    <div id="menuPreview">
                                        <div class="preview-container">
                                            <h6 class="text-muted mb-3">Menü Önizleme:</h6>
                                            <nav class="navbar navbar-expand-lg navbar-light bg-white border rounded">
                                                <div class="container-fluid">
                                                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                                                        <?php if (empty($menuItems)): ?>
                                                        <li class="nav-item">
                                                            <span class="nav-link text-muted">Henüz menü öğesi yok</span>
                                                        </li>
                                                        <?php else: ?>
                                                            <?php foreach ($menuItems as $item): ?>
                                                            <li class="nav-item">
                                                                <a class="nav-link" href="<?php echo $item['url'] ?: '/page/' . $item['slug']; ?>">
                                                                    <?php if ($item['icon']): ?>
                                                                    <i class="<?php echo $item['icon']; ?> me-1"></i>
                                                                    <?php endif; ?>
                                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                                </a>
                                                            </li>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menü Ekleme Modal -->
    <div class="modal fade" id="addMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Menü Öğesi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="menuForm">
                        <div class="mb-3">
                            <label class="form-label">Menü Başlığı</label>
                            <input type="text" class="form-control" id="menuTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Menü Tipi</label>
                            <select class="form-select" id="menuType" onchange="toggleMenuType()">
                                <option value="url">URL</option>
                                <option value="page">Sayfa</option>
                            </select>
                        </div>
                        <div class="mb-3" id="urlField">
                            <label class="form-label">URL</label>
                            <input type="text" class="form-control" id="menuUrl" placeholder="/sayfa-adi">
                        </div>
                        <div class="mb-3" id="pageField" style="display: none;">
                            <label class="form-label">Sayfa Seç</label>
                            <select class="form-select" id="menuPage">
                                <option value="">Sayfa seçin</option>
                                <?php foreach ($activePages as $page): ?>
                                <option value="<?php echo $page['id']; ?>"><?php echo htmlspecialchars($page['page_title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İkon (Font Awesome)</label>
                            <input type="text" class="form-control" id="menuIcon" placeholder="fas fa-home">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sıra</label>
                            <input type="number" class="form-control" id="menuOrder" value="0">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="menuActive" checked>
                                <label class="form-check-label">Aktif</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="menuFooter">
                                <label class="form-check-label">Footer'da Göster</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="saveMenuItem()">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <script>
        let sortable;
        let editingMenuItemId = null;

        $(document).ready(function() {
            // Sortable başlat
            sortable = new Sortable(document.getElementById('menuList'), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'dragging',
                onEnd: function(evt) {
                    updateMenuOrder();
                }
            });
        });

        // Menü tipi değiştiğinde
        function toggleMenuType() {
            const menuType = document.getElementById('menuType').value;
            const urlField = document.getElementById('urlField');
            const pageField = document.getElementById('pageField');
            
            if (menuType === 'url') {
                urlField.style.display = 'block';
                pageField.style.display = 'none';
            } else {
                urlField.style.display = 'none';
                pageField.style.display = 'block';
            }
        }

        // Menü öğesi kaydet
        function saveMenuItem() {
            const formData = {
                title: document.getElementById('menuTitle').value,
                type: document.getElementById('menuType').value,
                url: document.getElementById('menuUrl').value,
                page_id: document.getElementById('menuPage').value,
                icon: document.getElementById('menuIcon').value,
                order: document.getElementById('menuOrder').value,
                active: document.getElementById('menuActive').checked ? 1 : 0,
                footer: document.getElementById('menuFooter').checked ? 1 : 0,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            };

            if (editingMenuItemId) {
                formData.id = editingMenuItemId;
            }

            $.post('ajax/menu-save.php', formData).done(function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    location.reload();
                } else {
                    showToast('error', response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Menü öğesi düzenle
        function editMenuItem(id) {
            // AJAX ile menü öğesi bilgilerini al ve modal'ı doldur
            $.post('ajax/menu-get.php', {
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            }).done(function(response) {
                if (response.success) {
                    const item = response.menu;
                    document.getElementById('menuTitle').value = item.title;
                    document.getElementById('menuIcon').value = item.icon || '';
                    document.getElementById('menuOrder').value = item.menu_order;
                    document.getElementById('menuActive').checked = item.is_active == 1;
                    document.getElementById('menuFooter').checked = item.is_in_footer == 1;
                    
                    editingMenuItemId = id;
                    
                    const modal = new bootstrap.Modal(document.getElementById('addMenuModal'));
                    modal.show();
                } else {
                    showToast('error', response.message);
                }
            });
        }

        // Menü öğesi sil
        function deleteMenuItem(id) {
            if (confirm('Bu menü öğesini silmek istediğinizden emin misiniz?')) {
                $.post('ajax/menu-delete.php', {
                    id: id,
                    csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
                }).done(function(response) {
                    if (response.success) {
                        showToast('success', response.message);
                        location.reload();
                    } else {
                        showToast('error', response.message);
                    }
                }).fail(function() {
                    showToast('error', 'Sunucu hatası!');
                });
            }
        }

        // Menü sıralamasını güncelle
        function updateMenuOrder() {
            const items = document.querySelectorAll('#menuList .menu-item');
            items.forEach((item, index) => {
                item.setAttribute('data-order', index + 1);
            });
        }

        // Menü sıralamasını kaydet
        function saveMenuOrder() {
            const items = document.querySelectorAll('#menuList .menu-item');
            const orderData = [];
            
            items.forEach((item, index) => {
                orderData.push({
                    id: item.getAttribute('data-id'),
                    order: index + 1
                });
            });

            $.post('ajax/menu-save-order.php', {
                items: orderData,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            }).done(function(response) {
                if (response.success) {
                    showToast('success', 'Menü sıralaması kaydedildi!');
                } else {
                    showToast('error', response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Toast bildirim
        function showToast(type, message) {
            const toast = $(`
                <div class="toast" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-circle text-danger'} me-2"></i>
                        <strong class="me-auto">${type === 'success' ? 'Başarılı' : 'Hata'}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `);
            
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
            bsToast.show();
            toast.on('hidden.bs.toast', function() { $(this).remove(); });
        }
    </script>
</body>
</html>
