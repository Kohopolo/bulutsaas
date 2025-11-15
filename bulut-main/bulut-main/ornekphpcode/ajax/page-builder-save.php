<?php
/**
 * Sayfa Kaydet
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

$canCreate = hasDetailedPermission('page_builder_create');
$canEdit = hasDetailedPermission('page_builder_edit');
$canPublish = hasDetailedPermission('page_builder_publish');

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$pageId = $_POST['page_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$html = $_POST['html'] ?? '';
$css = $_POST['css'] ?? '';
$status = $_POST['status'] ?? 'draft';
$template = $_POST['template'] ?? '';
$menuSettings = $_POST['menu_settings'] ?? '{}';

// Validasyon
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Sayfa başlığı gerekli!']);
    exit;
}

// Yeni sayfa mı, düzenleme mi?
$isNew = empty($pageId);

if ($isNew && !$canCreate) {
    echo json_encode(['success' => false, 'message' => 'Sayfa oluşturma yetkiniz yok!']);
    exit;
}

if (!$isNew && !$canEdit) {
    echo json_encode(['success' => false, 'message' => 'Sayfa düzenleme yetkiniz yok!']);
    exit;
}

if ($status === 'published' && !$canPublish) {
    echo json_encode(['success' => false, 'message' => 'Sayfa yayınlama yetkiniz yok!']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Layout wrapper kontrolü ve ekleme
    if ($template && $template !== 'custom') {
        // Layout yapısı kontrolü
        $hasLayout = strpos($html, '<header') !== false || 
                     strpos($html, '<footer') !== false || 
                     strpos($html, 'class="header') !== false || 
                     strpos($html, 'class="footer') !== false ||
                     strpos($html, 'id="header') !== false ||
                     strpos($html, 'id="footer') !== false ||
                     strpos($html, 'template-layout-wrapper') !== false;
        
        if (!$hasLayout) {
            // Layout wrapper ekle
            $html = '
                <div class="template-layout-wrapper" data-template="' . htmlspecialchars($template) . '">
                    <header class="template-header">
                        <!-- Header content will be loaded from template -->
                    </header>
                    <main class="template-content">
                        ' . $html . '
                    </main>
                    <footer class="template-footer">
                        <!-- Footer content will be loaded from template -->
                    </footer>
                </div>
            ';
        }
    }
    
    // Slug oluştur (URL-friendly) - Benzersizlik kontrolü ile
    $slug = generateSlug($title, $pdo, $pageId);
    
    if ($isNew) {
        // Yeni sayfa oluştur
        $stmt = $pdo->prepare("
            INSERT INTO custom_pages 
            (page_title, page_slug, page_content, page_template, is_active, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $title,
            $slug,
            $html,
            $template,
            ($status === 'published') ? 1 : 0,
            $_SESSION['user_id']
        ]);
        
        $pageId = $pdo->lastInsertId();
        
        // Menü ayarlarını işle
        $menuData = json_decode($menuSettings, true);
        if ($menuData && $menuData['addToMenu']) {
            $stmt = $pdo->prepare("
                INSERT INTO menu_items 
                (title, url, slug, icon, menu_order, is_active, is_in_footer, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ");
            
            $menuSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $menuData['menuTitle']));
            $menuSlug = trim($menuSlug, '-');
            
            $stmt->execute([
                $menuData['menuTitle'],
                '/' . $slug,
                $menuSlug,
                $menuData['menuIcon'] ?? '',
                $menuData['menuOrder'] ?? 0,
                $menuData['menuFooter'] ? 1 : 0
            ]);
            
            $menuId = $pdo->lastInsertId();
            
            // Sayfa-menü ilişkisini ekle
            $stmt = $pdo->prepare("INSERT INTO page_menu_relations (page_id, menu_id) VALUES (?, ?)");
            $stmt->execute([$pageId, $menuId]);
        }
        
        // Revision kaydet (geçici olarak devre dışı)
        // saveRevision($pdo, $pageId, $html, $css, $_SESSION['user_id'], 'Sayfa oluşturuldu');
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sayfa başarıyla oluşturuldu!',
            'page_id' => $pageId
        ]);
        
    } else {
        // Mevcut sayfayı güncelle
        $stmt = $pdo->prepare("
            UPDATE custom_pages 
            SET page_title = ?,
                page_slug = ?,
                page_content = ?,
                page_template = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $slug,
            $html,
            $template,
            ($status === 'published') ? 1 : 0,
            $pageId
        ]);
        
        // Revision kaydet (geçici olarak devre dışı)
        // saveRevision($pdo, $pageId, $html, $css, $_SESSION['user_id'], 'Sayfa güncellendi');
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sayfa başarıyla kaydedildi!',
            'page_id' => $pageId
        ]);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}

/**
 * URL-friendly slug oluştur
 */
function generateSlug($title, $pdo, $excludeId = null) {
    // Türkçe karakterleri dönüştür
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = str_replace(
        ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'],
        ['c', 'g', 'i', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'],
        $slug
    );
    
    // Özel karakterleri temizle
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Benzersizlik kontrolü
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $query = "SELECT id FROM custom_pages WHERE page_slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Sayfa revizyonu kaydet (geçici olarak devre dışı)
 */
function saveRevision($pdo, $pageId, $html, $css, $userId, $changeNote) {
    // Geçici olarak devre dışı - page_revisions tablosu yok
    return true;
}

