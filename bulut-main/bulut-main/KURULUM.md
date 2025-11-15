# 🚀 SaaS 2026 - Hızlı Kurulum Rehberi

> **İlk kez mi görüyorsunuz? Bu dosya size projeyi nasıl çalıştıracağınızı gösterecek.**

## ✅ Proje Durumu: HAZIR!

Tüm altyapı kurulmuş ve test edilmeye hazır durumda.

---

## 📋 Kurulum Adımları

### 1️⃣ Önkoşullar

Windows bilgisayarınızda şunlar kurulu olmalı:

- ✅ **Docker Desktop** - https://www.docker.com/products/docker-desktop/
- ✅ **Git** (opsiyonel) - https://git-scm.com/

### 2️⃣ Proje Klasörü

Proje zaten burada:
```
C:\xampp\htdocs\saas2026\
```

### 3️⃣ Docker ile Başlat

PowerShell veya CMD'yi açın:

```powershell
# Proje klasörüne git
cd C:\xampp\htdocs\saas2026

# Docker servislerini başlat (ilk kez 5-10 dakika sürer)
docker-compose up -d

# Logları izle (isteğe bağlı)
docker-compose logs -f web
```

### 4️⃣ Database Migration

İlk kurulumda database'i hazırla:

```powershell
# Public schema migration
docker-compose exec web python manage.py migrate_schemas --shared

# Tüm tenant'lar için migration
docker-compose exec web python manage.py migrate_schemas

# Superuser oluştur
docker-compose exec web python manage.py createsuperuser

# Static dosyaları topla
docker-compose exec web python manage.py collectstatic --noinput
```

### 5️⃣ Tarayıcıda Aç

```
🌐 Ana Sayfa: http://localhost:8000
🔐 Admin Panel: http://localhost:8000/admin
📊 API Docs: http://localhost:8000/api/docs
```

---

## 🎯 Kullanım

### Günlük Kullanım

```powershell
# Başlat
docker-compose up -d

# Logları izle
docker-compose logs -f web

# Durdur
docker-compose down
```

### Django Komutları

```powershell
# Shell
docker-compose exec web python manage.py shell

# Migration oluştur
docker-compose exec web python manage.py makemigrations

# Migration çalıştır
docker-compose exec web python manage.py migrate_schemas

# Test
docker-compose exec web python manage.py test
```

### Database Yedekleme

```powershell
# Yedek al
docker-compose exec db pg_dump -U saas_user saas_db > backup.sql

# Geri yükle
docker-compose exec -T db psql -U saas_user saas_db < backup.sql
```

---

## 🗄️ Veritabanı Yapısı

### Public Schema (Ortak)
- `tenants` - Üye listesi
- `packages` - Paket tanımları
- `modules` - Modül sistemi
- `subscriptions` - Abonelik kayıtları

### Tenant Schema (İzole)
Her üye için otomatik `tenant_xxx` schema'sı oluşturulur.

---

## 📦 Paket ve Modül Oluşturma

### 1. Modül Oluştur

Admin Panel → Modüller → Yeni Modül

**Örnek:**
- Modül Adı: Rezervasyon Yönetimi
- Kod: reservation
- Kategori: Rezervasyon
- Mevcut İzinler:
```json
{
  "view": "Görüntüleme",
  "add": "Ekleme",
  "edit": "Düzenleme",
  "delete": "Silme",
  "checkin": "Check-in",
  "checkout": "Check-out"
}
```

### 2. Paket Oluştur

Admin Panel → Paketler → Yeni Paket

**Örnek:**
- Paket Adı: Başlangıç Paketi
- Kod: starter
- Aylık Fiyat: 299 TRY
- Limitler:
  - Maksimum Otel: 1
  - Maksimum Oda: 10
  - Maksimum Kullanıcı: 3

### 3. Pakete Modül Ekle

Paket düzenleme sayfasında → Modül seç → Yetkileri belirle

---

## 👥 Tenant (Üye) Oluşturma

Admin Panel → Üyeler → Yeni Üye

**Önemli:**
- **Schema Name**: Otomatik oluşturulur (örn: `tenant_otel_abc`)
- **Slug**: Benzersiz olmalı
- **Domain**: Otomatik oluşturulacak (örn: `otel-abc.localhost`)

---

## 🎨 UI/Tasarım Kuralları

### ⚠️ ÇOK ÖNEMLİ

Bu proje **Visual Basic masaüstü uygulama** tarzında tasarlanmıştır!

**Mutlaka Okuyun:**
- 📄 **DESIGN_STANDARD.md** - Tasarım DNA'sı
- 📄 **demo_layout.html** - Çalışır UI örneği

### Layout Yapısı (DEĞİŞMEZ!)

```
┌─────────────────────────────────────┐
│ TITLE BAR (Mavi)                    │ ← 56px
├─────────────────────────────────────┤
│ TOOLBAR (Gri)                       │ ← 48px
├──────────┬──────────────────────────┤
│ SIDEBAR  │ CONTENT AREA            │
│ 260px    │ (Flex: 1)               │
│ (Menü)   │ (İçerik)                │
├──────────┴──────────────────────────┤
│ STATUS BAR (Mavi)                   │ ← 36px
└─────────────────────────────────────┘
```

