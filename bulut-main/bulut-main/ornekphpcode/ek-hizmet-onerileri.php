<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/detailed_permission_functions.php';

$page_title = 'Ek Hizmet Öneri Motoru';

// Yetki kontrolü
requireDetailedPermission('ek_hizmet_goruntule', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Ek hizmet işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_service':
                if (!hasDetailedPermission('ek_hizmet_ekle')) {
                    $error_message = 'Ek hizmet ekleme yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $service_name = $_POST['service_name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $category = $_POST['category'];
                $duration = $_POST['duration'];
                $max_capacity = $_POST['max_capacity'];
                $is_active = $_POST['is_active'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ek_hizmetler 
                        (hizmet_adi, aciklama, fiyat, kategori, sure_dakika, max_kapasite, aktif, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $service_name, $description, $price, $category, $duration, $max_capacity, $is_active
                    ]);
                    
                    $success_message = "Ek hizmet başarıyla oluşturuldu!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'update_service':
                if (!hasDetailedPermission('ek_hizmet_duzenle')) {
                    $error_message = 'Ek hizmet düzenleme yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $service_id = $_POST['service_id'];
                $service_name = $_POST['service_name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $category = $_POST['category'];
                $duration = $_POST['duration'];
                $max_capacity = $_POST['max_capacity'];
                $is_active = $_POST['is_active'];
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE ek_hizmetler 
                        SET hizmet_adi = ?, aciklama = ?, fiyat = ?, kategori = ?, 
                            sure_dakika = ?, max_kapasite = ?, aktif = ?, guncelleme_tarihi = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $service_name, $description, $price, $category, $duration, $max_capacity, $is_active, $service_id
                    ]);
                    
                    $success_message = "Ek hizmet başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'delete_service':
                if (!hasDetailedPermission('ek_hizmet_sil')) {
                    $error_message = 'Ek hizmet silme yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $service_id = $_POST['service_id'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE ek_hizmetler SET aktif = 0 WHERE id = ?");
                    $stmt->execute([$service_id]);
                    $success_message = "Ek hizmet başarıyla silindi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// Ek hizmetleri getir
try {
    $stmt = $pdo->prepare("
        SELECT * FROM ek_hizmetler 
        WHERE aktif = 1
        ORDER BY kategori, hizmet_adi
    ");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Kategorileri getir
$categories = array_unique(array_column($services, 'kategori'));

// Öneri motoru istatistikleri
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as toplam_hizmet,
            AVG(fiyat) as ortalama_fiyat,
            SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif_hizmet
        FROM ek_hizmetler
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['toplam_hizmet' => 0, 'ortalama_fiyat' => 0, 'aktif_hizmet' => 0];
}

// Son önerileri getir
try {
    $stmt = $pdo->prepare("
        SELECT eo.*, eh.hizmet_adi, m.ad_soyad as musteri_adi
        FROM ek_hizmet_onerileri eo
        LEFT JOIN ek_hizmetler eh ON eo.hizmet_id = eh.id
        LEFT JOIN musteriler m ON eo.musteri_id = m.id
        ORDER BY eo.olusturma_tarihi DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_offers = [];
}

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-lightbulb me-2"></i>Ek Hizmet Öneri Motoru
                    </h1>
                    <p class="text-muted">Otomatik ek hizmet ve ürün önerileri</p>
                </div>
                <div class="col-auto">
                    <?php if (hasDetailedPermission('ek_hizmet_ekle')): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newServiceModal">
                        <i class="fas fa-plus me-2"></i>Yeni Ek Hizmet
                    </button>
                    <?php endif; ?>
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
                                <h4 class="mb-0"><?= $stats['toplam_hizmet'] ?></h4>
                                <p class="mb-0">Toplam Hizmet</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-concierge-bell fa-2x"></i>
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
                                <h4 class="mb-0"><?= $stats['aktif_hizmet'] ?></h4>
                                <p class="mb-0">Aktif Hizmet</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
                                <h4 class="mb-0"><?= number_format($stats['ortalama_fiyat'], 0) ?>₺</h4>
                                <p class="mb-0">Ortalama Fiyat</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-lira-sign fa-2x"></i>
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
                                <h4 class="mb-0"><?= count($recent_offers) ?></h4>
                                <p class="mb-0">Son Öneriler</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bullhorn fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ek Hizmetler -->
        <div class="row">
            <?php foreach ($categories as $category): ?>
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tag me-2"></i><?= htmlspecialchars($category) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            $category_services = array_filter($services, fn($s) => $s['kategori'] === $category);
                            foreach ($category_services as $service): 
                            ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="card-title"><?= htmlspecialchars($service['hizmet_adi']) ?></h6>
                                        <p class="card-text text-muted small">
                                            <?= htmlspecialchars(substr($service['aciklama'], 0, 80)) ?>...
                                        </p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <i class="fas fa-lira-sign text-primary"></i>
                                                    <div class="small"><?= number_format($service['fiyat']) ?>₺</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <i class="fas fa-clock text-success"></i>
                                                    <div class="small"><?= $service['sure_dakika'] ?> dk</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <i class="fas fa-users text-warning"></i>
                                                    <div class="small"><?= $service['max_kapasite'] ?> kişi</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group w-100" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewServiceDetails(<?= $service['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasDetailedPermission('ek_hizmet_duzenle')): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="editService(<?= $service['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasDetailedPermission('ek_hizmet_sil')): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteService(<?= $service['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Son Öneriler -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Son Öneriler
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Müşteri</th>
                                <th>Önerilen Hizmet</th>
                                <th>Öneri Tipi</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_offers as $offer): ?>
                            <tr>
                                <td>
                                    <small><?= date('d.m.Y H:i', strtotime($offer['olusturma_tarihi'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($offer['musteri_adi']) ?></td>
                                <td><?= htmlspecialchars($offer['hizmet_adi']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($offer['oneri_tipi']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'beklemede' => 'warning',
                                        'kabul_edildi' => 'success',
                                        'reddedildi' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$offer['durum']] ?? 'secondary' ?>">
                                        <?= ucfirst($offer['durum']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewOfferDetails(<?= $offer['id'] ?>)">
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

<!-- Yeni Ek Hizmet Modal -->
<?php if (hasDetailedPermission('ek_hizmet_ekle')): ?>
<div class="modal fade" id="newServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-concierge-bell me-2"></i>Yeni Ek Hizmet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_service">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Hizmet Adı *</label>
                                <input type="text" class="form-control" name="service_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kategori *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Kategori Seçin</option>
                                    <option value="Spa & Wellness">Spa & Wellness</option>
                                    <option value="Restoran & Bar">Restoran & Bar</option>
                                    <option value="Aktivite">Aktivite</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Temizlik">Temizlik</option>
                                    <option value="Diğer">Diğer</option>
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
                                <label class="form-label">Fiyat (₺) *</label>
                                <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Süre (Dakika) *</label>
                                <input type="number" class="form-control" name="duration" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Kapasite *</label>
                                <input type="number" class="form-control" name="max_capacity" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Durum</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Ek Hizmet Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function viewServiceDetails(serviceId) {
    // Hizmet detaylarını görüntüleme
    alert('Hizmet detayları: ' + serviceId);
}

function editService(serviceId) {
    // Hizmet düzenleme
    alert('Hizmet düzenleme: ' + serviceId);
}

function deleteService(serviceId) {
    if (confirm('Bu ek hizmeti silmek istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="service_id" value="${serviceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewOfferDetails(offerId) {
    // Öneri detaylarını görüntüleme
    alert('Öneri detayları: ' + offerId);
}
</script>

<?php include 'footer.php'; ?>
