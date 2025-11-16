# Docker Compose Yeniden Kurulum Rehberi
## Temiz Kurulum - Tüm Düzeltmeler İle

Bu rehber, docker-compose.yml dosyasını tamamen silip yeniden oluşturduktan sonra yapılacak adımları içerir.

---

## ✅ Hazırlanan Dosyalar

1. ✅ `docker-compose.yml` - Tüm düzeltmeler ile
2. ✅ `nginx/conf.d/default.conf` - Static ve media dosyaları ile
3. ✅ `.env` dosyası kontrolü

---

## 🚀 VPS'te Kurulum Adımları

### 1. Proje Klasörüne Git

```bash
cd /docker/bulutsaas
```

### 2. .env Dosyasını Kontrol Et

```bash
# .env dosyası var mı kontrol et
ls -la .env

# Yoksa oluştur
cp env.example .env

# .env dosyasını düzenle (gerekirse)
nano .env
```

**Önemli .env ayarları:**
```env
DEBUG=True
SECRET_KEY=django-insecure-development-key-change-in-production
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,72.62.35.155,bulutacente.com.tr,www.bulutacente.com.tr

# Database
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

# Redis
REDIS_URL=redis://redis:6379/0

# Celery
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0
```

### 3. Gerekli Klasörleri Oluştur

```bash
# Log klasörü
mkdir -p logs

# Certbot webroot klasörü
mkdir -p certbot/www

# Nginx config klasörü kontrolü
ls -la nginx/conf.d/
```

### 4. Docker Compose ile Başlat

```bash
# Container'ları durdur (varsa)
docker compose down

# Container'ları oluştur ve başlat
docker compose up -d --build

# Logları izle
docker compose logs -f
```

### 5. Middleware Dosyalarını Kontrol Et

```bash
# Container içinde middleware dosyalarının varlığını kontrol et
docker exec saas2026_web ls -la /app/apps/tenants/middleware/

# Beklenen çıktı:
# __init__.py
# tenant_middleware.py
```

### 6. Middleware Import Testi

```bash
docker exec saas2026_web python -c "
import sys
sys.path.insert(0, '/app')
try:
    from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware
    print('✅ Middleware import başarılı!')
except Exception as e:
    print(f'❌ Import hatası: {e}')
    import traceback
    traceback.print_exc()
"
```

### 7. Health Check Testi

```bash
# Container health check durumu
docker compose ps

# HTTP health check
curl http://localhost/health/
# veya
curl http://72.62.35.155/health/

# Beklenen çıktı: OK
```

### 8. Logları Kontrol Et

```bash
# Web container logları
docker compose logs web --tail=50

# Celery logları
docker compose logs celery --tail=50

# Celery Beat logları
docker compose logs celery-beat --tail=50

# Nginx logları
docker compose logs nginx --tail=50
```

---

## 🔧 Sorun Giderme

### Sorun 1: Middleware Import Hatası

**Hata:**
```
ModuleNotFoundError: No module named 'apps.tenants.middleware'
```

**Çözüm:**
```bash
# Volume mount'un çalıştığını kontrol et
docker exec saas2026_web ls -la /app/apps/tenants/

# Eğer dosyalar yoksa, container'ı yeniden oluştur
docker compose down
docker compose up -d --build
```

### Sorun 2: Container Sürekli Restart Oluyor

**Kontrol:**
```bash
# Container durumunu kontrol et
docker compose ps

# Logları kontrol et
docker compose logs web --tail=100
```

**Olası nedenler:**
- Middleware import hatası
- Database bağlantı hatası
- Environment variable eksikliği

**Çözüm:**
```bash
# .env dosyasını kontrol et
cat .env

# Container'ı yeniden oluştur
docker compose down
docker compose up -d --build
```

### Sorun 3: Nginx 502 Bad Gateway

**Kontrol:**
```bash
# Web container çalışıyor mu?
docker compose ps web

# Web container logları
docker compose logs web --tail=50

# Health check
curl http://localhost:8000/health/
```

**Çözüm:**
```bash
# Web container'ı yeniden başlat
docker compose restart web

# Tüm container'ları yeniden oluştur
docker compose down
docker compose up -d --build
```

### Sorun 4: Static Dosyalar Yüklenmiyor

**Kontrol:**
```bash
# Static dosyalar var mı?
docker exec saas2026_web ls -la /app/staticfiles/

# Collectstatic çalıştır
docker exec saas2026_web python manage.py collectstatic --noinput
```

---

## ✅ Başarılı Kurulum Kontrol Listesi

- [ ] Tüm container'lar çalışıyor (`docker compose ps`)
- [ ] Middleware dosyaları container'da mevcut
- [ ] Middleware import başarılı
- [ ] Health check çalışıyor (`/health/` endpoint)
- [ ] Nginx web sayfasına erişilebiliyor
- [ ] Static dosyalar yükleniyor
- [ ] Database bağlantısı çalışıyor
- [ ] Redis bağlantısı çalışıyor
- [ ] Celery worker çalışıyor
- [ ] Celery beat çalışıyor

---

## 📝 Sonraki Adımlar

1. **SSL Sertifikası Kurulumu** (Opsiyonel)
   ```bash
   # Certbot ile SSL sertifikası al
   docker run -it --rm \
     -v /etc/letsencrypt:/etc/letsencrypt \
     -v ./certbot/www:/var/www/certbot \
     certbot/certbot certonly --webroot \
     -w /var/www/certbot \
     -d bulutacente.com.tr \
     -d www.bulutacente.com.tr
   ```

2. **HTTPS Yapılandırması**
   - `nginx/conf.d/default.conf` dosyasında HTTPS server bloğunu aktif et
   - HTTP'den HTTPS'e yönlendirmeyi aktif et

3. **Production Ayarları**
   - `.env` dosyasında `DEBUG=False` yap
   - `SECRET_KEY` değiştir
   - `ALLOWED_HOSTS` güncelle

---

## 🎯 Hızlı Komutlar

```bash
# Tüm container'ları durdur
docker compose down

# Tüm container'ları başlat
docker compose up -d

# Container'ları yeniden oluştur
docker compose up -d --build

# Logları izle
docker compose logs -f

# Belirli bir container'ın loglarını izle
docker compose logs -f web

# Container durumunu kontrol et
docker compose ps

# Container'a bağlan
docker exec -it saas2026_web bash

# Django shell
docker exec -it saas2026_web python manage.py shell

# Migration çalıştır
docker exec saas2026_web python manage.py migrate_schemas --shared
docker exec saas2026_web python manage.py migrate_schemas

# Collectstatic
docker exec saas2026_web python manage.py collectstatic --noinput
```

---

## ✅ Tamamlandı!

Artık Docker Compose kurulumunuz hazır. Tüm servisler çalışıyor ve middleware dosyaları doğru şekilde yükleniyor.

