# Modüller Arası Veritabanı Bağlantıları - Detaylı Analiz

**Tarih:** 12 Kasım 2025  
**Durum:** Mevcut Bağlantılar ve Öneriler

---

## 📊 MODÜL YAPISI VE VERİTABANI BAĞLANTILARI

### 1. CORE MODÜLÜ (Merkezi Yönetim)

**Modeller:**
- `TenantUser` - Kullanıcı yönetimi
- `Role` - Rol yönetimi
- `Permission` - Yetki yönetimi
- `Customer` - **MERKEZİ MÜŞTERİ YÖNETİMİ (CRM)**

**Bağlantılar:**
- `Customer` → Tüm modüllerden kullanılır (ForeignKey)
- `TenantUser` → Tüm modüllerde `created_by`, `updated_by` için kullanılır

---

### 2. HOTELS MODÜLÜ (Otel Yönetimi)

**Modeller:**
- `Hotel` - Otel bilgileri
- `Room` - Oda tipleri
- `RoomNumber` - Fiziksel oda numaraları
- `RoomPrice` - Oda fiyatlandırması
- `BoardType` - Pansiyon tipleri

**Bağlantılar:**
- `Hotel` → Tüm modüllerden referans alınır
- `Room` → Reception modülünde rezervasyonlar için
- `RoomNumber` → Reception modülünde rezervasyonlara atanır
- `BoardType` → Reception modülünde rezervasyonlarda kullanılır

---

### 3. RECEPTION MODÜLÜ (Resepsiyon - Ön Büro)

**Modeller:**
- `Reservation` - **ANA REZERVASYON MODELİ**
- `CheckIn` - Check-in kayıtları
- `CheckOut` - Check-out kayıtları
- `KeyCard` - Dijital anahtar kartları
- `ReservationUpdate` - Rezervasyon güncelleme logları
- `RoomChange` - Oda değişiklik kayıtları
- `ReceptionSession` - Resepsiyon oturumları
- `ReceptionActivity` - Resepsiyon aktiviteleri

**Bağlantılar:**

#### Reservation Modeli Bağlantıları:
```python
# Otel ve Oda Bağlantıları
hotel = ForeignKey('hotels.Hotel')  # Otel
room = ForeignKey('hotels.Room')  # Oda tipi
room_number = ForeignKey('hotels.RoomNumber')  # Fiziksel oda numarası
board_type = ForeignKey('hotels.BoardType')  # Pansiyon tipi

# Müşteri Bağlantısı (CRM)
customer = ForeignKey('tenant_core.Customer')  # Merkezi müşteri profili

# Kullanıcı Bağlantıları
created_by = ForeignKey(User)  # Oluşturan
checked_in_by = ForeignKey(User)  # Check-in yapan
checked_out_by = ForeignKey(User)  # Check-out yapan
archived_by = ForeignKey(User)  # Arşivleyen

# Tur Acentesi Bağlantısı
agency = ForeignKey('tours.TourAgency')  # Tur acentesi (varsa)
```

#### Signal'lar ile Otomatik Entegrasyonlar:
1. **Finance Modülü Entegrasyonu:**
   - `total_paid` değiştiğinde → `CashTransaction` oluşturulur
   - `create_cash_transaction()` fonksiyonu ile kasa işlemi kaydedilir

2. **Accounting Modülü Entegrasyonu:**
   - Rezervasyon oluşturulduğunda → `Invoice` oluşturulur
   - `total_paid` değiştiğinde → `Payment` kaydı oluşturulur
   - `create_invoice()` ve `create_payment()` fonksiyonları kullanılır

3. **Refunds Modülü Entegrasyonu:**
   - Rezervasyon iptal edildiğinde → `RefundRequest` oluşturulur
   - `create_refund_request()` fonksiyonu ile iade talebi kaydedilir

---

### 4. FINANCE MODÜLÜ (Kasa Yönetimi)

**Modeller:**
- `CashAccount` - Kasa hesapları
- `CashTransaction` - Kasa işlemleri

**Bağlantılar:**
- `CashTransaction` → `source_module` ve `source_id` ile tüm modüllerden referans alır
- Reception modülünden otomatik olarak `CashTransaction` oluşturulur

**Örnek:**
```python
CashTransaction(
    account_id=account.pk,
    transaction_type='income',
    amount=payment_difference,
    source_module='reception',
    source_id=reservation.pk,
    source_reference=f"Rezervasyon: {reservation.reservation_code}",
    ...
)
```

---

### 5. ACCOUNTING MODÜLÜ (Muhasebe)

**Modeller:**
- `Account` - Hesap planı
- `Invoice` - Faturalar
- `Payment` - Ödemeler
- `JournalEntry` - Yevmiye kayıtları

**Bağlantılar:**
- `Invoice` → `source_module='reception'`, `source_id=reservation.pk`
- `Payment` → `invoice` ForeignKey ile faturaya bağlı
- Reception modülünden otomatik olarak `Invoice` ve `Payment` oluşturulur

