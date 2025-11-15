<?php
// CSRF Koruması - Tüm admin dosyalarında kullanılacak
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// CSRF token oluştur ve kontrol et
function initCSRFProtection() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // 1 saat geçerlilik
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
}

// Form için CSRF token input'u oluştur
function csrfTokenInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// CSRF token doğrulama fonksiyonu security.php'de tanımlı

// POST isteklerinde CSRF kontrolü yap
function checkCSRFOnPost() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token geçersiz. Lütfen sayfayı yenileyin ve tekrar deneyin.');
        }
    }
}

// Admin yetkisi kontrolü
function requireAdminAuth() {
    if (!checkLogin()) {
        // AJAX isteği mi kontrol et
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.']);
            exit;
        }
        
        header('Location: login.php');
        exit;
    }
    
    // Admin, superadmin ve personel rolleri admin paneline erişebilir
    if (!in_array($_SESSION['user_role'], ['admin', 'superadmin', 'personel', 'sales', 'ekip', 'housekeeper', 'housekeeper_manager'])) {
        $mesaj = "Admin paneline erişim yetkiniz bulunmamaktadır.";
        
        // AJAX isteği mi kontrol et
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $mesaj]);
            exit;
        }
        
        // Normal sayfa isteği - Özel alert ile göster
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Yetki Hatası</title>
            <style>
                .permission-alert {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: #fee;
                    border: 2px solid #fcc;
                    border-radius: 10px;
                    padding: 20px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    z-index: 9999;
                    max-width: 400px;
                    text-align: center;
                    font-family: Arial, sans-serif;
                }
                .permission-alert h3 {
                    color: #c33;
                    margin: 0 0 10px 0;
                }
                .permission-alert p {
                    color: #666;
                    margin: 0 0 15px 0;
                }
                .permission-alert button {
                    background: #c33;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                }
                .permission-alert button:hover {
                    background: #a22;
                }
            </style>
        </head>
        <body>
            <div class='permission-alert'>
                <h3>⚠️ Yetki Hatası</h3>
                <p>$mesaj</p>
                <button onclick='window.history.back()'>Geri Dön</button>
            </div>
            <script>
                setTimeout(() => {
                    // Native alert devre dışı - sadece özel alert gösteriliyor
                }, 100);
            </script>
        </body>
        </html>";
        exit;
    }
}

// CSRF korumasını başlat
initCSRFProtection();
?>