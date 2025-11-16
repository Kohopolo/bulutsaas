# Hostinger VPS Manuel Kurulum Rehberi
## Bulut Acente Yönetim Sistemi - Docker Olmadan Kurulum

Bu rehber, Django multi-tenant uygulamanızı Hostinger VPS'e Docker olmadan nasıl kuracağınızı adım adım açıklar.

**⚠️ ÖNEMLİ:** Bu rehber Docker kullanmadan manuel kurulum için hazırlanmıştır. Tüm servisler (PostgreSQL, Redis, Nginx, Gunicorn, Celery) doğrudan VPS üzerinde çalışacaktır.

---

## 📋 İçindekiler

1. [Gereksinimler](#gereksinimler)
2. [Sunucu Hazırlığı](#sunucu-hazırlığı)
3. [PostgreSQL Kurulumu](#postgresql-kurulumu)
4. [Redis Kurulumu](#redis-kurulumu)
5. [Python ve Bağımlılıklar](#python-ve-bağımlılıklar)
6. [Uygulama Kurulumu](#uygulama-kurulumu)
7. [Nginx Yapılandırması](#nginx-yapılandırması)
8. [Gunicorn Yapılandırması](#gunicorn-yapılandırması)
9. [Celery Worker ve Beat](#celery-worker-ve-beat)
10. [SSL Sertifikası (Let's Encrypt)](#ssl-sertifikası-lets-encrypt)
11. [Domain Yapılandırması](#domain-yapılandırması)
12. [Systemd Servisleri](#systemd-servisleri)
13. [Sorun Giderme](#sorun-giderme)

---

## 🎯 Gereksinimler

### Minimum Sistem Gereksinimleri
- **RAM**: 2GB (4GB önerilir)
- **CPU**: 2 vCPU
- **Disk**: 40GB SSD
- **İşletim Sistemi**: Ubuntu 22.04 LTS veya 20.04 LTS

### Gerekli Servisler
- PostgreSQL 14+ (veya Hostinger Managed Database)
- Redis 7+
- Python 3.11+
- Nginx
- Gunicorn
- Certbot (SSL için)

---

## 🛠️ Sunucu Hazırlığı

### 1. Sistem Güncellemesi

```bash
# Sistem güncellemesi
sudo apt update && sudo apt upgrade -y

# Temel araçlar
sudo apt install -y curl wget git build-essential software-properties-common
```

### 2. Kullanıcı Oluşturma (Opsiyonel)

```bash
# Yeni kullanıcı oluştur
sudo adduser bulutacente
sudo usermod -aG sudo bulutacente

# Kullanıcıya geç
su - bulutacente
```

### 3. Güvenlik Duvarı Yapılandırması

```bash
# UFW firewall kurulumu
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

---

## 🗄️ PostgreSQL Kurulumu

### Seçenek 1: Hostinger Managed Database (Önerilir)

Hostinger panelinden PostgreSQL managed database oluşturun ve bağlantı bilgilerini alın.

### Seçenek 2: VPS Üzerinde PostgreSQL

```bash
# PostgreSQL kurulumu
sudo apt install -y postgresql postgresql-contrib

# PostgreSQL servisini başlat
sudo systemctl start postgresql
sudo systemctl enable postgresql

# PostgreSQL kullanıcısına geç
sudo -u postgres psql

# Veritabanı ve kullanıcı oluştur
CREATE DATABASE bulutsaas;
CREATE USER bulutsaas_user WITH PASSWORD 'GÜÇLÜ_ŞİFRE_BURAYA';
ALTER ROLE bulutsaas_user SET client_encoding TO 'utf8';
ALTER ROLE bulutsaas_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE bulutsaas_user SET timezone TO 'UTC';
GRANT ALL PRIVILEGES ON DATABASE bulutsaas TO bulutsaas_user;
\q

# PostgreSQL konfigürasyonunu düzenle
sudo nano /etc/postgresql/14/main/postgresql.conf
# listen_addresses = 'localhost' olduğundan emin olun

sudo nano /etc/postgresql/14/main/pg_hba.conf
# local   all             all                                     peer
# host    all             all             127.0.0.1/32            md5

# PostgreSQL'i yeniden başlat
sudo systemctl restart postgresql
```

---

## 🔴 Redis Kurulumu

```bash
# Redis kurulumu
sudo apt install -y redis-server

# Redis konfigürasyonunu düzenle
sudo nano /etc/redis/redis.conf
# supervised systemd olarak değiştir
# bind 127.0.0.1 olduğundan emin olun

# Redis servisini başlat
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Redis test
redis-cli ping
# PONG döndürmeli
```

---

## 🐍 Python ve Bağımlılıklar

### 1. Python 3.11 Kurulumu

```bash
# Python 3.11 kurulumu
sudo apt install -y python3.11 python3.11-venv python3.11-dev python3-pip

# Python versiyonunu kontrol et
python3.11 --version
```

### 2. Uygulama Klasörü Oluşturma

```bash
# Uygulama klasörü oluştur
sudo mkdir -p /var/www/bulutsaas
sudo chown $USER:$USER /var/www/bulutsaas
cd /var/www/bulutsaas
```

### 3. Projeyi Klonlama

```bash
# Git'ten projeyi klonla
git clone https://github.com/Kohopolo/bulutsaas.git .

# Veya dosyaları SCP ile kopyala
# scp -r /local/path/to/project/* user@vps:/var/www/bulutsaas/
```

### 4. Virtual Environment Oluşturma

```bash
# Virtual environment oluştur
python3.11 -m venv venv

# Virtual environment'ı aktif et
source venv/bin/activate

# Pip'i güncelle
pip install --upgrade pip setuptools wheel
```

### 5. Bağımlılıkları Kurma

```bash
# Requirements.txt'den bağımlılıkları kur
pip install -r requirements.txt
```

---

## 📁 Uygulama Kurulumu

### 1. Environment Variables (.env)

```bash
# .env dosyası oluştur
cd /var/www/bulutsaas
nano .env
```

`.env` dosyası içeriği:

```env
# Django Settings
DEBUG=False
SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA
ALLOWED_HOSTS=bulutacente.com.tr,www.bulutacente.com.tr,72.62.35.155,localhost,127.0.0.1

# Database (Hostinger Managed Database veya VPS PostgreSQL)
DATABASE_NAME=bulutsaas
DATABASE_USER=bulutsaas_user
DATABASE_PASSWORD=GÜÇLÜ_ŞİFRE_BURAYA
DATABASE_HOST=localhost
DATABASE_PORT=5432

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0

# Celery
CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0

# Email (Opsiyonel)
EMAIL_HOST=smtp.hostinger.com
EMAIL_PORT=465
EMAIL_USE_SSL=True
EMAIL_HOST_USER=noreply@bulutacente.com.tr
EMAIL_HOST_PASSWORD=EMAIL_ŞİFRE_BURAYA
DEFAULT_FROM_EMAIL=noreply@bulutacente.com.tr

# Static ve Media
STATIC_ROOT=/var/www/bulutsaas/staticfiles
MEDIA_ROOT=/var/www/bulutsaas/media
```

### 2. Secret Key Oluşturma

```bash
# Secret key oluştur
python3.11 -c "import secrets; print(secrets.token_urlsafe(50))"
# Çıktıyı .env dosyasındaki SECRET_KEY'e kopyalayın
```

### 3. Veritabanı Migrasyonları

```bash
# Virtual environment aktif olmalı
source venv/bin/activate

# Django-tenants için önce shared schema migration
python manage.py migrate_schemas --shared

# Sonra tenant schema migration (varsa)
python manage.py migrate_schemas

# Veya tek tek:
# python manage.py migrate
```

### 4. Static Dosyaları Toplama

```bash
# Static dosyaları topla
python manage.py collectstatic --noinput --settings=config.settings
```

### 5. Superuser Oluşturma

```bash
# Superuser oluştur
python manage.py createsuperuser --settings=config.settings
```

---

## 🌐 Nginx Yapılandırması

### 1. Nginx Kurulumu

```bash
# Nginx kurulumu
sudo apt install -y nginx

# Nginx servisini başlat
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 2. Nginx Site Konfigürasyonu

```bash
# Site konfigürasyonu oluştur
sudo nano /etc/nginx/sites-available/bulutsaas
```

İçerik:

```nginx
upstream django {
    server unix:/var/www/bulutsaas/gunicorn.sock fail_timeout=0;
}

server {
    listen 80;
    server_name bulutacente.com.tr www.bulutacente.com.tr 72.62.35.155;
    client_max_body_size 50M;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Static files
    location /static/ {
        alias /var/www/bulutsaas/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files
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
sudo ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/

# Varsayılan site'ı kaldır (opsiyonel)
sudo rm /etc/nginx/sites-enabled/default

# Nginx konfigürasyonunu test et
sudo nginx -t

# Nginx'i yeniden yükle
sudo systemctl reload nginx
```

---

## 🔧 Gunicorn Yapılandırması

### 1. Gunicorn Socket ve Service Dosyası

```bash
# Gunicorn socket dosyası oluştur
sudo nano /etc/systemd/system/gunicorn.socket
```

İçerik:

```ini
[Unit]
Description=gunicorn socket

[Socket]
ListenStream=/var/www/bulutsaas/gunicorn.sock

[Install]
WantedBy=sockets.target
```

### 2. Gunicorn Service Dosyası

```bash
# Gunicorn service dosyası oluştur
sudo nano /etc/systemd/system/gunicorn.service
```

İçerik:

```ini
[Unit]
Description=gunicorn daemon
Requires=gunicorn.socket
After=network.target postgresql.service redis-server.service

[Service]
User=bulutacente
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
EnvironmentFile=/var/www/bulutsaas/.env
ExecStart=/var/www/bulutsaas/venv/bin/gunicorn \
    --access-logfile /var/www/bulutsaas/logs/gunicorn_access.log \
    --error-logfile /var/www/bulutsaas/logs/gunicorn_error.log \
    --workers 4 \
    --worker-class sync \
    --timeout 120 \
    --bind unix:/var/www/bulutsaas/gunicorn.sock \
    config.wsgi:application

[Install]
WantedBy=multi-user.target
```

**Not:** `User=bulutacente` yerine kendi kullanıcı adınızı kullanın. Root kullanıcısı ile çalışıyorsanız `User=root` yapın.

### 3. Gunicorn Servislerini Başlatma

```bash
# Socket ve service'i başlat
sudo systemctl start gunicorn.socket
sudo systemctl enable gunicorn.socket
sudo systemctl start gunicorn.service
sudo systemctl enable gunicorn.service

# Durumu kontrol et
sudo systemctl status gunicorn.service
```

---

## 🔄 Celery Worker ve Beat

### 1. Celery Worker Service

```bash
# Celery worker service dosyası oluştur
sudo nano /etc/systemd/system/celery_worker.service
```

İçerik:

```ini
[Unit]
Description=celery worker daemon
After=network.target redis.service postgresql.service

[Service]
Type=forking
User=bulutacente
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
ExecStart=/var/www/bulutsaas/venv/bin/celery -A config worker \
    --loglevel=info \
    --logfile=/var/www/bulutsaas/logs/celery_worker.log \
    --pidfile=/var/www/bulutsaas/logs/celery_worker.pid \
    --detach

[Install]
WantedBy=multi-user.target
```

### 2. Celery Beat Service

```bash
# Celery beat service dosyası oluştur
sudo nano /etc/systemd/system/celery_beat.service
```

İçerik:

```ini
[Unit]
Description=celery beat daemon
After=network.target redis.service postgresql.service

[Service]
Type=forking
User=bulutacente
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
ExecStart=/var/www/bulutsaas/venv/bin/celery -A config beat \
    --loglevel=info \
    --logfile=/var/www/bulutsaas/logs/celery_beat.log \
    --pidfile=/var/www/bulutsaas/logs/celery_beat.pid \
    --detach

[Install]
WantedBy=multi-user.target
```

### 3. Log ve Media Klasörleri Oluşturma

```bash
# Log klasörü oluştur
mkdir -p /var/www/bulutsaas/logs
chmod 755 /var/www/bulutsaas/logs

# Media klasörü oluştur
mkdir -p /var/www/bulutsaas/media
chmod 755 /var/www/bulutsaas/media

# Static klasörü oluştur
mkdir -p /var/www/bulutsaas/staticfiles
chmod 755 /var/www/bulutsaas/staticfiles

# Kullanıcı izinlerini ayarla (bulutacente yerine kendi kullanıcı adınızı kullanın)
sudo chown -R bulutacente:www-data /var/www/bulutsaas
```

### 4. Celery Servislerini Başlatma

```bash
# Celery servislerini başlat
sudo systemctl start celery_worker.service
sudo systemctl enable celery_worker.service
sudo systemctl start celery_beat.service
sudo systemctl enable celery_beat.service

# Durumu kontrol et
sudo systemctl status celery_worker.service
sudo systemctl status celery_beat.service
```

---

## 🔒 SSL Sertifikası (Let's Encrypt)

```bash
# Certbot kurulumu
sudo apt install -y certbot python3-certbot-nginx

# SSL sertifikası oluştur
sudo certbot --nginx -d bulutacente.com.tr -d www.bulutacente.com.tr

# Otomatik yenileme testi
sudo certbot renew --dry-run
```

---

## 🌍 Domain Yapılandırması

### 1. Django'da Domain Ekleme

```bash
# Virtual environment aktif olmalı
source /var/www/bulutsaas/venv/bin/activate

# Django shell'de domain ekle
cd /var/www/bulutsaas
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

### 2. Hostinger DNS Ayarları

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

## 🔄 Systemd Servisleri Yönetimi

### Servisleri Başlatma/Durdurma

```bash
# Gunicorn
sudo systemctl start gunicorn.service
sudo systemctl stop gunicorn.service
sudo systemctl restart gunicorn.service
sudo systemctl status gunicorn.service

# Celery Worker
sudo systemctl start celery_worker.service
sudo systemctl stop celery_worker.service
sudo systemctl restart celery_worker.service

# Celery Beat
sudo systemctl start celery_beat.service
sudo systemctl stop celery_beat.service
sudo systemctl restart celery_beat.service

# Nginx
sudo systemctl start nginx
sudo systemctl restart nginx
sudo systemctl status nginx
```

### Logları İzleme

```bash
# Gunicorn logları
sudo journalctl -u gunicorn.service -f

# Celery worker logları
tail -f /var/www/bulutsaas/logs/celery_worker.log

# Celery beat logları
tail -f /var/www/bulutsaas/logs/celery_beat.log

# Nginx logları
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
```

---

## 🐛 Sorun Giderme

### Gunicorn Socket Hatası

```bash
# Socket dosyasını kontrol et
ls -la /var/www/bulutsaas/gunicorn.sock

# Gunicorn'u yeniden başlat
sudo systemctl restart gunicorn.service
```

### Nginx 502 Bad Gateway

```bash
# Gunicorn durumunu kontrol et
sudo systemctl status gunicorn.service

# Socket izinlerini kontrol et
sudo chown bulutacente:www-data /var/www/bulutsaas/gunicorn.sock
sudo chmod 660 /var/www/bulutsaas/gunicorn.sock
```

### Database Bağlantı Hatası

```bash
# PostgreSQL durumunu kontrol et
sudo systemctl status postgresql

# Bağlantıyı test et
psql -U bulutsaas_user -d bulutsaas -h localhost
```

### Celery Çalışmıyor

```bash
# Celery worker durumunu kontrol et
sudo systemctl status celery_worker.service

# Logları kontrol et
tail -f /var/www/bulutsaas/logs/celery_worker.log
```

---

## ✅ Kurulum Sonrası Kontroller

```bash
# Tüm servislerin durumunu kontrol et
sudo systemctl status gunicorn.service
sudo systemctl status celery_worker.service
sudo systemctl status celery_beat.service
sudo systemctl status nginx
sudo systemctl status postgresql
sudo systemctl status redis-server

# Web sitesini test et
curl http://bulutacente.com.tr/health/
curl http://bulutacente.com.tr/admin/
```

---

## 📝 Notlar

- Tüm dosya yolları `/var/www/bulutsaas` olarak ayarlanmıştır, kendi yapınıza göre değiştirebilirsiniz
- Kullanıcı adı `bulutacente` olarak ayarlanmıştır, kendi kullanıcı adınıza göre değiştirin
- `.env` dosyasındaki tüm şifreleri güçlü şifrelerle değiştirin
- SSL sertifikası otomatik olarak yenilenecektir (certbot)

---

## 🎉 Kurulum Tamamlandı!

Artık uygulamanız Docker olmadan çalışıyor. Herhangi bir sorun yaşarsanız logları kontrol edin.

