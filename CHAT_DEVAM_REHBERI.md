# 💬 Chat Devam Rehberi

> **Amaç:** Chat kesilse bile, tüm süreçleri ve proje durumunu anlatabilmek için kapsamlı rehber.

---

## 🎯 Hızlı Başlangıç

### Projeyi Anlamak İçin Önce Şunları Okuyun:
1. **`PROJE_KAPSAMLI_DOKUMANTASYON.md`** - Tüm proje bilgileri
2. **`PROJECT_STATUS.md`** - Güncel proje durumu
3. **`DESIGN_STANDARD.md`** - Tasarım standartları (ÇOK ÖNEMLİ!)

---

## 📋 Proje Özeti

### Ne Yapıyoruz?
**SaaS 2026** - Multi-tenant otel/tur yönetim sistemi. Visual Basic tarzı masaüstü uygulama görünümünde.

### Temel Özellikler:
- ✅ Multi-tenancy (her tenant izole schema)
- ✅ Modüler yapı (Otel, Tur, Reception, Housekeeping vb.)
- ✅ Paket yönetimi
- ✅ Detaylı yetki sistemi
- ✅ Ödeme entegrasyonları

### Teknoloji:
- **Backend:** Django 5.0+, Python 3.11+
- **Database:** PostgreSQL 15+ (django-tenants)
- **Frontend:** Django Templates + Tailwind CSS (VB tarzı)
- **Cache:** Redis + Celery

---

## 🏗️ Proje Yapısı

### Ana Klasörler:
```
apps/
├── core/              # Super Admin core
├── tenants/           # Multi-tenancy
├── tenant_apps/       # Tenant modülleri
│   ├── reception/     # Önbüro (EN ÖNEMLİ!)
│   ├── hotels/        # Otel yönetimi
│   ├── tours/         # Tur yönetimi
│   └── ...
config/                # Django ayarları
templates/             # Template'ler
static/                # CSS, JS, images
```

### Önemli Dosyalar:
- `config/settings.py` - Django ayarları
- `config/urls.py` - Ana URL yapılandırması
- `templates/tenant/base.html` - Ana template
- `static/css/vb-layout.css` - Ana CSS

---

## 🎨 Tasarım Standardı (KRİTİK!)

### ⚠️ ÖNEMLİ: Bu proje Visual Basic tarzı masaüstü uygulama görünümündedir!

**KULLANILMAZ:**
- ❌ Card-based design
- ❌ Gradient backgrounds
- ❌ Glassmorphism
- ❌ Modern web trendleri

**KULLANILIR:**
- ✅ Panel-based layout (GroupBox)
- ✅ DataGridView tarzı tablolar
- ✅ Klasik Windows renkleri (mavi + gri)
- ✅ Tam ekran layout

**Detaylar:** `DESIGN_STANDARD.md` dosyasını MUTLAKA okuyun!

---

## 📦 Modüller

### Tamamlanan Modüller:

1. **Core** ✅ - Paket, modül, abonelik yönetimi
2. **Hotels** ✅ - Otel yönetimi
3. **Reception** ✅ - Önbüro/Resepsiyon (EN ÖNEMLİ!)
4. **Housekeeping** ✅ - Kat hizmetleri
5. **Technical Service** ✅ - Teknik servis
6. **Quality Control** ✅ - Kalite kontrol
7. **Sales** ✅ - Satış yönetimi
8. **Staff** ✅ - Personel yönetimi
9. **Tours** ✅ - Tur yönetimi
10. **Finance** ✅ - Kasa yönetimi
11. **Accounting** ✅ - Muhasebe
12. **Refunds** ✅ - İade yönetimi

### Reception Modülü (En Önemli):

**Özellikler:**
- Rezervasyon yönetimi (CRUD)
- Oda planı ve durumu
- Dashboard
- Voucher sistemi
- Ödeme entegrasyonu
- Arşivleme ve geri alma

**Son Değişiklikler (2025-11-13):**
- Başlık: "Silinmiş Rezervasyonlar Arşivi"
- Geri al butonları düzeltildi
- Modal CSS eklendi
- Favicon eklendi

---

