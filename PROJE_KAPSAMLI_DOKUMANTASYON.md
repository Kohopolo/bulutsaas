# 📚 SaaS 2026 - Kapsamlı Proje Dokümantasyonu

> **Son Güncelleme:** 2025-11-13  
> **Versiyon:** 1.0.0  
> **Durum:** Aktif Geliştirme

---

## 📋 İçindekiler

1. [Proje Genel Bakış](#proje-genel-bakış)
2. [Teknik Mimari](#teknik-mimari)
3. [Modüller ve Özellikler](#modüller-ve-özellikler)
4. [Veritabanı Yapısı](#veritabanı-yapısı)
5. [Yetki Sistemi](#yetki-sistemi)
6. [Son Yapılan Değişiklikler](#son-yapılan-değişiklikler)
7. [Kurulum ve Deployment](#kurulum-ve-deployment)
8. [Modül Entegrasyon Rehberi](#modül-entegrasyon-rehberi)
9. [Bilinen Sorunlar ve Çözümler](#bilinen-sorunlar-ve-çözümler)
10. [Gelecek Planlar](#gelecek-planlar)

---

## 🎯 Proje Genel Bakış

### Proje Adı
**SaaS 2026 - Multi-Tenant Otel/Tur Yönetim Sistemi**

### Proje Amacı
Otel, tur, villa gibi işletmeler için **multi-tenant** (çoklu kiracı) yapıda geliştirilmiş, Visual Basic tarzı masaüstü uygulama görünümlü bir yönetim sistemidir.

### Temel Özellikler
- ✅ **Multi-Tenancy:** Her tenant (kiracı) kendi izole PostgreSQL schema'sında çalışır
- ✅ **Modüler Yapı:** Dinamik modül yönetimi (Otel, Tur, Villa, Bilet vb.)
- ✅ **Paket Yönetimi:** Super Admin tarafından özelleştirilebilir paketler
- ✅ **Detaylı Yetki Sistemi:** Modül bazlı, otel bazlı, kullanıcı bazlı izinler
- ✅ **Ödeme Entegrasyonları:** Iyzico, PayTR, NestPay
- ✅ **Visual Basic Tarzı UI:** Masaüstü uygulama görünümü

---

## 🏗️ Teknik Mimari

### Teknoloji Stack

#### Backend
- **Python:** 3.11+
- **Django:** 5.0+
- **Django REST Framework:** API geliştirme
- **django-tenants:** Multi-tenancy desteği
- **PostgreSQL:** 15+ (Schema-based multi-tenancy)
- **Redis:** 7+ (Cache & Celery broker)
- **Celery:** Background task yönetimi
- **Celery Beat:** Zamanlanmış görevler

#### Frontend
- **Django Templates:** Server-side rendering
- **Tailwind CSS:** Utility-first CSS framework (VB tarzı override ile)
- **Vanilla JavaScript:** Minimal JS kullanımı
- **Font Awesome:** 6.4.0 (Icon library)
- **Custom CSS:** `vb-layout.css`, `vb-override-modern.css`

#### DevOps
- **Docker:** Containerization
- **Docker Compose:** Multi-container orchestration
- **Nginx:** Reverse proxy
- **Gunicorn:** WSGI server
- **Git:** Version control

### Proje Yapısı

```
bulutacente/
├── apps/                          # Ana uygulama modülleri
│   ├── core/                      # Core sistem (paket, modül, abonelik)
│   ├── tenants/                   # Multi-tenancy yönetimi
│   ├── subscriptions/             # Abonelik yönetimi
│   ├── packages/                  # Paket yönetimi
│   ├── modules/                   # Modül tanımları
│   ├── permissions/               # Yetki sistemi
│   ├── payments/                  # Ödeme entegrasyonları
│   ├── notifications/             # Bildirim sistemi
│   ├── ai/                        # AI özellikleri
│   └── tenant_apps/               # Tenant'a özel modüller
│       ├── core/                  # Tenant core (müşteri, CRM)
│       ├── hotels/                # Otel yönetimi
│       ├── reception/             # Önbüro/Resepsiyon
│       ├── housekeeping/          # Kat hizmetleri
│       ├── technical_service/     # Teknik servis
│       ├── quality_control/       # Kalite kontrol
│       ├── sales/                 # Satış yönetimi
│       ├── staff/                 # Personel yönetimi
│       ├── tours/                 # Tur yönetimi
│       ├── finance/               # Kasa yönetimi
│       ├── accounting/             # Muhasebe
│       └── refunds/              # İade yönetimi
├── config/                        # Django ayarları
│   ├── settings.py               # Ana ayarlar
│   ├── urls.py                   # Ana URL yapılandırması
│   ├── urls_public.py            # Public schema URL'leri
│   └── celery.py                 # Celery yapılandırması
├── templates/                    # Django template'leri
│   ├── tenant/                   # Tenant template'leri
│   └── public/                   # Public template'leri
├── static/                        # Static dosyalar
│   ├── css/                      # CSS dosyaları
│   ├── js/                       # JavaScript dosyaları
│   └── images/                   # Görseller
├── media/                         # Kullanıcı yüklenen dosyalar
├── scripts/                       # Yardımcı scriptler
├── nginx/                         # Nginx yapılandırmaları
└── *.md                          # Dokümantasyon dosyaları
```

### Multi-Tenancy Yapısı

**django-tenants** kullanılarak schema-based multi-tenancy implementasyonu yapılmıştır:

- **Public Schema:** `public` - Super Admin ve landing page
- **Tenant Schema:** `tenant_<domain>` - Her tenant için izole schema
- **Domain Model:** Her tenant'ın kendi domain'i (subdomain veya custom domain)

**Örnek:**
- `test-otel.localhost:8000` → `tenant_test-otel` schema
- `demo-otel.localhost:8000` → `tenant_demo-otel` schema

---

## 📦 Modüller ve Özellikler

### 1. Core Sistem ✅

**Modül Kodu:** `core`  
**URL Prefix:** `/` (root)

#### Özellikler:
- ✅ Paket yönetimi (Super Admin)
- ✅ Modül yönetimi (Super Admin)
- ✅ Abonelik takibi
- ✅ Tenant yönetimi
- ✅ Domain yönetimi
- ✅ Landing page (Bulut Acente)
- ✅ Super Admin paneli
- ✅ Tenant Admin paneli

#### Dosyalar:
- `apps/core/` - Super Admin core
- `apps/tenant_apps/core/` - Tenant core (müşteri, CRM)

---

### 2. Otel Yönetimi ✅

**Modül Kodu:** `hotels`  
**URL Prefix:** `/hotels/`

#### Özellikler:
- ✅ Otel CRUD işlemleri
- ✅ Oda tipi yönetimi
- ✅ Oda numarası yönetimi
- ✅ Yatak tipi yönetimi
- ✅ Otel ayarları
- ✅ Çoklu otel desteği (paket bazlı)

#### Modeller:
- `Hotel` - Otel bilgileri
- `RoomType` - Oda tipleri
- `RoomNumber` - Oda numaraları
- `BedType` - Yatak tipleri
- `HotelSettings` - Otel ayarları

---

### 3. Önbüro/Resepsiyon (Reception) ✅

**Modül Kodu:** `reception`  
**URL Prefix:** `/reception/`

#### Özellikler:
- ✅ Rezervasyon yönetimi (CRUD)
- ✅ Oda planı görünümü
- ✅ Oda durumu yönetimi
- ✅ Rezervasyon dashboard
- ✅ Müşteri bilgileri yönetimi
- ✅ Misafir bilgileri yönetimi
- ✅ Ödeme yönetimi
- ✅ Voucher sistemi
- ✅ Rezervasyon timeline
- ✅ İade işlemleri
- ✅ Arşivleme ve geri alma

#### Modeller:
- `Reservation` - Rezervasyonlar
- `ReservationGuest` - Misafir bilgileri
- `ReservationPayment` - Ödeme kayıtları
- `ReservationTimeline` - Rezervasyon geçmişi
- `ReservationVoucher` - Voucher'lar
- `VoucherTemplate` - Voucher şablonları

#### Son Yapılan Değişiklikler (2025-11-13):
- ✅ "Arşivlenmiş Rezervasyonlar" → "Silinmiş Rezervasyonlar Arşivi" başlık değişikliği
- ✅ Geri al butonları düzeltildi (detay ve listeleme sayfalarında)
- ✅ Modal CSS eklendi (görünürlük sorunu çözüldü)
- ✅ Event listener'lar düzeltildi (onclick yerine data-attribute kullanımı)
- ✅ Favicon eklendi (404 hatası giderildi)

#### URL'ler:
- `/reception/reservations/` - Rezervasyon listesi
- `/reception/reservations/<id>/` - Rezervasyon detayı
- `/reception/reservations/archived/` - Silinmiş rezervasyonlar
- `/reception/reservations/<id>/restore/` - Rezervasyon geri alma
- `/reception/room-plan/` - Oda planı
- `/reception/room-status/` - Oda durumu
- `/reception/dashboard/` - Dashboard
- `/reception/vouchers/` - Voucher listesi
- `/reception/voucher/<token>/` - Public voucher görüntüleme
- `/reception/voucher/<token>/payment/` - Voucher ödeme

---

### 4. Kat Hizmetleri (Housekeeping) ✅

**Modül Kodu:** `housekeeping`  
**URL Prefix:** `/housekeeping/`

#### Özellikler:
- ✅ Temizlik görevleri yönetimi
- ✅ Kontrol listesi sistemi
- ✅ Eksik malzeme takibi
- ✅ Çamaşır yönetimi
- ✅ Bakım talepleri
- ✅ Günlük raporlama

#### Modeller:
- `CleaningTask` - Temizlik görevleri
- `CleaningChecklistItem` - Kontrol listesi
- `MissingItem` - Eksik malzemeler
- `LaundryItem` - Çamaşır öğeleri
- `MaintenanceRequest` - Bakım talepleri
- `HousekeepingSettings` - Ayarlar
- `HousekeepingDailyReport` - Günlük raporlar

---

### 5. Teknik Servis ✅

**Modül Kodu:** `technical_service`  
**URL Prefix:** `/technical-service/`

#### Özellikler:
- ✅ Bakım talepleri yönetimi
- ✅ Bakım kayıtları
- ✅ Ekipman envanteri
- ✅ Önleyici bakım planlama

#### Modeller:
- `MaintenanceRequest` - Bakım talepleri
- `MaintenanceRecord` - Bakım kayıtları
- `Equipment` - Ekipman envanteri
- `TechnicalServiceSettings` - Ayarlar

---

### 6. Kalite Kontrol ✅

**Modül Kodu:** `quality_control`  
**URL Prefix:** `/quality-control/`

#### Özellikler:
- ✅ Oda kalite kontrolü
- ✅ Hizmet kalite değerlendirmesi
- ✅ Müşteri şikayet yönetimi
- ✅ Kalite standartları takibi
- ✅ Denetim raporları

---

### 7. Satış Yönetimi ✅

**Modül Kodu:** `sales`  
**URL Prefix:** `/sales/`

#### Özellikler:
- ✅ Rezervasyon satışları
- ✅ Acente yönetimi
- ✅ Komisyon takibi
- ✅ Satış raporları
- ✅ Hedef takibi

---

### 8. Personel Yönetimi ✅

**Modül Kodu:** `staff`  
**URL Prefix:** `/staff/`

#### Özellikler:
- ✅ Personel kayıtları
- ✅ Vardiya yönetimi
- ✅ İzin yönetimi
- ✅ Performans takibi
- ✅ Maaş yönetimi

---

### 9. Tur Yönetimi ✅

**Modül Kodu:** `tours`  
**URL Prefix:** `/tours/`

#### Özellikler:
- ✅ Tur CRUD işlemleri
- ✅ Dinamik kategoriler
- ✅ Tur tarihleri ve fiyatlandırma
- ✅ Gün gün tur programı
- ✅ Tur rezervasyon sistemi
- ✅ Voucher sistemi
- ✅ WhatsApp entegrasyonu

---

### 10. Kasa Yönetimi (Finance) ✅

**Modül Kodu:** `finance`  
**URL Prefix:** `/finance/`

#### Özellikler:
- ✅ Kasa işlemleri
- ✅ Gelir-gider takibi
- ✅ Raporlama

---

### 11. Muhasebe (Accounting) ✅

**Modül Kodu:** `accounting`  
**URL Prefix:** `/accounting/`

#### Özellikler:
- ✅ Muhasebe kayıtları
- ✅ Fatura yönetimi
- ✅ Raporlama

---

### 12. İade Yönetimi (Refunds) ✅

**Modül Kodu:** `refunds`  
**URL Prefix:** `/refunds/`

#### Özellikler:
- ✅ İade politikaları
- ✅ İade talepleri
- ✅ İade işlemleri
- ✅ Raporlama

---

## 🗄️ Veritabanı Yapısı

### Multi-Tenancy Yapısı

**django-tenants** kullanılarak schema-based multi-tenancy:

- Her tenant kendi PostgreSQL schema'sında çalışır
- Public schema: Super Admin ve landing page
- Tenant schema: `tenant_<domain>` formatında

### Önemli Tablolar

#### Public Schema:
- `tenants_tenant` - Tenant bilgileri
- `tenants_domain` - Domain bilgileri
- `packages_package` - Paket tanımları
- `modules_module` - Modül tanımları
- `subscriptions_subscription` - Abonelikler

#### Tenant Schema:
- `reception_reservation` - Rezervasyonlar
- `hotels_hotel` - Otel bilgileri
- `core_customer` - Müşteri bilgileri
- `reception_reservationguest` - Misafir bilgileri
- `reception_reservationpayment` - Ödeme kayıtları
- `reception_reservationtimeline` - Rezervasyon geçmişi
- `reception_reservationvoucher` - Voucher'lar

### Soft Delete

Birçok model `SoftDeleteModel` mixin'ini kullanır:
- `is_deleted` - Boolean
- `deleted_at` - DateTime
- `deleted_by` - ForeignKey to User

**Örnek:** `Reservation` modeli soft delete kullanır.

---

## 🔐 Yetki Sistemi

### Yetki Seviyeleri

1. **Super Admin:** Tüm sistem yönetimi
2. **Tenant Admin:** Tenant yönetimi
3. **Modül Yetkisi:** Modül bazlı izinler
4. **Otel Yetkisi:** Otel bazlı izinler (örn: `reception` modülü için)

### Yetki Decorator'ları

#### Modül Bazlı:
```python
@require_module_permission('reception', 'view')
def reservation_list(request):
    ...
```

#### Otel Bazlı:
```python
@require_hotel_permission('view')
def reservation_detail(request, pk):
    ...
```

#### Resepsiyon Bazlı:
```python
@require_reception_permission('view')
def room_plan(request):
    ...
```

### Yetki Tanımları

Her modül için 4 temel yetki:
- `view` - Görüntüleme
- `add` - Ekleme
- `change` - Düzenleme
- `delete` - Silme

**Örnek:** `reception.view_reservation`, `reception.add_reservation`

---

## 📝 Son Yapılan Değişiklikler

### 2025-11-13

#### Reception Modülü:
1. **Başlık Değişikliği:**
   - "Arşivlenmiş Rezervasyonlar" → "Silinmiş Rezervasyonlar Arşivi"
   - Tüm ilgili sayfalarda güncellendi

2. **Geri Al Butonları Düzeltmesi:**
   - Arşiv listesi sayfasında çalışmıyordu → Düzeltildi
   - Detay sayfasında çalışmıyordu → Düzeltildi
   - `onclick` yerine `data-attribute` + `event listener` kullanımı
   - Modal CSS eklendi (görünürlük sorunu çözüldü)

3. **Favicon Eklendi:**
   - `static/images/favicon.ico` oluşturuldu
   - Template'e favicon link'leri eklendi
   - 404 hatası giderildi

#### Teknik Detaylar:
- Modal CSS dinamik olarak ekleniyor (eğer yoksa)
- Event listener'lar `DOMContentLoaded` içinde
- Console.log'lar debug için eklendi
- Z-index: 10000 (diğer elementlerin üzerinde)

---

## 🚀 Kurulum ve Deployment

### Geliştirme Ortamı

#### Gereksinimler:
- Python 3.11+
- PostgreSQL 15+
- Redis 7+
- Docker Desktop (opsiyonel)

#### Kurulum Adımları:

1. **Projeyi klonla:**
```bash
cd C:\xampp\htdocs\
git clone <repo-url> bulutacente
cd bulutacente
```

2. **Virtual environment oluştur:**
```bash
python -m venv venv
venv\Scripts\activate
```

3. **Bağımlılıkları yükle:**
```bash
pip install -r requirements.txt
```

4. **Environment değişkenlerini ayarla:**
```bash
cp env.example .env
# .env dosyasını düzenle
```

5. **Veritabanını oluştur:**
```bash
python manage.py migrate_schemas
```

6. **Super user oluştur:**
```bash
python manage.py createsuperuser
```

7. **Sunucuyu başlat:**
```bash
python manage.py runserver
```

### Production Deployment

**Detaylar için:** `PRODUCTION_DEPLOYMENT.md` dosyasına bakın.

---

## 🔧 Modül Entegrasyon Rehberi

### Yeni Modül Ekleme

1. **Modül klasörü oluştur:**
```bash
mkdir apps/tenant_apps/new_module
```

2. **Temel dosyaları oluştur:**
- `models.py` - Veritabanı modelleri
- `forms.py` - Form sınıfları
- `views.py` - View fonksiyonları
- `urls.py` - URL pattern'leri
- `admin.py` - Admin kayıtları
- `decorators.py` - Yetki decorator'ları
- `apps.py` - App config

3. **Modülü kaydet:**
- `apps/modules/models.py` - Module model'ine ekle
- `config/settings.py` - INSTALLED_APPS'e ekle
- `config/urls.py` - URL pattern ekle

4. **Yetkileri oluştur:**
```bash
python manage.py create_module_permissions new_module
```

5. **Migration'ları çalıştır:**
```bash
python manage.py makemigrations
python manage.py migrate_schemas
```

**Detaylar için:** `MODUL_EKLEME_STANDARTLARI.md` dosyasına bakın.

---

## ⚠️ Bilinen Sorunlar ve Çözümler

### 1. Modal Görünmüyor
**Sorun:** Modal açılıyor ama görünmüyor.  
**Çözüm:** Modal CSS'inin eklendiğinden emin olun. Z-index değerini kontrol edin.

### 2. Yetki Hatası
**Sorun:** "Yetki kontrolü sırasında hata oluştu" mesajı.  
**Çözüm:** 
- Decorator'ların doğru kullanıldığından emin olun
- Kullanıcının ilgili yetkilere sahip olduğunu kontrol edin
- `request.active_hotel` değerinin set edildiğini kontrol edin

### 3. Migration Hataları
**Sorun:** Migration çalışmıyor.  
**Çözüm:**
```bash
python manage.py migrate_schemas --shared
python manage.py migrate_schemas
```

### 4. Static Dosyalar Yüklenmiyor
**Sorun:** CSS/JS dosyaları 404 veriyor.  
**Çözüm:**
```bash
python manage.py collectstatic
```

---

## 🔮 Gelecek Planlar

### Kısa Vadeli (1-2 Hafta)
- [ ] SMS API entegrasyonu
- [ ] WhatsApp Business API entegrasyonu
- [ ] Email template sistemi
- [ ] Raporlama iyileştirmeleri

### Orta Vadeli (1-2 Ay)
- [ ] Mobile app (React Native)
- [ ] Real-time bildirimler (WebSocket)
- [ ] Advanced analytics dashboard
- [ ] Multi-language desteği

### Uzun Vadeli (3-6 Ay)
- [ ] AI-powered öneriler
- [ ] Otomatik fiyatlandırma
- [ ] Blockchain entegrasyonu (isteğe bağlı)
- [ ] Marketplace entegrasyonu

---

## 📞 İletişim ve Destek

### Dokümantasyon Dosyaları:
- `README.md` - Genel bakış
- `PROJECT_STATUS.md` - Proje durumu
- `DESIGN_STANDARD.md` - Tasarım standartları
- `KURULUM.md` - Kurulum rehberi
- `MODUL_EKLEME_STANDARTLARI.md` - Modül ekleme rehberi

### Önemli Notlar:
- ⚠️ **Tasarım Standardı:** Bu proje Visual Basic tarzı masaüstü uygulama görünümündedir. Modern web trendleri kullanılmaz!
- ⚠️ **Multi-Tenancy:** Her tenant izole schema'da çalışır. Dikkatli olun!
- ⚠️ **Yetki Sistemi:** Modül bazlı ve otel bazlı yetkiler vardır.

---

**📅 Son Güncelleme:** 2025-11-13  
**✍️ Dokümantasyon:** AI Assistant  
**🔄 Versiyon:** 1.0.0





