# Domain ve DNS Yapılandırması Dokümantasyonu

## 📋 İçindekiler

1. [Genel Bakış](#genel-bakış)
2. [Tenant Domain Yapısı](#tenant-domain-yapısı)
3. [Custom Domain Ayarları](#custom-domain-ayarları)
4. [DNS Yapılandırması](#dns-yapılandırması)
5. [Otomatik Yönlendirme](#otomatik-yönlendirme)
6. [Domain Firması Ayarları](#domain-firması-ayarları)

---

## 🎯 Genel Bakış

Bu sistem **Django Tenants** kullanarak multi-tenant SaaS yapısında çalışmaktadır. Her tenant'ın kendi domain'i veya subdomain'i olabilir.

### Domain Tipleri

1. **Primary Domain (Ana Domain)**: Tenant'ın varsayılan domain'i
2. **Custom Domain (Özel Domain)**: Tenant'ın kendi domain'i (örn: `otelim.com`)
3. **Subdomain (Alt Domain)**: Ana domain'in alt domain'i (örn: `otel1.saas2026.com`)

---

## 🏗️ Tenant Domain Yapısı

### Model Yapısı

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
    ssl_certificate = TextField(blank=True)
```

### Domain Ekleme

#### 1. Admin Panelden Ekleme

```
Admin Panel → Domains → Add Domain

Domain: otel1.saas2026.com
Tenant: Test Oteli
Domain Type: subdomain
Is Primary: Yes
SSL Enabled: Yes (opsiyonel)

[Save]
```

#### 2. Management Command ile Ekleme

```bash
python manage.py add_tenant_domain \
    --tenant-schema=tenant_otel1 \
    --domain=otel1.saas2026.com \
    --is-primary
```

---

## 🌐 Custom Domain Ayarları

### Custom Domain Nasıl Çalışır?

1. **Tenant Custom Domain Ekler**: `otelim.com`
2. **DNS Ayarları Yapılır**: Domain firmasında DNS kayıtları eklenir
3. **Sistem Domain'i Tanır**: Django Tenants otomatik olarak domain'i tenant'a yönlendirir

### Custom Domain Ekleme Adımları

#### Adım 1: Django Admin'de Domain Ekle

```
Admin Panel → Domains → Add Domain

Domain: otelim.com
Tenant: Test Oteli
Domain Type: custom
Is Primary: No (çünkü zaten primary domain var)
SSL Enabled: Yes

[Save]
```

#### Adım 2: DNS Kayıtları Ekle

Domain firmanızın DNS yönetim paneline gidin ve şu kayıtları ekleyin:

**A Record (Ana Domain):**
```
Type: A
Name: @
Value: [VPS_IP_ADRESINIZ]  (örn: 123.45.67.89)
TTL: 3600
```

**A Record (WWW):**
```
Type: A
Name: www
Value: [VPS_IP_ADRESINIZ]
TTL: 3600
```

**CNAME Record (Wildcard - Opsiyonel):**
```
Type: CNAME
Name: *
Value: saas2026.com
TTL: 3600
```

#### Adım 3: SSL Sertifikası

SSL sertifikası otomatik olarak Let's Encrypt ile oluşturulur:

```bash
# Wildcard SSL için
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d otelim.com \
  -d *.otelim.com \
  --email info@otelim.com \
  --agree-tos \
  --non-interactive
```

---

## 🔧 DNS Yapılandırması

### DNS Kayıt Tipleri

#### 1. A Record (IPv4 Adresi)

**Kullanım**: Domain'i bir IP adresine yönlendirmek için

```
Type: A
Name: @ (veya boş)
Value: 123.45.67.89
TTL: 3600
```

**Örnekler:**
- `@` → `123.45.67.89` (ana domain)
- `www` → `123.45.67.89` (www subdomain)
- `*` → `123.45.67.89` (wildcard - tüm subdomain'ler)

#### 2. CNAME Record (Canonical Name)

**Kullanım**: Bir domain'i başka bir domain'e yönlendirmek için

```
Type: CNAME
Name: www
Value: saas2026.com
TTL: 3600
```

#### 3. MX Record (Mail Exchange)

**Kullanım**: E-posta sunucusu için (opsiyonel)

```
Type: MX
Name: @
Value: mail.saas2026.com
Priority: 10
TTL: 3600
```

---

## 🔄 Otomatik Yönlendirme

### Django Tenants Middleware

Sistem otomatik olarak domain'i tenant'a yönlendirir:

```python
# settings.py
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',
    # ... diğer middleware'ler
]

# Tenant ayarları
TENANT_MODEL = "tenants.Tenant"
DOMAIN_MODEL = "tenants.Domain"
```

### Yönlendirme Mantığı

1. **Request Gelir**: `http://otelim.com/`
2. **Middleware Devreye Girer**: `TenantMainMiddleware`
3. **Domain Kontrolü**: Domain veritabanında aranır
4. **Tenant Bulunur**: Domain'e bağlı tenant bulunur
5. **Schema Değiştirilir**: Tenant'ın schema'sına geçilir
6. **Request İşlenir**: Normal Django request işleme devam eder

### Nginx Yapılandırması

```nginx
# /etc/nginx/sites-available/saas2026
server {
    listen 80;
    server_name *.saas2026.com saas2026.com;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Custom domain için
server {
    listen 80;
    server_name otelim.com www.otelim.com;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🏢 Domain Firması Ayarları

### Popüler Domain Firmaları için DNS Ayarları

#### 1. Cloudflare

**Adımlar:**

1. Cloudflare'e giriş yapın
2. Domain'inizi ekleyin
3. Nameserver'ları domain firmanızda güncelleyin
4. DNS kayıtlarını ekleyin:

```
Type: A
Name: @
IPv4: [VPS_IP]
Proxy: DNS Only (gri bulut)

Type: A
Name: www
IPv4: [VPS_IP]
Proxy: DNS Only
```

#### 2. Namecheap

**Adımlar:**

1. Namecheap'e giriş yapın
2. Domain List → Domain'inizi seçin
3. "Advanced DNS" sekmesine gidin
4. DNS kayıtlarını ekleyin:

```
Type: A Record
Host: @
Value: [VPS_IP]
TTL: Automatic

Type: A Record
Host: www
Value: [VPS_IP]
TTL: Automatic
```

#### 3. GoDaddy

**Adımlar:**

1. GoDaddy'ye giriş yapın
2. My Products → Domain'inizi seçin
3. DNS → Manage DNS
4. DNS kayıtlarını ekleyin:

```
Type: A
Name: @
Value: [VPS_IP]
TTL: 600

Type: A
Name: www
Value: [VPS_IP]
TTL: 600
```

#### 4. Türkiye Registrar'ları (Natro, Turhost, vb.)

**Adımlar:**

1. Registrar paneline giriş yapın
2. Domain listesi → Domain'inizi seçin
3. "DNS Yönetimi" veya "Nameserver Ayarları"
4. DNS kayıtlarını ekleyin:

```
Tip: A Kaydı
İsim: @ (veya boş)
Değer: [VPS_IP]
TTL: 3600

Tip: A Kaydı
İsim: www
Değer: [VPS_IP]
TTL: 3600
```

---

## 📝 Tenant Domain Ayarları (Website Builder)

### Website Builder'da Domain Ayarlama

Website Builder modülünde tenant'lar kendi domain'lerini ayarlayabilir:

#### 1. Website Ayarları Sayfası

```
Website Builder → Websites → [Website Seç] → Settings → Domain
```

#### 2. Domain Ekleme Formu

```html
<form id="domainForm">
    <div class="form-group">
        <label>Custom Domain</label>
        <input type="text" name="domain" placeholder="otelim.com" />
        <small>Domain'inizi buraya girin (http:// veya https:// olmadan)</small>
    </div>
    <button type="submit">Domain Ayarla</button>
</form>
```

#### 3. DNS Yönlendirme Talimatları

Sistem otomatik olarak DNS yapılandırma talimatlarını gösterir:

```
✅ Domain başarıyla ayarlandı!

📋 DNS Yapılandırması:

Domain firmanızın DNS yönetim paneline gidin ve şu kayıtları ekleyin:

A Record:
  Type: A
  Name: @
  Value: 123.45.67.89
  TTL: 3600

A Record (WWW):
  Type: A
  Name: www
  Value: 123.45.67.89
  TTL: 3600

⚠️ DNS değişikliklerinin aktif olması 1-48 saat sürebilir.
```

---

## 🔐 SSL Sertifikası

### Let's Encrypt ile Otomatik SSL

#### 1. Certbot Kurulumu

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx
```

#### 2. SSL Sertifikası Oluşturma

```bash
# Tek domain için
sudo certbot --nginx -d otelim.com -d www.otelim.com

# Wildcard için (Cloudflare DNS kullanarak)
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d otelim.com \
  -d *.otelim.com
```

#### 3. Otomatik Yenileme

```bash
# Cron job ekle
sudo crontab -e

# Ekle:
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 🚀 Sistem Nasıl Çalışır?

### 1. Domain İsteği Gelir

```
Kullanıcı: http://otelim.com/ → Tarayıcı
```

### 2. DNS Çözümleme

```
DNS Server: otelim.com → 123.45.67.89 (VPS IP)
```

### 3. Nginx Yönlendirme

```
Nginx: Request'i Django'ya yönlendir
Host Header: otelim.com
```

### 4. Django Tenants Middleware

```python
# django_tenants/middleware/main.py
class TenantMainMiddleware:
    def process_request(self, request):
        # Domain'i al
        host = request.get_host().split(':')[0]
        
        # Domain'i veritabanında ara
        domain = Domain.objects.get(domain=host)
        
        # Tenant'ı bul
        tenant = domain.tenant
        
        # Schema'yı değiştir
        connection.set_tenant(tenant)
        
        # Request'i işle
        return None
```

### 5. Request İşleme

```
Django: Tenant schema'sında request'i işle
Response: Tenant'a özel içerik döndür
```

---

## 📊 Domain Durum Kontrolü

### Domain Doğrulama

```python
# apps/tenant_apps/website_builder/publish_utils.py
def validate_domain(domain):
    """
    Domain doğrulama
    """
    import re
    
    # Domain format kontrolü
    pattern = r'^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$'
    
    if not re.match(pattern, domain):
        return {
            'is_valid': False,
            'message': 'Geçersiz domain formatı.'
        }
    
    # DNS kontrolü (opsiyonel)
    try:
        import socket
        socket.gethostbyname(domain)
        return {
            'is_valid': True,
            'message': 'Domain geçerli ve DNS kaydı mevcut.'
        }
    except socket.gaierror:
        return {
            'is_valid': True,
            'message': 'Domain geçerli ancak DNS kaydı henüz aktif değil.'
        }
```

---

## ⚠️ Önemli Notlar

1. **DNS Propagation**: DNS değişikliklerinin aktif olması 1-48 saat sürebilir
2. **SSL Sertifikası**: Let's Encrypt sertifikaları 90 günde bir yenilenmelidir
3. **Wildcard Domain**: `*.saas2026.com` gibi wildcard domain'ler için özel yapılandırma gerekir
4. **Custom Domain**: Her custom domain için ayrı SSL sertifikası gerekir
5. **Subdomain**: Subdomain'ler ana domain'in SSL sertifikasını kullanabilir (wildcard SSL ile)

---

## 🔍 Sorun Giderme

### Domain Çalışmıyor

1. **DNS Kontrolü**: `nslookup otelim.com` komutu ile DNS kaydını kontrol edin
2. **Nginx Kontrolü**: Nginx loglarını kontrol edin (`/var/log/nginx/error.log`)
3. **Django Kontrolü**: Django loglarını kontrol edin
4. **Domain Veritabanı**: Admin panelde domain'in doğru kayıtlı olduğundan emin olun

### SSL Sertifikası Sorunları

1. **Sertifika Kontrolü**: `sudo certbot certificates` ile sertifikaları listeleyin
2. **Yenileme**: `sudo certbot renew` ile sertifikaları yenileyin
3. **Nginx Yeniden Başlatma**: `sudo systemctl reload nginx`

---

## 📚 Kaynaklar

- [Django Tenants Dokümantasyonu](https://django-tenants.readthedocs.io/)
- [Let's Encrypt Dokümantasyonu](https://letsencrypt.org/docs/)
- [Cloudflare DNS Dokümantasyonu](https://developers.cloudflare.com/dns/)

---

**Son Güncelleme**: 2024




## 📋 İçindekiler

1. [Genel Bakış](#genel-bakış)
2. [Tenant Domain Yapısı](#tenant-domain-yapısı)
3. [Custom Domain Ayarları](#custom-domain-ayarları)
4. [DNS Yapılandırması](#dns-yapılandırması)
5. [Otomatik Yönlendirme](#otomatik-yönlendirme)
6. [Domain Firması Ayarları](#domain-firması-ayarları)

---

## 🎯 Genel Bakış

Bu sistem **Django Tenants** kullanarak multi-tenant SaaS yapısında çalışmaktadır. Her tenant'ın kendi domain'i veya subdomain'i olabilir.

### Domain Tipleri

1. **Primary Domain (Ana Domain)**: Tenant'ın varsayılan domain'i
2. **Custom Domain (Özel Domain)**: Tenant'ın kendi domain'i (örn: `otelim.com`)
3. **Subdomain (Alt Domain)**: Ana domain'in alt domain'i (örn: `otel1.saas2026.com`)

---

## 🏗️ Tenant Domain Yapısı

### Model Yapısı

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
    ssl_certificate = TextField(blank=True)
```

### Domain Ekleme

#### 1. Admin Panelden Ekleme

```
Admin Panel → Domains → Add Domain

Domain: otel1.saas2026.com
Tenant: Test Oteli
Domain Type: subdomain
Is Primary: Yes
SSL Enabled: Yes (opsiyonel)

[Save]
```

#### 2. Management Command ile Ekleme

```bash
python manage.py add_tenant_domain \
    --tenant-schema=tenant_otel1 \
    --domain=otel1.saas2026.com \
    --is-primary
```

---

## 🌐 Custom Domain Ayarları

### Custom Domain Nasıl Çalışır?

1. **Tenant Custom Domain Ekler**: `otelim.com`
2. **DNS Ayarları Yapılır**: Domain firmasında DNS kayıtları eklenir
3. **Sistem Domain'i Tanır**: Django Tenants otomatik olarak domain'i tenant'a yönlendirir

### Custom Domain Ekleme Adımları

#### Adım 1: Django Admin'de Domain Ekle

```
Admin Panel → Domains → Add Domain

Domain: otelim.com
Tenant: Test Oteli
Domain Type: custom
Is Primary: No (çünkü zaten primary domain var)
SSL Enabled: Yes

[Save]
```

#### Adım 2: DNS Kayıtları Ekle

Domain firmanızın DNS yönetim paneline gidin ve şu kayıtları ekleyin:

**A Record (Ana Domain):**
```
Type: A
Name: @
Value: [VPS_IP_ADRESINIZ]  (örn: 123.45.67.89)
TTL: 3600
```

**A Record (WWW):**
```
Type: A
Name: www
Value: [VPS_IP_ADRESINIZ]
TTL: 3600
```

**CNAME Record (Wildcard - Opsiyonel):**
```
Type: CNAME
Name: *
Value: saas2026.com
TTL: 3600
```

#### Adım 3: SSL Sertifikası

SSL sertifikası otomatik olarak Let's Encrypt ile oluşturulur:

```bash
# Wildcard SSL için
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d otelim.com \
  -d *.otelim.com \
  --email info@otelim.com \
  --agree-tos \
  --non-interactive
```

---

## 🔧 DNS Yapılandırması

### DNS Kayıt Tipleri

#### 1. A Record (IPv4 Adresi)

**Kullanım**: Domain'i bir IP adresine yönlendirmek için

```
Type: A
Name: @ (veya boş)
Value: 123.45.67.89
TTL: 3600
```

**Örnekler:**
- `@` → `123.45.67.89` (ana domain)
- `www` → `123.45.67.89` (www subdomain)
- `*` → `123.45.67.89` (wildcard - tüm subdomain'ler)

#### 2. CNAME Record (Canonical Name)

**Kullanım**: Bir domain'i başka bir domain'e yönlendirmek için

```
Type: CNAME
Name: www
Value: saas2026.com
TTL: 3600
```

#### 3. MX Record (Mail Exchange)

**Kullanım**: E-posta sunucusu için (opsiyonel)

```
Type: MX
Name: @
Value: mail.saas2026.com
Priority: 10
TTL: 3600
```

---

## 🔄 Otomatik Yönlendirme

### Django Tenants Middleware

Sistem otomatik olarak domain'i tenant'a yönlendirir:

```python
# settings.py
MIDDLEWARE = [
    'django_tenants.middleware.main.TenantMainMiddleware',
    # ... diğer middleware'ler
]

# Tenant ayarları
TENANT_MODEL = "tenants.Tenant"
DOMAIN_MODEL = "tenants.Domain"
```

### Yönlendirme Mantığı

1. **Request Gelir**: `http://otelim.com/`
2. **Middleware Devreye Girer**: `TenantMainMiddleware`
3. **Domain Kontrolü**: Domain veritabanında aranır
4. **Tenant Bulunur**: Domain'e bağlı tenant bulunur
5. **Schema Değiştirilir**: Tenant'ın schema'sına geçilir
6. **Request İşlenir**: Normal Django request işleme devam eder

### Nginx Yapılandırması

```nginx
# /etc/nginx/sites-available/saas2026
server {
    listen 80;
    server_name *.saas2026.com saas2026.com;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Custom domain için
server {
    listen 80;
    server_name otelim.com www.otelim.com;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🏢 Domain Firması Ayarları

### Popüler Domain Firmaları için DNS Ayarları

#### 1. Cloudflare

**Adımlar:**

1. Cloudflare'e giriş yapın
2. Domain'inizi ekleyin
3. Nameserver'ları domain firmanızda güncelleyin
4. DNS kayıtlarını ekleyin:

```
Type: A
Name: @
IPv4: [VPS_IP]
Proxy: DNS Only (gri bulut)

Type: A
Name: www
IPv4: [VPS_IP]
Proxy: DNS Only
```

#### 2. Namecheap

**Adımlar:**

1. Namecheap'e giriş yapın
2. Domain List → Domain'inizi seçin
3. "Advanced DNS" sekmesine gidin
4. DNS kayıtlarını ekleyin:

```
Type: A Record
Host: @
Value: [VPS_IP]
TTL: Automatic

Type: A Record
Host: www
Value: [VPS_IP]
TTL: Automatic
```

#### 3. GoDaddy

**Adımlar:**

1. GoDaddy'ye giriş yapın
2. My Products → Domain'inizi seçin
3. DNS → Manage DNS
4. DNS kayıtlarını ekleyin:

```
Type: A
Name: @
Value: [VPS_IP]
TTL: 600

Type: A
Name: www
Value: [VPS_IP]
TTL: 600
```

#### 4. Türkiye Registrar'ları (Natro, Turhost, vb.)

**Adımlar:**

1. Registrar paneline giriş yapın
2. Domain listesi → Domain'inizi seçin
3. "DNS Yönetimi" veya "Nameserver Ayarları"
4. DNS kayıtlarını ekleyin:

```
Tip: A Kaydı
İsim: @ (veya boş)
Değer: [VPS_IP]
TTL: 3600

Tip: A Kaydı
İsim: www
Değer: [VPS_IP]
TTL: 3600
```

---

## 📝 Tenant Domain Ayarları (Website Builder)

### Website Builder'da Domain Ayarlama

Website Builder modülünde tenant'lar kendi domain'lerini ayarlayabilir:

#### 1. Website Ayarları Sayfası

```
Website Builder → Websites → [Website Seç] → Settings → Domain
```

#### 2. Domain Ekleme Formu

```html
<form id="domainForm">
    <div class="form-group">
        <label>Custom Domain</label>
        <input type="text" name="domain" placeholder="otelim.com" />
        <small>Domain'inizi buraya girin (http:// veya https:// olmadan)</small>
    </div>
    <button type="submit">Domain Ayarla</button>
</form>
```

#### 3. DNS Yönlendirme Talimatları

Sistem otomatik olarak DNS yapılandırma talimatlarını gösterir:

```
✅ Domain başarıyla ayarlandı!

📋 DNS Yapılandırması:

Domain firmanızın DNS yönetim paneline gidin ve şu kayıtları ekleyin:

A Record:
  Type: A
  Name: @
  Value: 123.45.67.89
  TTL: 3600

A Record (WWW):
  Type: A
  Name: www
  Value: 123.45.67.89
  TTL: 3600

⚠️ DNS değişikliklerinin aktif olması 1-48 saat sürebilir.
```

---

## 🔐 SSL Sertifikası

### Let's Encrypt ile Otomatik SSL

#### 1. Certbot Kurulumu

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx
```

#### 2. SSL Sertifikası Oluşturma

```bash
# Tek domain için
sudo certbot --nginx -d otelim.com -d www.otelim.com

# Wildcard için (Cloudflare DNS kullanarak)
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d otelim.com \
  -d *.otelim.com
```

#### 3. Otomatik Yenileme

```bash
# Cron job ekle
sudo crontab -e

# Ekle:
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 🚀 Sistem Nasıl Çalışır?

### 1. Domain İsteği Gelir

```
Kullanıcı: http://otelim.com/ → Tarayıcı
```

### 2. DNS Çözümleme

```
DNS Server: otelim.com → 123.45.67.89 (VPS IP)
```

### 3. Nginx Yönlendirme

```
Nginx: Request'i Django'ya yönlendir
Host Header: otelim.com
```

### 4. Django Tenants Middleware

```python
# django_tenants/middleware/main.py
class TenantMainMiddleware:
    def process_request(self, request):
        # Domain'i al
        host = request.get_host().split(':')[0]
        
        # Domain'i veritabanında ara
        domain = Domain.objects.get(domain=host)
        
        # Tenant'ı bul
        tenant = domain.tenant
        
        # Schema'yı değiştir
        connection.set_tenant(tenant)
        
        # Request'i işle
        return None
```

### 5. Request İşleme

```
Django: Tenant schema'sında request'i işle
Response: Tenant'a özel içerik döndür
```

---

## 📊 Domain Durum Kontrolü

### Domain Doğrulama

```python
# apps/tenant_apps/website_builder/publish_utils.py
def validate_domain(domain):
    """
    Domain doğrulama
    """
    import re
    
    # Domain format kontrolü
    pattern = r'^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$'
    
    if not re.match(pattern, domain):
        return {
            'is_valid': False,
            'message': 'Geçersiz domain formatı.'
        }
    
    # DNS kontrolü (opsiyonel)
    try:
        import socket
        socket.gethostbyname(domain)
        return {
            'is_valid': True,
            'message': 'Domain geçerli ve DNS kaydı mevcut.'
        }
    except socket.gaierror:
        return {
            'is_valid': True,
            'message': 'Domain geçerli ancak DNS kaydı henüz aktif değil.'
        }
```

---

## ⚠️ Önemli Notlar

1. **DNS Propagation**: DNS değişikliklerinin aktif olması 1-48 saat sürebilir
2. **SSL Sertifikası**: Let's Encrypt sertifikaları 90 günde bir yenilenmelidir
3. **Wildcard Domain**: `*.saas2026.com` gibi wildcard domain'ler için özel yapılandırma gerekir
4. **Custom Domain**: Her custom domain için ayrı SSL sertifikası gerekir
5. **Subdomain**: Subdomain'ler ana domain'in SSL sertifikasını kullanabilir (wildcard SSL ile)

---

## 🔍 Sorun Giderme

### Domain Çalışmıyor

1. **DNS Kontrolü**: `nslookup otelim.com` komutu ile DNS kaydını kontrol edin
2. **Nginx Kontrolü**: Nginx loglarını kontrol edin (`/var/log/nginx/error.log`)
3. **Django Kontrolü**: Django loglarını kontrol edin
4. **Domain Veritabanı**: Admin panelde domain'in doğru kayıtlı olduğundan emin olun

### SSL Sertifikası Sorunları

1. **Sertifika Kontrolü**: `sudo certbot certificates` ile sertifikaları listeleyin
2. **Yenileme**: `sudo certbot renew` ile sertifikaları yenileyin
3. **Nginx Yeniden Başlatma**: `sudo systemctl reload nginx`

---

## 📚 Kaynaklar

- [Django Tenants Dokümantasyonu](https://django-tenants.readthedocs.io/)
- [Let's Encrypt Dokümantasyonu](https://letsencrypt.org/docs/)
- [Cloudflare DNS Dokümantasyonu](https://developers.cloudflare.com/dns/)

---

**Son Güncelleme**: 2024




