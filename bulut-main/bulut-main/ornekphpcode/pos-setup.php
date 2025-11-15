<?php
/**
 * POS Kurulum Yardımcısı
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/pos-integration.php';

if (!checkAdmin()) { header('Location: login.php'); exit; }
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('pos_yonetimi')) { $_SESSION['error_message']='POS yönetimi yetkiniz bulunmamaktadır.'; header('Location: /error/403.php'); exit; }

$success_message = '';
$error_message = '';
$log_output = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        if ($action === 'install_sql') {
            $sqlFile = __DIR__ . '/../sql/pos_integration_tables.sql';
            if (!file_exists($sqlFile)) throw new Exception('SQL dosyası bulunamadı.');
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
            $success_message = 'POS veritabanı tabloları kuruldu.';
        }
        if ($action === 'seed_demo') {
            // Basit örnek veriler
            executeQuery("INSERT IGNORE INTO pos_terminalleri (terminal_kodu, terminal_adi, terminal_turu, lokasyon, olusturan_kullanici_id) VALUES
                ('RST-01','Restaurant Kasa','restaurant','Restaurant', ?),
                ('BAR-01','Bar Kasa','bar','Lobby Bar', ?)", [$_SESSION['user_id'], $_SESSION['user_id']]);
            $success_message = 'Örnek terminaller eklendi.';
        }

        if ($action === 'seed_room_account') {
            // Mevcut bir oda ve müşteri seç, açık hesap oluştur
            $oda = fetchOne("SELECT id FROM oda_numaralari ORDER BY id ASC LIMIT 1");
            $musteri = fetchOne("SELECT id FROM musteriler ORDER BY id ASC LIMIT 1");
            if (!$oda || !$musteri) {
                throw new Exception('Örnek için en az bir oda ve müşteri kaydı gereklidir.');
            }

            $hesapNo = 'OH-' . $oda['id'] . '-' . date('YmdHis');
            $sql = "INSERT INTO oda_hesaplari (oda_id, musteri_id, rezervasyon_id, hesap_no, acilis_tarihi, toplam_tutar, odenen_tutar, kalan_tutar, durum, odeme_durumu, olusturma_tarihi, guncelleme_tarihi)
                    VALUES (?, ?, NULL, ?, NOW(), 0.00, 0.00, 0.00, 'acik', 'odenmedi', NOW(), NOW())";
            if (executeQuery($sql, [$oda['id'], $musteri['id'], $hesapNo])) {
                $success_message = 'Örnek açık oda hesabı oluşturuldu. Oda ID: ' . $oda['id'] . ', Müşteri ID: ' . $musteri['id'];
            } else {
                throw new Exception('Örnek oda hesabı oluşturulamadı.');
            }
        }

        if ($action === 'seed_room_sale') {
            // Örnek POS satışı: ilk terminal, ilk ürün, ilk açık oda hesabı (yoksa oluştur)
            $terminal = fetchOne("SELECT id FROM pos_terminalleri ORDER BY id ASC LIMIT 1");
            if (!$terminal) { throw new Exception('Önce örnek terminal ekleyin (Örnek Verileri Ekle).'); }

            $urun = fetchOne("SELECT id, urun_adi, birim_fiyat, kdv_orani FROM pos_menu_urunleri ORDER BY id ASC LIMIT 1");
            if (!$urun) {
                // Hızlı demo ürünü ekle
                executeQuery("INSERT INTO pos_menu_urunleri (urun_kodu, urun_adi, kategori_id, birim_fiyat, kdv_orani, stok_takibi, aktif) VALUES ('DEM-001','Demo Ürün', 1, 25.00, 10.00, 0, 1)");
                $urun = fetchOne("SELECT id, urun_adi, birim_fiyat, kdv_orani FROM pos_menu_urunleri ORDER BY id ASC LIMIT 1");
            }

            $odaHesap = fetchOne("SELECT oh.id, oh.oda_id, oh.musteri_id FROM oda_hesaplari oh WHERE oh.durum='acik' ORDER BY oh.id ASC LIMIT 1");
            if (!$odaHesap) {
                // Hesap yoksa oluşturmak için örnek oda/müşteri seç
                $oda = fetchOne("SELECT id FROM oda_numaralari ORDER BY id ASC LIMIT 1");
                $musteri = fetchOne("SELECT id FROM musteriler ORDER BY id ASC LIMIT 1");
                if (!$oda || !$musteri) {
                    throw new Exception('Örnek satış için en az bir oda ve müşteri kaydı gereklidir.');
                }
                $hesapNo = 'OH-' . $oda['id'] . '-' . date('YmdHis');
                executeQuery("INSERT INTO oda_hesaplari (oda_id, musteri_id, rezervasyon_id, hesap_no, acilis_tarihi, toplam_tutar, odenen_tutar, kalan_tutar, durum, odeme_durumu, olusturma_tarihi, guncelleme_tarihi) VALUES (?, ?, NULL, ?, NOW(), 0.00, 0.00, 0.00, 'acik', 'odenmedi', NOW(), NOW())", [$oda['id'], $musteri['id'], $hesapNo]);
                $odaHesap = fetchOne("SELECT oh.id, oh.oda_id, oh.musteri_id FROM oda_hesaplari oh WHERE oh.durum='acik' ORDER BY oh.id DESC LIMIT 1");
            }

            $miktar = 2;
            $toplam = $miktar * (float)$urun['birim_fiyat'];
            $kdv = $toplam * ((float)$urun['kdv_orani'] / 100.0);
            $genel = $toplam + $kdv;

            $pos = new POSIntegration($pdo);
            $result = $pos->createSale([
                'terminal_id' => (int)$terminal['id'],
                'kullanici_id' => (int)($_SESSION['user_id'] ?? 1),
                'musteri_id' => (int)$odaHesap['musteri_id'],
                'oda_id' => (int)$odaHesap['oda_id'],
                'satis_turu' => 'oda_hesabi',
                'detaylar' => [[
                    'urun_id' => (int)$urun['id'],
                    'urun_adi' => $urun['urun_adi'],
                    'miktar' => $miktar,
                    'birim_fiyat' => (float)$urun['birim_fiyat'],
                    'kdv_orani' => (float)$urun['kdv_orani'],
                    'kdv_tutari' => $kdv,
                    'toplam_tutar' => $toplam,
                    'genel_toplam' => $genel
                ]],
                'toplam_tutar' => $toplam,
                'kdv_tutari' => $kdv,
                'genel_toplam' => $genel,
                'odenen_tutar' => 0,
            ]);

            if (!($result['success'] ?? false)) {
                throw new Exception('Örnek satış oluşturulamadı: ' . ($result['message'] ?? ''));
            }
            $success_message = 'Örnek POS satışı oluşturuldu. Fatura: ' . $result['fatura_no'];
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Kurulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">POS Kurulum</h4>
            <a href="pos-dashboard.php" class="btn btn-sm btn-secondary">Geri</a>
        </div>

        <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="action" value="install_sql">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-database"></i> SQL Tablolarını Kur</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="action" value="seed_demo">
                    <button class="btn btn-success" type="submit"><i class="fas fa-seedling"></i> Örnek Verileri Ekle</button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="action" value="seed_room_account">
                    <button class="btn btn-info" type="submit"><i class="fas fa-bed"></i> Örnek Oda Hesabı Ekle</button>
                    <small class="text-muted ms-2">(Sistemdeki ilk oda ve ilk müşteri kullanılır)</small>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="action" value="seed_room_sale">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-receipt"></i> Örnek POS Satışı (Oda Hesabı)</button>
                    <small class="text-muted ms-2">(İlk terminal, ilk ürün ve açık oda hesabı kullanılır; yoksa oluşturulur)</small>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


