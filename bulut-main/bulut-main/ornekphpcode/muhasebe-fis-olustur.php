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
if (!hasDetailedPermission('muhasebe_fis_olustur')) {
    $_SESSION['error_message'] = 'Muhasebe fiş oluşturma yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $fis_turu = sanitizeString($_POST['fis_turu']);
        $fis_tarihi = $_POST['fis_tarihi'];
        $aciklama = sanitizeString($_POST['aciklama']);
        $satirlar = $_POST['satirlar'];
        
        // Fiş numarası oluştur
        $fis_no = 'F' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        
        // Toplam tutarı hesapla
        $toplam_tutar = 0;
        foreach ($satirlar as $satir) {
            $toplam_tutar += floatval($satir['borc_tutari']) + floatval($satir['alacak_tutari']);
        }
        
        // Fiş oluştur
        $sql = "INSERT INTO muhasebe_fisleri (fis_no, fis_tarihi, fis_turu, aciklama, toplam_tutar, olusturan_id) VALUES (?, ?, ?, ?, ?, ?)";
        
        if (executeQuery($sql, [$fis_no, $fis_tarihi, $fis_turu, $aciklama, $toplam_tutar, $_SESSION['user_id']])) {
            $fis_id = $pdo->lastInsertId();
            
            // Fiş satırlarını ekle
            foreach ($satirlar as $satir) {
                if (!empty($satir['hesap_id']) && (floatval($satir['borc_tutari']) > 0 || floatval($satir['alacak_tutari']) > 0)) {
                    $sql = "INSERT INTO muhasebe_fis_satirlari (fis_id, hesap_id, aciklama, borc_tutari, alacak_tutari) VALUES (?, ?, ?, ?, ?)";
                    executeQuery($sql, [$fis_id, $satir['hesap_id'], $satir['aciklama'], $satir['borc_tutari'], $satir['alacak_tutari']]);
                }
            }
            
            $pdo->commit();
            $success_message = 'Fiş başarıyla oluşturuldu. Fiş No: ' . $fis_no;
        } else {
            throw new Exception('Fiş oluşturulurken hata oluştu.');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Hesapları getir
$hesaplar = fetchAll("
    SELECT * FROM muhasebe_hesaplari
    WHERE aktif = 1
    ORDER BY hesap_kodu
");

// Fiş türleri
$fis_turleri = [
    'gider' => 'Gider Fişi',
    'gelir' => 'Gelir Fişi',
    'mahsup' => 'Mahsup Fişi',
    'kapanis' => 'Kapanış Fişi'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muhasebe Fiş Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Muhasebe Fiş Oluştur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="muhasebe-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="fisForm">
                    <!-- Fiş Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-alt"></i> Fiş Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Fiş Türü <span class="text-danger">*</span></label>
                                    <select name="fis_turu" class="form-select" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($fis_turleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Fiş Tarihi <span class="text-danger">*</span></label>
                                    <input type="date" name="fis_tarihi" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <input type="text" name="aciklama" class="form-control" placeholder="Fiş açıklaması">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fiş Satırları -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list"></i> Fiş Satırları
                            </h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">
                                <i class="fas fa-plus"></i> Satır Ekle
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="satirlarTable">
                                    <thead>
                                        <tr>
                                            <th>Hesap</th>
                                            <th>Açıklama</th>
                                            <th>Borç</th>
                                            <th>Alacak</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody id="satirlarBody">
                                        <tr>
                                            <td>
                                                <select name="satirlar[0][hesap_id]" class="form-select" required>
                                                    <option value="">Hesap Seçin</option>
                                                    <?php foreach ($hesaplar as $hesap): ?>
                                                        <option value="<?php echo $hesap['id']; ?>">
                                                            <?php echo htmlspecialchars($hesap['hesap_kodu'] . ' - ' . $hesap['hesap_adi']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="satirlar[0][aciklama]" class="form-control" placeholder="Açıklama">
                                            </td>
                                            <td>
                                                <input type="number" name="satirlar[0][borc_tutari]" class="form-control" step="0.01" min="0" placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="satirlar[0][alacak_tutari]" class="form-control" step="0.01" min="0" placeholder="0.00">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Fiş Oluştur
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let rowCount = 1;

        function addRow() {
            const tbody = document.getElementById('satirlarBody');
            const newRow = tbody.insertRow();
            newRow.innerHTML = `
                <td>
                    <select name="satirlar[${rowCount}][hesap_id]" class="form-select" required>
                        <option value="">Hesap Seçin</option>
                        <?php foreach ($hesaplar as $hesap): ?>
                            <option value="<?php echo $hesap['id']; ?>">
                                <?php echo htmlspecialchars($hesap['hesap_kodu'] . ' - ' . $hesap['hesap_adi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="satirlar[${rowCount}][aciklama]" class="form-control" placeholder="Açıklama">
                </td>
                <td>
                    <input type="number" name="satirlar[${rowCount}][borc_tutari]" class="form-control" step="0.01" min="0" placeholder="0.00">
                </td>
                <td>
                    <input type="number" name="satirlar[${rowCount}][alacak_tutari]" class="form-control" step="0.01" min="0" placeholder="0.00">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            rowCount++;
        }

        function removeRow(button) {
            const row = button.closest('tr');
            row.remove();
        }
    </script>
</body>
</html>
