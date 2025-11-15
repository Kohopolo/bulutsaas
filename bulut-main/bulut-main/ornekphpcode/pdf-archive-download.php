<?php
// Güvenlik ve gerekli dosyaları dahil et
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/pdf-archive-manager.php';

// Admin kontrolü
requireAdmin();

// PDF ID kontrolü
$pdf_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$pdf_id) {
    http_response_code(404);
    die('PDF bulunamadı.');
}

try {
    // PDF Archive Manager'ı başlat
    $archiveManager = new PDFArchiveManager($pdo);
    
    // PDF'i oku
    $pdf_data = $archiveManager->readPDF($pdf_id);
    
    if (!$pdf_data) {
        http_response_code(404);
        die('PDF bulunamadı veya okunamadı.');
    }
    
    // PDF başlıklarını ayarla
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdf_data['file_name'] . '"');
    header('Content-Length: ' . strlen($pdf_data['content']));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // PDF içeriğini çıktıla
    echo $pdf_data['content'];
    
} catch (Exception $e) {
    http_response_code(500);
    die('PDF okuma hatası: ' . $e->getMessage());
}
?>