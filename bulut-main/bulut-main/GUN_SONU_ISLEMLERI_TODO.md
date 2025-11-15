# Gün Sonu İşlemleri (End of Day Operations) - TODO Listesi

## 📋 Genel Bakış
Bu doküman, resepsiyon modülüne eklenecek "Gün Sonu İşlemleri" sisteminin detaylı geliştirme planını içermektedir.

---

## 🎯 Faz 1: Temel Yapı ve Modeller

### 1.1. Model Oluşturma
- [ ] `EndOfDayOperation` modeli oluştur
  - [ ] `operation_date` (DateField)
  - [ ] `program_date` (DateField)
  - [ ] `hotel` (ForeignKey)
  - [ ] `status` (CharField: pending, running, completed, failed)
  - [ ] `is_async` (BooleanField)
  - [ ] `settings` (JSONField)
  - [ ] `results` (JSONField)
  - [ ] `started_at` (DateTimeField)
  - [ ] `completed_at` (DateTimeField)
  - [ ] `created_by` (ForeignKey)
  - [ ] `error_message` (TextField)
  - [ ] Meta sınıfı ve __str__ metodu

- [ ] `EndOfDaySettings` modeli oluştur
  - [ ] `hotel` (ForeignKey, unique=True)
  - [ ] `stop_if_room_price_zero` (BooleanField, default=True)
  - [ ] `stop_if_advance_folio_balance_not_zero` (BooleanField, default=True)
  - [ ] `check_checkout_folios` (BooleanField, default=True)
  - [ ] `cancel_no_show_reservations` (BooleanField, default=False)
  - [ ] `extend_non_checkout_reservations` (BooleanField, default=False)
  - [ ] `cancel_room_change_plans` (BooleanField, default=False)
  - [ ] `auto_run_time` (TimeField, null=True, blank=True)
  - [ ] `is_active` (BooleanField, default=True)
  - [ ] Meta sınıfı ve __str__ metodu

- [ ] `EndOfDayReport` modeli oluştur
  - [ ] `operation` (ForeignKey)
  - [ ] `report_type` (CharField: summary, financial, operational, guest)
  - [ ] `report_data` (JSONField)
  - [ ] `report_file` (FileField, null=True, blank=True)
  - [ ] `generated_at` (DateTimeField)
  - [ ] Meta sınıfı ve __str__ metodu

- [ ] `EndOfDayOperationStep` modeli oluştur (işlem adımları için)
  - [ ] `operation` (ForeignKey)
  - [ ] `step_name` (CharField)
  - [ ] `step_order` (IntegerField)
  - [ ] `status` (CharField: pending, running, completed, failed)
  - [ ] `started_at` (DateTimeField, null=True)
  - [ ] `completed_at` (DateTimeField, null=True)
  - [ ] `result_data` (JSONField, null=True)
  - [ ] `error_message` (TextField, null=True)

### 1.2. Migration ve Admin
- [ ] Migration dosyalarını oluştur
- [ ] Admin paneli kayıtları
- [ ] Admin list_display, list_filter, search_fields ayarları

### 1.3. URL Yapısı
- [ ] `apps/tenant_apps/reception/urls.py` dosyasına URL'ler ekle:
  - [ ] `end-of-day/` - Dashboard
  - [ ] `end-of-day/settings/` - Ayarlar
  - [ ] `end-of-day/run/` - İşlemleri çalıştır
  - [ ] `end-of-day/close/` - Günü kapat
  - [ ] `end-of-day/reports/` - Raporlar
  - [ ] `end-of-day/history/` - Geçmiş işlemler
  - [ ] `end-of-day/<pk>/` - İşlem detayı
  - [ ] `end-of-day/<pk>/status/` - İşlem durumu (AJAX)

---

## 🎨 Faz 2: View'lar ve Template'ler

### 2.1. Dashboard View
- [ ] `end_of_day_dashboard` view'ı oluştur
  - [ ] Program tarihi ve bugün tarihi gösterimi
  - [ ] Asenkron toggle gösterimi
  - [ ] Ayarlar gösterimi (6 toggle)
  - [ ] İşlem adımları listesi (10 adım)
  - [ ] Son işlem durumu
  - [ ] "Gün Sonu İşlemleri" butonu
  - [ ] "Günü Kapat" butonu

### 2.2. Settings View
- [ ] `end_of_day_settings` view'ı oluştur
  - [ ] GET: Ayarları göster
  - [ ] POST: Ayarları kaydet
  - [ ] Form validasyonu
  - [ ] Başarı/hata mesajları

### 2.3. Run Operations View
- [ ] `end_of_day_run` view'ı oluştur
  - [ ] Ön kontrolleri çalıştır
  - [ ] Hata varsa durdur
  - [ ] İşlem adımlarını sırayla çalıştır
  - [ ] İlerleme takibi
  - [ ] Sonuçları kaydet

