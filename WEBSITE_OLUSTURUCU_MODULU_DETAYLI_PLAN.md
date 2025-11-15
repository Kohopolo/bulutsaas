# Website Oluşturucu Modülü - Detaylı Plan ve Analiz

## 📋 Genel Bakış

Drag and Drop Website Builder modülü, kullanıcıların kod yazmadan profesyonel web siteleri oluşturmasına olanak tanıyan kapsamlı bir sistemdir.

## 🎯 Temel Özellikler

### 1. Drag and Drop Builder
- **Kütüphane Seçimi:** GrapesJS (en popüler, ücretsiz, açık kaynak)
- **Özellikler:**
  - Görsel sayfa düzenleyici
  - Bileşen kütüphanesi
  - Responsive tasarım desteği
  - Mobil önizleme
  - Canlı düzenleme

### 2. Sayfa Yönetimi
- Sayfa ekleme/düzenleme/silme
- Sayfa şablonları
- Sayfa ayarları (SEO, meta tags, custom CSS/JS)
- Sayfa durumu (draft, published, archived)

### 3. Menü Yönetimi
- Menü oluşturucu/düzenleyici
- Çok seviyeli menü desteği
- Drag and drop menü sıralama
- Menü şablonları

### 4. Header ve Footer
- Her sayfada standart header/footer
- Header/footer düzenleyici
- Logo, menü, sosyal medya linkleri
- Widget desteği

### 5. Slider/Hero Builder
- Anasayfa için slider oluşturucu
- Hero section builder
- Görsel/video desteği
- Animasyon efektleri

### 6. Responsive Tasarım
- Mobil arayüz ayrı düzenleme
- Tablet görünümü
- Desktop görünümü
- Canlı önizleme

### 7. Şablonlar
- Header şablonları
- Footer şablonları
- Sayfa şablonları (rooms, aktiviteler, galeri, hizmetlerimiz, iletişim, iletişim formu, rezervasyon)
- Tam site şablonları

### 8. Veri Entegrasyonu
- **Oda Tipleri:** Otomatik oda listesi ekleme
- **Otel Bilgileri:** Hizmetler, özellikler, bilgiler
- **Tur Bilgileri:** Tur listesi, detaylar
- **Bungalov Bilgileri:** Bungalov listesi, detaylar
- **Feribot Bilgileri:** Seferler, tarifeler
- **Rezervasyon Formu:** Entegre rezervasyon formu
- **İletişim Formu:** Dinamik iletişim formu

### 9. Tema Tipleri
- **Tip 1: Tek Otel/Tur/Bungalov/Feribot Sitesi**
  - Tek otel için özel site
  - Tek tur acentesi için site
  - Tek bungalov işletmesi için site
  - Tek feribot şirketi için site

- **Tip 2: Çoklu Acente Sitesi**
  - Çoklu otel acentesi
  - Çoklu tur acentesi
  - Karışık acente (otel + tur + bungalov)

### 10. AI Desteği
- **AI Website Oluşturma:** Otomatik site oluşturma
- **AI İçerik Oluşturma:** Metin, başlık, açıklama oluşturma
- **AI Tasarım Önerileri:** Renk, layout önerileri
- **AI SEO Optimizasyonu:** Meta tags, açıklamalar
- **AI Şablon Önerileri:** Uygun şablon önerileri

### 11. Theme Sistemi (Gelecek)
- Tema oluşturma/düzenleme
- Tema mağazası
- Tema paylaşımı
- Tema özelleştirme

## 🏗️ Mimari Yapı

