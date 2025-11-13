<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$page_title = 'Sadakat Programı';

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Sadakat programı işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_loyalty_settings':
                $points_per_lira = $_POST['points_per_lira'];
                $points_per_night = $_POST['points_per_night'];
                $points_per_review = $_POST['points_per_review'];
                $points_per_referral = $_POST['points_per_referral'];
                $min_redeem_points = $_POST['min_redeem_points'];
                $points_to_lira_ratio = $_POST['points_to_lira_ratio'];
                
                try {
                    // Sadakat ayarlarını güncelle
                    $settings = [
                        'points_per_lira' => $points_per_lira,
                        'points_per_night' => $points_per_night,
                        'points_per_review' => $points_per_review,
                        'points_per_referral' => $points_per_referral,
                        'min_redeem_points' => $min_redeem_points,
                        'points_to_lira_ratio' => $points_to_lira_ratio
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("
                            INSERT INTO sadakat_ayarlari (ayar_adi, ayar_degeri) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE ayar_degeri = ?
                        ");
                        $stmt->execute([$key, $value, $value]);
                    }
                    
                    $success_message = "Sadakat programı ayarları başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'add_points':
                $musteri_id = $_POST['musteri_id'];
                $points = $_POST['points'];
                $reason = $_POST['reason'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO sadakat_puanlari (musteri_id, puan, sebep, tip, olusturma_tarihi) 
                        VALUES (?, ?, ?, 'ekleme', NOW())
                    ");
                    $stmt->execute([$musteri_id, $points, $reason]);
                    
                    // Müşterinin toplam puanını güncelle
                    $stmt = $pdo->prepare("
                        UPDATE musteriler 
                        SET sadakat_puani = sadakat_puani + ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$points, $musteri_id]);
                    
                    $success_message = "Puan başarıyla eklendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'redeem_points':
                $musteri_id = $_POST['musteri_id'];
                $points = $_POST['points'];
                $reason = $_POST['reason'];
                
                try {
                    // Müşterinin puanını kontrol et
                    $stmt = $pdo->prepare("SELECT sadakat_puani FROM musteriler WHERE id = ?");
                    $stmt->execute([$musteri_id]);
                    $current_points = $stmt->fetchColumn();
                    
                    if ($current_points < $points) {
                        $error_message = "Yetersiz puan! Mevcut puan: " . $current_points;
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO sadakat_puanlari (musteri_id, puan, sebep, tip, olusturma_tarihi) 
                            VALUES (?, ?, ?, 'kullanim', NOW())
                        ");
                        $stmt->execute([$musteri_id, -$points, $reason]);
                        
                        // Müşterinin toplam puanını güncelle
                        $stmt = $pdo->prepare("
                            UPDATE musteriler 
                            SET sadakat_puani = sadakat_puani - ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$points, $musteri_id]);
                        
                        $success_message = "Puan başarıyla kullanıldı!";
                    }
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// Sadakat ayarlarını getir
$loyalty_settings = [];
try {
    $stmt = $pdo->prepare("SELECT ayar_adi, ayar_degeri FROM sadakat_ayarlari");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $loyalty_settings = $settings;
} catch (Exception $e) {
    // Varsayılan ayarlar
    $loyalty_settings = [
        'points_per_lira' => 1,
        'points_per_night' => 100,
        'points_per_review' => 50,
        'points_per_referral' => 200,
        'min_redeem_points' => 1000,
        'points_to_lira_ratio' => 100
    ];
}

// En çok puanı olan müşterileri getir
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.ad_soyad, m.email, m.telefon, m.sadakat_puani, 
               COUNT(r.id) as rezervasyon_sayisi,
               SUM(r.toplam_tutar) as toplam_harcama
        FROM musteriler m
        LEFT JOIN rezervasyonlar r ON m.id = r.musteri_id
        WHERE m.sadakat_puani > 0
        GROUP BY m.id
        ORDER BY m.sadakat_puani DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_customers = [];
}

// Son puan işlemlerini getir
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, m.ad_soyad as musteri_adi
        FROM sadakat_puanlari sp
        LEFT JOIN musteriler m ON sp.musteri_id = m.id
        ORDER BY sp.olusturma_tarihi DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_transactions = [];
}

