# Website Builder Modülü - Kapsamlı Dokümantasyon

## 📋 Genel Bakış

Website Builder modülü, drag-and-drop (sürükle-bırak) tabanlı, kod gerektirmeyen bir website oluşturucu sistemidir. GrapesJS kütüphanesi kullanılarak geliştirilmiştir ve otel, tur, bungalov, feribot bileti gibi farklı işletme tipleri için website oluşturma imkanı sunar.

## 🎯 Özellikler

### 1. Temel Özellikler
- ✅ Drag-and-drop sayfa düzenleyici (GrapesJS)
- ✅ Kod gerektirmeyen website oluşturma
- ✅ Responsive tasarım desteği
- ✅ Çoklu website yönetimi
- ✅ Menü yönetimi (hierarchical)
- ✅ Header/Footer builder
- ✅ Şablon sistemi
- ✅ Tema yönetimi

### 2. Veri Entegrasyonları
- ✅ Otel entegrasyonu (oda tipleri, otel bilgileri, hizmetler)
- ✅ Tur entegrasyonu
- ✅ Bungalov entegrasyonu
- ✅ Feribot entegrasyonu
- ✅ Rezervasyon formu entegrasyonu
- ✅ İletişim formu entegrasyonu
- ✅ Galeri entegrasyonu

### 3. AI Özellikleri
- ✅ AI ile website oluşturma
- ✅ AI ile içerik oluşturma
- ✅ AI tasarım önerileri
- ✅ AI SEO optimizasyonu
- ✅ AI bileşen oluşturma

### 4. Responsive ve Mobil
- ✅ 6 farklı cihaz boyutu desteği
- ✅ Responsive önizleme
- ✅ Breakpoint yönetimi
- ✅ Mobil optimizasyon önerileri

### 5. Yayınlama ve SEO
- ✅ Website yayınlama sistemi
- ✅ Domain yönetimi
- ✅ Public URL oluşturma
- ✅ Sitemap XML oluşturma
- ✅ Robots.txt oluşturma
- ✅ Cache yönetimi

## 📁 Modül Yapısı

```
apps/tenant_apps/website_builder/
├── __init__.py
├── apps.py
├── models.py              # Website, Page, Menu, Component, Template, Theme, WebsiteSettings, MenuItem
├── admin.py               # Django admin kayıtları
├── forms.py               # Form sınıfları
├── urls.py                # URL routing
├── views.py               # Temel CRUD view'ları
├── views_api.py           # API endpoint'leri
├── views_menu.py          # Menü builder view'ları
├── views_public.py        # Public preview view'ları
├── views_ai.py            # AI entegrasyon view'ları
├── views_responsive.py    # Responsive view'ları
├── views_publish.py       # Yayınlama view'ları
├── component_blocks.py    # Bileşen blok tanımları
├── component_handlers.js  # Frontend component handlers
├── data_renderers.py      # Veri render fonksiyonları
├── page_renderer.py       # Sayfa render sistemi
├── menu_builder.py        # Menü builder utilities
├── header_footer_builder.py # Header/Footer builder utilities
├── template_library.py    # Şablon kütüphanesi
├── template_utils.py      # Şablon utility fonksiyonları
├── ai_integration.py      # AI entegrasyon fonksiyonları
├── responsive_utils.py    # Responsive utility fonksiyonları
├── publish_utils.py      # Yayınlama utility fonksiyonları
├── tests.py               # Test dosyası
└── migrations/           # Database migration dosyaları
```

## 🗄️ Veritabanı Modelleri

### Website
- `name`: Website adı
- `slug`: URL slug
- `website_type`: Website tipi (single_hotel, multi_agency, vb.)
- `status`: Durum (draft, published, archived)
- `custom_domain`: Özel domain
- `subdomain`: Subdomain
- `hotel`: İlişkili otel (opsiyonel)
- `theme`: Website teması
- `meta_title`, `meta_description`, `meta_keywords`: SEO bilgileri
- `google_analytics_id`, `facebook_pixel_id`: Analytics

### Page
- `website`: İlişkili website
- `title`: Sayfa başlığı
- `slug`: URL slug
- `path`: Sayfa path'i
- `content`: GrapesJS içeriği (JSON)
- `page_type`: Sayfa tipi (home, rooms, contact, vb.)
- `is_homepage`: Anasayfa mı?
- `is_published`: Yayında mı?
- `meta_title`, `meta_description`, `meta_keywords`: SEO bilgileri

