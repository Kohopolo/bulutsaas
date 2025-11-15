# React Builder Entegrasyon Planı

## Mevcut Durum
- GrapesJS kullanılıyor ama sorunlar var
- Django backend mevcut
- API endpoint'leri hazır

## React Builder Seçenekleri

### Seçenek 1: React DnD Kit + Custom Builder (Önerilen)
**Avantajlar:**
- Tam kontrol
- Özelleştirilebilir
- Modern React hooks
- Ücretsiz ve açık kaynak

**Teknik Stack:**
- React 18+
- React DnD Kit (drag & drop)
- Tailwind CSS veya Material-UI
- Django REST Framework (API)

**Yapı:**
```
frontend/
├── src/
│   ├── components/
│   │   ├── Builder/
│   │   │   ├── Canvas.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Toolbar.tsx
│   │   │   └── BlockLibrary.tsx
│   │   └── Blocks/
│   │       ├── TextBlock.tsx
│   │       ├── ImageBlock.tsx
│   │       ├── SectionBlock.tsx
│   │       └── ...
│   ├── hooks/
│   │   ├── useBuilder.ts
│   │   └── useDragDrop.ts
│   ├── services/
│   │   └── api.ts
│   └── App.tsx
├── package.json
└── webpack.config.js
```

### Seçenek 2: Builder.io SDK
**Avantajlar:**
- Hazır çözüm
- Çok profesyonel
- Hızlı implementasyon

**Dezavantajlar:**
- Ücretli (ücretsiz plan sınırlı)
- Daha az özelleştirme

### Seçenek 3: Unlayer React SDK
**Avantajlar:**
- Email ve page builder
- SaaS için ideal
- Beyaz etiket

**Dezavantajlar:**
- Ücretli
- Email odaklı

## Önerilen Yaklaşım: React DnD Kit

### Faz 1: React Setup (1 gün)
1. React projesi oluştur (Create React App veya Vite)
2. Django REST API entegrasyonu
3. Temel component yapısı

### Faz 2: Builder Core (2-3 gün)
1. Canvas component
2. Drag & Drop implementasyonu
3. Block library
4. Sidebar ve toolbar

### Faz 3: Blocks (2 gün)
1. Temel blocks (Text, Image, Section, etc.)
2. Data integration blocks
3. Template blocks

### Faz 4: Features (2 gün)
1. Style editor
2. Responsive preview
3. Save/Load functionality
4. Export HTML/CSS

### Faz 5: Polish (1 gün)
1. UI/UX iyileştirmeleri
2. Animasyonlar
3. Error handling
4. Documentation

## Django Entegrasyonu

### API Endpoints (Mevcut)
- `/api/components/` - Block listesi
- `/api/pages/<id>/load/` - Sayfa yükleme
- `/api/pages/<id>/save/` - Sayfa kaydetme

### Yeni Endpoints (Gerekirse)
- `/api/blocks/` - Block CRUD
- `/api/templates/` - Template listesi
- `/api/preview/` - Preview endpoint

## Deployment

### Option 1: Django Template'de React
- React build'i static files'a ekle
- Django template'de React app'i yükle
- Basit ama sınırlı

### Option 2: Separate Frontend
- React app ayrı port'ta çalışır
- Django REST API kullanır
- Daha esnek ama karmaşık

## Karar

**Şimdilik:** GrapesJS'i düzelt (builder_new.html)
**Gelecekte:** React builder'a geçiş (1-2 hafta sürebilir)

## React Builder Başlangıç Komutları

```bash
# React projesi oluştur
npx create-react-app website-builder-frontend
cd website-builder-frontend

# Gerekli paketler
npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
npm install axios
npm install tailwindcss

# Django REST Framework için
pip install djangorestframework
```

## Sonuç

React builder daha profesyonel ama zaman alıcı. Şimdilik GrapesJS'i düzeltelim, sonra React'e geçiş yapabiliriz.




## Mevcut Durum
- GrapesJS kullanılıyor ama sorunlar var
- Django backend mevcut
- API endpoint'leri hazır

## React Builder Seçenekleri

### Seçenek 1: React DnD Kit + Custom Builder (Önerilen)
**Avantajlar:**
- Tam kontrol
- Özelleştirilebilir
- Modern React hooks
- Ücretsiz ve açık kaynak

**Teknik Stack:**
- React 18+
- React DnD Kit (drag & drop)
- Tailwind CSS veya Material-UI
- Django REST Framework (API)

**Yapı:**
```
frontend/
├── src/
│   ├── components/
│   │   ├── Builder/
│   │   │   ├── Canvas.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Toolbar.tsx
│   │   │   └── BlockLibrary.tsx
│   │   └── Blocks/
│   │       ├── TextBlock.tsx
│   │       ├── ImageBlock.tsx
│   │       ├── SectionBlock.tsx
│   │       └── ...
│   ├── hooks/
│   │   ├── useBuilder.ts
│   │   └── useDragDrop.ts
│   ├── services/
│   │   └── api.ts
│   └── App.tsx
├── package.json
└── webpack.config.js
```

### Seçenek 2: Builder.io SDK
**Avantajlar:**
- Hazır çözüm
- Çok profesyonel
- Hızlı implementasyon

**Dezavantajlar:**
- Ücretli (ücretsiz plan sınırlı)
- Daha az özelleştirme

### Seçenek 3: Unlayer React SDK
**Avantajlar:**
- Email ve page builder
- SaaS için ideal
- Beyaz etiket

**Dezavantajlar:**
- Ücretli
- Email odaklı

## Önerilen Yaklaşım: React DnD Kit

### Faz 1: React Setup (1 gün)
1. React projesi oluştur (Create React App veya Vite)
2. Django REST API entegrasyonu
3. Temel component yapısı

### Faz 2: Builder Core (2-3 gün)
1. Canvas component
2. Drag & Drop implementasyonu
3. Block library
4. Sidebar ve toolbar

### Faz 3: Blocks (2 gün)
1. Temel blocks (Text, Image, Section, etc.)
2. Data integration blocks
3. Template blocks

### Faz 4: Features (2 gün)
1. Style editor
2. Responsive preview
3. Save/Load functionality
4. Export HTML/CSS

### Faz 5: Polish (1 gün)
1. UI/UX iyileştirmeleri
2. Animasyonlar
3. Error handling
4. Documentation

## Django Entegrasyonu

### API Endpoints (Mevcut)
- `/api/components/` - Block listesi
- `/api/pages/<id>/load/` - Sayfa yükleme
- `/api/pages/<id>/save/` - Sayfa kaydetme

### Yeni Endpoints (Gerekirse)
- `/api/blocks/` - Block CRUD
- `/api/templates/` - Template listesi
- `/api/preview/` - Preview endpoint

## Deployment

### Option 1: Django Template'de React
- React build'i static files'a ekle
- Django template'de React app'i yükle
- Basit ama sınırlı

### Option 2: Separate Frontend
- React app ayrı port'ta çalışır
- Django REST API kullanır
- Daha esnek ama karmaşık

## Karar

**Şimdilik:** GrapesJS'i düzelt (builder_new.html)
**Gelecekte:** React builder'a geçiş (1-2 hafta sürebilir)

## React Builder Başlangıç Komutları

```bash
# React projesi oluştur
npx create-react-app website-builder-frontend
cd website-builder-frontend

# Gerekli paketler
npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
npm install axios
npm install tailwindcss

# Django REST Framework için
pip install djangorestframework
```

## Sonuç

React builder daha profesyonel ama zaman alıcı. Şimdilik GrapesJS'i düzeltelim, sonra React'e geçiş yapabiliriz.




