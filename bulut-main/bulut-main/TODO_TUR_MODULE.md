# Tur Modülü Geliştirme TODO Listesi

## ✅ Tamamlananlar

### 1. Modeller (✅ TAMAMLANDI)
- [x] **TourRegion** - Tur bölgeleri (Ege, Akdeniz, Bodrum, vb.)
- [x] **TourLocation** - Tur lokasyonları (Yurt İçi, Yurtdışı)
- [x] **TourCity** - Tur şehirleri (İzmir, İstanbul, vb.) - Harita koordinatları ile
- [x] **TourType** - Tur türleri (Kültür, Doğa, vb.)
- [x] **TourVoucherTemplate** - Voucher şablonları
- [x] **Tour** - Ana tur modeli (tüm özellikler dahil)
- [x] **TourDate** - Tur tarihleri (her tarih için ayrı fiyat/kontenjan)
- [x] **TourProgram** - Gün gün tur programı
- [x] **TourImage** - Tur galeri resimleri
- [x] **TourVideo** - Tur videoları (YouTube, Instagram)
- [x] **TourExtraService** - Ekstra hizmetler (kişi başı fiyat)
- [x] **TourRoute** - Tur rotası (harita için şehir şehir)
- [x] **TourReservation** - Tur rezervasyonları
- [x] **TourGuest** - Rezervasyondaki misafirler (ad soyad)
- [x] **TourReservationExtraService** - Rezervasyon ekstra hizmetleri
- [x] **TourPayment** - Tur ödemeleri
- [x] **TourReview** - Tur değerlendirmeleri ve yorumlar
- [x] Admin paneli yapılandırması
- [x] Migration'lar oluşturuldu ve çalıştırıldı

## 🔄 Devam Edenler

### 2. Views ve URL'ler (✅ TAMAMLANDI - 2025-01-XX)
- [x] **Tur CRUD Views:**
  - [x] `tour_list` - Tur listeleme (filtreleme, arama, sayfalama)
  - [x] `tour_detail` - Tur detay sayfası
  - [x] `tour_create` - Tur ekleme (form ile)
  - [x] `tour_update` - Tur düzenleme
  - [x] `tour_delete` - Tur silme (soft delete)
  - [x] `tour_duplicate` - Tur kopyalama

- [x] **Dinamik Yönetim Views:**
  - [x] `tour_region_list/create/update/delete` - Bölge yönetimi
  - [x] `tour_location_list/create/update/delete` - Lokasyon yönetimi
  - [x] `tour_city_list/create/update/delete` - Şehir yönetimi
  - [x] `tour_type_list/create/update/delete` - Tür yönetimi
  - [x] `tour_voucher_template_list/create/update/delete` - Voucher şablon yönetimi

- [x] **Forms:**
  - [x] `TourForm` - Tur ekleme/düzenleme formu
  - [x] `TourRegionForm`, `TourLocationForm`, `TourCityForm`, `TourTypeForm` - Dinamik yönetim formları
  - [x] `TourDateForm`, `TourProgramForm`, vb. - Tur detay formları

- [x] **Tur Detay Views:**
  - [x] `tour_date_add/update/delete` - Tur tarihi yönetimi
  - [x] `tour_program_add/update/delete` - Program günü yönetimi
  - [x] `tour_image_upload/delete` - Resim yükleme
  - [x] `tour_video_add/delete` - Video ekleme
  - [x] `tour_extra_service_add/update/delete` - Ekstra hizmet yönetimi
  - [x] `tour_route_add/update/delete` - Rota yönetimi

- [x] **Rezervasyon Views:**
  - [x] `tour_reservation_list` - Rezervasyon listeleme
  - [x] `tour_reservation_create` - Rezervasyon oluşturma (form ile, misafir formları TODO)
  - [x] `tour_reservation_detail` - Rezervasyon detayı
  - [x] `tour_reservation_update` - Rezervasyon güncelleme
  - [x] `tour_reservation_cancel` - Rezervasyon iptal
  - [x] `tour_reservation_refund` - Rezervasyon iade
  - [x] `tour_reservation_voucher` - Voucher oluşturma ve görüntüleme
  - [x] `tour_reservation_payment` - Ödeme ekleme/güncelleme
  - [x] `tour_toggle_status` - Tur durumunu değiştirme