### Menu
- `website`: İlişkili website
- `name`: Menü adı
- `location`: Menü konumu (header, footer, vb.)
- `is_active`: Aktif mi?

### MenuItem
- `menu`: İlişkili menü
- `parent`: Üst menü öğesi (hierarchical)
- `label`: Menü etiketi
- `url`: URL
- `page`: İlişkili sayfa (opsiyonel)
- `icon`: İkon
- `order`: Sıralama
- `is_active`: Aktif mi?

### Component
- `name`: Bileşen adı
- `category`: Kategori
- `content`: HTML içeriği
- `css`: CSS kodu
- `js`: JavaScript kodu

### Template
- `name`: Şablon adı
- `template_type`: Şablon tipi (page, header, footer)
- `category`: Kategori
- `content`: Şablon içeriği (JSON)
- `preview_image`: Önizleme görseli

### Theme
- `name`: Tema adı
- `theme_type`: Tema tipi (system, custom)
- `preview_image`: Önizleme görseli
- `file_path`: Tema dosya yolu

### WebsiteSettings
- `website`: İlişkili website (OneToOne)
- `logo`: Logo
- `favicon`: Favicon
- `header_config`: Header ayarları (JSON)
- `footer_config`: Footer ayarları (JSON)
- `social_media`: Sosyal medya linkleri (JSON)

## 🔗 URL Yapısı

### Website Yönetimi
- `/website-builder/` - Website listesi
- `/website-builder/create/` - Website oluştur
- `/website-builder/<id>/` - Website detay
- `/website-builder/<id>/edit/` - Website düzenle
- `/website-builder/<id>/delete/` - Website sil

### Sayfa Yönetimi
- `/website-builder/<website_id>/pages/` - Sayfa listesi
- `/website-builder/<website_id>/pages/create/` - Sayfa oluştur
- `/website-builder/pages/<id>/` - Sayfa detay
- `/website-builder/pages/<id>/edit/` - Sayfa düzenle
- `/website-builder/pages/<id>/delete/` - Sayfa sil

### Builder
- `/website-builder/builder/<page_id>/` - GrapesJS editor
- `/website-builder/builder/<page_id>/save/` - Sayfa kaydet
- `/website-builder/builder/<page_id>/load/` - Sayfa yükle

### Menü Yönetimi
- `/website-builder/<website_id>/menus/` - Menü listesi
- `/website-builder/<website_id>/menus/create/` - Menü oluştur
- `/website-builder/menus/<id>/builder/` - Menü builder
- `/website-builder/menus/<id>/builder/save/` - Menü kaydet

### AI Endpoints
- `/website-builder/ai/credit-check/` - AI kredi kontrolü
- `/website-builder/ai/generate-website/` - AI website oluştur
- `/website-builder/ai/pages/<id>/generate-content/` - AI içerik oluştur
- `/website-builder/ai/pages/<id>/design-suggestions/` - AI tasarım önerileri
- `/website-builder/ai/pages/<id>/optimize-seo/` - AI SEO optimizasyonu
- `/website-builder/ai/generate-component/` - AI bileşen oluştur

### Responsive Endpoints
- `/website-builder/responsive/pages/<id>/preview/<device>/` - Responsive önizleme
- `/website-builder/responsive/pages/<id>/validate/` - Responsive doğrulama
- `/website-builder/responsive/pages/<id>/optimize-mobile/` - Mobil optimizasyon

### Yayınlama Endpoints
- `/website-builder/websites/<id>/publish/` - Website yayınla
- `/website-builder/websites/<id>/unpublish/` - Website yayından kaldır
- `/website-builder/websites/<id>/set-domain/` - Domain ayarla
- `/website-builder/websites/<id>/public-url/` - Public URL al
- `/website-builder/websites/<id>/invalidate-cache/` - Cache temizle

### Public Preview
- `/website-builder/preview/<slug>/` - Website önizleme
- `/website-builder/preview/<slug>/<path>` - Sayfa önizleme
- `/website-builder/sitemap/<slug>.xml` - Sitemap XML
- `/website-builder/robots/<slug>.txt` - Robots.txt

## 🎨 Kullanım Senaryoları

### Senaryo 1: Tek Otel Web Sitesi Oluşturma
1. Website oluştur (website_type: single_hotel)
2. Otel seç
3. Anasayfa oluştur
4. Oda sayfası oluştur (veri entegrasyonu ile)
5. İletişim sayfası oluştur
6. Menü oluştur ve sayfaları ekle
7. Header/Footer ayarla
8. Website'i yayınla

