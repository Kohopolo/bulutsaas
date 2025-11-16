# Digital Ocean Droplet Deployment Rehberi
## Bulut Acente Yönetim Sistemi - Production Kurulum

Bu rehber, Django multi-tenant uygulamanızı Digital Ocean Droplet'e ve management database'e nasıl yükleyeceğinizi adım adım açıklar.

---

## 📋 İçindekiler

1. [Gereksinimler](#gereksinimler)
2. [Digital Ocean Droplet Oluşturma](#digital-ocean-droplet-oluşturma)
3. [Sunucu Hazırlığı](#sunucu-hazırlığı)
4. [PostgreSQL Database Kurulumu](#postgresql-database-kurulumu)
5. [Redis Kurulumu](#redis-kurulumu)
6. [Python ve Bağımlılıklar](#python-ve-bağımlılıklar)
7. [Uygulama Kurulumu](#uygulama-kurulumu)
8. [Nginx Yapılandırması](#nginx-yapılandırması)
9. [Gunicorn Yapılandırması](#gunicorn-yapılandırması)
10. [Celery Worker ve Beat](#celery-worker-ve-beat)
11. [SSL Sertifikası (Let's Encrypt)](#ssl-sertifikası-lets-encrypt)
12. [Domain Yapılandırması](#domain-yapılandırması)
13. [Yedekleme Stratejisi](#yedekleme-stratejisi)
14. [Monitoring ve Logging](#monitoring-ve-logging)
15. [Sorun Giderme](#sorun-giderme)

---

## 🎯 Gereksinimler

### Minimum Sistem Gereksinimleri
- **RAM**: 2GB (4GB önerilir)
- **CPU**: 2 vCPU
- **Disk**: 40GB SSD
- **İşletim Sistemi**: Ubuntu 22.04 LTS

### Gerekli Servisler
- PostgreSQL 14+ (Management Database)
- Redis 7+
- Python 3.11+
- Nginx
- Gunicorn
- Certbot (SSL için)

---

## 🚀 Digital Ocean Droplet Oluşturma

### 1. Droplet Oluşturma

1. Digital Ocean hesabınıza giriş yapın
2. **Create** > **Droplets** seçin
3. Aşağıdaki ayarları yapın:
   - **Image**: Ubuntu 22.04 LTS
   - **Plan**: Basic Plan - 4GB RAM / 2 vCPU / 80GB SSD ($24/ay)
   - **Datacenter**: Size yakın bir lokasyon seçin (Amsterdam, Frankfurt vb.)
   - **Authentication**: SSH Keys ekleyin (önerilir) veya Password
   - **Hostname**: `bulut-acente-prod`
   - **Tags**: `production`, `django`, `saas`

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

## 🛠️ Sunucu Hazırlığı

### 1. Sistem Güncellemesi

```bash
# Sistem güncellemesi
apt update && apt upgrade -y

# Temel araçlar
apt install -y curl wget git build-essential software-properties-common
```

### 2. Kullanıcı Oluşturma (Opsiyonel ama önerilir)

```bash
# Yeni kullanıcı oluştur
adduser bulutacente
usermod -aG sudo bulutacente

# SSH key kopyala (eğer kullanıyorsanız)
mkdir -p /home/bulutacente/.ssh
cp ~/.ssh/authorized_keys /home/bulutacente/.ssh/
chown -R bulutacente:bulutacente /home/bulutacente/.ssh
chmod 700 /home/bulutacente/.ssh
chmod 600 /home/bulutacente/.ssh/authorized_keys

# Kullanıcıya geç
su - bulutacente
```

---

## 🗄️ PostgreSQL Database Kurulumu

### 1. PostgreSQL Kurulumu

```bash
# PostgreSQL repository ekle
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update

# PostgreSQL 14 kurulumu
sudo apt install -y postgresql-14 postgresql-contrib-14

# PostgreSQL servisini başlat
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### 2. Database ve Kullanıcı Oluşturma

```bash
# PostgreSQL'e bağlan
sudo -u postgres psql

# PostgreSQL içinde:
CREATE DATABASE bulutacente_db;
CREATE USER bulutacente_user WITH PASSWORD 'GÜÇLÜ_ŞİFRE_BURAYA';
ALTER ROLE bulutacente_user SET client_encoding TO 'utf8';
ALTER ROLE bulutacente_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE bulutacente_user SET timezone TO 'Europe/Istanbul';
GRANT ALL PRIVILEGES ON DATABASE bulutacente_db TO bulutacente_user;

# PostgreSQL extension'ları aktifleştir (django-tenants için gerekli)
\c bulutacente_db
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
\q
```

### 3. PostgreSQL Yapılandırması

```bash
# PostgreSQL config dosyasını düzenle
sudo nano /etc/postgresql/14/main/postgresql.conf

# Aşağıdaki satırları bulun ve değiştirin:
# listen_addresses = 'localhost'
# max_connections = 100
# shared_buffers = 256MB

# PostgreSQL'i yeniden başlat
sudo systemctl restart postgresql
```

---

## 🔴 Redis Kurulumu

```bash
# Redis kurulumu
sudo apt install -y redis-server

# Redis config düzenle
sudo nano /etc/redis/redis.conf

# Aşağıdaki satırı bulun ve değiştirin:
# supervised systemd

# Redis'i başlat ve aktifleştir
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Redis test
redis-cli ping
# PONG yanıtı almalısınız
```

---

## 🐍 Python ve Bağımlılıklar

### 1. Python 3.11 Kurulumu

```bash
# Python 3.11 kurulumu
sudo apt install -y python3.11 python3.11-venv python3.11-dev python3-pip

# Python 3.11'i varsayılan yap
sudo update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.11 1
sudo update-alternatives --install /usr/bin/python python /usr/bin/python3.11 1

# pip güncelle
python3 -m pip install --upgrade pip
```

### 2. Sistem Bağımlılıkları

```bash
# Image processing için
sudo apt install -y libjpeg-dev zlib1g-dev libpng-dev libfreetype6-dev

# PostgreSQL client libraries
sudo apt install -y libpq-dev

# WeasyPrint için
sudo apt install -y python3-cffi python3-brotli libpango-1.0-0 libpangoft2-1.0-0

# Diğer bağımlılıklar
sudo apt install -y gettext
```

---

## 📦 Uygulama Kurulumu

### 1. Proje Dizini Oluşturma

```bash
# Proje dizini oluştur
sudo mkdir -p /var/www/bulutacente
sudo chown bulutacente:bulutacente /var/www/bulutacente
cd /var/www/bulutacente
```

### 2. Git ile Proje Çekme

```bash
# Git repository'den çek (veya dosya transferi ile yükleyin)
git clone YOUR_REPOSITORY_URL .

# Veya dosya transferi için:
# scp -r /local/path/to/project bulutacente@YOUR_DROPLET_IP:/var/www/bulutacente/
```

### 3. Virtual Environment Oluşturma

```bash
cd /var/www/bulutacente

# Virtual environment oluştur
python3.11 -m venv venv

# Virtual environment'ı aktifleştir
source venv/bin/activate

# pip güncelle
pip install --upgrade pip setuptools wheel
```

### 4. Python Bağımlılıklarını Yükleme

```bash
# Requirements yükle
pip install -r requirements.txt

# Eğer hata alırsanız, bazı paketleri ayrı yükleyin:
pip install psycopg2-binary
pip install django-tenants
pip install gunicorn
```

### 5. Environment Variables (.env) Dosyası Oluşturma

```bash
# .env dosyası oluştur
nano /var/www/bulutacente/.env
```

**`.env` dosyası içeriği:**

```env
# Django Settings
DEBUG=False
SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA_ÜRETİN
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,*.yourdomain.com

# Database (PostgreSQL)
POSTGRES_DB=bulutacente_db
POSTGRES_USER=bulutacente_user
POSTGRES_PASSWORD=GÜÇLÜ_ŞİFRE_BURAYA
POSTGRES_HOST=localhost
POSTGRES_PORT=5432

# Redis
REDIS_URL=redis://localhost:6379/0

# Celery
CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0

# Email (SMTP veya SES)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com

# Payment (Stripe)
STRIPE_PUBLIC_KEY=pk_live_xxxxx
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# Sentry (Monitoring - Opsiyonel)
SENTRY_DSN=https://xxxxx@sentry.io/xxxxx

# Application
SITE_NAME=Bulut Acente Yönetim Sistemi
SITE_URL=https://yourdomain.com
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

**SECRET_KEY üretmek için:**

```bash
python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
```

### 6. Database Migrations

```bash
# Virtual environment aktif olduğundan emin olun
source venv/bin/activate

# Public schema migrations
python manage.py migrate_schemas --shared

# Superuser oluştur (public schema için)
python manage.py createsuperuser --schema=public
```

### 7. Static Files Toplama

```bash
# Static files topla
python manage.py collectstatic --noinput
```

### 8. Media Dizini Oluşturma

```bash
# Media dizini oluştur
mkdir -p /var/www/bulutacente/media
chmod 755 /var/www/bulutacente/media
```

---

## 🌐 Nginx Yapılandırması

### 1. Nginx Kurulumu

```bash
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 2. Nginx Site Yapılandırması

```bash
# Site config dosyası oluştur
sudo nano /etc/nginx/sites-available/bulutacente
```

**Nginx config içeriği:**

```nginx
# Upstream Gunicorn
upstream bulutacente_app {
    server unix:/var/www/bulutacente/gunicorn.sock fail_timeout=0;
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
    
    # Client Max Body Size (file uploads için)
    client_max_body_size 100M;
    
    # Static Files
    location /static/ {
        alias /var/www/bulutacente/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Media Files
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
sudo ln -s /etc/nginx/sites-available/bulutacente /etc/nginx/sites-enabled/

# Default site'ı kaldır (opsiyonel)
sudo rm /etc/nginx/sites-enabled/default

# Nginx config test
sudo nginx -t

# Nginx'i yeniden başlat
sudo systemctl restart nginx
```

---

## 🔧 Gunicorn Yapılandırması

### 1. Gunicorn Config Dosyası

```bash
# Gunicorn config dosyası oluştur
nano /var/www/bulutacente/gunicorn_config.py
```

**`gunicorn_config.py` içeriği:**

```python
import multiprocessing
import os

# Server socket
bind = "unix:/var/www/bulutacente/gunicorn.sock"
backlog = 2048

# Worker processes
workers = multiprocessing.cpu_count() * 2 + 1
worker_class = "sync"
worker_connections = 1000
timeout = 60
keepalive = 5

# Logging
accesslog = "/var/www/bulutacente/logs/gunicorn_access.log"
errorlog = "/var/www/bulutacente/logs/gunicorn_error.log"
loglevel = "info"
access_log_format = '%(h)s %(l)s %(u)s %(t)s "%(r)s" %(s)s %(b)s "%(f)s" "%(a)s"'

# Process naming
proc_name = "bulutacente"

# Server mechanics
daemon = False
pidfile = "/var/www/bulutacente/gunicorn.pid"
umask = 0
user = "bulutacente"
group = "bulutacente"
tmp_upload_dir = None

# SSL (opsiyonel)
# keyfile = "/path/to/keyfile"
# certfile = "/path/to/certfile"
```

### 2. Log Dizini Oluşturma

```bash
mkdir -p /var/www/bulutacente/logs
chmod 755 /var/www/bulutacente/logs
```

### 3. Systemd Service Dosyası

```bash
# Systemd service dosyası oluştur
sudo nano /etc/systemd/system/bulutacente.service
```

**`/etc/systemd/system/bulutacente.service` içeriği:**

```ini
[Unit]
Description=Bulut Acente Gunicorn daemon
After=network.target postgresql.service redis-server.service

[Service]
User=bulutacente
Group=bulutacente
WorkingDirectory=/var/www/bulutacente
Environment="PATH=/var/www/bulutacente/venv/bin"
ExecStart=/var/www/bulutacente/venv/bin/gunicorn \
    --config /var/www/bulutacente/gunicorn_config.py \
    config.wsgi:application

Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 4. Gunicorn Servisini Başlatma

```bash
# Systemd reload
sudo systemctl daemon-reload

# Servisi başlat
sudo systemctl start bulutacente

# Servisi aktifleştir (boot'ta otomatik başlat)
sudo systemctl enable bulutacente

# Durum kontrolü
sudo systemctl status bulutacente
```

---

## ⚙️ Celery Worker ve Beat

### 1. Celery Worker Systemd Service

```bash
# Celery worker service dosyası oluştur
sudo nano /etc/systemd/system/bulutacente-celery.service
```

**`/etc/systemd/system/bulutacente-celery.service` içeriği:**

```ini
[Unit]
Description=Bulut Acente Celery Worker
After=network.target redis-server.service postgresql.service

[Service]
Type=forking
User=bulutacente
Group=bulutacente
WorkingDirectory=/var/www/bulutacente
Environment="PATH=/var/www/bulutacente/venv/bin"
ExecStart=/var/www/bulutacente/venv/bin/celery \
    -A config worker \
    --loglevel=info \
    --logfile=/var/www/bulutacente/logs/celery_worker.log \
    --pidfile=/var/www/bulutacente/celery_worker.pid \
    --detach

ExecStop=/bin/kill -s TERM $MAINPID
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 2. Celery Beat Systemd Service

```bash
# Celery beat service dosyası oluştur
sudo nano /etc/systemd/system/bulutacente-celerybeat.service
```

**`/etc/systemd/system/bulutacente-celerybeat.service` içeriği:**

```ini
[Unit]
Description=Bulut Acente Celery Beat
After=network.target redis-server.service postgresql.service

[Service]
Type=forking
User=bulutacente
Group=bulutacente
WorkingDirectory=/var/www/bulutacente
Environment="PATH=/var/www/bulutacente/venv/bin"
ExecStart=/var/www/bulutacente/venv/bin/celery \
    -A config beat \
    --loglevel=info \
    --logfile=/var/www/bulutacente/logs/celery_beat.log \
    --pidfile=/var/www/bulutacente/celery_beat.pid \
    --detach

ExecStop=/bin/kill -s TERM $MAINPID
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 3. Celery Servislerini Başlatma

```bash
# Systemd reload
sudo systemctl daemon-reload

# Celery worker başlat
sudo systemctl start bulutacente-celery
sudo systemctl enable bulutacente-celery

# Celery beat başlat
sudo systemctl start bulutacente-celerybeat
sudo systemctl enable bulutacente-celerybeat

# Durum kontrolü
sudo systemctl status bulutacente-celery
sudo systemctl status bulutacente-celerybeat
```

---

## 🔒 SSL Sertifikası (Let's Encrypt)

### 1. Certbot Kurulumu

```bash
# Certbot kurulumu
sudo apt install -y certbot python3-certbot-nginx

# Certbot için dizin oluştur
sudo mkdir -p /var/www/certbot
```

### 2. SSL Sertifikası Alma

```bash
# SSL sertifikası al (Nginx plugin ile)
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Email adresinizi girin ve şartları kabul edin
# Otomatik olarak Nginx config güncellenecek
```

### 3. Otomatik Yenileme

```bash
# Certbot otomatik yenileme test
sudo certbot renew --dry-run

# Cron job kontrolü (otomatik kurulur)
sudo systemctl status certbot.timer
```

---

## 🌍 Domain Yapılandırması

### 1. Digital Ocean DNS API Token

1. Digital Ocean hesabınıza giriş yapın
2. **API** > **Tokens/Keys** bölümüne gidin
3. **Generate New Token** butonuna tıklayın
4. Token adı: `bulutacente-dns-manager`
5. Scopes: `Write` yetkisi verin
6. Token'ı kopyalayın

### 2. Environment Variables

`.env` dosyasına DNS yönetimi için gerekli değişkenleri ekleyin:

```env
# Digital Ocean DNS
DO_API_TOKEN=your_digital_ocean_api_token_here
DO_DOMAIN=yourdomain.com
DO_DROPLET_IP=YOUR_DROPLET_IP_ADDRESS
```

### 3. Ana Domain DNS Kayıtları

Digital Ocean DNS veya domain sağlayıcınızda aşağıdaki kayıtları ekleyin:

**Digital Ocean DNS kullanıyorsanız:**

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

**Not:** Wildcard A record tüm subdomain'leri otomatik olarak droplet IP'sine yönlendirir.

### 4. Nginx Wildcard Domain Yapılandırması

Nginx config dosyasını wildcard domain desteği ile güncelleyin:

```bash
sudo nano /etc/nginx/sites-available/bulutacente
```

**Wildcard domain desteği için:**

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com *.yourdomain.com;
    
    # ... diğer ayarlar
}
```

### 5. Wildcard SSL Sertifikası

Wildcard SSL sertifikası alın (tüm subdomain'ler için):

```bash
# Wildcard SSL sertifikası al
sudo certbot certonly --manual --preferred-challenges dns \
    -d yourdomain.com \
    -d *.yourdomain.com \
    --email your-email@example.com \
    --agree-tos \
    --manual-public-ip-logging-ok

# Certbot size DNS TXT kaydı verecek
# Bu kaydı Digital Ocean DNS'e ekleyin:
# Type: TXT
# Name: _acme-challenge
# Value: [Certbot'un verdiği değer]
# TTL: 300

# DNS kaydını ekledikten sonra Enter'a basın
```

### 6. Django ALLOWED_HOSTS

`.env` dosyasında `ALLOWED_HOSTS` değişkenini güncelleyin:

```env
# Wildcard için '*' kullanabilirsiniz (middleware kontrol edecek)
ALLOWED_HOSTS=*
```

**Veya dinamik kontrol için:**

`config/settings.py` dosyasına middleware ekleyin:

```python
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',
    'apps.tenants.middleware.allowed_hosts.DynamicAllowedHostsMiddleware',  # Yeni
    # ... diğer middleware'ler
]

ALLOWED_HOSTS = ['*']  # Middleware kontrol edecek
```

### 7. Tenant Domain Oluşturma (Otomatik DNS ile)

**Management Command ile:**

```bash
cd /var/www/bulutacente
source venv/bin/activate

# Subdomain ekle (DNS otomatik oluşturulur)
python manage.py add_tenant_domain \
    --tenant-schema=test-otel \
    --domain=test-otel.yourdomain.com \
    --domain-type=subdomain \
    --is-primary

# Custom domain ekle (DNS manuel yapılmalı)
python manage.py add_tenant_domain \
    --tenant-schema=test-otel \
    --domain=otelim.com \
    --domain-type=custom \
    --skip-dns
```

**Django Shell ile:**

```bash
python manage.py shell
```

```python
from apps.tenants.models import Tenant, Domain
from django_tenants.utils import schema_context

# Public schema'da tenant oluştur
with schema_context('public'):
    tenant = Tenant.objects.create(
        schema_name='test-otel',
        name='Test Otel',
        owner_email='test@example.com',
        phone='+905551234567',
        is_active=True
    )
    
    # Domain oluştur (Signal otomatik DNS kaydı oluşturacak)
    Domain.objects.create(
        domain='test-otel.yourdomain.com',
        tenant=tenant,
        domain_type='subdomain',
        is_primary=True
    )
    
    print(f"Tenant oluşturuldu: {tenant.name}")
    print(f"Domain: test-otel.yourdomain.com")
    print("DNS kaydı otomatik oluşturuldu (eğer DO_API_TOKEN ayarlıysa)")
```

### 8. Domain Ekleme Sonrası Kontrol

```bash
# DNS kaydını kontrol et
dig test-otel.yourdomain.com

# Veya
nslookup test-otel.yourdomain.com

# Domain erişilebilirliğini test et
curl -I https://test-otel.yourdomain.com
```

### 9. Otomatik DNS Yönetimi Nasıl Çalışır?

1. **Domain Eklendiğinde**:
   - `Domain` modeli kaydedilir
   - `post_save` signal tetiklenir
   - `DigitalOceanDNSManager` API çağrılır
   - A record otomatik oluşturulur
   - Record ID domain modeline kaydedilir

2. **Domain Silindiğinde**:
   - `post_delete` signal tetiklenir
   - Kaydedilmiş record ID ile DNS kaydı silinir

3. **Custom Domain'ler**:
   - Custom domain'ler için DNS kaydı otomatik oluşturulmaz
   - Domain sahibinin kendi DNS ayarlarını yapması gerekir
   - Sistem sadece domain'i tanır ve routing yapar

### 10. Sorun Giderme

**DNS kaydı oluşturulmadı:**
```bash
# Signal loglarını kontrol et
tail -f /var/www/bulutacente/logs/django.log | grep DNS

# Manuel DNS kaydı oluştur
python manage.py shell
```

```python
from apps.tenants.utils.dns_manager import DigitalOceanDNSManager
dns_manager = DigitalOceanDNSManager()
dns_manager.create_a_record('test-otel')
```

**Domain erişilemiyor:**
```bash
# DNS propagasyon kontrolü (1-5 dakika sürebilir)
dig test-otel.yourdomain.com

# Nginx config kontrolü
sudo nginx -t

# Gunicorn loglarını kontrol et
tail -f /var/www/bulutacente/logs/gunicorn_error.log
```

---

## 💾 Yedekleme Stratejisi

### 1. Database Yedekleme Script

```bash
# Backup script oluştur
nano /var/www/bulutacente/backup_db.sh
```

**`backup_db.sh` içeriği:**

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/bulutacente"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="bulutacente_db"
DB_USER="bulutacente_user"

mkdir -p $BACKUP_DIR

# Database backup
PGPASSWORD='YOUR_DB_PASSWORD' pg_dump -U $DB_USER -h localhost $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Eski yedekleri sil (7 günden eski)
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/db_backup_$DATE.sql.gz"
```

```bash
# Script'i çalıştırılabilir yap
chmod +x /var/www/bulutacente/backup_db.sh

# Cron job ekle (her gün saat 02:00'de)
crontab -e
# Şu satırı ekleyin:
0 2 * * * /var/www/bulutacente/backup_db.sh >> /var/www/bulutacente/logs/backup.log 2>&1
```

### 2. Media Files Yedekleme

```bash
# Media backup script
nano /var/www/bulutacente/backup_media.sh
```

**`backup_media.sh` içeriği:**

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/bulutacente/media"
DATE=$(date +%Y%m%d_%H%M%S)
MEDIA_DIR="/var/www/bulutacente/media"

mkdir -p $BACKUP_DIR

# Media files backup
tar -czf $BACKUP_DIR/media_backup_$DATE.tar.gz -C /var/www/bulutacente media/

# Eski yedekleri sil (7 günden eski)
find $BACKUP_DIR -name "media_backup_*.tar.gz" -mtime +7 -delete

echo "Media backup completed: $BACKUP_DIR/media_backup_$DATE.tar.gz"
```

```bash
chmod +x /var/www/bulutacente/backup_media.sh

# Cron job ekle (her gün saat 03:00'de)
crontab -e
# Şu satırı ekleyin:
0 3 * * * /var/www/bulutacente/backup_media.sh >> /var/www/bulutacente/logs/backup.log 2>&1
```

---

## 📊 Monitoring ve Logging

### 1. Log Rotation Yapılandırması

```bash
# Logrotate config oluştur
sudo nano /etc/logrotate.d/bulutacente
```

**`/etc/logrotate.d/bulutacente` içeriği:**

```
/var/www/bulutacente/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 bulutacente bulutacente
    sharedscripts
    postrotate
        systemctl reload bulutacente > /dev/null 2>&1 || true
    endscript
}
```

### 2. System Monitoring (Opsiyonel)

```bash
# Htop kurulumu (system monitoring)
sudo apt install -y htop

# Netdata kurulumu (advanced monitoring - opsiyonel)
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
```

---

## 🔍 Sorun Giderme

### 1. Log Dosyalarını Kontrol Etme

```bash
# Gunicorn logları
tail -f /var/www/bulutacente/logs/gunicorn_error.log

# Nginx logları
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# Celery logları
tail -f /var/www/bulutacente/logs/celery_worker.log
tail -f /var/www/bulutacente/logs/celery_beat.log

# Systemd servis logları
sudo journalctl -u bulutacente -f
sudo journalctl -u bulutacente-celery -f
```

### 2. Servis Durumlarını Kontrol Etme

```bash
# Tüm servislerin durumu
sudo systemctl status bulutacente
sudo systemctl status bulutacente-celery
sudo systemctl status bulutacente-celerybeat
sudo systemctl status nginx
sudo systemctl status postgresql
sudo systemctl status redis-server
```

### 3. Database Bağlantı Testi

```bash
# PostgreSQL bağlantı testi
psql -U bulutacente_user -d bulutacente_db -h localhost

# Django shell ile test
cd /var/www/bulutacente
source venv/bin/activate
python manage.py dbshell
```

### 4. Yaygın Sorunlar ve Çözümleri

**Problem: 502 Bad Gateway**
```bash
# Gunicorn socket izinlerini kontrol et
ls -la /var/www/bulutacente/gunicorn.sock
sudo chown bulutacente:bulutacente /var/www/bulutacente/gunicorn.sock

# Gunicorn servisini yeniden başlat
sudo systemctl restart bulutacente
```

**Problem: Static files görünmüyor**
```bash
# Static files'ı yeniden topla
cd /var/www/bulutacente
source venv/bin/activate
python manage.py collectstatic --noinput

# Nginx'i yeniden başlat
sudo systemctl restart nginx
```

**Problem: Database connection error**
```bash
# PostgreSQL'in çalıştığını kontrol et
sudo systemctl status postgresql

# .env dosyasındaki database bilgilerini kontrol et
cat /var/www/bulutacente/.env | grep POSTGRES
```

**Problem: Celery çalışmıyor**
```bash
# Celery worker'ı yeniden başlat
sudo systemctl restart bulutacente-celery

# Redis'in çalıştığını kontrol et
redis-cli ping
```

---

## 📝 Önemli Notlar

1. **Güvenlik**:
   - `.env` dosyasını asla Git'e commit etmeyin
   - Firewall kurallarını düzenli kontrol edin
   - SSL sertifikalarını otomatik yenileyin
   - Düzenli güvenlik güncellemeleri yapın

2. **Performans**:
   - Gunicorn worker sayısını CPU'ya göre ayarlayın
   - PostgreSQL connection pool ayarlarını optimize edin
   - Redis cache kullanımını aktifleştirin
   - Static files için CDN kullanmayı düşünün

3. **Yedekleme**:
   - Database yedeklerini düzenli kontrol edin
   - Yedekleri farklı bir lokasyona kopyalayın
   - Restore testleri yapın

4. **Monitoring**:
   - Log dosyalarını düzenli kontrol edin
   - Disk kullanımını izleyin
   - Database boyutunu takip edin

---

## 🚀 Güncelleme İşlemi

Yeni kod güncellemeleri için:

```bash
cd /var/www/bulutacente
source venv/bin/activate

# Git pull
git pull origin main

# Bağımlılıkları güncelle
pip install -r requirements.txt

# Migrations
python manage.py migrate_schemas

# Static files
python manage.py collectstatic --noinput

# Servisleri yeniden başlat
sudo systemctl restart bulutacente
sudo systemctl restart bulutacente-celery
sudo systemctl restart bulutacente-celerybeat
```

---

## 📞 Destek

Sorun yaşarsanız:
1. Log dosyalarını kontrol edin
2. Servis durumlarını kontrol edin
3. Database bağlantısını test edin
4. Nginx config'i kontrol edin

---

**Son Güncelleme:** 2025-01-XX
**Versiyon:** 1.0