**Örnek:**
```python
Invoice(
    source_module='reception',
    source_id=reservation.pk,
    source_reference=f"Rezervasyon: {reservation.reservation_code}",
    customer_name=f"{reservation.customer_first_name} {reservation.customer_last_name}",
    total_amount=reservation.total_amount,
    ...
)
```

---

### 6. REFUNDS MODÜLÜ (İade Yönetimi)

**Modeller:**
- `RefundRequest` - İade talepleri

**Bağlantılar:**
- `RefundRequest` → `source_module='reception'`, `source_id=reservation.pk`
- Reception modülünden rezervasyon iptal edildiğinde otomatik oluşturulur

---

### 7. HOUSEKEEPING MODÜLÜ (Kat Hizmetleri)

**Modeller:**
- `HousekeepingTask` - Temizlik görevleri
- `HousekeepingAssignment` - Görev atamaları
- `RoomAmenity` - Oda olanakları
- `LostAndFound` - Kayıp eşya

**Bağlantılar:**
- `HousekeepingTask` → `room_number = ForeignKey('hotels.RoomNumber')`
- `HousekeepingTask` → `reservation = ForeignKey('reception.Reservation')` (opsiyonel)

**ÖNERİ:** Reception modülü ile entegrasyon:
- Check-out yapıldığında otomatik temizlik görevi oluşturulabilir
- Oda durumu değiştiğinde housekeeping'e bildirim gönderilebilir

---

### 8. TECHNICAL_SERVICE MODÜLÜ (Teknik Servis)

**Modeller:**
- `ServiceRequest` - Servis talepleri
- `ServiceAssignment` - Teknisyen atamaları

**Bağlantılar:**
- `ServiceRequest` → `room_number = ForeignKey('hotels.RoomNumber')`
- `ServiceRequest` → `reservation = ForeignKey('reception.Reservation')` (opsiyonel)

**ÖNERİ:** Reception modülü ile entegrasyon:
- Oda bakım durumuna geçtiğinde teknik servis talebi oluşturulabilir

---

### 9. QUALITY_CONTROL MODÜLÜ (Kalite Kontrol)

**Modeller:**
- `QualityChecklist` - Kalite kontrol listeleri
- `QualityAudit` - Denetimler
- `GuestFeedback` - Müşteri geri bildirimleri

**Bağlantılar:**
- `GuestFeedback` → `reservation = ForeignKey('reception.Reservation')`
- `GuestFeedback` → `customer = ForeignKey('tenant_core.Customer')`

**ÖNERİ:** Reception modülü ile entegrasyon:
- Check-out sonrası otomatik geri bildirim formu gönderilebilir

---

### 10. SALES MODÜLÜ (Satış Yönetimi)

**Modeller:**
- `SalesLead` - Satış potansiyelleri
- `SalesOpportunity` - Satış fırsatları
- `SalesActivity` - Satış aktiviteleri

**Bağlantılar:**
- `SalesLead` → `customer = ForeignKey('tenant_core.Customer')`
- `SalesOpportunity` → `customer = ForeignKey('tenant_core.Customer')`

**ÖNERİ:** Reception modülü ile entegrasyon:
- Yeni rezervasyon oluşturulduğunda satış fırsatı kaydedilebilir

---

### 11. STAFF MODÜLÜ (Personel Yönetimi)

**Modeller:**
- `Employee` - Personel kayıtları
- `Shift` - Vardiyalar
- `TimeSheet` - Mesai kayıtları

**Bağlantılar:**
- `Employee` → `hotel = ForeignKey('hotels.Hotel')`
- `Shift` → `hotel = ForeignKey('hotels.Hotel')`

**ÖNERİ:** Reception modülü ile entegrasyon:
- Check-in/out işlemlerini yapan personel bilgisi kaydedilebilir

---

## 🔗 MEVCUT ENTEGRASYONLAR

### ✅ Tam Entegre Edilmiş:

1. **Reception → Finance:**
   - Rezervasyon ödemesi → `CashTransaction` oluşturulur
   - Signal: `sync_payment_to_finance_and_accounting`

2. **Reception → Accounting:**
   - Rezervasyon oluşturulduğunda → `Invoice` oluşturulur
   - Ödeme yapıldığında → `Payment` kaydı oluşturulur
   - Signal: `create_accounting_invoice_on_reservation`

3. **Reception → Refunds:**
   - Rezervasyon iptal edildiğinde → `RefundRequest` oluşturulur
   - Signal: `create_reservation_update_log`

4. **Reception → Core (Customer):**
   - Rezervasyon oluşturulduğunda müşteri bulunur/oluşturulur
   - `Customer.get_or_create_by_identifier()` kullanılır

---

## 🚧 EKSİK ENTEGRASYONLAR (Öneriler)

### 1. Reception → Housekeeping:
- Check-out sonrası otomatik temizlik görevi
- Oda durumu değişikliklerinde bildirim