- [x] **Fiyat Hesaplama:**
  - [x] Tarih bazlı fiyat kontrolü
  - [x] Kampanya fiyat kontrolü
  - [x] Grup fiyat hesaplama
  - [x] Ekstra hizmet fiyat hesaplama
  - [x] Toplam fiyat hesaplama fonksiyonu (calculate_total)

- [x] **URL Routing:**
  - [x] `apps/tenant_apps/tours/urls.py` oluştur
  - [x] `config/urls.py` içine ekle

### 3. Templates (✅ TAMAMLANDI - 2025-01-XX)
- [x] **Tur Yönetimi Templates:**
  - [x] `tours/list.html` - Tur listeleme (filtreleme, arama, sayfalama)
  - [x] `tours/detail.html` - Tur detay sayfası (tab menü ile)
  - [x] `tours/form.html` - Tur ekleme/düzenleme formu

- [x] **Dinamik Yönetim Templates:**
  - [x] `tours/regions/list.html` - Bölge listesi
  - [x] `tours/regions/form.html` - Bölge formu
  - [x] `tours/locations/list.html` - Lokasyon listesi
  - [x] `tours/locations/form.html` - Lokasyon formu
  - [x] `tours/cities/list.html` - Şehir listesi
  - [x] `tours/cities/form.html` - Şehir formu (harita koordinatları ile)
  - [x] `tours/types/list.html` - Tür listesi
  - [x] `tours/types/form.html` - Tür formu
  - [x] `tours/voucher_templates/list.html` - Voucher şablon listesi
  - [x] `tours/voucher_templates/form.html` - Voucher şablon formu

- [x] **Tur Detay Templates:**
  - [x] `tours/dates/form.html` - Tur tarihi formu
  - [x] `tours/programs/form.html` - Program günü formu
  - [x] `tours/images/upload.html` - Resim yükleme
  - [x] `tours/videos/form.html` - Video formu
  - [x] `tours/extra_services/form.html` - Ekstra hizmet formu
  - [x] `tours/routes/form.html` - Rota formu

- [x] **Rezervasyon Templates:**
  - [x] `tours/reservations/list.html` - Rezervasyon listesi
  - [x] `tours/reservations/create.html` - Rezervasyon oluşturma (AJAX ile tarih/fiyat güncelleme)
  - [x] `tours/reservations/detail.html` - Rezervasyon detayı
  - [x] `tours/reservations/form.html` - Rezervasyon düzenleme formu (create.html kullanılıyor)
  - [x] `tours/reservations/voucher.html` - Voucher görüntüleme
  - [x] `tours/reservations/payment.html` - Ödeme formu

### 4. Rezervasyon Sistemi (✅ TAMAMLANDI)
- [x] **Rezervasyon İşlemleri:**
  - [x] Rezervasyon oluşturma (müşteri bilgileri + form)
  - [x] Kontenjan kontrolü (rezervasyon öncesi kontrol)
  - [x] Fiyat hesaplama (tarih bazlı, kampanya, grup, ekstra hizmetler)
  - [x] Rezervasyon durumu yönetimi
  - [x] Rezervasyon iptal sistemi
  - [x] Rezervasyon iade sistemi
  - [x] Satış elemanı atama

