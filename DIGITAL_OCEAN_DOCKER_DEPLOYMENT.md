# Digital Ocean Docker Compose Deployment Rehberi
## Bulut Acente Yönetim Sistemi - Docker ile Production Kurulum

Bu rehber, Django multi-tenant uygulamanızı Docker ve Docker Compose kullanarak Digital Ocean Droplet'e nasıl deploy edeceğinizi adım adım açıklar.

---

## 📋 İçindekiler

1. [Gereksinimler](#gereksinimler)
2. [Digital Ocean Droplet Oluşturma](#digital-ocean-droplet-oluşturma)
3. [Docker ve Docker Compose Kurulumu](#docker-ve-docker-compose-kurulumu)
4. [Proje Dosyalarını Hazırlama](#proje-dosyalarını-hazırlama)
5. [Environment Variables](#environment-variables)
6. [Docker Compose Yapılandırması](#docker-compose-yapılandırması)
7. [Uygulama Deployment](#uygulama-deployment)
8. [Nginx Reverse Proxy](#nginx-reverse-proxy)
9. [SSL Sertifikası (Let's Encrypt)](#ssl-sertifikası-lets-encrypt)
10. [Domain Yapılandırması](#domain-yapılandırması)
11. [Yedekleme ve Monitoring](#yedekleme-ve-monitoring)
12. [Sorun Giderme](#sorun-giderme)

---

## 🎯 Gereksinimler

### Minimum Sistem Gereksinimleri
- **RAM**: 4GB (8GB önerilir)
- **CPU**: 2 vCPU (4 vCPU önerilir)
- **Disk**: 60GB SSD
- **İşletim Sistemi**: Ubuntu 22.04 LTS

### Gerekli Servisler
- Docker 24.0+
- Docker Compose 2.20+
- PostgreSQL 14+ (Managed Database veya Container)
- Redis 7+ (Container)
- Nginx (Host üzerinde veya Container)

---

## 🚀 Digital Ocean Droplet Oluşturma

### 1. Droplet Oluşturma

1. Digital Ocean hesabınıza giriş yapın
2. **Create** > **Droplets** seçin
3. Aşağıdaki ayarları yapın:
   - **Image**: Ubuntu 22.04 LTS
   - **Plan**: Basic Plan - 4GB RAM / 2 vCPU / 80GB SSD ($24/ay)
   - **Datacenter**: Size yakın bir lokasyon seçin
   - **Authentication**: SSH Keys ekleyin (önerilir)
   - **Hostname**: `bulut-acente-docker`
   - **Tags**: `production`, `django`, `docker`, `saas`

4. **Create Droplet** butonuna tıklayın

### 2. İlk Bağlantı

```bash
# SSH ile bağlanın
ssh root@YOUR_DROPLET_IP

# Veya SSH key kullanıyorsanız
ssh -i ~/.ssh/your_key root@YOUR_DROPLET_IP
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
# Sistem güncellemesi
apt update && apt upgrade -y

# Docker için gerekli paketler
apt install -y apt-transport-https ca-certificates curl software-properties-common

# Docker'ın resmi GPG key'ini ekle
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Docker repository ekle
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Docker'ı kur
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Docker servisini başlat
systemctl start docker
systemctl enable docker

# Docker versiyonunu kontrol et
docker --version
docker compose version
```

### 2. Docker Compose Kurulumu (Eğer plugin yoksa)

```bash
# Docker Compose plugin zaten yüklü (docker compose plugin ile)
# Eğer eski versiyon kullanıyorsanız:
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
docker-compose --version
```

---

## 📁 Proje Dosyalarını Hazırlama

### 1. Proje Klasörü Oluşturma

```bash
# Proje klasörü oluştur
mkdir -p /var/www/bulutacente
cd /var/www/bulutacente
```

### 2. Git Repository'den Clone

```bash
# Git kurulumu (eğer yoksa)
apt install -y git

# Repository'yi clone et
git clone https://github.com/yourusername/bulutacente_yedek_calisan.git .

# Veya proje dosyalarını SCP ile yükleyin
# scp -r /local/path/* root@YOUR_DROPLET_IP:/var/www/bulutacente/
```

### 3. Docker Dosyalarını Kontrol Et

Projenizde şu dosyaların olması gerekir:
- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`
- `.env.example`

---

## 🔧 Docker Compose Yapılandırması

### 1. Production Docker Compose Dosyası Oluştur

`docker-compose.prod.yml` dosyası oluştur:

```yaml
version: '3.8'

services:
  # PostgreSQL Database
  db:
    image: postgres:14-alpine
    container_name: bulutacente_db
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-saas_db}
      POSTGRES_USER: ${POSTGRES_USER:-saas_user}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./backups:/backups
    ports:
      - "127.0.0.1:5432:5432"  # Sadece localhost'tan erişilebilir
    networks:
      - bulutacente_network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-saas_user}"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Redis
  redis:
    image: redis:7-alpine
    container_name: bulutacente_redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD:-redis_password}
    volumes:
      - redis_data:/data
    ports:
      - "127.0.0.1:6379:6379"  # Sadece localhost'tan erişilebilir
    networks:
      - bulutacente_network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Django Web Application
  web:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: bulutacente_web
    restart: unless-stopped
    command: gunicorn config.wsgi:application --bind 0.0.0.0:8000 --workers 4 --threads 2 --timeout 120 --access-logfile - --error-logfile -
    volumes:
      - .:/app
      - static_volume:/app/staticfiles
      - media_volume:/app/media
    ports:
      - "127.0.0.1:8000:8000"  # Sadece localhost'tan erişilebilir (Nginx üzerinden)
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - bulutacente_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # Celery Worker
  celery_worker:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: bulutacente_celery_worker
    restart: unless-stopped
    command: celery -A config worker --loglevel=info --concurrency=4
    volumes:
      - .:/app
      - media_volume:/app/media
    env_file:
      - .env
    depends_on:
      - db
      - redis
      - web
    networks:
      - bulutacente_network

  # Celery Beat (Scheduled Tasks)
  celery_beat:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: bulutacente_celery_beat
    restart: unless-stopped
    command: celery -A config beat --loglevel=info
    volumes:
      - .:/app
    env_file:
      - .env
    depends_on:
      - db
      - redis
      - web
    networks:
      - bulutacente_network

volumes:
  postgres_data:
    driver: local
  redis_data:
    driver: local
  static_volume:
    driver: local
  media_volume:
    driver: local

networks:
  bulutacente_network:
    driver: bridge
```

### 2. Dockerfile Güncelleme

`Dockerfile` dosyanızın şu şekilde olduğundan emin olun:

```dockerfile
FROM python:3.11-slim

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV DEBIAN_FRONTEND=noninteractive

# Set work directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    postgresql-client \
    gcc \
    python3-dev \
    libpq-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

# Copy project
COPY . .

# Collect static files (will be run during deployment)
# RUN python manage.py collectstatic --noinput

# Expose port
EXPOSE 8000

# Default command (will be overridden in docker-compose)
CMD ["gunicorn", "config.wsgi:application", "--bind", "0.0.0.0:8000"]
```

### 3. .dockerignore Dosyası

`.dockerignore` dosyası oluştur:

```
*.pyc
__pycache__/
*.py[cod]
*$py.class
*.so
.Python
env/
venv/
ENV/
.venv
*.log
.git/
.gitignore
.env
.env.local
.idea/
.vscode/
*.swp
*.swo
*~
.DS_Store
media/
staticfiles/
db.sqlite3
*.md
```

---

## 🔐 Environment Variables

### 1. .env Dosyası Oluştur

```bash
cd /var/www/bulutacente
cp .env.example .env
nano .env
```

### 2. .env İçeriği

```env
# Django Settings
DEBUG=False
SECRET_KEY=your-super-secret-key-change-this-in-production
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,*.yourdomain.com

# Database
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=your-strong-database-password
POSTGRES_HOST=db
POSTGRES_PORT=5432

# Redis
REDIS_URL=redis://:redis_password@redis:6379/0
REDIS_PASSWORD=redis_password

# Celery
CELERY_BROKER_URL=redis://:redis_password@redis:6379/0
CELERY_RESULT_BACKEND=redis://:redis_password@redis:6379/0

# Email (SMTP)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com

# Digital Ocean DNS (Opsiyonel)
DO_API_TOKEN=your_digital_ocean_api_token
DO_DOMAIN=yourdomain.com
DO_DROPLET_IP=YOUR_DROPLET_IP

# Media & Static
MEDIA_ROOT=/app/media
STATIC_ROOT=/app/staticfiles
```

---

## 🚀 Uygulama Deployment

### 1. Docker Image'ları Build Et

```bash
cd /var/www/bulutacente

# Production compose dosyası ile build
docker compose -f docker-compose.prod.yml build
```

### 2. Database Migration (Django-Tenants için)

```bash
# Shared schema migration (public schema)
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas --shared

# Tenant schema'ları için migration (varsa)
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas

# Static files topla
docker compose -f docker-compose.prod.yml run --rm web python manage.py collectstatic --noinput
```

### 3. Superuser Oluştur

```bash
docker compose -f docker-compose.prod.yml run --rm web python manage.py createsuperuser
```

### 4. Servisleri Başlat

```bash
# Tüm servisleri başlat
docker compose -f docker-compose.prod.yml up -d

# Logları kontrol et
docker compose -f docker-compose.prod.yml logs -f

# Servis durumunu kontrol et
docker compose -f docker-compose.prod.yml ps
```

### 5. Health Check

```bash
# Web servisi health check
curl http://localhost:8000/health/

# Database bağlantısı kontrolü
docker compose -f docker-compose.prod.yml exec web python manage.py dbshell
```

---

## 🌐 Nginx Reverse Proxy

### 1. Nginx Kurulumu (Host Üzerinde)

```bash
# Nginx kurulumu
apt install -y nginx

# Nginx config dosyası oluştur
nano /etc/nginx/sites-available/bulutacente
```

### 2. Nginx Configuration

```nginx
# Upstream Docker containers
upstream bulutacente_app {
    server 127.0.0.1:8000;
    keepalive 64;
}

# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com *.yourdomain.com;
    
    # Let's Encrypt için
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# HTTPS Server
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com *.yourdomain.com;
    
    # SSL Sertifikaları (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL Yapılandırması
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Client Max Body Size
    client_max_body_size 100M;
    
    # Static Files (Docker volume'dan)
    location /static/ {
        alias /var/www/bulutacente/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Media Files (Docker volume'dan)
    location /media/ {
        alias /var/www/bulutacente/media/;
        expires 7d;
        add_header Cache-Control "public";
    }
    
    # Gunicorn Proxy
    location / {
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Host $http_host;
        proxy_redirect off;
        proxy_buffering off;
        proxy_pass http://bulutacente_app;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
    
    # Health Check
    location /health/ {
        proxy_pass http://bulutacente_app;
        access_log off;
    }
}
```

### 3. Nginx Site Aktifleştirme

```bash
# Site'ı aktifleştir
ln -s /etc/nginx/sites-available/bulutacente /etc/nginx/sites-enabled/

# Default site'ı kaldır
rm /etc/nginx/sites-enabled/default

# Nginx config test
nginx -t

# Nginx'i yeniden başlat
systemctl restart nginx
systemctl enable nginx
```

---

## 🔒 SSL Sertifikası (Let's Encrypt)

### 1. Certbot Kurulumu

```bash
# Certbot kurulumu
apt install -y certbot python3-certbot-nginx

# Wildcard SSL sertifikası al
certbot certonly --manual --preferred-challenges dns \
    -d yourdomain.com \
    -d *.yourdomain.com \
    --email your-email@example.com \
    --agree-tos \
    --manual-public-ip-logging-ok

# DNS TXT kaydını Digital Ocean DNS'e ekleyin
# Sonra Enter'a basın
```

### 2. SSL Otomatik Yenileme

```bash
# Certbot timer kontrolü
systemctl status certbot.timer

# Test renewal
certbot renew --dry-run
```

### 3. Nginx SSL Config Güncelle

SSL sertifikası aldıktan sonra Nginx config'i güncelleyin (yukarıdaki config'de SSL satırları var).

---

## 🌍 Domain Yapılandırması

### 1. Digital Ocean DNS Ayarları

1. Digital Ocean > **Networking** > **Domains**
2. Domain'inizi ekleyin veya seçin
3. Aşağıdaki kayıtları ekleyin:

```
A Record:
Name: @
Value: YOUR_DROPLET_IP
TTL: 300

A Record:
Name: www
Value: YOUR_DROPLET_IP
TTL: 300

A Record (Wildcard):
Name: *
Value: YOUR_DROPLET_IP
TTL: 300
```

### 2. Django ALLOWED_HOSTS

`.env` dosyasında `ALLOWED_HOSTS` değişkenini güncelleyin:

```env
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,*.yourdomain.com
```

---

## 💾 Yedekleme ve Monitoring

### 1. Database Yedekleme Script

`backup.sh` dosyası oluştur:

```bash
#!/bin/bash
# Database backup script

BACKUP_DIR="/var/www/bulutacente/backups"
DATE=$(date +%Y%m%d_%H%M%S)
FILENAME="backup_${DATE}.sql"

# Backup oluştur
docker compose -f docker-compose.prod.yml exec -T db pg_dump -U saas_user saas_db > "${BACKUP_DIR}/${FILENAME}"

# Eski backup'ları sil (30 günden eski)
find ${BACKUP_DIR} -name "backup_*.sql" -mtime +30 -delete

echo "Backup completed: ${FILENAME}"
```

```bash
# Script'i çalıştırılabilir yap
chmod +x backup.sh

# Cron job ekle (her gün saat 02:00'de)
crontab -e
# Şu satırı ekle:
0 2 * * * /var/www/bulutacente/backup.sh >> /var/log/bulutacente_backup.log 2>&1
```

### 2. Docker Log Monitoring

```bash
# Logları görüntüle
docker compose -f docker-compose.prod.yml logs -f web
docker compose -f docker-compose.prod.yml logs -f celery_worker

# Son 100 satır log
docker compose -f docker-compose.prod.yml logs --tail=100 web
```

### 3. Container Health Monitoring

```bash
# Container durumlarını kontrol et
docker compose -f docker-compose.prod.yml ps

# Resource kullanımı
docker stats

# Disk kullanımı
docker system df
```

---

## 🔄 Güncelleme ve Bakım

### 1. Kod Güncelleme

```bash
cd /var/www/bulutacente

# Git pull
git pull origin main

# Docker image'ları yeniden build et
docker compose -f docker-compose.prod.yml build

# Servisleri yeniden başlat
docker compose -f docker-compose.prod.yml up -d

# Migration çalıştır (gerekirse - Django-Tenants için)
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas --shared
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas

# Static files topla (gerekirse)
docker compose -f docker-compose.prod.yml run --rm web python manage.py collectstatic --noinput
```

### 2. Servis Yönetimi

```bash
# Servisleri durdur
docker compose -f docker-compose.prod.yml down

# Servisleri başlat
docker compose -f docker-compose.prod.yml up -d

# Belirli bir servisi yeniden başlat
docker compose -f docker-compose.prod.yml restart web

# Logları temizle
docker compose -f docker-compose.prod.yml down
docker system prune -f
```

---

## 🐛 Sorun Giderme

### 1. Container Logları

```bash
# Tüm loglar
docker compose -f docker-compose.prod.yml logs

# Belirli servis logları
docker compose -f docker-compose.prod.yml logs web
docker compose -f docker-compose.prod.yml logs db

# Real-time log takibi
docker compose -f docker-compose.prod.yml logs -f web
```

### 2. Container'a Bağlanma

```bash
# Web container'a bash ile bağlan
docker compose -f docker-compose.prod.yml exec web bash

# Database shell
docker compose -f docker-compose.prod.yml exec web python manage.py dbshell

# Redis CLI
docker compose -f docker-compose.prod.yml exec redis redis-cli
```

### 3. Yaygın Sorunlar

**Database bağlantı hatası:**
```bash
# Database container durumunu kontrol et
docker compose -f docker-compose.prod.yml ps db

# Database loglarını kontrol et
docker compose -f docker-compose.prod.yml logs db

# Database'i yeniden başlat
docker compose -f docker-compose.prod.yml restart db
```

**Static files bulunamıyor:**
```bash
# Static files topla
docker compose -f docker-compose.prod.yml run --rm web python manage.py collectstatic --noinput

# Nginx static path'ini kontrol et
ls -la /var/www/bulutacente/staticfiles/
```

**Memory hatası:**
```bash
# Container resource limitlerini kontrol et
docker stats

# docker-compose.prod.yml'de memory limit ekle:
# deploy:
#   resources:
#     limits:
#       memory: 2G
```

---

## 📊 Performans Optimizasyonu

### 1. Gunicorn Worker Sayısı

`docker-compose.prod.yml` dosyasında worker sayısını ayarlayın:

```yaml
command: gunicorn config.wsgi:application --bind 0.0.0.0:8000 --workers 4 --threads 2
```

**Worker sayısı formülü:** `(2 x CPU cores) + 1`

### 2. Database Connection Pooling

`settings.py` dosyasına ekleyin:

```python
DATABASES = {
    'default': {
        # ... mevcut ayarlar
        'CONN_MAX_AGE': 600,  # Connection pooling
    }
}
```

### 3. Redis Cache Aktifleştirme

`.env` dosyasında Redis URL'i doğru olduğundan emin olun ve `settings.py`'de cache'i aktifleştirin.

---

## ✅ Deployment Checklist

- [ ] Digital Ocean Droplet oluşturuldu
- [ ] Docker ve Docker Compose kuruldu
- [ ] Proje dosyaları droplet'e yüklendi
- [ ] `.env` dosyası oluşturuldu ve yapılandırıldı
- [ ] `docker-compose.prod.yml` dosyası hazırlandı
- [ ] Docker image'ları build edildi
- [ ] Database migration çalıştırıldı
- [ ] Superuser oluşturuldu
- [ ] Static files toplandı
- [ ] Servisler başlatıldı
- [ ] Nginx yapılandırıldı
- [ ] SSL sertifikası alındı
- [ ] Domain DNS ayarları yapıldı
- [ ] Yedekleme script'i kuruldu
- [ ] Health check başarılı
- [ ] Monitoring ayarlandı

---

**Son Güncelleme:** 2025-01-XX
**Versiyon:** 1.0