### Backend (Django)
```
apps/tenant_apps/website_builder/
├── models.py              # Website, Page, Component, Menu, Template modelleri
├── views.py               # CRUD işlemleri, API endpoints
├── forms.py               # Form sınıfları
├── admin.py               # Admin paneli
├── urls.py                # URL yapılandırması
├── serializers.py         # API serializers
├── utils.py               # Yardımcı fonksiyonlar
├── ai_utils.py            # AI entegrasyon fonksiyonları
├── integrations/          # Veri entegrasyonları
│   ├── hotels.py          # Otel verileri
│   ├── tours.py            # Tur verileri
│   ├── bungalovs.py       # Bungalov verileri
│   ├── ferry_tickets.py   # Feribot verileri
│   └── reception.py       # Rezervasyon verileri
├── templates/             # Django şablonları
│   ├── website_builder/
│   │   ├── builder.html    # GrapesJS editor
│   │   ├── pages/
│   │   ├── menus/
│   │   ├── templates/
│   │   └── settings/
│   └── website/           # Oluşturulan sitelerin render edildiği şablonlar
│       ├── base.html
│       ├── page.html
│       └── components/
└── static/
    └── website_builder/
        ├── js/
        │   ├── builder.js  # GrapesJS entegrasyonu
        │   ├── components.js
        │   └── ai.js       # AI entegrasyonu
        └── css/
            └── builder.css
```

### Frontend (GrapesJS)
- GrapesJS core
- Custom plugins
- Component library
- AI integration UI

## 📊 Veritabanı Modelleri

### Website
- `id`, `name`, `slug`, `description`
- `website_type` (single_hotel, single_tour, single_bungalov, single_ferry, multi_hotel, multi_tour, multi_mixed)
- `status` (draft, published, archived)
- `domain` (custom domain)
- `settings` (JSON)
- `theme_id` (gelecek için)
- `hotel` (ForeignKey, nullable - tek otel için)
- `created_by`, `created_at`, `updated_at`

### Page
- `id`, `website` (ForeignKey)
- `title`, `slug`, `path`
- `page_type` (home, page, rooms, activities, gallery, services, contact, reservation, custom)
- `content` (JSON - GrapesJS content)
- `meta_title`, `meta_description`, `meta_keywords`
- `custom_css`, `custom_js`
- `is_published`, `is_homepage`
- `sort_order`
- `created_at`, `updated_at`

### Component
- `id`, `name`, `code`, `category`
- `component_type` (block, section, widget)
- `template` (HTML template)
- `settings` (JSON - ayarlanabilir özellikler)
- `is_active`, `is_system`
- `created_at`, `updated_at`

### Menu
- `id`, `website` (ForeignKey)
- `name`, `code`
- `items` (JSON - menü yapısı)
- `location` (header, footer, sidebar)
- `is_active`
- `created_at`, `updated_at`

### Template
- `id`, `name`, `description`
- `template_type` (page, header, footer, full_site)
- `category` (hotel, tour, bungalov, ferry, agency)
- `preview_image`
- `content` (JSON)
- `is_active`, `is_premium`
- `created_at`, `updated_at`

### WebsiteSettings
- `id`, `website` (OneToOneField)
- `header_config` (JSON)
- `footer_config` (JSON)
- `seo_settings` (JSON)
- `analytics_code`
- `custom_domain`
- `created_at`, `updated_at`

## 🔌 Entegrasyonlar

### 1. Otel Modülü Entegrasyonu
- Oda tipleri listesi
- Oda detayları
- Otel bilgileri
- Hizmetler
- Galeri görselleri
- Rezervasyon formu

### 2. Tur Modülü Entegrasyonu
- Tur listesi
- Tur detayları
- Tur kategorileri
- Rezervasyon formu

### 3. Bungalov Modülü Entegrasyonu
- Bungalov listesi
- Bungalov detayları
- Rezervasyon formu

### 4. Feribot Modülü Entegrasyonu
- Sefer listesi
- Tarife bilgileri
- Bilet satış formu

### 5. Rezervasyon Modülü Entegrasyonu
- Rezervasyon formu widget'ı
- Rezervasyon durumu gösterimi

## 🤖 AI Entegrasyonu

### AI Kullanım Senaryoları

