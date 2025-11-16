# .env Dosyası Kontrol ve Düzeltme

## ⚠️ Eksik ve Düzeltilmesi Gerekenler

`.env` dosyanızda bazı eksiklikler ve production için düzeltilmesi gerekenler var.

---

## ❌ Eksikler

### 1. VPS IP Adresi Eksik

**Eksik:**
```
VPS_IP=72.62.35.155
```

**Neden gerekli:** Django `ALLOWED_HOSTS`'e otomatik eklenir.

### 2. Hostinger VPS IP ve Domain Eksik

**Eksik:**
```
HOSTINGER_VPS_IP=88.255.216.16
HOSTINGER_VPS_DOMAIN=srv1132080.hstgr.cloud
```

**Not:** Bu opsiyonel, çünkü `config/settings.py`'de direkt ekledik.

---

## ⚠️ Production İçin Düzeltilmesi Gerekenler

### 1. DEBUG Modu

**Şu anki:**
```
DEBUG=True
```

**Production için:**
```
DEBUG=False
```

**Neden:** Production'da `DEBUG=True` güvenlik riski oluşturur.

### 2. SECRET_KEY

**Şu anki:**
```
SECRET_KEY=django-insecure-change-this-in-production-xyz123
```

**Production için:** Güçlü bir secret key oluşturun:
```bash
python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
```

### 3. ALLOWED_HOSTS

**Şu anki:**
```
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0
```

**Production için ekleyin:**
```
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,72.62.35.155,88.255.216.16,srv1132080.hstgr.cloud,bulutacente.com.tr,www.bulutacente.com.tr
```

**Veya:** `VPS_IP` ekleyip `config/settings.py` otomatik eklesin.

### 4. SITE_URL

**Şu anki:**
```
SITE_URL=http://localhost:8000
```

**Production için:**
```
SITE_URL=http://bulutacente.com.tr
```

**Veya HTTPS için:**
```
SITE_URL=https://bulutacente.com.tr
```

---

## ✅ Doğru Olanlar

- ✅ Database ayarları doğru
- ✅ Redis ayarları doğru
- ✅ Celery ayarları doğru
- ✅ Tenant ayarları doğru
- ✅ Subscription ayarları doğru
- ✅ Limit ayarları doğru

---

## 📋 Düzeltilmiş .env Dosyası

```env
# Django Settings
DEBUG=False
SECRET_KEY=<GÜÇLÜ_SECRET_KEY_BURAYA>
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,72.62.35.155,88.255.216.16,srv1132080.hstgr.cloud,bulutacente.com.tr,www.bulutacente.com.tr
VPS_IP=72.62.35.155

# Database (PostgreSQL)
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

# Redis
REDIS_URL=redis://redis:6379/0

# Celery
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Email (SES or SMTP)
EMAIL_BACKEND=django.core.mail.backends.console.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-password
DEFAULT_FROM_EMAIL=noreply@saas2026.com

# Payment (Stripe)
STRIPE_PUBLIC_KEY=pk_test_xxxxx
#STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# Sentry (Monitoring)
SENTRY_DSN=

# AWS S3 (Optional - Media files)
USE_S3=False
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_STORAGE_BUCKET_NAME=
AWS_S3_REGION_NAME=eu-west-1

# Application
SITE_NAME=SaaS 2026
SITE_URL=https://bulutacente.com.tr
ADMIN_URL=admin/

# Tenant Settings
TENANT_MODEL=tenants.Tenant
TENANT_DOMAIN_MODEL=tenants.Domain
PUBLIC_SCHEMA_NAME=public
PUBLIC_SCHEMA_URLCONF=config.urls_public

# Subscription Settings
TRIAL_PERIOD_DAYS=14
SUBSCRIPTION_GRACE_PERIOD_DAYS=3

# Limits (Default değerler)
DEFAULT_MAX_HOTELS=1
DEFAULT_MAX_ROOMS=10
DEFAULT_MAX_USERS=3
DEFAULT_MAX_RESERVATIONS_PER_MONTH=50
```

---

## 🔧 Adım Adım Düzeltme

### Adım 1: VPS IP Ekle

`.env` dosyasına şu satırı ekleyin:
```
VPS_IP=72.62.35.155
```

### Adım 2: DEBUG Modunu Kapat (Production için)

```
DEBUG=False
```

### Adım 3: SECRET_KEY Oluştur

VPS'te şu komutu çalıştırın:
```bash
docker exec saas2026_web python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
```

Çıktıyı `.env` dosyasındaki `SECRET_KEY` değerine yapıştırın.

### Adım 4: ALLOWED_HOSTS Güncelle

```
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,72.62.35.155,88.255.216.16,srv1132080.hstgr.cloud,bulutacente.com.tr,www.bulutacente.com.tr
```

**Veya:** Sadece `VPS_IP=72.62.35.155` ekleyin, `config/settings.py` otomatik ekler.

### Adım 5: SITE_URL Güncelle

```
SITE_URL=https://bulutacente.com.tr
```

### Adım 6: Container'ı Yeniden Başlat

```bash
docker compose restart web
```

---

## ⚠️ Önemli Notlar

1. **DEBUG=False**: Production'da mutlaka `False` olmalı
2. **SECRET_KEY**: Güçlü bir key oluşturun, asla paylaşmayın
3. **ALLOWED_HOSTS**: Tüm domain ve IP'leri ekleyin
4. **SITE_URL**: Domain'inizi kullanın (HTTPS için `https://`)

---

## ✅ Özet

**Eksikler:**
- ❌ `VPS_IP=72.62.35.155`
- ❌ `DEBUG=False` (production için)
- ❌ Güçlü `SECRET_KEY`
- ❌ `ALLOWED_HOSTS` güncellemesi
- ❌ `SITE_URL` güncellemesi

**Doğru Olanlar:**
- ✅ Database ayarları
- ✅ Redis ayarları
- ✅ Celery ayarları
- ✅ Tenant ayarları

**Sonuç**: Yukarıdaki düzeltmeleri yapın!

