# Reception Modülü - Migration Durumu

## ✅ Tamamlanan İşlemler

### 1. Sidebar Menü ✅
- Reception modülü için sidebar menüsü eklendi
- Menü öğeleri: Dashboard, Rezervasyonlar, Oda Planı, Oda Durumu, Voucher Şablonları

### 2. SaaS Modül Kaydı ✅
- Reception modülü SaaS sistemine kaydedildi (public schema)
- `create_reception_module` komutu çalıştırıldı

### 3. Public Schema Migration ✅
- Public schema'da migration'lar başarıyla uygulandı
- `0001_initial.py` ✅
- `0002_vouchertemplate_and_more.py` ✅

## ⚠️ Yapılması Gerekenler

### Tenant Schema Migration'ları

Tenant schema'larda migration'lar çalıştırılmalı. Her tenant schema için:

```bash
# Virtual environment aktifleştir
venv\Scripts\activate  # Windows
# veya
source venv/bin/activate  # Linux/Mac

# Her tenant schema için migration çalıştır
python manage.py migrate reception 0001 --schema=<tenant_schema_name>
python manage.py migrate reception --schema=<tenant_schema_name>

# Veya tüm tenant'lar için
python manage.py migrate_schemas --tenant
```

**Not:** `migrate_schemas --tenant` komutu çalıştırıldığında bazı tenant schema'larda `reception_reservation` tablosu bulunamadı hatası alınabilir. Bu durumda:

1. Önce `0001_initial.py` migration'ını çalıştırın
2. Sonra `0002_vouchertemplate_and_more.py` migration'ını çalıştırın

### Permission'lar

Her tenant schema'da permission'lar oluşturulmalı:

```bash
# Her tenant schema için
python manage.py create_reception_permissions --schema=<tenant_schema_name>

# Veya tüm tenant'lar için (eğer komut destekliyorsa)
python manage.py create_reception_permissions
```

## 📋 Migration Dosyaları

1. **0001_initial.py**
   - Reservation modeli
   - ReservationGuest modeli
   - ReservationPayment modeli
   - ReservationTimeline modeli
   - ReservationVoucher modeli

2. **0002_vouchertemplate_and_more.py**
   - VoucherTemplate modeli
   - Reservation modeline eklenen alanlar:
     - reservation_agent (ForeignKey to sales.Agency)
     - reservation_channel (ForeignKey to channels.Channel)
     - is_manual_price
     - discount_type, discount_percentage
     - is_comp, is_no_show
     - early_check_in, late_check_out
     - early_check_in_fee, late_check_out_fee
     - cancellation_refund_amount
     - created_by, updated_by (ForeignKey to User)

## 🔧 Sorun Giderme

### Hata: "relation reception_reservation does not exist"

**Çözüm:**
1. Önce `0001_initial.py` migration'ını çalıştırın
2. Sonra `0002_vouchertemplate_and_more.py` migration'ını çalıştırın

```bash
python manage.py migrate reception 0001 --schema=<tenant_schema>
python manage.py migrate reception --schema=<tenant_schema>
```

### Hata: "relation tenant_core_permission does not exist"

**Çözüm:**
Önce tenant_core migration'larını çalıştırın:

```bash
python manage.py migrate tenant_core --schema=<tenant_schema>
```

## 📝 Notlar

- Public schema'da migration'lar başarıyla tamamlandı
- Tenant schema'larda migration'lar manuel olarak çalıştırılmalı
- Her tenant schema için ayrı ayrı permission'lar oluşturulmalı
- Migration sırası önemli: `0001` → `0002`

