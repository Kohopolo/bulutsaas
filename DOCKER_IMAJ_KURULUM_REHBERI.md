# 🐳 Docker İmaj Otomatik Kurulum Rehberi

> **Supervisord ile tek Docker imajında tüm servisler (Web + Celery + Celery-Beat)**

**VPS IP:** 78.46.142.212 (Hetzner)

---

## 📋 Genel Bakış

Bu yöntem, tüm Django servislerini (Web, Celery, Celery-Beat) **tek bir Docker imajında** çalıştırır. **Supervisord** ile servisleri yönetir.

### ✅ Avantajlar

- ✅ **Tek Docker imajı** - Daha basit yönetim
- ✅ **Otomatik başlatma** - Tüm servisler tek container'da
- ✅ **Daha az kaynak** - 3 container yerine 1 container
- ✅ **Kolay kurulum** - Tek script ile her şey

### 📦 Yapı

```
┌─────────────────────────────────────┐
│  Docker Container (saas2026_app)   │
│  ┌───────────────────────────────┐  │
│  │  Supervisord (Process Manager)│  │
│  │  ├── Gunicorn (Web Server)   │  │
│  │  ├── Celery Worker           │  │
│  │  └── Celery Beat             │  │
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘
         │
         ├── PostgreSQL (db)
         └── Redis (redis)
```

---

## 🚀 Hızlı Kurulum (Otomatik)

### Adım 1: Script'i İndir ve Çalıştır

```bash
# VPS'e SSH ile bağlan
ssh root@78.46.142.212

# Script'i indir
wget https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştırılabilir yap
chmod +x DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştır
./DOCKER_IMAJ_OTOMATIK_KURULUM.sh
```

**Script otomatik olarak:**
1. ✅ Docker ve Docker Compose kurar
2. ✅ Projeyi GitHub'dan çeker
3. ✅ `.env` dosyasını oluşturur
4. ✅ Docker imajını build eder
5. ✅ Tüm servisleri başlatır

---

## 📝 Manuel Kurulum

### Adım 1: Docker Kurulumu

```bash
# Docker kurulumu
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
rm get-docker.sh

# Docker Compose kurulumu
apt install -y docker-compose-plugin

# Docker servisini başlat
systemctl start docker
systemctl enable docker

# Kullanıcıyı docker grubuna ekle
usermod -aG docker $USER
newgrp docker
```

### Adım 2: Proje Dizini

```bash
# Proje dizini oluştur
mkdir -p /var/www/bulutsaas
cd /var/www/bulutsaas

# Projeyi GitHub'dan çek
git clone https://github.com/Kohopolo/bulutsaas.git .

# Veya branch belirt
git clone -b main https://github.com/Kohopolo/bulutsaas.git .
```

### Adım 3: .env Dosyası

```bash
# .env dosyası oluştur
cp env.example .env

# Düzenle
nano .env
```

**Önemli ayarlar:**
```env
DEBUG=False
SECRET_KEY=django-insecure-change-this-in-production-xyz123
ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,78.46.142.212
VPS_IP=78.46.142.212
SITE_URL=http://78.46.142.212

# Database (docker-compose.simple.yml ile uyumlu)
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db

# Redis
REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0
```

### Adım 4: Docker İmajını Build Et

```bash
# Docker imajını build et (Supervisord ile)
docker compose -f docker-compose.simple.yml build

# Veya cache olmadan
docker compose -f docker-compose.simple.yml build --no-cache
```

### Adım 5: Servisleri Başlat

```bash
# Tüm servisleri başlat
docker compose -f docker-compose.simple.yml up -d

# Durumu kontrol et
docker compose -f docker-compose.simple.yml ps

# Logları izle
docker compose -f docker-compose.simple.yml logs -f
```

---

## 🔧 Servis Yönetimi

### Servisleri Başlat/Durdur

```bash
# Başlat
docker compose -f docker-compose.simple.yml up -d

# Durdur
docker compose -f docker-compose.simple.yml down

# Yeniden başlat
docker compose -f docker-compose.simple.yml restart

# Sadece app container'ını yeniden başlat
docker compose -f docker-compose.simple.yml restart app
```

### Logları İzle

```bash
# Tüm loglar
docker compose -f docker-compose.simple.yml logs -f

# Sadece app logları
docker compose -f docker-compose.simple.yml logs -f app

# Son 100 satır
docker compose -f docker-compose.simple.yml logs --tail=100
```

### Container İçine Gir

```bash
# App container'ına gir
docker exec -it saas2026_app sh

# Django komutlarını çalıştır
docker exec -it saas2026_app python manage.py migrate_schemas
docker exec -it saas2026_app python manage.py createsuperuser
```