1. **AI Website Oluşturma**
   - Kullanıcı bilgileri girilir (sektör, özellikler, renk tercihleri)
   - AI otomatik site oluşturur
   - Şablon seçimi ve içerik oluşturma

2. **AI İçerik Oluşturma**
   - Sayfa başlıkları
   - Meta açıklamaları
   - Blog yazıları
   - Ürün açıklamaları

3. **AI Tasarım Önerileri**
   - Renk paleti önerileri
   - Layout önerileri
   - Bileşen yerleşim önerileri

4. **AI SEO Optimizasyonu**
   - Meta tag önerileri
   - SEO açıklamaları
   - Anahtar kelime önerileri

### AI API Entegrasyonu
- Mevcut `apps/ai/services.py` kullanılacak
- `generate_ai_content()` fonksiyonu kullanılacak
- Paket bazlı AI kredi kontrolü yapılacak

## 📦 Paket Yönetimi

### Modül Ekleme
- `apps/modules/models.py` içine yeni modül eklenecek
- Paketlere modül eklenecek
- Limitler tanımlanacak:
  - `max_websites`: Maksimum website sayısı
  - `max_pages_per_website`: Website başına maksimum sayfa
  - `max_ai_generations`: AI ile oluşturma limiti
  - `custom_domain`: Özel domain desteği

## 🎨 GrapesJS Entegrasyonu

### Kurulum
```bash
npm install grapesjs
```

### Özelleştirmeler
- Custom blocks (oda kartı, tur kartı, rezervasyon formu)
- Custom components (otel bilgileri, hizmetler, galeri)
- Custom plugins (AI assistant, template library)
- Custom panels (veri entegrasyonu paneli)

## 📱 Responsive Tasarım

### Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

### Özellikler
- Her breakpoint için ayrı düzenleme
- Canlı önizleme
- Responsive görüntüleme

## 🚀 Geliştirme Fazları

### Faz 1: Temel Altyapı (1-2 hafta)
- Modül oluşturma
- Veritabanı modelleri
- Admin paneli
- Temel CRUD işlemleri

### Faz 2: GrapesJS Entegrasyonu (2-3 hafta)
- GrapesJS kurulumu
- Editor arayüzü
- Temel bileşenler
- Sayfa kaydetme/yükleme

### Faz 3: Bileşen Kütüphanesi (2-3 hafta)
- Sistem bileşenleri
- Veri entegrasyon bileşenleri
- Şablon bileşenleri

### Faz 4: Menü ve Header/Footer (1-2 hafta)
- Menü builder
- Header builder
- Footer builder
- Widget sistemi

### Faz 5: Şablonlar (2-3 hafta)
- Şablon oluşturma
- Şablon kütüphanesi
- Şablon uygulama

### Faz 6: Veri Entegrasyonları (2-3 hafta)
- Otel entegrasyonu
- Tur entegrasyonu
- Bungalov entegrasyonu
- Feribot entegrasyonu
- Rezervasyon entegrasyonu

### Faz 7: AI Entegrasyonu (2-3 hafta)
- AI website oluşturma
- AI içerik oluşturma
- AI tasarım önerileri
- AI SEO optimizasyonu

### Faz 8: Responsive ve Mobil (1-2 hafta)
- Responsive düzenleme
- Mobil önizleme
- Tablet önizleme

### Faz 9: Site Render ve Yayınlama (1-2 hafta)
- Site render sistemi
- Domain yönetimi
- Yayınlama sistemi

### Faz 10: Test ve Optimizasyon (1-2 hafta)
- Testler
- Performans optimizasyonu
- Hata düzeltmeleri

## 📝 Notlar

- Theme sistemi gelecekte eklenecek, şimdilik temel yapı hazırlanacak
- AI entegrasyonu mevcut paket sisteminden gelecek
- Veri entegrasyonları dinamik olacak, kullanıcı dostu arayüzle
- Şablonlar bol miktarda olacak, kategorize edilecek
- Responsive tasarım her aşamada dikkate alınacak