// Tüm müşterileri getir (puan ekleme/çıkarma için)
try {
    $stmt = $pdo->prepare("SELECT id, ad_soyad, email, sadakat_puani FROM musteriler ORDER BY ad_soyad");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customers = [];
}

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-star me-2"></i>Sadakat Programı
                    </h1>
                    <p class="text-muted">Müşteri puanlama ve üyelik sistemi</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pointsModal">
                        <i class="fas fa-plus me-2"></i>Puan İşlemi
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
                                <h4 class="mb-0"><?= count($top_customers) ?></h4>
                                <p class="mb-0">Aktif Üye</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
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
                                <h4 class="mb-0"><?= array_sum(array_column($top_customers, 'sadakat_puani')) ?></h4>
                                <p class="mb-0">Toplam Puan</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-star fa-2x"></i>
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
                                <h4 class="mb-0"><?= count($recent_transactions) ?></h4>
                                <p class="mb-0">Son İşlemler</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exchange-alt fa-2x"></i>
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
                                <h4 class="mb-0"><?= $loyalty_settings['points_per_lira'] ?? 1 ?></h4>
                                <p class="mb-0">Puan/₺</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-lira-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sadakat Ayarları -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cog me-2"></i>Sadakat Programı Ayarları
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_loyalty_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Her ₺ için Puan</label>
                                        <input type="number" class="form-control" name="points_per_lira" 
                                               value="<?= $loyalty_settings['points_per_lira'] ?? 1 ?>" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Her Gece için Puan</label>
                                        <input type="number" class="form-control" name="points_per_night" 
                                               value="<?= $loyalty_settings['points_per_night'] ?? 100 ?>" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Değerlendirme Puanı</label>
                                        <input type="number" class="form-control" name="points_per_review" 
                                               value="<?= $loyalty_settings['points_per_review'] ?? 50 ?>" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Referans Puanı</label>
                                        <input type="number" class="form-control" name="points_per_referral" 
                                               value="<?= $loyalty_settings['points_per_referral'] ?? 200 ?>" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Min. Kullanım Puanı</label>
                                        <input type="number" class="form-control" name="min_redeem_points" 
                                               value="<?= $loyalty_settings['min_redeem_points'] ?? 1000 ?>" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Puan/₺ Oranı</label>
                                        <input type="number" class="form-control" name="points_to_lira_ratio" 
                                               value="<?= $loyalty_settings['points_to_lira_ratio'] ?? 100 ?>" min="0">
                                        <small class="text-muted">Kaç puan = 1₺</small>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Ayarları Kaydet
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- En Çok Puanı Olan Müşteriler -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-trophy me-2"></i>En Çok Puanı Olan Müşteriler
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Müşteri</th>
                                        <th>Puan</th>
                                        <th>Rezervasyon</th>
                                        <th>Harcama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($customer['ad_soyad']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?= number_format($customer['sadakat_puani']) ?></span>
                                        </td>
                                        <td><?= $customer['rezervasyon_sayisi'] ?></td>
                                        <td><?= number_format($customer['toplam_harcama'] ?? 0) ?>₺</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Puan İşlemleri -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Son Puan İşlemleri
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Müşteri</th>
                                <th>Puan</th>
                                <th>Tip</th>
                                <th>Sebep</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <small><?= date('d.m.Y H:i', strtotime($transaction['olusturma_tarihi'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($transaction['musteri_adi']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $transaction['puan'] > 0 ? 'success' : 'danger' ?>">
                                        <?= $transaction['puan'] > 0 ? '+' : '' ?><?= $transaction['puan'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $transaction['tip'] === 'ekleme' ? 'success' : 'warning' ?>">
                                        <?= $transaction['tip'] === 'ekleme' ? 'Ekleme' : 'Kullanım' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($transaction['sebep']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Puan İşlemi Modal -->
<div class="modal fade" id="pointsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star me-2"></i>Puan İşlemi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="pointsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="add-points-tab" data-bs-toggle="tab" data-bs-target="#add-points" type="button">
                            <i class="fas fa-plus me-1"></i>Puan Ekle
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="redeem-points-tab" data-bs-toggle="tab" data-bs-target="#redeem-points" type="button">
                            <i class="fas fa-minus me-1"></i>Puan Kullan
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="pointsTabContent">
                    <!-- Puan Ekleme -->
                    <div class="tab-pane fade show active" id="add-points">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_points">
                            
                            <div class="mb-3">
                                <label class="form-label">Müşteri *</label>
                                <select class="form-select" name="musteri_id" required>
                                    <option value="">Müşteri Seçin</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>">
                                        <?= htmlspecialchars($customer['ad_soyad']) ?> 
                                        (<?= number_format($customer['sadakat_puani']) ?> puan)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Puan Miktarı *</label>
                                <input type="number" class="form-control" name="points" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Sebep *</label>
                                <input type="text" class="form-control" name="reason" required placeholder="Örn: Rezervasyon bonusu, değerlendirme puanı...">
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Puan Ekle
                            </button>
                        </form>
                    </div>
                    
                    <!-- Puan Kullanma -->
                    <div class="tab-pane fade" id="redeem-points">
                        <form method="POST">
                            <input type="hidden" name="action" value="redeem_points">
                            
                            <div class="mb-3">
                                <label class="form-label">Müşteri *</label>
                                <select class="form-select" name="musteri_id" required>
                                    <option value="">Müşteri Seçin</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <?php if ($customer['sadakat_puani'] > 0): ?>
                                    <option value="<?= $customer['id'] ?>">
                                        <?= htmlspecialchars($customer['ad_soyad']) ?> 
                                        (<?= number_format($customer['sadakat_puani']) ?> puan)
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Kullanılacak Puan *</label>
                                <input type="number" class="form-control" name="points" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Sebep *</label>
                                <input type="text" class="form-control" name="reason" required placeholder="Örn: İndirim kullanımı, hediye...">
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-minus me-2"></i>Puan Kullan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
