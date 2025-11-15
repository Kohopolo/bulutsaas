<?php
/**
 * Dinamik değişken kaydet
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$variableName = trim($_POST['variable_name'] ?? '');
$variableTitle = trim($_POST['variable_title'] ?? '');
$variableDescription = trim($_POST['variable_description'] ?? '');
$variableType = $_POST['variable_type'] ?? 'text';
$variableContent = $_POST['variable_content'] ?? '';

// Validasyon
if (empty($variableName) || empty($variableTitle) || empty($variableContent)) {
    echo json_encode(['success' => false, 'message' => 'Gerekli alanlar boş olamaz!']);
    exit;
}

// Değişken adı validasyonu (sadece harf, rakam, alt çizgi)
if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variableName)) {
    echo json_encode(['success' => false, 'message' => 'Değişken adı sadece harf, rakam ve alt çizgi içerebilir!']);
    exit;
}

try {
    // Aynı isimde değişken var mı kontrol et
    $stmt = $pdo->prepare("SELECT id FROM dynamic_variables WHERE variable_name = ?");
    $stmt->execute([$variableName]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bu değişken adı zaten kullanılıyor!']);
        exit;
    }
    
    // Değişkeni kaydet
    $stmt = $pdo->prepare("
        INSERT INTO dynamic_variables 
        (variable_name, variable_title, variable_description, variable_type, variable_content, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");
    
    $stmt->execute([
        $variableName,
        $variableTitle,
        $variableDescription,
        $variableType,
        $variableContent,
        $_SESSION['user_id']
    ]);
    
    $variableId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Değişken başarıyla oluşturuldu!',
        'variable_id' => $variableId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>

