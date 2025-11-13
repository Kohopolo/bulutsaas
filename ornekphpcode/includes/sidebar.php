<?php
// Detaylı yetki fonksiyonlarını dahil et
require_once __DIR__ . '/../../includes/detailed_permission_functions.php';

// Aktif sayfa kontrolü için
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = dirname($_SERVER['PHP_SELF']);

// Cache sistemi kaldırıldı - doğrudan yetki kontrolleri kullanılıyor

// Submenu açık olup olmayacağını belirle
$rezervasyon_pages = ['rezervasyonlar.php', 'rezervasyon-ekle.php', 'rezervasyon-detay.php', 'check-in-out.php', 'rezervasyon-iadeleri.php', 'silinen-rezervasyonlar.php'];
$oda_pages = ['oda-tipleri.php', 'oda-tipi-ekle.php', 'oda-tipi-duzenle.php', 'oda-numaralari.php', 'musaitlik-yonetimi.php'];
$fiyat_pages = ['fiyat-yonetimi.php', 'kampanya-fiyatlari.php', 'sezonluk-fiyatlar.php', 'ozel-fiyatlar.php'];
$resepsiyon_pages = ['resepsiyon.php', 'resepsiyon-ana-ekran.php', 'resepsiyon-checkin.php', 'resepsiyon-checkout.php', 'resepsiyon-hizli-rezervasyon.php', 'resepsiyon-oda-yonetimi.php', 'resepsiyon-misafir-hizmetleri.php', 'resepsiyon-raporlar.php', 'resepsiyon-rezervasyonlar.php', 'resepsiyon-odalar.php', 'resepsiyon-doluluk-orani.php'];
$housekeeping_pages = ['housekeeping-dashboard.php', 'housekeeping-gorev-olustur.php', 'housekeeping-oda-temizlik.php', 'housekeeping-kalite-kontrol.php', 'housekeeping-raporlar.php', 'housekeeping-personel-yonetimi.php'];
$fnb_pages = ['fnb-dashboard.php', 'fnb-siparis-al.php', 'fnb-siparis-yonetimi.php', 'fnb-menu-yonetimi.php', 'fnb-stok-yonetimi.php', 'fnb-raporlar.php', 'fnb-paket-yonetimi.php'];
$teknik_servis_pages = ['teknik-servis-dashboard.php', 'teknik-servis-talep-olustur.php', 'teknik-servis-talep-listesi.php', 'teknik-servis-talep-detay.php', 'teknik-servis-talep-duzenle.php', 'teknik-servis-ekipman-yonetimi.php', 'teknik-servis-raporlar.php'];
$ik_pages = ['ik-dashboard.php', 'ik-personel-listesi.php', 'ik-personel-ekle.php', 'ik-personel-duzenle.php', 'ik-izin-talepleri.php', 'ik-devam-kayitlari.php', 'ik-performans-degerlendirme.php', 'ik-maas-yonetimi.php', 'ik-raporlar.php'];
$muhasebe_pages = ['muhasebe-dashboard.php', 'muhasebe-hesap-plani.php', 'muhasebe-fis-olustur.php', 'muhasebe-fis-listesi.php', 'muhasebe-fatura-yonetimi.php', 'muhasebe-raporlar.php', 'muhasebe-kapanis.php'];
$satin_alma_pages = ['satin-alma-dashboard.php', 'satin-alma-talep-olustur.php', 'satin-alma-talep-listesi.php', 'satin-alma-tedarikci-yonetimi.php', 'satin-alma-siparis-yonetimi.php', 'satin-alma-raporlar.php'];
$kanal_pages = ['kanal-listesi.php', 'kanal-ekle.php', 'kanal-duzenle.php', 'kanal-performans.php', 'kanal-analiz.php', 'kanal-senkronizasyon.php', 'kanal-api-test.php', 'kanal-komisyon-yonetimi.php', 'kanal-fiyat-senkronizasyon.php', 'kanal-rezervasyon-takibi.php', 'kanal-raporlama.php', 'kanal-entegrasyon-test.php'];
$odeme_pages = ['odeme-yonetimi.php', 'odeme-islemleri.php', 'odeme-raporlari.php', 'odeme-provider-ekle.php', 'odeme-provider-duzenle.php', 'odeme-api-test.php', 'odeme-webhook-yonetimi.php', 'odeme-ssl-kontrol.php', 'odeme-guvenlik-yonetimi.php', 'odeme-guvenlik-monitoring.php', 'odeme-dil-ayarlari.php', 'odeme-komisyon-yonetimi.php', 'odeme-iade-yonetimi.php', 'odeme-backup-yonetimi.php', 'odeme-performans-yonetimi.php', 'odeme-compliance-yonetimi.php', 'odeme-log-yonetimi.php', 'odeme-test-yonetimi.php', 'odeme-api-dokumantasyon.php'];
$rezervasyon_active = in_array($current_page, $rezervasyon_pages);
$oda_active = in_array($current_page, $oda_pages);
$fiyat_active = in_array($current_page, $fiyat_pages);
$resepsiyon_active = in_array($current_page, $resepsiyon_pages);
$housekeeping_active = in_array($current_page, $housekeeping_pages);
$fnb_active = in_array($current_page, $fnb_pages);
$teknik_servis_active = in_array($current_page, $teknik_servis_pages);
$ik_active = in_array($current_page, $ik_pages);
$muhasebe_active = in_array($current_page, $muhasebe_pages);
$satin_alma_active = in_array($current_page, $satin_alma_pages);
$kanal_active = in_array($current_page, $kanal_pages);
$odeme_active = in_array($current_page, $odeme_pages);
?>