### 2.4. Close Day View
- [ ] `end_of_day_close` view'ı oluştur
  - [ ] Gün sonu işlemlerinin tamamlandığını kontrol et
  - [ ] Günü kapat
  - [ ] Yeni gün için hazırlık yap

### 2.5. Reports View
- [ ] `end_of_day_reports` view'ı oluştur
  - [ ] Rapor listesi
  - [ ] Rapor detayı
  - [ ] PDF/Excel export

### 2.6. History View
- [ ] `end_of_day_history` view'ı oluştur
  - [ ] Geçmiş işlemler listesi
  - [ ] Filtreleme (tarih, durum)
  - [ ] Detay görüntüleme

### 2.7. Status View (AJAX)
- [ ] `end_of_day_status` view'ı oluştur
  - [ ] İşlem durumunu döndür
  - [ ] İlerleme yüzdesi
  - [ ] Hata mesajları

### 2.8. Template'ler
- [ ] `templates/reception/end_of_day/dashboard.html`
- [ ] `templates/reception/end_of_day/settings.html`
- [ ] `templates/reception/end_of_day/run.html`
- [ ] `templates/reception/end_of_day/reports.html`
- [ ] `templates/reception/end_of_day/history.html`
- [ ] `templates/reception/end_of_day/detail.html`

---

## 🔍 Faz 3: Kontrol İşlemleri (Pre-Checks)

### 3.1. Oda Fiyatı Kontrolü
- [ ] `check_room_prices()` fonksiyonu oluştur
  - [ ] Tüm aktif rezervasyonları kontrol et
  - [ ] Sıfır fiyatlı rezervasyonları tespit et
  - [ ] Hata listesi oluştur
  - [ ] Ayar aktifse işlemi durdur

### 3.2. Peşin Folyo Balansı Kontrolü
- [ ] `check_advance_folio_balances()` fonksiyonu oluştur
  - [ ] Peşin ödemeli rezervasyonları bul
  - [ ] Folyo balanslarını kontrol et
  - [ ] Sıfır olmayan balansları tespit et
  - [ ] Ayar aktifse işlemi durdur

### 3.3. Check-out Folyo Kontrolü
- [ ] `check_checkout_folios()` fonksiyonu oluştur
  - [ ] Check-out yapılmış rezervasyonları bul
  - [ ] Folyo balanslarını kontrol et
  - [ ] Sıfır olmayan balansları tespit et
  - [ ] Ayar aktifse işlemi durdur

### 3.4. Hata Yönetimi
- [ ] Hata mesajlarını formatla
- [ ] Kullanıcıya gösterilebilir hale getir
- [ ] Loglama

---

## ⚙️ Faz 4: Otomatik İşlemler

### 4.1. Cash Folyo Balansı Kontrolü
- [ ] `process_cash_folio_balances()` fonksiyonu oluştur
  - [ ] Tüm aktif rezervasyonların cash balanslarını kontrol et
  - [ ] Özet rapor oluştur
  - [ ] Sonuçları kaydet

### 4.2. Çıkış Yapmış Odalar Kontrolü
- [ ] `process_checkout_room_balances()` fonksiyonu oluştur
  - [ ] Check-out yapılmış odaları bul
  - [ ] Folyo balanslarını kontrol et
  - [ ] Hata listesi oluştur
  - [ ] Rapor oluştur

### 4.3. No-Show Rezervasyon İşleme
- [ ] `process_no_show_reservations()` fonksiyonu oluştur
  - [ ] Check-in tarihi geçmiş rezervasyonları bul
  - [ ] Check-in yapılmamış rezervasyonları tespit et
  - [ ] Ayar kontrolü (iptal et veya yarına al)
  - [ ] İptal işlemi veya uzatma işlemi
  - [ ] Rapor oluştur

### 4.4. Uzatma İşlemleri
- [ ] `extend_non_checkout_reservations()` fonksiyonu oluştur
  - [ ] Check-out tarihi geçmiş rezervasyonları bul
  - [ ] Check-out yapılmamış rezervasyonları tespit et
  - [ ] Ayar kontrolü
  - [ ] Otomatik uzatma (1 gün)
  - [ ] Fiyatlandırma güncellemesi
  - [ ] Rapor oluştur

### 4.5. Oda Değişim İptali
- [ ] `cancel_room_change_plans()` fonksiyonu oluştur
  - [ ] Planlanmış oda değişimlerini bul
  - [ ] Gerçekleşmemiş değişimleri tespit et
  - [ ] Ayar kontrolü
  - [ ] İptal işlemi
  - [ ] Rapor oluştur

### 4.6. Fiyat İşleme
- [ ] `process_room_prices()` fonksiyonu oluştur
  - [ ] Günlük oda fiyatlarını güncelle
  - [ ] Dinamik fiyatlandırma uygula
  - [ ] Fiyat değişikliklerini logla
  - [ ] Rapor oluştur

### 4.7. Yedekleme
- [ ] `backup_daily_data()` fonksiyonu oluştur
  - [ ] Check-in işlemlerini yedekle
  - [ ] Folyo işlemlerini yedekle
  - [ ] JSON formatında kaydet
  - [ ] Sıkıştırma
  - [ ] Yedekleme geçmişi

