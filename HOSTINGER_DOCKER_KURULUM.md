# Hostinger VPS Docker Kurulum Rehberi
## Bulut Acente Yönetim Sistemi - Docker Compose ile Kurulum

Bu rehber, Django multi-tenant uygulamanızı Hostinger VPS'e Docker Compose kullanarak nasıl kuracağınızı adım adım açıklar.

**✅ Domain:** `bulutacente.com.tr` aktif

---

## 📋 İçindekiler

1. [Gereksinimler](#gereksinimler)
2. [VPS Hazırlığı](#vps-hazırlığı)
3. [Docker ve Docker Compose Kurulumu](#docker-ve-docker-compose-kurulumu)
4. [Proje Dosyalarını Hazırlama](#proje-dosyalarını-hazırlama)
5. [Environment Variables (.env)](#environment-variables-env)
6. [Docker Compose Yapılandırması](#docker-compose-yapılandırması)
7. [Uygulama Deployment](#uygulama-deployment)
8. [Nginx Reverse Proxy (Host Üzerinde)](#nginx-reverse-proxy-host-üzerinde)
9. [SSL Sertifikası (Let's Encrypt)](#ssl-sertifikası-lets-encrypt)
10. [Domain Yapılandırması](#domain-yapılandırması)
11. [Sorun Giderme](#sorun-giderme)

---

## 🎯 Gereksinimler

### Minimum Sistem Gereksinimleri
- **RAM**: 4GB (8GB önerilir)
- **CPU**: 2 vCPU (4 vCPU önerilir)
- **Disk**: 60GB SSD
- **İşletim Sistemi**: Ubuntu 24.04 LTS

### Gerekli Servisler
- Docker 24.0+
- Docker Compose 2.20+
- Nginx (Host üzerinde - reverse proxy için)

---

## 🛠️ VPS Hazırlığı

### 1. SSH ile Bağlanma

```bash
# SSH ile bağlanın
ssh root@72.62.35.155

# Veya domain ile
ssh root@bulutacente.com.tr
```

### 2. Sistem Güncellemesi

```bash
# Sistem güncellemesi
apt update && apt upgrade -y

# Temel araçlar
apt install -y curl wget git build-essential software-properties-common
```

### 3. Güvenlik Duvarı Yapılandırması

```bash
# UFW firewall kurulumu
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
ufw status
```

---

## 🐳 Docker ve Docker Compose Kurulumu

### 1. Docker Kurulumu

```bash
# Docker kurulum scriptini çalıştır
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Docker Compose plugin'ini kur
apt install -y docker-compose-plugin

# Docker servisini başlat
systemctl start docker
systemctl enable docker

# Root kullanıcısını docker grubuna ekle
usermod -aG docker root
newgrp docker
```

### 2. Docker Kurulumunu Kontrol Etme

```bash
# Docker versiyonunu kontrol et
docker --version

# Docker Compose versiyonunu kontrol et
docker compose version

# Docker servisini kontrol et
systemctl status docker

# Docker test
docker ps
docker run hello-world
```

**Beklenen Çıktı:**
```
Docker version 24.x.x
Docker Compose version v2.x.x
● docker.service - Docker Application Container Engine
     Active: active (running)
Hello from Docker!
```

---

## 📁 Proje Dosyalarını Hazırlama

### 1. Proje Klasörü Oluşturma

```bash
# Proje klasörü oluştur
mkdir -p /var/www/bulutsaas
cd /var/www/bulutsaas
```

### 2. Projeyi Klonlama

```bash
# Git'ten projeyi klonla
git clone https://github.com/Kohopolo/bulutsaas.git .

# Veya dosyaları SCP ile kopyala
# scp -r /local/path/to/project/* root@72.62.35.155:/var/www/bulutsaas/
```

### 3. Gerekli Dosyaları Kontrol Etme

```bash
# Docker dosyalarını kontrol et
ls -la Dockerfile
ls -la docker-compose.yml
ls -la requirements.txt

# Eğer yoksa, dosyaları oluşturun
```

---

## 🔐 Environment Variables (.env)

### 1. .env Dosyası Oluşturma

```bash
# .env dosyası oluştur
cd /var/www/bulutsaas
nano .env
```

### 2. .env İçeriği

```env
# Django Settings
DEBUG=False
SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA_OLUŞTURUN
ALLOWED_HOSTS=bulutacente.com.tr,www.bulutacente.com.tr,72.62.35.155,localhost,127.0.0.1

# Database (Docker container içindeki PostgreSQL)
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

# Redis (Docker container içindeki Redis)
REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Site URL
SITE_URL=https://bulutacente.com.tr

# Static ve Media
STATIC_ROOT=/app/staticfiles
MEDIA_ROOT=/app/media

# Email (Opsiyonel)
EMAIL_HOST=smtp.hostinger.com
EMAIL_PORT=465
EMAIL_USE_SSL=True
EMAIL_HOST_USER=noreply@bulutacente.com.tr
EMAIL_HOST_PASSWORD=EMAIL_ŞİFRE_BURAYA
DEFAULT_FROM_EMAIL=noreply@bulutacente.com.tr

# Digital Ocean DNS (Opsiyonel)
DO_API_TOKEN=your_digital_ocean_api_token
DO_DOMAIN=bulutacente.com.tr
DO_DROPLET_IP=72.62.35.155
```

### 3. Secret Key Oluşturma

```bash
# Secret key oluştur
python3 -c "import secrets; print(secrets.token_urlsafe(50))"

# Çıktıyı .env dosyasındaki SECRET_KEY'e kopyalayın
```

---

## 🐳 Docker Compose Yapılandırması

### 1. docker-compose.yml Dosyasını Kontrol Etme

Mevcut `docker-compose.yml` dosyası kullanılabilir. Nginx servisini kaldırmak gerekebilir çünkü host üzerinde Nginx kullanacağız.

### 2. docker-compose.yml (Nginx Olmadan)

```bash
# docker-compose.yml dosyasını düzenle
nano docker-compose.yml
```

**Önemli:** Nginx servisini kaldırın veya yorum satırı yapın. Host üzerinde Nginx kullanacağız.

---

## 🚀 Uygulama Deployment

### 1. Docker Image'ları Build Etme

```bash
# Proje dizinine git
cd /var/www/bulutsaas

# Docker image'ları build et
docker compose build

# Build durumunu kontrol et
docker images
```

### 2. Container'ları Başlatma

```bash
# Container'ları başlat (detached mode)
docker compose up -d

# Container durumunu kontrol et
docker compose ps

# Logları kontrol et
docker compose logs -f
```

**Beklenen Çıktı:**
```
NAME                IMAGE               STATUS
bulutsaas_db        postgres:15-alpine  Up
bulutsaas_redis     redis:7-alpine      Up
bulutsaas_web       bulutsaas_web       Up
bulutsaas_celery    bulutsaas_web       Up
bulutsaas_celery_beat bulutsaas_web    Up
```

### 3. Database Migration

```bash
# Web container'ına bağlan
docker compose exec web bash

# Shared schema migration
python manage.py migrate_schemas --shared

# Tenant schema migration
python manage.py migrate_schemas

# Static files topla
python manage.py collectstatic --noinput

# Superuser oluştur
python manage.py createsuperuser

# Container'dan çık
exit
```

### 4. Health Check

```bash
# Web servisi health check
curl http://localhost:8000/health/

# Beklenen: OK

# Container loglarını kontrol et
docker compose logs web
docker compose logs celery
docker compose logs celery-beat
```

---

## 🌐 Nginx Reverse Proxy (Host Üzerinde)

### 1. Nginx Kurulumu

```bash
# Nginx kurulumu
apt install -y nginx

# Nginx servisini başlat
systemctl start nginx
systemctl enable nginx
```

### 2. Nginx Site Konfigürasyonu

```bash
# Site konfigürasyonu oluştur
nano /etc/nginx/sites-available/bulutsaas
```

İçerik:

```nginx
upstream django {
    server 127.0.0.1:8000;
    keepalive 64;
}

server {
    listen 80;
    server_name bulutacente.com.tr www.bulutacente.com.tr 72.62.35.155;
    client_max_body_size 50M;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Static files (Docker volume'dan)
    location /static/ {
        alias /var/www/bulutsaas/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files (Docker volume'dan)
    location /media/ {
        alias /var/www/bulutsaas/media/;
        expires 7d;
    }

    # Django application
    location / {
        proxy_pass http://django;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
        
        # Timeout settings
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

### 3. Site'ı Aktif Etme

```bash
# Site'ı aktif et
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/

# Varsayılan site'ı kaldır (opsiyonel)
rm /etc/nginx/sites-enabled/default

# Nginx konfigürasyonunu test et
nginx -t

# Nginx'i yeniden yükle
systemctl reload nginx
```

### 4. Static ve Media Klasörlerini Host'a Mount Etme

```bash
# Static ve media klasörlerini oluştur
mkdir -p /var/www/bulutsaas/staticfiles
mkdir -p /var/www/bulutsaas/media

# İzinleri ayarla
chmod -R 755 /var/www/bulutsaas/staticfiles
chmod -R 755 /var/www/bulutsaas/media
```

**Not:** `docker-compose.yml` dosyasında static ve media volume'ları host'a mount edilmiş olmalı.

---

## 🔒 SSL Sertifikası (Let's Encrypt)

### 1. Certbot Kurulumu

```bash
# Certbot kurulumu
apt install -y certbot python3-certbot-nginx
```

### 2. SSL Sertifikası Oluşturma

```bash
# SSL sertifikası oluştur
certbot --nginx -d bulutacente.com.tr -d www.bulutacente.com.tr

# Email adresinizi girin
# Terms of Service'i kabul edin
# Otomatik yönlendirme için 2 seçin (HTTPS'e yönlendir)
```

### 3. Otomatik Yenileme Testi

```bash
# Otomatik yenileme testi
certbot renew --dry-run
```

---

## 🌍 Domain Yapılandırması

### 1. Django'da Domain Ekleme

```bash
# Web container'ına bağlan
docker compose exec web bash

# Django shell'de domain ekle
python manage.py shell
```

Python shell'de:

```python
from apps.tenants.models import Tenant, Domain
from django_tenants.utils import get_public_schema_name
from django.db import connection

connection.set_schema_to_public()
public_tenant = Tenant.objects.get(schema_name=get_public_schema_name())

# Ana domain
domain, created = Domain.objects.get_or_create(
    domain='bulutacente.com.tr',
    tenant=public_tenant,
    defaults={'is_primary': True, 'domain_type': 'primary'}
)

# WWW subdomain
www_domain, www_created = Domain.objects.get_or_create(
    domain='www.bulutacente.com.tr',
    tenant=public_tenant,
    defaults={'is_primary': False, 'domain_type': 'subdomain'}
)

exit()
```

### 2. DNS Ayarları

Hostinger DNS yönetiminde:

```
Type: A
Name: @
Value: 72.62.35.155
TTL: 3600

Type: A
Name: www
Value: 72.62.35.155
TTL: 3600
```

---

## 🔄 Container Yönetimi

### Container'ları Başlatma/Durdurma

```bash
# Tüm container'ları başlat
docker compose up -d

# Tüm container'ları durdur
docker compose down

# Container'ları yeniden başlat
docker compose restart

# Belirli bir container'ı yeniden başlat
docker compose restart web
docker compose restart celery
docker compose restart celery-beat
```

### Logları İzleme

```bash
# Tüm logları görüntüle
docker compose logs -f

# Belirli bir container'ın loglarını görüntüle
docker compose logs -f web
docker compose logs -f celery
docker compose logs -f celery-beat
docker compose logs -f db
docker compose logs -f redis
```

### Container Durumunu Kontrol Etme

```bash
# Container durumunu kontrol et
docker compose ps

# Container kaynak kullanımını kontrol et
docker stats

# Container'a bağlan
docker compose exec web bash
docker compose exec db psql -U saas_user -d saas_db
docker compose exec redis redis-cli
```

---

## 🐛 Sorun Giderme

### Container'lar Başlamıyor

```bash
# Logları kontrol et
docker compose logs

# Container durumunu kontrol et
docker compose ps

# Container'ları yeniden build et
docker compose build --no-cache

# Container'ları yeniden başlat
docker compose down
docker compose up -d
```

### Database Bağlantı Hatası

```bash
# Database container'ını kontrol et
docker compose ps db

# Database loglarını görüntüle
docker compose logs db

# Database'e bağlan
docker compose exec db psql -U saas_user -d saas_db
```

### Nginx 502 Bad Gateway

```bash
# Web container'ını kontrol et
docker compose ps web

# Web container loglarını görüntüle
docker compose logs web

# Web container'ı yeniden başlat
docker compose restart web

# Nginx loglarını kontrol et
tail -f /var/log/nginx/error.log
```

### Static Files Görünmüyor

```bash
# Static files klasörünü kontrol et
ls -la /var/www/bulutsaas/staticfiles/

# Static files'ı yeniden topla
docker compose exec web python manage.py collectstatic --noinput

# Nginx'i yeniden yükle
systemctl reload nginx
```

---

## ✅ Kurulum Sonrası Kontroller

```bash
# Tüm container'ların durumunu kontrol et
docker compose ps

# Web sitesini test et
curl http://bulutacente.com.tr/health/
curl https://bulutacente.com.tr/health/

# Admin panelini test et
curl https://bulutacente.com.tr/admin/

# Container kaynak kullanımını kontrol et
docker stats
```

---

## 📝 Önemli Notlar

- **Port Yapılandırması:** Web container'ı `127.0.0.1:8000` üzerinde çalışır (sadece localhost'tan erişilebilir)
- **Nginx:** Host üzerinde çalışır ve Docker container'larına reverse proxy yapar
- **Static/Media Files:** Docker volume'ları host'a mount edilir
- **SSL:** Let's Encrypt ile otomatik SSL sertifikası
- **Backup:** Docker volume'ları `/var/lib/docker/volumes/` altında saklanır

---

## 🎉 Kurulum Tamamlandı!

Artık uygulamanız Docker ile çalışıyor. Herhangi bir sorun yaşarsanız logları kontrol edin.

**Web Sitesi:** https://bulutacente.com.tr
**Admin Panel:** https://bulutacente.com.tr/admin/

**Başarılar! 🚀**