## 📋 Genel Bakış

Drag and Drop Website Builder modülü, kullanıcıların kod yazmadan profesyonel web siteleri oluşturmasına olanak tanıyan kapsamlı bir sistemdir.

## 🎯 Temel Özellikler

### 1. Drag and Drop Builder
- **Kütüphane Seçimi:** GrapesJS (en popüler, ücretsiz, açık kaynak)
- **Özellikler:**
  - Görsel sayfa düzenleyici
  - Bileşen kütüphanesi
  - Responsive tasarım desteği
  - Mobil önizleme
  - Canlı düzenleme

### 2. Sayfa Yönetimi
- Sayfa ekleme/düzenleme/silme
- Sayfa şablonları
- Sayfa ayarları (SEO, meta tags, custom CSS/JS)
- Sayfa durumu (draft, published, archived)

### 3. Menü Yönetimi
- Menü oluşturucu/düzenleyici
- Çok seviyeli menü desteği
- Drag and drop menü sıralama
- Menü şablonları

### 4. Header ve Footer
- Her sayfada standart header/footer
- Header/footer düzenleyici
- Logo, menü, sosyal medya linkleri
- Widget desteği

### 5. Slider/Hero Builder
- Anasayfa için slider oluşturucu
- Hero section builder
- Görsel/video desteği
- Animasyon efektleri

### 6. Responsive Tasarım
- Mobil arayüz ayrı düzenleme
- Tablet görünümü
- Desktop görünümü
- Canlı önizleme

### 7. Şablonlar
- Header şablonları
- Footer şablonları
- Sayfa şablonları (rooms, aktiviteler, galeri, hizmetlerimiz, iletişim, iletişim formu, rezervasyon)
- Tam site şablonları

### 8. Veri Entegrasyonu
- **Oda Tipleri:** Otomatik oda listesi ekleme
- **Otel Bilgileri:** Hizmetler, özellikler, bilgiler
- **Tur Bilgileri:** Tur listesi, detaylar
- **Bungalov Bilgileri:** Bungalov listesi, detaylar
- **Feribot Bilgileri:** Seferler, tarifeler
- **Rezervasyon Formu:** Entegre rezervasyon formu
- **İletişim Formu:** Dinamik iletişim formu

### 9. Tema Tipleri
- **Tip 1: Tek Otel/Tur/Bungalov/Feribot Sitesi**
  - Tek otel için özel site
  - Tek tur acentesi için site
  - Tek bungalov işletmesi için site
  - Tek feribot şirketi için site

- **Tip 2: Çoklu Acente Sitesi**
  - Çoklu otel acentesi
  - Çoklu tur acentesi
  - Karışık acente (otel + tur + bungalov)

### 10. AI Desteği
- **AI Website Oluşturma:** Otomatik site oluşturma
- **AI İçerik Oluşturma:** Metin, başlık, açıklama oluşturma
- **AI Tasarım Önerileri:** Renk, layout önerileri
- **AI SEO Optimizasyonu:** Meta tags, açıklamalar
- **AI Şablon Önerileri:** Uygun şablon önerileri

### 11. Theme Sistemi (Gelecek)
- Tema oluşturma/düzenleme
- Tema mağazası
- Tema paylaşımı
- Tema özelleştirme

## 🏗️ Mimari Yapı

