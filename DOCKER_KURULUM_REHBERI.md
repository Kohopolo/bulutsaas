# Docker ile Proje Kurulum Rehberi

## 📋 Genel Bakış

Bu rehber, Bulut Acente Yönetim Sistemi'ni Docker ve Docker Compose kullanarak nasıl kuracağınızı adım adım açıklar.

---

## 🎯 Gereksinimler

### Sistem Gereksinimleri

- **RAM**: Minimum 4GB (8GB önerilir)
- **CPU**: Minimum 2 vCPU (4 vCPU önerilir)
- **Disk**: Minimum 60GB SSD
- **İşletim Sistemi**: Ubuntu 22.04 LTS (veya Docker destekleyen herhangi bir Linux)

### Yazılım Gereksinimleri

- **Docker**: 24.0+ 
- **Docker Compose**: 2.20+
- **Git**: Projeyi çekmek için

---

## 🚀 Adım 1: VPS Hazırlığı

### 1.1. VPS Oluşturma

1. **Hostinger/Digital Ocean/Hetzner**'dan VPS oluşturun
2. **Ubuntu 22.04 LTS** seçin
3. **Boş Ubuntu** veya **Docker** seçeneğini seçin
4. SSH ile bağlanın

### 1.2. Sistem Güncellemesi

```bash
# Sistem güncellemesi
sudo apt update && sudo apt upgrade -y

# Temel araçlar
sudo apt install -y curl wget git
```

---

## 🐳 Adım 2: Docker Kurulumu

### 2.1. Docker Kurulumu (Eğer Kurulu Değilse)

```bash
# Docker kurulum script'ini indir ve çalıştır
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Docker servisini başlat ve aktifleştir
sudo systemctl start docker
sudo systemctl enable docker

# Docker versiyonunu kontrol et
docker --version
docker compose version
```

### 2.2. Docker Kullanıcı Ayarları

```bash
# Kullanıcıyı docker grubuna ekle (sudo gerektirmeden Docker kullanmak için)
sudo usermod -aG docker $USER

# Yeni grup ayarlarını aktifleştir (logout/login gerekebilir)
newgrp docker

# Docker'ın çalıştığını test et
docker run hello-world
```

---

## 📦 Adım 3: Proje Dosyalarını Hazırlama

### 3.1. Proje Dizini Oluşturma

```bash
# Proje dizini oluştur
sudo mkdir -p /var/www/bulutacente
sudo chown $USER:$USER /var/www/bulutacente
cd /var/www/bulutacente
```

### 3.2. Projeyi Git ile Çekme

```bash
# Git ile projeyi çek
git clone YOUR_REPOSITORY_URL .

# Veya proje dosyalarını SCP ile yükleyin
# scp -r /local/path/to/project/* root@YOUR_VPS_IP:/var/www/bulutacente/
```

### 3.3. Gerekli Dosyaları Kontrol Et

Projenizde şu dosyaların olması gerekir:

```bash
# Kontrol et
ls -la

# Olması gerekenler:
# - Dockerfile
# - docker-compose.prod.yml
# - env.example
# - requirements.txt
# - manage.py
# - apps/core/management/commands/wait_for_db.py (Database bekleme komutu)
```

**Not**: `.dockerignore` dosyası opsiyoneldir ama önerilir. Eğer yoksa oluşturabilirsiniz:

```bash
# .dockerignore dosyası oluştur
cat > .dockerignore << EOF
.git
.gitignore
.env
*.pyc
__pycache__
*.log
*.sql
*.sql.gz
backupdatabase/
venv/
env/
.venv/
node_modules/
.DS_Store
*.swp
*.swo
*~
EOF
```

---

## ⚙️ Adım 4: Environment Variables (.env) Dosyası

### 4.1. .env Dosyası Oluşturma

```bash
# env.example'dan .env oluştur
cp env.example .env

# .env dosyasını düzenle
nano .env
```

### 4.2. .env Dosyası İçeriği

