# Feribot Bileti Modülü - Kurulum Talimatları

**Tarih:** 2025-01-XX  
**Modül:** `apps.tenant_apps.ferry_tickets`

---

## 📋 Önkoşullar

1. ✅ Django ve tüm bağımlılıklar kurulu olmalı
2. ✅ Virtual environment aktif olmalı
3. ✅ Veritabanı bağlantısı çalışıyor olmalı
4. ✅ Public schema ve tenant schema'lar mevcut olmalı

---

## 🚀 Kurulum Adımları

### Adım 1: Virtual Environment Aktifleştirme

```bash
# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate
```

### Adım 2: Public Schema'da Modül Oluşturma

```bash
# Public schema'da modülü oluştur
python manage.py create_ferry_tickets_module
```

**Beklenen Çıktı:**
```
[OK] Feribot Bileti modülü oluşturuldu: Feribot Bileti
```

### Adım 3: Migration'ları Çalıştırma

#### 3.1. Public Schema Migration

```bash
# Public schema'da migration çalıştır
python manage.py migrate_schemas --schema=public ferry_tickets
```

**Veya tüm app'ler için:**
```bash
python manage.py migrate_schemas --schema=public
```

#### 3.2. Tenant Schema Migration'ları

**Seçenek A: Tüm Tenant'lar İçin (Önerilen)**

```bash
# Tüm tenant schema'larda migration çalıştır
python manage.py migrate_schemas --tenant ferry_tickets
```

**Seçenek B: Tek Tenant İçin**

```bash
# Belirli bir tenant schema'da migration çalıştır
python manage.py migrate_schemas --schema=<tenant_schema_name> ferry_tickets
```

**Örnek:**
```bash
python manage.py migrate_schemas --schema=test-otel ferry_tickets
```

### Adım 4: Permission'ları Oluşturma

#### 4.1. Tek Tenant İçin

```bash
# Belirli bir tenant schema'da permission'ları oluştur
python manage.py create_ferry_tickets_permissions --schema=<tenant_schema_name>
```

**Örnek:**
```bash
python manage.py create_ferry_tickets_permissions --schema=test-otel
```

#### 4.2. Tüm Tenant'lar İçin (Otomatik Script)

```bash
# Tüm tenant'larda otomatik kurulum (migration + permission)
python manage.py setup_ferry_tickets_all_tenants
```

**Seçenekler:**
```bash
# Sadece permission oluştur (migration'ları atla)
python manage.py setup_ferry_tickets_all_tenants --skip-migration

# Sadece migration çalıştır (permission'ları atla)
python manage.py setup_ferry_tickets_all_tenants --skip-permission

# Public schema'yı atla
python manage.py setup_ferry_tickets_all_tenants --skip-public
```

---

## 📝 Detaylı Kurulum Senaryoları

### Senaryo 1: Yeni Kurulum (Tüm Tenant'lar)

```bash
# 1. Virtual environment aktifleştir
venv\Scripts\activate  # Windows
# veya
source venv/bin/activate  # Linux/Mac

# 2. Public schema'da modül oluştur
python manage.py create_ferry_tickets_module

# 3. Public schema migration
python manage.py migrate_schemas --schema=public ferry_tickets

# 4. Tüm tenant'larda otomatik kurulum
python manage.py setup_ferry_tickets_all_tenants
```

### Senaryo 2: Tek Tenant İçin Kurulum

```bash
# 1. Virtual environment aktifleştir
venv\Scripts\activate

# 2. Public schema'da modül oluştur (eğer yapılmadıysa)
python manage.py create_ferry_tickets_module

# 3. Public schema migration (eğer yapılmadıysa)
python manage.py migrate_schemas --schema=public ferry_tickets

# 4. Tenant schema migration
python manage.py migrate_schemas --schema=<tenant_schema_name> ferry_tickets

# 5. Tenant schema permission
python manage.py create_ferry_tickets_permissions --schema=<tenant_schema_name>
```

### Senaryo 3: Sadece Permission Güncelleme

