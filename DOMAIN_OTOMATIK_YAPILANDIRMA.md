# Domain ve DNS Otomatik Yapılandırma Rehberi
## Digital Ocean Droplet için Tenant Domain Yönetimi

Bu dokümantasyon, tenant domain'leri eklendiğinde Digital Ocean'da DNS ayarlarının nasıl otomatik yapılacağını açıklar.

---

## 📋 İçindekiler

1. [Mevcut Sistem Durumu](#mevcut-sistem-durumu)
2. [.htaccess Dosyası Gerekli mi?](#htaccess-dosyası-gerekli-mi)
3. [Django-Tenants Domain Routing](#django-tenants-domain-routing)
4. [Nginx Wildcard Domain Yapılandırması](#nginx-wildcard-domain-yapılandırması)
5. [Digital Ocean DNS API Entegrasyonu](#digital-ocean-dns-api-entegrasyonu)
6. [Otomatik Domain Yönetimi](#otomatik-domain-yönetimi)
7. [SSL Sertifikası Otomasyonu](#ssl-sertifikası-otomasyonu)

---

## 🔍 Mevcut Sistem Durumu

### ✅ Mevcut Özellikler

1. **Django-Tenants Middleware**: Domain routing otomatik yapılıyor
2. **Domain Modeli**: Custom domain ve subdomain desteği var
3. **Domain Tipi**: Primary, Custom, Subdomain seçenekleri mevcut
4. **SSL Desteği**: Domain modelinde SSL alanları var

### ❌ Eksik Özellikler

1. **Digital Ocean DNS API Entegrasyonu**: Yok
2. **Otomatik DNS Kaydı**: Yok
3. **ALLOWED_HOSTS Dinamik Güncelleme**: Yok (wildcard desteklenmiyor)
4. **SSL Sertifikası Otomasyonu**: Yok

---

## ❓ .htaccess Dosyası Gerekli mi?

### Cevap: **HAYIR, Gerekli Değil**

**Neden?**

1. **Nginx Kullanılıyor**: Sistem Apache değil, Nginx kullanıyor
   - `.htaccess` dosyası sadece Apache için geçerlidir
   - Nginx'te `.htaccess` benzeri yapılandırma yoktur
   - Tüm ayarlar Nginx config dosyasında yapılır

2. **Django-Tenants Middleware**: Domain routing Django seviyesinde yapılıyor
   - `TenantMainMiddleware` her request'te domain'i kontrol eder
   - Domain'e göre tenant schema'sına geçer
   - Web server seviyesinde routing gerekmez

3. **Mevcut .htaccess Dosyası**: 
   - Sadece `backupdatabase` klasöründe var (güvenlik için)
   - Bu Apache için hazırlanmış ama Nginx'te kullanılmıyor

**Sonuç**: `.htaccess` dosyasına ihtiyaç yoktur. Tüm yapılandırma Nginx ve Django seviyesinde yapılır.

---

## 🔄 Django-Tenants Domain Routing

### Nasıl Çalışır?

1. **Request Gelir**: `http://test-otel.yourdomain.com/`
2. **TenantMainMiddleware Devreye Girer**: İlk middleware olarak çalışır
3. **Domain Kontrolü**: `Domain` modelinde domain aranır
4. **Tenant Bulunur**: Domain'e bağlı tenant bulunur
5. **Schema Değiştirilir**: Tenant'ın PostgreSQL schema'sına geçilir
6. **Request İşlenir**: Normal Django request işleme devam eder

### Mevcut Yapılandırma

```python
# config/settings.py
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',  # İlk sırada!
    # ... diğer middleware'ler
]

TENANT_MODEL = "tenants.Tenant"
TENANT_DOMAIN_MODEL = "tenants.Domain"
PUBLIC_SCHEMA_NAME = 'public'
PUBLIC_SCHEMA_URLCONF = 'config.urls_public'
```

### Domain Modeli

```python
# apps/tenants/models.py
class Domain(DomainMixin):
    tenant = ForeignKey(Tenant)
    domain_type = CharField(choices=[
        ('primary', 'Ana Domain'),
        ('custom', 'Özel Domain'),
        ('subdomain', 'Alt Domain'),
    ])
    ssl_enabled = BooleanField(default=False)
```

---

## 🌐 Nginx Wildcard Domain Yapılandırması

### Mevcut Durum

Şu anda Nginx config'de her domain için ayrı `server` bloğu gerekiyor. Bu pratik değil.

### Çözüm: Wildcard Domain Yapılandırması

Nginx wildcard domain desteği ile tüm subdomain'leri tek bir config ile yönetebiliriz.

**Güncellenmiş Nginx Config:**

```nginx
# /etc/nginx/sites-available/bulutacente

# Upstream Gunicorn
upstream bulutacente_app {
    server unix:/var/www/bulutacente/gunicorn.sock fail_timeout=0;
}

# HTTP -> HTTPS redirect (Wildcard)
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

# HTTPS Server (Wildcard)
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com *.yourdomain.com;
    
    # SSL Sertifikaları (Wildcard sertifikası gerekli)
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
    
    # Gunicorn Proxy (Tüm domain'ler için)
    location / {
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Host $http_host;  # Domain bilgisini Django'ya iletir
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

**Önemli Notlar:**

1. **Wildcard SSL Sertifikası**: `*.yourdomain.com` için wildcard SSL sertifikası gerekli
2. **Custom Domain'ler**: Custom domain'ler için ayrı `server` bloğu gerekebilir
3. **Host Header**: `proxy_set_header Host $http_host;` kritik - Django'ya domain bilgisini iletir

---

## 🔌 Digital Ocean DNS API Entegrasyonu

### 1. Digital Ocean API Token Oluşturma

1. Digital Ocean hesabınıza giriş yapın
2. **API** > **Tokens/Keys** bölümüne gidin
3. **Generate New Token** butonuna tıklayın
4. Token adı: `bulutacente-dns-manager`
5. Scopes: `Write` yetkisi verin
6. Token'ı kopyalayın ve güvenli bir yere kaydedin

### 2. Python Paketi Kurulumu

```bash
# Digital Ocean API için
pip install python-digitalocean
```

`requirements.txt` dosyasına ekleyin:

```txt
python-digitalocean==1.17.0
```

### 3. Environment Variables

`.env` dosyasına ekleyin:

```env
# Digital Ocean DNS
DO_API_TOKEN=your_digital_ocean_api_token_here
DO_DOMAIN=yourdomain.com
DO_DROPLET_IP=YOUR_DROPLET_IP_ADDRESS
```

### 4. Digital Ocean DNS Manager Utility

Yeni bir utility dosyası oluşturun:

```python
# apps/tenants/utils/dns_manager.py
import os
import requests
from django.conf import settings
from django.core.exceptions import ImproperlyConfigured

class DigitalOceanDNSManager:
    """Digital Ocean DNS API Manager"""
    
    def __init__(self):
        self.api_token = os.getenv('DO_API_TOKEN')
        self.domain = os.getenv('DO_DOMAIN', 'yourdomain.com')
        self.droplet_ip = os.getenv('DO_DROPLET_IP')
        
        if not self.api_token:
            raise ImproperlyConfigured('DO_API_TOKEN environment variable is required')
        if not self.droplet_ip:
            raise ImproperlyConfigured('DO_DROPLET_IP environment variable is required')
        
        self.base_url = 'https://api.digitalocean.com/v2'
        self.headers = {
            'Authorization': f'Bearer {self.api_token}',
            'Content-Type': 'application/json'
        }
    
    def create_a_record(self, subdomain, ip_address=None, ttl=300):
        """
        A Record oluştur
        
        Args:
            subdomain: Subdomain adı (örn: 'test-otel' veya '@' ana domain için)
            ip_address: IP adresi (None ise droplet IP kullanılır)
            ttl: TTL değeri (saniye)
        
        Returns:
            dict: API response
        """
        if ip_address is None:
            ip_address = self.droplet_ip
        
        # Ana domain için '@' kullan
        name = '@' if subdomain == self.domain or subdomain == '' else subdomain
        
        url = f'{self.base_url}/domains/{self.domain}/records'
        data = {
            'type': 'A',
            'name': name,
            'data': ip_address,
            'ttl': ttl
        }
        
        response = requests.post(url, headers=self.headers, json=data)
        response.raise_for_status()
        return response.json()
    
    def create_cname_record(self, subdomain, target, ttl=300):
        """
        CNAME Record oluştur
        
        Args:
            subdomain: Subdomain adı
            target: Hedef domain
            ttl: TTL değeri
        
        Returns:
            dict: API response
        """
        url = f'{self.base_url}/domains/{self.domain}/records'
        data = {
            'type': 'CNAME',
            'name': subdomain,
            'data': target,
            'ttl': ttl
        }
        
        response = requests.post(url, headers=self.headers, json=data)
        response.raise_for_status()
        return response.json()
    
    def delete_record(self, record_id):
        """
        DNS Record sil
        
        Args:
            record_id: Record ID
        
        Returns:
            dict: API response
        """
        url = f'{self.base_url}/domains/{self.domain}/records/{record_id}'
        response = requests.delete(url, headers=self.headers)
        response.raise_for_status()
        return response.json()
    
    def get_records(self, record_type=None, name=None):
        """
        DNS Record'ları listele
        
        Args:
            record_type: Record tipi (A, CNAME, vb.)
            name: Record adı
        
        Returns:
            list: Record listesi
        """
        url = f'{self.base_url}/domains/{self.domain}/records'
        params = {}
        
        if record_type:
            params['type'] = record_type
        if name:
            params['name'] = name
        
        response = requests.get(url, headers=self.headers, params=params)
        response.raise_for_status()
        return response.json().get('domain_records', [])
    
    def find_record(self, name, record_type='A'):
        """
        Belirli bir record'u bul
        
        Args:
            name: Record adı
            record_type: Record tipi
        
        Returns:
            dict: Record dict veya None
        """
        records = self.get_records(record_type=record_type, name=name)
        for record in records:
            if record['name'] == name and record['type'] == record_type:
                return record
        return None
```

### 5. Domain Signal Handler

Domain eklendiğinde otomatik DNS kaydı oluşturmak için signal kullanın:

```python
# apps/tenants/signals.py
from django.db.models.signals import post_save, post_delete
from django.dispatch import receiver
from django.conf import settings
from .models import Domain
import logging

logger = logging.getLogger(__name__)

@receiver(post_save, sender=Domain)
def create_dns_record(sender, instance, created, **kwargs):
    """Domain eklendiğinde DNS kaydı oluştur"""
    if not created:
        return
    
    # Sadece production'da çalıştır
    if settings.DEBUG:
        logger.info(f"DEBUG mode: DNS record creation skipped for {instance.domain}")
        return
    
    try:
        from .utils.dns_manager import DigitalOceanDNSManager
        
        dns_manager = DigitalOceanDNSManager()
        domain = instance.domain
        
        # Custom domain kontrolü
        if instance.domain_type == 'custom':
            # Custom domain için A record oluştur
            # Not: Custom domain'ler için domain sahibinin DNS ayarlarını yapması gerekir
            logger.info(f"Custom domain detected: {domain}. DNS configuration should be done by domain owner.")
            return
        
        # Subdomain için
        if instance.domain_type == 'subdomain':
            # Subdomain'i ana domain'den ayır
            if '.' in domain:
                subdomain = domain.split('.')[0]
            else:
                subdomain = domain
            
            # A record oluştur
            result = dns_manager.create_a_record(subdomain)
            logger.info(f"DNS A record created for {domain}: {result}")
            
            # Domain modeline record ID'yi kaydet (opsiyonel)
            if 'domain_record' in result:
                instance.ssl_certificate = str(result['domain_record']['id'])  # Geçici olarak ID'yi kaydet
                instance.save(update_fields=['ssl_certificate'])
    
    except Exception as e:
        logger.error(f"DNS record creation failed for {instance.domain}: {str(e)}")
        # Hata durumunda domain oluşturma işlemini durdurma (sadece log)

@receiver(post_delete, sender=Domain)
def delete_dns_record(sender, instance, **kwargs):
    """Domain silindiğinde DNS kaydını sil"""
    if settings.DEBUG:
        return
    
    try:
        from .utils.dns_manager import DigitalOceanDNSManager
        
        dns_manager = DigitalOceanDNSManager()
        
        # Record ID'yi al (ssl_certificate alanında saklanmışsa)
        if instance.ssl_certificate and instance.ssl_certificate.isdigit():
            record_id = int(instance.ssl_certificate)
            dns_manager.delete_record(record_id)
            logger.info(f"DNS record deleted for {instance.domain}")
    
    except Exception as e:
        logger.error(f"DNS record deletion failed for {instance.domain}: {str(e)}")
```

**Signal'ı aktifleştirmek için:**

```python
# apps/tenants/apps.py
from django.apps import AppConfig

class TenantsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenants'
    
    def ready(self):
        import apps.tenants.signals  # Signal'ları yükle
```

---

## 🎯 Otomatik Domain Yönetimi

### Management Command: Domain Ekleme

```python
# apps/tenants/management/commands/add_tenant_domain.py
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context
from apps.tenants.models import Tenant, Domain
from apps.tenants.utils.dns_manager import DigitalOceanDNSManager
import logging

logger = logging.getLogger(__name__)

class Command(BaseCommand):
    help = 'Tenant domain ekle ve DNS kaydı oluştur'
    
    def add_arguments(self, parser):
        parser.add_argument('--tenant-schema', type=str, required=True, help='Tenant schema adı')
        parser.add_argument('--domain', type=str, required=True, help='Domain adı')
        parser.add_argument('--domain-type', type=str, choices=['primary', 'custom', 'subdomain'], default='subdomain')
        parser.add_argument('--is-primary', action='store_true', help='Primary domain olarak işaretle')
        parser.add_argument('--skip-dns', action='store_true', help='DNS kaydı oluşturma')
    
    def handle(self, *args, **options):
        schema_name = options['tenant_schema']
        domain_name = options['domain']
        domain_type = options['domain_type']
        is_primary = options['is_primary']
        skip_dns = options['skip_dns']
        
        with schema_context('public'):
            try:
                tenant = Tenant.objects.get(schema_name=schema_name)
            except Tenant.DoesNotExist:
                self.stdout.write(self.style.ERROR(f'Tenant bulunamadı: {schema_name}'))
                return
            
            # Domain oluştur
            domain = Domain.objects.create(
                tenant=tenant,
                domain=domain_name,
                domain_type=domain_type,
                is_primary=is_primary
            )
            
            self.stdout.write(self.style.SUCCESS(f'Domain oluşturuldu: {domain_name}'))
            
            # DNS kaydı oluştur (opsiyonel)
            if not skip_dns:
                try:
                    dns_manager = DigitalOceanDNSManager()
                    
                    if domain_type == 'subdomain':
                        subdomain = domain_name.split('.')[0]
                        result = dns_manager.create_a_record(subdomain)
                        self.stdout.write(self.style.SUCCESS(f'DNS A record oluşturuldu: {subdomain}'))
                    elif domain_type == 'custom':
                        self.stdout.write(self.style.WARNING(
                            f'Custom domain: {domain_name}. '
                            'DNS ayarlarını domain sahibi yapmalıdır.'
                        ))
                    
                except Exception as e:
                    self.stdout.write(self.style.ERROR(f'DNS kaydı oluşturulamadı: {str(e)}'))
```

### Kullanım:

```bash
# Subdomain ekle
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

---

## 🔒 SSL Sertifikası Otomasyonu

### Wildcard SSL Sertifikası Alma

Subdomain'ler için wildcard SSL sertifikası gerekli:

```bash
# Wildcard SSL sertifikası al
sudo certbot certonly --manual --preferred-challenges dns \
    -d yourdomain.com \
    -d *.yourdomain.com \
    --email your-email@example.com \
    --agree-tos \
    --manual-public-ip-logging-ok

# Certbot size DNS TXT kaydı verecek, bunu Digital Ocean DNS'e ekleyin
# Sonra Enter'a basın
```

### Otomatik SSL Sertifikası (Opsiyonel)

Custom domain'ler için otomatik SSL:

```python
# apps/tenants/management/commands/setup_ssl.py
from django.core.management.base import BaseCommand
from apps.tenants.models import Domain
import subprocess
import os

class Command(BaseCommand):
    help = 'Domain için SSL sertifikası oluştur'
    
    def add_arguments(self, parser):
        parser.add_argument('--domain', type=str, required=True)
    
    def handle(self, *args, **options):
        domain = options['domain']
        
        # Certbot ile SSL sertifikası al
        cmd = [
            'sudo', 'certbot', '--nginx',
            '-d', domain,
            '--non-interactive',
            '--agree-tos',
            '--email', os.getenv('ADMIN_EMAIL', 'admin@yourdomain.com')
        ]
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
            if result.returncode == 0:
                # Domain modelini güncelle
                domain_obj = Domain.objects.get(domain=domain)
                domain_obj.ssl_enabled = True
                domain_obj.save()
                self.stdout.write(self.style.SUCCESS(f'SSL sertifikası oluşturuldu: {domain}'))
            else:
                self.stdout.write(self.style.ERROR(f'SSL sertifikası oluşturulamadı: {result.stderr}'))
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'Hata: {str(e)}'))
```

---

## 📝 ALLOWED_HOSTS Dinamik Güncelleme

Django wildcard `ALLOWED_HOSTS` desteklemiyor. Çözüm:

### 1. Middleware ile Dinamik Kontrol

```python
# apps/tenants/middleware/allowed_hosts.py
from django.core.exceptions import DisallowedHost
from django_tenants.utils import schema_context
from apps.tenants.models import Domain

class DynamicAllowedHostsMiddleware:
    """ALLOWED_HOSTS kontrolünü dinamik yapar"""
    
    def __init__(self, get_response):
        self.get_response = get_response
    
    def __call__(self, request):
        host = request.get_host().split(':')[0]
        
        # Public schema'da domain kontrolü yap
        with schema_context('public'):
            domain_exists = Domain.objects.filter(domain=host).exists()
            
            if not domain_exists:
                # Ana domain kontrolü (wildcard için)
                base_domain = '.'.join(host.split('.')[-2:])  # yourdomain.com
                if Domain.objects.filter(domain__endswith=base_domain).exists():
                    domain_exists = True
            
            if not domain_exists:
                raise DisallowedHost(f"Invalid host header: {host}")
        
        return self.get_response(request)
```

**Settings'e ekleyin:**

```python
# config/settings.py
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',
    'apps.tenants.middleware.allowed_hosts.DynamicAllowedHostsMiddleware',  # Yeni middleware
    # ... diğer middleware'ler
]

# ALLOWED_HOSTS'u genişlet (wildcard için)
ALLOWED_HOSTS = ['*']  # Middleware kontrol edecek
```

---

## 🚀 Otomatik Domain Ekleme Akışı

### Senaryo: Yeni Tenant Domain Ekleme

1. **Admin Panel veya API ile Domain Ekle**
   ```python
   domain = Domain.objects.create(
       tenant=tenant,
       domain='yeni-otel.yourdomain.com',
       domain_type='subdomain',
       is_primary=True
   )
   ```

2. **Signal Otomatik Çalışır**
   - `post_save` signal tetiklenir
   - Digital Ocean DNS API çağrılır
   - A record oluşturulur

3. **DNS Propagasyon**
   - DNS kaydı 1-5 dakika içinde aktif olur
   - Domain erişilebilir hale gelir

4. **SSL Sertifikası (Opsiyonel)**
   - Wildcard sertifika varsa otomatik çalışır
   - Custom domain için manuel veya otomatik SSL

---

## 📋 Özet

### ✅ Yapılması Gerekenler

1. **Digital Ocean API Token**: Oluştur ve `.env`'e ekle
2. **DNS Manager Utility**: `apps/tenants/utils/dns_manager.py` oluştur
3. **Signal Handler**: `apps/tenants/signals.py` oluştur
4. **Nginx Wildcard Config**: Nginx config'i güncelle
5. **Wildcard SSL**: Wildcard SSL sertifikası al
6. **Dynamic ALLOWED_HOSTS**: Middleware ekle (opsiyonel)

### ❌ Gereksiz Olanlar

1. **.htaccess Dosyası**: Nginx kullanıldığı için gerekli değil
2. **Manuel DNS Yönetimi**: API ile otomatik yapılacak
3. **Her Domain İçin Ayrı Nginx Config**: Wildcard ile tek config yeterli

---

## 🔧 Hızlı Kurulum

```bash
# 1. Paket yükle
pip install python-digitalocean

# 2. .env dosyasına ekle
echo "DO_API_TOKEN=your_token_here" >> .env
echo "DO_DOMAIN=yourdomain.com" >> .env
echo "DO_DROPLET_IP=YOUR_IP" >> .env

# 3. DNS Manager ve Signal dosyalarını oluştur (yukarıdaki kodları kullan)

# 4. Nginx config'i güncelle (wildcard domain ekle)

# 5. Wildcard SSL al
sudo certbot certonly --manual --preferred-challenges dns \
    -d yourdomain.com -d *.yourdomain.com

# 6. Test et
python manage.py add_tenant_domain \
    --tenant-schema=test-otel \
    --domain=test-otel.yourdomain.com \
    --domain-type=subdomain \
    --is-primary
```

---

**Son Güncelleme:** 2025-01-XX
**Versiyon:** 1.0