```bash
# Django Ayarları
DEBUG=False
SECRET_KEY=your-super-secret-key-here-change-this-in-production
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com,YOUR_VPS_IP

# Database (PostgreSQL - Container içinde)
DATABASE_URL=postgresql://saas_user:GÜÇLÜ_ŞİFRE_BURAYA@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=GÜÇLÜ_ŞİFRE_BURAYA
POSTGRES_HOST=db
POSTGRES_PORT=5432

# Redis (Container içinde)
REDIS_URL=redis://redis:6379/0
REDIS_PASSWORD=GÜÇLÜ_REDIS_ŞİFRE_BURAYA

# Celery
CELERY_BROKER_URL=redis://:GÜÇLÜ_REDIS_ŞİFRE_BURAYA@redis:6379/0
CELERY_RESULT_BACKEND=redis://:GÜÇLÜ_REDIS_ŞİFRE_BURAYA@redis:6379/0

# Email Ayarları (Gmail örneği)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com

# Timezone
TIME_ZONE=Europe/Istanbul

# Media & Static
MEDIA_ROOT=/app/media
STATIC_ROOT=/app/staticfiles

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

### 4.3. SECRET_KEY Oluşturma

```bash
# Django SECRET_KEY oluştur
python3 -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"

# Çıktıyı kopyalayıp .env dosyasındaki SECRET_KEY'e yapıştırın
```

---

## 🐳 Adım 5: Docker Compose ile Servisleri Başlatma

### 5.1. Docker Image'ları Build Etme

```bash
# Production docker-compose dosyası ile build
docker compose -f docker-compose.prod.yml build

# Build işlemi biraz zaman alabilir (ilk seferinde)
```

### 5.2. Servisleri Başlatma (Sadece Database ve Redis)

```bash
# Önce sadece database ve redis'i başlat
docker compose -f docker-compose.prod.yml up -d db redis

# Servislerin hazır olmasını bekle (30-60 saniye)
docker compose -f docker-compose.prod.yml ps

# Database'in hazır olduğunu kontrol et
docker compose -f docker-compose.prod.yml exec db pg_isready -U saas_user
```

### 5.3. Database Migration (Django-Tenants için)

```bash
# Database'in hazır olmasını bekle (wait_for_db komutu otomatik çalışır ama manuel kontrol için)
docker compose -f docker-compose.prod.yml exec db pg_isready -U saas_user -d saas_db

# Shared schema migration (public schema)
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas --shared

# Tenant schema'ları için migration (varsa)
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas

# Migration durumunu kontrol et
docker compose -f docker-compose.prod.yml run --rm web python manage.py showmigrations
```

**Not**: `docker-compose.prod.yml` dosyasında `web` servisi başlatıldığında otomatik olarak:
1. `wait_for_db` komutu çalışır (database hazır olana kadar bekler)
2. Shared schema migration çalıştırılır
3. Tenant schema migration çalıştırılır
4. Static files toplanır
5. Gunicorn başlatılır

Bu adımları manuel olarak çalıştırmak isterseniz yukarıdaki komutları kullanabilirsiniz.

### 5.4. Static Files Toplama

```bash
# Static files topla
docker compose -f docker-compose.prod.yml run --rm web python manage.py collectstatic --noinput
```

### 5.5. Superuser Oluşturma

```bash
# Public schema için superuser oluştur
docker compose -f docker-compose.prod.yml run --rm web python manage.py createsuperuser --schema=public

# Sorular:
# Username: admin
# Email: admin@example.com
# Password: GÜÇLÜ_ŞİFRE_BURAYA
```

### 5.6. Tüm Servisleri Başlatma

```bash
# Tüm servisleri başlat (web, celery, celery-beat)
docker compose -f docker-compose.prod.yml up -d

# Servis durumunu kontrol et
docker compose -f docker-compose.prod.yml ps

# Logları kontrol et
docker compose -f docker-compose.prod.yml logs -f
```

---

## 🌐 Adım 6: Nginx Reverse Proxy Kurulumu

### 6.1. Nginx Kurulumu (Host Üzerinde)

```bash
# Nginx kurulumu
sudo apt install -y nginx

# Nginx config dosyası oluştur
sudo nano /etc/nginx/sites-available/bulutacente
```

### 6.2. Nginx Configuration

```nginx
# /etc/nginx/sites-available/bulutacente

