<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_goruntule', 'Ödeme yönetimi yetkiniz bulunmamaktadır.');

$page_title = "Ödeme Yönetimi";
$current_page = "odeme-yonetimi";

// Filtreleme parametreleri
$provider_tipi = $_GET['provider_tipi'] ?? '';
$aktif_durum = $_GET['aktif_durum'] ?? '';
$arama = $_GET['arama'] ?? '';

// Ödeme sağlayıcıları sorgusu
$where_conditions = [];
$params = [];

if (!empty($provider_tipi)) {
    $where_conditions[] = "provider_tipi = ?";
    $params[] = $provider_tipi;
}

if ($aktif_durum !== '') {
    $where_conditions[] = "aktif = ?";
    $params[] = $aktif_durum;
}

if (!empty($arama)) {
    $where_conditions[] = "(provider_adi LIKE ? OR provider_kodu LIKE ?)";
    $params[] = "%$arama%";
    $params[] = "%$arama%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Ödeme sağlayıcılarını getir
$providerlar = fetchAll("
    SELECT 
        p.*,
        COUNT(DISTINCT oi.id) as toplam_islem,
        COUNT(DISTINCT CASE WHEN oi.durum = 'basarili' THEN oi.id END) as basarili_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.komisyon_tutari ELSE 0 END) as toplam_komisyon
    FROM odeme_providerlari p
    LEFT JOIN odeme_islemleri oi ON p.id = oi.provider_id
    $where_clause
    GROUP BY p.id
    ORDER BY p.siralama, p.provider_adi
", $params);

// İstatistikler
$istatistikler = fetchOne("
    SELECT 
        COUNT(DISTINCT p.id) as toplam_provider,
        COUNT(DISTINCT CASE WHEN p.aktif = 1 THEN p.id END) as aktif_provider,
        COUNT(DISTINCT oi.id) as toplam_islem,
        COUNT(DISTINCT CASE WHEN oi.durum = 'basarili' THEN oi.id END) as basarili_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.komisyon_tutari ELSE 0 END) as toplam_komisyon,
        AVG(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as ortalama_tutar
    FROM odeme_providerlari p
    LEFT JOIN odeme_islemleri oi ON p.id = oi.provider_id
");

// Başarı oranı hesapla
$basarili_oran = $istatistikler['toplam_islem'] > 0 ? 
    ($istatistikler['basarili_islem'] / $istatistikler['toplam_islem']) * 100 : 0;

// Son işlemler
$son_islemler = fetchAll("
    SELECT 
        oi.*,
        p.provider_adi,
        r.rezervasyon_kodu,
        m.ad as musteri_adi,
        m.soyad as musteri_soyadi
    FROM odeme_islemleri oi
    JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN rezervasyonlar r ON oi.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON oi.musteri_id = m.id
    ORDER BY oi.islem_tarihi DESC
    LIMIT 10
");

include 'header.php';
?>

<div class="desktop-container">
    <div class="desktop-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title">
                    <i class="fas fa-credit-card me-2"></i>
                    Ödeme Yönetimi
                </h1>
                <p class="page-subtitle mb-0">Sanal pos sağlayıcıları ve ödeme işlemleri yönetimi</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <?php if (hasDetailedPermission('odeme_provider_ekle')): ?>
                    <a href="odeme-provider-ekle.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Yeni Sağlayıcı
                    </a>
                    <?php endif; ?>
                    <?php if (hasDetailedPermission('odeme_api_test')): ?>
                    <a href="odeme-api-test.php" class="btn btn-outline-info">
                        <i class="fas fa-flask"></i> API Test
                    </a>
                    <?php endif; ?>
                    <?php if (hasDetailedPermission('odeme_raporlar')): ?>
                    <a href="odeme-raporlari.php" class="btn btn-outline-success">
                        <i class="fas fa-chart-bar"></i> Raporlar
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['toplam_provider'] ?? 0) ?></div>
                        <div class="stats-label">Toplam Sağlayıcı</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['aktif_provider'] ?? 0) ?></div>
                        <div class="stats-label">Aktif Sağlayıcı</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['toplam_islem'] ?? 0) ?></div>
                        <div class="stats-label">Toplam İşlem</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($basarili_oran, 1) ?>%</div>
                        <div class="stats-label">Başarı Oranı</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreleme -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtreleme
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Sağlayıcı Tipi</label>
                        <select name="provider_tipi" class="form-select">
                            <option value="">Tümü</option>
                            <option value="iyzico" <?= $provider_tipi === 'iyzico' ? 'selected' : '' ?>>İyzico</option>
                            <option value="paytr" <?= $provider_tipi === 'paytr' ? 'selected' : '' ?>>PayTR</option>
                            <option value="akbank" <?= $provider_tipi === 'akbank' ? 'selected' : '' ?>>Akbank</option>
                            <option value="yapikredi" <?= $provider_tipi === 'yapikredi' ? 'selected' : '' ?>>Yapı Kredi</option>
                            <option value="qnb" <?= $provider_tipi === 'qnb' ? 'selected' : '' ?>>QNB Finansbank</option>
                            <option value="garanti" <?= $provider_tipi === 'garanti' ? 'selected' : '' ?>>Garanti BBVA</option>
                            <option value="isbank" <?= $provider_tipi === 'isbank' ? 'selected' : '' ?>>İş Bankası</option>
                            <option value="ziraat" <?= $provider_tipi === 'ziraat' ? 'selected' : '' ?>>Ziraat Bankası</option>
                            <option value="vakifbank" <?= $provider_tipi === 'vakifbank' ? 'selected' : '' ?>>VakıfBank</option>
                            <option value="halkbank" <?= $provider_tipi === 'halkbank' ? 'selected' : '' ?>>Halkbank</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durum</label>
                        <select name="aktif_durum" class="form-select">
                            <option value="">Tümü</option>
                            <option value="1" <?= $aktif_durum === '1' ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $aktif_durum === '0' ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Arama</label>
                        <input type="text" name="arama" class="form-control" placeholder="Sağlayıcı adı veya kodu..." value="<?= htmlspecialchars($arama) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sağlayıcı Listesi -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Ödeme Sağlayıcıları
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sağlayıcı</th>
                                <th>Tip</th>
                                <th>Durum</th>
                                <th>Komisyon</th>
                                <th>İşlemler</th>
                                <th>Toplam Tutar</th>
                                <th>Başarı Oranı</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providerlar as $provider): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="provider-logo me-3">
                                            <i class="fas fa-credit-card text-primary"></i>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($provider['provider_adi']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($provider['provider_kodu']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= strtoupper($provider['provider_tipi']) ?></span>
                                </td>
                                <td>
                                    <?php if ($provider['aktif']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                    <?php if ($provider['varsayilan']): ?>
                                        <br><small class="text-primary">Varsayılan</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= number_format($provider['komisyon_orani'], 2) ?>%</strong>
                                    <?php if ($provider['sabit_komisyon'] > 0): ?>
                                        <br><small class="text-muted">+<?= number_format($provider['sabit_komisyon'], 2) ?>₺</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="metric-value"><?= number_format($provider['toplam_islem'] ?? 0) ?></div>
                                    <small class="text-success"><?= number_format($provider['basarili_islem'] ?? 0) ?> başarılı</small>
                                </td>
                                <td>
                                    <strong><?= number_format($provider['toplam_tutar'] ?? 0, 0) ?>₺</strong>
                                    <br><small class="text-muted"><?= number_format($provider['toplam_komisyon'] ?? 0, 0) ?>₺ komisyon</small>
                                </td>
                                <td>
                                    <?php 
                                    $provider_basarili_oran = $provider['toplam_islem'] > 0 ? 
                                        ($provider['basarili_islem'] / $provider['toplam_islem']) * 100 : 0;
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $provider_basarili_oran >= 90 ? 'bg-success' : ($provider_basarili_oran >= 70 ? 'bg-warning' : 'bg-danger') ?>" 
                                             style="width: <?= $provider_basarili_oran ?>%">
                                            <?= number_format($provider_basarili_oran, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (hasDetailedPermission('odeme_provider_duzenle')): ?>
                                        <a href="odeme-provider-duzenle.php?id=<?= $provider['id'] ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasDetailedPermission('odeme_api_test')): ?>
                                        <a href="odeme-api-test.php?provider_id=<?= $provider['id'] ?>" class="btn btn-sm btn-outline-info" title="API Test">
                                            <i class="fas fa-flask"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasDetailedPermission('odeme_raporlar')): ?>
                                        <a href="odeme-raporlari.php?provider_id=<?= $provider['id'] ?>" class="btn btn-sm btn-outline-success" title="Raporlar">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Son İşlemler -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Son Ödeme İşlemleri
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>İşlem No</th>
                                <th>Sağlayıcı</th>
                                <th>Müşteri</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($son_islemler as $islem): ?>
                            <tr>
                                <td>
                                    <code><?= htmlspecialchars($islem['islem_no']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($islem['provider_adi']) ?></span>
                                </td>
                                <td>
                                    <?php if ($islem['musteri_adi']): ?>
                                        <?= htmlspecialchars($islem['musteri_adi'] . ' ' . $islem['musteri_soyadi']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= number_format($islem['tutar'], 2) ?>₺</strong>
                                </td>
                                <td>
                                    <?php
                                    $durum_class = [
                                        'beklemede' => 'bg-warning',
                                        'isleniyor' => 'bg-info',
                                        'basarili' => 'bg-success',
                                        'basarisiz' => 'bg-danger',
                                        'iptal' => 'bg-secondary',
                                        'iade' => 'bg-dark'
                                    ];
                                    $class = $durum_class[$islem['durum']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $class ?>"><?= ucfirst($islem['durum']) ?></span>
                                </td>
                                <td>
                                    <small><?= date('d.m.Y H:i', strtotime($islem['islem_tarihi'])) ?></small>
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

<style>
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-icon {
    font-size: 2.5rem;
    margin-right: 15px;
    opacity: 0.8;
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stats-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.provider-logo {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.metric-value {
    font-size: 1.1rem;
    font-weight: bold;
    color: #495057;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
}

.header-actions .btn {
    margin-left: 10px;
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}
</style>

<?php include 'footer.php'; ?>
