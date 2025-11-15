-- Multi Otel Modülü için Veritabanı Tabloları
-- Bu dosya mevcut veritabanına multi otel desteği ekler

-- 1. Oteller tablosu oluştur
CREATE TABLE IF NOT EXISTS `oteller` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `otel_adi` varchar(255) NOT NULL,
  `kisa_aciklama` text DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `resim_url` varchar(500) DEFAULT NULL,
  `galeri_resimleri` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ozellikler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sira_no` int(11) DEFAULT 1,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_durum` (`durum`),
  KEY `idx_sira_no` (`sira_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mevcut tablolara otel_id sütunu ekle (eğer yoksa)
-- Oda tipleri tablosuna otel_id ekle
ALTER TABLE `oda_tipleri` 
ADD COLUMN IF NOT EXISTS `otel_id` int(11) NOT NULL DEFAULT 1 AFTER `id`,
ADD KEY IF NOT EXISTS `idx_otel_id` (`otel_id`);

-- Oda numaraları tablosuna otel_id ekle
ALTER TABLE `oda_numaralari` 
ADD COLUMN IF NOT EXISTS `otel_id` int(11) NOT NULL DEFAULT 1 AFTER `id`,
ADD KEY IF NOT EXISTS `idx_otel_id` (`otel_id`);

-- Rezervasyonlar tablosuna otel_id ekle
ALTER TABLE `rezervasyonlar` 
ADD COLUMN IF NOT EXISTS `otel_id` int(11) NOT NULL DEFAULT 1 AFTER `id`,
ADD KEY IF NOT EXISTS `idx_otel_id` (`otel_id`);

-- Müşteriler tablosuna otel_id ekle (opsiyonel - müşteriler birden fazla otelde rezervasyon yapabilir)
-- Bu sütun sadece müşterinin hangi otelde ilk rezervasyon yaptığını gösterir
ALTER TABLE `musteriler` 
ADD COLUMN IF NOT EXISTS `ilk_otel_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY IF NOT EXISTS `idx_ilk_otel_id` (`ilk_otel_id`);

-- 3. Varsayılan otel ekle (mevcut veriler için)
INSERT IGNORE INTO `oteller` (`id`, `otel_adi`, `kisa_aciklama`, `durum`) 
VALUES (1, 'Ana Otel', 'Sistem ana oteli', 'aktif');

-- 4. Mevcut verileri varsayılan otele ata
UPDATE `oda_tipleri` SET `otel_id` = 1 WHERE `otel_id` = 0 OR `otel_id` IS NULL;
UPDATE `oda_numaralari` SET `otel_id` = 1 WHERE `otel_id` = 0 OR `otel_id` IS NULL;
UPDATE `rezervasyonlar` SET `otel_id` = 1 WHERE `otel_id` = 0 OR `otel_id` IS NULL;

-- 5. Multi otel için yeni tablolar

-- Otel yöneticileri tablosu (her otel için farklı yöneticiler)
CREATE TABLE IF NOT EXISTS `otel_yoneticileri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `otel_id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `rol` enum('yonetici','mudur','resepsiyon','temizlik','bakim') DEFAULT 'yonetici',
  `yetkiler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otel_id` (`otel_id`),
  KEY `idx_kullanici_id` (`kullanici_id`),
  UNIQUE KEY `unique_otel_kullanici` (`otel_id`, `kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Otel ayarları tablosu (her otel için farklı ayarlar)
CREATE TABLE IF NOT EXISTS `otel_ayarlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `otel_id` int(11) NOT NULL,
  `anahtar` varchar(100) NOT NULL,
  `deger` text DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `kategori` varchar(50) DEFAULT 'genel',
  `tip` enum('text','textarea','number','boolean','select','file') DEFAULT 'text',
  `secenekler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otel_id` (`otel_id`),
  KEY `idx_anahtar` (`anahtar`),
  UNIQUE KEY `unique_otel_anahtar` (`otel_id`, `anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Otel fiyat politikaları tablosu
CREATE TABLE IF NOT EXISTS `otel_fiyat_politikalari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `otel_id` int(11) NOT NULL,
  `politika_adi` varchar(255) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  `fiyat_carpani` decimal(5,2) DEFAULT 1.00,
  `indirim_yuzdesi` decimal(5,2) DEFAULT 0.00,
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otel_id` (`otel_id`),
  KEY `idx_tarih_araligi` (`baslangic_tarihi`, `bitis_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Foreign key constraints ekle
ALTER TABLE `oda_tipleri` 
ADD CONSTRAINT `fk_oda_tipleri_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `oda_numaralari` 
ADD CONSTRAINT `fk_oda_numaralari_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `rezervasyonlar` 
ADD CONSTRAINT `fk_rezervasyonlar_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `otel_yoneticileri` 
ADD CONSTRAINT `fk_otel_yoneticileri_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_otel_yoneticileri_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `otel_ayarlari` 
ADD CONSTRAINT `fk_otel_ayarlari_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `otel_fiyat_politikalari` 
ADD CONSTRAINT `fk_otel_fiyat_politikalari_otel` FOREIGN KEY (`otel_id`) REFERENCES `oteller` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 7. Varsayılan otel ayarları ekle
INSERT IGNORE INTO `otel_ayarlari` (`otel_id`, `anahtar`, `deger`, `aciklama`, `kategori`) VALUES
(1, 'checkin_saati', '14:00', 'Check-in saati', 'genel'),
(1, 'checkout_saati', '12:00', 'Check-out saati', 'genel'),
(1, 'min_rezervasyon_gunu', '1', 'Minimum rezervasyon günü', 'genel'),
(1, 'max_rezervasyon_gunu', '30', 'Maksimum rezervasyon günü', 'genel'),
(1, 'erken_checkin_ucreti', '0', 'Erken check-in ücreti', 'fiyat'),
(1, 'gec_checkout_ucreti', '0', 'Geç check-out ücreti', 'fiyat'),
(1, 'iptal_politikasi', '24', 'İptal politikası (saat)', 'genel');