### Senaryo 2: AI ile Website Oluşturma
1. AI ile website oluştur butonuna tıkla
2. Website açıklaması gir
3. AI website'i oluşturur
4. İçeriği düzenle
5. AI tasarım önerileri al
6. AI SEO optimizasyonu yap
7. Website'i yayınla

### Senaryo 3: Responsive Tasarım
1. Builder'da sayfa düzenle
2. Device manager'dan cihaz seç
3. Responsive önizleme aç
4. Breakpoint seç
5. Mobil optimizasyon önerileri al
6. Responsive doğrulama yap

## 🔧 API Kullanımı

### Veri Entegrasyon API'leri
```javascript
// Oda listesi
fetch('/website-builder/api/rooms/?hotel_id=1&limit=10')
  .then(response => response.json())
  .then(data => console.log(data.rooms));

// Otel bilgileri
fetch('/website-builder/api/hotels/1/')
  .then(response => response.json())
  .then(data => console.log(data.hotel));

// Tur listesi
fetch('/website-builder/api/tours/?limit=10')
  .then(response => response.json())
  .then(data => console.log(data.tours));
```

### AI API Kullanımı
```javascript
// AI içerik oluştur
fetch('/website-builder/ai/pages/1/generate-content/', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRFToken': csrfToken
  },
  body: JSON.stringify({
    prompt: 'Modern bir otel anasayfası oluştur'
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    // İçeriği editor'a ekle
    editor.addComponents(data.content.html);
  }
});
```

## 📝 Best Practices

### 1. Website Oluşturma
- Website tipini doğru seç
- Slug'ı benzersiz ve SEO-friendly yap
- SEO bilgilerini doldur
- Domain'i doğru formatta gir

### 2. Sayfa Oluşturma
- Her sayfa için benzersiz slug kullan
- Path'i doğru ayarla
- SEO meta bilgilerini doldur
- Responsive tasarım yap

### 3. Veri Entegrasyonu
- Veri entegrasyon bileşenlerini doğru kullan
- Oda/Tur ID'lerini doğru gir
- Hata durumlarını handle et

### 4. AI Kullanımı
- AI kredisi kontrolü yap
- Prompt'ları detaylı yaz
- AI önerilerini değerlendir

### 5. Yayınlama
- Yayınlamadan önce önizleme yap
- Domain'i doğru ayarla
- Cache'i temizle
- Sitemap ve robots.txt kontrol et

## 🐛 Bilinen Sorunlar ve Çözümler

### Sorun 1: GrapesJS Editor Yüklenmiyor
**Çözüm**: CDN linklerinin doğru yüklendiğinden emin olun. Browser console'u kontrol edin.

### Sorun 2: Veri Entegrasyon Bileşenleri Çalışmıyor
**Çözüm**: `component_handlers.js` dosyasının yüklendiğinden ve API endpoint'lerinin çalıştığından emin olun.

### Sorun 3: AI İçerik Oluşturma Başarısız
**Çözüm**: AI kredisi kontrolü yapın. Paket AI modelinin aktif olduğundan emin olun.

