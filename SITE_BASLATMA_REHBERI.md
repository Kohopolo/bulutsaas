# Site Başlatma Rehberi

## Virtual Environment Aktifleştirme

### Windows PowerShell:
```powershell
. venv\Scripts\Activate.ps1
```

### Windows CMD:
```cmd
venv\Scripts\activate.bat
```

## Django Sunucusunu Başlatma

### Standart Başlatma:
```bash
python manage.py runserver
```

### Tüm IP'lerden Erişim İçin:
```bash
python manage.py runserver 0.0.0.0:8000
```

### Belirli Port İçin:
```bash
python manage.py runserver 0.0.0.0:8080
```

## Domain Yapılandırması

### 1. Hosts Dosyası Kontrolü
Windows hosts dosyası: `C:\Windows\System32\drivers\etc\hosts`

Gerekli satırlar:
```
127.0.0.1 test-otel.localhost
127.0.0.1 localhost
```

### 2. Domain Kontrolü
```bash
python manage.py shell -c "from apps.tenants.models import Domain; print([d.domain for d in Domain.objects.all()])"
```

### 3. Domain Oluşturma (Gerekirse)
```bash
python manage.py shell
```

```python
from apps.tenants.models import Tenant, Domain
tenant = Tenant.objects.get(schema_name='test_otel')
Domain.objects.get_or_create(domain='test-otel.localhost', tenant=tenant, is_primary=True)
```

## Sorun Giderme

### ERR_CONNECTION_REFUSED Hatası:
1. ✅ Django sunucusu çalışıyor mu kontrol et: `netstat -ano | findstr :8000`
2. ✅ Virtual environment aktif mi kontrol et: `python --version`
3. ✅ Hosts dosyasında domain var mı kontrol et
4. ✅ Domain veritabanında kayıtlı mı kontrol et

### Port Kullanımda Hatası:
```bash
# Farklı port kullan
python manage.py runserver 0.0.0.0:8080
```

### Virtual Environment Hatası:
```bash
# Yeniden oluştur
python -m venv venv
. venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## Hızlı Başlatma Komutu

```powershell
# Virtual environment aktif et
. venv\Scripts\Activate.ps1

# Django sunucusunu başlat
python manage.py runserver 0.0.0.0:8000
```

---

**Son Güncelleme:** 2025-11-14




## Virtual Environment Aktifleştirme

### Windows PowerShell:
```powershell
. venv\Scripts\Activate.ps1
```

### Windows CMD:
```cmd
venv\Scripts\activate.bat
```

## Django Sunucusunu Başlatma

### Standart Başlatma:
```bash
python manage.py runserver
```

### Tüm IP'lerden Erişim İçin:
```bash
python manage.py runserver 0.0.0.0:8000
```

### Belirli Port İçin:
```bash
python manage.py runserver 0.0.0.0:8080
```

## Domain Yapılandırması

### 1. Hosts Dosyası Kontrolü
Windows hosts dosyası: `C:\Windows\System32\drivers\etc\hosts`

Gerekli satırlar:
```
127.0.0.1 test-otel.localhost
127.0.0.1 localhost
```

### 2. Domain Kontrolü
```bash
python manage.py shell -c "from apps.tenants.models import Domain; print([d.domain for d in Domain.objects.all()])"
```

### 3. Domain Oluşturma (Gerekirse)
```bash
python manage.py shell
```

```python
from apps.tenants.models import Tenant, Domain
tenant = Tenant.objects.get(schema_name='test_otel')
Domain.objects.get_or_create(domain='test-otel.localhost', tenant=tenant, is_primary=True)
```

## Sorun Giderme

### ERR_CONNECTION_REFUSED Hatası:
1. ✅ Django sunucusu çalışıyor mu kontrol et: `netstat -ano | findstr :8000`
2. ✅ Virtual environment aktif mi kontrol et: `python --version`
3. ✅ Hosts dosyasında domain var mı kontrol et
4. ✅ Domain veritabanında kayıtlı mı kontrol et

### Port Kullanımda Hatası:
```bash
# Farklı port kullan
python manage.py runserver 0.0.0.0:8080
```

### Virtual Environment Hatası:
```bash
# Yeniden oluştur
python -m venv venv
. venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## Hızlı Başlatma Komutu

```powershell
# Virtual environment aktif et
. venv\Scripts\Activate.ps1

# Django sunucusunu başlat
python manage.py runserver 0.0.0.0:8000
```

---

**Son Güncelleme:** 2025-11-14