```bash
# Tüm tenant'larda sadece permission'ları güncelle
python manage.py setup_ferry_tickets_all_tenants --skip-migration
```

---

## ✅ Kurulum Kontrolü

### 1. Modül Kontrolü

```bash
# Django shell'de kontrol et
python manage.py shell

# Shell'de:
from apps.modules.models import Module
module = Module.objects.get(code='ferry_tickets')
print(f"Modül: {module.name}, Aktif: {module.is_active}")
```

### 2. Migration Kontrolü

```bash
# Migration durumunu kontrol et
python manage.py showmigrations ferry_tickets --schema=<tenant_schema_name>
```

### 3. Permission Kontrolü

```bash
# Django shell'de kontrol et
python manage.py shell

# Shell'de (tenant schema'da):
from apps.tenant_apps.core.models import Permission
from apps.modules.models import Module

module = Module.objects.get(code='ferry_tickets')
permissions = Permission.objects.filter(module=module)
print(f"Toplam {permissions.count()} permission bulundu:")
for p in permissions:
    print(f"  - {p.name} ({p.code})")
```

---

## 🔧 Paket Yönetiminde Modülü Aktifleştirme

### Super Admin Panelinden:

1. **Super Admin'e Giriş Yap**
   - URL: `http://your-domain/admin/`
   - Super user ile giriş yap

2. **Paket Yönetimi**
   - Paketler > Paket seç > Düzenle
   - Modüller sekmesine git

3. **Feribot Bileti Modülünü Aktifleştir**
   - "Feribot Bileti" modülünü bul
   - ✅ Aktif işaretle
   - Kaydet

4. **Yetkileri Ayarla (Opsiyonel)**
   - Paket düzenleme sayfasında
   - "Yetkiler" JSON alanına ekle:
   ```json
   {
     "view": true,
     "add": true,
     "edit": true,
     "delete": true,
     "voucher": true,
     "payment": true
   }
   ```

5. **Limitleri Ayarla (Opsiyonel)**
   - "Limitler" JSON alanına ekle:
   ```json
   {
     "max_ferry_tickets": 1000,
     "max_ferry_tickets_per_month": 100
   }
   ```

---

## 🐛 Sorun Giderme

### Hata: "Module not found"

**Çözüm:**
```bash
# Public schema'da modülü oluştur
python manage.py create_ferry_tickets_module
```

### Hata: "Migration not found"

**Çözüm:**
```bash
# Migration dosyalarını kontrol et
ls apps/tenant_apps/ferry_tickets/migrations/

# Migration oluştur (eğer eksikse)
python manage.py makemigrations ferry_tickets
```

### Hata: "Permission already exists"

**Çözüm:**
- Bu hata normaldir, permission zaten mevcut demektir
- Devam edebilirsiniz

### Hata: "Schema does not exist"

**Çözüm:**
```bash
# Tenant schema'ları kontrol et
python manage.py shell

# Shell'de:
from django_tenants.utils import get_tenant_model
TenantModel = get_tenant_model()
tenants = TenantModel.objects.filter(is_active=True)
for t in tenants:
    print(f"{t.schema_name} - {t.name}")
```

---

## 📊 Kurulum Sonrası Kontrol Listesi

- [ ] Public schema'da modül oluşturuldu mu?
- [ ] Public schema'da migration çalıştırıldı mı?
- [ ] Tüm tenant schema'larda migration çalıştırıldı mı?
- [ ] Tüm tenant schema'larda permission'lar oluşturuldu mu?
- [ ] Paket yönetiminde modül aktifleştirildi mi?
- [ ] Modül sidebar'da görünüyor mu?
- [ ] Modül sayfaları açılıyor mu?

---

## 🎯 Sonuç

Kurulum tamamlandıktan sonra:

1. ✅ Modül kullanıma hazır
2. ✅ Tüm tenant'larda erişilebilir
3. ✅ Permission sistemi aktif
4. ✅ Paket kontrolü çalışıyor

**Modül URL'i:** `/ferry-tickets/`

---

**Son Güncelleme:** 2025-01-XX  
**Hazırlayan:** AI Assistant





