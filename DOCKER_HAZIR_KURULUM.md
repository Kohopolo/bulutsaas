# Docker Hazır Sistem - Hızlı Kurulum Rehberi

## 🚀 Docker Zaten Kurulu - Direkt Proje Kurulumu

**IP:** `72.62.35.155`  
**Domain:** `bulutacente.com.tr`  
**Durum:** Docker ve Docker Compose hazır ✅

---

## 📋 ADIM 1: VPS'e Bağlanma

```bash
ssh root@72.62.35.155
```

---

## 📋 ADIM 2: Docker Kontrolü (Hızlı Test)

```bash
docker --version
```

```bash
docker compose version
```

```bash
docker ps
```

**Beklenen:** Docker çalışıyor, container yok (henüz)

---

## 📋 ADIM 3: Sistem Güncellemesi (Opsiyonel)

```bash
apt update
```

```bash
apt install -y curl wget git build-essential
```

---

## 📋 ADIM 4: Güvenlik Duvarı Yapılandırması

```bash
ufw allow OpenSSH
```

```bash
ufw allow 80/tcp
```

```bash
ufw allow 443/tcp
```

```bash
ufw enable
```

```bash
ufw status
```

---

## 📋 ADIM 5: Proje Klasörü Oluşturma

```bash
mkdir -p /var/www/bulutsaas
```

```bash
cd /var/www/bulutsaas
```

---

## 📋 ADIM 6: Projeyi Klonlama

```bash
git clone https://github.com/Kohopolo/bulutsaas.git .
```

**Eğer git yoksa:**
```bash
apt install -y git
git clone https://github.com/Kohopolo/bulutsaas.git .
```

---

## 📋 ADIM 7: Gerekli Dosyaları Kontrol Etme

```bash
ls -la Dockerfile
```

```bash
ls -la docker-compose.yml
```

```bash
ls -la requirements.txt
```

---

## 📋 ADIM 8: .env Dosyası Oluşturma

```bash
nano .env
```

**Aşağıdaki içeriği yapıştırın:**

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

**Kaydetmek için:** `Ctrl+O`, `Enter`, `Ctrl+X`

---

## 📋 ADIM 9: Secret Key Oluşturma

```bash
python3 -c "import secrets; print(secrets.token_urlsafe(50))"
```

**Çıktıyı kopyalayın ve .env dosyasındaki `SECRET_KEY=` satırına yapıştırın:**

```bash
nano .env
```

**SECRET_KEY satırını bulun, değiştirin ve kaydedin.**

---

## 📋 ADIM 10: Docker Compose Yapılandırması Kontrolü

```bash
nano docker-compose.yml
```

**Önemli:** Nginx servisini kaldırın veya yorum satırı yapın (host üzerinde Nginx kullanacağız).

**Web container port ayarı:**
```yaml
ports:
  - "127.0.0.1:8000:8000"  # Sadece localhost'tan erişilebilir
```

---

## 📋 ADIM 11: Docker Image'ları Build Etme

```bash
cd /var/www/bulutsaas
```

```bash
docker compose build
```

**Bu işlem 5-10 dakika sürebilir. Bekleyin...**

**Build durumunu kontrol edin:**
```bash
docker images
```

**Beklenen:** `bulutsaas_web` image'ı görünmeli

---

## 📋 ADIM 12: Container'ları Başlatma

```bash
docker compose up -d
```

**Container durumunu kontrol edin:**
```bash
docker compose ps
```

**Beklenen Çıktı:**
```
NAME                IMAGE               STATUS
bulutsaas_db        postgres:15-alpine  Up
bulutsaas_redis     redis:7-alpine      Up
bulutsaas_web       bulutsaas_web       Up
bulutsaas_celery    bulutsaas_web       Up
bulutsaas_celery_beat bulutsaas_web     Up
```

**Logları kontrol edin:**
```bash
docker compose logs -f
```

**Ctrl+C ile çıkın**

---

## 📋 ADIM 13: Database Migration

```bash
docker compose exec web python manage.py migrate_schemas --shared
```

**Beklenen:** Migration başarılı mesajları

```bash
docker compose exec web python manage.py migrate_schemas
```

**Beklenen:** Migration başarılı mesajları

```bash
docker compose exec web python manage.py collectstatic --noinput
```

**Beklenen:** Static files toplandı mesajları

```bash
docker compose exec web python manage.py createsuperuser
```

**Kullanıcı adı, email ve şifre girin**

---

## 📋 ADIM 14: Health Check

```bash
curl http://localhost:8000/health/
```

**Beklenen:** `OK`

**Container loglarını kontrol edin:**
```bash
docker compose logs web | tail -50
```

```bash
docker compose logs celery | tail -50
```

```bash
docker compose logs celery-beat | tail -50
```

---

## 📋 ADIM 15: Nginx Kurulumu

```bash
apt install -y nginx
```

```bash
systemctl start nginx
```

```bash
systemctl enable nginx
```

---

## 📋 ADIM 16: Nginx Site Konfigürasyonu

