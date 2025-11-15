# Reception Modülü - Otel Bazlı Yetki Sistemi

## 📋 Genel Bakış

Reception (Önbüro) modülü için **otel bazlı yetki sistemi** uygulanmıştır. Bu sistem, kullanıcıların belirli otellere erişim yetkisi olup olmadığını ve hangi seviyede yetkiye sahip olduklarını kontrol eder.

## 🔐 Yetki Kontrol Sistemi

### İki Seviyeli Yetki Kontrolü

1. **Modül Yetkisi (Module Permission)**
   - Reception modülüne erişim yetkisi
   - `Permission` modeli üzerinden kontrol edilir
   - Rol bazlı veya direkt kullanıcıya atanabilir

2. **Otel Yetkisi (Hotel Permission)**
   - Belirli bir otel için erişim yetkisi
   - `HotelUserPermission` modeli üzerinden kontrol edilir
   - Otel bazlı yetki seviyeleri: `view`, `manage`, `admin`

### Yetki Seviyeleri

#### Modül Yetkileri (Reception)
- `view`: Rezervasyonları görüntüleme
- `add`: Yeni rezervasyon oluşturma
- `edit`: Rezervasyon düzenleme
- `delete`: Rezervasyon silme
- `checkin`: Check-in yapma
- `checkout`: Check-out yapma

#### Otel Yetki Seviyeleri
- `view`: Sadece görüntüleme (read-only)
- `manage`: Yönetim (ekleme, düzenleme, check-in/out)
- `admin`: Tam yetki (silme dahil)

### Yetki Kontrol Akışı

```
Kullanıcı İsteği
    ↓
1. Login Kontrolü
    ↓
2. Aktif Otel Kontrolü
    ↓
3. Reception Modülü Yetkisi Kontrolü
    ↓
4. Otel Bazlı Yetki Kontrolü
    ↓
5. İşlem Seviyesi Yetki Kontrolü
    ↓
İşlem İzni Verilir/Reddedilir
```

## 🛠️ Decorator Kullanımı

### `require_hotel_permission` (Mevcut)

Reception view'larında şu anda `require_hotel_permission` decorator'ı kullanılıyor:

```python
from apps.tenant_apps.hotels.decorators import require_hotel_permission

@login_required
@require_hotel_permission('view')
def dashboard(request):
    """Rezervasyon Dashboard"""
    ...
```

### `require_reception_permission` (Gelişmiş)

Reception modülü için özel decorator oluşturuldu. Bu decorator hem modül yetkisini hem de otel yetkisini kontrol eder:

```python
from apps.tenant_apps.reception.decorators import require_reception_permission

@login_required
@require_reception_permission('add')
def reservation_create(request):
    """Yeni Rezervasyon Oluştur"""
    ...
```

### Yetki Seviyesi Eşleştirmesi

Reception işlemleri için gereken otel yetki seviyeleri:

| Reception İşlemi | Gerekli Otel Yetki Seviyesi |
|------------------|----------------------------|
| `view`           | `view` (0)                  |
| `add`            | `manage` (1)                |
| `edit`           | `manage` (1)                |
| `delete`         | `admin` (2)                 |
| `checkin`        | `manage` (1)                |
| `checkout`       | `manage` (1)                |

## 📝 Yetki Atama

### 1. Modül Yetkisi Atama

**Tenant Panel → Kullanıcılar → Kullanıcı Seç → Yetki Ata**

1. Reception modülünü seç
2. İstenen yetkileri seç (view, add, edit, delete, checkin, checkout)
3. Kaydet

**Veya Rol Üzerinden:**

1. Tenant Panel → Roller → Rol Seç → Yetki Ata
2. Reception modülü yetkilerini seç
3. Kullanıcıya rol atanır

### 2. Otel Yetkisi Atama

**Tenant Panel → Oteller → Kullanıcılar → Otel Yetkisi Ata**

1. Kullanıcıyı seç
2. Otelleri seç
3. Her otel için yetki seviyesi seç (view, manage, admin)
4. Kaydet

**Veya Toplu Atama:**

1. Tenant Panel → Oteller → Kullanıcılar → Toplu Otel Yetkisi Ata
2. Birden fazla kullanıcı seç
3. Otelleri ve yetki seviyelerini seç
4. Kaydet

## 🎯 Kullanım Senaryoları

### Senaryo 1: Sadece Görüntüleme Yetkisi

**Kullanıcı:**
- Reception modülü: `view` yetkisi
- Otel A: `view` seviyesi

**Sonuç:**
- ✅ Rezervasyonları görüntüleyebilir
- ❌ Yeni rezervasyon oluşturamaz
- ❌ Rezervasyon düzenleyemez
- ❌ Check-in/out yapamaz

