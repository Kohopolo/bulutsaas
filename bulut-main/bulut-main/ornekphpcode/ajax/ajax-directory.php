<?php
// AJAX dizini için güvenlik kontrolü
if (!defined('AJAX_REQUEST')) {
    define('AJAX_REQUEST', true);
}

// Güvenlik başlıkları
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// JSON response için content type
header('Content-Type: application/json; charset=utf-8');

// Hata raporlamayı kapat (production için)
error_reporting(0);
ini_set('display_errors', 0);
?>