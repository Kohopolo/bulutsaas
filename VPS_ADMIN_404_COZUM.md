# Admin Panel 404 Hatası Çözüm Rehberi
## IP Adresi ile Admin Paneline Erişim Sorunu

---

## 🔍 Sorun Tespiti

### 1. Logları Kontrol Edin

```bash
# Django loglarını kontrol edin
docker compose logs web --tail=100 | grep -i "404\|error\|admin\|72.62.35.155"

# Nginx loglarını kontrol edin
docker compose logs nginx --tail=50

# Tüm web loglarını görün
docker compose logs web --tail=100
```

### 2. Curl ile Test Edin

```bash
# Host header ile test
curl -v -H "Host: 72.62.35.155" http://localhost/admin/ 2>&1 | head -50

# Direkt IP ile test
curl -v http://72.62.35.155/admin/ 2>&1 | head -50

# Container'ın port 8000'ini direkt test
curl -v http://localhost:8000/admin/ 2>&1 | head -50
```

### 3. Middleware ve URL Routing Testi

```bash
docker exec saas2026_web python manage.py shell -c "
from django.test import RequestFactory
from django.db import connection
from django_tenants.utils import get_public_schema_name
from django.urls import resolve

# Public schema'ya geç
connection.set_schema_to_public()

# Request oluştur
factory = RequestFactory()
request = factory.get('/admin/')
request.META['HTTP_HOST'] = '72.62.35.155'
request.META['SERVER_NAME'] = '72.62.35.155'

# URL resolve testi
try:
    from django.conf import settings
    from django.urls import set_urlconf
    set_urlconf(settings.PUBLIC_SCHEMA_URLCONF)
    
    resolver = resolve('/admin/')
    print('✅ URL resolve başarılı')
    print('URL name:', resolver.url_name)
    print('View:', resolver.func)
except Exception as e:
    print('❌ URL resolve hatası:', e)
    import traceback
    traceback.print_exc()
"
```

---

## 🔧 Olası Çözümler

### Çözüm 1: Nginx Host Header Kontrolü

Nginx'in Host header'ını doğru iletip iletmediğini kontrol edin:

```bash
# Nginx config'i kontrol edin
cat nginx/conf.d/default.conf | grep -A 10 "location /"
```

### Çözüm 2: Django ALLOWED_HOSTS Kontrolü

```bash
docker exec saas2026_web python manage.py shell -c "
from django.conf import settings
print('ALLOWED_HOSTS:', settings.ALLOWED_HOSTS)
print('DEBUG:', settings.DEBUG)
"
```

### Çözüm 3: Container'ı Yeniden Başlatın

```bash
# Container'ları yeniden başlat
docker compose restart web nginx

# Logları izle
docker compose logs -f web
```

---

## ✅ Hızlı Test Komutları

```bash
# 1. Container durumu
docker compose ps

# 2. Health check
curl http://localhost/health/

# 3. Admin panel (container port)
curl http://localhost:8000/admin/

# 4. Admin panel (nginx üzerinden)
curl -H "Host: 72.62.35.155" http://localhost/admin/

# 5. Logları kontrol
docker compose logs web --tail=50
```

---

## 📝 Notlar

- Domain zaten mevcut: `72.62.35.155`
- Health check çalışıyor: `/health/` endpoint'i OK döndürüyor
- Landing page çalışıyor: Ana sayfa görüntüleniyor
- Admin panel 404 veriyor: `/admin/` endpoint'i bulunamıyor

Bu durumda sorun muhtemelen:
1. URL routing'de (public schema URL conf doğru yüklenmiyor)
2. Middleware'de (domain bulunuyor ama public schema'ya geçemiyor)
3. Nginx'te (Host header yanlış iletilmiş olabilir)