### 2. Reception → Technical Service:
- Oda bakım durumuna geçtiğinde servis talebi
- Bakım tamamlandığında oda durumu güncelleme

### 3. Reception → Quality Control:
- Check-out sonrası otomatik geri bildirim formu
- Müşteri memnuniyet anketi

### 4. Reception → Sales:
- Yeni rezervasyon → Satış fırsatı kaydı
- Müşteri segmentasyonu

### 5. Reception → Staff:
- Check-in/out işlemlerini yapan personel kaydı
- Personel performans takibi

---

## 📝 REZERVASYON KAYIT SİSTEMİ

### Rezervasyon Nereye Kaydediliyor?

**Model:** `apps.tenant_apps.reception.models.Reservation`

**Veritabanı Tablosu:** `reception_reservation` (tenant schema içinde)

**Kayıt Süreci:**

1. **Form Gönderimi:**
   - `reception/reservations/create/` URL'ine POST isteği
   - `ReservationForm` ile validasyon

2. **View İşlemi:**
   - `reservation_create` view fonksiyonu
   - `apps/tenant_apps/reception/views.py`

3. **Kayıt İşlemleri:**
   ```python
   # 1. Rezervasyon kodu otomatik oluşturulur
   reservation_code = f'RES-{year}-{number:04d}'
   
   # 2. Müşteri bulunur/oluşturulur (CRM)
   customer = Customer.get_or_create_by_identifier(
       email=email,
       phone=phone,
       tc_no=tc_no
   )
   
   # 3. Rezervasyon kaydedilir
   reservation = Reservation.objects.create(
       hotel=hotel,
       room=room,
       customer=customer,
       ...
   )
   ```

4. **Signal'lar Tetiklenir:**
   - `post_save` signal → `create_reservation_update_log`
   - `post_save` signal → `create_accounting_invoice_on_reservation`
   - `pre_save` signal → `sync_payment_to_finance_and_accounting` (ödeme varsa)

5. **Otomatik Entegrasyonlar:**
   - **Accounting:** Fatura oluşturulur
   - **Finance:** Ödeme varsa kasa işlemi kaydedilir
   - **Core (Customer):** Müşteri istatistikleri güncellenir

---

## 🗄️ VERİTABANI YAPISI

### Tenant Schema (Her tenant için ayrı):
- `reception_reservation` - Rezervasyonlar
- `reception_checkin` - Check-in kayıtları
- `reception_checkout` - Check-out kayıtları
- `reception_keycard` - Anahtar kartları
- `hotels_hotel` - Oteller
- `hotels_room` - Oda tipleri
- `hotels_roomnumber` - Oda numaraları
- `tenant_core_customer` - Müşteriler
- `finance_cashaccount` - Kasa hesapları
- `finance_cashtransaction` - Kasa işlemleri
- `accounting_invoice` - Faturalar
- `accounting_payment` - Ödemeler
- `refunds_refundrequest` - İade talepleri

### Public Schema (Tüm tenant'lar için ortak):
- `tenants_tenant` - Tenant bilgileri
- `modules_module` - Modül tanımları
- `packages_package` - Paket tanımları
- `subscriptions_subscription` - Abonelikler

---

## 🔄 VERİ AKIŞI DİYAGRAMI

```
REZERVASYON OLUŞTURULDU
    ↓
Reservation.save()
    ↓
Signal: post_save
    ↓
    ├─→ Accounting: Invoice oluştur
    ├─→ Core: Customer bul/oluştur
    └─→ Reception: ReservationUpdate log
    ↓
ÖDEME YAPILDI (total_paid değişti)
    ↓
Signal: pre_save
    ↓
    ├─→ Finance: CashTransaction oluştur
    └─→ Accounting: Payment kaydı oluştur
    ↓
REZERVASYON İPTAL EDİLDİ
    ↓
Signal: post_save
    ↓
    └─→ Refunds: RefundRequest oluştur
```

---

## 📌 SONUÇ VE ÖNERİLER

### Mevcut Durum:
✅ Reception → Finance entegrasyonu çalışıyor  
✅ Reception → Accounting entegrasyonu çalışıyor  
✅ Reception → Refunds entegrasyonu çalışıyor  
✅ Reception → Core (Customer) entegrasyonu çalışıyor  

### Eksik Entegrasyonlar:
❌ Reception → Housekeeping  
❌ Reception → Technical Service  
❌ Reception → Quality Control  
❌ Reception → Sales  
❌ Reception → Staff  

### Öneriler:
1. Housekeeping modülüne signal eklenebilir (check-out sonrası temizlik görevi)
2. Technical Service modülüne signal eklenebilir (oda bakım durumu)
3. Quality Control modülüne signal eklenebilir (check-out sonrası geri bildirim)
4. Sales modülüne signal eklenebilir (yeni rezervasyon → satış fırsatı)
5. Staff modülüne entegrasyon eklenebilir (personel performans takibi)