### Yeni Sayfa Oluşturma

```html
{% extends 'base.html' %}

{% block title %}Sayfa Başlığı{% endblock %}

{% block content %}
<div class="content-header">
    <div class="content-title">Başlık</div>
    <div class="content-subtitle">Alt başlık</div>
</div>

<div class="content-body">
    <!-- GroupBox ile panel -->
    <div class="groupbox">
        <div class="groupbox-header">📋 Liste</div>
        <div class="groupbox-body">
            <!-- DataGrid ile tablo -->
            <table class="datagrid">
                <thead>
                    <tr><th>Sütun 1</th></tr>
                </thead>
                <tbody>
                    <tr><td>Veri 1</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
```

---

## 🔧 Sorun Giderme

### Docker başlamıyor

```powershell
# Logları kontrol et
docker-compose logs

# Servisleri yeniden başlat
docker-compose down
docker-compose up -d --build
```

### Port zaten kullanımda

`docker-compose.yml` dosyasında portları değiştir:
```yaml
ports:
  - "8001:8000"  # 8000 yerine 8001 kullan
```

### Database hatası

```powershell
# Database'i sıfırla (DİKKAT: Tüm veriyi siler!)
docker-compose down -v
docker-compose up -d
```

---

## 📁 Önemli Dosyalar

| Dosya | Açıklama |
|-------|----------|
| `DESIGN_STANDARD.md` | 🎨 Tasarım kuralları (MUTLAKA OKU!) |
| `README.md` | 📖 Detaylı proje dökümanı |
| `KURULUM.md` | ⚡ Bu dosya (hızlı başlangıç) |
| `demo_layout.html` | 🖼️ UI demo (tarayıcıda aç) |
| `docker-compose.yml` | 🐳 Docker yapılandırma |
| `requirements.txt` | 📦 Python bağımlılıkları |

---

## 🌐 Servisler

| Servis | Port | Kullanım |
|--------|------|----------|
| Django Web | 8000 | Ana uygulama |
| PostgreSQL | 5432 | Database |
| Redis | 6379 | Cache & Celery |
| Nginx | 80, 443 | Reverse proxy |

---

## 📚 Django Apps

| App | Açıklama |
|-----|----------|
| `apps.core` | Temel modeller |
| `apps.tenants` | Tenant yönetimi ⭐ |
| `apps.packages` | Paket sistemi ⭐ |
| `apps.modules` | Modül sistemi ⭐ |
| `apps.subscriptions` | Abonelik & ödeme ⭐ |
| `apps.permissions` | Yetki yönetimi |

---

## 🤖 Celery Tasks

Otomatik çalışan görevler:

| Görev | Zaman | Açıklama |
|-------|-------|----------|
| `check_expired_subscriptions` | 02:00 | Süresi dolan abonelikleri pasif yap |
| `send_subscription_reminders` | 09:00 | Hatırlatma e-postaları gönder |
| `cleanup_cache` | Her 6 saat | Cache temizliği |

---

## 🆘 Yardım

### Sorun mu yaşıyorsunuz?

1. ✅ **Bu dosyayı okudunuz mu?**
2. ✅ **README.md'yi kontrol ettiniz mi?**
3. ✅ **DESIGN_STANDARD.md'yi okudunuz mu?**
4. ✅ **Docker loglarını kontrol ettiniz mi?**

### Hala çözülmedi mi?

```powershell
# Sistem durumunu kontrol et
docker-compose ps

# Tüm logları göster
docker-compose logs

# Web servisinin loglarını göster
docker-compose logs web

# Database loglarını göster
docker-compose logs db
```

---

## 🎯 Sonraki Adımlar

1. ✅ Admin panele giriş yap: http://localhost:8000/admin
2. ✅ İlk modülünü oluştur (Rezervasyon)
3. ✅ İlk paketini oluştur (Başlangıç Paketi)
4. ✅ İlk tenant'ı oluştur (Test Oteli)
5. ✅ Demo layout'u incele: `demo_layout.html`

---

## ⚠️ HATIRLATMA (Chat Hafıza İçin)

**Eğer bu proje yeni bir chat oturumunda açılırsa:**

1. ✅ **İlk olarak:** `DESIGN_STANDARD.md` dosyasını oku
2. ✅ **UI görmek için:** `demo_layout.html` aç
3. ✅ **Hızlı başlangıç için:** `KURULUM.md` (bu dosya)
4. ✅ **Detaylı bilgi için:** `README.md`

**Asla unutma:**
- Bu proje VB tarzı masaüstü görünümlü
- Layout yapısı değişmez (titlebar, toolbar, sidebar, content, statusbar)
- Renk paleti sabit (mavi + gri + koyu tema)
- Modern web trendleri kullanılmaz

---

**🎉 Başarılar! Projeniz kullanıma hazır!**

📅 Oluşturulma: 2025-11-09  
✍️ Geliştirici: SaaS 2026 Team



