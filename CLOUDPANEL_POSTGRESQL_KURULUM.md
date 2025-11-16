# CloudPanel PostgreSQL Kurulum Rehberi

## 🗄️ CloudPanel'de PostgreSQL Kurulumu

CloudPanel'de PostgreSQL'i iki şekilde kurabilirsiniz:
1. **CloudPanel Database Manager** (Önerilen)
2. **Docker Compose ile** (Mevcut kurulumunuz için)

---

## ✅ Yöntem 1: CloudPanel Database Manager (ÖNERİLEN)

### Adım 1: Database Oluşturma

1. **CloudPanel → Databases → Create Database**
2. **Database Type**: PostgreSQL seçin
3. **Database Name**: `saas_db` (veya istediğiniz isim)
4. **Database User**: `saas_user` (veya istediğiniz kullanıcı)
5. **Database Password**: Güçlü bir şifre oluşturun
6. **Create**

### Adım 2: Bağlantı Bilgilerini Not Edin

CloudPanel size şu bilgileri verecek:
```
Host: localhost (veya 127.0.0.1)
Port: 5432
Database: saas_db
User: saas_user
Password: <oluşturduğunuz_şifre>
```

### Adım 3: .env Dosyasını Güncelle

```env
DATABASE_URL=postgresql://saas_user:şifre@localhost:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=şifre
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
```

---

## ✅ Yöntem 2: Docker Compose ile (Mevcut Kurulum)

Mevcut Docker Compose kurulumunuzda PostgreSQL zaten var. CloudPanel'de Docker Compose site oluşturduğunuzda PostgreSQL otomatik kurulur.

### Adım 1: Docker Compose Site Oluşturma

1. **CloudPanel → Sites → Create Site**
2. **Site Type**: Docker Compose
3. **docker-compose.yml** dosyanızı yükleyin
4. **.env** dosyanızı yükleyin
5. **Create**

### Adım 2: PostgreSQL Container Kontrolü

Docker Compose kurulumunuzda PostgreSQL container'ı otomatik başlar:
```yaml
db:
  image: postgres:15-alpine
  container_name: saas2026_db
  environment:
    POSTGRES_DB: saas_db
    POSTGRES_USER: saas_user
    POSTGRES_PASSWORD: saas_password_2026
```

---

## 🔧 CloudPanel Database Manager Kullanımı

### PostgreSQL Database Oluşturma:

1. **CloudPanel → Databases → Create Database**
2. **Database Type**: PostgreSQL
3. **Database Name**: `saas_db`
4. **Database User**: `saas_user`
5. **Password**: Güçlü şifre oluşturun
6. **Create**

### Database Yönetimi:

1. **CloudPanel → Databases → Database Seç**
2. **phpPgAdmin** veya **pgAdmin** ile yönetim
3. **Backup/Restore** işlemleri
4. **User Management**

---

## 📋 Docker Compose ile PostgreSQL (Mevcut Kurulum)

### Mevcut docker-compose.yml:

```yaml
db:
  image: postgres:15-alpine
  container_name: saas2026_db
  environment:
    POSTGRES_DB: saas_db
    POSTGRES_USER: saas_user
    POSTGRES_PASSWORD: saas_password_2026
    POSTGRES_HOST_AUTH_METHOD: trust
  volumes:
    - postgres_data:/var/lib/postgresql/data
  ports:
    - "5432:5432"
  networks:
    - saas_network
  restart: unless-stopped
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U saas_user -d saas_db"]
    interval: 10s
    timeout: 5s
    retries: 5
```

### CloudPanel'de Docker Compose Site:

1. **Sites → Create Site**
2. **Docker Compose** seçin
3. **docker-compose.yml** yükleyin
4. **.env** yükleyin
5. **Create**

PostgreSQL otomatik kurulur ve çalışır!

---

## 🔍 PostgreSQL Bağlantı Kontrolü

### CloudPanel Database Manager ile:

1. **CloudPanel → Databases → Database Seç**
2. **Connection Info** sekmesine bakın
3. **Test Connection** butonuna tıklayın

### Docker Compose ile:

```bash
# Container içinden test
docker exec saas2026_db psql -U saas_user -d saas_db -c "SELECT version();"

# Veya CloudPanel → Sites → Site Seç → Containers → db → Logs
```

---

## 📝 .env Dosyası Güncelleme

### CloudPanel Database Manager Kullanıyorsanız:

```env
DATABASE_URL=postgresql://saas_user:şifre@localhost:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=şifre
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
```

### Docker Compose Kullanıyorsanız (Mevcut):

```env
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026
```

**Not:** Docker Compose'da `db` hostname'i kullanılır (container adı)

---

## 🎯 Öneri

### Docker Compose Kullanın (ÖNERİLEN)

**Neden:**
- ✅ Mevcut kurulumunuzu kullanabilirsiniz
- ✅ Tüm servisler birlikte çalışır (Django, Celery, Redis, PostgreSQL, Nginx)
- ✅ Environment variables yönetimi kolay
- ✅ Backup yönetimi kolay

**Adımlar:**
1. **CloudPanel → Sites → Create Site**
2. **Docker Compose** seçin
3. **docker-compose.yml** yükleyin
4. **.env** yükleyin
5. **Create**

PostgreSQL otomatik kurulur!

---

## 🔧 CloudPanel Database Manager (Alternatif)

Eğer Docker Compose kullanmıyorsanız:

1. **CloudPanel → Databases → Create Database**
2. **PostgreSQL** seçin
3. **Database bilgilerini** girin
4. **Create**
5. **.env dosyasını** güncelleyin

---

## 📋 PostgreSQL Versiyonları

CloudPanel'de genellikle şu PostgreSQL versiyonları mevcuttur:
- PostgreSQL 15 (Önerilen)
- PostgreSQL 14
- PostgreSQL 13
- PostgreSQL 12

**Öneri:** PostgreSQL 15 kullanın (docker-compose.yml'deki gibi)

---

## ✅ Sonuç ve Öneri

### Docker Compose Kullanın (ÖNERİLEN) ⭐

**Neden:**
- ✅ Mevcut kurulumunuzu kullanabilirsiniz
- ✅ PostgreSQL otomatik kurulur
- ✅ Tüm servisler birlikte çalışır

**Adımlar:**
1. **CloudPanel → Sites → Create Site**
2. **Docker Compose** seçin
3. **docker-compose.yml** yükleyin
4. **.env** yükleyin
5. **Create**

PostgreSQL otomatik kurulur ve çalışır!

---

## 🆘 Sorun Giderme

### PostgreSQL Bağlantı Hatası:

1. **CloudPanel → Sites → Site Seç → Containers → db → Logs**
2. **PostgreSQL loglarını kontrol edin**
3. **Environment variables kontrol edin**

### Database Bulunamadı:

1. **CloudPanel → Databases → Database Seç**
2. **Connection Info** kontrol edin
3. **.env dosyasını** güncelleyin

---

## 📝 Özet

**ÖNERİLEN:** Docker Compose kullanın

**PostgreSQL Kurulumu:**
- ✅ Docker Compose ile otomatik kurulur
- ✅ CloudPanel → Sites → Docker Compose Site oluşturun
- ✅ docker-compose.yml ve .env dosyalarını yükleyin

**ALTERNATİF:** CloudPanel Database Manager

**PostgreSQL Kurulumu:**
- ✅ CloudPanel → Databases → Create Database
- ✅ PostgreSQL seçin
- ✅ Database bilgilerini girin

**Sonuç:** Docker Compose kullanırsanız PostgreSQL otomatik kurulur!