upstream bulutacente_app {
    server 127.0.0.1:8000;
    keepalive 64;
}

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    # Logs
    access_log /var/log/nginx/bulutacente_access.log;
    error_log /var/log/nginx/bulutacente_error.log;

    # Client max body size (dosya yükleme için)
    client_max_body_size 100M;

    # Static files
    location /static/ {
        alias /var/www/bulutacente/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files
    location /media/ {
        alias /var/www/bulutacente/media/;
        expires 7d;
        add_header Cache-Control "public";
    }

    # Django application
    location / {
        proxy_pass http://bulutacente_app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
        
        # WebSocket support (gerekirse)
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

### 6.3. Nginx Site'ı Aktifleştirme

```bash
# Site'ı aktifleştir
sudo ln -s /etc/nginx/sites-available/bulutacente /etc/nginx/sites-enabled/

# Varsayılan site'ı kaldır (opsiyonel)
sudo rm /etc/nginx/sites-enabled/default

# Nginx config'i test et
sudo nginx -t

# Nginx'i yeniden başlat
sudo systemctl restart nginx
sudo systemctl enable nginx
```

---

## 🔒 Adım 7: SSL Sertifikası (Let's Encrypt)

### 7.1. Certbot Kurulumu

```bash
# Certbot kurulumu
sudo apt install -y certbot python3-certbot-nginx
```

### 7.2. SSL Sertifikası Oluşturma

```bash
# SSL sertifikası oluştur ve Nginx'i otomatik yapılandır
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Sorular:
# Email: your-email@example.com
# Terms: A (Agree)
# Share email: N (No)
# Redirect HTTP to HTTPS: 2 (Redirect)
```

### 7.3. Otomatik Yenileme

```bash
# Certbot otomatik yenileme test et
sudo certbot renew --dry-run

# Cron job zaten otomatik kurulur
```

---

## 🏢 Adım 8: İlk Tenant Oluşturma

### 8.1. Tenant Oluşturma (Management Command ile)

```bash
# Tenant oluştur
docker compose -f docker-compose.prod.yml exec web python manage.py create_test_package_tenant

# Veya manuel olarak
docker compose -f docker-compose.prod.yml exec web python manage.py shell
```

### 8.2. Tenant Oluşturma (Python Shell ile)

```python
# Python shell içinde
from apps.tenants.models import Tenant, Domain
from apps.packages.models import Package

# Package oluştur (eğer yoksa)
package, created = Package.objects.get_or_create(
    name='Test Package',
    defaults={
        'is_active': True,
        'price': 0,
    }
)

# Tenant oluştur
tenant = Tenant.objects.create(
    schema_name='tenant_test-otel',
    name='Test Otel',
    package=package,
    is_active=True
)

# Domain oluştur
Domain.objects.create(
    domain='test-otel.yourdomain.com',
    tenant=tenant,
    is_primary=True
)

# Tenant user oluştur
from apps.tenant_apps.core.models import TenantUser
from django.contrib.auth import get_user_model
User = get_user_model()

user = User.objects.create_user(
    username='admin',
    email='admin@test-otel.com',
    password='GÜÇLÜ_ŞİFRE_BURAYA'
)

TenantUser.objects.create(
    user=user,
    tenant=tenant,
    is_active=True
)
```

---

## 🔍 Adım 9: Servisleri Kontrol Etme

### 9.1. Servis Durumu

```bash
# Tüm servislerin durumunu kontrol et
docker compose -f docker-compose.prod.yml ps

# Beklenen çıktı:
# NAME                      STATUS          PORTS
# bulutacente_db            Up (healthy)    127.0.0.1:5432->5432/tcp
# bulutacente_redis         Up (healthy)    127.0.0.1:6379->6379/tcp
# bulutacente_web           Up (healthy)    127.0.0.1:8000->8000/tcp
# bulutacente_celery_worker Up              8000/tcp
# bulutacente_celery_beat   Up              8000/tcp
```

### 9.2. Log Kontrolü

```bash
# Tüm servislerin loglarını görüntüle
docker compose -f docker-compose.prod.yml logs -f

# Belirli bir servisin loglarını görüntüle
docker compose -f docker-compose.prod.yml logs -f web
docker compose -f docker-compose.prod.yml logs -f celery_worker
docker compose -f docker-compose.prod.yml logs -f celery_beat
```

### 9.3. Health Check

```bash
# Web servisi health check (Docker container içinden)
docker compose -f docker-compose.prod.yml exec web curl -f http://localhost:8000/health/

# Veya host üzerinden (Nginx üzerinden)
curl http://localhost/health/

# Veya browser'dan
# http://YOUR_VPS_IP/health/
# https://yourdomain.com/health/ (SSL kurulduysa)
```

**Not**: Health check endpoint'i (`/health/`) Django URL yapılandırmasında tanımlı olmalıdır. Eğer yoksa, `config/urls.py` veya `config/urls_public.py` dosyasına ekleyin:

```python
# config/urls_public.py veya config/urls.py
from django.http import JsonResponse
from django.urls import path

def health_check(request):
    return JsonResponse({'status': 'ok'}, status=200)

urlpatterns = [
    # ... diğer URL'ler
    path('health/', health_check, name='health_check'),
]
```

---

## 🔄 Adım 10: Servis Yönetimi

### 10.1. Servisleri Başlatma/Durdurma

```bash
# Tüm servisleri başlat
docker compose -f docker-compose.prod.yml up -d

# Tüm servisleri durdur
docker compose -f docker-compose.prod.yml down

# Servisleri durdur ve volume'ları sil (DİKKAT: Veri kaybı!)
docker compose -f docker-compose.prod.yml down -v

# Belirli bir servisi yeniden başlat
docker compose -f docker-compose.prod.yml restart web
docker compose -f docker-compose.prod.yml restart celery_worker
docker compose -f docker-compose.prod.yml restart celery_beat
```

### 10.2. Servisleri Güncelleme

```bash
# Yeni kod çek
git pull

# Docker image'ları yeniden build et
docker compose -f docker-compose.prod.yml build

# Servisleri yeniden başlat
docker compose -f docker-compose.prod.yml up -d

# Migration çalıştır (gerekirse)
docker compose -f docker-compose.prod.yml exec web python manage.py migrate_schemas --shared
docker compose -f docker-compose.prod.yml exec web python manage.py migrate_schemas

# Static files topla (gerekirse)
docker compose -f docker-compose.prod.yml exec web python manage.py collectstatic --noinput
```

---

## 📊 Adım 11: Monitoring ve Logging

### 11.1. Docker Stats

```bash
# Container kaynak kullanımını görüntüle
docker stats

# Belirli container'ları görüntüle
docker stats bulutacente_web bulutacente_db bulutacente_redis
```

### 11.2. Log Yönetimi

```bash
# Log dosyalarını görüntüle
docker compose -f docker-compose.prod.yml logs --tail=100 web

# Log dosyalarını temizle (DİKKAT: Log kaybı!)
docker compose -f docker-compose.prod.yml logs --tail=0 -f web > /dev/null
```

---

## 🔧 Adım 12: Sorun Giderme

### 12.1. Servisler Çalışmıyor

```bash
# Servis durumunu kontrol et
docker compose -f docker-compose.prod.yml ps

# Logları kontrol et
docker compose -f docker-compose.prod.yml logs web

# Container'ı yeniden başlat
docker compose -f docker-compose.prod.yml restart web
```

### 12.2. Database Bağlantı Sorunu

```bash
# Database container'ının çalıştığını kontrol et
docker compose -f docker-compose.prod.yml ps db

# Database'e bağlan
docker compose -f docker-compose.prod.yml exec db psql -U saas_user -d saas_db

# Django shell'den database'e bağlan
docker compose -f docker-compose.prod.yml exec web python manage.py dbshell
```

### 12.3. Static Files Sorunu

```bash
# Static files'ı yeniden topla
docker compose -f docker-compose.prod.yml exec web python manage.py collectstatic --noinput

# Static files dizinini kontrol et
ls -la /var/www/bulutacente/staticfiles/
```

### 12.4. Permission Sorunları

```bash
# Dosya izinlerini düzelt
sudo chown -R $USER:$USER /var/www/bulutacente
sudo chmod -R 755 /var/www/bulutacente

# Media dizini izinleri
sudo chmod -R 775 /var/www/bulutacente/media

# Static files dizini izinleri
sudo chmod -R 755 /var/www/bulutacente/staticfiles
```

### 12.5. Container Logları ve Debug

```bash
# Tüm container loglarını görüntüle
docker compose -f docker-compose.prod.yml logs

# Belirli bir container'ın loglarını görüntüle
docker compose -f docker-compose.prod.yml logs web
docker compose -f docker-compose.prod.yml logs db
docker compose -f docker-compose.prod.yml logs redis

# Son 100 satır log
docker compose -f docker-compose.prod.yml logs --tail=100 web

# Canlı log takibi
docker compose -f docker-compose.prod.yml logs -f web

# Container içine gir (debug için)
docker compose -f docker-compose.prod.yml exec web sh
docker compose -f docker-compose.prod.yml exec db psql -U saas_user -d saas_db
```

### 12.6. Database Backup ve Restore

```bash
# Database backup (container içinden)
docker compose -f docker-compose.prod.yml exec db pg_dump -U saas_user saas_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Database restore
docker compose -f docker-compose.prod.yml exec -T db psql -U saas_user saas_db < backup_20250116_120000.sql

# Veya Django management command ile
docker compose -f docker-compose.prod.yml exec web python manage.py backup_database --schema=public
```

---

## ✅ Kurulum Kontrol Listesi

- [ ] Docker kuruldu ve çalışıyor
- [ ] Proje dosyaları yüklendi
- [ ] .env dosyası oluşturuldu ve yapılandırıldı
- [ ] Docker image'ları build edildi
- [ ] Database ve Redis başlatıldı
- [ ] Migrations çalıştırıldı
- [ ] Static files toplandı
- [ ] Superuser oluşturuldu
- [ ] Tüm servisler başlatıldı
- [ ] Nginx yapılandırıldı
- [ ] SSL sertifikası kuruldu
- [ ] İlk tenant oluşturuldu
- [ ] Servisler çalışıyor ve erişilebilir

---

## 🎯 Hızlı Başlangıç Komutları

```bash
# Tüm kurulumu tek seferde yapmak için
cd /var/www/bulutacente

# 1. Docker image'ları build et
docker compose -f docker-compose.prod.yml build

# 2. Database ve Redis'i başlat
docker compose -f docker-compose.prod.yml up -d db redis

# 3. Migration çalıştır
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas --shared
docker compose -f docker-compose.prod.yml run --rm web python manage.py migrate_schemas

# 4. Static files topla
docker compose -f docker-compose.prod.yml run --rm web python manage.py collectstatic --noinput

# 5. Superuser oluştur
docker compose -f docker-compose.prod.yml run --rm web python manage.py createsuperuser --schema=public

# 6. Tüm servisleri başlat
docker compose -f docker-compose.prod.yml up -d

# 7. Servis durumunu kontrol et
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f
```

---

## 📚 Ek Kaynaklar

- [Docker Dokümantasyonu](https://docs.docker.com/)
- [Docker Compose Dokümantasyonu](https://docs.docker.com/compose/)
- [Django Deployment Guide](https://docs.djangoproject.com/en/stable/howto/deployment/)
- [Nginx Dokümantasyonu](https://nginx.org/en/docs/)

---

---

## 📝 Önemli Notlar

### Docker Compose Otomatik İşlemler

`docker-compose.prod.yml` dosyasındaki `web` servisi başlatıldığında otomatik olarak şu işlemler yapılır:

1. **Database Bekleme**: `wait_for_db` komutu database hazır olana kadar bekler
2. **Migration**: Shared ve tenant schema migration'ları çalıştırılır
3. **Static Files**: `collectstatic` komutu çalıştırılır
4. **Gunicorn**: Web sunucusu başlatılır

Bu nedenle ilk kurulumda sadece servisleri başlatmanız yeterlidir:

```bash
docker compose -f docker-compose.prod.yml up -d
```

### Volume Yönetimi

Docker Compose aşağıdaki volume'ları kullanır:

- `postgres_data`: PostgreSQL veritabanı verileri
- `redis_data`: Redis verileri
- `static_volume`: Static files (CSS, JS, images)
- `media_volume`: Kullanıcı yüklenen dosyalar (media)

Bu volume'lar Docker tarafından yönetilir ve container'lar silinse bile veriler korunur.

### Production İçin Öneriler

1. **Güvenlik**:
   - `.env` dosyasını asla Git'e commit etmeyin
   - `SECRET_KEY` ve şifreleri güçlü tutun
   - Firewall kurallarını yapılandırın (sadece 80, 443, 22 portları açık)

2. **Performans**:
   - Gunicorn worker sayısını CPU sayısına göre ayarlayın
   - Redis cache kullanın
   - Static files için CDN kullanmayı düşünün

3. **Monitoring**:
   - Log aggregation (ELK, Loki) kurun
   - Application monitoring (Sentry, New Relic) ekleyin
   - Database monitoring (pgAdmin, Grafana) yapılandırın

4. **Backup**:
   - Otomatik database backup'ları yapılandırın
   - Backup dosyalarını harici bir depolama alanına kopyalayın
   - Backup restore testleri yapın

---

**Son Güncelleme**: 2025-01-16