- [x] **Misafir Yönetimi:**
  - [x] Dinamik misafir formu (kişi sayısı kadar) - Formset ile eklendi
  - [x] Yetişkin/çocuk ayrımı (model'de var)
  - [x] TC/Pasaport bilgileri (model'de var)
  - [ ] Toplu misafir ekleme - TODO (isteğe bağlı)

### 5. PDF Program Oluşturma (✅ TAMAMLANDI)
- [x] **PDF Oluşturma:**
  - [x] `reportlab` kütüphanesi entegrasyonu (requirements.txt'de var)
  - [x] PDF şablon tasarımı (profesyonel tur programı formatı)
  - [x] PDF oluşturma fonksiyonu (utils.py)
  - [x] PDF arşivleme (media/tours/pdfs/)
  - [x] PDF görüntüleme ve indirme
  - [x] PDF içeriği: Tur bilgileri, program, fiyat, notlar

### 6. Harita Entegrasyonu (✅ TAMAMLANDI)
- [x] **Google Maps Entegrasyonu:**
  - [x] Google Maps API entegrasyonu (template'de hazır)
  - [x] Tur rotası harita gösterimi (şehir şehir iz)
  - [x] Otomatik rota çizimi (TourRoute modelinden)
  - [x] Harita üzerinde şehir işaretleme
  - [x] Rota bilgisi gösterimi
  - [x] Harita embed (tur detay sayfasında)

### 7. Voucher Sistemi (✅ TAMAMLANDI)
- [x] **Voucher Oluşturma:**
  - [x] Voucher şablonu seçimi
  - [x] Voucher HTML oluşturma (dinamik veriler ile)
  - [x] Voucher görüntüleme
  - [x] Voucher yazdırma
  - [ ] Voucher PDF oluşturma - TODO (HTML mevcut)

### 8. WhatsApp Entegrasyonu (✅ TAMAMLANDI)
- [x] **WhatsApp Gönderimi:**
  - [x] WhatsApp.me link oluşturma
  - [x] Voucher WhatsApp ile gönderme
  - [x] Rezervasyon bilgisi WhatsApp ile gönderme
  - [x] Mesaj şablonları
  - [x] Gönderim seçenekleri (wa.me link)
  - [ ] WhatsApp API entegrasyonu - TODO (isteğe bağlı)

### 9. Ödeme Entegrasyonu (✅ TAMAMLANDI)
- [x] **Ödeme Sistemi:**
  - [x] Ödeme kaydı oluşturma
  - [x] Ödeme yöntemleri (Nakit, Havale, Kredi Kartı, POS)
  - [x] Ödeme formu
  - [x] Ödeme geçmişi (rezervasyon detayında)
  - [x] Ödeme durumu yönetimi (pending, partial, paid)
  - [x] Ödeme yöntemleri model'de tanımlı (İyzico, PayTR, NestPay, Garanti, Akbank)
  - [ ] Kiracının ödeme yönetimi modülünden ödeme yöntemlerini çekme - TODO (payments app entegrasyonu - isteğe bağlı)
  - [ ] Türk ödeme POS entegrasyonları (Iyzico, PayTR, NestPay) - TODO (payments app'ten çekilecek - isteğe bağlı)

### 10. Ek Profesyonel Özellikler (✅ TAMAMLANDI - 2025-01-XX)
- [x] **CRM ve Sadakat Sistemi:**
  - [x] Müşteri profili ve geçmişi
  - [x] Sadakat puanları
  - [x] VIP seviyeleri
  - [x] Müşteri notları

- [x] **Komisyon ve Acente Yönetimi:**
  - [x] Acente kayıt ve yönetimi
  - [x] Otomatik komisyon hesaplama
  - [x] Komisyon ödeme takibi

- [x] **Operasyonel Yönetim:**
  - [x] Rehber yönetimi
  - [x] Araç yönetimi
  - [x] Otel yönetimi
  - [x] Transfer yönetimi

- [x] **Kampanya ve Promosyon:**
  - [x] Kampanya oluşturma
  - [x] Promosyon kodu sistemi
  - [x] Otomatik indirim uygulama

- [x] **Bildirim Sistemi:**
  - [x] Bildirim şablon yönetimi
  - [x] Tetikleyici olaylar
  - [x] Bildirim geçmişi

- [x] **Raporlama:**
  - [x] Tur raporları
  - [x] Rezervasyon raporları
  - [x] Gelir raporları
  - [x] Müşteri analizi
  - [x] Acente performans raporları
  - [x] Kampanya performans raporları
  - [x] CSV export

- [ ] **Rating ve Yorumlar:** (İsteğe Bağlı)
  - [ ] Tur değerlendirme sistemi
  - [ ] Yorum onaylama
  - [ ] Rating ortalaması hesaplama
  - [ ] Yorum görüntüleme (tur detay sayfasında)

- [ ] **Ek Özellikler:** (İsteğe Bağlı)
  - [ ] SEO ayarları (meta title, description, keywords)
  - [ ] Toplu işlemler (toplu durum değiştirme, toplu silme)
  - [ ] Tur şablonları

## 📝 Notlar

### Dosya Yapısı
```
apps/tenant_apps/tours/
├── models.py ✅
├── admin.py ✅
├── views.py (yapılacak)
├── urls.py (yapılacak)
├── forms.py (yapılacak)
├── utils.py (PDF, harita, voucher işlemleri için)
└── management/commands/ (varsayılan veri oluşturma)

templates/tenant/tours/
├── list.html
├── detail.html
├── form.html
├── reservations/
│   ├── list.html
│   ├── create.html
│   ├── detail.html
│   └── voucher.html
└── ...

static/tours/
└── css/js (tur modülü özel stilleri)
```

### Önemli Fonksiyonlar
- `Tour.get_current_price()` - Tarih bazlı fiyat hesaplama
- `Tour.get_available_capacity()` - Müsait kontenjan kontrolü
- `Tour.generate_pdf_program()` - PDF program oluşturma
- `TourReservation.calculate_total()` - Rezervasyon toplam fiyat hesaplama
- `TourReservation.generate_voucher()` - Voucher oluşturma

### Entegrasyonlar
- Google Maps API (harita)
- reportlab/weasyprint (PDF)
- WhatsApp Business API (mesajlaşma)
- Ödeme modülü (payments app)

### Test Edilmesi Gerekenler
- Tur CRUD işlemleri
- Rezervasyon oluşturma ve kontenjan azaltma
- Fiyat hesaplama (tarih bazlı, kampanya, grup)
- PDF oluşturma
- Harita gösterimi
- Voucher oluşturma
- WhatsApp gönderimi
- Ödeme işlemleri

## 🚀 Başlangıç Komutları

```bash
# Migration çalıştırma
python manage.py migrate_schemas

# Tur yetkilerini oluşturma (tüm tenant'larda)
python manage.py create_tour_permissions_all_tenants

# Tur modülünü paketlere ekleme
python manage.py add_tour_module_to_packages

# Test
python manage.py test apps.tenant_apps.tours
```

## ✅ Son Tamamlanan İşler (2025-01-XX)

### Alt Modüller Tamamlandı:
1. ✅ **CRM (Müşteri Yönetimi)** - CRUD, form, detail, list template'leri
2. ✅ **Acente Yönetimi** - CRUD, form, detail, list template'leri, istatistikler
3. ✅ **Kampanya Yönetimi** - CRUD, promo code yönetimi, template'ler
4. ✅ **Bildirim Şablonları** - CRUD, detail, list template'leri, istatistikler
5. ✅ **Operasyonel Yönetim** - Rehber, Araç, Otel, Transfer CRUD işlemleri

### Teknik Düzeltmeler:
- ✅ `models.Q` → `Q` import hatası düzeltildi
- ✅ `campaign.reservations` ilişkisi düzeltildi
- ✅ Tüm list template'lerindeki butonlar güncellendi
- ✅ Detail view'larda istatistikler eklendi

## 📚 Referanslar
- Django Models: https://docs.djangoproject.com/en/4.2/topics/db/models/
- Google Maps API: https://developers.google.com/maps
- reportlab: https://www.reportlab.com/
- WhatsApp Business API: https://developers.facebook.com/docs/whatsapp

