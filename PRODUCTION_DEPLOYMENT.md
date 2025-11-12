# 🚀 Production VPS Deployment Rehberi

> **Domain'e canlıya çıkarken adım adım yapılacaklar**

## 📋 Ön Hazırlık

### Gereksinimler:
- ✅ VPS (Ubuntu 22.04 LTS)
- ✅ Domain (örn: saas2026.com)
- ✅ SSH erişimi

---

## 1️⃣ VPS Siparişi

### Önerilen Sağlayıcılar:

| Sağlayıcı | Fiyat | Özellikler |
|-----------|-------|------------|
| **Hetzner** 🥇 | 4€/ay | 2 vCPU, 4 GB RAM, 40 GB SSD |
| **DigitalOcean** 🥈 | $12/ay | 2 vCPU, 4 GB RAM, 80 GB SSD |
| **Linode** 🥉 | $12/ay | 2 vCPU, 4 GB RAM, 80 GB SSD |
| **Vultr** | $12/ay | 2 vCPU, 4 GB RAM, 80 GB SSD |

### VPS Özellikleri:
```
İşletim Sistemi: Ubuntu 22.04 LTS (64-bit)
RAM: 4 GB (minimum 2 GB)
CPU: 2 vCPU
Disk: 50 GB SSD
Region: Frankfurt (Türkiye'ye en yakın)
```

**⚠️ UYARI:** Control Panel (cPanel, Plesk, OpenLiteSpeed) SEÇMEYİN!

---

## 2️⃣ Domain DNS Ayarları

### Cloudflare (Önerilen):

1. Domain'i Cloudflare'e ekle
2. Nameserver'ları değiştir
3. DNS kayıtları ekle:

```
A Record:
  Name: @
  IPv4: [VPS_IP_ADRESINIZ]
  Proxy: ON (turuncu bulut)

A Record:
  Name: www
  IPv4: [VPS_IP_ADRESINIZ]
  Proxy: ON

CNAME Record (Wildcard - Tenant domain'leri için):
  Name: *
  Target: saas2026.com
  Proxy: OFF (gri bulut)
```

### Domain Registrar (GoDaddy, Namecheap vb.):

Eğer Cloudflare kullanmayacaksanız:

```
A Record:
  Host: @
  Value: [VPS_IP_ADRESINIZ]
  TTL: 3600

A Record:
  Host: www
  Value: [VPS_IP_ADRESINIZ]
  TTL: 3600

A Record (Wildcard):
  Host: *
  Value: [VPS_IP_ADRESINIZ]
  TTL: 3600
```

---

## 3️⃣ VPS'e İlk Bağlantı

### SSH ile Bağlan:

```bash
# Windows (PowerShell/CMD)
ssh root@[VPS_IP_ADRESINIZ]

# Şifreyi gir (VPS sağlayıcıdan gelecek)
```

---

## 4️⃣ VPS Güvenlik Ayarları

### Root Kullanıcısı Yerine Yeni Kullanıcı:

```bash
# Yeni kullanıcı oluştur
adduser saasadmin

# Sudo yetkisi ver
usermod -aG sudo saasadmin

# Yeni kullanıcıya geç
su - saasadmin
```

### Firewall Ayarları:

```bash
# UFW kur ve yapılandır
sudo apt update
sudo apt install ufw -y

# Portları aç
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Firewall'ı etkinleştir
sudo ufw enable

# Durumu kontrol et
sudo ufw status
```

### SSH Güvenliği:

```bash
# SSH config düzenle
sudo nano /etc/ssh/sshd_config

# Değiştir:
# PermitRootLogin no
# PasswordAuthentication no  # (SSH key kullanacaksanız)

# SSH'ı yeniden başlat
sudo systemctl restart sshd
```

---

## 5️⃣ Docker Kurulumu

```bash
# Sistem güncellemeleri
sudo apt update && sudo apt upgrade -y

# Gerekli paketler
sudo apt install apt-transport-https ca-certificates curl software-properties-common git -y

# Docker GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Docker repository
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Docker kur
sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io docker-compose-plugin -y

# Docker Compose kur (standalone)
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Kullanıcıyı docker grubuna ekle
sudo usermod -aG docker $USER

# Oturumu yenile (logout/login veya)
newgrp docker

# Docker versiyonlarını kontrol et
docker --version
docker-compose --version
```

---

## 6️⃣ Projeyi VPS'e Yükleme

### Git ile Clone (Önerilen):

```bash
# Ana dizin
cd /home/saasadmin

# Proje dizini oluştur
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www

# Projeyi clone et
cd /var/www
git clone <REPO_URL> saas2026
cd saas2026
```

### Manuel Upload (FTP ile):

```bash
# FileZilla veya WinSCP ile yükle
# Hedef: /var/www/saas2026/
```

---

## 7️⃣ Environment Ayarları (ÇOK ÖNEMLİ!)

```bash
cd /var/www/saas2026

# .env dosyası oluştur
cp env.example .env

# .env dosyasını düzenle
nano .env
```

