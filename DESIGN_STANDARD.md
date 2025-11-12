# 🎨 SaaS 2026 - Tasarım Standardı (KALICI)

> **⚠️ ÖNEMLİ: Bu dosya projenin tasarım DNA'sıdır. Her yeni özellik bu standartlara uygun geliştirilmelidir!**

## 📋 Genel Prensip

Bu proje **Visual Basic Desktop Application** tarzında **tam ekran, panel-bazlı** bir layout kullanır.
PHP/Laravel admin panelleri gibi modern card-based değil, **masaüstü uygulama görünümündedir**.

---

## 🎯 Layout Yapısı (DEĞİŞMEZ!)

### Ana Bileşenler:

```
┌─────────────────────────────────────────────────┐
│ TITLE BAR (Mavi başlık - Always visible)       │ ← 56px yükseklik
├─────────────────────────────────────────────────┤
│ TOOLBAR (Butonlar - Always visible)            │ ← 48px yükseklik
├──────────┬──────────────────────────────────────┤
│          │                                      │
│ SIDEBAR  │  CONTENT AREA                       │
│ (Menu)   │  (Dinamik içerik)                   │
│ 260px    │  (Flex: 1)                          │
│          │                                      │
│ Fixed    │  Scrollable                         │
│          │                                      │
├──────────┴──────────────────────────────────────┤
│ STATUS BAR (Alt durum çubuğu)                  │ ← 36px yükseklik
└─────────────────────────────────────────────────┘
```

### CSS Class Yapısı:

| Class | Açıklama | Değiştirilebilir mi? |
|-------|----------|---------------------|
| `.desktop-app` | Ana konteyner | ❌ HAYIR |
| `.titlebar` | Üst başlık | ❌ HAYIR |
| `.toolbar` | Toolbar | ❌ HAYIR |
| `.sidebar` | Sol menü (260px) | ⚠️ Sadece genişlik |
| `.content-area` | Ana içerik | ✅ İçerik değişir |
| `.statusbar` | Alt çubuk | ❌ HAYIR |

---

## 🎨 Renk Paleti (Standart)

### Temel Renkler:

```css
/* Title Bar */
--titlebar-bg: linear-gradient(to bottom, #0078d4, #0063b1);
--titlebar-border: #005a9e;

/* Toolbar */
--toolbar-bg: #f3f3f3;
--toolbar-border: #d4d4d4;

/* Sidebar */
--sidebar-bg: #2d2d30;
--sidebar-header-bg: #252526;
--sidebar-text: #cccccc;
--sidebar-active: #094771;
--sidebar-hover: #37373d;

/* Content */
--content-bg: #f5f5f5;
--content-card-bg: #ffffff;

/* Status Bar */
--statusbar-bg: #007acc;
--statusbar-border: #005a9e;

/* Buttons (VB Style) */
--button-default: #e1e1e1;
--button-border: #adadad;
--button-hover: #bee6fd;
--button-primary: #0078d4;
--button-success: #107c10;
--button-danger: #d13438;

/* DataGrid */
--grid-header: #f0f0f0;
--grid-border: #d4d4d4;
--grid-row-hover: #f5f5f5;
--grid-row-selected: #cce8ff;
```

### Renk Kullanım Kuralları:

- ❌ Gradient butonlar kullanma (VB düz renk kullanır)
- ❌ Shadow efektleri minimal olmalı
- ✅ Keskin köşeler tercih et (border-radius: 3-4px max)
- ✅ Border'lar görünür olmalı (VB tarzı)

---

## 🧩 Standart Komponentler

### 1. GroupBox (VB Tarzı Panel)

```html
<div class="groupbox">
    <div class="groupbox-header">
        📋 Başlık
    </div>
    <div class="groupbox-body">
        <!-- İçerik -->
    </div>
</div>
```

**CSS Kuralları:**
- Background: white
- Border: 1px solid #d4d4d4
- Header background: #f7f7f7
- Border-radius: 4px (max)

### 2. DataGridView (Tablo)

```html
<table class="datagrid">
    <thead>
        <tr>
            <th>Sütun 1</th>
            <th>Sütun 2</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Veri 1</td>
            <td>Veri 2</td>
        </tr>
    </tbody>
</table>
```

**CSS Kuralları:**
- Border-collapse: collapse
- Her hücre border'lı olmalı
- Hover efekti: #f5f5f5
- Seçili satır: #cce8ff

### 3. VB Button

```html
<button class="vb-button">Normal</button>
<button class="vb-button primary">Primary</button>
<button class="vb-button success">Success</button>
<button class="vb-button danger">Danger</button>
```

