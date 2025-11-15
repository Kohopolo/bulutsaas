# Website Builder Modern Upgrade Plan

## Mevcut Durum Analizi
- GrapesJS kullanılıyor ama düzgün çalışmıyor
- Block'lar görünmüyor
- Canvas boş kalıyor
- Responsive preview modal açık kalıyor

## Çözüm Seçenekleri

### Seçenek 1: GrapesJS'i Düzelt ve Modernleştir (Hızlı - 2-3 saat)
**Avantajlar:**
- Mevcut kod tabanını korur
- Hızlı implementasyon
- Django ile uyumlu

**Yapılacaklar:**
1. GrapesJS'i düzgün yapılandır
2. Block'ları doğru şekilde yükle
3. Modern UI/UX ekle
4. Bootstrap 5 entegrasyonu
5. Daha iyi block kütüphanesi

### Seçenek 2: React Builder Entegrasyonu (Profesyonel - 1-2 gün)
**Avantajlar:**
- Çok daha modern ve kullanıcı dostu
- Daha iyi performans
- Daha fazla özellik

**Seçenekler:**
1. **React DnD Kit + Custom Builder** (Önerilen)
   - Tam kontrol
   - Özelleştirilebilir
   - Modern React hooks

2. **Builder.io SDK**
   - Hazır çözüm
   - Ücretli (ücretsiz plan var)
   - Çok profesyonel

3. **Unlayer React SDK**
   - Email ve page builder
   - Ücretli
   - SaaS için ideal

## Önerilen Yaklaşım

**Faz 1: GrapesJS'i Düzelt (Şimdi)**
- Block'ları düzgün yükle
- Canvas'ı çalışır hale getir
- Modern UI ekle

**Faz 2: React Builder'a Geçiş (Gelecek)**
- React DnD Kit ile custom builder
- Django REST API entegrasyonu
- Modern component library

## Teknik Detaylar

### GrapesJS Düzeltmeleri
```javascript
// Doğru yapılandırma
editor = grapesjs.init({
    container: '#gjs',
    height: '100vh',
    storageManager: false,
    plugins: ['gjs-preset-webpage'],
    pluginsOpts: {
        'gjs-preset-webpage': {
            blocksBasicOpts: {
                blocks: ['column1', 'column2', 'column3', 'text', 'link', 'image', 'video'],
                flexGrid: 1,
            }
        }
    },
    canvas: {
        styles: [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
        ]
    },
    blockManager: {
        appendTo: '.blocks-container'
    }
});
```

### React Builder Yapısı
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
│   │       └── ...
│   ├── hooks/
│   │   ├── useBuilder.ts
│   │   └── useDragDrop.ts
│   └── services/
│       └── api.ts
```

## Karar

**Şimdilik:** GrapesJS'i düzelt ve modernleştir
**Gelecekte:** React builder'a geçiş yap




## Mevcut Durum Analizi
- GrapesJS kullanılıyor ama düzgün çalışmıyor
- Block'lar görünmüyor
- Canvas boş kalıyor
- Responsive preview modal açık kalıyor

## Çözüm Seçenekleri

### Seçenek 1: GrapesJS'i Düzelt ve Modernleştir (Hızlı - 2-3 saat)
**Avantajlar:**
- Mevcut kod tabanını korur
- Hızlı implementasyon
- Django ile uyumlu

**Yapılacaklar:**
1. GrapesJS'i düzgün yapılandır
2. Block'ları doğru şekilde yükle
3. Modern UI/UX ekle
4. Bootstrap 5 entegrasyonu
5. Daha iyi block kütüphanesi

### Seçenek 2: React Builder Entegrasyonu (Profesyonel - 1-2 gün)
**Avantajlar:**
- Çok daha modern ve kullanıcı dostu
- Daha iyi performans
- Daha fazla özellik

**Seçenekler:**
1. **React DnD Kit + Custom Builder** (Önerilen)
   - Tam kontrol
   - Özelleştirilebilir
   - Modern React hooks

2. **Builder.io SDK**
   - Hazır çözüm
   - Ücretli (ücretsiz plan var)
   - Çok profesyonel

3. **Unlayer React SDK**
   - Email ve page builder
   - Ücretli
   - SaaS için ideal

## Önerilen Yaklaşım

**Faz 1: GrapesJS'i Düzelt (Şimdi)**
- Block'ları düzgün yükle
- Canvas'ı çalışır hale getir
- Modern UI ekle

**Faz 2: React Builder'a Geçiş (Gelecek)**
- React DnD Kit ile custom builder
- Django REST API entegrasyonu
- Modern component library

## Teknik Detaylar

### GrapesJS Düzeltmeleri
```javascript
// Doğru yapılandırma
editor = grapesjs.init({
    container: '#gjs',
    height: '100vh',
    storageManager: false,
    plugins: ['gjs-preset-webpage'],
    pluginsOpts: {
        'gjs-preset-webpage': {
            blocksBasicOpts: {
                blocks: ['column1', 'column2', 'column3', 'text', 'link', 'image', 'video'],
                flexGrid: 1,
            }
        }
    },
    canvas: {
        styles: [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
        ]
    },
    blockManager: {
        appendTo: '.blocks-container'
    }
});
```

### React Builder Yapısı
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
│   │       └── ...
│   ├── hooks/
│   │   ├── useBuilder.ts
│   │   └── useDragDrop.ts
│   └── services/
│       └── api.ts
```

## Karar

**Şimdilik:** GrapesJS'i düzelt ve modernleştir
**Gelecekte:** React builder'a geçiş yap




