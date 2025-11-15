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
if (!hasDetailedPermission('satin_alma_talep_olustur')) {
    $_SESSION['error_message'] = 'Satın alma talep oluşturma yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $departman = sanitizeString($_POST['departman']);
        $acil_durum = sanitizeString($_POST['acil_durum']);
        $talep_tarihi = $_POST['talep_tarihi'];
        $teslim_tarihi = !empty($_POST['teslim_tarihi']) ? $_POST['teslim_tarihi'] : null;
        $aciklama = sanitizeString($_POST['aciklama']);
        $satirlar = $_POST['satirlar'];
        
        // Talep numarası oluştur
        $talep_no = 'SA' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        
        // Toplam tutarı hesapla
        $toplam_tutar = 0;
        foreach ($satirlar as $satir) {
            $toplam_tutar += floatval($satir['toplam_fiyat']);
        }
        
        // Talep oluştur
        $sql = "INSERT INTO satin_alma_talepleri (talep_no, talep_eden_id, departman, acil_durum, talep_tarihi, teslim_tarihi, toplam_tutar, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        if (executeQuery($sql, [$talep_no, $_SESSION['user_id'], $departman, $acil_durum, $talep_tarihi, $teslim_tarihi, $toplam_tutar, $aciklama])) {
            $talep_id = $pdo->lastInsertId();
            
            // Talep detaylarını ekle
            foreach ($satirlar as $satir) {
                if (!empty($satir['urun_adi']) && floatval($satir['miktar']) > 0) {
                    $sql = "INSERT INTO satin_alma_talep_detaylari (talep_id, urun_adi, urun_aciklamasi, miktar, birim, birim_fiyat, toplam_fiyat, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql, [$talep_id, $satir['urun_adi'], $satir['urun_aciklamasi'], $satir['miktar'], $satir['birim'], $satir['birim_fiyat'], $satir['toplam_fiyat'], $satir['aciklama']]);
                }
            }
            
            $pdo->commit();
            $success_message = 'Satın alma talebi başarıyla oluşturuldu. Talep No: ' . $talep_no;
        } else {
            throw new Exception('Talep oluşturulurken hata oluştu.');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Departmanları getir
$departmanlar = fetchAll("
    SELECT DISTINCT departman 
    FROM satin_alma_talepleri 
    WHERE departman IS NOT NULL AND departman != ''
    ORDER BY departman
");

// Acil durum seviyeleri
$acil_durum_seviyeleri = [
    'normal' => 'Normal',
    'acil' => 'Acil',
    'kritik' => 'Kritik'
];

// Birim seçenekleri
$birim_secenekleri = [
    'adet' => 'Adet',
    'kg' => 'Kilogram',
    'lt' => 'Litre',
    'm' => 'Metre',
    'm2' => 'Metrekare',
    'm3' => 'Metreküp',
    'paket' => 'Paket',
    'kutu' => 'Kutu',
    'düzine' => 'Düzine',
    'gross' => 'Gross'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satın Alma Talep Oluştur</title>
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
                    <h1 class="h2">Satın Alma Talep Oluştur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="satin-alma-dashboard.php" class="btn btn-outline-secondary">
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

                <form method="POST" id="talepForm">
                    <!-- Talep Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shopping-cart"></i> Talep Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Departman <span class="text-danger">*</span></label>
                                    <select name="departman" class="form-select" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($departmanlar as $departman): ?>
                                            <option value="<?php echo htmlspecialchars($departman['departman']); ?>">
                                                <?php echo htmlspecialchars($departman['departman']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Acil Durum</label>
                                    <select name="acil_durum" class="form-select">
                                        <?php foreach ($acil_durum_seviyeleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $key == 'normal' ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Talep Tarihi <span class="text-danger">*</span></label>
                                    <input type="date" name="talep_tarihi" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Teslim Tarihi</label>
                                    <input type="date" name="teslim_tarihi" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea name="aciklama" class="form-control" rows="3" placeholder="Talep açıklaması"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Talep Detayları -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list"></i> Talep Detayları
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
                                            <th>Ürün Adı</th>
                                            <th>Açıklama</th>
                                            <th>Miktar</th>
                                            <th>Birim</th>
                                            <th>Birim Fiyat</th>
                                            <th>Toplam Fiyat</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody id="satirlarBody">
                                        <tr>
                                            <td>
                                                <input type="text" name="satirlar[0][urun_adi]" class="form-control" placeholder="Ürün adı" required>
                                            </td>
                                            <td>
                                                <input type="text" name="satirlar[0][urun_aciklamasi]" class="form-control" placeholder="Ürün açıklaması">
                                            </td>
                                            <td>
                                                <input type="number" name="satirlar[0][miktar]" class="form-control" step="0.01" min="0" placeholder="0" required onchange="calculateTotal(this)">
                                            </td>
                                            <td>
                                                <select name="satirlar[0][birim]" class="form-select">
                                                    <?php foreach ($birim_secenekleri as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="satirlar[0][birim_fiyat]" class="form-control" step="0.01" min="0" placeholder="0.00" onchange="calculateTotal(this)">
                                            </td>
                                            <td>
                                                <input type="number" name="satirlar[0][toplam_fiyat]" class="form-control" step="0.01" min="0" placeholder="0.00" readonly>
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
                            <i class="fas fa-save"></i> Talep Oluştur
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
                    <input type="text" name="satirlar[${rowCount}][urun_adi]" class="form-control" placeholder="Ürün adı" required>
                </td>
                <td>
                    <input type="text" name="satirlar[${rowCount}][urun_aciklamasi]" class="form-control" placeholder="Ürün açıklaması">
                </td>
                <td>
                    <input type="number" name="satirlar[${rowCount}][miktar]" class="form-control" step="0.01" min="0" placeholder="0" required onchange="calculateTotal(this)">
                </td>
                <td>
                    <select name="satirlar[${rowCount}][birim]" class="form-select">
                        <?php foreach ($birim_secenekleri as $key => $value): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="satirlar[${rowCount}][birim_fiyat]" class="form-control" step="0.01" min="0" placeholder="0.00" onchange="calculateTotal(this)">
                </td>
                <td>
                    <input type="number" name="satirlar[${rowCount}][toplam_fiyat]" class="form-control" step="0.01" min="0" placeholder="0.00" readonly>
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

        function calculateTotal(input) {
            const row = input.closest('tr');
            const miktar = parseFloat(row.querySelector('input[name*="[miktar]"]').value) || 0;
            const birimFiyat = parseFloat(row.querySelector('input[name*="[birim_fiyat]"]').value) || 0;
            const toplamFiyat = miktar * birimFiyat;
            row.querySelector('input[name*="[toplam_fiyat]"]').value = toplamFiyat.toFixed(2);
        }
    </script>
</body>
</html>
