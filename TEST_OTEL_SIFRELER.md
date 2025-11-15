# Test-Otel Tenant Kullanıcı Bilgileri

## Tarih: 2025-11-14

---

## 🔐 Test-Otel Tenant Kullanıcı Bilgileri

### Tenant Bilgileri:
- **Tenant Adı:** Test Otel
- **Schema:** tenant_test-otel
- **Domain:** test-otel.localhost
- **Owner Email:** test@example.com

---

## 👤 Kullanıcı Bilgileri

### Ana Kullanıcı:
- **Kullanıcı Adı:** `testadmin`
- **Email:** `admin@testotel.com`
- **Ad Soyad:** Test Admin
- **Şifre:** `test123` (veya `testadmin123` - varsayılan format)

---

## 🌐 Erişim URL'leri

### Tenant Login:
```
http://test-otel.localhost:8000/login/
```

### Tenant Dashboard:
```
http://test-otel.localhost:8000/
http://test-otel.localhost:8000/dashboard/
```

---

## 📝 Şifre Formatı

Varsayılan şifre formatı: `{username}123`

Örnek:
- Kullanıcı adı: `testadmin` → Şifre: `testadmin123`
- Kullanıcı adı: `test` → Şifre: `test123`

---

## 🔧 Şifre Sıfırlama

Şifreyi sıfırlamak için:
```bash
python manage.py shell
```

```python
from django_tenants.utils import schema_context
from apps.tenants.models import Tenant
from django.contrib.auth.models import User

tenant = Tenant.objects.get(schema_name='tenant_test-otel')
with schema_context(tenant.schema_name):
    user = User.objects.get(username='testadmin')
    user.set_password('yeni_sifre')
    user.save()
    print('Şifre güncellendi!')
```

---

**Son Güncelleme:** 2025-11-14




## Tarih: 2025-11-14

---

## 🔐 Test-Otel Tenant Kullanıcı Bilgileri

### Tenant Bilgileri:
- **Tenant Adı:** Test Otel
- **Schema:** tenant_test-otel
- **Domain:** test-otel.localhost
- **Owner Email:** test@example.com

---

## 👤 Kullanıcı Bilgileri

### Ana Kullanıcı:
- **Kullanıcı Adı:** `testadmin`
- **Email:** `admin@testotel.com`
- **Ad Soyad:** Test Admin
- **Şifre:** `test123` (veya `testadmin123` - varsayılan format)

---

## 🌐 Erişim URL'leri

### Tenant Login:
```
http://test-otel.localhost:8000/login/
```

### Tenant Dashboard:
```
http://test-otel.localhost:8000/
http://test-otel.localhost:8000/dashboard/
```

---

## 📝 Şifre Formatı

Varsayılan şifre formatı: `{username}123`

Örnek:
- Kullanıcı adı: `testadmin` → Şifre: `testadmin123`
- Kullanıcı adı: `test` → Şifre: `test123`

---

## 🔧 Şifre Sıfırlama

Şifreyi sıfırlamak için:
```bash
python manage.py shell
```

```python
from django_tenants.utils import schema_context
from apps.tenants.models import Tenant
from django.contrib.auth.models import User

tenant = Tenant.objects.get(schema_name='tenant_test-otel')
with schema_context(tenant.schema_name):
    user = User.objects.get(username='testadmin')
    user.set_password('yeni_sifre')
    user.save()
    print('Şifre güncellendi!')
```

---

**Son Güncelleme:** 2025-11-14




