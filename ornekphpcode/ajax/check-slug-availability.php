<?php
/**
 * Slug Kullanılabilirlik Kontrolü
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$pageId = $_POST['page_id'] ?? null;

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Başlık gerekli!']);
    exit;
}

try {
    // Slug oluştur
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = str_replace(
        ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'],
        ['c', 'g', 'i', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'],
        $slug
    );
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Benzersizlik kontrolü
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $query = "SELECT id FROM custom_pages WHERE page_slug = ?";
        $params = [$slug];
        
        if ($pageId) {
            $query .= " AND id != ?";
            $params[] = $pageId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    echo json_encode([
        'success' => true,
        'slug' => $slug,
        'is_original' => ($slug === $originalSlug),
        'message' => $slug === $originalSlug ? 'Slug kullanılabilir' : 'Slug otomatik olarak düzeltildi'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

