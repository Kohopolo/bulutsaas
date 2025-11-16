# VPS GitHub Kurulum Rehberi
## Docker Compose - GitHub ile Otomatik Kurulum

Bu rehber, VPS'te GitHub'dan projeyi çekip Docker Compose ile kurulum yapmanızı sağlar.

---

## 🚀 Hızlı Kurulum (Script ile)

### 1. Script'i İndir ve Çalıştır

```bash
# Script'i indir
cd /docker/bulutsaas
wget https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/VPS_GITHUB_KURULUM.sh

# Çalıştırılabilir yap
chmod +x VPS_GITHUB_KURULUM.sh

# Çalıştır
./VPS_GITHUB_KURULUM.sh
```

---

## 📝 Manuel Kurulum (Adım Adım)

### 1. Proje Klasörüne Git

```bash
cd /docker/bulutsaas
```

### 2. Git Repository Kontrolü

**İlk kurulum ise:**
```bash
# Git repository yoksa
git init
git remote add origin https://github.com/Kohopolo/bulutsaas.git
git fetch origin
git checkout -b main origin/main
```

**Mevcut repository varsa:**
```bash
# Remote URL'i kontrol et
git remote -v

# Güncelle
git remote set-url origin https://github.com/Kohopolo/bulutsaas.git

# GitHub'dan çek
git fetch origin
git pull origin main
```

### 3. .env Dosyasını Kontrol Et

```bash
# .env dosyası var mı?
ls -la .env

# Yoksa oluştur
if [ ! -f ".env" ]; then
    cp env.example .env
    echo "⚠️  .env dosyası oluşturuldu, düzenleyin: nano .env"
fi
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

### 4. Gerekli Klasörleri Oluştur

```bash
mkdir -p logs
mkdir -p certbot/www
mkdir -p nginx/conf.d
```

### 5. Eski Container'ları Durdur

```bash
docker compose down
# veya
docker-compose down
```

### 6. Container'ları Oluştur ve Başlat

```bash
docker compose up -d --build
# veya
docker-compose up -d --build
```

### 7. Container Durumunu Kontrol Et

```bash
# Durum kontrolü
docker compose ps

# Logları izle
docker compose logs -f

# Belirli bir container'ın logları
docker compose logs -f web
```

### 8. Middleware Dosyalarını Kontrol Et

```bash
# Container içinde middleware dosyalarının varlığını kontrol et
docker exec saas2026_web ls -la /app/apps/tenants/middleware/

# Beklenen çıktı:
# __init__.py
# tenant_middleware.py
```

### 9. Middleware Import Testi

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

### 10. Health Check

```bash
# Health check endpoint'i test et
curl http://localhost/health/
# veya
curl http://72.62.35.155/health/

# Beklenen çıktı: OK
```

---

## 🔄 Güncelleme (GitHub'dan)

Gelecekte güncellemeleri almak için:

```bash
cd /docker/bulutsaas

# GitHub'dan çek
git pull origin main

# Container'ları yeniden oluştur
docker compose down
docker compose up -d --build

# Logları kontrol et
docker compose logs -f
```

---

## 🔧 Sorun Giderme

### Sorun 1: Git Pull Başarısız

**Hata:**
```
error: Your local changes to 'docker-compose.yml' would be overwritten by merge
```

**Çözüm:**
```bash
# Değişiklikleri sakla
git stash

# Pull yap
git pull origin main

# Değişiklikleri geri getir (gerekirse)
git stash pop
```

### Sorun 2: Container Başlamıyor

**Kontrol:**
```bash
# Logları kontrol et
docker compose logs web --tail=100

# Container durumu
docker compose ps

# Container'a bağlan
docker exec -it saas2026_web bash
```

**Çözüm:**
```bash
# Container'ları tamamen temizle
docker compose down -v

# Yeniden oluştur
docker compose up -d --build
```

### Sorun 3: Middleware Import Hatası

**Kontrol:**
```bash
# Volume mount'un çalıştığını kontrol et
docker exec saas2026_web ls -la /app/apps/tenants/

# Eğer dosyalar yoksa, container'ı yeniden oluştur
docker compose down
docker compose up -d --build
```

### Sorun 4: Port Çakışması

**Hata:**
```
Error: bind: address already in use
```

**Çözüm:**
```bash
# Port'u kullanan process'i bul
sudo lsof -i :80
sudo lsof -i :443
sudo lsof -i :8000

# Process'i durdur (gerekirse)
sudo kill -9 <PID>
```

---

## ✅ Başarılı Kurulum Kontrol Listesi

- [ ] Git repository bağlı (`git remote -v`)
- [ ] GitHub'dan pull başarılı (`git pull origin main`)
- [ ] .env dosyası mevcut ve düzenlenmiş
- [ ] Tüm container'lar çalışıyor (`docker compose ps`)
- [ ] Middleware dosyaları container'da mevcut
- [ ] Middleware import başarılı
- [ ] Health check çalışıyor (`/health/` endpoint)
- [ ] Nginx web sayfasına erişilebiliyor
- [ ] Static dosyalar yükleniyor
- [ ] Database bağlantısı çalışıyor

---

## 📋 Hızlı Komutlar

```bash
# GitHub'dan güncelle
cd /docker/bulutsaas && git pull origin main

# Container'ları yeniden başlat
docker compose restart

# Container'ları yeniden oluştur
docker compose down && docker compose up -d --build

# Logları izle
docker compose logs -f

# Container durumu
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

## 🔗 GitHub Repository

**URL:** `https://github.com/Kohopolo/bulutsaas.git`

**Branch:** `main`

---

## ✅ Tamamlandı!

Artık GitHub üzerinden otomatik kurulum yapabilirsiniz. Gelecekteki güncellemeler için sadece `git pull origin main` komutunu çalıştırmanız yeterli!

