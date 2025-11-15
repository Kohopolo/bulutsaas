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
if (!hasDetailedPermission('fnb_siparis_al')) {
    $_SESSION['error_message'] = 'F&B sipariş alma yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $oda_id = intval($_POST['oda_id']);
        $musteri_id = intval($_POST['musteri_id']);
        $departman = sanitizeString($_POST['departman']);
        $aciklama = sanitizeString($_POST['aciklama']);
        $menu_ogeleri = $_POST['menu_ogeleri'] ?? [];
        
        // Validasyon
        if (empty($oda_id) || empty($departman) || empty($menu_ogeleri)) {
            throw new Exception('Lütfen tüm gerekli alanları doldurun.');
        }
        
        // Sipariş numarası oluştur
        $siparis_no = 'SP' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Toplam tutarı hesapla
        $toplam_tutar = 0;
        $toplam_maliyet = 0;
        
        foreach ($menu_ogeleri as $ogesi) {
            $menu_ogesi = fetchOne("SELECT * FROM menu_ogeleri WHERE id = ?", [$ogesi['id']]);
            if ($menu_ogesi) {
                $toplam_tutar += $ogesi['adet'] * $menu_ogesi['fiyat'];
                $toplam_maliyet += $ogesi['adet'] * $menu_ogesi['maliyet'];
            }
        }
        
        // Siparişi oluştur
        $siparis_sql = "INSERT INTO fnb_siparisler (
            siparis_no, oda_id, musteri_id, departman, siparis_durumu, 
            toplam_tutar, toplam_maliyet, siparis_tarihi, aciklama, siparis_alan_id
        ) VALUES (?, ?, ?, ?, 'alindi', ?, ?, NOW(), ?, ?)";
        
        $siparis_id = executeQuery($siparis_sql, [
            $siparis_no, $oda_id, $musteri_id, $departman, 
            $toplam_tutar, $toplam_maliyet, $aciklama, $_SESSION['user_id']
        ]);
        
        if ($siparis_id) {
            // Sipariş detaylarını ekle
            foreach ($menu_ogeleri as $ogesi) {
                $menu_ogesi = fetchOne("SELECT * FROM menu_ogeleri WHERE id = ?", [$ogesi['id']]);
                if ($menu_ogesi) {
                    $detay_sql = "INSERT INTO fnb_siparis_detaylari (
                        siparis_id, menu_ogesi_id, adet, birim_fiyat, toplam_fiyat,
                        birim_maliyet, toplam_maliyet, ozel_notlar
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($detay_sql, [
                        $siparis_id, $ogesi['id'], $ogesi['adet'], 
                        $menu_ogesi['fiyat'], $ogesi['adet'] * $menu_ogesi['fiyat'],
                        $menu_ogesi['maliyet'], $ogesi['adet'] * $menu_ogesi['maliyet'],
                        $ogesi['notlar'] ?? ''
                    ]);
                }
            }
            
            $pdo->commit();
            $success_message = 'Sipariş başarıyla alındı. Sipariş No: ' . $siparis_no;
            
            // Formu temizle
            $_POST = [];
        } else {
            throw new Exception('Sipariş oluşturulurken hata oluştu.');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Odaları getir
$odalar = fetchAll("
    SELECT oda_numaralari.id, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, oda_numaralari.durum
    FROM oda_numaralari
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    WHERE oda_numaralari.durum IN ('dolu', 'musait')
    ORDER BY oda_numaralari.oda_numarasi
");

// Müşterileri getir
$musteriler = fetchAll("
    SELECT id, ad, soyad, email, telefon
    FROM musteriler
    ORDER BY ad, soyad
");

// Menü kategorilerini getir
$kategoriler = fetchAll("
    SELECT * FROM menu_kategorileri
    ORDER BY kategori_adi
");

// Menü öğelerini getir
$menu_ogeleri = fetchAll("
    SELECT mo.*, mk.kategori_adi
    FROM menu_ogeleri mo
    LEFT JOIN menu_kategorileri mk ON mo.kategori_id = mk.id
    ORDER BY mk.kategori_adi, mo.urun_adi
");

// Departmanlar
$departmanlar = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane'
];

error_log("F&B Sipariş Al - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Sipariş Al - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-plus me-2"></i>F&B Sipariş Al</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="siparisForm">
                    <?php echo csrfTokenInput(); ?>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Sipariş Bilgileri -->
                            <div class="card shadow mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Sipariş Bilgileri
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="oda_id" class="form-label">Oda <span class="text-danger">*</span></label>
                                            <select class="form-select" id="oda_id" name="oda_id" required>
                                                <option value="">Oda Seçin</option>
                                                <?php foreach ($odalar as $oda): ?>
                                                <option value="<?php echo $oda['id']; ?>" 
                                                        <?php echo (isset($_POST['oda_id']) && $_POST['oda_id'] == $oda['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($oda['oda_numarasi'] . ' - ' . $oda['oda_tipi']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="musteri_id" class="form-label">Müşteri</label>
                                            <select class="form-select" id="musteri_id" name="musteri_id">
                                                <option value="">Müşteri Seçin (Opsiyonel)</option>
                                                <?php foreach ($musteriler as $musteri): ?>
                                                <option value="<?php echo $musteri['id']; ?>"
                                                        <?php echo (isset($_POST['musteri_id']) && $_POST['musteri_id'] == $musteri['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="departman" class="form-label">Departman <span class="text-danger">*</span></label>
                                            <select class="form-select" id="departman" name="departman" required>
                                                <option value="">Departman Seçin</option>
                                                <?php foreach ($departmanlar as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"
                                                        <?php echo (isset($_POST['departman']) && $_POST['departman'] == $key) ? 'selected' : ''; ?>>
                                                    <?php echo $value; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="aciklama" class="form-label">Açıklama</label>
                                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" 
                                                      placeholder="Sipariş ile ilgili özel notlar..."><?php echo isset($_POST['aciklama']) ? htmlspecialchars($_POST['aciklama']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Menü Seçimi -->
                            <div class="card shadow mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-utensils me-2"></i>Menü Seçimi
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php 
                                        $current_departman = '';
                                        foreach ($menu_ogeleri as $ogesi): 
                                            if ($current_departman != $ogesi['departman']):
                                                if ($current_departman != ''): ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <div class="col-12 mb-4">
                                                    <h6 class="text-primary border-bottom pb-2">
                                                        <i class="fas fa-<?php echo $ogesi['departman'] == 'mutfak' ? 'fire' : ($ogesi['departman'] == 'restoran' ? 'utensils' : ($ogesi['departman'] == 'bar' ? 'wine-glass' : 'birthday-cake')); ?> me-2"></i>
                                                        <?php echo $departmanlar[$ogesi['departman']]; ?>
                                                    </h6>
                                                    <div class="row">
                                        <?php 
                                                $current_departman = $ogesi['departman'];
                                            endif; ?>
                                            
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($ogesi['urun_adi']); ?></h6>
                                                        <p class="card-text small text-muted">
                                                            <?php echo htmlspecialchars($ogesi['urun_aciklamasi']); ?>
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="text-success fw-bold">
                                                                <?php echo number_format($ogesi['fiyat'], 2); ?>₺
                                                            </span>
                                                            <div class="input-group" style="width: 120px;">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                        onclick="changeQuantity('<?php echo $ogesi['id']; ?>', -1)">-</button>
                                                                <input type="number" class="form-control form-control-sm text-center" 
                                                                       id="qty_<?php echo $ogesi['id']; ?>" 
                                                                       name="menu_ogeleri[<?php echo $ogesi['id']; ?>][adet]" 
                                                                       value="0" min="0" max="99">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                        onclick="changeQuantity('<?php echo $ogesi['id']; ?>', 1)">+</button>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="menu_ogeleri[<?php echo $ogesi['id']; ?>][id]" value="<?php echo $ogesi['id']; ?>">
                                                        <textarea class="form-control form-control-sm mt-2" 
                                                                  name="menu_ogeleri[<?php echo $ogesi['id']; ?>][notlar]" 
                                                                  placeholder="Özel notlar..." rows="2"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($current_departman != ''): ?>
                                                    </div>
                                                </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Sipariş Özeti -->
                            <div class="card shadow mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>Sipariş Özeti
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="siparis-ozeti">
                                        <p class="text-muted">Henüz ürün seçilmedi.</p>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Toplam:</strong>
                                        <strong id="toplam-tutar">0,00₺</strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sipariş Butonları -->
                            <div class="card shadow">
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-check me-2"></i>Siparişi Onayla
                                        </button>
                                        <a href="fnb-dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>İptal
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Miktar değiştirme
        function changeQuantity(id, change) {
            const input = document.getElementById('qty_' + id);
            let value = parseInt(input.value) + change;
            if (value < 0) value = 0;
            if (value > 99) value = 99;
            input.value = value;
            updateOrderSummary();
        }
        
        // Sipariş özetini güncelle
        function updateOrderSummary() {
            const form = document.getElementById('siparisForm');
            const formData = new FormData(form);
            const selectedItems = [];
            let total = 0;
            
            // Seçili ürünleri topla
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('menu_ogeleri[') && key.endsWith('][adet]') && parseInt(value) > 0) {
                    const id = key.match(/\[(\d+)\]/)[1];
                    const quantity = parseInt(value);
                    const price = parseFloat(document.querySelector(`input[name="menu_ogeleri[${id}][id]"]`).closest('.card').querySelector('.text-success').textContent.replace('₺', '').replace(',', '.'));
                    const name = document.querySelector(`input[name="menu_ogeleri[${id}][id]"]`).closest('.card').querySelector('.card-title').textContent;
                    
                    selectedItems.push({
                        id: id,
                        name: name,
                        quantity: quantity,
                        price: price,
                        total: quantity * price
                    });
                    
                    total += quantity * price;
                }
            }
            
            // Özeti güncelle
            const ozetDiv = document.getElementById('siparis-ozeti');
            const toplamDiv = document.getElementById('toplam-tutar');
            
            if (selectedItems.length === 0) {
                ozetDiv.innerHTML = '<p class="text-muted">Henüz ürün seçilmedi.</p>';
                toplamDiv.textContent = '0,00₺';
            } else {
                let html = '';
                selectedItems.forEach(item => {
                    html += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small class="fw-bold">${item.name}</small>
                                <br>
                                <small class="text-muted">${item.quantity} adet x ${item.price.toFixed(2)}₺</small>
                            </div>
                            <strong>${item.total.toFixed(2)}₺</strong>
                        </div>
                    `;
                });
                ozetDiv.innerHTML = html;
                toplamDiv.textContent = total.toFixed(2) + '₺';
            }
        }
        
        // Sayfa yüklendiğinde ve input değişikliklerinde özeti güncelle
        document.addEventListener('DOMContentLoaded', function() {
            updateOrderSummary();
            
            // Tüm miktar inputlarını dinle
            document.querySelectorAll('input[name*="[adet]"]').forEach(input => {
                input.addEventListener('change', updateOrderSummary);
            });
        });
    </script>
</body>
</html>