### .env İçeriği (Production):

```bash
# Django Settings
DEBUG=False
SECRET_KEY=SÜPER-GÜÇLÜ-RASTGELE-ANAHTAR-BURAYA  # ← Değiştir!
ALLOWED_HOSTS=saas2026.com,www.saas2026.com,*.saas2026.com

# Database (PostgreSQL)
DATABASE_URL=postgresql://saas_user:GÜÇLÜ_ŞİFRE_123@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=GÜÇLÜ_ŞİFRE_123  # ← Değiştir!

# Redis
REDIS_URL=redis://redis:6379/0

# Celery
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Email (Gmail SMTP örneği)
EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USE_TLS=True
EMAIL_HOST_USER=info@saas2026.com
EMAIL_HOST_PASSWORD=gmail_uygulama_şifresi
DEFAULT_FROM_EMAIL=noreply@saas2026.com

# Payment (Stripe - canlı anahtarlar)
STRIPE_PUBLIC_KEY=pk_live_xxxxx
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# Application
SITE_NAME=SaaS 2026
SITE_URL=https://saas2026.com  # ← Domain'iniz
ADMIN_URL=super-admin/  # ← Güvenlik için değiştirin

# Tenant Settings
TENANT_MODEL=tenants.Tenant
TENANT_DOMAIN_MODEL=tenants.Domain
PUBLIC_SCHEMA_NAME=public
PUBLIC_SCHEMA_URLCONF=config.urls_public

# Subscription
TRIAL_PERIOD_DAYS=14
SUBSCRIPTION_GRACE_PERIOD_DAYS=3
```

**⚠️ Güvenlik İpuçları:**

```bash
# SECRET_KEY oluştur (Python ile)
python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"

# Güçlü şifre oluştur
openssl rand -base64 32
```

---

## 8️⃣ Nginx Domain Ayarları

```bash
# Nginx config düzenle
nano nginx/conf.d/default.conf
```

### Production Nginx Config:

```nginx
upstream django {
    server web:8000;
}

# HTTP → HTTPS Redirect
server {
    listen 80;
    server_name saas2026.com www.saas2026.com *.saas2026.com;
    
    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    # Diğer tüm istekler HTTPS'e yönlendir
    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS Server
server {
    listen 443 ssl http2;
    server_name saas2026.com www.saas2026.com *.saas2026.com;
    client_max_body_size 50M;

    # SSL Certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/saas2026.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saas2026.com/privkey.pem;
    
    # SSL Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Static files
    location /static/ {
        alias /app/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files
    location /media/ {
        alias /app/media/;
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

    # Health check
    location /health/ {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }
}
```

---

## 9️⃣ SSL Sertifikası (Let's Encrypt)

### Certbot Kurulumu:

```bash
# Certbot kur
sudo apt install certbot -y

# Certbot klasörleri oluştur
mkdir -p certbot/conf certbot/www
```

### SSL Sertifikası Al:

```bash
# İlk defa (HTTP üzerinden)
sudo certbot certonly --standalone \
  -d saas2026.com \
  -d www.saas2026.com \
  --email info@saas2026.com \
  --agree-tos \
  --no-eff-email

# Wildcard sertifika (DNS challenge - Cloudflare)
sudo certbot certonly --dns-cloudflare \
  -d saas2026.com \
  -d *.saas2026.com \
  --email info@saas2026.com \
  --agree-tos
```

### Otomatik Yenileme:

```bash
# Cron job ekle
sudo crontab -e

# Ekle:
0 3 * * * certbot renew --quiet && docker-compose -f /var/www/saas2026/docker-compose.yml restart nginx
```

---

## 🔟 Docker Compose ile Başlat

```bash
cd /var/www/saas2026

# Servisleri başlat
docker-compose up -d

# Logları izle
docker-compose logs -f

# Database migration
docker-compose exec web python manage.py migrate_schemas --shared
docker-compose exec web python manage.py migrate_schemas

# Superuser oluştur
docker-compose exec web python manage.py createsuperuser

# Static dosyaları topla
docker-compose exec web python manage.py collectstatic --noinput
```

---

## 1️⃣1️⃣ Test ve Doğrulama

### Domain Kontrolü:

```bash
# Domain çözümlenmiş mi?
nslookup saas2026.com

# HTTPS çalışıyor mu?
curl -I https://saas2026.com

# Admin panel
curl -I https://saas2026.com/admin/
```

### Tarayıcıda Test:

```
✅ https://saas2026.com - Ana sayfa
✅ https://saas2026.com/admin - Admin panel
✅ https://saas2026.com/api/docs - API docs
✅ https://www.saas2026.com - WWW redirect
```

---

## 1️⃣2️⃣ Monitoring & Bakım

### Log İzleme:

```bash
# Tüm loglar
docker-compose logs -f

# Sadece web
docker-compose logs -f web

# Sadece nginx
docker-compose logs -f nginx

# Son 100 satır
docker-compose logs --tail=100
```

### Database Yedekleme:

