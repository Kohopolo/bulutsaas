<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$page_title = 'Paket Tur Yönetimi';

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Paket işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_package':
                $package_name = $_POST['package_name'];
                $description = $_POST['description'];
                $duration_days = $_POST['duration_days'];
                $base_price = $_POST['base_price'];
                $min_guests = $_POST['min_guests'];
                $max_guests = $_POST['max_guests'];
                $includes = $_POST['includes'];
                $excludes = $_POST['excludes'];
                $status = $_POST['status'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO paket_turlari 
                        (paket_adi, aciklama, sure_gun, temel_fiyat, min_misafir, max_misafir, 
                         dahil_olanlar, dahil_olmayanlar, durum, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $package_name, $description, $duration_days, $base_price, 
                        $min_guests, $max_guests, $includes, $excludes, $status
                    ]);
                    
                    $success_message = "Paket tur başarıyla oluşturuldu!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'update_package':
                $package_id = $_POST['package_id'];
                $package_name = $_POST['package_name'];
                $description = $_POST['description'];
                $duration_days = $_POST['duration_days'];
                $base_price = $_POST['base_price'];
                $min_guests = $_POST['min_guests'];
                $max_guests = $_POST['max_guests'];
                $includes = $_POST['includes'];
                $excludes = $_POST['excludes'];
                $status = $_POST['status'];
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE paket_turlari 
                        SET paket_adi = ?, aciklama = ?, sure_gun = ?, temel_fiyat = ?, 
                            min_misafir = ?, max_misafir = ?, dahil_olanlar = ?, 
                            dahil_olmayanlar = ?, durum = ?, guncelleme_tarihi = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $package_name, $description, $duration_days, $base_price, 
                        $min_guests, $max_guests, $includes, $excludes, $status, $package_id
                    ]);
                    
                    $success_message = "Paket tur başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'delete_package':
                $package_id = $_POST['package_id'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE paket_turlari SET durum = 'silindi' WHERE id = ?");
                    $stmt->execute([$package_id]);
                    $success_message = "Paket tur başarıyla silindi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// Paket turlarını getir
try {
    $stmt = $pdo->prepare("
        SELECT * FROM paket_turlari 
        WHERE durum != 'silindi'
        ORDER BY olusturma_tarihi DESC
    ");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $packages = [];
}

// Paket rezervasyonlarını getir
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, pt.paket_adi, m.ad_soyad as musteri_adi
        FROM paket_rezervasyonlari pr
        LEFT JOIN paket_turlari pt ON pr.paket_id = pt.id
        LEFT JOIN musteriler m ON pr.musteri_id = m.id
        ORDER BY pr.olusturma_tarihi DESC
        LIMIT 10
    ");
    $stmt->execute();
    $package_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $package_bookings = [];
}

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-gift me-2"></i>Paket Tur Yönetimi
                    </h1>
                    <p class="text-muted">Aktivite ve hizmet paketleri ile entegre rezervasyon sistemi</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPackageModal">
                        <i class="fas fa-plus me-2"></i>Yeni Paket Tur
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= count($packages) ?></h4>
                                <p class="mb-0">Aktif Paket</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-gift fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= count($package_bookings) ?></h4>
                                <p class="mb-0">Toplam Rezervasyon</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= array_sum(array_column($packages, 'temel_fiyat')) ?>₺</h4>
                                <p class="mb-0">Toplam Değer</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-lira-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= count(array_filter($packages, fn($p) => $p['durum'] === 'aktif')) ?></h4>
                                <p class="mb-0">Satışta</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paket Turları -->
        <div class="row">
            <?php foreach ($packages as $package): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= htmlspecialchars($package['paket_adi']) ?></h6>
                        <span class="badge bg-<?= $package['durum'] === 'aktif' ? 'success' : 'secondary' ?>">
                            <?= $package['durum'] === 'aktif' ? 'Aktif' : 'Pasif' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="card-text text-muted small">
                            <?= htmlspecialchars(substr($package['aciklama'], 0, 100)) ?>...
                        </p>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-calendar-day text-primary"></i>
                                    <div class="small"><?= $package['sure_gun'] ?> Gün</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-users text-success"></i>
                                    <div class="small"><?= $package['min_misafir'] ?>-<?= $package['max_misafir'] ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-lira-sign text-warning"></i>
                                    <div class="small"><?= number_format($package['temel_fiyat']) ?>₺</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="small text-success mb-1">Dahil Olanlar:</h6>
                            <p class="small text-muted"><?= htmlspecialchars(substr($package['dahil_olanlar'], 0, 80)) ?>...</p>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPackageDetails(<?= $package['id'] ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="editPackage(<?= $package['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="viewPackageBookings(<?= $package['id'] ?>)">
                                <i class="fas fa-calendar-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePackage(<?= $package['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Son Paket Rezervasyonları -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock me-2"></i>Son Paket Rezervasyonları
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Paket</th>
                                <th>Müşteri</th>
                                <th>Tarihler</th>
                                <th>Misafir Sayısı</th>
                                <th>Toplam Tutar</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($package_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($booking['paket_adi']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($booking['musteri_adi']) ?></td>
                                <td>
                                    <div>
                                        <i class="fas fa-calendar-check me-1 text-success"></i><?= date('d.m.Y', strtotime($booking['baslangic_tarihi'])) ?><br>
                                        <i class="fas fa-calendar-times me-1 text-danger"></i><?= date('d.m.Y', strtotime($booking['bitis_tarihi'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-users me-1"></i><?= $booking['misafir_sayisi'] ?>
                                </td>
                                <td>
                                    <strong><?= number_format($booking['toplam_tutar']) ?>₺</strong>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'beklemede' => 'warning',
                                        'onaylandi' => 'success',
                                        'iptal' => 'danger',
                                        'tamamlandi' => 'info'
                                    ];
                                    $status_texts = [
                                        'beklemede' => 'Beklemede',
                                        'onaylandi' => 'Onaylandı',
                                        'iptal' => 'İptal',
                                        'tamamlandi' => 'Tamamlandı'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$booking['durum']] ?? 'secondary' ?>">
                                        <?= $status_texts[$booking['durum']] ?? $booking['durum'] ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewBookingDetails(<?= $booking['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Paket Modal -->
<div class="modal fade" id="newPackageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-gift me-2"></i>Yeni Paket Tur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_package">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Paket Adı *</label>
                                <input type="text" class="form-control" name="package_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Durum *</label>
                                <select class="form-select" name="status" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="pasif">Pasif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama *</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Süre (Gün) *</label>
                                <input type="number" class="form-control" name="duration_days" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Temel Fiyat (₺) *</label>
                                <input type="number" class="form-control" name="base_price" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Min Misafir *</label>
                                <input type="number" class="form-control" name="min_guests" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Max Misafir *</label>
                                <input type="number" class="form-control" name="max_guests" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dahil Olanlar *</label>
                        <textarea class="form-control" name="includes" rows="3" required placeholder="Konaklama, kahvaltı, transfer, rehber..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dahil Olmayanlar</label>
                        <textarea class="form-control" name="excludes" rows="2" placeholder="Uçak bileti, öğle yemeği, kişisel harcamalar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Paket Tur Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewPackageDetails(packageId) {
    // Paket detaylarını görüntüleme
    alert('Paket detayları: ' + packageId);
}

function editPackage(packageId) {
    // Paket düzenleme
    alert('Paket düzenleme: ' + packageId);
}

function viewPackageBookings(packageId) {
    // Paket rezervasyonlarını görüntüleme
    alert('Paket rezervasyonları: ' + packageId);
}

function deletePackage(packageId) {
    if (confirm('Bu paket turunu silmek istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_package">
            <input type="hidden" name="package_id" value="${packageId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewBookingDetails(bookingId) {
    // Rezervasyon detaylarını görüntüleme
    alert('Rezervasyon detayları: ' + bookingId);
}
</script>

<?php include 'footer.php'; ?>