### 4.8. Gün Detay Bilgileri
- [ ] `process_daily_details()` fonksiyonu oluştur
  - [ ] Doluluk oranı hesapla
  - [ ] Ortalama oda fiyatı (ADR) hesapla
  - [ ] Toplam gelir hesapla
  - [ ] Check-in/Check-out sayıları
  - [ ] Veritabanına kaydet

### 4.9. Rapor Oluşturma
- [ ] `generate_daily_reports()` fonksiyonu oluştur
  - [ ] Gelir raporu
  - [ ] Doluluk raporu
  - [ ] Rezervasyon raporu
  - [ ] Ödeme raporu
  - [ ] PDF/Excel export

### 4.10. Misafir Raporları Transfer
- [ ] `transfer_guest_reports()` fonksiyonu oluştur
  - [ ] Misafir raporlarını hazırla
  - [ ] İlgili departmanlara gönder
  - [ ] Transfer geçmişi

---

## 🔄 Faz 5: Asenkron İşlemler

### 5.1. Celery Entegrasyonu
- [ ] Celery task'ları oluştur
  - [ ] `run_end_of_day_operations_async` task
  - [ ] `process_end_of_day_step` task
- [ ] Task durumu takibi
- [ ] Hata yönetimi

### 5.2. İlerleme Takibi
- [ ] WebSocket veya AJAX polling
- [ ] İlerleme çubuğu
- [ ] Adım adım durum gösterimi

### 5.3. Bildirim Sistemi
- [ ] İşlem tamamlandığında bildirim
- [ ] Hata durumunda bildirim
- [ ] Email/SMS bildirimleri

### 5.4. Otomatik Çalışma
- [ ] Scheduled task oluştur
- [ ] Belirlenen saatte otomatik çalıştır
- [ ] Ayarlardan zaman yönetimi

---

## 📊 Faz 6: Raporlama ve Export

### 6.1. PDF Raporlar
- [ ] WeasyPrint veya ReportLab entegrasyonu
- [ ] Gün sonu özet raporu
- [ ] Finansal rapor
- [ ] Operasyonel rapor

### 6.2. Excel Raporlar
- [ ] openpyxl entegrasyonu
- [ ] Detaylı veri export
- [ ] Grafikler ve özetler

### 6.3. JSON API
- [ ] API endpoint'leri
- [ ] Rapor verilerini JSON formatında döndür
- [ ] Filtreleme ve sayfalama

---

## 🧪 Faz 7: Test ve Optimizasyon

### 7.1. Unit Testler
- [ ] Model testleri
- [ ] View testleri
- [ ] Utility fonksiyon testleri

### 7.2. Integration Testler
- [ ] End-to-end testler
- [ ] Senaryo testleri

### 7.3. Performance Optimizasyonu
- [ ] Database query optimizasyonu
- [ ] Caching stratejileri
- [ ] Bulk operations

### 7.4. Dokümantasyon
- [ ] Kullanıcı kılavuzu
- [ ] API dokümantasyonu
- [ ] Teknik dokümantasyon

---

## 🔐 Faz 8: Güvenlik ve Yetkilendirme

### 8.1. Yetkilendirme
- [ ] Decorator oluştur (`@require_end_of_day_permission`)
- [ ] View'lara yetki kontrolü ekle
- [ ] Admin paneli yetkileri

### 8.2. Loglama
- [ ] İşlem geçmişi loglama
- [ ] Hata loglama
- [ ] Audit trail

### 8.3. Rollback Mekanizması
- [ ] Hata durumunda geri alma
- [ ] Transaction yönetimi
- [ ] Veri bütünlüğü kontrolü

---

## 📱 Faz 9: UI/UX İyileştirmeleri

### 9.1. Dashboard İyileştirmeleri
- [ ] Real-time güncellemeler
- [ ] İlerleme çubuğu
- [ ] Renk kodlu durumlar

### 9.2. Responsive Tasarım
- [ ] Mobil uyumluluk
- [ ] Tablet uyumluluk

### 9.3. Kullanıcı Deneyimi
- [ ] Tooltip'ler
- [ ] Yardım metinleri
- [ ] Hata mesajları iyileştirme

---

## ✅ Son Kontroller

- [ ] Tüm testler geçiyor mu?
- [ ] Dokümantasyon tamamlandı mı?
- [ ] Performance testleri yapıldı mı?
- [ ] Güvenlik kontrolleri yapıldı mı?
- [ ] Kullanıcı kabul testleri yapıldı mı?

---

## 📝 Notlar

- Her faz tamamlandığında commit yapılmalı
- Her faz için code review yapılmalı
- Test coverage %80'in üzerinde olmalı
- Dokümantasyon güncel tutulmalı

---

**Toplam Tahmini Süre:** 6-8 Hafta
**Öncelik:** Yüksek
**Durum:** Planlama Aşaması - Onay Bekleniyor

