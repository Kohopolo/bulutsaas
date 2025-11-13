<?php
/**
 * Sayfa Listesi
 */

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/detailed_permission_functions.php';
require_once '../config/database.php';

// Yetki kontrolü
if (!hasDetailedPermission('page_builder_view')) {
    header('Location: ../error/403.php');
    exit;
}

$pageTitle = "Sayfa Yönetimi";
$current_page = basename($_SERVER['PHP_SELF']);

// Sayfaları çek
$stmt = $pdo->query("
    SELECT 
        cp.*,
        cp.page_title as title,
        cp.page_slug as slug,
        cp.page_content as content_html,
        cp.is_active as status,
        cp.page_template as template,
        (SELECT COUNT(*) FROM page_analytics WHERE page_id = cp.id) as total_views
    FROM custom_pages cp
    ORDER BY cp.page_template ASC, cp.updated_at DESC
");

$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if (file_exists('assets/css/admin.css')): ?>
    <link href="assets/css/admin.css" rel="stylesheet">
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
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
                                <h1 class="h3 mb-2"><i class="fas fa-file-alt"></i> Sayfa Yönetimi</h1>
                                <p class="text-muted">Özel sayfalarınızı oluşturun ve yönetin</p>
                            </div>
                            <?php if (hasDetailedPermission('page_builder_create')): ?>
                            <div>
                                <a href="page-builder-v2.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Yeni Sayfa Oluştur
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Template Filtreleme -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6><i class="fas fa-filter"></i> Template Filtresi</h6>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-primary active" onclick="filterTemplate('all')">
                                        <i class="fas fa-th"></i> Tümü
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" onclick="filterTemplate('default')">
                                        <i class="fas fa-palette"></i> Default
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="filterTemplate('elegant-hotel')">
                                        <i class="fas fa-palette"></i> Elegant Hotel
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" onclick="filterTemplate('luxury-hotel')">
                                        <i class="fas fa-palette"></i> Luxury Hotel
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="filterTemplate('modern-hotel')">
                                        <i class="fas fa-palette"></i> Modern Hotel
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="filterTemplate('premium-hotel')">
                                        <i class="fas fa-palette"></i> Premium Hotel
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="filterTemplate('custom')">
                                        <i class="fas fa-edit"></i> Özel Sayfalar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sayfa Listesi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Başlık</th>
                                                <th>Template</th>
                                                <th>Slug</th>
                                                <th>Durum</th>
                                                <th>Görüntülenme</th>
                                                <th>Oluşturan</th>
                                                <th>Güncelleme</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($pages)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p>Henüz sayfa oluşturulmamış.</p>
                                                    <?php if (hasDetailedPermission('page_builder_create')): ?>
                                                    <a href="page-builder-ultimate-v3.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> İlk Sayfayı Oluştur
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                        <?php foreach ($pages as $page): ?>
                                                        <tr class="page-row" data-template="<?php echo $page['template'] ?: 'custom'; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($page['title']); ?></strong>
                                                        <?php if ($page['template']): ?>
                                                            <br><small class="text-muted">
                                                                <i class="fas fa-folder"></i> 
                                                                <?php echo ucwords(str_replace('-', ' ', $page['template'])); ?> Template
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $template = $page['template'] ?: 'custom';
                                                        $templateColors = [
                                                            'default' => 'primary',
                                                            'elegant-hotel' => 'success', 
                                                            'luxury-hotel' => 'warning',
                                                            'modern-hotel' => 'info',
                                                            'premium-hotel' => 'danger',
                                                            'custom' => 'secondary'
                                                        ];
                                                        $color = $templateColors[$template] ?? 'secondary';
                                                        $templateName = ($template === 'custom') ? 'Özel Sayfa' : ucwords(str_replace('-', ' ', $template));
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>">
                                                            <i class="fas fa-<?php echo ($template === 'custom') ? 'edit' : 'palette'; ?>"></i> <?php echo $templateName; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($page['slug']); ?></code>
                                                    </td>
                                                    <td>
                                                        <?php if ($page['status'] == 1): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check"></i> Yayında
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-clock"></i> Taslak
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-eye text-muted"></i> 
                                                        <?php echo number_format($page['total_views']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($page['created_by_name'] ?? 'Bilinmiyor'); ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d.m.Y H:i', strtotime($page['updated_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if ($page['status'] === 'published'): ?>
                                                            <a href="../page/<?php echo htmlspecialchars($page['slug']); ?>" 
                                                               class="btn btn-info" target="_blank" title="Görüntüle">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (hasDetailedPermission('page_builder_edit')): ?>
                                                            <a href="page-builder-ultimate-v3.php?page_id=<?php echo $page['id']; ?>" 
                                                               class="btn btn-warning" title="Düzenle">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (hasDetailedPermission('page_builder_delete')): ?>
                                                            <button class="btn btn-danger" 
                                                                    onclick="deletePage(<?php echo $page['id']; ?>)" 
                                                                    title="Sil">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
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
    </div>

<script>
const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

function deletePage(pageId) {
    if (!confirm('Bu sayfayı silmek istediğinize emin misiniz?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/page-builder-delete.php',
        method: 'POST',
        data: {
            page_id: pageId,
            csrf_token: csrfToken
        },
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.message);
                location.reload();
            } else {
                alert('❌ ' + response.message);
            }
        },
        error: function() {
            alert('❌ Sunucu hatası!');
        }
    });
}

// Template filtreleme fonksiyonu
function filterTemplate(template) {
    // Tüm butonları pasif yap
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Seçilen butonu aktif yap
    event.target.classList.add('active');
    
    // Tüm satırları göster
    document.querySelectorAll('.page-row').forEach(row => {
        row.style.display = '';
    });
    
    // Eğer "Tümü" seçilmediyse filtrele
    if (template !== 'all') {
        document.querySelectorAll('.page-row').forEach(row => {
            const rowTemplate = row.getAttribute('data-template');
            if (rowTemplate !== template) {
                row.style.display = 'none';
            }
        });
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