### Backend (Django)
```
apps/tenant_apps/website_builder/
├── models.py              # Website, Page, Component, Menu, Template modelleri
├── views.py               # CRUD işlemleri, API endpoints
├── forms.py               # Form sınıfları
├── admin.py               # Admin paneli
├── urls.py                # URL yapılandırması
├── serializers.py         # API serializers
├── utils.py               # Yardımcı fonksiyonlar
├── ai_utils.py            # AI entegrasyon fonksiyonları
├── integrations/          # Veri entegrasyonları
│   ├── hotels.py          # Otel verileri
│   ├── tours.py            # Tur verileri
│   ├── bungalovs.py       # Bungalov verileri
│   ├── ferry_tickets.py   # Feribot verileri
│   └── reception.py       # Rezervasyon verileri
├── templates/             # Django şablonları
│   ├── website_builder/
│   │   ├── builder.html    # GrapesJS editor
│   │   ├── pages/
│   │   ├── menus/
│   │   ├── templates/
│   │   └── settings/
│   └── website/           # Oluşturulan sitelerin render edildiği şablonlar
│       ├── base.html
│       ├── page.html
│       └── components/
└── static/
    └── website_builder/
        ├── js/
        │   ├── builder.js  # GrapesJS entegrasyonu
        │   ├── components.js
        │   └── ai.js       # AI entegrasyonu
        └── css/
            └── builder.css
```

### Frontend (GrapesJS)
- GrapesJS core
- Custom plugins
- Component library
- AI integration UI

## 📊 Veritabanı Modelleri

### Website
- `id`, `name`, `slug`, `description`
- `website_type` (single_hotel, single_tour, single_bungalov, single_ferry, multi_hotel, multi_tour, multi_mixed)
- `status` (draft, published, archived)
- `domain` (custom domain)
- `settings` (JSON)
- `theme_id` (gelecek için)
- `hotel` (ForeignKey, nullable - tek otel için)
- `created_by`, `created_at`, `updated_at`

### Page
- `id`, `website` (ForeignKey)
- `title`, `slug`, `path`
- `page_type` (home, page, rooms, activities, gallery, services, contact, reservation, custom)
- `content` (JSON - GrapesJS content)
- `meta_title`, `meta_description`, `meta_keywords`
- `custom_css`, `custom_js`
- `is_published`, `is_homepage`
- `sort_order`
- `created_at`, `updated_at`

### Component
- `id`, `name`, `code`, `category`
- `component_type` (block, section, widget)
- `template` (HTML template)
- `settings` (JSON - ayarlanabilir özellikler)
- `is_active`, `is_system`
- `created_at`, `updated_at`

### Menu
- `id`, `website` (ForeignKey)
- `name`, `code`
- `items` (JSON - menü yapısı)
- `location` (header, footer, sidebar)
- `is_active`
- `created_at`, `updated_at`

### Template
- `id`, `name`, `description`
- `template_type` (page, header, footer, full_site)
- `category` (hotel, tour, bungalov, ferry, agency)
- `preview_image`
- `content` (JSON)
- `is_active`, `is_premium`
- `created_at`, `updated_at`

### WebsiteSettings
- `id`, `website` (OneToOneField)
- `header_config` (JSON)
- `footer_config` (JSON)
- `seo_settings` (JSON)
- `analytics_code`
- `custom_domain`
- `created_at`, `updated_at`

## 🔌 Entegrasyonlar

### 1. Otel Modülü Entegrasyonu
- Oda tipleri listesi
- Oda detayları
- Otel bilgileri
- Hizmetler
- Galeri görselleri
- Rezervasyon formu

### 2. Tur Modülü Entegrasyonu
- Tur listesi
- Tur detayları
- Tur kategorileri
- Rezervasyon formu

### 3. Bungalov Modülü Entegrasyonu
- Bungalov listesi
- Bungalov detayları
- Rezervasyon formu

### 4. Feribot Modülü Entegrasyonu
- Sefer listesi
- Tarife bilgileri
- Bilet satış formu

### 5. Rezervasyon Modülü Entegrasyonu
- Rezervasyon formu widget'ı
- Rezervasyon durumu gösterimi

## 🤖 AI Entegrasyonu

### AI Kullanım Senaryoları

1. **AI Website Oluşturma**
   - Kullanıcı bilgileri girilir (sektör, özellikler, renk tercihleri)
   - AI otomatik site oluşturur
   - Şablon seçimi ve içerik oluşturma

