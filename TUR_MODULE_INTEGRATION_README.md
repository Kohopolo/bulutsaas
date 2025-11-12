# Tur Modülü Paket/Modül Sistemi Entegrasyonu

## 📋 Genel Bakış

Tur modülü artık SaaS paket/modül sistemine entegre edilmiştir. Bu sayede:
- Paket bazlı tur sayısı limitleri
- Paket bazlı tur modülü kullanıcı sayısı limitleri
- Paket bazlı tur rezervasyon sayısı limitleri
- Detaylı yetki sistemi (view, add, edit, delete, report, vb.)

## 🚀 Kurulum

### 1. Tur Modülünü Oluştur

```bash
python manage.py create_tour_module
```

Bu komut `Module` tablosuna "Tur Modülü"nü ekler ve detaylı yetki tanımlarını oluşturur.

### 2. Tenant Schema'da Yetkileri Oluştur

Her tenant schema'da çalıştırılmalı:

```bash
# Public schema'da (super admin)
python manage.py create_tour_permissions

# Tenant schema'da (örnek: test-otel)
python manage.py migrate_schemas --schema=test-otel
python manage.py create_tour_permissions --schema=test-otel
```

### 3. Paket Yönetiminde Tur Modülünü Aktifleştir

Super Admin panelinden:
1. **Paketler** > Paket seç > **Düzenle**
2. **Modüller** sekmesinde **Tur Modülü**nü seç
3. **Aktif** işaretle
4. **Limitler** JSON alanına şunları ekle:

```json
{
  "max_tours": 50,
  "max_tour_users": 5,
  "max_tour_reservations": 1000,
  "max_tour_reservations_per_month": 100
}
```

5. **Yetkiler** JSON alanına şunları ekle:

```json
{
  "view": true,
  "add": true,
  "edit": true,
  "delete": false,
  "report": true,
  "export": true,
  "reservation_view": true,
  "reservation_add": true,
  "reservation_edit": true,
  "reservation_delete": false,
  "reservation_cancel": true,
  "reservation_refund": false,
  "reservation_payment": true,
  "reservation_voucher": true,
  "dynamic_manage": true
}
```

## 📊 Limitler

### Tur Sayısı Limiti (`max_tours`)
- Pakette maksimum kaç tur eklenebileceğini belirler
- Limit aşıldığında yeni tur ekleme engellenir
- Mesaj: "Tur limitine ulaştınız. Maksimum X tur ekleyebilirsiniz."

### Tur Modülü Kullanıcı Sayısı Limiti (`max_tour_users`)
- Tur modülüne erişebilen kullanıcı sayısını belirler
- Tur modülünde en az bir yetkisi olan kullanıcılar sayılır
- Limit aşıldığında uyarı mesajı gösterilir

### Tur Rezervasyon Sayısı Limiti (`max_tour_reservations`)
- Toplam rezervasyon sayısı limiti
- Limit aşıldığında yeni rezervasyon ekleme engellenir

### Aylık Tur Rezervasyon Limiti (`max_tour_reservations_per_month`)
- Aylık rezervasyon sayısı limiti
- Her ay sıfırlanır
- Limit aşıldığında yeni rezervasyon ekleme engellenir

## 🔐 Yetkiler

Tur modülü için detaylı yetkiler:

### Temel Tur Yetkileri
- `view`: Tur görüntüleme
- `add`: Tur ekleme
- `edit`: Tur düzenleme
- `delete`: Tur silme
- `report`: Tur raporlama
- `export`: Tur dışa aktarma

### Rezervasyon Yetkileri
- `reservation_view`: Rezervasyon görüntüleme
- `reservation_add`: Rezervasyon ekleme
- `reservation_edit`: Rezervasyon düzenleme
- `reservation_delete`: Rezervasyon silme
- `reservation_cancel`: Rezervasyon iptal
- `reservation_refund`: Rezervasyon iade
- `reservation_payment`: Rezervasyon ödeme
- `reservation_voucher`: Voucher oluşturma

### Dinamik Yönetim Yetkileri
- `dynamic_manage`: Bölge, Şehir, Tür, Lokasyon yönetimi

## 💻 Kod Kullanımı

### Decorator'lar

```python
from apps.tenant_apps.tours.decorators import (
    require_tour_module,
    check_tour_limit,
    check_tour_reservation_limit
)

@login_required
@require_tour_module  # Modülün pakette aktif olduğunu kontrol eder
@check_tour_limit  # Tur sayısı limitini kontrol eder
def tour_create(request):
    # ...
```

### Limit Kontrolü

```python
from apps.tenant_apps.tours.decorators import get_tour_module_limits

limits = get_tour_module_limits(request)
if limits:
    max_tours = limits['max_tours']
    current_tours = Tour.objects.filter(is_active=True).count()
    if current_tours >= max_tours:
        # Limit aşıldı
        pass
```

## 📈 Kullanım İstatistikleri

Paket yönetim sayfasında tur modülü istatistikleri gösterilir:

- `current_tours`: Mevcut tur sayısı
- `current_tour_users`: Tur modülüne erişimi olan kullanıcı sayısı
- `current_tour_reservations`: Toplam rezervasyon sayısı
- `current_tour_reservations_this_month`: Bu ayki rezervasyon sayısı

## ⚠️ Önemli Notlar

1. **Migration Gerekli**: Yeni decorator'lar ve fonksiyonlar eklendi, migration gerekmez (sadece komutlar çalıştırılmalı)

2. **Tenant Schema**: `create_tour_permissions` komutu her tenant schema'da çalıştırılmalı

3. **Paket Limitleri**: Paket limitleri `PackageModule.limits` JSON alanında saklanır

4. **Yetki Kontrolü**: Tüm tur views'ları `@require_tour_module` decorator'ı ile korunmalı

5. **Limit Mesajları**: Limit aşıldığında kullanıcıya bilgilendirici mesajlar gösterilir

## 🔄 Güncelleme

Eğer tur modülü zaten varsa:

```bash
# Modülü güncelle
python manage.py create_tour_module

# Yetkileri güncelle (her tenant schema'da)
python manage.py create_tour_permissions --schema=tenant_schema_name
```

## 📝 Örnek Paket Yapılandırması

### Temel Paket
```json
{
  "max_tours": 10,
  "max_tour_users": 2,
  "max_tour_reservations": 100,
  "max_tour_reservations_per_month": 20
}
```

### Profesyonel Paket
```json
{
  "max_tours": 100,
  "max_tour_users": 10,
  "max_tour_reservations": 5000,
  "max_tour_reservations_per_month": 500
}
```

### Enterprise Paket
```json
{
  "max_tours": -1,  // Sınırsız
  "max_tour_users": -1,  // Sınırsız
  "max_tour_reservations": -1,  // Sınırsız
  "max_tour_reservations_per_month": -1  // Sınırsız
}
```

**Not**: `-1` değeri sınırsız anlamına gelir.