```bash
# Manuel yedek
docker-compose exec db pg_dump -U saas_user saas_db > backup_$(date +%Y%m%d).sql

# Otomatik yedek (cron)
sudo crontab -e

# Ekle: Her gece 02:00'de yedek al
0 2 * * * cd /var/www/saas2026 && docker-compose exec -T db pg_dump -U saas_user saas_db > /var/backups/db_backup_$(date +\%Y\%m\%d).sql
```

### Disk Kullanımı:

```bash
# Disk durumu
df -h

# Docker imaj temizliği
docker system prune -a --volumes
```

### Güncelleme:

```bash
cd /var/www/saas2026

# Git pull (kod güncellemeleri)
git pull origin main

# Docker imajlarını güncelle
docker-compose pull

# Yeniden başlat
docker-compose up -d --build

# Migration varsa
docker-compose exec web python manage.py migrate_schemas
```

---

## 1️⃣3️⃣ Performans Optimizasyonu

### Docker Compose (Production):

```yaml
# docker-compose.yml içinde değişiklik:

web:
  # ... diğer ayarlar
  command: gunicorn config.wsgi:application --bind 0.0.0.0:8000 --workers 4 --threads 2 --timeout 120
  deploy:
    resources:
      limits:
        cpus: '1.5'
        memory: 2G
```

### PostgreSQL Tuning:

```bash
# PostgreSQL config (varsa)
docker-compose exec db psql -U saas_user -d saas_db

# Ayarlar:
ALTER SYSTEM SET shared_buffers = '512MB';
ALTER SYSTEM SET effective_cache_size = '2GB';
ALTER SYSTEM SET maintenance_work_mem = '128MB';

# Yeniden başlat
docker-compose restart db
```

---

## 🔒 Güvenlik Checklist

- ✅ DEBUG=False
- ✅ Güçlü SECRET_KEY
- ✅ Firewall (UFW) aktif
- ✅ SSH root login kapalı
- ✅ SSL/HTTPS aktif
- ✅ Admin URL değiştirilmiş (/super-admin/)
- ✅ Database şifreleri güçlü
- ✅ Otomatik yedekleme aktif
- ✅ Fail2Ban kurulu (opsiyonel)
- ✅ Cloudflare proxy aktif (DDoS koruması)

---

## 📊 Sistem Gereksinimleri (Ölçek)

### Küçük (0-100 Tenant):
```
RAM: 2 GB
CPU: 1 vCPU
Disk: 40 GB
Fiyat: ~$6/ay (Hetzner)
```

### Orta (100-500 Tenant):
```
RAM: 4 GB
CPU: 2 vCPU
Disk: 80 GB
Fiyat: ~$12/ay (DigitalOcean)
```

### Büyük (500+ Tenant):
```
RAM: 8+ GB
CPU: 4+ vCPU
Disk: 160+ GB
Load Balancer: Evet
Managed DB: Önerilen
Fiyat: ~$50+/ay
```

---

## 🆘 Sorun Giderme

### Domain açılmıyor:

```bash
# DNS propagasyonu bekleyin (24-48 saat)
# Kontrol:
nslookup saas2026.com
ping saas2026.com
```

### SSL hatası:

```bash
# Sertifikayı yeniden al
sudo certbot certonly --force-renew -d saas2026.com -d www.saas2026.com

# Nginx'i yeniden başlat
docker-compose restart nginx
```

### Database bağlantı hatası:

```bash
# Database loglarını kontrol et
docker-compose logs db

# Database'i yeniden başlat
docker-compose restart db
```

### Disk dolu:

```bash
# Docker temizliği
docker system prune -a --volumes

# Log temizliği
sudo journalctl --vacuum-time=7d
```

---

## ✅ Production Checklist

Canlıya çıkmadan önce:

- [ ] .env dosyası güncel
- [ ] DEBUG=False
- [ ] SECRET_KEY değiştirildi
- [ ] Database şifreleri değiştirildi
- [ ] Domain DNS kayıtları eklendi
- [ ] SSL sertifikası alındı
- [ ] Nginx config domain'e göre ayarlandı
- [ ] Firewall ayarları yapıldı
- [ ] Superuser oluşturuldu
- [ ] İlk test tenant oluşturuldu
- [ ] E-posta gönderimi test edildi
- [ ] Yedekleme cron job'u eklendi
- [ ] Monitoring kuruldu

---

## 📞 Yararlı Komutlar

```bash
# Servis durumu
docker-compose ps

# Servisleri yeniden başlat
docker-compose restart

# Logları temizle
docker-compose down && docker-compose up -d

# Shell'e gir
docker-compose exec web bash

# Database shell
docker-compose exec db psql -U saas_user -d saas_db

# Django shell
docker-compose exec web python manage.py shell

# Migrate
docker-compose exec web python manage.py migrate_schemas
```

---

**🎉 Canlıya Çıkmaya Hazırsınız!**

📅 Oluşturulma: 2025-11-09  
✍️ Geliştirici: SaaS 2026 Team



