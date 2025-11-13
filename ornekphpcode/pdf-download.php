<?php
// PDF Download Endpoint
// Bu dosya PDF indirme işlemlerini yönetir

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/pdf-generator.php';

// Admin kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Yetkisiz erişim');
}

// CSRF token kontrolü
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    die('Güvenlik hatası');
}

try {
    $pdf_generator = new PDFGenerator($pdo);
    $result = null;
    $filename = '';
    
    // PDF türüne göre işlem yap
    if (isset($_POST['voucher']) && isset($_POST['rezervasyon_id'])) {
        // Rezervasyon Voucher
        $rezervasyon_id = (int)$_POST['rezervasyon_id'];
        $result = $pdf_generator->generateReservationVoucher($rezervasyon_id, $_SESSION['admin_id']);
        $filename = 'voucher_' . $rezervasyon_id . '_' . date('Y-m-d') . '.pdf';
        
        // Güvenlik logu
        logSecurityEvent('PDF_VOUCHER_GENERATED', [
            'admin_id' => $_SESSION['admin_id'],
            'rezervasyon_id' => $rezervasyon_id,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
    } elseif (isset($_POST['contract']) && isset($_POST['rezervasyon_id'])) {
        // Konaklama Sözleşmesi
        $rezervasyon_id = (int)$_POST['rezervasyon_id'];
        $result = $pdf_generator->generateContract($rezervasyon_id, $_SESSION['admin_id']);
        $filename = 'sozlesme_' . $rezervasyon_id . '_' . date('Y-m-d') . '.pdf';
        
        // Güvenlik logu
        logSecurityEvent('PDF_CONTRACT_GENERATED', [
            'admin_id' => $_SESSION['admin_id'],
            'rezervasyon_id' => $rezervasyon_id,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
    } elseif (isset($_POST['receipt']) && isset($_POST['odeme_id'])) {
        // Ödeme Makbuzu
        $odeme_id = (int)$_POST['odeme_id'];
        try {
            $pdf_content = $pdf_generator->generatePaymentReceipt($odeme_id);
            $result = [
                'success' => true,
                'pdf_content' => $pdf_content
            ];
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        $filename = 'makbuz_' . $odeme_id . '_' . date('Y-m-d') . '.pdf';
        
        // Güvenlik logu
        logSecurityEvent('PDF_RECEIPT_GENERATED', [
            'admin_id' => $_SESSION['admin_id'],
            'odeme_id' => $odeme_id,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
    } else {
        throw new Exception('Geçersiz PDF türü');
    }
    
    // Sonucu kontrol et
    if (!$result || !$result['success']) {
        throw new Exception($result['error'] ?? 'PDF oluşturulamadı');
    }
    
    $pdf_content = $result['pdf_content'];
    
    // PDF'i inline olarak göster (yeni sekmede açılır)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf_content;
    
} catch (Exception $e) {
    // Hata logu
    logSecurityEvent('PDF_GENERATION_ERROR', [
        'admin_id' => $_SESSION['admin_id'],
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    http_response_code(500);
    die('PDF oluşturulurken hata: ' . $e->getMessage());
}
?>