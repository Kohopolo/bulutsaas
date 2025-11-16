# Ubuntu 24.04 Sorun Giderme Rehberi
## Docker ve Django Multi-Tenant Projesi - Ubuntu 24.04 Uyumluluk

Ubuntu 24.04 ile ilgili potansiyel sorunlar ve çözümleri.

---

## 🔍 Potansiyel Sorunlar

### 1. Python Versiyonu

**Sorun**: Ubuntu 24.04 varsayılan Python 3.12 kullanır, proje Python 3.11 için tasarlanmış.

**Kontrol**:
```bash
python3 --version
# Ubuntu 24.04: Python 3.12.x
# Proje: Python 3.11
```

**Çözüm**: Dockerfile'da Python 3.11 kullanılıyor, sorun yok ✅

### 2. Docker Versiyonu

**Sorun**: Ubuntu 24.04'te Docker Compose V2 kullanılıyor, bazı komutlar farklı olabilir.

**Kontrol**:
```bash
docker --version
docker compose version
```

**Çözüm**: `docker compose` (boşluklu) kullanın, `docker-compose` (tireli) değil.

### 3. Systemd ve Docker Entegrasyonu

**Sorun**: Ubuntu 24.04'te systemd-resolved Docker ile çakışabilir.

**Kontrol**:
```bash
systemctl status systemd-resolved
```

**Çözüm**: Genellikle sorun yok, Docker network kendi DNS'ini kullanır.

### 4. Volume Mount Sorunları

**Sorun**: Ubuntu 24.04'te volume mount izinleri farklı olabilir.

**Kontrol**:
```bash
ls -la /docker/bulutsaas/apps/tenants/middleware/
```

**Çözüm**: Volume mount'ları kontrol edin.

---

## ✅ Çözüm: Docker Compose Düzeltmesi

Ana sorun: `docker-compose.yml` dosyasında web servisinde volume mount eksik. Middleware dosyaları container'a kopyalanmıyor.

### 1. docker-compose.yml Düzeltmesi

```yaml
web:
  volumes:
    - .:/app  # EKLE - Proje dosyalarını mount et
    - static_volume:/app/staticfiles
    - media_volume:/app/media
```

### 2. VPS'te Düzeltme Komutları

```bash
cd /docker/bulutsaas

# 1. docker-compose.yml'yi düzenle
nano docker-compose.yml

# Web servisinin volumes bölümünü bulun ve şununla değiştirin:
# volumes:
#   - .:/app  # EKLE
#   - static_volume:/app/staticfiles
#   - media_volume:/app/media
#   - ./logs:/app/logs

# 2. Container'ları durdur
docker compose down

# 3. Container'ları yeniden oluştur
docker compose up -d --build

# 4. Middleware dosyalarının varlığını kontrol et
docker exec saas2026_web ls -la /app/apps/tenants/middleware/

# 5. Import testi
docker exec saas2026_web python -c "
import sys
sys.path.insert(0, '/app')
from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware
print('✅ Middleware import başarılı!')
"
```

---

## 🔧 Ubuntu 24.04 Özel Düzeltmeler

### 1. Docker Compose Komut Farkı

Ubuntu 24.04'te:
```bash
# Eski (çalışmayabilir)
docker-compose up -d

# Yeni (doğru)
docker compose up -d
```

### 2. Python 3.12 Uyumluluğu

Eğer host'ta Python 3.12 varsa ve sorun yaşıyorsanız:

```bash
# Python 3.11 kurulumu (host için)
sudo apt install -y python3.11 python3.11-venv

# Veya Docker kullanın (zaten kullanıyorsunuz ✅)
```

### 3. Systemd-Resolved Çakışması

Eğer DNS sorunları varsa:

```bash
# Docker DNS ayarları
sudo nano /etc/docker/daemon.json
```

İçerik:
```json
{
  "dns": ["8.8.8.8", "8.8.4.4"]
}
```

```bash
sudo systemctl restart docker
```

---

## 📝 Hızlı Düzeltme Scripti

```bash
#!/bin/bash
# Ubuntu 24.04 Docker Sorun Giderme Scripti

cd /docker/bulutsaas

echo "=== 1. Docker Compose Versiyonu ==="
docker compose version

echo "=== 2. Python Versiyonu (Host) ==="
python3 --version

echo "=== 3. Container Python Versiyonu ==="
docker exec saas2026_web python --version

echo "=== 4. Middleware Dosyaları Kontrolü ==="
# Local'de kontrol
ls -la apps/tenants/middleware/

# Container'da kontrol
docker exec saas2026_web ls -la /app/apps/tenants/middleware/ 2>/dev/null || echo "❌ Container'da dosyalar yok"

echo "=== 5. docker-compose.yml Volume Mount Kontrolü ==="
grep -A 5 "volumes:" docker-compose.yml | grep -A 5 "web:"

echo "=== 6. Container'ları Yeniden Oluşturma ==="
docker compose down
docker compose up -d --build

echo "=== 7. Middleware Import Testi ==="
sleep 10
docker exec saas2026_web python -c "
import sys
sys.path.insert(0, '/app')
try:
    from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware
    print('✅ Middleware import başarılı!')
except Exception as e:
    print(f'❌ Import hatası: {e}')
"

echo "=== 8. Health Check Testi ==="
curl -s http://72.62.35.155/health/ || echo "❌ Health check başarısız"
```

---

## ✅ Sonuç

**Ubuntu 24.04 sorun kaynağı değil.** 

Asıl sorun:
- ❌ docker-compose.yml'de volume mount eksik
- ❌ Middleware dosyaları container'a kopyalanmıyor
- ❌ Container restart oluyor çünkü middleware import edilemiyor

**Çözüm**: docker-compose.yml'yi düzeltin ve container'ları yeniden oluşturun.

