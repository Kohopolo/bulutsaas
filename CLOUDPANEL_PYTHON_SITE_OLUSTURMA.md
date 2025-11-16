# CloudPanel Python Site Oluşturma Rehberi

## ⚠️ ÖNEMLİ: Docker Compose Kullanın!

Mevcut Docker Compose kurulumunuz var. **Python Site** yerine **Docker Compose Site** oluşturmanız önerilir.

---

## 🔄 Docker Compose Site Oluşturma (ÖNERİLEN)

### Adım 1: Docker Compose Seçeneğini Bulun

1. **CloudPanel → Sites → Create Site**
2. **Site Type** seçeneklerini kontrol edin:
   - **PHP Site** ❌
   - **Python Site** ❌
   - **Node.js Site** ❌
   - **Docker Compose** ✅ **BUNU SEÇİN!**
   - **Static Site** ❌

### Adım 2: Docker Compose Site Oluşturma

**Eğer Docker Compose seçeneği varsa:**

1. **Site Type**: Docker Compose
2. **Domain Name**: `bulutacente.com.tr` (www olmadan)
3. **Docker Compose File**: `docker-compose.yml` dosyanızı yükleyin
4. **Environment File**: `.env` dosyanızı yükleyin
5. **Create**

---

## 📋 Python Site Formu Doldurma (Alternatif)

Eğer Docker Compose seçeneği yoksa ve Python Site oluşturmanız gerekiyorsa:

### Form Alanları:

#### 1. Domain Name*
```
www.bulutacente.com.tr
```
**Düzeltme:** `www` olmadan `bulutacente.com.tr` kullanın (veya her ikisini de ekleyin)

#### 2. Python Version*
```
Python 3.12
```
**Doğru:** Python 3.12 uygun (Django için)

#### 3. App Port*
```
8090
```
**Dikkat:** Bu port CloudPanel tarafından yönetilir. Docker Compose kullanıyorsanız farklı olabilir.

#### 4. Site User*
```
bulutacente
```
**Doğru:** Bu kullanıcı adı uygun

#### 5. Site User Password*
```
a69NWUYMRVAdN54trBab
```
**Öneri:** Güçlü bir şifre oluşturun veya "Generate new password" butonuna tıklayın

---

## ⚠️ Önemli Notlar

### Python Site vs Docker Compose:

**Python Site:**
- ⚠️ Tek Python uygulaması için
- ⚠️ Docker Compose desteği yok
- ⚠️ Celery, Redis, PostgreSQL ayrı kurulum gerekir
- ⚠️ Mevcut Docker Compose kurulumunuzu kullanamazsınız

**Docker Compose Site:**
- ✅ Tüm servislerinizi içerir (Django, Celery, Redis, PostgreSQL, Nginx)
- ✅ Mevcut kurulumunuzu kullanabilirsiniz
- ✅ Environment variables yönetimi
- ✅ Otomatik SSL
- ✅ Log görüntüleme

---

## 🔧 Docker Compose Site Oluşturma (Detaylı)

### Adım 1: Docker Compose Dosyasını Hazırlayın

Mevcut `docker-compose.yml` dosyanızı kullanabilirsiniz.

### Adım 2: Environment Dosyasını Hazırlayın

`.env` dosyanızı hazırlayın (VPS_IP ekleyin):
```env
DEBUG=False
SECRET_KEY=<GÜÇLÜ_SECRET_KEY>
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0
VPS_IP=72.62.35.155

DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

SITE_URL=https://bulutacente.com.tr
```

### Adım 3: CloudPanel'de Site Oluşturma

1. **Sites → Create Site**
2. **Docker Compose** seçin
3. **Domain**: `bulutacente.com.tr`
4. **Docker Compose File**: Yükleyin
5. **Environment File**: Yükleyin
6. **Create**

---

## 📋 Python Site Formu Doldurma (Eğer Docker Compose Yoksa)

### Form Alanları:

#### 1. Domain Name*
```
bulutacente.com.tr
```
**Not:** `www` olmadan kullanın, CloudPanel otomatik `www` subdomain'i ekler

#### 2. Python Version*
```
Python 3.12
```
**Doğru:** Django için Python 3.12 uygun

#### 3. App Port*
```
8090
```
**Not:** CloudPanel bu portu yönetir, değiştirmeyin

#### 4. Site User*
```
bulutacente
```
**Doğru:** Bu kullanıcı adı uygun

#### 5. Site User Password*
```
<GÜÇLÜ_ŞİFRE>
```
**Öneri:** "Generate new password" butonuna tıklayın ve şifreyi kaydedin

---

## ✅ Önerilen Yol

### 1. Docker Compose Site Oluşturun (ÖNERİLEN)

**Avantajlar:**
- ✅ Mevcut Docker Compose kurulumunuzu kullanabilirsiniz
- ✅ Tüm servisler (Django, Celery, Redis, PostgreSQL, Nginx) birlikte çalışır
- ✅ Environment variables yönetimi
- ✅ Otomatik SSL
- ✅ Log görüntüleme

### 2. Python Site Oluşturun (Alternatif)

**Sadece eğer:**
- Docker Compose seçeneği yoksa
- Tek Python uygulaması yeterliyse
- Celery, Redis, PostgreSQL ayrı kurulum yapacaksanız

---

## 🔍 Docker Compose Seçeneğini Bulma

### CloudPanel'de Docker Compose:

1. **Sites → Create Site**
2. **Site Type** dropdown'ını açın
3. **Docker Compose** seçeneğini arayın

**Eğer görünmüyorsa:**
- CloudPanel versiyonunu kontrol edin
- Güncel versiyonda Docker Compose desteği olmalı
- Alternatif olarak Coolify kullanabilirsiniz (tam Docker Compose desteği)

---

## 📝 Form Doldurma Özeti

### Python Site Formu (Eğer Docker Compose Yoksa):

```
Domain Name: bulutacente.com.tr
Python Version: Python 3.12
App Port: 8090
Site User: bulutacente
Site User Password: [Generate new password] tıklayın
```

### Docker Compose Site (ÖNERİLEN):

```
Site Type: Docker Compose
Domain: bulutacente.com.tr
Docker Compose File: docker-compose.yml (yükleyin)
Environment File: .env (yükleyin)
```

---

## 🆘 Sorun Giderme

### Docker Compose Seçeneği Görünmüyor:

1. **CloudPanel versiyonunu kontrol edin**
2. **Güncelleme yapın** (eğer mümkünse)
3. **Alternatif:** Coolify kullanın (tam Docker Compose desteği)

### Python Site Oluşturduktan Sonra:

1. **Docker Compose kurulumunu manuel yapmanız gerekir**
2. **Celery, Redis, PostgreSQL ayrı kurulum**
3. **Nginx yapılandırması manuel**

---

## ✅ Sonuç

**ÖNERİLEN:** Docker Compose Site oluşturun

**Form Doldurma (Docker Compose):**
- Domain: `bulutacente.com.tr`
- Docker Compose File: `docker-compose.yml`
- Environment File: `.env`

**Form Doldurma (Python Site - Alternatif):**
- Domain Name: `bulutacente.com.tr`
- Python Version: `Python 3.12`
- App Port: `8090`
- Site User: `bulutacente`
- Site User Password: `[Generate new password]`

Hangi yöntemi kullanmak istiyorsunuz? Docker Compose önerilir!

