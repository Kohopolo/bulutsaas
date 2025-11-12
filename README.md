# 🚀 SaaS 2026 - Multi-Tenant Otel/Tur Yönetim Sistemi

> **Visual Basic tarzı masaüstü uygulama görünümlü, modern SaaS platformu**

## 📋 Proje Hakkında

SaaS 2026, otel, tur, villa gibi işletmeler için **multi-tenant** (çoklu kiracı) yapıda geliştirilmiş bir yönetim sistemidir.

### 🎯 Ana Özellikler:

- ✅ **Dinamik Paket Yönetimi** (Super Admin)
- ✅ **Modüler Yapı** (Otel, Tur, Villa, Bilet modülleri)
- ✅ **Detaylı Yetki Yönetimi** (Modül bazlı izinler)
- ✅ **Multi-Tenancy** (Her üye izole ortamda)
- ✅ **Otomatik Abonelik Takibi**
- ✅ **Custom Domain Desteği**

---

## 🎨 Tasarım Standardı

**⚠️ ÖNEMLİ:** Bu proje **Visual Basic masaüstü uygulama** tarzında tasarlanmıştır!

- ✅ Tam ekran layout (titlebar, toolbar, sidebar, content, statusbar)
- ✅ Panel-based mimari (GroupBox, DataGridView)
- ✅ Klasik Windows renkleri (mavi + gri)
- ❌ Modern web trendleri kullanılmaz (card, gradient, glassmorphism vb.)

**📖 Detaylar:** [`DESIGN_STANDARD.md`](DESIGN_STANDARD.md) dosyasını mutlaka okuyun!

---

## 🛠️ Teknoloji Stack

### Backend:
- **Python 3.11+**
- **Django 5.0+**
- **Django REST Framework** (API)
- **django-tenants** (Multi-tenancy)
- **PostgreSQL 15+** (Database)
- **Redis 7+** (Cache & Celery)
- **Celery** (Background tasks)

### Frontend:
- **Django Templates** (Server-side rendering)
- **Vanilla JavaScript** (Minimal JS)
- **Custom CSS** (VB tarzı - `vb-layout.css`)

### DevOps:
- **Docker + Docker Compose**
- **Nginx** (Reverse proxy)
- **Gunicorn** (WSGI server)

---

## 🚀 Hızlı Başlangıç

### 1️⃣ Ön Gereksinimler

```bash
# Windows için:
- Docker Desktop (https://www.docker.com/products/docker-desktop/)
- Git (https://git-scm.com/)

# Kurulu mu kontrol et:
docker --version
docker-compose --version
git --version
```

### 2️⃣ Projeyi Klonla

```bash
cd C:\xampp\htdocs\
git clone <repo-url> saas2026
cd saas2026
```

### 3️⃣ Environment Ayarları

```bash
# .env.example dosyasını kopyala
copy .env.example .env

# .env dosyasını düzenle (şifreleri değiştir)
notepad .env
```

### 4️⃣ Docker ile Başlat

```bash
# Tüm servisleri başlat (ilk kez 5-10 dakika sürer)
docker-compose up -d

# Logları izle
docker-compose logs -f web

# Database migration
docker-compose exec web python manage.py migrate

# Superuser oluştur
docker-compose exec web python manage.py createsuperuser

# Static dosyaları topla
docker-compose exec web python manage.py collectstatic --noinput
```

### 5️⃣ Tarayıcıda Aç

```
🌐 Ana Site: http://localhost:8000
🔐 Admin Panel: http://localhost:8000/admin
📊 API Docs: http://localhost:8000/api/docs
```

---

## 📁 Proje Yapısı

