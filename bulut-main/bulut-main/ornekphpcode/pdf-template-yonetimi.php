<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('pdf_template_yonetimi', 'PDF template yönetimi yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// PDF template kaydetme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası!';
    } else {
        $template_type = $_POST['template_type'];
        $template_content = $_POST['template_content'];
        
        // Template'i veritabanına kaydet
        $sql = "INSERT INTO pdf_templates (type, content, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW()) 
                ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()";
        
        if (executeQuery($sql, [$template_type, $template_content])) {
            $success_message = 'Template başarıyla kaydedildi.';
        } else {
            $error_message = 'Template kaydedilirken hata oluştu.';
        }
    }
}

// Mevcut template'leri al
$voucher_template = fetchOne("SELECT * FROM pdf_templates WHERE type = 'voucher'");
$contract_template = fetchOne("SELECT * FROM pdf_templates WHERE type = 'contract'");

// Varsayılan template'ler
$default_voucher = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rezervasyon Voucher</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
        .hotel-name { font-size: 28px; font-weight: bold; color: #007bff; margin-bottom: 10px; }
        .voucher-title { font-size: 24px; color: #333; margin-bottom: 5px; }
        .info-section { margin-bottom: 25px; }
        .section-title { font-size: 18px; font-weight: bold; color: #007bff; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px; }
        .info-row { display: flex; margin-bottom: 8px; }
        .info-label { font-weight: bold; width: 150px; }
        .info-value { flex: 1; }
        .total-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .total-amount { font-size: 20px; font-weight: bold; color: #007bff; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="hotel-name">{{OTEL_ADI}}</div>
        <div class="voucher-title">REZERVASYON VOUCHER</div>
        <div class="voucher-subtitle">Voucher No: #{{REZERVASYON_ID}}</div>
    </div>
    
    <div class="info-section">
        <div class="section-title">Misafir Bilgileri</div>
        <div class="info-row">
            <div class="info-label">Ad Soyad:</div>
            <div class="info-value">{{MUSTERI_AD}} {{MUSTERI_SOYAD}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">E-posta:</div>
            <div class="info-value">{{MUSTERI_EMAIL}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Telefon:</div>
            <div class="info-value">{{MUSTERI_TELEFON}}</div>
        </div>
    </div>
    
    <div class="info-section">
        <div class="section-title">Rezervasyon Detayları</div>
        <div class="info-row">
            <div class="info-label">Giriş Tarihi:</div>
            <div class="info-value">{{GIRIS_TARIHI}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Çıkış Tarihi:</div>
            <div class="info-value">{{CIKIS_TARIHI}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Oda Tipi:</div>
            <div class="info-value">{{ODA_TIPI}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Yetişkin:</div>
            <div class="info-value">{{YETISKIN_SAYISI}} kişi</div>
        </div>
        <div class="info-row">
            <div class="info-label">Çocuk:</div>
            <div class="info-value">{{COCUK_SAYISI}} kişi</div>
        </div>
    </div>
    
    <div class="total-section">
        <div class="info-row">
            <div class="info-label">Toplam Tutar:</div>
            <div class="total-amount">{{TOPLAM_TUTAR}} TL</div>
        </div>
    </div>
</body>
</html>';

$default_contract = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Konaklama Sözleşmesi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
        .hotel-name { font-size: 24px; font-weight: bold; color: #007bff; }
        .contract-title { font-size: 20px; margin-top: 10px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .clause { margin-bottom: 15px; text-align: justify; }
        .signature-section { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature-box { width: 200px; text-align: center; border-top: 1px solid #333; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="hotel-name">{{OTEL_ADI}}</div>
        <div class="contract-title">KONAKLAMA SÖZLEŞMESİ</div>
        <div>Sözleşme No: {{REZERVASYON_ID}}</div>
    </div>
    
    <div class="section">
        <div class="section-title">TARAFLAR</div>
        <div class="clause">
            <strong>Otel:</strong> {{OTEL_ADI}}<br>
            <strong>Misafir:</strong> {{MUSTERI_AD}} {{MUSTERI_SOYAD}}<br>
            <strong>E-posta:</strong> {{MUSTERI_EMAIL}}<br>
            <strong>Telefon:</strong> {{MUSTERI_TELEFON}}
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">KONAKLAMA BİLGİLERİ</div>
        <div class="clause">
            <strong>Giriş Tarihi:</strong> {{GIRIS_TARIHI}}<br>
            <strong>Çıkış Tarihi:</strong> {{CIKIS_TARIHI}}<br>
            <strong>Oda Tipi:</strong> {{ODA_TIPI}}<br>
            <strong>Misafir Sayısı:</strong> {{YETISKIN_SAYISI}} Yetişkin, {{COCUK_SAYISI}} Çocuk<br>
            <strong>Toplam Tutar:</strong> {{TOPLAM_TUTAR}} TL
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">GENEL ŞARTLAR</div>
        <div class="clause">
            1. Check-in saati 14:00, check-out saati 12:00\'dır.
        </div>
        <div class="clause">
            2. Rezervasyon iptali check-in tarihinden 48 saat öncesine kadar ücretsizdir.
        </div>
        <div class="clause">
            3. Otel, misafirlerin güvenliği için gerekli önlemleri almakla yükümlüdür.
        </div>
        <div class="clause">
            4. Bu sözleşme Türk Hukuku\'na tabidir.
        </div>
    </div>
    
    <div class="signature-section">
        <div class="signature-box">
            <div>Otel Yetkilisi</div>
        </div>
        <div class="signature-box">
            <div>Misafir</div>
        </div>
    </div>
</body>
</html>';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Template Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-pdf me-2"></i>PDF Template Yönetimi
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Voucher Template</h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="template_type" value="voucher">
                                                    <div class="mb-3">
                                                        <label class="form-label">HTML Template:</label>
                                                        <textarea name="template_content" class="form-control" rows="15" style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($voucher_template['content'] ?? $default_voucher); ?></textarea>
                                                    </div>
                                                    <button type="submit" name="save_template" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Kaydet
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0"><i class="fas fa-file-contract me-2"></i>Sözleşme Template</h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="template_type" value="contract">
                                                    <div class="mb-3">
                                                        <label class="form-label">HTML Template:</label>
                                                        <textarea name="template_content" class="form-control" rows="15" style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($contract_template['content'] ?? $default_contract); ?></textarea>
                                                    </div>
                                                    <button type="submit" name="save_template" class="btn btn-success">
                                                        <i class="fas fa-save me-2"></i>Kaydet
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card border-info">
                                            <div class="card-header bg-info text-white">
                                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Kullanılabilir Değişkenler</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Otel Bilgileri:</h6>
                                                        <ul class="list-unstyled">
                                                            <li><code>{{OTEL_ADI}}</code> - Otel adı</li>
                                                            <li><code>{{OTEL_ADRES}}</code> - Otel adresi</li>
                                                            <li><code>{{OTEL_TELEFON}}</code> - Otel telefonu</li>
                                                            <li><code>{{OTEL_EMAIL}}</code> - Otel e-postası</li>
                                                        </ul>
                                                        
                                                        <h6>Müşteri Bilgileri:</h6>
                                                        <ul class="list-unstyled">
                                                            <li><code>{{MUSTERI_AD}}</code> - Müşteri adı</li>
                                                            <li><code>{{MUSTERI_SOYAD}}</code> - Müşteri soyadı</li>
                                                            <li><code>{{MUSTERI_EMAIL}}</code> - Müşteri e-postası</li>
                                                            <li><code>{{MUSTERI_TELEFON}}</code> - Müşteri telefonu</li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Rezervasyon Bilgileri:</h6>
                                                        <ul class="list-unstyled">
                                                            <li><code>{{REZERVASYON_ID}}</code> - Rezervasyon ID</li>
                                                            <li><code>{{GIRIS_TARIHI}}</code> - Giriş tarihi</li>
                                                            <li><code>{{CIKIS_TARIHI}}</code> - Çıkış tarihi</li>
                                                            <li><code>{{ODA_TIPI}}</code> - Oda tipi</li>
                                                            <li><code>{{YETISKIN_SAYISI}}</code> - Yetişkin sayısı</li>
                                                            <li><code>{{COCUK_SAYISI}}</code> - Çocuk sayısı</li>
                                                            <li><code>{{TOPLAM_TUTAR}}</code> - Toplam tutar</li>
                                                            <li><code>{{DURUM}}</code> - Rezervasyon durumu</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>