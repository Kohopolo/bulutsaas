# Görev ve Task Standartları

## 📋 Genel Standartlar

### 1. Her Yeni Modül ve İşlev İçin Zorunlu Adımlar

#### 1.1. Modül Oluşturma
- ✅ Modül modeli oluştur (`apps/modules/models.py` veya yeni model)
- ✅ Modül kaydı oluştur komutu (`create_[modul]_module.py`)
- ✅ Modülü public schema'ya kaydet

#### 1.2. Yetki Sistemi Entegrasyonu
- ✅ Yetki oluşturma komutu (`create_[modul]_permissions.py`)
- ✅ Tüm tenant'larda yetki oluşturma komutu (`create_[modul]_permissions_all_tenants.py`)
- ✅ **Admin rolüne otomatik yetki atama** (`assign_module_permissions_to_admin`)
- ✅ Yetki oluşturma komutunun sonuna otomatik admin yetki atama ekle

#### 1.3. Paket Sistemi Entegrasyonu
- ✅ Paketlere modül ekleme komutu (`add_[modul]_module_to_packages.py`)
- ✅ Varsayılan yetkileri tanımla
- ✅ Varsayılan limitleri tanımla
- ✅ Tüm aktif paketlere modülü ekle

#### 1.4. Sidebar ve URL Entegrasyonu
- ✅ Sidebar'a modül linki ekle (`templates/tenant/base.html`)
- ✅ Context processor'a modül kontrolü ekle (`has_[modul]_module`)
- ✅ URL'leri tanımla (`apps/tenant_apps/[modul]/urls.py`)
- ✅ View'lara yetki kontrolü ekle (`@require_module_permission`)

#### 1.5. Template ve Form Oluşturma
- ✅ List template (`list.html`)
- ✅ Form template (`form.html`)
- ✅ Detail template (`detail.html`)
- ✅ Form sınıfları (`forms.py`)

#### 1.6. Migration'lar
- ✅ Model migration'ları oluştur
- ✅ Shared schema migration'ları çalıştır
- ✅ Tenant schema migration'ları çalıştır

---

## 🔐 Admin Otomatik Yetki Atama Sistemi

### 2.1. Yeni Modül Eklendiğinde

**ZORUNLU:** Her yeni modül için yetki oluşturma komutunun sonuna admin rolüne otomatik yetki atama eklenmelidir:

```python
# apps/tenant_apps/[modul]/management/commands/create_[modul]_permissions.py

# Yetkileri oluşturduktan sonra:
try:
    from django.core.management import call_command
    call_command('assign_module_permissions_to_admin', '--module-code', '[modul_kodu]', verbosity=0)
    self.stdout.write(self.style.SUCCESS('[OK] [Modul] modulu yetkileri admin rolune otomatik atandi'))
except Exception as e:
    self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))
```

### 2.2. Mevcut Modüller İçin Yetki Atama

Mevcut modüller için admin rolüne yetki atamak:

```bash
# Belirli modül için
python manage.py assign_module_permissions_to_admin_all_tenants --module-code=customers

# Tüm modüller için
python manage.py assign_module_permissions_to_admin_all_tenants
```

### 2.3. Subscription Signal'inde Otomatik Atama

`apps/subscriptions/signals.py` içinde:
- Yeni subscription aktif olduğunda `assign_all_permissions_to_admin_role` çağrılır
- Tüm aktif yetkiler admin rolüne otomatik atanır

---

## 📦 Paket Sistemi Entegrasyonu

### 3.1. Modülü Paketlere Ekleme

**ZORUNLU:** Her yeni modül için paketlere ekleme komutu oluştur:

```python
# apps/packages/management/commands/add_[modul]_module_to_packages.py

from apps.modules.models import Module
from apps.packages.models import Package, PackageModule

# Modülü bul
module = Module.objects.get(code='[modul_kodu]')

# Tüm aktif paketlere ekle
packages = Package.objects.filter(is_active=True)
for package in packages:
    PackageModule.objects.get_or_create(
        package=package,
        module=module,
        defaults={
            'is_enabled': True,
            'permissions': {
                'view': True,
                'add': True,
                'edit': True,
                'delete': True,
            },
            'limits': {},
        }
    )
```

### 3.2. Paket Modül Yetki Tanımlamaları

Her modül için `PackageModule` modelinde:
- `permissions`: Modül yetkileri (dict)
- `limits`: Modül limitleri (dict)

Örnek:
```python
'permissions': {
    'view': True,
    'add': True,
    'edit': True,
    'delete': True,
    'export': True,  # Modüle özel yetkiler
}
'limits': {
    'max_users': 10,  # Modüle özel limitler
    'max_tours': 50,
}
```

---

## ✅ Kontrol Listesi

Her yeni modül/işlev için kontrol listesi:

- [ ] Modül modeli oluşturuldu
- [ ] Modül kaydı oluştur komutu (`create_[modul]_module.py`)
- [ ] Yetki oluşturma komutu (`create_[modul]_permissions.py`)
- [ ] **Admin rolüne otomatik yetki atama eklendi**
- [ ] Tüm tenant'larda yetki oluşturma komutu (`create_[modul]_permissions_all_tenants.py`)
- [ ] Paketlere modül ekleme komutu (`add_[modul]_module_to_packages.py`)
- [ ] Sidebar entegrasyonu
- [ ] Context processor güncellemesi
- [ ] URL tanımlamaları
- [ ] View'lar ve yetki kontrolleri
- [ ] Template'ler
- [ ] Form'lar
- [ ] Migration'lar
- [ ] Test edildi

---

## 📝 Notlar

1. **Admin Yetki Atama:** Her yeni modül eklendiğinde admin rolüne otomatik yetki atama **ZORUNLUDUR**
2. **Paket Entegrasyonu:** Her modül paket sistemine entegre edilmelidir
3. **Yetki Kontrolü:** Tüm view'larda `@require_module_permission` kullanılmalıdır
4. **Migration'lar:** Shared ve tenant schema migration'ları ayrı ayrı çalıştırılmalıdır

---

## 🔄 Güncelleme Tarihi

Son güncelleme: 2025-01-XX
Versiyon: 1.0