```
saas2026/
├── 📄 DESIGN_STANDARD.md          ← Tasarım kuralları (ÖNCE BUNU OKU!)
├── 📄 README.md                   ← Bu dosya
├── 📄 demo_layout.html            ← UI demo (tarayıcıda açılabilir)
│
├── 🐳 docker-compose.yml          ← Docker yapılandırma
├── 🐳 Dockerfile                  ← Python/Django imajı
├── 📦 requirements.txt            ← Python bağımlılıkları
├── ⚙️ .env.example                ← Environment değişkenleri
│
├── config/                        ← Django ayarları
│   ├── __init__.py
│   ├── settings.py
│   ├── urls.py
│   └── wsgi.py
│
├── apps/                          ← Django uygulamaları
│   ├── core/                      ← Temel modeller (User, Tenant)
│   ├── packages/                  ← Paket yönetimi
│   ├── modules/                   ← Modül sistemi
│   ├── permissions/               ← Yetki yönetimi
│   ├── subscriptions/             ← Abonelik takibi
│   ├── tenants/                   ← Tenant yönetimi
│   │
│   └── tenant_apps/               ← Tenant uygulamaları
│       ├── reservations/          ← Rezervasyon modülü
│       ├── housekeeping/          ← Housekeeping modülü
│       ├── channels/              ← Kanal entegrasyonu
│       ├── hotels/                ← Otel yönetimi
│       └── tours/                 ← Tur modülü
│
├── templates/                     ← Django templates
│   ├── base.html                  ← Ana layout (VB tarzı)
│   ├── includes/
│   │   ├── titlebar.html
│   │   ├── toolbar.html
│   │   ├── sidebar.html
│   │   └── statusbar.html
│   ├── dashboard/
│   ├── packages/
│   └── modules/
│
├── static/                        ← Static dosyalar
│   ├── css/
│   │   ├── vb-layout.css         ← Ana layout CSS (değişmez!)
│   │   └── vb-components.css     ← Component CSS
│   ├── js/
│   │   ├── vb-layout.js
│   │   └── app.js
│   └── images/
│
├── media/                         ← Upload dosyalar
└── logs/                          ← Log dosyaları
```

---

## 🔧 Geliştirme

### Günlük Kullanım:

```bash
# Servisleri başlat
docker-compose up -d

# Logları izle
docker-compose logs -f web

# Django shell
docker-compose exec web python manage.py shell

# Yeni migration
docker-compose exec web python manage.py makemigrations
docker-compose exec web python manage.py migrate

# Test
docker-compose exec web python manage.py test

# Servisleri durdur
docker-compose down
```

### Yeni Uygulama Oluştur:

```bash
# Django app oluştur
docker-compose exec web python manage.py startapp myapp apps/myapp

# App'i settings.py'a ekle
# INSTALLED_APPS = [..., 'apps.myapp']
```

### Yeni Sayfa Oluştur:

1. **Template oluştur:** `templates/myapp/page.html`
2. **`DESIGN_STANDARD.md` kurallarına uy!**
3. **`{% extends 'base.html' %}` kullan**
4. **VB komponentlerini kullan:** `.groupbox`, `.datagrid`, `.vb-button`

```html
{% extends 'base.html' %}

{% block content %}
<div class="content-header">
    <div class="content-title">Sayfa Başlığı</div>
</div>

<div class="content-body">
    <div class="groupbox">
        <div class="groupbox-header">📋 Başlık</div>
        <div class="groupbox-body">
            <!-- İçerik -->
        </div>
    </div>
</div>
{% endblock %}
```

---

## 📦 Paket Sistemi

### Super Admin (SaaS Yöneticisi):

1. **Paket Oluştur:** Modülleri seç, limitleri belirle
2. **Modül Yetkileri:** Her modül için detaylı izinler
3. **Fiyatlandırma:** Aylık/yıllık fiyat
4. **Üye Yönetimi:** Tüm üyeleri görüntüle

### Tenant Admin (Otel/Tur Sahibi):