### Supervisord Kontrolü

```bash
# Container içinde supervisord durumunu kontrol et
docker exec -it saas2026_app supervisorctl status

# Servisleri yeniden başlat
docker exec -it saas2026_app supervisorctl restart all

# Sadece gunicorn'u yeniden başlat
docker exec -it saas2026_app supervisorctl restart gunicorn
```

---

## 🌐 Nginx Yapılandırması

Nginx'i host üzerinde kurun ve şu yapılandırmayı kullanın:

```nginx
server {
    listen 80;
    server_name 78.46.142.212 _;

    client_max_body_size 100M;

    # Static dosyalar
    location /static/ {
        alias /var/www/bulutsaas/staticfiles/;
    }

    # Media dosyalar
    location /media/ {
        alias /var/www/bulutsaas/media/;
    }

    # Django uygulaması
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 300s;
        proxy_send_timeout 300s;
        proxy_read_timeout 300s;
    }
}
```

**Nginx kurulumu:**
```bash
apt install -y nginx
nano /etc/nginx/sites-available/bulutsaas
# Yukarıdaki yapılandırmayı ekle
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

---

## 🔍 Sorun Giderme

### Container Başlamıyor

```bash
# Logları kontrol et
docker compose -f docker-compose.simple.yml logs app

# Container durumunu kontrol et
docker compose -f docker-compose.simple.yml ps

# Health check'i kontrol et
docker inspect saas2026_app | grep -A 10 Health
```

### Database Bağlantı Hatası

```bash
# Database container'ını kontrol et
docker compose -f docker-compose.simple.yml ps db

# Database loglarını kontrol et
docker compose -f docker-compose.simple.yml logs db

# .env dosyasındaki DATABASE_URL'i kontrol et
cat .env | grep DATABASE_URL
```

### Supervisord Servisleri Çalışmıyor

```bash
# Container içine gir
docker exec -it saas2026_app sh

# Supervisord durumunu kontrol et
supervisorctl status

# Logları kontrol et
cat /app/logs/gunicorn.err.log
cat /app/logs/celery.err.log
cat /app/logs/celery-beat.err.log
```

### İmajı Yeniden Build Et

```bash
# Eski container'ları durdur
docker compose -f docker-compose.simple.yml down

# İmajı yeniden build et
docker compose -f docker-compose.simple.yml build --no-cache

# Yeniden başlat
docker compose -f docker-compose.simple.yml up -d
```

---

## 📊 Performans İzleme

### Container Kaynak Kullanımı

```bash
# Container kaynak kullanımı
docker stats saas2026_app

# Disk kullanımı
docker system df
```

### Supervisord Process'leri

```bash
# Container içinde process'leri görüntüle
docker exec -it saas2026_app ps aux

# Supervisord status
docker exec -it saas2026_app supervisorctl status
```

---

## 🔄 Güncelleme

### Projeyi Güncelle

```bash
cd /var/www/bulutsaas

# Git pull
git pull

# İmajı yeniden build et
docker compose -f docker-compose.simple.yml build

# Container'ları yeniden başlat
docker compose -f docker-compose.simple.yml up -d
```

---

## 📝 Dosya Yapısı

```
bulutsaas/
├── docker-compose.simple.yml    # Basitleştirilmiş compose (tek app imajı)
├── Dockerfile.supervisord       # Supervisord ile Dockerfile
├── supervisord.conf             # Supervisord yapılandırması
├── DOCKER_IMAJ_OTOMATIK_KURULUM.sh  # Otomatik kurulum script'i
└── .env                         # Environment değişkenleri
```

---

## ✅ Kontrol Listesi

- [ ] Docker ve Docker Compose kurulu
- [ ] Proje GitHub'dan çekildi
- [ ] `.env` dosyası oluşturuldu ve düzenlendi
- [ ] Docker imajı build edildi
- [ ] Servisler başlatıldı (`docker compose -f docker-compose.simple.yml ps`)
- [ ] Nginx yapılandırıldı
- [ ] Site erişilebilir (`http://78.46.142.212`)

---

## 🎯 Sonuç

Artık tüm servisler **tek bir Docker imajında** çalışıyor! Supervisord ile web, celery ve celery-beat otomatik olarak yönetiliyor.

**Avantajlar:**
- ✅ Daha basit yönetim
- ✅ Daha az kaynak kullanımı
- ✅ Otomatik başlatma
- ✅ Kolay güncelleme

**Sorularınız için:** GitHub Issues veya dokümantasyonu kontrol edin.

