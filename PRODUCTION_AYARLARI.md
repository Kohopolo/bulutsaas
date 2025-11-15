# Production Ayarları Rehberi

Bu doküman, Django SaaS 2026 projesinin production ortamına deploy edilmesi için gerekli tüm güvenlik ve performans ayarlarını içermektedir.

## 📋 İçindekiler

1. [Güvenlik Ayarları](#güvenlik-ayarları)
2. [Environment Variables (.env)](#environment-variables-env)
3. [SSL/HTTPS Yapılandırması](#sslhttps-yapılandırması)
4. [Database Ayarları](#database-ayarları)
5. [Static ve Media Dosyaları](#static-ve-media-dosyaları)
6. [Email Yapılandırması](#email-yapılandırması)
7. [Redis ve Cache Yapılandırması](#redis-ve-cache-yapılandırması)
8. [Celery Yapılandırması](#celery-yapılandırması)
9. [Logging Yapılandırması](#logging-yapılandırması)
10. [Web Server Yapılandırması](#web-server-yapılandırması)
11. [Monitoring ve Backup](#monitoring-ve-backup)
12. [Deployment Checklist](#deployment-checklist)

---

## 🔒 Güvenlik Ayarları

### 1. SECRET_KEY

**ÖNEMLİ:** Production'da mutlaka güçlü ve benzersiz bir SECRET_KEY kullanın!

```bash
# Python ile güçlü SECRET_KEY oluşturma
python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
```

**Minimum Gereksinimler:**
- En az 50 karakter
- En az 5 benzersiz karakter
- Rastgele ve tahmin edilemez olmalı
- `django-insecure-` ile başlamamalı

### 2. DEBUG Modu

**Production'da DEBUG mutlaka `False` olmalıdır!**

```python
# config/settings.py
DEBUG = False  # Production için
```

**Neden Önemli:**
- DEBUG=True olduğunda hassas bilgiler (database şifreleri, SECRET_KEY vb.) hata sayfalarında görünebilir
- Performans sorunlarına neden olur
- Güvenlik açıkları oluşturur

### 3. ALLOWED_HOSTS

Production domain'lerinizi mutlaka ekleyin:

```python
# config/settings.py veya .env
ALLOWED_HOSTS = [
    'yourdomain.com',
    'www.yourdomain.com',
    'api.yourdomain.com',
    # Tenant domain'leri
    'tenant1.yourdomain.com',
    'tenant2.yourdomain.com',
]
```

**Not:** Django Tenants kullanıldığı için her tenant domain'ini ayrı ayrı eklemeniz gerekir.

---

## 🔐 SSL/HTTPS Yapılandırması

### 1. SSL Sertifikası

Production'da mutlaka SSL sertifikası kullanın:
- **Let's Encrypt** (Ücretsiz, önerilen)
- **Cloudflare** (CDN + SSL)
- **Ticari SSL Sertifikaları**

### 2. Django SSL Ayarları

Aşağıdaki ayarlar `config/settings.py` dosyasında `DEBUG=False` olduğunda otomatik aktif olur:

```python
# config/settings.py (zaten mevcut)
if not DEBUG:
    SECURE_SSL_REDIRECT = True  # HTTP'den HTTPS'ye yönlendirme
    SESSION_COOKIE_SECURE = True  # Session cookie'leri sadece HTTPS üzerinden
    CSRF_COOKIE_SECURE = True  # CSRF cookie'leri sadece HTTPS üzerinden
    SECURE_BROWSER_XSS_FILTER = True  # XSS koruması
    SECURE_CONTENT_TYPE_NOSNIFF = True  # MIME type sniffing koruması
    X_FRAME_OPTIONS = 'DENY'  # Clickjacking koruması
```

### 3. HSTS (HTTP Strict Transport Security)

**ÖNEMLİ:** HSTS'yi dikkatli kullanın! Yanlış yapılandırma site erişimini engelleyebilir.

```python
# config/settings.py
if not DEBUG:
    # HSTS ayarları (SSL sertifikanız hazır olduktan sonra aktif edin!)
    SECURE_HSTS_SECONDS = 31536000  # 1 yıl (sadece SSL hazır olduktan sonra!)
    SECURE_HSTS_INCLUDE_SUBDOMAINS = True  # Alt domain'leri de dahil et
    SECURE_HSTS_PRELOAD = True  # Browser preload listesine ekleme için
```

**HSTS Aktifleştirme Adımları:**
1. SSL sertifikanızın çalıştığını doğrulayın
2. Tüm sayfaların HTTPS üzerinden erişilebildiğini test edin
3. Önce küçük bir değerle başlayın (örn: 3600 = 1 saat)
4. Sorun yoksa artırın (31536000 = 1 yıl)

---

## 📝 Environment Variables (.env)

Production için `.env` dosyası örneği:

```bash
# .env.production

# Django Ayarları
DEBUG=False
SECRET_KEY=your-super-secret-key-minimum-50-characters-long-and-random
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,api.yourdomain.com

# Database
POSTGRES_DB=saas_production_db
POSTGRES_USER=saas_prod_user
POSTGRES_PASSWORD=strong-database-password-here
POSTGRES_HOST=db.example.com
POSTGRES_PORT=5432

# Redis
REDIS_URL=redis://redis.example.com:6379/0

# Celery
CELERY_BROKER_URL=redis://redis.example.com:6379/1
CELERY_RESULT_BACKEND=redis://redis.example.com:6379/2

# Email (SMTP)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-specific-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com

# Site Bilgileri
SITE_NAME=Your SaaS Platform
SITE_URL=https://yourdomain.com
ADMIN_URL=admin/

# Stripe (Ödeme)
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Subscription Ayarları
TRIAL_PERIOD_DAYS=14
SUBSCRIPTION_GRACE_PERIOD_DAYS=3
```

**Güvenlik Notları:**
- `.env` dosyasını **ASLA** Git'e commit etmeyin!
- `.gitignore` dosyasına `.env` ekleyin
- Production sunucuda `.env` dosyasına sadece gerekli kullanıcılar erişebilmeli (chmod 600)
- Farklı ortamlar için farklı `.env` dosyaları kullanın (`.env.production`, `.env.staging`)

---

## 🗄️ Database Ayarları

### 1. PostgreSQL Yapılandırması

```python
# config/settings.py (zaten mevcut)
DATABASES = {
    'default': {
        'ENGINE': 'django_tenants.postgresql_backend',
        'NAME': env('POSTGRES_DB', default='saas_db'),
        'USER': env('POSTGRES_USER', default='saas_user'),
        'PASSWORD': env('POSTGRES_PASSWORD', default='saas_password_2026'),
        'HOST': env('POSTGRES_HOST', default='db'),
        'PORT': env('POSTGRES_PORT', default='5432'),
        'OPTIONS': {
            'connect_timeout': 10,
        },
        'CONN_MAX_AGE': 600,  # Connection pooling için
    }
}
```

### 2. Database Backup

**ÖNEMLİ:** Düzenli database backup'ları alın!

```bash
# Otomatik backup script örneği
#!/bin/bash
BACKUP_DIR="/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)
pg_dump -h db.example.com -U saas_prod_user saas_production_db > "$BACKUP_DIR/backup_$DATE.sql"
# Eski backup'ları sil (30 günden eski)
find $BACKUP_DIR -name "backup_*.sql" -mtime +30 -delete
```

### 3. Database Connection Pooling

Production'da connection pooling kullanın:
- **PgBouncer** (Önerilen)
- **Django CONN_MAX_AGE** (basit çözüm)

---

## 📁 Static ve Media Dosyaları

### 1. Static Files

```python
# config/settings.py (zaten mevcut)
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR / 'staticfiles'
```

**Collectstatic:**
```bash
python manage.py collectstatic --noinput
```

### 2. Media Files

**ÖNEMLİ:** Media dosyalarını CDN veya object storage'a taşıyın!

**Seçenekler:**
- **AWS S3** + CloudFront
- **Google Cloud Storage**
- **Azure Blob Storage**
- **DigitalOcean Spaces**

**Django S3 Örneği:**
```python
# pip install django-storages boto3
INSTALLED_APPS = [
    ...
    'storages',
]

# AWS S3 Ayarları
AWS_ACCESS_KEY_ID = env('AWS_ACCESS_KEY_ID')
AWS_SECRET_ACCESS_KEY = env('AWS_SECRET_ACCESS_KEY')
AWS_STORAGE_BUCKET_NAME = env('AWS_STORAGE_BUCKET_NAME')
AWS_S3_REGION_NAME = env('AWS_S3_REGION_NAME', default='us-east-1')
AWS_S3_CUSTOM_DOMAIN = f'{AWS_STORAGE_BUCKET_NAME}.s3.amazonaws.com'
AWS_DEFAULT_ACL = 'public-read'
AWS_S3_OBJECT_PARAMETERS = {
    'CacheControl': 'max-age=86400',
}

# Media files için S3
DEFAULT_FILE_STORAGE = 'storages.backends.s3boto3.S3Boto3Storage'
MEDIA_URL = f'https://{AWS_S3_CUSTOM_DOMAIN}/'
```

### 3. Nginx Yapılandırması

```nginx
# /etc/nginx/sites-available/yourdomain.com
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL ayarları
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Static files
    location /static/ {
        alias /path/to/your/project/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files (S3 kullanmıyorsanız)
    location /media/ {
        alias /path/to/your/project/media/;
        expires 7d;
        add_header Cache-Control "public";
    }

    # Django uygulaması
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
    }
}
```

---

## 📧 Email Yapılandırması

### 1. SMTP Ayarları

```python
# config/settings.py (zaten mevcut)
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = env('EMAIL_HOST', default='smtp.gmail.com')
EMAIL_PORT = env.int('EMAIL_PORT', default=587)
EMAIL_USE_TLS = env.bool('EMAIL_USE_TLS', default=True)
EMAIL_HOST_USER = env('EMAIL_HOST_USER', default='')
EMAIL_HOST_PASSWORD = env('EMAIL_HOST_PASSWORD', default='')
DEFAULT_FROM_EMAIL = env('DEFAULT_FROM_EMAIL', default='noreply@saas2026.com')
```

### 2. Email Servis Sağlayıcıları

**Gmail (Gmail API):**
- App-specific password kullanın
- 2FA aktif olmalı

**SendGrid:**
```python
EMAIL_BACKEND = 'sendgrid_backend.SendgridBackend'
SENDGRID_API_KEY = env('SENDGRID_API_KEY')
```

**Amazon SES:**
```python
EMAIL_BACKEND = 'django_ses.SESBackend'
AWS_SES_REGION_NAME = 'us-east-1'
AWS_SES_REGION_ENDPOINT = 'email.us-east-1.amazonaws.com'
```

---

## 🔴 Redis ve Cache Yapılandırması

### 1. Redis Kurulumu

```bash
# Ubuntu/Debian
sudo apt-get install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 2. Django Cache Yapılandırması

```python
# config/settings.py
CACHES = {
    'default': {
        'BACKEND': 'django_redis.cache.RedisCache',
        'LOCATION': env('REDIS_URL', default='redis://127.0.0.1:6379/0'),
        'OPTIONS': {
            'CLIENT_CLASS': 'django_redis.client.DefaultClient',
        },
        'KEY_PREFIX': 'saas2026',
        'TIMEOUT': 300,
    }
}

# Session için Redis
SESSION_ENGINE = 'django.contrib.sessions.backends.cache'
SESSION_CACHE_ALIAS = 'default'
```

### 3. Redis Güvenlik

```bash
# /etc/redis/redis.conf
requirepass your-strong-redis-password
bind 127.0.0.1  # Sadece localhost'tan erişim
```

---

## ⚙️ Celery Yapılandırması

### 1. Celery Worker

```bash
# Production için Celery worker başlatma
celery -A config worker --loglevel=info --concurrency=4

# Supervisor ile otomatik başlatma (önerilen)
```

### 2. Supervisor Yapılandırması

```ini
# /etc/supervisor/conf.d/celery.conf
[program:celery]
command=/path/to/venv/bin/celery -A config worker --loglevel=info
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/celery/worker.log
```

### 3. Celery Beat (Zamanlanmış Görevler)

```ini
# /etc/supervisor/conf.d/celery-beat.conf
[program:celery-beat]
command=/path/to/venv/bin/celery -A config beat --loglevel=info
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/celery/beat.log
```

---

## 📊 Logging Yapılandırması

```python
# config/settings.py (zaten mevcut)
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '{levelname} {asctime} {module} {process:d} {thread:d} {message}',
            'style': '{',
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'verbose',
        },
        'file': {
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': '/var/log/django/django.log',
            'maxBytes': 1024 * 1024 * 10,  # 10 MB
            'backupCount': 5,
            'formatter': 'verbose',
        },
    },
    'root': {
        'handlers': ['console', 'file'],
        'level': 'INFO',  # Production için INFO, DEBUG değil!
    },
    'loggers': {
        'django': {
            'handlers': ['console', 'file'],
            'level': 'INFO',
            'propagate': False,
        },
    },
}
```

**Log Rotation:**
```bash
# Logrotate yapılandırması
# /etc/logrotate.d/django
/var/log/django/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

---

## 🌐 Web Server Yapılandırması

### 1. Gunicorn

```bash
# Gunicorn kurulumu
pip install gunicorn

# Gunicorn başlatma
gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4 \
    --worker-class sync \
    --timeout 120 \
    --max-requests 1000 \
    --max-requests-jitter 50 \
    --access-logfile /var/log/gunicorn/access.log \
    --error-logfile /var/log/gunicorn/error.log
```

### 2. Supervisor ile Gunicorn

```ini
# /etc/supervisor/conf.d/gunicorn.conf
[program:gunicorn]
command=/path/to/venv/bin/gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4 \
    --timeout 120
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/gunicorn/gunicorn.log
```

### 3. Systemd Service (Alternatif)

```ini
# /etc/systemd/system/gunicorn.service
[Unit]
Description=gunicorn daemon
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/project
ExecStart=/path/to/venv/bin/gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 📈 Monitoring ve Backup

### 1. Monitoring Araçları

- **Sentry** (Error tracking)
- **New Relic** (Performance monitoring)
- **Datadog** (Infrastructure monitoring)
- **Prometheus + Grafana** (Self-hosted)

### 2. Backup Stratejisi

**Database Backup:**
- Günlük full backup
- Haftalık arşivleme
- Aylık uzun süreli arşivleme

**Media Files Backup:**
- S3 versioning aktif
- Cross-region replication
- Düzenli snapshot'lar

### 3. Health Check Endpoint

```python
# config/urls.py
from django.http import JsonResponse

def health_check(request):
    return JsonResponse({
        'status': 'healthy',
        'database': 'connected',
        'cache': 'connected',
    })
```

---

## ✅ Deployment Checklist

### Pre-Deployment

- [ ] SECRET_KEY güçlü ve benzersiz
- [ ] DEBUG=False
- [ ] ALLOWED_HOSTS production domain'leri içeriyor
- [ ] Database migration'ları hazır
- [ ] Static files collect edildi
- [ ] Environment variables ayarlandı
- [ ] SSL sertifikası hazır
- [ ] Backup stratejisi belirlendi

### Security

- [ ] SECURE_SSL_REDIRECT=True
- [ ] SESSION_COOKIE_SECURE=True
- [ ] CSRF_COOKIE_SECURE=True
- [ ] SECURE_HSTS_SECONDS ayarlandı (SSL hazır olduktan sonra)
- [ ] Database şifreleri güçlü
- [ ] Redis şifresi ayarlandı
- [ ] .env dosyası Git'e commit edilmedi

### Performance

- [ ] Database connection pooling aktif
- [ ] Redis cache aktif
- [ ] Static files CDN'de veya optimize edildi
- [ ] Media files object storage'da
- [ ] Gunicorn worker sayısı optimize edildi
- [ ] Celery worker'lar çalışıyor

### Monitoring

- [ ] Logging yapılandırıldı
- [ ] Error tracking (Sentry) aktif
- [ ] Health check endpoint hazır
- [ ] Backup otomasyonu kuruldu
- [ ] Monitoring araçları yapılandırıldı

### Post-Deployment

- [ ] Site HTTPS üzerinden erişilebilir
- [ ] Tüm sayfalar çalışıyor
- [ ] Database bağlantısı başarılı
- [ ] Email gönderimi test edildi
- [ ] Celery görevleri çalışıyor
- [ ] Static ve media dosyaları yükleniyor
- [ ] Performance testleri yapıldı

---

## 🚨 Önemli Notlar

1. **SECRET_KEY'i asla Git'e commit etmeyin!**
2. **DEBUG=False olmadan production'a deploy etmeyin!**
3. **SSL sertifikası olmadan HSTS aktifleştirmeyin!**
4. **Database backup'larını düzenli alın!**
5. **Environment variables'ı güvenli tutun!**
6. **Production'da console email backend kullanmayın!**
7. **Log dosyalarını düzenli temizleyin!**
8. **Güvenlik güncellemelerini takip edin!**

---

## 📚 Ek Kaynaklar

- [Django Security Best Practices](https://docs.djangoproject.com/en/stable/topics/security/)
- [Django Deployment Checklist](https://docs.djangoproject.com/en/stable/howto/deployment/checklist/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Gunicorn Documentation](https://docs.gunicorn.org/)
- [Celery Best Practices](https://docs.celeryproject.org/en/stable/userguide/optimizing.html)

---

**Son Güncelleme:** 2026-01-XX
**Versiyon:** 1.0.0




Bu doküman, Django SaaS 2026 projesinin production ortamına deploy edilmesi için gerekli tüm güvenlik ve performans ayarlarını içermektedir.

## 📋 İçindekiler

1. [Güvenlik Ayarları](#güvenlik-ayarları)
2. [Environment Variables (.env)](#environment-variables-env)
3. [SSL/HTTPS Yapılandırması](#sslhttps-yapılandırması)
4. [Database Ayarları](#database-ayarları)
5. [Static ve Media Dosyaları](#static-ve-media-dosyaları)
6. [Email Yapılandırması](#email-yapılandırması)
7. [Redis ve Cache Yapılandırması](#redis-ve-cache-yapılandırması)
8. [Celery Yapılandırması](#celery-yapılandırması)
9. [Logging Yapılandırması](#logging-yapılandırması)
10. [Web Server Yapılandırması](#web-server-yapılandırması)
11. [Monitoring ve Backup](#monitoring-ve-backup)
12. [Deployment Checklist](#deployment-checklist)

---

## 🔒 Güvenlik Ayarları

### 1. SECRET_KEY

**ÖNEMLİ:** Production'da mutlaka güçlü ve benzersiz bir SECRET_KEY kullanın!

```bash
# Python ile güçlü SECRET_KEY oluşturma
python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
```

**Minimum Gereksinimler:**
- En az 50 karakter
- En az 5 benzersiz karakter
- Rastgele ve tahmin edilemez olmalı
- `django-insecure-` ile başlamamalı

### 2. DEBUG Modu

**Production'da DEBUG mutlaka `False` olmalıdır!**

```python
# config/settings.py
DEBUG = False  # Production için
```

**Neden Önemli:**
- DEBUG=True olduğunda hassas bilgiler (database şifreleri, SECRET_KEY vb.) hata sayfalarında görünebilir
- Performans sorunlarına neden olur
- Güvenlik açıkları oluşturur

### 3. ALLOWED_HOSTS

Production domain'lerinizi mutlaka ekleyin:

```python
# config/settings.py veya .env
ALLOWED_HOSTS = [
    'yourdomain.com',
    'www.yourdomain.com',
    'api.yourdomain.com',
    # Tenant domain'leri
    'tenant1.yourdomain.com',
    'tenant2.yourdomain.com',
]
```

**Not:** Django Tenants kullanıldığı için her tenant domain'ini ayrı ayrı eklemeniz gerekir.

---

## 🔐 SSL/HTTPS Yapılandırması

### 1. SSL Sertifikası

Production'da mutlaka SSL sertifikası kullanın:
- **Let's Encrypt** (Ücretsiz, önerilen)
- **Cloudflare** (CDN + SSL)
- **Ticari SSL Sertifikaları**

### 2. Django SSL Ayarları

Aşağıdaki ayarlar `config/settings.py` dosyasında `DEBUG=False` olduğunda otomatik aktif olur:

```python
# config/settings.py (zaten mevcut)
if not DEBUG:
    SECURE_SSL_REDIRECT = True  # HTTP'den HTTPS'ye yönlendirme
    SESSION_COOKIE_SECURE = True  # Session cookie'leri sadece HTTPS üzerinden
    CSRF_COOKIE_SECURE = True  # CSRF cookie'leri sadece HTTPS üzerinden
    SECURE_BROWSER_XSS_FILTER = True  # XSS koruması
    SECURE_CONTENT_TYPE_NOSNIFF = True  # MIME type sniffing koruması
    X_FRAME_OPTIONS = 'DENY'  # Clickjacking koruması
```

### 3. HSTS (HTTP Strict Transport Security)

**ÖNEMLİ:** HSTS'yi dikkatli kullanın! Yanlış yapılandırma site erişimini engelleyebilir.

```python
# config/settings.py
if not DEBUG:
    # HSTS ayarları (SSL sertifikanız hazır olduktan sonra aktif edin!)
    SECURE_HSTS_SECONDS = 31536000  # 1 yıl (sadece SSL hazır olduktan sonra!)
    SECURE_HSTS_INCLUDE_SUBDOMAINS = True  # Alt domain'leri de dahil et
    SECURE_HSTS_PRELOAD = True  # Browser preload listesine ekleme için
```

**HSTS Aktifleştirme Adımları:**
1. SSL sertifikanızın çalıştığını doğrulayın
2. Tüm sayfaların HTTPS üzerinden erişilebildiğini test edin
3. Önce küçük bir değerle başlayın (örn: 3600 = 1 saat)
4. Sorun yoksa artırın (31536000 = 1 yıl)

---

## 📝 Environment Variables (.env)

Production için `.env` dosyası örneği:

```bash
# .env.production

# Django Ayarları
DEBUG=False
SECRET_KEY=your-super-secret-key-minimum-50-characters-long-and-random
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,api.yourdomain.com

# Database
POSTGRES_DB=saas_production_db
POSTGRES_USER=saas_prod_user
POSTGRES_PASSWORD=strong-database-password-here
POSTGRES_HOST=db.example.com
POSTGRES_PORT=5432

# Redis
REDIS_URL=redis://redis.example.com:6379/0

# Celery
CELERY_BROKER_URL=redis://redis.example.com:6379/1
CELERY_RESULT_BACKEND=redis://redis.example.com:6379/2

# Email (SMTP)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-specific-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com

# Site Bilgileri
SITE_NAME=Your SaaS Platform
SITE_URL=https://yourdomain.com
ADMIN_URL=admin/

# Stripe (Ödeme)
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Subscription Ayarları
TRIAL_PERIOD_DAYS=14
SUBSCRIPTION_GRACE_PERIOD_DAYS=3
```

**Güvenlik Notları:**
- `.env` dosyasını **ASLA** Git'e commit etmeyin!
- `.gitignore` dosyasına `.env` ekleyin
- Production sunucuda `.env` dosyasına sadece gerekli kullanıcılar erişebilmeli (chmod 600)
- Farklı ortamlar için farklı `.env` dosyaları kullanın (`.env.production`, `.env.staging`)

---

## 🗄️ Database Ayarları

### 1. PostgreSQL Yapılandırması

```python
# config/settings.py (zaten mevcut)
DATABASES = {
    'default': {
        'ENGINE': 'django_tenants.postgresql_backend',
        'NAME': env('POSTGRES_DB', default='saas_db'),
        'USER': env('POSTGRES_USER', default='saas_user'),
        'PASSWORD': env('POSTGRES_PASSWORD', default='saas_password_2026'),
        'HOST': env('POSTGRES_HOST', default='db'),
        'PORT': env('POSTGRES_PORT', default='5432'),
        'OPTIONS': {
            'connect_timeout': 10,
        },
        'CONN_MAX_AGE': 600,  # Connection pooling için
    }
}
```

### 2. Database Backup

**ÖNEMLİ:** Düzenli database backup'ları alın!

```bash
# Otomatik backup script örneği
#!/bin/bash
BACKUP_DIR="/backups/database"
DATE=$(date +%Y%m%d_%H%M%S)
pg_dump -h db.example.com -U saas_prod_user saas_production_db > "$BACKUP_DIR/backup_$DATE.sql"
# Eski backup'ları sil (30 günden eski)
find $BACKUP_DIR -name "backup_*.sql" -mtime +30 -delete
```

### 3. Database Connection Pooling

Production'da connection pooling kullanın:
- **PgBouncer** (Önerilen)
- **Django CONN_MAX_AGE** (basit çözüm)

---

## 📁 Static ve Media Dosyaları

### 1. Static Files

```python
# config/settings.py (zaten mevcut)
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR / 'staticfiles'
```

**Collectstatic:**
```bash
python manage.py collectstatic --noinput
```

### 2. Media Files

**ÖNEMLİ:** Media dosyalarını CDN veya object storage'a taşıyın!

**Seçenekler:**
- **AWS S3** + CloudFront
- **Google Cloud Storage**
- **Azure Blob Storage**
- **DigitalOcean Spaces**

**Django S3 Örneği:**
```python
# pip install django-storages boto3
INSTALLED_APPS = [
    ...
    'storages',
]

# AWS S3 Ayarları
AWS_ACCESS_KEY_ID = env('AWS_ACCESS_KEY_ID')
AWS_SECRET_ACCESS_KEY = env('AWS_SECRET_ACCESS_KEY')
AWS_STORAGE_BUCKET_NAME = env('AWS_STORAGE_BUCKET_NAME')
AWS_S3_REGION_NAME = env('AWS_S3_REGION_NAME', default='us-east-1')
AWS_S3_CUSTOM_DOMAIN = f'{AWS_STORAGE_BUCKET_NAME}.s3.amazonaws.com'
AWS_DEFAULT_ACL = 'public-read'
AWS_S3_OBJECT_PARAMETERS = {
    'CacheControl': 'max-age=86400',
}

# Media files için S3
DEFAULT_FILE_STORAGE = 'storages.backends.s3boto3.S3Boto3Storage'
MEDIA_URL = f'https://{AWS_S3_CUSTOM_DOMAIN}/'
```

### 3. Nginx Yapılandırması

```nginx
# /etc/nginx/sites-available/yourdomain.com
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL ayarları
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Static files
    location /static/ {
        alias /path/to/your/project/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files (S3 kullanmıyorsanız)
    location /media/ {
        alias /path/to/your/project/media/;
        expires 7d;
        add_header Cache-Control "public";
    }

    # Django uygulaması
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
    }
}
```

---

## 📧 Email Yapılandırması

### 1. SMTP Ayarları

```python
# config/settings.py (zaten mevcut)
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = env('EMAIL_HOST', default='smtp.gmail.com')
EMAIL_PORT = env.int('EMAIL_PORT', default=587)
EMAIL_USE_TLS = env.bool('EMAIL_USE_TLS', default=True)
EMAIL_HOST_USER = env('EMAIL_HOST_USER', default='')
EMAIL_HOST_PASSWORD = env('EMAIL_HOST_PASSWORD', default='')
DEFAULT_FROM_EMAIL = env('DEFAULT_FROM_EMAIL', default='noreply@saas2026.com')
```

### 2. Email Servis Sağlayıcıları

**Gmail (Gmail API):**
- App-specific password kullanın
- 2FA aktif olmalı

**SendGrid:**
```python
EMAIL_BACKEND = 'sendgrid_backend.SendgridBackend'
SENDGRID_API_KEY = env('SENDGRID_API_KEY')
```

**Amazon SES:**
```python
EMAIL_BACKEND = 'django_ses.SESBackend'
AWS_SES_REGION_NAME = 'us-east-1'
AWS_SES_REGION_ENDPOINT = 'email.us-east-1.amazonaws.com'
```

---

## 🔴 Redis ve Cache Yapılandırması

### 1. Redis Kurulumu

```bash
# Ubuntu/Debian
sudo apt-get install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 2. Django Cache Yapılandırması

```python
# config/settings.py
CACHES = {
    'default': {
        'BACKEND': 'django_redis.cache.RedisCache',
        'LOCATION': env('REDIS_URL', default='redis://127.0.0.1:6379/0'),
        'OPTIONS': {
            'CLIENT_CLASS': 'django_redis.client.DefaultClient',
        },
        'KEY_PREFIX': 'saas2026',
        'TIMEOUT': 300,
    }
}

# Session için Redis
SESSION_ENGINE = 'django.contrib.sessions.backends.cache'
SESSION_CACHE_ALIAS = 'default'
```

### 3. Redis Güvenlik

```bash
# /etc/redis/redis.conf
requirepass your-strong-redis-password
bind 127.0.0.1  # Sadece localhost'tan erişim
```

---

## ⚙️ Celery Yapılandırması

### 1. Celery Worker

```bash
# Production için Celery worker başlatma
celery -A config worker --loglevel=info --concurrency=4

# Supervisor ile otomatik başlatma (önerilen)
```

### 2. Supervisor Yapılandırması

```ini
# /etc/supervisor/conf.d/celery.conf
[program:celery]
command=/path/to/venv/bin/celery -A config worker --loglevel=info
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/celery/worker.log
```

### 3. Celery Beat (Zamanlanmış Görevler)

```ini
# /etc/supervisor/conf.d/celery-beat.conf
[program:celery-beat]
command=/path/to/venv/bin/celery -A config beat --loglevel=info
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/celery/beat.log
```

---

## 📊 Logging Yapılandırması

```python
# config/settings.py (zaten mevcut)
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '{levelname} {asctime} {module} {process:d} {thread:d} {message}',
            'style': '{',
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'verbose',
        },
        'file': {
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': '/var/log/django/django.log',
            'maxBytes': 1024 * 1024 * 10,  # 10 MB
            'backupCount': 5,
            'formatter': 'verbose',
        },
    },
    'root': {
        'handlers': ['console', 'file'],
        'level': 'INFO',  # Production için INFO, DEBUG değil!
    },
    'loggers': {
        'django': {
            'handlers': ['console', 'file'],
            'level': 'INFO',
            'propagate': False,
        },
    },
}
```

**Log Rotation:**
```bash
# Logrotate yapılandırması
# /etc/logrotate.d/django
/var/log/django/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

---

## 🌐 Web Server Yapılandırması

### 1. Gunicorn

```bash
# Gunicorn kurulumu
pip install gunicorn

# Gunicorn başlatma
gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4 \
    --worker-class sync \
    --timeout 120 \
    --max-requests 1000 \
    --max-requests-jitter 50 \
    --access-logfile /var/log/gunicorn/access.log \
    --error-logfile /var/log/gunicorn/error.log
```

### 2. Supervisor ile Gunicorn

```ini
# /etc/supervisor/conf.d/gunicorn.conf
[program:gunicorn]
command=/path/to/venv/bin/gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4 \
    --timeout 120
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/gunicorn/gunicorn.log
```

### 3. Systemd Service (Alternatif)

```ini
# /etc/systemd/system/gunicorn.service
[Unit]
Description=gunicorn daemon
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/project
ExecStart=/path/to/venv/bin/gunicorn config.wsgi:application \
    --bind 127.0.0.1:8000 \
    --workers 4
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 📈 Monitoring ve Backup

### 1. Monitoring Araçları

- **Sentry** (Error tracking)
- **New Relic** (Performance monitoring)
- **Datadog** (Infrastructure monitoring)
- **Prometheus + Grafana** (Self-hosted)

### 2. Backup Stratejisi

**Database Backup:**
- Günlük full backup
- Haftalık arşivleme
- Aylık uzun süreli arşivleme

**Media Files Backup:**
- S3 versioning aktif
- Cross-region replication
- Düzenli snapshot'lar

### 3. Health Check Endpoint

```python
# config/urls.py
from django.http import JsonResponse

def health_check(request):
    return JsonResponse({
        'status': 'healthy',
        'database': 'connected',
        'cache': 'connected',
    })
```

---

## ✅ Deployment Checklist

### Pre-Deployment

- [ ] SECRET_KEY güçlü ve benzersiz
- [ ] DEBUG=False
- [ ] ALLOWED_HOSTS production domain'leri içeriyor
- [ ] Database migration'ları hazır
- [ ] Static files collect edildi
- [ ] Environment variables ayarlandı
- [ ] SSL sertifikası hazır
- [ ] Backup stratejisi belirlendi

### Security

- [ ] SECURE_SSL_REDIRECT=True
- [ ] SESSION_COOKIE_SECURE=True
- [ ] CSRF_COOKIE_SECURE=True
- [ ] SECURE_HSTS_SECONDS ayarlandı (SSL hazır olduktan sonra)
- [ ] Database şifreleri güçlü
- [ ] Redis şifresi ayarlandı
- [ ] .env dosyası Git'e commit edilmedi

### Performance

- [ ] Database connection pooling aktif
- [ ] Redis cache aktif
- [ ] Static files CDN'de veya optimize edildi
- [ ] Media files object storage'da
- [ ] Gunicorn worker sayısı optimize edildi
- [ ] Celery worker'lar çalışıyor

### Monitoring

- [ ] Logging yapılandırıldı
- [ ] Error tracking (Sentry) aktif
- [ ] Health check endpoint hazır
- [ ] Backup otomasyonu kuruldu
- [ ] Monitoring araçları yapılandırıldı

### Post-Deployment

- [ ] Site HTTPS üzerinden erişilebilir
- [ ] Tüm sayfalar çalışıyor
- [ ] Database bağlantısı başarılı
- [ ] Email gönderimi test edildi
- [ ] Celery görevleri çalışıyor
- [ ] Static ve media dosyaları yükleniyor
- [ ] Performance testleri yapıldı

---

## 🚨 Önemli Notlar

1. **SECRET_KEY'i asla Git'e commit etmeyin!**
2. **DEBUG=False olmadan production'a deploy etmeyin!**
3. **SSL sertifikası olmadan HSTS aktifleştirmeyin!**
4. **Database backup'larını düzenli alın!**
5. **Environment variables'ı güvenli tutun!**
6. **Production'da console email backend kullanmayın!**
7. **Log dosyalarını düzenli temizleyin!**
8. **Güvenlik güncellemelerini takip edin!**

---

## 📚 Ek Kaynaklar

- [Django Security Best Practices](https://docs.djangoproject.com/en/stable/topics/security/)
- [Django Deployment Checklist](https://docs.djangoproject.com/en/stable/howto/deployment/checklist/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Gunicorn Documentation](https://docs.gunicorn.org/)
- [Celery Best Practices](https://docs.celeryproject.org/en/stable/userguide/optimizing.html)

---

**Son Güncelleme:** 2026-01-XX
**Versiyon:** 1.0.0