1. **Pakete Abone Ol:** Ödeme yap, otomatik aktif olur
2. **Modülleri Kullan:** Sadece paketindeki modüller görünür
3. **Kullanıcı Ekle:** Kendi çalışanlarını ekle
4. **Limitler:** Paket limitlerini aşamaz

---

## 🗄️ Database Yapısı

### Shared Schema (Ortak):
- `public.tenants` - Üye listesi
- `public.packages` - Paket tanımları
- `public.modules` - Modül listesi
- `public.subscriptions` - Abonelikler

### Tenant Schema (İzole):
- `tenant_xxx.reservations` - Rezervasyonlar
- `tenant_xxx.rooms` - Odalar
- `tenant_xxx.users` - Kullanıcılar
- `tenant_xxx.settings` - Ayarlar

Her tenant için otomatik PostgreSQL schema oluşturulur!

---

## 🔐 Güvenlik

- ✅ Django CSRF koruması
- ✅ SQL Injection koruması (ORM)
- ✅ XSS koruması (template escaping)
- ✅ Tenant izolasyonu (schema-based)
- ✅ Rate limiting (DRF throttle)
- ✅ SSL/TLS (production'da)

---

## 📊 Modüller

### Mevcut Modüller:

| Modül | Açıklama | Durum |
|-------|----------|-------|
| **Rezervasyon** | Rezervasyon yönetimi | ✅ Aktif |
| **Housekeeping** | Oda temizlik takibi | ✅ Aktif |
| **Kanal Entegrasyonu** | OTA kanalları (Booking, Airbnb) | 🚧 Geliştiriliyor |
| **Otel Yönetimi** | Otel/oda tanımları | ✅ Aktif |
| **Tur Yönetimi** | Tur programları | 🔜 Planlanan |
| **Villa Yönetimi** | Villa rezervasyonları | 🔜 Planlanan |
| **Bilet Satış** | Aktivite/etkinlik | 🔜 Planlanan |

### Yeni Modül Ekle:

1. Django app oluştur: `apps/tenant_apps/mymodule/`
2. Model tanımla (tenant schema'da)
3. Admin paneli ekle (VB tarzı!)
4. Yetkileri tanımla: `mymodule_view`, `mymodule_add`, vb.
5. `modules` tablosuna kaydet

---

## 🧪 Test

```bash
# Tüm testler
docker-compose exec web python manage.py test

# Belirli app
docker-compose exec web python manage.py test apps.packages

# Coverage
docker-compose exec web coverage run --source='.' manage.py test
docker-compose exec web coverage report
```

---

## 🚢 Production Deployment

### 1. Environment Ayarları:

```bash
# .env dosyasını düzenle
DEBUG=False
ALLOWED_HOSTS=yourdomain.com
SECRET_KEY=güçlü-rastgele-anahtar
DATABASE_URL=postgresql://user:pass@db:5432/saas_db
```

### 2. SSL Sertifikası:

```bash
# Let's Encrypt
docker-compose exec nginx certbot --nginx -d yourdomain.com
```

### 3. Static Dosyalar:

```bash
docker-compose exec web python manage.py collectstatic --noinput
```

### 4. Database Backup:

```bash
# Yedek al
docker-compose exec db pg_dump -U postgres saas_db > backup.sql

# Geri yükle
docker-compose exec -T db psql -U postgres saas_db < backup.sql
```

---

## 🆘 Sorun Giderme

### Docker başlamıyor:

```bash
# Logları kontrol et
docker-compose logs

# Servisleri yeniden başlat
docker-compose down
docker-compose up -d --build
```

### Database bağlanamıyor:

```bash
# PostgreSQL çalışıyor mu?
docker-compose ps

# Database oluştur (manuel)
docker-compose exec db psql -U postgres
CREATE DATABASE saas_db;
```

### Static dosyalar yüklenmiyor:

```bash
# Collectstatic yeniden çalıştır
docker-compose exec web python manage.py collectstatic --noinput

# Nginx yeniden başlat
docker-compose restart nginx
```

---

## 📚 Dökümanlar

- 📄 **[DESIGN_STANDARD.md](DESIGN_STANDARD.md)** - Tasarım kuralları (MUTLAKA OKU!)
- 📄 **demo_layout.html** - UI demo (tarayıcıda aç)
- 📄 **API Docs** - http://localhost:8000/api/docs (Swagger)

---

## 🤝 Katkıda Bulunma

1. **Tasarım kurallarına uy:** `DESIGN_STANDARD.md`
2. **VB tarzını koru:** Modern trendler ekleme
3. **Test yaz:** Her özellik için test
4. **Commit mesajları:** Türkçe, açıklayıcı

---

## 📞 İletişim

- 📧 Email: info@saas2026.com
- 🌐 Website: https://saas2026.com

---

## 📝 Lisans

Bu proje özel bir projedir. Tüm hakları saklıdır.

---

## ⚠️ ÖNEMLİ NOTLAR (CHAT HAFİZA İÇİN)

### 🔄 Yeni Chat Oturumunda Yapılacaklar:

1. ✅ **İlk olarak:** `PROJECT_STATUS.md` dosyasını oku (genel durum)
2. ✅ **Chat devamı için:** `CHAT_CONTINUATION_GUIDE.md` dosyasını oku
3. ✅ **Tur modülü detayları:** `TODO_TUR_MODULE.md` dosyasını oku
4. ✅ **Tasarım kuralları:** `DESIGN_STANDARD.md` dosyasını oku
5. ✅ **UI görmek için:** `demo_layout.html` aç
6. ✅ **Proje yapısı:** Bu README'yi oku

### 🎨 Tasarım Hatırlatıcı:

- Bu proje **Visual Basic** masaüstü uygulama tarzında
- **Tam ekran layout:** titlebar → toolbar → sidebar+content → statusbar
- **Renk paleti:** Mavi (#0078d4) + Gri (#2d2d30) + Beyaz
- **Komponentler:** `.groupbox`, `.datagrid`, `.vb-button`, `.vb-textbox`
- **YASAK:** Modern web trendleri (gradient, card, glassmorphism vb.)

### 📁 Önemli Dosyalar:

```
📄 PROJECT_STATUS.md              ← Genel proje durumu (ÖNCE BUNU OKU!)
📄 CHAT_CONTINUATION_GUIDE.md     ← Chat devam etme rehberi
📄 TODO_TUR_MODULE.md              ← Tur modülü TODO listesi
📄 DESIGN_STANDARD.md              ← Tasarım DNA'sı
📄 demo_layout.html                ← Çalışır UI örneği
📄 templates/base.html             ← Ana template
📄 static/css/vb-layout.css        ← Ana CSS
```

### 🚨 Asla Unutma:

> Bu proje VB tarzı masaüstü görünümlü. Modern web tasarım trendleri kullanılmaz!

---

**📅 Oluşturulma:** 2025-11-09  
**🔄 Son Güncelleme:** 2025-01-XX  
**🎯 Versiyon:** 1.0.0  
**✍️ Geliştirici:** SaaS 2026 Team

---

## 📊 Son Durum (2025-01-XX)

### ✅ Tamamlanan:
- **Tur Modülü:** %95 tamamlandı
  - Temel tur yönetimi ✅
  - Rezervasyon sistemi ✅
  - Dinamik fiyatlandırma ✅
  - Bekleme listesi ✅
  - CRM ve sadakat sistemi ✅
  - Acente yönetimi ✅
  - Operasyonel yönetim ✅
  - Kampanya yönetimi ✅
  - Bildirim sistemi ✅
  - Raporlama sistemi ✅

### 🔄 Devam Eden:
- Test yazımı
- SMS/WhatsApp API entegrasyonları
- Rating/yorum sistemi (isteğe bağlı)

**Detaylar için:** `PROJECT_STATUS.md` dosyasını okuyun!



