# Gün Sonu İşlemleri (End of Day Operations) - Detaylı Analiz ve Geliştirme Raporu

## 📋 İçindekiler
1. [Sektörel Araştırma](#sektörel-araştırma)
2. [Mevcut Sistem Analizi](#mevcut-sistem-analizi)
3. [Gün Sonu İşlemleri Detayları](#gün-sonu-işlemleri-detayları)
4. [Sistem Gereksinimleri](#sistem-gereksinimleri)
5. [Geliştirme Planı](#geliştirme-planı)
6. [Teknik Detaylar](#teknik-detaylar)

---

## 🔍 Sektörel Araştırma

### Otelcilik Sektöründe Gün Sonu İşlemleri (Night Audit / End of Day)

Gün sonu işlemleri, otel operasyonlarının en kritik süreçlerinden biridir. Genellikle gece vardiyası (night audit) sırasında gerçekleştirilir ve şu amaçları taşır:

#### 1. **Finansal Kontrol ve Doğrulama**
- Günlük gelir-gider kontrolü
- Nakit, kredi kartı ve diğer ödeme yöntemlerinin doğrulanması
- Folyo (misafir hesabı) balanslarının kontrolü
- Peşin ödemelerin kontrolü
- Oda fiyatlarının doğru uygulanması

#### 2. **Rezervasyon ve Konaklama Yönetimi**
- Check-in yapılmamış rezervasyonların kontrolü (No-Show)
- Check-out yapılmamış konaklamaların uzatılması
- Oda durumlarının güncellenmesi
- Gelecek günün rezervasyonlarının hazırlanması

#### 3. **Oda ve Fiyatlandırma Kontrolü**
- Oda fiyatlarının sıfır olmaması kontrolü
- Dinamik fiyatlandırma güncellemeleri
- Oda değişim planlarının kontrolü

#### 4. **Raporlama ve Yedekleme**
- Günlük operasyonel raporlar
- Finansal raporlar
- Misafir raporları
- Sistem yedeklemeleri

#### 5. **Sistem Bakımı**
- Veri bütünlüğü kontrolleri
- Otomatik yedeklemeler
- Sistem optimizasyonu

---

## 📊 Mevcut Sistem Analizi

### Resepsiyon Modülü Mevcut Yapısı

**Mevcut Modeller:**
- `Reservation` - Rezervasyon yönetimi
- `ReservationPayment` - Ödeme işlemleri
- `ReservationGuest` - Misafir bilgileri
- `ReservationTimeline` - Rezervasyon geçmişi
- `ReservationVoucher` - Voucher yönetimi

**Mevcut Özellikler:**
- ✅ Rezervasyon CRUD işlemleri
- ✅ Check-in/Check-out işlemleri
- ✅ Ödeme yönetimi
- ✅ Oda durumu yönetimi
- ✅ Müşteri folyo takibi

**Eksik Özellikler:**
- ❌ Gün sonu işlemleri modülü
- ❌ Otomatik gün sonu kontrolleri
- ❌ Gün sonu raporları
- ❌ Asenkron gün sonu işlemleri
- ❌ Gün sonu ayarları ve konfigürasyonu

---

## 🎯 Gün Sonu İşlemleri Detayları

### 1. **Ön Kontroller (Pre-Checks)**

#### 1.1. Oda Fiyatı Kontrolü
- **Amaç:** Sıfır fiyatlı odaların tespiti
- **Kontrol:** Tüm aktif rezervasyonlarda oda fiyatı > 0 olmalı
- **Aksiyon:** Sıfır fiyatlı rezervasyonlar için uyarı ve durdurma

#### 1.2. Peşin Folyo Balansı Kontrolü
- **Amaç:** Peşin ödemeli rezervasyonlarda balans kontrolü
- **Kontrol:** Peşin ödemeli rezervasyonlarda folyo balansı = 0 olmalı
- **Aksiyon:** Balans varsa uyarı ve durdurma

#### 1.3. Check-out Olmuş Folyolar Kontrolü
- **Amaç:** Check-out yapılmış rezervasyonların folyo kontrolü
- **Kontrol:** Check-out yapılmış rezervasyonlarda folyo balansı = 0 olmalı
- **Aksiyon:** Balans varsa uyarı ve durdurma

### 2. **Otomatik İşlemler**

#### 2.1. Cash Folyo Balansı Kontrolü
- **İşlem:** Tüm aktif rezervasyonların cash folyo balanslarını kontrol et
- **Amaç:** Nakit işlemlerin doğruluğunu sağla
- **Rapor:** Cash balans özeti

#### 2.2. Çıkış Yapmış Odaların Balansı Kontrolü
- **İşlem:** Check-out yapılmış odaların folyo balanslarını kontrol et
- **Amaç:** Kapanmamış hesapları tespit et
- **Rapor:** Check-out balans raporu

#### 2.3. Giriş Yapılmamış Odalar İptal Listesine Aktarılması
- **İşlem:** Check-in tarihi geçmiş ve check-in yapılmamış rezervasyonları iptal et veya yarına al
- **Koşul:** Ayarlarda "Gelmeyen Rezervasyonları İptal Et" aktifse
- **Aksiyon:** 
  - İptal et (varsayılan)
  - Yarına al (opsiyonel)
- **Rapor:** No-show rezervasyon listesi

#### 2.4. Çıkış Yapılmamış Konaklayan Odaların Uzatılması
- **İşlem:** Check-out tarihi geçmiş ve check-out yapılmamış rezervasyonları uzat
- **Koşul:** Ayarlarda "CheckOut Olmamış Konaklayanları UZAT" aktifse
- **Aksiyon:** 
  - Otomatik uzatma (1 gün)
  - Fiyatlandırma güncellemesi
- **Rapor:** Uzatılan rezervasyon listesi

#### 2.5. Oda Değişim Planlarının İptali
- **İşlem:** Planlanmış ama gerçekleşmemiş oda değişimlerini iptal et
- **Koşul:** Ayarlarda "Oda Değişim Planlarını İPTAL Et" aktifse
- **Rapor:** İptal edilen oda değişim listesi

#### 2.6. Oda Fiyatları İşleme
- **İşlem:** Günlük oda fiyatlarını işle ve güncelle
- **Amaç:** Dinamik fiyatlandırma güncellemeleri
- **Rapor:** Fiyat güncelleme raporu

#### 2.7. Giriş ve Folyo İşlemleri Yedekleme
- **İşlem:** Günlük check-in ve folyo işlemlerini yedekle
- **Amaç:** Veri güvenliği
- **Format:** JSON/CSV yedekleme

#### 2.8. Gün Detay Bilgileri Düzenleme
- **İşlem:** Günlük operasyonel verileri düzenle ve kaydet
- **İçerik:**
  - Günlük doluluk oranı
  - Ortalama oda fiyatı (ADR)
  - Toplam gelir
  - Check-in/Check-out sayıları

#### 2.9. Günlük Yönetim Raporları Düzenleme
- **İşlem:** Yönetim için günlük raporları hazırla
- **Raporlar:**
  - Gelir raporu
  - Doluluk raporu
  - Rezervasyon raporu
  - Ödeme raporu

#### 2.10. Gün Sonu Raporları Hazırlama
- **İşlem:** Gün sonu özet raporlarını hazırla
- **Raporlar:**
  - Gün sonu özeti
  - Finansal özet
  - Operasyonel özet

#### 2.11. Misafir Raporları Transfer Etme
- **İşlem:** Misafir raporlarını ilgili departmanlara transfer et
- **Amaç:** Departmanlar arası bilgi paylaşımı

---

## ⚙️ Sistem Gereksinimleri

### 1. **Model Gereksinimleri**

#### `EndOfDayOperation` Modeli
```python
- operation_date: DateField (İşlem tarihi)
- program_date: DateField (Program tarihi)
- hotel: ForeignKey (Otel)
- status: CharField (pending, running, completed, failed)
- is_async: BooleanField (Asenkron mu?)
- settings: JSONField (Ayarlar)
- results: JSONField (Sonuçlar)
- started_at: DateTimeField
- completed_at: DateTimeField
- created_by: ForeignKey (User)
```

#### `EndOfDaySettings` Modeli
```python
- hotel: ForeignKey (Otel)
- stop_if_room_price_zero: BooleanField
- stop_if_advance_folio_balance_not_zero: BooleanField
- check_checkout_folios: BooleanField
- cancel_no_show_reservations: BooleanField
- extend_non_checkout_reservations: BooleanField
- cancel_room_change_plans: BooleanField
- auto_run_time: TimeField (Otomatik çalışma saati)
- is_active: BooleanField
```

#### `EndOfDayReport` Modeli
```python
- operation: ForeignKey (EndOfDayOperation)
- report_type: CharField (summary, financial, operational, guest)
- report_data: JSONField
- report_file: FileField (PDF/Excel)
- generated_at: DateTimeField
```

### 2. **View Gereksinimleri**

- `end_of_day_dashboard` - Ana dashboard
- `end_of_day_settings` - Ayarlar sayfası
- `end_of_day_run` - Gün sonu işlemlerini çalıştır
- `end_of_day_close` - Günü kapat
- `end_of_day_reports` - Raporlar sayfası
- `end_of_day_history` - Geçmiş işlemler

### 3. **Utility Fonksiyonları**

- `check_room_prices()` - Oda fiyatları kontrolü
- `check_advance_folio_balances()` - Peşin folyo kontrolü
- `check_checkout_folios()` - Check-out folyo kontrolü
- `process_no_show_reservations()` - No-show işleme
- `extend_non_checkout_reservations()` - Uzatma işlemi
- `cancel_room_change_plans()` - Oda değişim iptali
- `process_room_prices()` - Fiyat işleme
- `backup_daily_data()` - Yedekleme
- `generate_daily_reports()` - Rapor oluşturma

### 4. **Background Task Gereksinimleri**

- Celery task'ları (asenkron işlemler için)
- Scheduled task'lar (otomatik çalışma için)

---

## 🚀 Geliştirme Planı

### Faz 1: Temel Yapı (1-2 Hafta)
1. Model oluşturma (`EndOfDayOperation`, `EndOfDaySettings`, `EndOfDayReport`)
2. Temel view'lar ve URL'ler
3. Dashboard sayfası
4. Ayarlar sayfası

### Faz 2: Kontrol İşlemleri (1 Hafta)
1. Oda fiyatı kontrolü
2. Peşin folyo balansı kontrolü
3. Check-out folyo kontrolü
4. Hata yönetimi ve uyarılar

### Faz 3: Otomatik İşlemler (2 Hafta)
1. Cash folyo balansı kontrolü
2. Çıkış yapmış odalar kontrolü
3. No-show rezervasyon işleme
4. Uzatma işlemleri
5. Oda değişim iptali
6. Fiyat işleme

### Faz 4: Yedekleme ve Raporlama (1 Hafta)
1. Yedekleme sistemi
2. Gün detay bilgileri
3. Rapor oluşturma
4. PDF/Excel export

### Faz 5: Asenkron İşlemler ve Otomasyon (1 Hafta)
1. Celery entegrasyonu
2. Asenkron gün sonu işlemleri
3. Otomatik çalışma zamanlayıcısı
4. Bildirim sistemi

### Faz 6: Test ve Optimizasyon (1 Hafta)
1. Unit testler
2. Integration testler
3. Performance optimizasyonu
4. Dokümantasyon

---

## 🔧 Teknik Detaylar

### 1. **Asenkron İşlemler**

Asenkron gün sonu işlemleri için Celery kullanılacak:
- Uzun süren işlemler arka planda çalışacak
- Kullanıcı işlem durumunu takip edebilecek
- Hata durumunda bildirim gönderilecek

### 2. **Veri Yedekleme**

- Günlük veriler JSON formatında yedeklenecek
- Yedekler sıkıştırılarak saklanacak
- Yedekleme geçmişi tutulacak

### 3. **Raporlama**

- PDF raporlar (WeasyPrint veya ReportLab)
- Excel raporlar (openpyxl)
- JSON API endpoint'leri

### 4. **Güvenlik**

- Sadece yetkili kullanıcılar gün sonu işlemlerini çalıştırabilir
- İşlem geçmişi loglanacak
- Rollback mekanizması (hata durumunda geri alma)

---

## 📝 Notlar

1. **Gün Sonu İşlemleri genellikle gece 02:00-04:00 arası yapılır**
2. **İşlemler sıralı olarak çalışmalı (bir hata varsa durmalı)**
3. **Her işlem için detaylı log tutulmalı**
4. **Kullanıcı işlem durumunu gerçek zamanlı görebilmeli**
5. **Hata durumunda bildirim gönderilmeli**

---

## ✅ Onay Bekleniyor

Bu rapor onayınıza sunulmuştur. Onayınız sonrasında geliştirme sürecine başlanacaktır.

**Önerilen Başlangıç:** Faz 1 - Temel Yapı




## 📋 İçindekiler
1. [Sektörel Araştırma](#sektörel-araştırma)
2. [Mevcut Sistem Analizi](#mevcut-sistem-analizi)
3. [Gün Sonu İşlemleri Detayları](#gün-sonu-işlemleri-detayları)
4. [Sistem Gereksinimleri](#sistem-gereksinimleri)
5. [Geliştirme Planı](#geliştirme-planı)
6. [Teknik Detaylar](#teknik-detaylar)

---

## 🔍 Sektörel Araştırma

### Otelcilik Sektöründe Gün Sonu İşlemleri (Night Audit / End of Day)

Gün sonu işlemleri, otel operasyonlarının en kritik süreçlerinden biridir. Genellikle gece vardiyası (night audit) sırasında gerçekleştirilir ve şu amaçları taşır:

#### 1. **Finansal Kontrol ve Doğrulama**
- Günlük gelir-gider kontrolü
- Nakit, kredi kartı ve diğer ödeme yöntemlerinin doğrulanması
- Folyo (misafir hesabı) balanslarının kontrolü
- Peşin ödemelerin kontrolü
- Oda fiyatlarının doğru uygulanması

#### 2. **Rezervasyon ve Konaklama Yönetimi**
- Check-in yapılmamış rezervasyonların kontrolü (No-Show)
- Check-out yapılmamış konaklamaların uzatılması
- Oda durumlarının güncellenmesi
- Gelecek günün rezervasyonlarının hazırlanması

#### 3. **Oda ve Fiyatlandırma Kontrolü**
- Oda fiyatlarının sıfır olmaması kontrolü
- Dinamik fiyatlandırma güncellemeleri
- Oda değişim planlarının kontrolü

#### 4. **Raporlama ve Yedekleme**
- Günlük operasyonel raporlar
- Finansal raporlar
- Misafir raporları
- Sistem yedeklemeleri

#### 5. **Sistem Bakımı**
- Veri bütünlüğü kontrolleri
- Otomatik yedeklemeler
- Sistem optimizasyonu

---

## 📊 Mevcut Sistem Analizi

### Resepsiyon Modülü Mevcut Yapısı

**Mevcut Modeller:**
- `Reservation` - Rezervasyon yönetimi
- `ReservationPayment` - Ödeme işlemleri
- `ReservationGuest` - Misafir bilgileri
- `ReservationTimeline` - Rezervasyon geçmişi
- `ReservationVoucher` - Voucher yönetimi

**Mevcut Özellikler:**
- ✅ Rezervasyon CRUD işlemleri
- ✅ Check-in/Check-out işlemleri
- ✅ Ödeme yönetimi
- ✅ Oda durumu yönetimi
- ✅ Müşteri folyo takibi

**Eksik Özellikler:**
- ❌ Gün sonu işlemleri modülü
- ❌ Otomatik gün sonu kontrolleri
- ❌ Gün sonu raporları
- ❌ Asenkron gün sonu işlemleri
- ❌ Gün sonu ayarları ve konfigürasyonu

---

## 🎯 Gün Sonu İşlemleri Detayları

### 1. **Ön Kontroller (Pre-Checks)**

#### 1.1. Oda Fiyatı Kontrolü
- **Amaç:** Sıfır fiyatlı odaların tespiti
- **Kontrol:** Tüm aktif rezervasyonlarda oda fiyatı > 0 olmalı
- **Aksiyon:** Sıfır fiyatlı rezervasyonlar için uyarı ve durdurma

#### 1.2. Peşin Folyo Balansı Kontrolü
- **Amaç:** Peşin ödemeli rezervasyonlarda balans kontrolü
- **Kontrol:** Peşin ödemeli rezervasyonlarda folyo balansı = 0 olmalı
- **Aksiyon:** Balans varsa uyarı ve durdurma

#### 1.3. Check-out Olmuş Folyolar Kontrolü
- **Amaç:** Check-out yapılmış rezervasyonların folyo kontrolü
- **Kontrol:** Check-out yapılmış rezervasyonlarda folyo balansı = 0 olmalı
- **Aksiyon:** Balans varsa uyarı ve durdurma

### 2. **Otomatik İşlemler**

#### 2.1. Cash Folyo Balansı Kontrolü
- **İşlem:** Tüm aktif rezervasyonların cash folyo balanslarını kontrol et
- **Amaç:** Nakit işlemlerin doğruluğunu sağla
- **Rapor:** Cash balans özeti

#### 2.2. Çıkış Yapmış Odaların Balansı Kontrolü
- **İşlem:** Check-out yapılmış odaların folyo balanslarını kontrol et
- **Amaç:** Kapanmamış hesapları tespit et
- **Rapor:** Check-out balans raporu

#### 2.3. Giriş Yapılmamış Odalar İptal Listesine Aktarılması
- **İşlem:** Check-in tarihi geçmiş ve check-in yapılmamış rezervasyonları iptal et veya yarına al
- **Koşul:** Ayarlarda "Gelmeyen Rezervasyonları İptal Et" aktifse
- **Aksiyon:** 
  - İptal et (varsayılan)
  - Yarına al (opsiyonel)
- **Rapor:** No-show rezervasyon listesi

#### 2.4. Çıkış Yapılmamış Konaklayan Odaların Uzatılması
- **İşlem:** Check-out tarihi geçmiş ve check-out yapılmamış rezervasyonları uzat
- **Koşul:** Ayarlarda "CheckOut Olmamış Konaklayanları UZAT" aktifse
- **Aksiyon:** 
  - Otomatik uzatma (1 gün)
  - Fiyatlandırma güncellemesi
- **Rapor:** Uzatılan rezervasyon listesi

#### 2.5. Oda Değişim Planlarının İptali
- **İşlem:** Planlanmış ama gerçekleşmemiş oda değişimlerini iptal et
- **Koşul:** Ayarlarda "Oda Değişim Planlarını İPTAL Et" aktifse
- **Rapor:** İptal edilen oda değişim listesi

#### 2.6. Oda Fiyatları İşleme
- **İşlem:** Günlük oda fiyatlarını işle ve güncelle
- **Amaç:** Dinamik fiyatlandırma güncellemeleri
- **Rapor:** Fiyat güncelleme raporu

#### 2.7. Giriş ve Folyo İşlemleri Yedekleme
- **İşlem:** Günlük check-in ve folyo işlemlerini yedekle
- **Amaç:** Veri güvenliği
- **Format:** JSON/CSV yedekleme

#### 2.8. Gün Detay Bilgileri Düzenleme
- **İşlem:** Günlük operasyonel verileri düzenle ve kaydet
- **İçerik:**
  - Günlük doluluk oranı
  - Ortalama oda fiyatı (ADR)
  - Toplam gelir
  - Check-in/Check-out sayıları

#### 2.9. Günlük Yönetim Raporları Düzenleme
- **İşlem:** Yönetim için günlük raporları hazırla
- **Raporlar:**
  - Gelir raporu
  - Doluluk raporu
  - Rezervasyon raporu
  - Ödeme raporu

#### 2.10. Gün Sonu Raporları Hazırlama
- **İşlem:** Gün sonu özet raporlarını hazırla
- **Raporlar:**
  - Gün sonu özeti
  - Finansal özet
  - Operasyonel özet

#### 2.11. Misafir Raporları Transfer Etme
- **İşlem:** Misafir raporlarını ilgili departmanlara transfer et
- **Amaç:** Departmanlar arası bilgi paylaşımı

---

## ⚙️ Sistem Gereksinimleri

### 1. **Model Gereksinimleri**

#### `EndOfDayOperation` Modeli
```python
- operation_date: DateField (İşlem tarihi)
- program_date: DateField (Program tarihi)
- hotel: ForeignKey (Otel)
- status: CharField (pending, running, completed, failed)
- is_async: BooleanField (Asenkron mu?)
- settings: JSONField (Ayarlar)
- results: JSONField (Sonuçlar)
- started_at: DateTimeField
- completed_at: DateTimeField
- created_by: ForeignKey (User)
```

#### `EndOfDaySettings` Modeli
```python
- hotel: ForeignKey (Otel)
- stop_if_room_price_zero: BooleanField
- stop_if_advance_folio_balance_not_zero: BooleanField
- check_checkout_folios: BooleanField
- cancel_no_show_reservations: BooleanField
- extend_non_checkout_reservations: BooleanField
- cancel_room_change_plans: BooleanField
- auto_run_time: TimeField (Otomatik çalışma saati)
- is_active: BooleanField
```

#### `EndOfDayReport` Modeli
```python
- operation: ForeignKey (EndOfDayOperation)
- report_type: CharField (summary, financial, operational, guest)
- report_data: JSONField
- report_file: FileField (PDF/Excel)
- generated_at: DateTimeField
```

### 2. **View Gereksinimleri**

- `end_of_day_dashboard` - Ana dashboard
- `end_of_day_settings` - Ayarlar sayfası
- `end_of_day_run` - Gün sonu işlemlerini çalıştır
- `end_of_day_close` - Günü kapat
- `end_of_day_reports` - Raporlar sayfası
- `end_of_day_history` - Geçmiş işlemler

### 3. **Utility Fonksiyonları**

- `check_room_prices()` - Oda fiyatları kontrolü
- `check_advance_folio_balances()` - Peşin folyo kontrolü
- `check_checkout_folios()` - Check-out folyo kontrolü
- `process_no_show_reservations()` - No-show işleme
- `extend_non_checkout_reservations()` - Uzatma işlemi
- `cancel_room_change_plans()` - Oda değişim iptali
- `process_room_prices()` - Fiyat işleme
- `backup_daily_data()` - Yedekleme
- `generate_daily_reports()` - Rapor oluşturma

### 4. **Background Task Gereksinimleri**

- Celery task'ları (asenkron işlemler için)
- Scheduled task'lar (otomatik çalışma için)

---

## 🚀 Geliştirme Planı

### Faz 1: Temel Yapı (1-2 Hafta)
1. Model oluşturma (`EndOfDayOperation`, `EndOfDaySettings`, `EndOfDayReport`)
2. Temel view'lar ve URL'ler
3. Dashboard sayfası
4. Ayarlar sayfası

### Faz 2: Kontrol İşlemleri (1 Hafta)
1. Oda fiyatı kontrolü
2. Peşin folyo balansı kontrolü
3. Check-out folyo kontrolü
4. Hata yönetimi ve uyarılar

### Faz 3: Otomatik İşlemler (2 Hafta)
1. Cash folyo balansı kontrolü
2. Çıkış yapmış odalar kontrolü
3. No-show rezervasyon işleme
4. Uzatma işlemleri
5. Oda değişim iptali
6. Fiyat işleme

### Faz 4: Yedekleme ve Raporlama (1 Hafta)
1. Yedekleme sistemi
2. Gün detay bilgileri
3. Rapor oluşturma
4. PDF/Excel export

### Faz 5: Asenkron İşlemler ve Otomasyon (1 Hafta)
1. Celery entegrasyonu
2. Asenkron gün sonu işlemleri
3. Otomatik çalışma zamanlayıcısı
4. Bildirim sistemi

### Faz 6: Test ve Optimizasyon (1 Hafta)
1. Unit testler
2. Integration testler
3. Performance optimizasyonu
4. Dokümantasyon

---

## 🔧 Teknik Detaylar

### 1. **Asenkron İşlemler**

Asenkron gün sonu işlemleri için Celery kullanılacak:
- Uzun süren işlemler arka planda çalışacak
- Kullanıcı işlem durumunu takip edebilecek
- Hata durumunda bildirim gönderilecek

### 2. **Veri Yedekleme**

- Günlük veriler JSON formatında yedeklenecek
- Yedekler sıkıştırılarak saklanacak
- Yedekleme geçmişi tutulacak

### 3. **Raporlama**

- PDF raporlar (WeasyPrint veya ReportLab)
- Excel raporlar (openpyxl)
- JSON API endpoint'leri

### 4. **Güvenlik**

- Sadece yetkili kullanıcılar gün sonu işlemlerini çalıştırabilir
- İşlem geçmişi loglanacak
- Rollback mekanizması (hata durumunda geri alma)

---

## 📝 Notlar

1. **Gün Sonu İşlemleri genellikle gece 02:00-04:00 arası yapılır**
2. **İşlemler sıralı olarak çalışmalı (bir hata varsa durmalı)**
3. **Her işlem için detaylı log tutulmalı**
4. **Kullanıcı işlem durumunu gerçek zamanlı görebilmeli**
5. **Hata durumunda bildirim gönderilmeli**

---

## ✅ Onay Bekleniyor

Bu rapor onayınıza sunulmuştur. Onayınız sonrasında geliştirme sürecine başlanacaktır.

**Önerilen Başlangıç:** Faz 1 - Temel Yapı




