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
requireDetailedPermission('odeme_goruntule', 'Ödeme işlemlerini görüntüleme yetkiniz bulunmamaktadır.');

$page_title = "Ödeme İşlemleri";
$current_page = "odeme-islemleri";

// Filtreleme parametreleri
$provider_id = $_GET['provider_id'] ?? '';
$durum = $_GET['durum'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? '';
$tarih_bitis = $_GET['tarih_bitis'] ?? '';
$arama = $_GET['arama'] ?? '';
$sayfa = intval($_GET['sayfa'] ?? 1);
$limit = 50;
$offset = ($sayfa - 1) * $limit;

// Ödeme sağlayıcıları listesi
$providerlar = fetchAll("SELECT id, provider_adi FROM odeme_providerlari WHERE aktif = 1 ORDER BY provider_adi");

// Filtreleme koşulları
$where_conditions = [];
$params = [];

if (!empty($provider_id)) {
    $where_conditions[] = "oi.provider_id = ?";
    $params[] = $provider_id;
}

if (!empty($durum)) {
    $where_conditions[] = "oi.durum = ?";
    $params[] = $durum;
}

if (!empty($tarih_baslangic)) {
    $where_conditions[] = "DATE(oi.islem_tarihi) >= ?";
    $params[] = $tarih_baslangic;
}

if (!empty($tarih_bitis)) {
    $where_conditions[] = "DATE(oi.islem_tarihi) <= ?";
    $params[] = $tarih_bitis;
}

if (!empty($arama)) {
    $where_conditions[] = "(oi.islem_no LIKE ? OR oi.kart_sahibi LIKE ? OR r.rezervasyon_kodu LIKE ? OR m.ad LIKE ? OR m.soyad LIKE ?)";
    $search_term = "%$arama%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Toplam kayıt sayısı
$toplam_kayit = fetchOne("
    SELECT COUNT(*) as toplam
    FROM odeme_islemleri oi
    LEFT JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN rezervasyonlar r ON oi.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON oi.musteri_id = m.id
    $where_clause
", $params)['toplam'];

$toplam_sayfa = ceil($toplam_kayit / $limit);

// Ödeme işlemlerini getir
$islemler = fetchAll("
    SELECT 
        oi.*,
        p.provider_adi,
        r.rezervasyon_kodu,
        m.ad as musteri_adi,
        m.soyad as musteri_soyadi,
        m.telefon as musteri_telefon,
        m.email as musteri_email
    FROM odeme_islemleri oi
    LEFT JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN rezervasyonlar r ON oi.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON oi.musteri_id = m.id
    $where_clause
    ORDER BY oi.islem_tarihi DESC
    LIMIT $limit OFFSET $offset
", $params);

// İstatistikler
$istatistikler = fetchOne("
    SELECT 
        COUNT(*) as toplam_islem,
        COUNT(CASE WHEN durum = 'basarili' THEN 1 END) as basarili_islem,
        COUNT(CASE WHEN durum = 'basarisiz' THEN 1 END) as basarisiz_islem,
        COUNT(CASE WHEN durum = 'beklemede' THEN 1 END) as beklemede_islem,
        COUNT(CASE WHEN durum = 'iptal' THEN 1 END) as iptal_islem,
        SUM(CASE WHEN durum = 'basarili' THEN tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN durum = 'basarili' THEN komisyon_tutari ELSE 0 END) as toplam_komisyon,
        AVG(CASE WHEN durum = 'basarili' THEN tutar ELSE NULL END) as ortalama_tutar
    FROM odeme_islemleri oi
    LEFT JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN rezervasyonlar r ON oi.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON oi.musteri_id = m.id
    $where_clause
", $params);

// Başarı oranı hesapla
$basarili_oran = $istatistikler['toplam_islem'] > 0 ? 
    ($istatistikler['basarili_islem'] / $istatistikler['toplam_islem']) * 100 : 0;

include 'header.php';
?>

<div class="desktop-container">
    <div class="desktop-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title">
                    <i class="fas fa-list-alt me-2"></i>
                    Ödeme İşlemleri
                </h1>
                <p class="page-subtitle mb-0">Tüm ödeme işlemlerini görüntüleyin ve yönetin</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <?php if (hasDetailedPermission('odeme_raporlar')): ?>
                    <a href="odeme-raporlari.php" class="btn btn-outline-success">
                        <i class="fas fa-chart-bar"></i> Raporlar
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel'e Aktar
                    </button>
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['basarili_islem'] ?? 0) ?></div>
                        <div class="stats-label">Başarılı İşlem</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['basarisiz_islem'] ?? 0) ?></div>
                        <div class="stats-label">Başarısız İşlem</div>
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
                    <i class="fas fa-filter me-2"></i>Filtreleme ve Arama
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Sağlayıcı</label>
                        <select name="provider_id" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($providerlar as $provider): ?>
                            <option value="<?= $provider['id'] ?>" <?= $provider_id == $provider['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($provider['provider_adi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Durum</label>
                        <select name="durum" class="form-select">
                            <option value="">Tümü</option>
                            <option value="beklemede" <?= $durum === 'beklemede' ? 'selected' : '' ?>>Beklemede</option>
                            <option value="isleniyor" <?= $durum === 'isleniyor' ? 'selected' : '' ?>>İşleniyor</option>
                            <option value="basarili" <?= $durum === 'basarili' ? 'selected' : '' ?>>Başarılı</option>
                            <option value="basarisiz" <?= $durum === 'basarisiz' ? 'selected' : '' ?>>Başarısız</option>
                            <option value="iptal" <?= $durum === 'iptal' ? 'selected' : '' ?>>İptal</option>
                            <option value="iade" <?= $durum === 'iade' ? 'selected' : '' ?>>İade</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="tarih_baslangic" class="form-control" value="<?= htmlspecialchars($tarih_baslangic) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="tarih_bitis" class="form-control" value="<?= htmlspecialchars($tarih_bitis) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Arama</label>
                        <input type="text" name="arama" class="form-control" placeholder="İşlem no, müşteri, rezervasyon..." value="<?= htmlspecialchars($arama) ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ödeme İşlemleri Listesi -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Ödeme İşlemleri
                    <span class="badge bg-secondary ms-2"><?= number_format($toplam_kayit) ?> kayıt</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($islemler)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Ödeme işlemi bulunamadı</h5>
                    <p class="text-muted">Belirtilen kriterlere uygun ödeme işlemi bulunmamaktadır.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="islemlerTable">
                        <thead>
                            <tr>
                                <th>İşlem No</th>
                                <th>Sağlayıcı</th>
                                <th>Müşteri</th>
                                <th>Rezervasyon</th>
                                <th>Tutar</th>
                                <th>Taksit</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($islemler as $islem): ?>
                            <tr>
                                <td>
                                    <code><?= htmlspecialchars($islem['islem_no']) ?></code>
                                    <?php if ($islem['provider_islem_id']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($islem['provider_islem_id']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($islem['provider_adi']) ?></span>
                                </td>
                                <td>
                                    <?php if ($islem['musteri_adi']): ?>
                                        <div>
                                            <strong><?= htmlspecialchars($islem['musteri_adi'] . ' ' . $islem['musteri_soyadi']) ?></strong>
                                            <?php if ($islem['musteri_telefon']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($islem['musteri_telefon']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($islem['rezervasyon_kodu']): ?>
                                        <a href="rezervasyon-detay.php?id=<?= $islem['rezervasyon_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($islem['rezervasyon_kodu']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= number_format($islem['tutar'], 2) ?>₺</strong>
                                        <?php if ($islem['komisyon_tutari'] > 0): ?>
                                            <br><small class="text-muted">Komisyon: <?= number_format($islem['komisyon_tutari'], 2) ?>₺</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($islem['taksit_sayisi'] > 1): ?>
                                        <span class="badge bg-warning"><?= $islem['taksit_sayisi'] ?> Taksit</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Peşin</span>
                                    <?php endif; ?>
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
                                    <?php if ($islem['hata_mesaji']): ?>
                                        <br><small class="text-danger" title="<?= htmlspecialchars($islem['hata_mesaji']) ?>">
                                            <i class="fas fa-exclamation-triangle"></i> Hata
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <small><?= date('d.m.Y', strtotime($islem['islem_tarihi'])) ?></small>
                                        <br><small class="text-muted"><?= date('H:i:s', strtotime($islem['islem_tarihi'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info" onclick="showDetails(<?= $islem['id'] ?>)" title="Detaylar">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (hasDetailedPermission('odeme_iade') && $islem['durum'] === 'basarili'): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="refundPayment(<?= $islem['id'] ?>)" title="İade">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sayfalama -->
                <?php if ($toplam_sayfa > 1): ?>
                <nav aria-label="Sayfa navigasyonu" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($sayfa > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $sayfa - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $baslangic = max(1, $sayfa - 2);
                        $bitis = min($toplam_sayfa, $sayfa + 2);
                        
                        for ($i = $baslangic; $i <= $bitis; $i++):
                        ?>
                        <li class="page-item <?= $i == $sayfa ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($sayfa < $toplam_sayfa): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $sayfa + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- İşlem Detay Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">İşlem Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(islemId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const content = document.getElementById('detailsContent');
    
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Yükleniyor...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // AJAX ile detayları getir
    fetch(`ajax/get-payment-details.php?id=${islemId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Detaylar yüklenirken hata oluştu.
                </div>
            `;
        });
}

function refundPayment(islemId) {
    if (confirm('Bu ödemeyi iade etmek istediğinizden emin misiniz?')) {
        // İade işlemi için form gönder
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'ajax/refund-payment.php';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?= generateCSRFToken() ?>';
        
        const islemIdInput = document.createElement('input');
        islemIdInput.type = 'hidden';
        islemIdInput.name = 'islem_id';
        islemIdInput.value = islemId;
        
        form.appendChild(csrfToken);
        form.appendChild(islemIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.open('ajax/export-payments.php?' + params.toString(), '_blank');
}
</script>

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

.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.pagination .page-link {
    color: #667eea;
    border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #667eea;
    border-color: #667eea;
}

.pagination .page-link:hover {
    color: #5a6fd8;
    background-color: #e9ecef;
    border-color: #dee2e6;
}
</style>

<?php include 'footer.php'; ?>
