<?php
/**
 * Resim Yükleme Endpoint
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

$canUpload = hasDetailedPermission('page_builder_edit');
if (!$canUpload) {
    echo json_encode(['success' => false, 'message' => 'Resim yükleme yetkiniz yok!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

// Dosya kontrolü
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Dosya yüklenemedi!']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Dosya tipi kontrolü
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz dosya tipi! Sadece JPG, PNG, GIF, WebP desteklenir.']);
    exit;
}

// Dosya boyutu kontrolü
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Dosya çok büyük! Maksimum 5MB olmalı.']);
    exit;
}

try {
    // Uploads dizinini oluştur
    $uploadDir = '../../uploads/page-builder/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Benzersiz dosya adı oluştur
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('img_', true) . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Veritabanına kaydet
        $stmt = $pdo->prepare("
            INSERT INTO page_images 
            (original_name, file_name, file_path, file_size, file_type, uploaded_by, uploaded_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $relativePath = 'uploads/page-builder/' . $fileName;
        $stmt->execute([
            $file['name'],
            $fileName,
            $relativePath,
            $file['size'],
            $file['type'],
            $_SESSION['user_id']
        ]);
        
        $imageId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Resim başarıyla yüklendi!',
            'data' => [
                'id' => $imageId,
                'url' => $relativePath,
                'name' => $file['name'],
                'size' => $file['size']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dosya kaydedilemedi!']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