**CSS Kuralları:**
- Padding: 8px 20px
- Border: 1px solid (görünür)
- Border-radius: 3px
- Hover: border rengi değişir (#0078d4)

### 4. VB TextBox

```html
<input type="text" class="vb-textbox">
<textarea class="vb-textbox" rows="3"></textarea>
<select class="vb-textbox">
    <option>Seçenek</option>
</select>
```

**CSS Kuralları:**
- Border: 1px solid #adadad
- Border-radius: 2px (minimal)
- Focus: border #0078d4 + shadow

### 5. Form Grid

```html
<div class="form-grid">
    <label class="form-label">Alan Adı:</label>
    <input type="text" class="vb-textbox">
</div>
```

**CSS Kuralları:**
- Grid: 150px (label) + 1fr (input)
- Gap: 15px
- Label: font-weight 600

---

## 📐 Layout Kuralları

### Sidebar Menü:

```css
/* Menü Yapısı */
.menu-group-title  /* Grup başlığı (uppercase, 11px) */
.menu-item         /* Menü öğesi */
.menu-item.active  /* Aktif menü (mavi background) */
.menu-icon         /* İkon alanı (20x20px) */
```

**Kurallar:**
- Menü genişliği: 260px (sabit)
- Aktif menü: sol border (3px) mavi
- Hover efekti: background #37373d
- İkonlar: emoji veya font-awesome

### Content Area:

```css
/* İçerik Yapısı */
.content-header    /* Başlık alanı (beyaz bg) */
.content-body      /* Ana içerik (scrollable) */
```

**Kurallar:**
- Header: beyaz, padding 20px 25px
- Body: #f5f5f5, padding 20px
- Overflow-y: auto (scroll)

### Stats Cards:

```html
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-content">
            <div class="stat-label">Başlık</div>
            <div class="stat-value">123</div>
        </div>
    </div>
</div>
```

**Kurallar:**
- Grid: repeat(auto-fit, minmax(250px, 1fr))
- Icon: 50x50px, border-radius 4px
- Renkler: blue, green, orange, red

---

## 🚫 YAPILMAMASI GEREKENLER

### ❌ Modern Web Trendleri (Bu projede KULLANILMAZ):

1. **Glassmorphism** (Cam efekti)
2. **Neumorphism** (3D yumuşak gölgeler)
3. **Gradient Buttons** (Degradeli butonlar)
4. **Card-based Layout** (Bootstrap card'lar)
5. **Floating Action Buttons** (FAB butonlar)
6. **Parallax Effects** (Parallax kaydırma)
7. **Smooth Animations** (Yavaş animasyonlar - VB hızlıdır)

### ❌ CSS Framework Komponentleri:

- Bootstrap Card → Kullan: `.groupbox`
- Bootstrap Button → Kullan: `.vb-button`
- Bootstrap Form → Kullan: `.form-grid` + `.vb-textbox`
- Bootstrap Table → Kullan: `.datagrid`

---

## ✅ YAPILMASI GEREKENLER

### Django Template Yapısı:

```
templates/
├── base.html                    ← Ana layout (VB tarzı)
├── includes/
│   ├── titlebar.html           ← Başlık çubuğu
│   ├── toolbar.html            ← Toolbar
│   ├── sidebar.html            ← Sol menü
│   └── statusbar.html          ← Alt çubuk
├── dashboard/
│   └── index.html              ← Dashboard sayfası
├── packages/
│   ├── list.html               ← Paket listesi
│   └── create.html             ← Paket oluştur
└── modules/
    └── list.html               ← Modül listesi
```

### Static Dosya Yapısı:

```
static/
├── css/
│   ├── vb-layout.css           ← Ana layout CSS (DEĞİŞMEZ!)
│   ├── vb-components.css       ← Komponent CSS
│   └── custom.css              ← Özel eklemeler (opsiyonel)
├── js/
│   ├── vb-layout.js            ← Layout fonksiyonları
│   └── app.js                  ← Uygulama JS
└── images/
    └── logo.png
```

---

## 📱 Responsive Kuralları

### Tablet (768px - 1024px):

- Sidebar: 220px (daralt)
- Font-size: %90'a düş

### Mobile (< 768px):

- Sidebar: -260px (gizle, toggle button ile aç)
- Stats: tek sütun
- Form-grid: tek sütun
- DataGrid: horizontal scroll

**Ama öncelik: Desktop! (1920x1080+)**

---

## 🎯 Yeni Sayfa Oluştururken Checklist

- [ ] `{% extends 'base.html' %}` kullan
- [ ] `.content-header` ekle (başlık için)
- [ ] `.content-body` içine içeriği yaz
- [ ] Tablo kullanacaksan → `.datagrid`
- [ ] Panel kullanacaksan → `.groupbox`
- [ ] Form kullanacaksan → `.form-grid` + `.vb-textbox`
- [ ] Buton kullanacaksan → `.vb-button`
- [ ] İstatistik göstereceksen → `.stat-card`

---

## 🔄 Chat Yenilendiğinde Hatırlatma

**Eğer bu proje yeni bir chat oturumunda açılırsa:**

1. ✅ **İlk önce bu dosyayı oku:** `DESIGN_STANDARD.md`
2. ✅ **Demo layout'u kontrol et:** `demo_layout.html`
3. ✅ **Base template'i incele:** `templates/base.html`
4. ✅ **CSS dosyasına bak:** `static/css/vb-layout.css`

**Asla unutma:**
- Bu proje VB tarzı masaüstü görünümlü
- Modern web trendleri kullanılmaz
- Layout yapısı sabittir (titlebar, toolbar, sidebar, content, statusbar)
- Renk paleti değişmez (mavi + gri + koyu tema)

---

## 📞 Tasarım Sorularında

**"Bunu nasıl yapmalıyım?" → Cevap:**

1. `demo_layout.html` dosyasında örneği var mı? → Kopyala
2. Bu dosyada tanımlı bir component var mı? → Kullan
3. Yoksa → VB tarzında, minimalist, border'lı, düz renkli yap

**"Bu özelliği eklemeli miyim?" → Cevap:**

- Modern ve fancy mi? → ❌ Ekleme
- VB'de var mı? → ✅ Ekle
- Masaüstü uygulamalarda kullanılır mı? → ✅ Ekle

---

## 🎨 Örnek Sayfa Şablonu

```html
{% extends 'base.html' %}

{% block title %}Sayfa Başlığı{% endblock %}

{% block content %}
<div class="content-header">
    <div class="content-title">Ana Başlık</div>
    <div class="content-subtitle">Alt başlık açıklama</div>
</div>

<div class="content-body">
    
    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📊</div>
            <div class="stat-content">
                <div class="stat-label">Toplam</div>
                <div class="stat-value">123</div>
            </div>
        </div>
    </div>

    <!-- Liste -->
    <div class="groupbox">
        <div class="groupbox-header">📋 Liste Başlığı</div>
        <div class="groupbox-body">
            <table class="datagrid">
                <thead>
                    <tr>
                        <th>Sütun 1</th>
                        <th>Sütun 2</th>
                    </tr>
                </thead>
                <tbody>
                    {% for item in items %}
                    <tr>
                        <td>{{ item.name }}</td>
                        <td>{{ item.value }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            <div style="margin-top: 15px;">
                <button class="vb-button primary">➕ Yeni Ekle</button>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="groupbox">
        <div class="groupbox-header">➕ Yeni Oluştur</div>
        <div class="groupbox-body">
            <form method="post">
                {% csrf_token %}
                <div class="form-grid">
                    <label class="form-label">Alan 1:</label>
                    <input type="text" class="vb-textbox" name="field1">
                    
                    <label class="form-label">Alan 2:</label>
                    <input type="text" class="vb-textbox" name="field2">
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="vb-button success">💾 Kaydet</button>
                    <button type="reset" class="vb-button">❌ İptal</button>
                </div>
            </form>
        </div>
    </div>

</div>
{% endblock %}
```

---

## 📚 Referanslar

- ✅ **Ana Demo:** `demo_layout.html` (Her şey burada!)
- ✅ **CSS Dosyası:** `static/css/vb-layout.css`
- ✅ **Base Template:** `templates/base.html`
- ✅ **Bu Dosya:** `DESIGN_STANDARD.md` (Standartlar)

---

**🎯 SON SÖZ:**

> Bu proje Visual Basic masaüstü uygulaması gibi görünmelidir.
> Modern web tasarım trendleri yerine, klasik, kullanışlı, profesyonel bir görünüm hedeflenir.
> **Bu standartlar değişmez. Tüm geliştirmeler bu kurallara uygun yapılmalıdır.**

**📅 Oluşturulma:** 2025-11-09  
**🔄 Son Güncelleme:** 2025-11-09  
**✍️ Oluşturan:** AI Assistant (Claude)  
**🔒 Durum:** KALICI - DEĞİŞTİRİLEMEZ (İçerik eklenebilir)



