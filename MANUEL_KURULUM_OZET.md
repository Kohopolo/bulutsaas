# Manuel Kurulum Özet Rehberi (Docker Olmadan)

## 🚀 Hızlı Başlangıç

Docker kullanmadan projeyi kurmak için aşağıdaki adımları izleyin.

---

## 📋 Adım Adım Kurulum

### 1. Sunucu Hazırlığı

```bash
# Sistem güncellemesi
apt update && apt upgrade -y

# Temel araçlar
apt install -y curl wget git build-essential software-properties-common

# Güvenlik duvarı
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

---

### 2. PostgreSQL Kurulumu

```bash
# PostgreSQL kurulumu
apt install -y postgresql postgresql-contrib

# PostgreSQL servisini başlat
systemctl start postgresql
systemctl enable postgresql

# Veritabanı ve kullanıcı oluştur
sudo -u postgres psql
```

PostgreSQL shell'de:
```sql
CREATE DATABASE bulutsaas;
CREATE USER bulutsaas_user WITH PASSWORD 'GÜÇLÜ_ŞİFRE_BURAYA';
ALTER ROLE bulutsaas_user SET client_encoding TO 'utf8';
ALTER ROLE bulutsaas_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE bulutsaas_user SET timezone TO 'UTC';
GRANT ALL PRIVILEGES ON DATABASE bulutsaas TO bulutsaas_user;
\q
```

---

### 3. Redis Kurulumu

```bash
# Redis kurulumu
apt install -y redis-server

# Redis servisini başlat
systemctl start redis-server
systemctl enable redis-server

# Redis test
redis-cli ping
# PONG döndürmeli
```

---

### 4. Python ve Proje Kurulumu

```bash
# Python 3.11 kurulumu
apt install -y python3.11 python3.11-venv python3.11-dev python3-pip

# Uygulama klasörü oluştur
mkdir -p /var/www/bulutsaas
cd /var/www/bulutsaas

# Projeyi klonla
git clone https://github.com/Kohopolo/bulutsaas.git .

# Virtual environment oluştur
python3.11 -m venv venv
source venv/bin/activate

# Bağımlılıkları kur
pip install --upgrade pip setuptools wheel
pip install -r requirements.txt
```

---

### 5. Environment Variables (.env)

```bash
# .env dosyası oluştur
nano .env
```

`.env` içeriği:
```env
DEBUG=False
SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA
ALLOWED_HOSTS=bulutacente.com.tr,www.bulutacente.com.tr,88.255.216.16,localhost,127.0.0.1

DATABASE_NAME=bulutsaas
DATABASE_USER=bulutsaas_user
DATABASE_PASSWORD=GÜÇLÜ_ŞİFRE_BURAYA
DATABASE_HOST=localhost
DATABASE_PORT=5432

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0

CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0

STATIC_ROOT=/var/www/bulutsaas/staticfiles
MEDIA_ROOT=/var/www/bulutsaas/media
```

Secret key oluştur:
```bash
python3.11 -c "import secrets; print(secrets.token_urlsafe(50))"
```

---

### 6. Database Migration ve Static Files

```bash
# Virtual environment aktif olmalı
source venv/bin/activate

# Migrasyonları çalıştır
python manage.py migrate_schemas --shared
python manage.py migrate_schemas

# Static files topla
python manage.py collectstatic --noinput

# Superuser oluştur
python manage.py createsuperuser
```

---

### 7. Gunicorn Kurulumu ve Yapılandırması

```bash
# Gunicorn socket dosyası
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

```bash
# Gunicorn service dosyası
sudo nano /etc/systemd/system/gunicorn.service
```

İçerik:
```ini
[Unit]
Description=gunicorn daemon
Requires=gunicorn.socket
After=network.target postgresql.service redis-server.service

[Service]
User=root
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
ExecStart=/var/www/bulutsaas/venv/bin/gunicorn \
    --access-logfile /var/www/bulutsaas/logs/gunicorn_access.log \
    --error-logfile /var/www/bulutsaas/logs/gunicorn_error.log \
    --workers 4 \
    --timeout 120 \
    --bind unix:/var/www/bulutsaas/gunicorn.sock \
    config.wsgi:application

[Install]
WantedBy=multi-user.target
```

