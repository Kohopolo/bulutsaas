<?php
/**
 * Resim Listesi Endpoint
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

$canView = hasDetailedPermission('page_builder_edit');
if (!$canView) {
    echo json_encode(['success' => false, 'message' => 'Resim görüntüleme yetkiniz yok!']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT id, original_name, file_name, file_path, file_size, file_type, uploaded_at 
        FROM page_images 
        ORDER BY uploaded_at DESC 
        LIMIT 50
    ");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Template resimlerini de ekle
    $templateImages = [
        [
            'id' => 'template_logo',
            'original_name' => 'Logo',
            'file_name' => 'logo.svg',
            'file_path' => 'templates/premium-hotel/assets/images/logo.svg',
            'file_type' => 'image/svg+xml',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_logo2',
            'original_name' => 'Logo 2',
            'file_name' => 'logo-2.svg',
            'file_path' => 'templates/premium-hotel/assets/images/logo-2.svg',
            'file_type' => 'image/svg+xml',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_avatar1',
            'original_name' => 'Avatar 1',
            'file_name' => 'avatar-1.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/avatar-1.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_avatar2',
            'original_name' => 'Avatar 2',
            'file_name' => 'avatar-2.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/avatar-2.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_avatar3',
            'original_name' => 'Avatar 3',
            'file_name' => 'avatar-3.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/avatar-3.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_avatar4',
            'original_name' => 'Avatar 4',
            'file_name' => 'avatar-4.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/avatar-4.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_flower',
            'original_name' => 'Flower',
            'file_name' => 'flower.svg',
            'file_path' => 'templates/premium-hotel/assets/images/flower.svg',
            'file_type' => 'image/svg+xml',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_page_header',
            'original_name' => 'Page Header',
            'file_name' => 'page-header-5.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/page-header-5.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_testimonial_bg',
            'original_name' => 'Testimonial Background',
            'file_name' => 'testimonial-bg-3.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/testimonial-bg-3.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_video_bg1',
            'original_name' => 'Video Background 1',
            'file_name' => 'video-bg-1.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/video-bg-1.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_video_bg2',
            'original_name' => 'Video Background 2',
            'file_name' => 'video-bg-2.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/video-bg-2.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_video_bg3',
            'original_name' => 'Video Background 3',
            'file_name' => 'video-bg-3.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/video-bg-3.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 'template_video_bg4',
            'original_name' => 'Video Background 4',
            'file_name' => 'video-bg-4.jpg',
            'file_path' => 'templates/premium-hotel/assets/images/video-bg-4.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Template resimlerini başa ekle
    $allImages = array_merge($templateImages, $images);
    
    echo json_encode([
        'success' => true,
        'images' => $allImages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

