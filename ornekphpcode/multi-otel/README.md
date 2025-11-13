# Multi Otel Modülü

Bu modül, mevcut otel rezervasyon sistemine multi otel desteği ekler. Kullanıcılar birden fazla otel yönetebilir ve her otel için ayrı rezervasyon sistemi kullanabilir.

## 🏨 Özellikler

### Temel Özellikler
- **Çoklu Otel Yönetimi**: Birden fazla otel ekleme ve yönetme
- **Otel Bazlı Rezervasyon**: Her otel için ayrı rezervasyon sistemi
- **Otel Seçimi**: Rezervasyon sırasında otel seçimi
- **Otel İstatistikleri**: Her otel için ayrı istatistikler
- **Otel Bazlı Oda Yönetimi**: Her otel için ayrı oda tipleri ve numaraları

### Gelişmiş Özellikler
- **Çoklu Oda Rezervasyonu**: Tek seferde birden fazla oda rezervasyonu
- **Otel Bazlı Fiyatlandırma**: Her otel için farklı fiyat politikaları
- **Otel Bazlı Ayarlar**: Her otel için özel ayarlar
- **Otel Yönetici Yetkileri**: Otel bazlı yetki yönetimi

## 📁 Dosya Yapısı

```
admin/multi-otel/
├── includes/
│   ├── multi-otel-functions.php      # Multi otel fonksiyonları
│   └── multi-otel-sidebar.php        # Multi otel sidebar
├── ajax/
│   └── switch-hotel.php              # Otel değiştirme AJAX
├── sql/
│   └── multi-hotel-tables.sql        # Veritabanı tabloları
├── index.php                         # Ana dashboard
├── oteller.php                       # Otel listesi
├── otel-ekle.php                     # Yeni otel ekleme
├── otel-duzenle.php                  # Otel düzenleme
├── rezervasyonlar.php                # Rezervasyon listesi
├── rezervasyon-ekle-multi.php        # Çoklu oda rezervasyon
└── README.md                         # Bu dosya
```

## 🚀 Kurulum

### 1. Veritabanı Kurulumu

Önce veritabanı tablolarını oluşturun:

```sql
-- admin/multi-otel/sql/multi-hotel-tables.sql dosyasını çalıştırın
```

### 2. Dosya Yapısı

Multi otel modülü dosyalarını `admin/multi-otel/` klasörüne kopyalayın.

### 3. Yetki Ayarları

Kullanıcıların multi otel modülüne erişim yetkisi olması için gerekli yetkileri ekleyin:

```sql
-- Gerekli yetkiler
INSERT INTO yetkiler (yetki_adi, aciklama) VALUES 
('otel_goruntule', 'Otel görüntüleme'),
('otel_ekle', 'Otel ekleme'),
('otel_duzenle', 'Otel düzenleme'),
('otel_sil', 'Otel silme');
```

### 4. Kullanıcı Yetkileri

Kullanıcılara otel yönetimi yetkilerini verin:

```sql
-- Örnek: Kullanıcı ID 1'e tüm otel yetkilerini ver
INSERT INTO kullanici_yetkileri (kullanici_id, yetki_id) 
SELECT 1, id FROM yetkiler WHERE yetki_adi IN ('otel_goruntule', 'otel_ekle', 'otel_duzenle', 'otel_sil');
```

## 🔧 Kullanım

### Otel Ekleme

1. **Otel Listesi**: `admin/multi-otel/oteller.php`
2. **Yeni Otel**: "Yeni Otel Ekle" butonuna tıklayın
3. **Otel Bilgileri**: Gerekli bilgileri doldurun
4. **Kaydet**: Otel bilgilerini kaydedin

### Rezervasyon Yapma

1. **Multi Otel Dashboard**: `admin/multi-otel/index.php`
2. **Çoklu Oda Rezervasyon**: "Çoklu Oda Rezervasyon" butonuna tıklayın
3. **Otel Seçimi**: Rezervasyon yapılacak oteli seçin
4. **Müşteri Bilgileri**: Müşteri bilgilerini girin
5. **Oda Ekleme**: "Yeni Oda Ekle" ile odaları ekleyin
6. **Rezervasyon**: Rezervasyonu tamamlayın

### Otel Değiştirme

1. **Sidebar**: Sol menüden "Otel Değiştir" dropdown'ını kullanın
2. **Otel Seçimi**: Yönetmek istediğiniz oteli seçin
3. **Otomatik Yönlendirme**: Sistem otomatik olarak seçilen otele geçer

## 📊 Özellikler Detayı

### Otel Yönetimi

- **Otel Bilgileri**: Ad, açıklama, adres, iletişim bilgileri
- **Otel Durumu**: Aktif/Pasif durum yönetimi
- **Otel Sıralaması**: Otel listesinde sıralama
- **Otel Ayarları**: Her otel için özel ayarlar

### Rezervasyon Sistemi

- **Otel Bazlı Rezervasyon**: Her otel için ayrı rezervasyon sistemi
- **Çoklu Oda Desteği**: Tek seferde birden fazla oda rezervasyonu
- **Otel Seçimi**: Rezervasyon sırasında otel seçimi
- **Otel Bazlı Fiyatlandırma**: Her otel için farklı fiyat politikaları

### İstatistikler

- **Otel Bazlı İstatistikler**: Her otel için ayrı istatistikler
- **Rezervasyon Sayıları**: Toplam, bugünkü, aktif konaklama
- **Gelir Takibi**: Otel bazlı gelir istatistikleri
- **Doluluk Oranı**: Otel bazlı doluluk oranı

## 🔒 Güvenlik

### CSRF Koruması
Tüm formlar CSRF token ile korunur.

### XSS Koruması
Tüm kullanıcı girdileri XSS saldırılarına karşı korunur.

### Yetki Kontrolü
Her işlem için detaylı yetki kontrolü yapılır.

## 🐛 Sorun Giderme

### Yaygın Sorunlar

1. **Otel Görünmüyor**
   - Kullanıcının otel yetkilerini kontrol edin
   - `otel_yoneticileri` tablosunu kontrol edin

2. **Rezervasyon Oluşturulamıyor**
   - Otel seçiminin yapıldığından emin olun
   - Oda tiplerinin otel ile eşleştiğini kontrol edin

3. **Fiyat Hesaplanamıyor**
   - Oda tipi fiyatlarının tanımlandığından emin olun
   - Tarih formatlarının doğru olduğunu kontrol edin

### Log Dosyaları

Hata durumlarında log dosyalarını kontrol edin:
- `logs/error_*.log`
- `logs/api.log`

## 📝 Güncellemeler

### v1.0.0 (İlk Sürüm)
- Multi otel desteği
- Çoklu oda rezervasyonu
- Otel bazlı yönetim
- Dashboard ve istatistikler

## 🤝 Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit yapın (`git commit -m 'Add amazing feature'`)
4. Push yapın (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır.

## 📞 Destek

Sorularınız için:
- Email: support@example.com
- Dokümantasyon: [Link]
- Issue Tracker: [Link]

---

**Not**: Bu modül mevcut otel rezervasyon sistemi ile uyumlu olarak tasarlanmıştır. Kurulum öncesi mevcut sisteminizin yedeğini almanız önerilir.