```bash
# Log klasörü oluştur
mkdir -p /var/www/bulutsaas/logs

# Servisleri başlat
systemctl start gunicorn.socket
systemctl enable gunicorn.socket
systemctl start gunicorn.service
systemctl enable gunicorn.service

# Durumu kontrol et
systemctl status gunicorn.service
```

---

### 8. Nginx Kurulumu ve Yapılandırması

```bash
# Nginx kurulumu
apt install -y nginx

# Site konfigürasyonu
sudo nano /etc/nginx/sites-available/bulutsaas
```

İçerik:
```nginx
upstream django {
    server unix:/var/www/bulutsaas/gunicorn.sock fail_timeout=0;
}

server {
    listen 80;
    server_name bulutacente.com.tr www.bulutacente.com.tr 88.255.216.16;
    client_max_body_size 50M;

    location /static/ {
        alias /var/www/bulutsaas/staticfiles/;
        expires 30d;
    }

    location /media/ {
        alias /var/www/bulutsaas/media/;
        expires 7d;
    }

    location / {
        proxy_pass http://django;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
    }
}
```

```bash
# Site'ı aktif et
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

# Nginx test ve restart
nginx -t
systemctl restart nginx
systemctl enable nginx
```

---

### 9. Celery Worker ve Beat

```bash
# Celery worker service
sudo nano /etc/systemd/system/celery_worker.service
```

İçerik:
```ini
[Unit]
Description=celery worker daemon
After=network.target redis.service postgresql.service

[Service]
Type=simple
User=root
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
ExecStart=/var/www/bulutsaas/venv/bin/celery -A config worker \
    --loglevel=info \
    --logfile=/var/www/bulutsaas/logs/celery_worker.log

[Install]
WantedBy=multi-user.target
```

```bash
# Celery beat service
sudo nano /etc/systemd/system/celery_beat.service
```

İçerik:
```ini
[Unit]
Description=celery beat daemon
After=network.target redis.service postgresql.service

[Service]
Type=simple
User=root
Group=www-data
WorkingDirectory=/var/www/bulutsaas
Environment="PATH=/var/www/bulutsaas/venv/bin"
ExecStart=/var/www/bulutsaas/venv/bin/celery -A config beat \
    --loglevel=info \
    --logfile=/var/www/bulutsaas/logs/celery_beat.log \
    --scheduler django_celery_beat.schedulers:DatabaseScheduler

[Install]
WantedBy=multi-user.target
```

```bash
# Servisleri başlat
systemctl start celery_worker.service
systemctl enable celery_worker.service
systemctl start celery_beat.service
systemctl enable celery_beat.service

# Durumu kontrol et
systemctl status celery_worker.service
systemctl status celery_beat.service
```

---

### 10. SSL Sertifikası (Let's Encrypt)

```bash
# Certbot kurulumu
apt install -y certbot python3-certbot-nginx

# SSL sertifikası oluştur
certbot --nginx -d bulutacente.com.tr -d www.bulutacente.com.tr

# Otomatik yenileme testi
certbot renew --dry-run
```

---

## ✅ Kontrol Komutları

```bash
# Tüm servislerin durumunu kontrol et
systemctl status gunicorn.service
systemctl status celery_worker.service
systemctl status celery_beat.service
systemctl status nginx
systemctl status postgresql
systemctl status redis-server

# Web sitesini test et
curl http://bulutacente.com.tr/health/
curl http://bulutacente.com.tr/admin/

# Logları kontrol et
tail -f /var/www/bulutsaas/logs/gunicorn_error.log
tail -f /var/www/bulutsaas/logs/celery_worker.log
```

---

## 📝 Önemli Notlar

- Tüm dosya yolları `/var/www/bulutsaas` olarak ayarlanmıştır
- Kullanıcı `root` olarak ayarlanmıştır (güvenlik için özel kullanıcı oluşturabilirsiniz)
- `.env` dosyasındaki tüm şifreleri güçlü şifrelerle değiştirin
- SSL sertifikası otomatik olarak yenilenecektir

---

## 🎉 Kurulum Tamamlandı!

Detaylı rehber için `HOSTINGER_VPS_MANUAL_KURULUM.md` dosyasına bakın.

