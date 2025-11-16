# CloudPanel Docker Compose Site Oluşturma Rehberi

## ✅ Docker Kurulumu Tamamlandı!

Docker başarıyla kuruldu ve test edildi:
- ✅ Docker daemon çalışıyor
- ✅ Docker Compose v2.40.3 kurulu
- ✅ Root kullanıcısı docker grubunda

---

## 🚀 CloudPanel'de Docker Compose Site Oluşturma

### Adım 1: CloudPanel'e Giriş

1. Tarayıcınızda şu adrese gidin:
   ```
   https://88.255.216.16:8443
   ```
   veya
   ```
   https://srv1132080.hstgr.cloud:8443
   ```

2. CloudPanel'e giriş yapın (ilk kurulumda admin şifresi oluşturun)

---

### Adım 2: Docker Compose Site Oluşturma

1. **CloudPanel → Sites → Create Site**

2. **Site Type** seçin:
   - ✅ **Docker Compose** seçin (önerilen)
   - ❌ Python Site (Docker Compose daha iyi)

3. **Docker Compose Site Formu:**

   **Domain Name:**
   ```
   bulutacente.com.tr
   ```
   (www olmadan, CloudPanel otomatik ekler)

   **Docker Compose File:**
   - `docker-compose.yml` dosyanızı yükleyin
   - Veya GitHub repository URL'i ekleyin

   **Environment File (.env):**
   - `.env` dosyanızı yükleyin
   - Veya CloudPanel'de environment variables ekleyin

4. **Create** butonuna tıklayın

---

### Adım 3: Docker Compose Dosyasını Hazırlama

#### docker-compose.yml Dosyası:

**Not:** Projenizde `docker-compose.cloudpanel.yml` dosyası hazırlandı. CloudPanel'de bu dosyayı kullanın.

**Alternatif:** Mevcut `docker-compose.yml` dosyanızı kullanabilirsiniz, ancak Nginx servisini kaldırmanız gerekebilir (CloudPanel kendi reverse proxy'sini kullanır).

**Önemli Değişiklikler:**
- ✅ Nginx servisi kaldırıldı (CloudPanel kendi reverse proxy'sini kullanır)
- ✅ Port yapılandırması CloudPanel için uyarlandı
- ✅ Container isimleri kaldırıldı (CloudPanel otomatik yönetir)
- ✅ Environment variables `.env` dosyasından okunur

---

### Adım 4: Environment Variables (.env) Hazırlama

#### .env Dosyası Örneği:

```env
# Django Settings
DEBUG=False
SECRET_KEY=<GÜÇLÜ_SECRET_KEY_BURAYA>
ALLOWED_HOSTS=bulutacente.com.tr,www.bulutacente.com.tr,88.255.216.16,srv1132080.hstgr.cloud

# Database
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

# Redis
REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Site URL
SITE_URL=https://bulutacente.com.tr

# Email (Opsiyonel)
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-password
DEFAULT_FROM_EMAIL=noreply@bulutacente.com.tr

# Digital Ocean DNS (Opsiyonel)
DO_API_TOKEN=your_digital_ocean_api_token
DO_DOMAIN=bulutacente.com.tr
DO_DROPLET_IP=88.255.216.16
```

---

### Adım 5: CloudPanel'de Environment Variables Ekleme

**Alternatif:** CloudPanel'de environment variables ekleyebilirsiniz:

1. **Sites → [Site Adı] → Environment Variables**
2. Her bir değişkeni ekleyin:
   - `DEBUG=False`
   - `SECRET_KEY=...`
   - `ALLOWED_HOSTS=...`
   - vb.

---

### Adım 6: Site Oluşturma Sonrası

#### 1. SSL Sertifikası Ekleme

1. **Sites → [Site Adı] → SSL**
2. **Let's Encrypt** seçin
3. **Domain**: `bulutacente.com.tr`
4. **Email**: SSL sertifikası için email
5. **Create**

#### 2. Git Repository Bağlama (Opsiyonel)

1. **Sites → [Site Adı] → Git**
2. **Repository URL**: `https://github.com/Kohopolo/bulutsaas.git`
3. **Branch**: `main`
4. **Auto Deploy**: Aktif edin
5. **Save**

#### 3. Database Migration

1. **Sites → [Site Adı] → Terminal** veya **SSH**
2. Şu komutları çalıştırın:

```bash
# Container'a bağlan
docker compose exec web bash

# Shared schema migration
python manage.py migrate_schemas --shared

# Tenant schema migration (varsa)
python manage.py migrate_schemas

# Static files topla
python manage.py collectstatic --noinput

# Superuser oluştur
python manage.py createsuperuser
```

#### 4. Servisleri Başlatma

CloudPanel otomatik olarak `docker compose up -d` komutunu çalıştırır.

**Manuel kontrol:**

```bash
# Container durumunu kontrol et
docker compose ps

# Logları görüntüle
docker compose logs -f

# Web servisi kontrolü
curl http://localhost:8000/health/
```

---

## 🔧 CloudPanel'de Docker Compose Yönetimi

### Container Yönetimi:

1. **Sites → [Site Adı] → Containers**
   - Container'ları görüntüle
   - Container'ları başlat/durdur/restart
   - Container loglarını görüntüle

### Log Görüntüleme:

1. **Sites → [Site Adı] → Logs**
   - Web logları
   - Database logları
   - Celery logları

### Backup:

1. **Sites → [Site Adı] → Backup**
   - Otomatik backup ayarları
   - Manuel backup oluşturma
   - Backup geri yükleme

---

## 📋 Kontrol Listesi

### ✅ Docker Kurulumu:
- [x] Docker kurulu ve çalışıyor
- [x] Docker Compose v2.40.3 kurulu
- [x] Root kullanıcısı docker grubunda

### ✅ CloudPanel Hazırlığı:
- [ ] CloudPanel'e giriş yapıldı
- [ ] Domain hazır (bulutacente.com.tr)
- [ ] DNS kayıtları yapıldı

### ✅ Dosya Hazırlığı:
- [ ] `docker-compose.yml` hazır
- [ ] `.env` dosyası hazır
- [ ] `Dockerfile` hazır (eğer build gerekiyorsa)

### ✅ Site Oluşturma:
- [ ] Docker Compose site oluşturuldu
- [ ] SSL sertifikası eklendi
- [ ] Git repository bağlandı (opsiyonel)

### ✅ Deployment:
- [ ] Database migration yapıldı
- [ ] Static files toplandı
- [ ] Superuser oluşturuldu
- [ ] Servisler çalışıyor

---

## 🐛 Sorun Giderme

### Container'lar Başlamıyor:

```bash
# Logları kontrol et
docker compose logs

# Container durumunu kontrol et
docker compose ps

# Yeniden başlat
docker compose restart
```

### Database Bağlantı Hatası:

```bash
# Database container'ını kontrol et
docker compose ps db

# Database loglarını görüntüle
docker compose logs db

# Database'e bağlan
docker compose exec db psql -U saas_user -d saas_db
```

### Port Çakışması:

```bash
# Port kullanımını kontrol et
netstat -tulpn | grep 8000

# docker-compose.yml'de port değiştir
ports:
  - "8001:8000"  # 8000 yerine 8001 kullan
```

---

## ✅ Sonuç

Docker kurulumu tamamlandı! Şimdi CloudPanel'de Docker Compose site oluşturabilirsiniz.

**Sonraki Adımlar:**
1. CloudPanel'e giriş yapın
2. Docker Compose site oluşturun
3. SSL sertifikası ekleyin
4. Database migration yapın
5. Siteyi test edin

**Başarılar! 🚀**