<!-- Sidebar -->
<nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-hotel me-2"></i>Otel Admin</h4>
    </div>
    <ul class="list-unstyled components">
        <?php if (hasDetailedPermission('dashboard_goruntule')): ?>
        <li <?php echo ($current_page == 'index.php') ? 'class="active"' : ''; ?>>
            <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('resepsiyon')): ?>
        <li <?php echo $resepsiyon_active ? 'class="active"' : ''; ?>>
            <a href="#resepsiyonSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $resepsiyon_active ? 'true' : 'false'; ?>">
                <i class="fas fa-concierge-bell"></i> Resepsiyon
            </a>
            <ul class="collapse list-unstyled <?php echo $resepsiyon_active ? 'show' : ''; ?>" id="resepsiyonSubmenu">
                <?php if (hasDetailedPermission('resepsiyon_dashboard')): ?>
                <li <?php echo ($current_page == 'resepsiyon.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon.php">Resepsiyon Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasModulePermission('resepsiyon')): ?>
                <li <?php echo ($current_page == 'resepsiyon-ana-ekran.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-ana-ekran.php">
                        <i class="fas fa-desktop me-1"></i>Ana Ekran
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_checkin_islemleri')): ?>
                <li <?php echo ($current_page == 'resepsiyon-checkin.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-checkin.php">Check-In İşlemleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_checkout_islemleri')): ?>
                <li <?php echo ($current_page == 'resepsiyon-checkout.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-checkout.php">Check-Out İşlemleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_hizli_rezervasyon')): ?>
                <li <?php echo ($current_page == 'resepsiyon-hizli-rezervasyon.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-hizli-rezervasyon.php">Hızlı Rezervasyon</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_oda_yonetimi')): ?>
                <li <?php echo ($current_page == 'resepsiyon-oda-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-oda-yonetimi.php">Oda Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_misafir_hizmetleri')): ?>
                <li <?php echo ($current_page == 'resepsiyon-misafir-hizmetleri.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-misafir-hizmetleri.php">Misafir Hizmetleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_gunluk_raporlar')): ?>
                <li <?php echo ($current_page == 'resepsiyon-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-raporlar.php">Günlük Raporlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_gunluk_raporlar')): ?>
                <li <?php echo ($current_page == 'resepsiyon-doluluk-orani.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-doluluk-orani.php">
                        <i class="fas fa-chart-pie me-1"></i>Doluluk Oranı (OCC)
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_rezervasyonlar')): ?>
                <li <?php echo ($current_page == 'resepsiyon-rezervasyonlar.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-rezervasyonlar.php">Rezervasyonlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('resepsiyon_odalar')): ?>
                <li <?php echo ($current_page == 'resepsiyon-odalar.php') ? 'class="active"' : ''; ?>>
                    <a href="resepsiyon-odalar.php">Odalar</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('housekeeping')): ?>
        <li <?php echo $housekeeping_active ? 'class="active"' : ''; ?>>
            <a href="#housekeepingSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $housekeeping_active ? 'true' : 'false'; ?>">
                <i class="fas fa-broom"></i> Housekeeping
            </a>
            <ul class="collapse list-unstyled <?php echo $housekeeping_active ? 'show' : ''; ?>" id="housekeepingSubmenu">
                <?php if (hasDetailedPermission('housekeeping_dashboard')): ?>
                <li <?php echo ($current_page == 'housekeeping-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('housekeeping_gorev_olustur')): ?>
                <li <?php echo ($current_page == 'housekeeping-gorev-olustur.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-gorev-olustur.php">Görev Oluştur</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('housekeeping_oda_temizlik')): ?>
                <li <?php echo ($current_page == 'housekeeping-oda-temizlik.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-oda-temizlik.php">Oda Temizlik</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('housekeeping_kalite_kontrol')): ?>
                <li <?php echo ($current_page == 'housekeeping-kalite-kontrol.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-kalite-kontrol.php">Kalite Kontrol</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('housekeeping_raporlar')): ?>
                <li <?php echo ($current_page == 'housekeeping-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('housekeeping_personel_yonetimi')): ?>
                <li <?php echo ($current_page == 'housekeeping-personel-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="housekeeping-personel-yonetimi.php">Personel Yönetimi</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('fnb')): ?>
        <li <?php echo $fnb_active ? 'class="active"' : ''; ?>>
            <a href="#fnbSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $fnb_active ? 'true' : 'false'; ?>">
                <i class="fas fa-utensils"></i> F&B
            </a>
            <ul class="collapse list-unstyled <?php echo $fnb_active ? 'show' : ''; ?>" id="fnbSubmenu">
                <?php if (hasDetailedPermission('fnb_dashboard')): ?>
                <li <?php echo ($current_page == 'fnb-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_siparis_al')): ?>
                <li <?php echo ($current_page == 'fnb-siparis-al.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-siparis-al.php">Sipariş Al</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_siparis_yonetimi')): ?>
                <li <?php echo ($current_page == 'fnb-siparis-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-siparis-yonetimi.php">Sipariş Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_menu_yonetimi')): ?>
                <li <?php echo ($current_page == 'fnb-menu-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-menu-yonetimi.php">Menü Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_stok_yonetimi')): ?>
                <li <?php echo ($current_page == 'fnb-stok-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-stok-yonetimi.php">Stok Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_raporlar')): ?>
                <li <?php echo ($current_page == 'fnb-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('fnb_paket_yonetimi')): ?>
                <li <?php echo ($current_page == 'fnb-paket-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="fnb-paket-yonetimi.php">Paket Yönetimi</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('teknik_servis')): ?>
        <li <?php echo $teknik_servis_active ? 'class="active"' : ''; ?>>
            <a href="#teknikServisSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $teknik_servis_active ? 'true' : 'false'; ?>">
                <i class="fas fa-tools"></i> Teknik Servis
            </a>
            <ul class="collapse list-unstyled <?php echo $teknik_servis_active ? 'show' : ''; ?>" id="teknikServisSubmenu">
                <?php if (hasDetailedPermission('teknik_servis_dashboard')): ?>
                <li <?php echo ($current_page == 'teknik-servis-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="teknik-servis-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('teknik_servis_talep_olustur')): ?>
                <li <?php echo ($current_page == 'teknik-servis-talep-olustur.php') ? 'class="active"' : ''; ?>>
                    <a href="teknik-servis-talep-olustur.php">Talep Oluştur</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('teknik_servis_talep_listesi')): ?>
                <li <?php echo ($current_page == 'teknik-servis-talep-listesi.php') ? 'class="active"' : ''; ?>>
                    <a href="teknik-servis-talep-listesi.php">Talep Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('teknik_servis_ekipman_yonetimi')): ?>
                <li <?php echo ($current_page == 'teknik-servis-ekipman-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="teknik-servis-ekipman-yonetimi.php">Ekipman Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('teknik_servis_raporlar')): ?>
                <li <?php echo ($current_page == 'teknik-servis-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="teknik-servis-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('insan_kaynaklari')): ?>
        <li <?php echo $ik_active ? 'class="active"' : ''; ?>>
            <a href="#ikSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $ik_active ? 'true' : 'false'; ?>">
                <i class="fas fa-users-cog"></i> İnsan Kaynakları
            </a>
            <ul class="collapse list-unstyled <?php echo $ik_active ? 'show' : ''; ?>" id="ikSubmenu">
                <?php if (hasDetailedPermission('ik_dashboard')): ?>
                <li <?php echo ($current_page == 'ik-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_personel_listesi')): ?>
                <li <?php echo ($current_page == 'ik-personel-listesi.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-personel-listesi.php">Personel Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_personel_ekle')): ?>
                <li <?php echo ($current_page == 'ik-personel-ekle.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-personel-ekle.php">Personel Ekle</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_izin_talepleri')): ?>
                <li <?php echo ($current_page == 'ik-izin-talepleri.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-izin-talepleri.php">İzin Talepleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_devam_kayitlari')): ?>
                <li <?php echo ($current_page == 'ik-devam-kayitlari.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-devam-kayitlari.php">Devam Kayıtları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_performans_degerlendirme')): ?>
                <li <?php echo ($current_page == 'ik-performans-degerlendirme.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-performans-degerlendirme.php">Performans Değerlendirme</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_maas_yonetimi')): ?>
                <li <?php echo ($current_page == 'ik-maas-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-maas-yonetimi.php">Maaş Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ik_raporlar')): ?>
                <li <?php echo ($current_page == 'ik-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="ik-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('muhasebe')): ?>
        <li <?php echo $muhasebe_active ? 'class="active"' : ''; ?>>
            <a href="#muhasebeSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $muhasebe_active ? 'true' : 'false'; ?>">
                <i class="fas fa-calculator"></i> Muhasebe
            </a>
            <ul class="collapse list-unstyled <?php echo $muhasebe_active ? 'show' : ''; ?>" id="muhasebeSubmenu">
                <?php if (hasDetailedPermission('muhasebe_dashboard')): ?>
                <li <?php echo ($current_page == 'muhasebe-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_hesap_plani')): ?>
                <li <?php echo ($current_page == 'muhasebe-hesap-plani.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-hesap-plani.php">Hesap Planı</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_fis_olustur')): ?>
                <li <?php echo ($current_page == 'muhasebe-fis-olustur.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-fis-olustur.php">Fiş Oluştur</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_fis_listesi')): ?>
                <li <?php echo ($current_page == 'muhasebe-fis-listesi.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-fis-listesi.php">Fiş Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_fatura_yonetimi')): ?>
                <li <?php echo ($current_page == 'muhasebe-fatura-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-fatura-yonetimi.php">Fatura Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_raporlar')): ?>
                <li <?php echo ($current_page == 'muhasebe-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('muhasebe_kapanis')): ?>
                <li <?php echo ($current_page == 'muhasebe-kapanis.php') ? 'class="active"' : ''; ?>>
                    <a href="muhasebe-kapanis.php">Kapanış İşlemleri</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('satin_alma')): ?>
        <li <?php echo $satin_alma_active ? 'class="active"' : ''; ?>>
            <a href="#satinAlmaSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $satin_alma_active ? 'true' : 'false'; ?>">
                <i class="fas fa-shopping-cart"></i> Satın Alma
            </a>
            <ul class="collapse list-unstyled <?php echo $satin_alma_active ? 'show' : ''; ?>" id="satinAlmaSubmenu">
                <?php if (hasDetailedPermission('satin_alma_dashboard')): ?>
                <li <?php echo ($current_page == 'satin-alma-dashboard.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('satin_alma_talep_olustur')): ?>
                <li <?php echo ($current_page == 'satin-alma-talep-olustur.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-talep-olustur.php">Talep Oluştur</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('satin_alma_talep_listesi')): ?>
                <li <?php echo ($current_page == 'satin-alma-talep-listesi.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-talep-listesi.php">Talep Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('satin_alma_tedarikci_yonetimi')): ?>
                <li <?php echo ($current_page == 'satin-alma-tedarikci-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-tedarikci-yonetimi.php">Tedarikçi Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('satin_alma_siparis_yonetimi')): ?>
                <li <?php echo ($current_page == 'satin-alma-siparis-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-siparis-yonetimi.php">Sipariş Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('satin_alma_raporlar')): ?>
                <li <?php echo ($current_page == 'satin-alma-raporlar.php') ? 'class="active"' : ''; ?>>
                    <a href="satin-alma-raporlar.php">Raporlar</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
	 <?php if (hasModulePermission('kanal_yonetimi')): ?>
        <li <?php echo $kanal_active ? 'class="active"' : ''; ?>>
            <a href="#kanalSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $kanal_active ? 'true' : 'false'; ?>">
                <i class="fas fa-network-wired"></i> Kanal Yönetimi
            </a>
            <ul class="collapse list-unstyled <?php echo $kanal_active ? 'show' : ''; ?>" id="kanalSubmenu">
                <?php if (hasDetailedPermission('kanal_listele')): ?>
                <li <?php echo ($current_page == 'kanal-listesi.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-listesi.php">Kanal Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_ekle')): ?>
                <li <?php echo ($current_page == 'kanal-ekle.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-ekle.php">Kanal Ekle</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_performans')): ?>
                <li <?php echo ($current_page == 'kanal-performans.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-performans.php">Performans</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_analiz')): ?>
                <li <?php echo ($current_page == 'kanal-analiz.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-analiz.php">Analiz</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_senkronizasyon')): ?>
                <li <?php echo ($current_page == 'kanal-senkronizasyon.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-senkronizasyon.php">Senkronizasyon</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_api_yonetimi')): ?>
                <li <?php echo ($current_page == 'kanal-api-test.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-api-test.php">API Test</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_komisyon')): ?>
                <li <?php echo ($current_page == 'kanal-komisyon-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-komisyon-yonetimi.php">Komisyon Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_fiyat_senkronizasyon')): ?>
                <li <?php echo ($current_page == 'kanal-fiyat-senkronizasyon.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-fiyat-senkronizasyon.php">Fiyat & Stok Senkronizasyonu</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_analiz')): ?>
                <li <?php echo ($current_page == 'kanal-rezervasyon-takibi.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-rezervasyon-takibi.php">Rezervasyon Takibi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kanal_raporlar')): ?>
                <li <?php echo ($current_page == 'kanal-raporlama.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-raporlama.php">Raporlama Sistemi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('sistem_ayarlari_goruntule')): ?>
                <li <?php echo ($current_page == 'kanal-entegrasyon-test.php') ? 'class="active"' : ''; ?>>
                    <a href="kanal-entegrasyon-test.php"><i class="fas fa-vial"></i> Entegrasyon Test</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('odeme_yonetimi')): ?>
        <li <?php echo $odeme_active ? 'class="active"' : ''; ?>>
            <a href="#odemeSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $odeme_active ? 'true' : 'false'; ?>">
                <i class="fas fa-credit-card"></i> Ödeme Yönetimi
            </a>
            <ul class="collapse list-unstyled <?php echo $odeme_active ? 'show' : ''; ?>" id="odemeSubmenu">
                <?php if (hasDetailedPermission('odeme_goruntule')): ?>
                <li <?php echo ($current_page == 'odeme-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-yonetimi.php">Ödeme Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_goruntule')): ?>
                <li <?php echo ($current_page == 'odeme-islemleri.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-islemleri.php">Ödeme İşlemleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_raporlar')): ?>
                <li <?php echo ($current_page == 'odeme-raporlari.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-raporlari.php">Ödeme Raporları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_provider_ekle')): ?>
                <li <?php echo ($current_page == 'odeme-provider-ekle.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-provider-ekle.php">Provider Ekle</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_provider_duzenle')): ?>
                <li <?php echo ($current_page == 'odeme-provider-duzenle.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-provider-duzenle.php">Provider Düzenle</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_api_test')): ?>
                <li <?php echo ($current_page == 'odeme-api-test.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-api-test.php">API Test</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_webhook')): ?>
                <li <?php echo ($current_page == 'odeme-webhook-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-webhook-yonetimi.php">Webhook Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_ssl_kontrol')): ?>
                <li <?php echo ($current_page == 'odeme-ssl-kontrol.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-ssl-kontrol.php">SSL Kontrol</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_guvenlik_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-guvenlik-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-guvenlik-yonetimi.php">Güvenlik Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_guvenlik_monitoring')): ?>
                <li <?php echo ($current_page == 'odeme-guvenlik-monitoring.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-guvenlik-monitoring.php">Güvenlik Monitoring</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_dil_ayarlari')): ?>
                <li <?php echo ($current_page == 'odeme-dil-ayarlari.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-dil-ayarlari.php">Dil Ayarları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_komisyon_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-komisyon-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-komisyon-yonetimi.php">Komisyon Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_iade_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-iade-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-iade-yonetimi.php">İade Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_backup_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-backup-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-backup-yonetimi.php">Backup Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_performans_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-performans-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-performans-yonetimi.php">Performans Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_compliance_goruntule')): ?>
                <li <?php echo ($current_page == 'odeme-compliance-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-compliance-yonetimi.php">Compliance Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_log_goruntule')): ?>
                <li <?php echo ($current_page == 'odeme-log-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-log-yonetimi.php">Log Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_test_yonetimi')): ?>
                <li <?php echo ($current_page == 'odeme-test-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-test-yonetimi.php">Test Yönetimi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('odeme_api_dokumantasyon')): ?>
                <li <?php echo ($current_page == 'odeme-api-dokumantasyon.php') ? 'class="active"' : ''; ?>>
                    <a href="odeme-api-dokumantasyon.php">API Dokümantasyon</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('rezervasyon')): ?>
        <li <?php echo $rezervasyon_active ? 'class="active"' : ''; ?>>
            <a href="#rezervasyonSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $rezervasyon_active ? 'true' : 'false'; ?>">
                <i class="fas fa-calendar-check"></i> Rezervasyonlar
            </a>
            <ul class="collapse list-unstyled <?php echo $rezervasyon_active ? 'show' : ''; ?>" id="rezervasyonSubmenu">
                <?php if (hasDetailedPermission('rezervasyon_goruntule')): ?>
                <li <?php echo ($current_page == 'rezervasyonlar.php') ? 'class="active"' : ''; ?>>
                    <a href="rezervasyonlar.php">Tüm Rezervasyonlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('rezervasyon_ekle')): ?>
                <li <?php echo ($current_page == 'rezervasyon-ekle.php') ? 'class="active"' : ''; ?>>
                    <a href="rezervasyon-ekle.php">Yeni Rezervasyon</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('checkin_checkout')): ?>
                <li <?php echo ($current_page == 'check-in-out.php') ? 'class="active"' : ''; ?>>
                    <a href="check-in-out.php">Check-in / Check-out</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('rezervasyon_iade_goruntule')): ?>
                <li <?php echo ($current_page == 'rezervasyon-iadeleri.php') ? 'class="active"' : ''; ?>>
                    <a href="rezervasyon-iadeleri.php">Rezervasyon İadeleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('rezervasyon_silinen_goruntule')): ?>
                <li <?php echo ($current_page == 'silinen-rezervasyonlar.php') ? 'class="active"' : ''; ?>>
                    <a href="silinen-rezervasyonlar.php">
                        <i class="fas fa-trash-alt me-1"></i>Silinen Rezervasyonlar
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        </li>
        <?php if (hasModulePermission('oda_yonetimi')): ?>
        <li <?php echo $oda_active ? 'class="active"' : ''; ?>>
            <a href="#odaSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $oda_active ? 'true' : 'false'; ?>">
                <i class="fas fa-bed"></i> Oda Yönetimi
            </a>
            <ul class="collapse list-unstyled <?php echo $oda_active ? 'show' : ''; ?>" id="odaSubmenu">
                <?php if (hasDetailedPermission('oda_tipleri_goruntule')): ?>
                <li <?php echo ($current_page == 'oda-tipleri.php') ? 'class="active"' : ''; ?>>
                    <a href="oda-tipleri.php">Oda Tipleri</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('oda_numaralari_goruntule')): ?>
                <li <?php echo ($current_page == 'oda-numaralari.php') ? 'class="active"' : ''; ?>>
                    <a href="oda-numaralari.php">Oda Numaraları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('oda_musaitlik_yonetimi')): ?>
                <li <?php echo ($current_page == 'musaitlik-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="musaitlik-yonetimi.php">Müsaitlik Yönetimi</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('fiyat_yonetimi')): ?>
        <li <?php echo $fiyat_active ? 'class="active"' : ''; ?>>
            <a href="#fiyatSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $fiyat_active ? 'true' : 'false'; ?>">
                <i class="fas fa-tags"></i> Fiyat Yönetimi
            </a>
            <ul class="collapse list-unstyled <?php echo $fiyat_active ? 'show' : ''; ?>" id="fiyatSubmenu">
                <?php if (hasDetailedPermission('fiyat_yonetimi_goruntule')): ?>
                <li <?php echo ($current_page == 'fiyat-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="fiyat-yonetimi.php">Temel Fiyatlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('kampanya_fiyatlari_goruntule')): ?>
                <li <?php echo ($current_page == 'kampanya-fiyatlari.php') ? 'class="active"' : ''; ?>>
                    <a href="kampanya-fiyatlari.php">Kampanya Fiyatları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('sezonluk_fiyatlar_goruntule')): ?>
                <li <?php echo ($current_page == 'sezonluk-fiyatlar.php') ? 'class="active"' : ''; ?>>
                    <a href="sezonluk-fiyatlar.php">Sezonluk Fiyatlar</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('ozel_fiyatlar_goruntule')): ?>
                <li <?php echo ($current_page == 'ozel-fiyatlar.php') ? 'class="active"' : ''; ?>>
                    <a href="ozel-fiyatlar.php">Özel Fiyatlar</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('musteri_goruntule')): ?>
        <li <?php echo ($current_page == 'musteriler.php') ? 'class="active"' : ''; ?>>
            <a href="musteriler.php"><i class="fas fa-users"></i> Müşteriler</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('hizmetler_goruntule')): ?>
        <li <?php echo ($current_page == 'hizmetler.php') ? 'class="active"' : ''; ?>>
            <a href="hizmetler.php"><i class="fas fa-concierge-bell"></i> Hizmetler</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('galeri_goruntule')): ?>
        <li <?php echo ($current_page == 'galeri.php') ? 'class="active"' : ''; ?>>
            <a href="galeri.php"><i class="fas fa-images"></i> Galeri</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('konsept_goruntule')): ?>
        <li <?php echo ($current_page == 'konsept.php') ? 'class="active"' : ''; ?>>
            <a href="konsept.php"><i class="fas fa-lightbulb"></i> Konsept</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('slider_goruntule')): ?>
        <li <?php echo ($current_page == 'slider.php') ? 'class="active"' : ''; ?>>
            <a href="slider.php"><i class="fas fa-images"></i> Slider Yönetimi</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('webp_converter')): ?>
        <li <?php echo ($current_page == 'webp-converter.php') ? 'class="active"' : ''; ?>>
            <a href="webp-converter.php"><i class="fas fa-image"></i> WebP Dönüştürücü</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('mesajlar_goruntule')): ?>
        <li <?php echo ($current_page == 'mesajlar.php') ? 'class="active"' : ''; ?>>
            <a href="mesajlar.php"><i class="fas fa-envelope"></i> Mesajlar</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('raporlar_goruntule')): ?>
        <li <?php echo ($current_page == 'raporlar.php') ? 'class="active"' : ''; ?>>
            <a href="raporlar.php"><i class="fas fa-chart-bar"></i> Raporlar</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('forecast_goruntule')): ?>
        <li <?php echo ($current_page == 'forecast-dashboard.php') ? 'class="active"' : ''; ?>>
            <a href="forecast-dashboard.php"><i class="fas fa-brain"></i> AI Forecast</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('kullanici_yonetimi_goruntule')): ?>
        <li <?php echo ($current_page == 'kullanicilar.php') ? 'class="active"' : ''; ?>>
            <a href="kullanicilar.php"><i class="fas fa-user-cog"></i> Kullanıcılar</a>
        </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
        <li <?php echo ($current_page == 'yetki-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="yetki-yonetimi.php"><i class="fas fa-user-shield"></i> Yetki Yönetimi</a>
        </li>
        <li <?php echo ($current_page == 'test-permissions.php') ? 'class="active"' : ''; ?>>
            <a href="test-permissions.php"><i class="fas fa-vial"></i> Yetki Test</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('pdf_template_goruntule')): ?>
        <li <?php echo ($current_page == 'pdf-template-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="pdf-template-yonetimi.php"><i class="fas fa-file-pdf"></i> PDF Template Yönetimi</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('sistem_ayarlari_goruntule')): ?>
        <li <?php echo ($current_page == 'ayarlar.php') ? 'class="active"' : ''; ?>>
            <a href="ayarlar.php"><i class="fas fa-cog"></i> Ayarlar</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('script_yonetimi_goruntule')): ?>
        <li <?php echo ($current_page == 'script-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="script-yonetimi.php"><i class="fas fa-code"></i> Script Yönetimi</a>
        </li>
        <?php endif; ?>
        <?php if (hasDetailedPermission('ai_settings_view') || hasDetailedPermission('page_builder_view') || hasDetailedPermission('form_builder_view')): ?>
        <li <?php echo (strpos($current_page, 'ai-') !== false || strpos($current_page, 'page-builder') !== false || strpos($current_page, 'form-builder') !== false) ? 'class="active"' : ''; ?>>
            <a href="#aiSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo (strpos($current_page, 'ai-') !== false) ? 'true' : 'false'; ?>">
                <i class="fas fa-robot"></i> AI & Page Builder
            </a>
            <ul class="collapse list-unstyled <?php echo (strpos($current_page, 'ai-') !== false || strpos($current_page, 'page-builder') !== false || strpos($current_page, 'form-builder') !== false) ? 'show' : ''; ?>" id="aiSubmenu">
                <?php if (hasDetailedPermission('ai_settings_view')): ?>
                <li <?php echo ($current_page == 'ai-settings.php') ? 'class="active"' : ''; ?>>
                    <a href="ai-settings.php"><i class="fas fa-cog"></i> AI Ayarları</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('page_builder_view')): ?>
                <li <?php echo ($current_page == 'page-list.php') ? 'class="active"' : ''; ?>>
                    <a href="page-list.php"><i class="fas fa-file-alt"></i> Sayfa Listesi</a>
                </li>
                <li <?php echo ($current_page == 'page-builder-ultimate.php' || $current_page == 'page-builder-v2.php' || $current_page == 'page-builder.php') ? 'class="active"' : ''; ?>>
                    <a href="page-builder-ultimate.php"><i class="fas fa-magic"></i> Ultimate Builder</a>
                </li>
                <?php endif; ?>
                    <?php if (hasDetailedPermission('form_builder_view')): ?>
                    <li <?php echo ($current_page == 'form-builder.php') ? 'class="active"' : ''; ?>>
                        <a href="form-builder.php"><i class="fas fa-wpforms"></i> Form Builder</a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasDetailedPermission('page_builder_view')): ?>
                    <li <?php echo ($current_page == 'menu-manager.php') ? 'class="active"' : ''; ?>>
                        <a href="menu-manager.php"><i class="fas fa-bars"></i> Menü Yöneticisi</a>
                    </li>
                    <?php endif; ?>
                <?php if (hasDetailedPermission('form_submissions_view')): ?>
                <li <?php echo ($current_page == 'form-submissions.php') ? 'class="active"' : ''; ?>>
                    <a href="form-submissions.php"><i class="fas fa-inbox"></i> Form Gönderileri</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php if (hasModulePermission('template')): ?>
        <li <?php echo (strpos($current_page, 'template') !== false && $current_page != 'pdf-template-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="#templateSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo (strpos($current_page, 'template') !== false) ? 'true' : 'false'; ?>">
                <i class="fas fa-paint-brush"></i> Template Yönetimi
            </a>
            <ul class="collapse list-unstyled <?php echo (strpos($current_page, 'template') !== false) ? 'show' : ''; ?>" id="templateSubmenu">
                <?php if (hasDetailedPermission('template_goruntule')): ?>
                <li <?php echo ($current_page == 'template-yonetimi.php') ? 'class="active"' : ''; ?>>
                    <a href="template-yonetimi.php">Template Listesi</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('template_upload')): ?>
                <li <?php echo ($current_page == 'template-upload.php') ? 'class="active"' : ''; ?>>
                    <a href="template-upload.php">Template Yükle</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('template_editor')): ?>
                <li <?php echo ($current_page == 'template-editor.php') ? 'class="active"' : ''; ?>>
                    <a href="template-editor.php">Template Editör</a>
                </li>
                <?php endif; ?>
                <?php if (hasDetailedPermission('template_test')): ?>
                <li <?php echo ($current_page == 'template-test.php') ? 'class="active"' : ''; ?>>
                    <a href="template-test.php">Template Test</a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- KPI Dashboard -->
        <?php if (hasDetailedPermission('kpi_dashboard')): ?>
        <li <?php echo (strpos($current_page, 'kpi-') !== false) ? 'class="active"' : ''; ?>>
            <a href="kpi-dashboard.php">
                <i class="fas fa-chart-line"></i> KPI Dashboard
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasDetailedPermission('kpi_yonetimi')): ?>
        <li <?php echo ($current_page == 'kpi-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="kpi-yonetimi.php">
                <i class="fas fa-cogs"></i> KPI Yönetimi
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasDetailedPermission('performans_metrikleri')): ?>
        <li <?php echo ($current_page == 'performans-metrikleri.php') ? 'class="active"' : ''; ?>>
            <a href="performans-metrikleri.php">
                <i class="fas fa-chart-bar"></i> Performans Metrikleri
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasDetailedPermission('tahminleme_algoritmalari')): ?>
        <li <?php echo ($current_page == 'tahminleme-algoritmalari.php') ? 'class="active"' : ''; ?>>
            <a href="tahminleme-algoritmalari.php">
                <i class="fas fa-brain"></i> Tahminleme Algoritmaları
            </a>
        </li>
        <?php endif; ?>
        
        <!-- POS Yönetimi -->
        <?php if (hasDetailedPermission('pos_yonetimi')): ?>
        <li class="nav-divider">
            <span>POS Yönetimi</span>
        </li>
        <li <?php echo ($current_page == 'pos-dashboard.php') ? 'class="active"' : ''; ?>>
            <a href="pos-dashboard.php">
                <i class="fas fa-cash-register"></i> POS Dashboard
            </a>
        </li>
        <li <?php echo ($current_page == 'pos-setup.php') ? 'class="active"' : ''; ?>>
            <a href="pos-setup.php">
                <i class="fas fa-tools"></i> Kurulum
            </a>
        </li>
        <li <?php echo ($current_page == 'pos-satis-raporu.php') ? 'class="active"' : ''; ?>>
            <a href="pos-satis-raporu.php">
                <i class="fas fa-chart-bar"></i> Satış Raporu
            </a>
        </li>
        <li <?php echo ($current_page == 'pos-menu-yonetimi.php') ? 'class="active"' : ''; ?>>
            <a href="pos-menu-yonetimi.php">
                <i class="fas fa-utensils"></i> Menü Yönetimi
            </a>
        </li>
        <li <?php echo ($current_page == 'pos-oda-hesaplari.php') ? 'class="active"' : ''; ?>>
            <a href="pos-oda-hesaplari.php">
                <i class="fas fa-bed"></i> Oda Hesapları
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Gelir Yönetimi -->
        <?php if (hasDetailedPermission('revenue_management')): ?>
        <li class="nav-divider">
            <span>Gelir Yönetimi</span>
        </li>
        <li <?php echo ($current_page == 'revenue-management.php') ? 'class="active"' : ''; ?>>
            <a href="revenue-management.php">
                <i class="fas fa-coins"></i> Fiyat Önizleme
            </a>
        </li>
        <li <?php echo ($current_page == 'revenue-rules.php') ? 'class="active"' : ''; ?>>
            <a href="revenue-rules.php">
                <i class="fas fa-sliders-h"></i> Fiyat Kuralları
            </a>
        </li>
        <li <?php echo ($current_page == 'revenue-seasons.php') ? 'class="active"' : ''; ?>>
            <a href="revenue-seasons.php">
                <i class="fas fa-sun"></i> Sezon/Etkinlikler
            </a>
        </li>
        <?php endif; ?>
        
        <!-- CRM -->
        <?php if (hasDetailedPermission('crm_modulu')): ?>
        <li class="nav-divider">
            <span>CRM</span>
        </li>
        <li <?php echo ($current_page == 'crm-dashboard.php') ? 'class="active"' : ''; ?>><a href="crm-dashboard.php"><i class="fas fa-user-friends"></i> CRM Dashboard</a></li>
        <li <?php echo ($current_page == 'crm-preferences.php') ? 'class="active"' : ''; ?>><a href="crm-preferences.php"><i class="fas fa-heart"></i> Müşteri Tercihleri</a></li>
        <li <?php echo ($current_page == 'crm-loyalty.php') ? 'class="active"' : ''; ?>><a href="crm-loyalty.php"><i class="fas fa-gift"></i> Sadakat</a></li>
        <li <?php echo ($current_page == 'crm-interactions.php') ? 'class="active"' : ''; ?>><a href="crm-interactions.php"><i class="fas fa-comments"></i> Etkileşimler</a></li>
        <?php endif; ?>

        <!-- Pazarlama & Promosyon -->
        <?php if (hasDetailedPermission('marketing_promotions')): ?>
        <li class="nav-divider">
            <span>Pazarlama & Promosyon</span>
        </li>
        <li <?php echo ($current_page == 'mp-campaigns.php') ? 'class="active"' : ''; ?>><a href="mp-campaigns.php"><i class="fas fa-bullhorn"></i> Kampanyalar</a></li>
        <li <?php echo ($current_page == 'mp-coupons.php') ? 'class="active"' : ''; ?>><a href="mp-coupons.php"><i class="fas fa-ticket-alt"></i> Kuponlar</a></li>
        <li <?php echo ($current_page == 'mp-rules.php') ? 'class="active"' : ''; ?>><a href="mp-rules.php"><i class="fas fa-sliders-h"></i> Kurallar</a></li>
        <?php endif; ?>
        
        <!-- Sistem Yönetimi -->
        <?php if (hasDetailedPermission('sistem_yonetimi')): ?>
        <li class="nav-divider">
            <span>Sistem Yönetimi</span>
        </li>
        <li <?php echo ($current_page == 'system-integration-test.php') ? 'class="active"' : ''; ?>>
            <a href="system-integration-test.php">
                <i class="fas fa-cogs"></i> Sistem Entegrasyonu
            </a>
        </li>
        <li <?php echo ($current_page == 'security-audit.php') ? 'class="active"' : ''; ?>>
            <a href="security-audit.php">
                <i class="fas fa-shield-alt"></i> Güvenlik Denetimi
            </a>
        </li>
        <li <?php echo ($current_page == 'performance-optimization.php') ? 'class="active"' : ''; ?>>
            <a href="performance-optimization.php">
                <i class="fas fa-tachometer-alt"></i> Performans Optimizasyonu
            </a>
        </li>
        <li <?php echo ($current_page == 'backup-system.php') ? 'class="active"' : ''; ?>>
            <a href="backup-system.php">
                <i class="fas fa-database"></i> Yedekleme Sistemi
            </a>
        </li>
        <li <?php echo ($current_page == 'monitoring-alerts.php') ? 'class="active"' : ''; ?>>
            <a href="monitoring-alerts.php">
                <i class="fas fa-heartbeat"></i> İzleme ve Uyarı Sistemi
            </a>
        </li>
        <?php endif; ?>

        <!-- Çoklu Dil ve Para Birimi -->
        <?php if (hasDetailedPermission('multi_language_currency')): ?>
        <li class="nav-divider">
            <span>Çoklu Dil & Para Birimi</span>
        </li>
        <li <?php echo ($current_page == 'mlc-languages.php') ? 'class="active"' : ''; ?>>
            <a href="mlc-languages.php">
                <i class="fas fa-language"></i> Dil Yönetimi
            </a>
        </li>
        <li <?php echo ($current_page == 'mlc-currencies.php') ? 'class="active"' : ''; ?>>
            <a href="mlc-currencies.php">
                <i class="fas fa-coins"></i> Para Birimi
            </a>
        </li>
        <li <?php echo ($current_page == 'mlc-translations.php') ? 'class="active"' : ''; ?>>
            <a href="mlc-translations.php">
                <i class="fas fa-translate"></i> Çeviriler
            </a>
        </li>
        <li <?php echo ($current_page == 'mlc-setup.php') ? 'class="active"' : ''; ?>>
            <a href="mlc-setup.php">
                <i class="fas fa-cogs"></i> Kurulum
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>