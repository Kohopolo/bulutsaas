# Gün Sonu İşlemleri (Night Audit / End of Day) - Güncellenmiş Detaylı Rapor

## 📋 İçindekiler
1. [Gün Sonu İşlemi Nedir?](#gün-sonu-işlemi-nedir)
2. [Sektörel Standartlar ve Referanslar](#sektörel-standartlar)
3. [Sistem Mimarisi (5 Ana Motor)](#sistem-mimarisi)
4. [Gün Sonu İşlem Adımları (Detaylı)](#gün-sonu-işlem-adımları)
5. [Muhasebe Entegrasyonu](#muhasebe-entegrasyonu)
6. [Raporlama Sistemi](#raporlama-sistemi)
7. [Otomasyon Türleri](#otomasyon-türleri)
8. [Teknik Gereksinimler](#teknik-gereksinimler)
9. [Geliştirme Planı](#geliştirme-planı)

---

## 🏨 Gün Sonu İşlemi Nedir?

**Gün Sonu İşlemi (Night Audit / End of Day - EOD)**, otelde gün boyunca gerçekleşen tüm operasyonların:
- ✅ **Doğrulanması**
- ✅ **Düzenlenmesi**
- ✅ **Finansal kayıtlarının kapanması**
- ✅ **Ertesi günün başlaması**

için yapılan **zorunlu süreçtir**.

### Neden Yapılır?
- Finansal kontrol ve doğrulama
- Operasyonel verilerin düzenlenmesi
- Muhasebe kayıtlarının tamamlanması
- Ertesi günün hazırlanması
- Raporlama ve analiz

---

## 🌍 Sektörel Standartlar ve Referanslar

### Uluslararası PMS Referansları
Bu sistem aşağıdaki uluslararası PMS sistemlerinin standartlarına göre tasarlanmıştır:
- **Marriott** - MARSHA System
- **Hilton** - OnQ PMS
- **Accor** - FOLS PMS
- **Opera PMS** (Oracle Hospitality)
- **Fidelio** (Oracle)
- **Elektra** (Protel)
- **InnRoad**
- **CloudBeds**
- **Mews**

### Standart Özellikler
Tüm bu sistemlerde bulunan ortak özellikler:
1. ✅ Pre-Audit kontrolleri
2. ✅ Sıralı işlem motoru
3. ✅ Otomatik muhasebe entegrasyonu
4. ✅ Kapsamlı raporlama
5. ✅ Rollback mekanizması
6. ✅ Asenkron işlem desteği

---

## 🧩 Sistem Mimarisi (5 Ana Motor)

### 🟦 1. Pre-Audit Kontrol Motoru

**Amaç:** Gün sonu işlemlerinden önce kritik kontrolleri yapar.

**Kontroller:**
- ✅ Folyo bakiyesi sıfır mı?
- ✅ Gelir girişi eksik mi?
- ✅ Check-in/Check-out durum hatası var mı?
- ✅ Oda fiyatı işlem görmüş mü?
- ✅ Günlük gelirler tamamlanmış mı?
- ✅ Oda fiyatı sıfır mı? (Durdur!)
- ✅ Peşin folyo balansı sıfır değil mi? (Durdur!)
- ✅ Check-out olmuş folyolar kontrol edildi mi?

**Hata Durumu:**
- → Gün sonu başlatılmaz
- → Kullanıcıya hata bildirimi + çözüm önerisi
- → Detaylı hata raporu

### 🟦 2. Audit Sequence Engine (Sıralı İşlem Motoru)

**Amaç:** Her işlemi sırayla çalıştırır, hata varsa durdurur.

**İşlem Sırası:**
1. ✅ Cash folyo kontrol
2. ✅ Checkout olmuş odaların folyo işlemi
3. ✅ No-show kuralı (iptal/yarına al)
4. ✅ Uzatma işlemleri
5. ✅ Oda değişim planları iptali
6. ✅ Oda fiyatı posting
7. ✅ Gelir dağıtımı (departman bazlı)
8. ✅ Yedekleme
9. ✅ Raporlama
10. ✅ Tarih güncelleme

**Özellikler:**
- Her adım başarılı → bir sonraki adım
- Hata → durdur + "nerede hata var" bildir
- İlerleme takibi (%0-100)
- Rollback desteği

### 🟦 3. Gelir ve Muhasebe Motoru

**Amaç:** Gelirleri toplar, sınıflandırır ve muhasebe fişi oluşturur.

**Gelir Türleri:**
- **Room Revenue** (Konaklama Geliri)
- **F&B Revenue** (Yiyecek-İçecek Geliri)
- **Spa Revenue** (Spa Geliri)
- **Extra Services** (Ek Hizmetler)
- **Taxes & Fees** (Vergiler ve Ücretler)

**Pazarlama Segmentleri:**
- Direct (Direkt)
- Online (Online)
- Agency (Acente)
- Corporate (Kurumsal)
- Group (Grup)
- Walk-in (Gel-Al)

**Otomatik Muhasebe Fişleri:**

Türkiye Tek Düzen Hesap Planı:
- **600** - Konaklama Geliri
- **391** - Hesaplanan KDV
- **102** - Kasa
- **120** - Müşteri (Alacaklar)
- **108** - Kredi Kartı Blokesi
- **320** - Satıcılar (Borçlar)

**Desteklenen Standartlar:**
- ✅ IPSAS (International Public Sector Accounting Standards)
- ✅ IFRS (International Financial Reporting Standards)
- ✅ Türkiye Tek Düzen Hesap Planı

**Entegrasyonlar:**
- POS sistemleri
- F&B sistemleri
- SPA sistemleri
- Dış ERP sistemleri

### 🟦 4. Raporlama Motoru

**Amaç:** Tüm raporları otomatik üretir ve dağıtır.

**Rapor Türleri:**

#### 4.1. Yönetim Raporları
- **Daily Revenue Report** (Günlük Gelir Raporu)
- **Manager Summary** (Yönetici Özeti)
- **Cashier Report** (Kasiyer Raporu)
- **Payment Report** (Ödeme Raporu)
- **Arrivals / Departures** (Girişler / Çıkışlar)
- **In-House Guest List** (Konaklayan Misafir Listesi)

#### 4.2. Finansal Raporlar
- **Financial Summary** (Finansal Özet)
- **Revenue by Department** (Departman Bazlı Gelir)
- **Market Segment Report** (Pazar Segmenti Raporu)
- **Meal Plan Count Report** (Yemek Planı Sayım Raporu)

#### 4.3. Operasyonel Raporlar
- **Room Occupancy Report** (Oda Doluluk Raporu)
- **ADR Report** (Ortalama Oda Fiyatı)
- **RevPAR Report** (Oda Başına Gelir)
- **Housekeeping Status** (Kat Hizmetleri Durumu)

#### 4.4. Misafir Raporları
- **Guest Ledger** (Misafir Defteri)
- **Company Ledger** (Şirket Defteri)
- **Agency Ledger** (Acente Defteri)
- **City Ledger** (Şehir Defteri)

**Export Formatları:**
- PDF (WeasyPrint / ReportLab)
- Excel (openpyxl)
- JSON (API)
- CSV

### 🟦 5. Gün Sonu Güncelleme Motoru

**Amaç:** Günü kapatır ve ertesi günü hazırlar.

**İşlemler:**
- ✅ Sistem tarihinin 1 gün ileri alınması
- ✅ Gelecek günün odaları ve fiyatlarının hazırlanması
- ✅ Yeni giriş/çıkış planının aktif edilmesi
- ✅ Oda durumlarının sıfırlanması
- ✅ Housekeeping günlük döngüsünün başlatılması
- ✅ Yeni folyo açılışları
- ✅ Özel fiyatların güncellenmesi

---

## 📋 Gün Sonu İşlem Adımları (Detaylı)

### 🔴 Adım 1: Pre-Audit Başlangıcı

#### 1.1. Oda Fiyatı Kontrolü
- **Kontrol:** Tüm aktif rezervasyonlarda oda fiyatı > 0
- **Hata:** Sıfır fiyatlı rezervasyon varsa → DURDUR
- **Rapor:** Sıfır fiyatlı rezervasyon listesi

#### 1.2. Peşin Folyo Balansı Kontrolü
- **Kontrol:** Peşin ödemeli rezervasyonlarda folyo balansı = 0
- **Hata:** Balans varsa → DURDUR
- **Rapor:** Peşin folyo balans raporu

#### 1.3. Check-out Olmuş Folyolar Kontrolü
- **Kontrol:** Check-out yapılmış rezervasyonlarda folyo balansı = 0
- **Hata:** Balans varsa → DURDUR
- **Rapor:** Check-out folyo balans raporu

### 🟡 Adım 2: Folyo İşlemleri

#### 2.1. Cash Folyo Balansı Kontrolü
- **İşlem:** Tüm aktif rezervasyonların cash folyo balanslarını kontrol et
- **Amaç:** Nakit işlemlerin doğruluğunu sağla
- **Rapor:** Cash balans özeti

#### 2.2. Çıkış Yapmış Odaların Bakiyesi Kontrolü
- **İşlem:** Check-out yapılmış odaların folyo bakiyelerini kontrol et
- **Amaç:** Kapanmamış hesapları tespit et
- **Rapor:** Check-out balans raporu

#### 2.3. No-Show Aktarımı
- **İşlem:** Check-in tarihi geçmiş ve check-in yapılmamış rezervasyonları işle
- **Kural:** 
  - Kredi kartı garanti yoksa → No-Show
  - Garantili ise → ücret uygulanır / ertesi güne taşınır
- **Ayar:** "Gelmeyen Rezervasyonları İptal Et veya Yarına Al"
- **Rapor:** No-show rezervasyon listesi

#### 2.4. Uzatılacak Odaların Uzatılması
- **İşlem:** Check-out tarihi geçmiş ve check-out yapılmamış rezervasyonları uzat
- **Ayar:** "CheckOut Olmamış Konaklayanları UZAT"
- **Aksiyon:** 
  - Otomatik uzatma (1 gün)
  - Fiyatlandırma güncellemesi
  - Oda durumu güncellemesi
- **Rapor:** Uzatılan rezervasyon listesi

#### 2.5. Oda Değişim Planlarının İptali
- **İşlem:** Planlanmış ama gerçekleşmemiş oda değişimlerini iptal et
- **Ayar:** "Oda Değişim Planlarını İPTAL Et"
- **Rapor:** İptal edilen oda değişim listesi

### 🟢 Adım 3: Posting & Gelir Analizi

#### 3.1. Oda Fiyatları İşleniyor
- **İşlem:** Her oda için o günün oda ücreti posting işlemi
- **İçerik:**
  - Oda ücreti posting
  - City ledger aktarımı
  - Tur operatörü anlaşma fiyatı kesintileri
  - Dinamik fiyatlandırma güncellemeleri
- **Rapor:** Fiyat güncelleme raporu

#### 3.2. Extra Posting İşlemleri
- **İşlem:** Minibar, restaurant, transfer, ek hizmetler posting
- **Amaç:** Tüm gelir kalemlerini kaydet
- **Rapor:** Extra posting raporu

#### 3.3. Departman Gelir Raporu Hazırlama
- **İşlem:** Departman bazlı gelir analizi
- **Departmanlar:**
  - Room Revenue
  - F&B Revenue
  - Spa Revenue
  - Extra Services
  - Taxes & Fees
- **Rapor:** Departman gelir raporu

#### 3.4. Kur Farkı İşlemleri
- **İşlem:** POS döviz işlemleri yeniden hesaplama
- **Amaç:** Kur farklarını kaydet
- **Rapor:** Kur farkı raporu

### 🔵 Adım 4: Operasyonel Yedekleme

#### 4.1. Giriş/Folyo İşlemleri Yedekleme
- **İşlem:** Günlük check-in ve folyo işlemlerini yedekle
- **Format:** JSON/CSV
- **Sıkıştırma:** ZIP
- **Saklama:** Yedekleme geçmişi

#### 4.2. Log Kayıtlarının Arşivlenmesi
- **İşlem:** Sistem loglarını arşivle
- **Amaç:** Audit trail
- **Format:** JSON/CSV

#### 4.3. Oda Durumlarının Güncellemesi
- **İşlem:** Housekeeping – PMS senkronu
- **Amaç:** Oda durumlarını güncelle
- **Durumlar:** Dirty, Clean, Vacant, Occupied, Out of Order

### 🟣 Adım 5: Raporlama

#### 5.1. Yönetim Raporları Hazırlanıyor
- Daily Revenue Report
- Manager Summary
- Cashier Report
- Payment Report
- Arrivals / Departures
- In-House Guest List

#### 5.2. Gün Sonu Raporları Oluşturuluyor
- Gün sonu özeti
- Finansal özet
- Operasyonel özet

#### 5.3. Misafir Raporları Export Ediliyor
- Guest Ledger
- Company Ledger
- Agency Ledger
- City Ledger

### ⚫ Adım 6: Gün Kapatma

#### 6.1. Sistem Tarihi 1 Gün İleri
- **İşlem:** Program tarihini güncelle
- **Amaç:** Yeni günün başlaması

#### 6.2. Ertesi Günün Fiyat/Folyo/Kayıt Hazırlığı
- **İşlem:** Gelecek günün hazırlıkları
- **İçerik:**
  - Yeni oda fiyatları
  - Özel fiyatlar
  - Yeni folyo açılışları
  - Rezervasyon planları

#### 6.3. Oda Durum Döngüsü Resetlenir
- **İşlem:** Oda durumlarını sıfırla
- **Amaç:** Yeni gün için hazırlık

#### 6.4. Housekeeping Günlük Döngüsünün Başlatılması
- **İşlem:** Housekeeping modülüne yeni gün bildirimi
- **Amaç:** Temizlik planlarının hazırlanması

---

## 💰 Muhasebe Entegrasyonu

### Hesap Planı Yapısı

#### Gelir Hesapları
- **600** - Konaklama Geliri
  - 600.01 - Oda Geliri
  - 600.02 - Ek Hizmet Geliri
- **601** - Yiyecek-İçecek Geliri
- **602** - Spa Geliri
- **603** - Diğer Gelirler

#### Vergi Hesapları
- **391** - Hesaplanan KDV
  - 391.01 - KDV %20
  - 391.02 - KDV %10
  - 391.03 - KDV %1

#### Varlık Hesapları
- **102** - Kasa
- **120** - Müşteri (Alacaklar)
- **108** - Kredi Kartı Blokesi

#### Borç Hesapları
- **320** - Satıcılar (Borçlar)

### Otomatik Muhasebe Fişi Oluşturma

**Gün Sonu İşleminde Otomatik Oluşturulan Fişler:**

#### 1. Konaklama Geliri Fişi
```
Borç: 102 Kasa / 120 Müşteri / 108 Kredi Kartı
Alacak: 600 Konaklama Geliri
Alacak: 391 Hesaplanan KDV
```

#### 2. F&B Geliri Fişi
```
Borç: 102 Kasa / 120 Müşteri
Alacak: 601 Yiyecek-İçecek Geliri
Alacak: 391 Hesaplanan KDV
```

#### 3. Spa Geliri Fişi
```
Borç: 102 Kasa / 120 Müşteri
Alacak: 602 Spa Geliri
Alacak: 391 Hesaplanan KDV
```

### Market Segment Bazlı Sınıflandırma

Her gelir kalemi pazarlama segmentine göre sınıflandırılır:
- Direct
- Online
- Agency
- Corporate
- Group
- Walk-in

---

## 📊 Raporlama Sistemi

### Rapor Kategorileri

#### 1. Yönetim Raporları
- **Daily Revenue Report:** Günlük gelir özeti
- **Manager Summary:** Yönetici için özet bilgiler
- **Cashier Report:** Kasiyer işlem raporu
- **Payment Report:** Ödeme yöntemleri raporu
- **Arrivals / Departures:** Giriş/çıkış listesi
- **In-House Guest List:** Konaklayan misafir listesi

#### 2. Finansal Raporlar
- **Financial Summary:** Finansal özet
- **Revenue by Department:** Departman bazlı gelir
- **Market Segment Report:** Pazar segmenti analizi
- **Meal Plan Count Report:** Yemek planı sayımı (kaç kahvaltı?)

#### 3. Operasyonel Raporlar
- **Room Occupancy Report:** Oda doluluk raporu
- **ADR Report:** Ortalama Oda Fiyatı (Average Daily Rate)
- **RevPAR Report:** Oda Başına Gelir (Revenue Per Available Room)
- **Housekeeping Status:** Kat hizmetleri durumu

#### 4. Misafir Raporları
- **Guest Ledger:** Misafir defteri
- **Company Ledger:** Şirket defteri
- **Agency Ledger:** Acente defteri
- **City Ledger:** Şehir defteri

### Rapor Formatları

- **PDF:** WeasyPrint veya ReportLab ile oluşturulur
- **Excel:** openpyxl ile detaylı veri export
- **JSON:** API üzerinden dış sistemlere gönderim
- **CSV:** Basit veri export

---

## 🔄 Otomasyon Türleri

### 1. Zaman Planlı (Scheduler / Cron)
- **Özellik:** Belirli saatte otomatik başlar
- **Kullanım:** Gece 02:00-04:00 arası
- **Ayarlar:** `auto_run_time` field'ından yönetilir

### 2. Manuel + Otomatik Karışık
- **Özellik:** Resepsiyon başlatır → sistem devam eder
- **Kullanım:** İnsan kontrolü ile otomatik işlem
- **Avantaj:** Hata durumunda müdahale edilebilir

### 3. Tam Otomatik (AI Destekli)
- **Özellik:** Sistem kendi başlatır
- **Koşullar:**
  - Doluluk kontrolü
  - Geç giriş kontrolü
  - Gece personeli yok kontrolü
- **Avantaj:** İnsan müdahalesi gerektirmez

---

## ⚙️ Teknik Gereksinimler

### Model Gereksinimleri

#### `EndOfDayOperation`
```python
- operation_date: DateField
- program_date: DateField
- hotel: ForeignKey
- status: CharField (pending, running, completed, failed, rolled_back)
- is_async: BooleanField
- automation_type: CharField (scheduled, manual, automatic)
- settings: JSONField
- results: JSONField
- started_at: DateTimeField
- completed_at: DateTimeField
- created_by: ForeignKey
- error_message: TextField
- rollback_data: JSONField (rollback için)
```

#### `EndOfDaySettings`
```python
- hotel: ForeignKey (unique=True)
- stop_if_room_price_zero: BooleanField (default=True)
- stop_if_advance_folio_balance_not_zero: BooleanField (default=True)
- check_checkout_folios: BooleanField (default=True)
- cancel_no_show_reservations: BooleanField (default=False)
- extend_non_checkout_reservations: BooleanField (default=False)
- cancel_room_change_plans: BooleanField (default=False)
- auto_run_time: TimeField (null=True, blank=True)
- automation_type: CharField (scheduled, manual, automatic)
- is_active: BooleanField (default=True)
- enable_rollback: BooleanField (default=True)
```

#### `EndOfDayReport`
```python
- operation: ForeignKey
- report_type: CharField (summary, financial, operational, guest, management)
- report_data: JSONField
- report_file: FileField (PDF/Excel)
- generated_at: DateTimeField
- exported_to: JSONField (hangi sistemlere gönderildi)
```

#### `EndOfDayOperationStep`
```python
- operation: ForeignKey
- step_name: CharField
- step_order: IntegerField
- status: CharField (pending, running, completed, failed)
- started_at: DateTimeField
- completed_at: DateTimeField
- result_data: JSONField
- error_message: TextField
- rollback_data: JSONField
```

#### `EndOfDayJournalEntry` (Muhasebe Entegrasyonu)
```python
- operation: ForeignKey
- journal_entry: ForeignKey (JournalEntry)
- entry_type: CharField (revenue, expense, transfer)
- department: CharField (room, f&b, spa, extra)
- market_segment: CharField
- amount: DecimalField
- currency: CharField
```

### Utility Fonksiyonları

#### Pre-Audit Kontrolleri
- `pre_audit_check_room_prices()`
- `pre_audit_check_advance_folio_balances()`
- `pre_audit_check_checkout_folios()`
- `pre_audit_check_revenue_completeness()`

#### Audit Sequence Engine
- `run_audit_sequence(operation)`
- `execute_audit_step(step)`
- `handle_audit_error(step, error)`
- `rollback_audit_step(step)`

#### Gelir ve Muhasebe Motoru
- `process_revenue_posting(operation)`
- `create_revenue_journal_entries(operation)`
- `classify_revenue_by_segment(revenue_data)`
- `calculate_currency_differences(operation)`
- `distribute_revenue_by_department(operation)`

#### Raporlama Motoru
- `generate_management_reports(operation)`
- `generate_financial_reports(operation)`
- `generate_operational_reports(operation)`
- `generate_guest_reports(operation)`
- `export_reports_to_external_systems(operation)`

#### Gün Sonu Güncelleme Motoru
- `update_system_date(operation)`
- `prepare_next_day(operation)`
- `reset_room_statuses(operation)`
- `initialize_housekeeping_cycle(operation)`

---

## 🚀 Geliştirme Planı

### Faz 1: Temel Yapı ve Modeller (2 Hafta)
- Model oluşturma
- Migration'lar
- Admin paneli
- Temel view'lar

### Faz 2: Pre-Audit Kontrol Motoru (1 Hafta)
- Kontrol fonksiyonları
- Hata yönetimi
- Uyarı sistemi

### Faz 3: Audit Sequence Engine (2 Hafta)
- Sıralı işlem motoru
- İlerleme takibi
- Hata yönetimi
- Rollback mekanizması

### Faz 4: Gelir ve Muhasebe Motoru (2 Hafta)
- Gelir toplama ve sınıflandırma
- Muhasebe fişi oluşturma
- Market segment analizi
- Departman bazlı dağıtım

### Faz 5: Raporlama Motoru (2 Hafta)
- Tüm rapor türlerinin oluşturulması
- PDF/Excel export
- API entegrasyonu
- Dış sistemlere gönderim

### Faz 6: Gün Sonu Güncelleme Motoru (1 Hafta)
- Sistem tarihi güncelleme
- Ertesi gün hazırlığı
- Oda durum resetleme
- Housekeeping entegrasyonu

### Faz 7: Asenkron İşlemler ve Otomasyon (2 Hafta)
- Celery entegrasyonu
- Scheduled tasks
- Otomatik başlatma
- Bildirim sistemi

### Faz 8: Test ve Optimizasyon (1 Hafta)
- Unit testler
- Integration testler
- Performance optimizasyonu
- Dokümantasyon

**Toplam Tahmini Süre:** 13 Hafta (3+ Ay)

---

## ✅ Sonuç

Bu sistem:
- ✅ **Tam otomatik** çalışabilir
- ✅ **Hataya izin vermez** (pre-audit kontrolleri)
- ✅ **Operasyon ve muhasebeyi %100 kapsar**
- ✅ **Raporları kendi üretir**
- ✅ **Kendi kendini doğrular**
- ✅ **Geri alınabilir** (rollback)
- ✅ **Uluslararası standartlara uygun**

---

## 📝 Notlar

1. **Gün Sonu İşlemleri genellikle gece 02:00-04:00 arası yapılır**
2. **İşlemler sıralı olarak çalışmalı (bir hata varsa durmalı)**
3. **Her işlem için detaylı log tutulmalı**
4. **Kullanıcı işlem durumunu gerçek zamanlı görebilmeli**
5. **Hata durumunda bildirim gönderilmeli**
6. **Rollback mekanizması kritik öneme sahip**
7. **Muhasebe entegrasyonu zorunludur**
8. **Raporlar otomatik oluşturulmalı ve dağıtılmalı**

---

## ✅ Onay Bekleniyor

Bu rapor ChatGPT'nin sektörel araştırması ve uluslararası PMS standartları dikkate alınarak hazırlanmıştır. Onayınız sonrasında geliştirme sürecine başlanacaktır.

**Önerilen Başlangıç:** Faz 1 - Temel Yapı ve Modeller