2. **AI İçerik Oluşturma**
   - Sayfa başlıkları
   - Meta açıklamaları
   - Blog yazıları
   - Ürün açıklamaları

3. **AI Tasarım Önerileri**
   - Renk paleti önerileri
   - Layout önerileri
   - Bileşen yerleşim önerileri

4. **AI SEO Optimizasyonu**
   - Meta tag önerileri
   - SEO açıklamaları
   - Anahtar kelime önerileri

### AI API Entegrasyonu
- Mevcut `apps/ai/services.py` kullanılacak
- `generate_ai_content()` fonksiyonu kullanılacak
- Paket bazlı AI kredi kontrolü yapılacak

## 📦 Paket Yönetimi

### Modül Ekleme
- `apps/modules/models.py` içine yeni modül eklenecek
- Paketlere modül eklenecek
- Limitler tanımlanacak:
  - `max_websites`: Maksimum website sayısı
  - `max_pages_per_website`: Website başına maksimum sayfa
  - `max_ai_generations`: AI ile oluşturma limiti
  - `custom_domain`: Özel domain desteği

## 🎨 GrapesJS Entegrasyonu

### Kurulum
```bash
npm install grapesjs
```

### Özelleştirmeler
- Custom blocks (oda kartı, tur kartı, rezervasyon formu)
- Custom components (otel bilgileri, hizmetler, galeri)
- Custom plugins (AI assistant, template library)
- Custom panels (veri entegrasyonu paneli)

## 📱 Responsive Tasarım

### Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

### Özellikler
- Her breakpoint için ayrı düzenleme
- Canlı önizleme
- Responsive görüntüleme

## 🚀 Geliştirme Fazları

### Faz 1: Temel Altyapı (1-2 hafta)
- Modül oluşturma
- Veritabanı modelleri
- Admin paneli
- Temel CRUD işlemleri

### Faz 2: GrapesJS Entegrasyonu (2-3 hafta)
- GrapesJS kurulumu
- Editor arayüzü
- Temel bileşenler
- Sayfa kaydetme/yükleme

### Faz 3: Bileşen Kütüphanesi (2-3 hafta)
- Sistem bileşenleri
- Veri entegrasyon bileşenleri
- Şablon bileşenleri

### Faz 4: Menü ve Header/Footer (1-2 hafta)
- Menü builder
- Header builder
- Footer builder
- Widget sistemi

### Faz 5: Şablonlar (2-3 hafta)
- Şablon oluşturma
- Şablon kütüphanesi
- Şablon uygulama

### Faz 6: Veri Entegrasyonları (2-3 hafta)
- Otel entegrasyonu
- Tur entegrasyonu
- Bungalov entegrasyonu
- Feribot entegrasyonu
- Rezervasyon entegrasyonu

### Faz 7: AI Entegrasyonu (2-3 hafta)
- AI website oluşturma
- AI içerik oluşturma
- AI tasarım önerileri
- AI SEO optimizasyonu

### Faz 8: Responsive ve Mobil (1-2 hafta)
- Responsive düzenleme
- Mobil önizleme
- Tablet önizleme

### Faz 9: Site Render ve Yayınlama (1-2 hafta)
- Site render sistemi
- Domain yönetimi
- Yayınlama sistemi

### Faz 10: Test ve Optimizasyon (1-2 hafta)
- Testler
- Performans optimizasyonu
- Hata düzeltmeleri

## 📝 Notlar

- Theme sistemi gelecekte eklenecek, şimdilik temel yapı hazırlanacak
- AI entegrasyonu mevcut paket sisteminden gelecek
- Veri entegrasyonları dinamik olacak, kullanıcı dostu arayüzle
- Şablonlar bol miktarda olacak, kategorize edilecek
- Responsive tasarım her aşamada dikkate alınacak




