<?php
// C:\xampp\htdocs\otelonofexe\web\admin\ajax\get-ssl-details.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('odeme_guvenlik_ayarlari')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ödeme güvenlik ayarları yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF token kontrolü
if (!isset($_POST['log_id']) || !is_numeric($_POST['log_id'])) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz log ID']);
    exit;
}

$log_id = (int)$_POST['log_id'];

try {
    // SSL log detaylarını getir
    $log = fetchOne("
        SELECT ssl.*, p.provider_adi, p.base_url 
        FROM odeme_ssl_loglari ssl 
        LEFT JOIN odeme_providerlari p ON ssl.provider_id = p.id 
        WHERE ssl.id = ?
    ", [$log_id]);
    
    if (!$log) {
        echo json_encode(['success' => false, 'error' => 'Log bulunamadı']);
        exit;
    }
    
    // Sertifika bilgilerini decode et
    $certificate_info = json_decode($log['certificate_info'], true);
    
    $html = '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<h6>Genel Bilgiler</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>Sağlayıcı:</strong></td><td>' . htmlspecialchars($log['provider_adi']) . '</td></tr>';
    $html .= '<tr><td><strong>URL:</strong></td><td>' . htmlspecialchars($log['url']) . '</td></tr>';
    $html .= '<tr><td><strong>Durum:</strong></td><td>';
    $html .= '<span class="badge bg-' . ($log['ssl_valid'] ? 'success' : 'danger') . '">';
    $html .= $log['ssl_valid'] ? 'Geçerli' : 'Geçersiz';
    $html .= '</span></td></tr>';
    $html .= '<tr><td><strong>Kontrol Tarihi:</strong></td><td>' . date('d.m.Y H:i:s', strtotime($log['kontrol_tarihi'])) . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';
    
    if ($log['ssl_valid'] && $certificate_info) {
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Sertifika Detayları</h6>';
        $html .= '<table class="table table-sm">';
        
        if (isset($certificate_info['subject'])) {
            $html .= '<tr><td><strong>Konu:</strong></td><td>' . htmlspecialchars($certificate_info['subject']['CN'] ?? 'N/A') . '</td></tr>';
        }
        if (isset($certificate_info['issuer'])) {
            $html .= '<tr><td><strong>Yayıncı:</strong></td><td>' . htmlspecialchars($certificate_info['issuer']['CN'] ?? 'N/A') . '</td></tr>';
        }
        if (isset($certificate_info['valid_from'])) {
            $html .= '<tr><td><strong>Geçerlilik Başlangıcı:</strong></td><td>' . htmlspecialchars($certificate_info['valid_from']) . '</td></tr>';
        }
        if (isset($certificate_info['valid_to'])) {
            $html .= '<tr><td><strong>Geçerlilik Bitişi:</strong></td><td>' . htmlspecialchars($certificate_info['valid_to']) . '</td></tr>';
        }
        if (isset($certificate_info['serial_number'])) {
            $html .= '<tr><td><strong>Seri Numarası:</strong></td><td>' . htmlspecialchars($certificate_info['serial_number']) . '</td></tr>';
        }
        if (isset($certificate_info['signature_algorithm'])) {
            $html .= '<tr><td><strong>İmza Algoritması:</strong></td><td>' . htmlspecialchars($certificate_info['signature_algorithm']) . '</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
    } else {
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Hata Detayları</h6>';
        $html .= '<div class="alert alert-danger">';
        $html .= '<strong>Hata:</strong> ' . htmlspecialchars($log['error_message'] ?? 'Bilinmeyen hata');
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Sertifika süresi kontrolü
    if ($log['ssl_valid'] && isset($certificate_info['valid_to'])) {
        $valid_to = strtotime($certificate_info['valid_to']);
        $now = time();
        $days_remaining = floor(($valid_to - $now) / (60 * 60 * 24));
        
        $html .= '<div class="row mt-3">';
        $html .= '<div class="col-12">';
        $html .= '<div class="alert alert-' . ($days_remaining < 30 ? 'warning' : 'info') . '">';
        $html .= '<i class="fas fa-clock me-2"></i>';
        if ($days_remaining > 0) {
            $html .= '<strong>Sertifika Süresi:</strong> ' . $days_remaining . ' gün kaldı';
            if ($days_remaining < 30) {
                $html .= ' <span class="badge bg-warning">Yakında süresi dolacak!</span>';
            }
        } else {
            $html .= '<strong>Sertifika Süresi:</strong> <span class="text-danger">Süresi dolmuş!</span>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    error_log("SSL detayları getirme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası']);
}
?>