### Senaryo 2: Yönetim Yetkisi

**Kullanıcı:**
- Reception modülü: `add`, `edit`, `checkin`, `checkout` yetkileri
- Otel A: `manage` seviyesi

**Sonuç:**
- ✅ Rezervasyonları görüntüleyebilir
- ✅ Yeni rezervasyon oluşturabilir
- ✅ Rezervasyon düzenleyebilir
- ✅ Check-in/out yapabilir
- ❌ Rezervasyon silemez

### Senaryo 3: Tam Yetki

**Kullanıcı:**
- Reception modülü: Tüm yetkiler
- Otel A: `admin` seviyesi

**Sonuç:**
- ✅ Tüm işlemleri yapabilir (silme dahil)

### Senaryo 4: Çoklu Otel

**Kullanıcı:**
- Reception modülü: Tüm yetkiler
- Otel A: `manage` seviyesi
- Otel B: `view` seviyesi

**Sonuç:**
- Otel A'da: Yönetim yetkileri (ekleme, düzenleme, check-in/out)
- Otel B'da: Sadece görüntüleme

## 🔧 Teknik Detaylar

### Decorator İmplementasyonu

```python
def require_reception_permission(permission_level='view'):
    """
    Reception modülü yetki kontrolü
    - Modül yetkisi kontrolü
    - Otel bazlı yetki kontrolü
    - İşlem seviyesi kontrolü
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            # 1. Login kontrolü
            # 2. Aktif otel kontrolü
            # 3. Modül yetkisi kontrolü
            # 4. Otel yetkisi kontrolü
            # 5. İşlem seviyesi kontrolü
            return view_func(request, *args, **kwargs)
        return _wrapped_view
    return decorator
```

### View Kullanımı

```python
@login_required
@require_reception_permission('add')
def reservation_create(request):
    """Yeni Rezervasyon Oluştur"""
    hotel = request.active_hotel  # Otel yetkisi kontrol edilmiş
    ...
```

## 📊 Veritabanı Yapısı

### HotelUserPermission Modeli

```python
class HotelUserPermission(TimeStampedModel):
    tenant_user = ForeignKey(TenantUser)
    hotel = ForeignKey(Hotel)
    permission_level = CharField(choices=['view', 'manage', 'admin'])
    is_active = BooleanField()
    assigned_by = ForeignKey(User)
```

### Permission Modeli (Reception Modülü)

```python
class Permission(TimeStampedModel):
    module = ForeignKey(Module)  # code='reception'
    code = CharField()  # 'view', 'add', 'edit', 'delete', 'checkin', 'checkout'
    name = CharField()
    description = TextField()
```

## 🚀 Kurulum

### 1. Reception Modülü Oluştur

```bash
python manage.py create_reception_module
```

### 2. Reception Permission'larını Oluştur

```bash
python manage.py create_reception_permissions
```

### 3. Kullanıcılara Yetki Ata

1. **Modül Yetkisi:**
   - Tenant Panel → Kullanıcılar → Yetki Ata
   - Reception modülü yetkilerini seç

2. **Otel Yetkisi:**
   - Tenant Panel → Oteller → Kullanıcılar → Otel Yetkisi Ata
   - Otelleri ve yetki seviyelerini seç

## ✅ Özellikler

- ✅ İki seviyeli yetki kontrolü (modül + otel)
- ✅ Otel bazlı erişim kontrolü
- ✅ İşlem seviyesi yetki kontrolü
- ✅ Çoklu otel desteği
- ✅ Rol bazlı yetki atama
- ✅ Direkt kullanıcı yetki atama
- ✅ Toplu yetki atama
- ✅ Detaylı hata mesajları

## 📌 Notlar

1. **Superuser/Staff:** Tüm yetkilere sahiptir, kontrol atlanır
2. **Modül Admin:** Reception modülü admin yetkisi varsa, otel yetkisi kontrol edilmez
3. **Otel Yetkisi Yok:** Modül yetkisi varsa, sadece `view` seviyesinde izin verilir
4. **Otel Yetkisi Var:** İşlem seviyesi kontrolü yapılır

## 🔍 Hata Mesajları

- "Aktif otel seçilmedi." → Otel seçilmemiş
- "Reception modülüne erişim yetkiniz bulunmamaktadır." → Modül yetkisi yok
- "{Otel Adı} oteline erişim yetkiniz bulunmamaktadır." → Otel yetkisi yok
- "Bu işlem için yeterli yetkiniz bulunmamaktadır." → İşlem seviyesi yetkisi yetersiz