### Sorun 4: Domain Ayarlama Çalışmıyor
**Çözüm**: Domain formatını kontrol edin (example.com, https:// eklemeyin). Domain kullanılabilirlik kontrolü yapın.

## 🚀 Performans İpuçları

1. **Cache Kullanımı**: Website cache'ini düzenli temizleyin
2. **Görsel Optimizasyonu**: Görselleri optimize edin
3. **CSS Minimize**: CSS kodunu minimize edin
4. **Lazy Loading**: Görseller için lazy loading kullanın
5. **CDN Kullanımı**: Statik dosyalar için CDN kullanın

## 📚 Ek Kaynaklar

- [GrapesJS Dokümantasyonu](https://grapesjs.com/docs/)
- [Django Dokümantasyonu](https://docs.djangoproject.com/)
- [Website Builder API Dokümantasyonu](#)

## 🔄 Güncelleme Notları

### Versiyon 1.0 (İlk Sürüm)
- Temel website oluşturma
- GrapesJS entegrasyonu
- Veri entegrasyonları
- AI entegrasyonu
- Responsive desteği
- Yayınlama sistemi

## 📞 Destek

Sorularınız için:
- GitHub Issues: [Repository URL]
- Email: support@example.com
- Dokümantasyon: [Documentation URL]




## 📋 Genel Bakış

Website Builder modülü, drag-and-drop (sürükle-bırak) tabanlı, kod gerektirmeyen bir website oluşturucu sistemidir. GrapesJS kütüphanesi kullanılarak geliştirilmiştir ve otel, tur, bungalov, feribot bileti gibi farklı işletme tipleri için website oluşturma imkanı sunar.

## 🎯 Özellikler

### 1. Temel Özellikler
- ✅ Drag-and-drop sayfa düzenleyici (GrapesJS)
- ✅ Kod gerektirmeyen website oluşturma
- ✅ Responsive tasarım desteği
- ✅ Çoklu website yönetimi
- ✅ Menü yönetimi (hierarchical)
- ✅ Header/Footer builder
- ✅ Şablon sistemi
- ✅ Tema yönetimi

### 2. Veri Entegrasyonları
- ✅ Otel entegrasyonu (oda tipleri, otel bilgileri, hizmetler)
- ✅ Tur entegrasyonu
- ✅ Bungalov entegrasyonu
- ✅ Feribot entegrasyonu
- ✅ Rezervasyon formu entegrasyonu
- ✅ İletişim formu entegrasyonu
- ✅ Galeri entegrasyonu

### 3. AI Özellikleri
- ✅ AI ile website oluşturma
- ✅ AI ile içerik oluşturma
- ✅ AI tasarım önerileri
- ✅ AI SEO optimizasyonu
- ✅ AI bileşen oluşturma

### 4. Responsive ve Mobil
- ✅ 6 farklı cihaz boyutu desteği
- ✅ Responsive önizleme
- ✅ Breakpoint yönetimi
- ✅ Mobil optimizasyon önerileri

### 5. Yayınlama ve SEO
- ✅ Website yayınlama sistemi
- ✅ Domain yönetimi
- ✅ Public URL oluşturma
- ✅ Sitemap XML oluşturma
- ✅ Robots.txt oluşturma
- ✅ Cache yönetimi

## 📁 Modül Yapısı

```
apps/tenant_apps/website_builder/
├── __init__.py
├── apps.py
├── models.py              # Website, Page, Menu, Component, Template, Theme, WebsiteSettings, MenuItem
├── admin.py               # Django admin kayıtları
├── forms.py               # Form sınıfları
├── urls.py                # URL routing
├── views.py               # Temel CRUD view'ları
├── views_api.py           # API endpoint'leri
├── views_menu.py          # Menü builder view'ları
├── views_public.py        # Public preview view'ları
├── views_ai.py            # AI entegrasyon view'ları
├── views_responsive.py    # Responsive view'ları
├── views_publish.py       # Yayınlama view'ları
├── component_blocks.py    # Bileşen blok tanımları
├── component_handlers.js  # Frontend component handlers
├── data_renderers.py      # Veri render fonksiyonları
├── page_renderer.py       # Sayfa render sistemi
├── menu_builder.py        # Menü builder utilities
├── header_footer_builder.py # Header/Footer builder utilities
├── template_library.py    # Şablon kütüphanesi
├── template_utils.py      # Şablon utility fonksiyonları
├── ai_integration.py      # AI entegrasyon fonksiyonları
├── responsive_utils.py    # Responsive utility fonksiyonları
├── publish_utils.py      # Yayınlama utility fonksiyonları
├── tests.py               # Test dosyası
└── migrations/           # Database migration dosyaları
```

## 🗄️ Veritabanı Modelleri

### Website
- `name`: Website adı
- `slug`: URL slug
- `website_type`: Website tipi (single_hotel, multi_agency, vb.)
- `status`: Durum (draft, published, archived)
- `custom_domain`: Özel domain
- `subdomain`: Subdomain
- `hotel`: İlişkili otel (opsiyonel)
- `theme`: Website teması
- `meta_title`, `meta_description`, `meta_keywords`: SEO bilgileri
- `google_analytics_id`, `facebook_pixel_id`: Analytics

### Page
- `website`: İlişkili website
- `title`: Sayfa başlığı
- `slug`: URL slug
- `path`: Sayfa path'i
- `content`: GrapesJS içeriği (JSON)
- `page_type`: Sayfa tipi (home, rooms, contact, vb.)
- `is_homepage`: Anasayfa mı?
- `is_published`: Yayında mı?
- `meta_title`, `meta_description`, `meta_keywords`: SEO bilgileri

### Menu
- `website`: İlişkili website
- `name`: Menü adı
- `location`: Menü konumu (header, footer, vb.)
- `is_active`: Aktif mi?

### MenuItem
- `menu`: İlişkili menü
- `parent`: Üst menü öğesi (hierarchical)
- `label`: Menü etiketi
- `url`: URL
- `page`: İlişkili sayfa (opsiyonel)
- `icon`: İkon
- `order`: Sıralama
- `is_active`: Aktif mi?

### Component
- `name`: Bileşen adı
- `category`: Kategori
- `content`: HTML içeriği
- `css`: CSS kodu
- `js`: JavaScript kodu

### Template
- `name`: Şablon adı
- `template_type`: Şablon tipi (page, header, footer)
- `category`: Kategori
- `content`: Şablon içeriği (JSON)
- `preview_image`: Önizleme görseli

### Theme
- `name`: Tema adı
- `theme_type`: Tema tipi (system, custom)
- `preview_image`: Önizleme görseli
- `file_path`: Tema dosya yolu

### WebsiteSettings
- `website`: İlişkili website (OneToOne)
- `logo`: Logo
- `favicon`: Favicon
- `header_config`: Header ayarları (JSON)
- `footer_config`: Footer ayarları (JSON)
- `social_media`: Sosyal medya linkleri (JSON)

## 🔗 URL Yapısı

### Website Yönetimi
- `/website-builder/` - Website listesi
- `/website-builder/create/` - Website oluştur
- `/website-builder/<id>/` - Website detay
- `/website-builder/<id>/edit/` - Website düzenle
- `/website-builder/<id>/delete/` - Website sil

### Sayfa Yönetimi
- `/website-builder/<website_id>/pages/` - Sayfa listesi
- `/website-builder/<website_id>/pages/create/` - Sayfa oluştur
- `/website-builder/pages/<id>/` - Sayfa detay
- `/website-builder/pages/<id>/edit/` - Sayfa düzenle
- `/website-builder/pages/<id>/delete/` - Sayfa sil

### Builder
- `/website-builder/builder/<page_id>/` - GrapesJS editor
- `/website-builder/builder/<page_id>/save/` - Sayfa kaydet
- `/website-builder/builder/<page_id>/load/` - Sayfa yükle

### Menü Yönetimi
- `/website-builder/<website_id>/menus/` - Menü listesi
- `/website-builder/<website_id>/menus/create/` - Menü oluştur
- `/website-builder/menus/<id>/builder/` - Menü builder
- `/website-builder/menus/<id>/builder/save/` - Menü kaydet

### AI Endpoints
- `/website-builder/ai/credit-check/` - AI kredi kontrolü
- `/website-builder/ai/generate-website/` - AI website oluştur
- `/website-builder/ai/pages/<id>/generate-content/` - AI içerik oluştur
- `/website-builder/ai/pages/<id>/design-suggestions/` - AI tasarım önerileri
- `/website-builder/ai/pages/<id>/optimize-seo/` - AI SEO optimizasyonu
- `/website-builder/ai/generate-component/` - AI bileşen oluştur

### Responsive Endpoints
- `/website-builder/responsive/pages/<id>/preview/<device>/` - Responsive önizleme
- `/website-builder/responsive/pages/<id>/validate/` - Responsive doğrulama
- `/website-builder/responsive/pages/<id>/optimize-mobile/` - Mobil optimizasyon

### Yayınlama Endpoints
- `/website-builder/websites/<id>/publish/` - Website yayınla
- `/website-builder/websites/<id>/unpublish/` - Website yayından kaldır
- `/website-builder/websites/<id>/set-domain/` - Domain ayarla
- `/website-builder/websites/<id>/public-url/` - Public URL al
- `/website-builder/websites/<id>/invalidate-cache/` - Cache temizle

### Public Preview
- `/website-builder/preview/<slug>/` - Website önizleme
- `/website-builder/preview/<slug>/<path>` - Sayfa önizleme
- `/website-builder/sitemap/<slug>.xml` - Sitemap XML
- `/website-builder/robots/<slug>.txt` - Robots.txt

## 🎨 Kullanım Senaryoları

### Senaryo 1: Tek Otel Web Sitesi Oluşturma
1. Website oluştur (website_type: single_hotel)
2. Otel seç
3. Anasayfa oluştur
4. Oda sayfası oluştur (veri entegrasyonu ile)
5. İletişim sayfası oluştur
6. Menü oluştur ve sayfaları ekle
7. Header/Footer ayarla
8. Website'i yayınla

### Senaryo 2: AI ile Website Oluşturma
1. AI ile website oluştur butonuna tıkla
2. Website açıklaması gir
3. AI website'i oluşturur
4. İçeriği düzenle
5. AI tasarım önerileri al
6. AI SEO optimizasyonu yap
7. Website'i yayınla

### Senaryo 3: Responsive Tasarım
1. Builder'da sayfa düzenle
2. Device manager'dan cihaz seç
3. Responsive önizleme aç
4. Breakpoint seç
5. Mobil optimizasyon önerileri al
6. Responsive doğrulama yap

## 🔧 API Kullanımı

### Veri Entegrasyon API'leri
```javascript
// Oda listesi
fetch('/website-builder/api/rooms/?hotel_id=1&limit=10')
  .then(response => response.json())
  .then(data => console.log(data.rooms));

// Otel bilgileri
fetch('/website-builder/api/hotels/1/')
  .then(response => response.json())
  .then(data => console.log(data.hotel));

// Tur listesi
fetch('/website-builder/api/tours/?limit=10')
  .then(response => response.json())
  .then(data => console.log(data.tours));
```

### AI API Kullanımı
```javascript
// AI içerik oluştur
fetch('/website-builder/ai/pages/1/generate-content/', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRFToken': csrfToken
  },
  body: JSON.stringify({
    prompt: 'Modern bir otel anasayfası oluştur'
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    // İçeriği editor'a ekle
    editor.addComponents(data.content.html);
  }
});
```

## 📝 Best Practices

### 1. Website Oluşturma
- Website tipini doğru seç
- Slug'ı benzersiz ve SEO-friendly yap
- SEO bilgilerini doldur
- Domain'i doğru formatta gir

### 2. Sayfa Oluşturma
- Her sayfa için benzersiz slug kullan
- Path'i doğru ayarla
- SEO meta bilgilerini doldur
- Responsive tasarım yap

### 3. Veri Entegrasyonu
- Veri entegrasyon bileşenlerini doğru kullan
- Oda/Tur ID'lerini doğru gir
- Hata durumlarını handle et

### 4. AI Kullanımı
- AI kredisi kontrolü yap
- Prompt'ları detaylı yaz
- AI önerilerini değerlendir

### 5. Yayınlama
- Yayınlamadan önce önizleme yap
- Domain'i doğru ayarla
- Cache'i temizle
- Sitemap ve robots.txt kontrol et

## 🐛 Bilinen Sorunlar ve Çözümler

### Sorun 1: GrapesJS Editor Yüklenmiyor
**Çözüm**: CDN linklerinin doğru yüklendiğinden emin olun. Browser console'u kontrol edin.

### Sorun 2: Veri Entegrasyon Bileşenleri Çalışmıyor
**Çözüm**: `component_handlers.js` dosyasının yüklendiğinden ve API endpoint'lerinin çalıştığından emin olun.

### Sorun 3: AI İçerik Oluşturma Başarısız
**Çözüm**: AI kredisi kontrolü yapın. Paket AI modelinin aktif olduğundan emin olun.

### Sorun 4: Domain Ayarlama Çalışmıyor
**Çözüm**: Domain formatını kontrol edin (example.com, https:// eklemeyin). Domain kullanılabilirlik kontrolü yapın.

## 🚀 Performans İpuçları

1. **Cache Kullanımı**: Website cache'ini düzenli temizleyin
2. **Görsel Optimizasyonu**: Görselleri optimize edin
3. **CSS Minimize**: CSS kodunu minimize edin
4. **Lazy Loading**: Görseller için lazy loading kullanın
5. **CDN Kullanımı**: Statik dosyalar için CDN kullanın

## 📚 Ek Kaynaklar

- [GrapesJS Dokümantasyonu](https://grapesjs.com/docs/)
- [Django Dokümantasyonu](https://docs.djangoproject.com/)
- [Website Builder API Dokümantasyonu](#)

## 🔄 Güncelleme Notları

### Versiyon 1.0 (İlk Sürüm)
- Temel website oluşturma
- GrapesJS entegrasyonu
- Veri entegrasyonları
- AI entegrasyonu
- Responsive desteği
- Yayınlama sistemi

## 📞 Destek

Sorularınız için:
- GitHub Issues: [Repository URL]
- Email: support@example.com
- Dokümantasyon: [Documentation URL]




