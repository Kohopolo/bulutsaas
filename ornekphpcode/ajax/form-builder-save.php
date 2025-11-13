<?php
/**
 * Form Builder - Kaydet
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

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$formId = $_POST['form_id'] ?? null;
$formName = trim($_POST['form_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$isActive = $_POST['is_active'] ?? 0;
$notificationEmail = trim($_POST['notification_email'] ?? '');
$formFields = $_POST['form_fields'] ?? '[]';

// Validasyon
if (empty($formName)) {
    echo json_encode(['success' => false, 'message' => 'Form adı gerekli!']);
    exit;
}

$isNew = empty($formId);

if ($isNew && !hasDetailedPermission('form_builder_create')) {
    echo json_encode(['success' => false, 'message' => 'Form oluşturma yetkiniz yok!']);
    exit;
}

if (!$isNew && !hasDetailedPermission('form_builder_edit')) {
    echo json_encode(['success' => false, 'message' => 'Form düzenleme yetkiniz yok!']);
    exit;
}

try {
    if ($isNew) {
        // Yeni form oluştur
        $stmt = $pdo->prepare("
            INSERT INTO custom_forms 
            (form_name, description, form_fields, is_active, notification_email, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $formName,
            $description,
            $formFields,
            $isActive,
            $notificationEmail
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Form başarıyla oluşturuldu!',
            'form_id' => $pdo->lastInsertId()
        ]);
        
    } else {
        // Mevcut formu güncelle
        $stmt = $pdo->prepare("
            UPDATE custom_forms 
            SET form_name = ?,
                description = ?,
                form_fields = ?,
                is_active = ?,
                notification_email = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $formName,
            $description,
            $formFields,
            $isActive,
            $notificationEmail,
            $formId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Form başarıyla güncellendi!',
            'form_id' => $formId
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}