## 🔐 Yetki Sistemi

### Yetki Seviyeleri:
1. **Super Admin** - Tüm sistem
2. **Tenant Admin** - Tenant yönetimi
3. **Modül Yetkisi** - Modül bazlı
4. **Otel Yetkisi** - Otel bazlı

### Decorator'lar:
```python
@require_module_permission('reception', 'view')
@require_hotel_permission('view')
@require_reception_permission('view')
```

---

## 🗄️ Veritabanı

### Multi-Tenancy:
- **Public Schema:** Super Admin
- **Tenant Schema:** `tenant_<domain>` formatında

### Önemli Tablolar:
- `reception_reservation` - Rezervasyonlar
- `hotels_hotel` - Otel bilgileri
- `core_customer` - Müşteri bilgileri

### Soft Delete:
Birçok model `SoftDeleteModel` kullanır:
- `is_deleted`
- `deleted_at`
- `deleted_by`

---

## 🐛 Bilinen Sorunlar

### 1. Modal Görünmüyor
**Çözüm:** Modal CSS'inin eklendiğinden emin olun.

### 2. Yetki Hatası
**Çözüm:** Decorator'ları ve kullanıcı yetkilerini kontrol edin.

### 3. Migration Hataları
**Çözüm:**
```bash
python manage.py migrate_schemas --shared
python manage.py migrate_schemas
```

---

## 🚀 Yeni Özellik Ekleme

### Adımlar:
1. İlgili modülü bulun (`apps/tenant_apps/<module>/`)
2. Model ekleyin (`models.py`)
3. Form ekleyin (`forms.py`)
4. View ekleyin (`views.py`)
5. URL ekleyin (`urls.py`)
6. Template ekleyin (`templates/`)
7. Migration çalıştırın

**Detaylar:** `MODUL_EKLEME_STANDARTLARI.md`

---

## 📝 Son Yapılan İşler

### 2025-11-13:
1. ✅ Reception modülü: "Silinmiş Rezervasyonlar Arşivi" başlık değişikliği
2. ✅ Geri al butonları düzeltildi (detay ve listeleme)
3. ✅ Modal CSS eklendi
4. ✅ Favicon eklendi

---

## 💡 İpuçları

### Debug İçin:
- Console.log'ları kontrol edin (F12)
- Django debug toolbar kullanın
- Log dosyalarını kontrol edin (`logs/django.log`)

### Hızlı Test:
```bash
python manage.py runserver
# Tarayıcıda: http://localhost:8000
```

### Migration:
```bash
python manage.py makemigrations
python manage.py migrate_schemas
```

---

## 📚 Dokümantasyon Dosyaları

### Mutlaka Okunması Gerekenler:
1. `PROJE_KAPSAMLI_DOKUMANTASYON.md` - Tüm bilgiler
2. `DESIGN_STANDARD.md` - Tasarım standartları
3. `PROJECT_STATUS.md` - Proje durumu
4. `MODUL_EKLEME_STANDARTLARI.md` - Modül ekleme

### Yardımcı Dosyalar:
- `KURULUM.md` - Kurulum rehberi
- `GITHUB_UPLOAD_REHBERI.md` - GitHub yükleme
- `PRODUCTION_DEPLOYMENT.md` - Production deployment

---

## 🎯 Chat'e Devam Ederken

### Söylemeniz Gerekenler:
1. **"Proje durumunu anlat"** → `PROJECT_STATUS.md` okuyun
2. **"Yeni özellik ekle"** → `MODUL_EKLEME_STANDARTLARI.md` okuyun
3. **"Tasarım standardı nedir?"** → `DESIGN_STANDARD.md` okuyun
4. **"Hata var"** → Hata mesajını ve konsol loglarını paylaşın

### Örnek Başlangıç:
```
Merhaba! SaaS 2026 projesinde çalışıyoruz. 
Reception modülünde geri al butonları çalışmıyor. 
Son değişiklikler: PROJE_KAPSAMLI_DOKUMANTASYON.md dosyasında.
```

---

**📅 Son Güncelleme:** 2025-11-13  
**🔄 Versiyon:** 1.0.0

