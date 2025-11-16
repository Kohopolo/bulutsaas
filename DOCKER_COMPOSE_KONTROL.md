# Docker Compose Yapılandırması Kontrol

## ⚠️ Önemli Eksiklik Var!

`docker-compose.yml` dosyanızda bir önemli eksiklik var.

---

## ❌ Eksik: Health Check Condition

### Web Service - depends_on

**Şu anki (Yanlış):**
```yaml
depends_on:
  - db
  - redis
```

**Olması Gereken (Doğru):**
```yaml
depends_on:
  db:
    condition: service_healthy
  redis:
    condition: service_healthy
```

**Neden Önemli:**
- `condition: service_healthy` olmadan web container'ı db ve redis hazır olmadan başlayabilir
- Bu, database bağlantı hatalarına neden olabilir
- `wait_for_db` komutu çalışsa bile, race condition oluşabilir

---

## ✅ Doğru Olanlar

### 1. Database Service ✅
- Image: `postgres:15-alpine` ✅
- Healthcheck: Doğru ✅
- Volumes: Doğru ✅
- Ports: Doğru ✅

### 2. Redis Service ✅
- Image: `redis:7-alpine` ✅
- Healthcheck: Doğru ✅
- Volumes: Doğru ✅
- Ports: Doğru ✅

### 3. Web Service ✅ (depends_on hariç)
- Build: Doğru ✅
- Command: Doğru ✅
- Volumes: Doğru ✅
- Ports: Doğru ✅
- Healthcheck: Doğru ✅
- **depends_on**: ⚠️ Eksik (`condition: service_healthy`)

### 4. Celery Service ✅
- Build: Doğru ✅
- Command: Doğru ✅
- Volumes: Doğru ✅
- depends_on: Doğru ✅

### 5. Celery Beat Service ✅
- Build: Doğru ✅
- Command: Doğru ✅
- Volumes: Doğru ✅
- depends_on: Doğru ✅

### 6. Nginx Service ✅
- Build: Doğru ✅
- Ports: Doğru ✅
- Volumes: Doğru ✅
- depends_on: Doğru ✅

### 7. Volumes ✅
- Tüm volume'lar doğru tanımlanmış ✅

### 8. Networks ✅
- Network yapılandırması doğru ✅

---

## 🔧 Düzeltilmesi Gereken

### Web Service - depends_on Düzeltmesi

**Şu anki:**
```yaml
web:
  # ...
  depends_on:
    - db
    - redis
```

**Olması Gereken:**
```yaml
web:
  # ...
  depends_on:
    db:
      condition: service_healthy
    redis:
      condition: service_healthy
```

---

## 📋 Düzeltilmiş Web Service Bölümü

```yaml
  # Django Web Application
  web:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: saas2026_web
    command: >
      sh -c "
      python manage.py wait_for_db &&
      python manage.py migrate_schemas --shared &&
      python manage.py migrate_schemas &&
      python manage.py collectstatic --noinput &&
      gunicorn config.wsgi:application --bind 0.0.0.0:8000 --workers 4 --timeout 120
      "
    volumes:
      - .:/app  # Proje dosyalarını mount et (middleware dahil)
      - static_volume:/app/staticfiles
      - media_volume:/app/media
      - ./logs:/app/logs
    ports:
      - "127.0.0.1:8000:8000"  # Sadece localhost'tan erişilebilir (Nginx üzerinden)
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - saas_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health/"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

---

## 🔧 Adım Adım Düzeltme

### Adım 1: docker-compose.yml Dosyasını Düzenle

`web` service'inin `depends_on` bölümünü şu şekilde değiştirin:

**Değiştir:**
```yaml
depends_on:
  - db
  - redis
```

**Şununla:**
```yaml
depends_on:
  db:
    condition: service_healthy
  redis:
    condition: service_healthy
```

### Adım 2: Container'ları Yeniden Başlat

```bash
cd /docker/bulutsaas

# Container'ları durdur
docker compose down

# Yeniden başlat
docker compose up -d

# Logları kontrol et
docker compose logs web --tail=50
```

---

## ⚠️ Önemli Notlar

1. **Health Check Condition**: Web container'ının db ve redis hazır olmadan başlamasını önler
2. **Race Condition**: `condition: service_healthy` olmadan web container'ı erken başlayabilir
3. **Database Bağlantı Hataları**: Health check condition olmadan database bağlantı hataları oluşabilir

---

## ✅ Özet

**Eksik:**
- ❌ Web service'inde `depends_on` için `condition: service_healthy` eksik

**Doğru Olanlar:**
- ✅ Tüm service'ler doğru yapılandırılmış
- ✅ Healthcheck'ler doğru
- ✅ Volume'lar doğru
- ✅ Network yapılandırması doğru

**Sonuç**: `depends_on` bölümünü düzeltin ve container'ları yeniden başlatın!

