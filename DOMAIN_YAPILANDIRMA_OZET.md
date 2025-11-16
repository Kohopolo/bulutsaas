# Domain ve DNS Yapılandırması - Özet Dokümantasyon

## 📋 Hızlı Cevap

### .htaccess Dosyası Gerekli mi?
**HAYIR** - Sistem Nginx kullanıyor, `.htaccess` sadece Apache için geçerlidir.

### Custom Domain ve Subdomain Desteği Var mı?
**EVET** - Sistemde mevcut:
- ✅ Domain modeli (custom, subdomain, primary)
- ✅ Django-tenants middleware (otomatik routing)
- ✅ Domain tipi seçenekleri

### Digital Ocean'da Otomatik DNS Yapılandırması Nasıl Yapılacak?
**Otomatik DNS Yönetimi** - Yeni oluşturulan dosyalar:
- ✅ `apps/tenants/utils/dns_manager.py` - Digital Ocean DNS API manager
- ✅ `apps/tenants/signals.py` - Domain eklendiğinde otomatik DNS kaydı
- ✅ `apps/tenants/management/commands/add_tenant_domain.py` - Management command
- ✅ `apps/tenants/middleware/allowed_hosts.py` - Dinamik ALLOWED_HOSTS kontrolü

---

## 🔄 Nasıl Çalışır?

### 1. Domain Ekleme Akışı

```
1. Admin Panel veya Management Command ile Domain Ekle
   ↓
2. Domain Modeli Kaydedilir (apps/tenants/models.py)
   ↓
3. post_save Signal Tetiklenir (apps/tenants/signals.py)
   ↓
4. DigitalOceanDNSManager API Çağrılır (apps/tenants/utils/dns_manager.py)
   ↓
5. Digital Ocean DNS'de A Record Oluşturulur
   ↓
6. Record ID Domain Modeline Kaydedilir
   ↓
7. DNS Propagasyon (1-5 dakika)
   ↓
8. Domain Erişilebilir ✅
```

### 2. Domain Routing (Django-Tenants)

```
Request: https://test-otel.yourdomain.com/
   ↓
Nginx: Wildcard domain (*.yourdomain.com) yakalar
   ↓
Gunicorn: Request'i Django'ya iletir
   ↓
TenantMainMiddleware: Domain'i kontrol eder
   ↓
Domain Model: Domain veritabanında aranır
   ↓
Tenant Bulunur: Domain'e bağlı tenant bulunur
   ↓
Schema Değiştirilir: Tenant'ın PostgreSQL schema'sına geçilir
   ↓
Request İşlenir: Normal Django request işleme devam eder
```

---

## 🛠️ Kurulum Adımları

### 1. Paket Yükleme

```bash
pip install python-digitalocean
```

### 2. Environment Variables

`.env` dosyasına ekleyin:

```env
DO_API_TOKEN=your_digital_ocean_api_token_here
DO_DOMAIN=yourdomain.com
DO_DROPLET_IP=YOUR_DROPLET_IP_ADDRESS
```

### 3. Nginx Wildcard Config

`/etc/nginx/sites-available/bulutacente` dosyasında:

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com *.yourdomain.com;
    # ... diğer ayarlar
}
```

### 4. Wildcard SSL Sertifikası

```bash
sudo certbot certonly --manual --preferred-challenges dns \
    -d yourdomain.com -d *.yourdomain.com
```

### 5. Digital Ocean DNS Wildcard A Record

Digital Ocean DNS'de:
```
Type: A
Name: *
Value: YOUR_DROPLET_IP
TTL: 300
```

### 6. Django Settings (Opsiyonel)

`config/settings.py`:

```python
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',
    'apps.tenants.middleware.allowed_hosts.DynamicAllowedHostsMiddleware',  # Yeni
    # ... diğer middleware'ler
]

ALLOWED_HOSTS = ['*']  # Middleware kontrol edecek
```

---

## 📝 Kullanım Örnekleri

### Management Command ile Domain Ekleme

```bash
# Subdomain ekle (DNS otomatik)
python manage.py add_tenant_domain \
    --tenant-schema=test-otel \
    --domain=test-otel.yourdomain.com \
    --domain-type=subdomain \
    --is-primary

# Custom domain ekle (DNS manuel)
python manage.py add_tenant_domain \
    --tenant-schema=test-otel \
    --domain=otelim.com \
    --domain-type=custom \
    --skip-dns
```

### Django Shell ile Domain Ekleme

```python
from apps.tenants.models import Tenant, Domain
from django_tenants.utils import schema_context

with schema_context('public'):
    tenant = Tenant.objects.get(schema_name='test-otel')
    
    # Domain oluştur (Signal otomatik DNS kaydı oluşturacak)
    Domain.objects.create(
        domain='test-otel.yourdomain.com',
        tenant=tenant,
        domain_type='subdomain',
        is_primary=True
    )
```

---

## 🔍 Kontrol ve Test

### DNS Kaydı Kontrolü

```bash
# DNS kaydını kontrol et
dig test-otel.yourdomain.com

# Nginx loglarını kontrol et
sudo tail -f /var/log/nginx/access.log

# Gunicorn loglarını kontrol et
tail -f /var/www/bulutacente/logs/gunicorn_error.log
```

### Domain Erişilebilirlik Testi

```bash
# HTTP test
curl -I http://test-otel.yourdomain.com

# HTTPS test
curl -I https://test-otel.yourdomain.com

# Browser'da test
# https://test-otel.yourdomain.com
```

---

## ⚠️ Önemli Notlar

1. **Wildcard DNS**: Digital Ocean DNS'de wildcard A record (`*`) ekleyin
2. **Wildcard SSL**: Tüm subdomain'ler için wildcard SSL sertifikası gerekli
3. **DNS Propagasyon**: DNS kayıtları 1-5 dakika içinde aktif olur
4. **Custom Domain**: Custom domain'ler için DNS ayarları domain sahibi tarafından yapılmalı
5. **ALLOWED_HOSTS**: Middleware ile dinamik kontrol yapılıyor, wildcard `*` kullanılabilir

---

## 📚 Detaylı Dokümantasyon

- **Domain Otomatik Yapılandırma**: `DOMAIN_OTOMATIK_YAPILANDIRMA.md`
- **Digital Ocean Deployment**: `DIGITAL_OCEAN_DEPLOYMENT.md`
- **Domain DNS Yapılandırma**: `DOMAIN_DNS_YAPILANDIRMA.md`

---

**Son Güncelleme:** 2025-01-XX
**Versiyon:** 1.0