```bash
nano /etc/nginx/sites-available/bulutsaas
```

**Aşağıdaki içeriği yapıştırın:**

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

**Kaydetmek için:** `Ctrl+O`, `Enter`, `Ctrl+X`

---

## 📋 ADIM 17: Nginx Site'ı Aktif Etme

```bash
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/
```

```bash
rm /etc/nginx/sites-enabled/default
```

```bash
nginx -t
```

**Beklenen:** `syntax is ok` ve `test is successful`

```bash
systemctl reload nginx
```

---

## 📋 ADIM 18: Static ve Media Klasörlerini Oluşturma

```bash
mkdir -p /var/www/bulutsaas/staticfiles
```

```bash
mkdir -p /var/www/bulutsaas/media
```

```bash
chmod -R 755 /var/www/bulutsaas/staticfiles
```

```bash
chmod -R 755 /var/www/bulutsaas/media
```

**Not:** Docker volume'ları bu klasörlere mount edilmiş olmalı.

---

## 📋 ADIM 19: SSL Sertifikası (Let's Encrypt)

```bash
apt install -y certbot python3-certbot-nginx
```

```bash
certbot --nginx -d bulutacente.com.tr -d www.bulutacente.com.tr
```

**Sorular:**
- Email adresinizi girin
- Terms of Service'i kabul edin (A)
- Email paylaşımı için Y veya N
- HTTP'den HTTPS'e yönlendirme için **2** seçin

**Beklenen:** SSL sertifikası başarıyla oluşturuldu

---

## 📋 ADIM 20: SSL Otomatik Yenileme Testi

```bash
certbot renew --dry-run
```

**Beklenen:** Test başarılı

---

## 📋 ADIM 21: Django'da Domain Ekleme

```bash
docker compose exec web python manage.py shell
```

**Python shell'de aşağıdaki komutları çalıştırın:**

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

---

## 📋 ADIM 22: DNS Ayarları (Hostinger Panel)

Hostinger DNS yönetiminde şu kayıtları ekleyin:

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

## 📋 ADIM 23: Final Kontroller

```bash
docker compose ps
```

**Tüm container'lar `Up` durumunda olmalı**

```bash
curl http://bulutacente.com.tr/health/
```

**Beklenen:** `OK`

```bash
curl https://bulutacente.com.tr/health/
```

**Beklenen:** `OK`

```bash
curl https://bulutacente.com.tr/admin/
```

**Beklenen:** Admin login sayfası HTML'i

---

## ✅ Kurulum Tamamlandı!

**Web Sitesi:** https://bulutacente.com.tr  
**Admin Panel:** https://bulutacente.com.tr/admin/

---

## 🔄 Container Yönetimi Komutları

### Container'ları Yeniden Başlatma

```bash
docker compose restart
```

### Belirli Container'ı Yeniden Başlatma

```bash
docker compose restart web
```

```bash
docker compose restart celery
```

```bash
docker compose restart celery-beat
```

### Logları İzleme

```bash
docker compose logs -f web
```

```bash
docker compose logs -f celery
```

```bash
docker compose logs -f celery-beat
```

### Container Durumunu Kontrol Etme

```bash
docker compose ps
```

```bash
docker stats
```

---

## 🐛 Sorun Giderme

### Container'lar Başlamıyor

```bash
docker compose logs
```

```bash
docker compose down
```

```bash
docker compose up -d
```

### Database Bağlantı Hatası

```bash
docker compose logs db
```

```bash
docker compose exec db psql -U saas_user -d saas_db
```

### Nginx 502 Bad Gateway

```bash
docker compose logs web
```

```bash
docker compose restart web
```

```bash
tail -f /var/log/nginx/error.log
```

### Static Files Görünmüyor

```bash
docker compose exec web python manage.py collectstatic --noinput
```

```bash
systemctl reload nginx
```

---

## 📝 Özet - Hızlı Komutlar

```bash
# 1. Bağlan
ssh root@72.62.35.155

# 2. Docker kontrol
docker --version && docker compose version

# 3. Proje klasörü
mkdir -p /var/www/bulutsaas && cd /var/www/bulutsaas

# 4. Projeyi klonla
git clone https://github.com/Kohopolo/bulutsaas.git .

# 5. .env dosyası oluştur
nano .env
# (İçeriği yapıştır, SECRET_KEY oluştur)

# 6. Build ve başlat
docker compose build
docker compose up -d

# 7. Migration
docker compose exec web python manage.py migrate_schemas --shared
docker compose exec web python manage.py migrate_schemas
docker compose exec web python manage.py collectstatic --noinput
docker compose exec web python manage.py createsuperuser

# 8. Nginx kurulumu
apt install -y nginx
nano /etc/nginx/sites-available/bulutsaas
# (Config yapıştır)
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 9. SSL
certbot --nginx -d bulutacente.com.tr -d www.bulutacente.com.tr

# 10. Domain ekle
docker compose exec web python manage.py shell
# (Python komutları çalıştır)

# 11. Kontrol
curl https://bulutacente.com.tr/health/
```

**Başarılar! 🚀**

