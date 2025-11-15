<?php
// Güvenlik ve gerekli dosyaları dahil et
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/pdf-archive-manager.php';

// Admin kontrolü
requireAdmin();

// PDF Archive Manager'ı başlat
$archiveManager = new PDFArchiveManager($pdo);

$success_message = '';
$error_message = '';

// Rezervasyon ID'si kontrolü
$rezervasyon_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$rezervasyon_id) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Rezervasyon bilgilerini getir
$rezervasyon = fetchOne("
    SELECT r.*, m.ad as musteri_ad, m.soyad as musteri_soyad, m.email as musteri_email, 
           m.telefon as musteri_telefon, ot.oda_tipi_adi, odn.oda_numarasi
    FROM rezervasyonlar r
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id
    WHERE r.id = ?
", [$rezervasyon_id]);

if (!$rezervasyon) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Arşivlenmiş PDF'leri getir
$archived_pdfs = $archiveManager->listPDFs($rezervasyon_id);

// PDF silme işlemi
if (isset($_POST['delete_pdf']) && isset($_POST['pdf_id'])) {
    $pdf_id = intval($_POST['pdf_id']);
    
    if ($archiveManager->deletePDF($pdf_id)) {
        $success_message = 'PDF başarıyla silindi.';
        // Listeyi yenile
        $archived_pdfs = $archiveManager->listPDFs($rezervasyon_id);
    } else {
        $error_message = 'PDF silinirken bir hata oluştu.';
    }
}

// Sayfa başlığı
$page_title = 'PDF Arşivi';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">PDF Arşivi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rezervasyon-detay.php?id=<?php echo $rezervasyon_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Rezervasyona Dön
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Rezervasyon Bilgileri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Rezervasyon Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Rezervasyon ID:</strong> #<?php echo $rezervasyon['id']; ?></p>
                                <p><strong>Müşteri:</strong> <?php echo htmlspecialchars($rezervasyon['musteri_ad'] . ' ' . $rezervasyon['musteri_soyad']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($rezervasyon['musteri_email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Giriş Tarihi:</strong> <?php echo date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])); ?></p>
                                <p><strong>Çıkış Tarihi:</strong> <?php echo date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])); ?></p>
                                <p><strong>Oda Tipi:</strong> <?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PDF Arşivi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-archive me-2"></i>Arşivlenmiş PDF'ler
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archived_pdfs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Bu rezervasyon için henüz arşivlenmiş PDF bulunmuyor.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>PDF Tipi</th>
                                        <th>Dosya Adı</th>
                                        <th>Boyut</th>
                                        <th>Oluşturma Tarihi</th>
                                        <th>Oluşturan Admin</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archived_pdfs as $pdf): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $pdf['pdf_type'] == 'voucher' ? 'primary' : 'secondary'; ?>">
                                                <i class="fas fa-<?php echo $pdf['pdf_type'] == 'voucher' ? 'ticket-alt' : 'file-contract'; ?> me-1"></i>
                                                <?php echo $pdf['pdf_type'] == 'voucher' ? 'Voucher' : 'Sözleşme'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($pdf['file_name']); ?></td>
                                        <td><?php echo number_format($pdf['file_size'] / 1024, 2); ?> KB</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($pdf['created_at'])); ?></td>
                                        <td>
                                            <?php if ($pdf['admin_id']): ?>
                                                <span class="text-muted">Admin #<?php echo $pdf['admin_id']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Sistem</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="pdf-archive-download.php?id=<?php echo $pdf['id']; ?>" 
                                                   class="btn btn-outline-primary" target="_blank" title="PDF'i Görüntüle">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $pdf['id']; ?>)" title="PDF'i Sil">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Silme Onayı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Bu PDF'i silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="pdf_id" id="deletePdfId">
                        <button type="submit" name="delete_pdf" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(pdfId) {
            document.getElementById('deletePdfId').value = pdfId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>