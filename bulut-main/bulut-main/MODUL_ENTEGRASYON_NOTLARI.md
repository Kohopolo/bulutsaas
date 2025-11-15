# Modül Entegrasyon Notları

## Ortak Modül Entegrasyon Sistemi

### Mevcut Durum

Sistem, modüller arası entegrasyon için **Django Signals** kullanmaktadır. Bu sayede:
- **Finance (Kasa)** modülü
- **Accounting (Muhasebe)** modülü  
- **Refunds (İade Yönetimi)** modülü

Tüm modüllerden (Tur, Otel, Villa, Bungalov, vb.) otomatik olarak kullanılabilir.

### Entegrasyon Yapısı

#### 1. Tur Modülü ✅
- **Dosya:** `apps/tenant_apps/tours/signals.py`
- **Entegrasyonlar:**
  - ✅ Finance: `TourPayment` → `CashTransaction` (otomatik)
  - ✅ Accounting: `TourReservation` → `Invoice` (otomatik)
  - ✅ Refunds: `TourReservation` (iptal) → `RefundRequest` (otomatik)

#### 2. Otel Modülü ⚠️ (Hazır, Rezervasyon modelleri oluşturulduğunda aktif)
- **Dosya:** `apps/tenant_apps/hotels/signals.py`
- **Durum:** Signals dosyası hazır, rezervasyon modelleri oluşturulduğunda aktif hale getirilecek
- **Entegrasyonlar:**
  - ⚠️ Finance: `HotelReservationPayment` → `CashTransaction` (hazır)
  - ⚠️ Accounting: `HotelReservation` → `Invoice` (hazır)
  - ⚠️ Refunds: `HotelReservation` (iptal) → `RefundRequest` (hazır)

#### 3. Gelecek Modüller
Aşağıdaki modüller eklendiğinde aynı yapı kullanılacak:
- Villa Modülü
- Bungalov Modülü
- Devre Mülk Modülü
- Feribot Bileti Modülü
- Acente Modülü
- Call Center Modülü
- Satış Yönetimi Modülü

### Entegrasyon Standartları

Her yeni modül için:

1. **Signals Dosyası Oluştur:**
   ```python
   # apps/tenant_apps/[modul]/signals.py
   from django.db.models.signals import post_save, pre_save
   from django.dispatch import receiver
   
   @receiver(post_save, sender=[Modul]Payment)
   def create_finance_transaction(sender, instance, created, **kwargs):
       # Finance entegrasyonu
       pass
   
   @receiver(post_save, sender=[Modul]Reservation)
   def create_accounting_invoice(sender, instance, created, **kwargs):
       # Accounting entegrasyonu
       pass
   
   @receiver(pre_save, sender=[Modul]Reservation)
   def create_refund_request(sender, instance, **kwargs):
       # Refunds entegrasyonu
       pass
   ```

2. **Apps.py'da Signals'ı Yükle:**
   ```python
   def ready(self):
       import apps.tenant_apps.[modul].signals
   ```

3. **source_module Parametresi:**
   - Tüm entegrasyonlarda `source_module` parametresi modül kodunu içermelidir:
     - `'tours'` - Tur modülü
     - `'hotels'` - Otel modülü
     - `'villas'` - Villa modülü
     - vb.

### Fiyatlama Utility Fonksiyonu

**ÖNEMLİ NOT:** 
- `calculate_dynamic_price` fonksiyonu (`apps/tenant_apps/core/utils.py`) **sadece Otel modülünün rezervasyon hesaplamalarında** kullanılacaktır.
- Tur modülü kendi dinamik fiyatlama sistemini kullanmaktadır (`Tour.calculate_dynamic_price`).
- Diğer modüller (Villa, Bungalov, vb.) eklendiğinde kendi fiyatlama sistemlerini kullanabilir veya bu utility'yi kullanabilirler.

### Utility Fonksiyonları

Ortak modüller için utility fonksiyonları:

1. **Finance:**
   - `create_cash_transaction()` - Kasa işlemi oluştur
   - `get_default_cash_account()` - Varsayılan kasa hesabını al

2. **Accounting:**
   - `create_invoice()` - Fatura oluştur
   - `create_payment()` - Ödeme kaydı oluştur

3. **Refunds:**
   - `create_refund_request()` - İade talebi oluştur
   - `process_refund()` - İade işlemini gerçekleştir

### Test Edilmesi Gerekenler

- [ ] Tur modülü → Finance entegrasyonu
- [ ] Tur modülü → Accounting entegrasyonu
- [ ] Tur modülü → Refunds entegrasyonu
- [ ] Otel modülü → Finance entegrasyonu (rezervasyon modelleri oluşturulduğunda)
- [ ] Otel modülü → Accounting entegrasyonu (rezervasyon modelleri oluşturulduğunda)
- [ ] Otel modülü → Refunds entegrasyonu (rezervasyon modelleri oluşturulduğunda)

### Yeni Ortak Modül Ekleme

Sistem, yeni ortak modüllerin eklenmesine tamamen açıktır. Örnek: **Web Site Oluşturucu Modülü**

#### Yeni Ortak Modül Ekleme Adımları:

1. **Modül Oluştur:**
   ```bash
   python manage.py startapp website_builder apps/tenant_apps/website_builder
   ```

2. **Settings.py'a Ekle:**
   ```python
   TENANT_APPS = [
       # ... mevcut modüller
       'apps.tenant_apps.website_builder',  # Yeni modül
   ]
   ```

3. **Module Tablosuna Kaydet:**
   - Super Admin panelden veya management command ile
   - `code='website_builder'`, `name='Web Site Oluşturucu'`

4. **Paketlere Ekle:**
   - Super Admin panelden paketlere modülü ekle
   - Yetkileri ve limitleri tanımla

5. **Diğer Modüllerle Entegre Et:**
   - Tur, Otel, Villa modüllerinden web site oluşturucuya erişim
   - Utility fonksiyonları ile modüller arası iletişim

#### Örnek: Web Site Oluşturucu Modülü

```python
# apps/tenant_apps/website_builder/models.py
class Website(TimeStampedModel):
    source_module = models.CharField('Kaynak Modül', max_length=50)  # 'tours', 'hotels', 'villas'
    source_id = models.IntegerField('Kaynak ID')  # Tour ID, Hotel ID, vb.
    # ... web site bilgileri
```

Bu şekilde:
- Tur modülünden → Web site oluşturucuya erişim
- Otel modülünden → Web site oluşturucuya erişim
- Villa modülünden → Web site oluşturucuya erişim

Tüm modüller aynı ortak modülü kullanabilir!

### Son Güncelleme
2025-01-XX - Otel modülü signals dosyası hazırlandı, rezervasyon modelleri oluşturulduğunda aktif hale getirilecek.

