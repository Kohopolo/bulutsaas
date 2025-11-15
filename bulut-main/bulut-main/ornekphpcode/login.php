
<?php
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenli session başlat
startSecureSession();

// Zaten giriş yapmışsa admin paneline yönlendir
if (checkLogin()) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$debug_info = '';

if ($_POST) {
    $email = sanitizeString($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $debug_info .= "Email: " . $email . "<br>";
    $debug_info .= "Password length: " . strlen($password) . "<br>";
    
    if (empty($email) || empty($password)) {
        $error_message = 'E-posta ve şifre alanları zorunludur.';
    } else {
        $login_result = loginUser($email, $password);
        $debug_info .= "Login result: " . ($login_result ? 'true' : 'false') . "<br>";
        $debug_info .= "Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
        $debug_info .= "Session user_role: " . ($_SESSION['user_role'] ?? 'not set') . "<br>";
        $debug_info .= "checkLogin(): " . (checkLogin() ? 'true' : 'false') . "<br>";
        $debug_info .= "checkAdmin(): " . (checkAdmin() ? 'true' : 'false') . "<br>";
        
        if ($login_result) {
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'Geçersiz e-posta veya şifre.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Girişi - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background: transparent;
            border-right: none;
        }
        .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .input-group-text + .form-control {
            border-radius: 10px 0 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card border-0">
                    <div class="card-header login-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-hotel me-2"></i>
                            Admin Paneli
                        </h3>
                        <p class="mb-0 mt-2 opacity-75">Otel Yönetim Sistemi</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if ($debug_info && $_POST): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <strong>Debug Bilgileri:</strong><br>
                            <?php echo $debug_info; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta Adresi</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="admin@otel.com" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="••••••••" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    Beni hatırla
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Giriş Yap
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <small class="text-muted">
                                Demo Hesaplar:<br>
                                <strong>SuperAdmin:</strong> superadmin@otel.com / password<br>
                                <strong>Admin:</strong> admin@otel.com / password
                            </small>
                        </div>
                    </div>
                    <div class="card-footer text-center py-3 bg-transparent border-0">
                        <a href="../index.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>
                            Ana Sayfaya Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şifre göster/gizle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validasyonu
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Lütfen tüm alanları doldurun.');
            }
        });
    </script>
</body>
</html>
