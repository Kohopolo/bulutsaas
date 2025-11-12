# 🔄 VB Stil Migration Rehberi

## ⚠️ SORUN

Projede şu anda **Tailwind CSS** ve modern web trendleri kullanılıyor:
- ❌ `rounded-lg`, `shadow-lg`, `bg-gradient` gibi modern class'lar
- ❌ Card-based layout
- ❌ Tailwind CDN kullanımı
- ❌ 228 template dosyasında modern trendler

## ✅ ÇÖZÜM STRATEJİSİ

### 1. Tailwind'i VB Tarzına Dönüştürme

Tailwind'i tamamen kaldırmak yerine, **sadece VB tarzı utility'ler** kullanılacak.

### 2. Modern Class'ları VB Class'larına Çevirme

Aşağıdaki mapping tablosunu kullanarak modern class'ları VB class'larına çevirin.

---

## 📋 CLASS MAPPING TABLOSU

### Butonlar

| Modern (YANLIŞ) | VB Tarzı (DOĞRU) |
|----------------|-------------------|
| `px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600` | `vb-button primary` |
| `px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600` | `vb-button success` |
| `px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600` | `vb-button danger` |
| `px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300` | `vb-button` |

### Form Elemanları

| Modern (YANLIŞ) | VB Tarzı (DOĞRU) |
|----------------|-------------------|
| `w-full px-3 py-2 border border-gray-300 rounded-lg` | `vb-textbox` |
| `w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2` | `vb-textbox` |

### Layout

| Modern (YANLIŞ) | VB Tarzı (DOĞRU) |
|----------------|-------------------|
| `bg-white rounded-lg border border-gray-200 p-6 shadow-sm` | `groupbox` |
| `bg-white rounded-lg border border-gray-200 shadow-sm` | `groupbox` |
| `p-6` | `.content-body` içinde otomatik padding |

### Tablolar

| Modern (YANLIŞ) | VB Tarzı (DOĞRU) |
|----------------|-------------------|
| `w-full border-collapse` | `datagrid` |
| `table w-full border-collapse` | `datagrid` |

### Grid Layout

| Modern (YANLIŞ) | VB Tarzı (DOĞRU) |
|----------------|-------------------|
| `grid grid-cols-1 md:grid-cols-2 gap-4` | `form-grid` (form için) |
| `grid grid-cols-1 md:grid-cols-3 gap-4` | Manuel grid (VB tarzı) |

---

## 🚫 YASAK CLASS'LAR

Bu class'lar **ASLA** kullanılmamalı:

- ❌ `rounded-lg`, `rounded-xl`, `rounded-full` → Kullan: `rounded-vb` (3px) veya border-radius yok
- ❌ `shadow-lg`, `shadow-xl`, `shadow-2xl` → Kullan: `shadow-vb-sm` (minimal) veya shadow yok
- ❌ `bg-gradient-to-r`, `bg-gradient-to-b` → Kullan: Düz renk
- ❌ `backdrop-blur`, `bg-opacity` → Glassmorphism yasak!
- ❌ `card`, `card-body`, `card-header` → Kullan: `groupbox`
- ❌ `btn`, `btn-primary` (Bootstrap) → Kullan: `vb-button`

---

## 📝 TEMPLATE GÜNCELLEME ÖRNEKLERİ

### Örnek 1: Form Template

**ÖNCE (Modern):**
```html
<div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
    <form class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Alan Adı
                </label>
                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
            Kaydet
        </button>
    </form>
</div>
```

**SONRA (VB Tarzı):**
```html
<div class="groupbox">
    <div class="groupbox-header">📋 Form Başlığı</div>
    <div class="groupbox-body">
        <form>
            <div class="form-grid">
                <label class="form-label">Alan Adı:</label>
                <input type="text" class="vb-textbox" name="field_name">
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="vb-button primary">💾 Kaydet</button>
                <button type="reset" class="vb-button">❌ İptal</button>
            </div>
        </form>
    </div>
</div>
```

### Örnek 2: Liste Template

**ÖNCE (Modern):**
```html
<div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full border-collapse">
        <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Sütun</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-3">Veri</td>
            </tr>
        </tbody>
    </table>
</div>
```

**SONRA (VB Tarzı):**
```html
<div class="datagrid">
    <div class="vb-datagrid-container">
        <table>
            <thead>
                <tr>
                    <th>Sütun</th>
                </tr>
                <tr class="filter-row">
                    <th><input type="text" class="filter-input" placeholder="Filtrele..."></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Veri</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

---

## 🔧 OTOMATIK DÖNÜŞTÜRME SCRIPTİ

Aşağıdaki script ile template'lerdeki modern class'ları VB class'larına çevirebilirsiniz:

```python
# scripts/convert_to_vb_style.py
import re
import os
from pathlib import Path

# Mapping dictionary
CLASS_MAPPINGS = {
    # Butonlar
    r'class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"': 'class="vb-button primary"',
    r'class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600"': 'class="vb-button success"',
    r'class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"': 'class="vb-button danger"',
    
    # Form elemanları
    r'class="w-full px-3 py-2 border border-gray-300 rounded-lg"': 'class="vb-textbox"',
    
    # Layout
    r'class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm"': 'class="groupbox"',
}

def convert_template(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Modern class'ları VB class'larına çevir
    for old_pattern, new_class in CLASS_MAPPINGS.items():
        content = re.sub(old_pattern, new_class, content)
    
    # rounded-lg'yi kaldır veya rounded-vb'ye çevir
    content = re.sub(r'rounded-lg', 'rounded-vb', content)
    content = re.sub(r'rounded-xl', 'rounded-vb', content)
    
    # shadow-lg'yi kaldır veya shadow-vb-sm'ye çevir
    content = re.sub(r'shadow-lg', 'shadow-vb-sm', content)
    content = re.sub(r'shadow-xl', '', content)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

# Tüm template dosyalarını işle
templates_dir = Path('templates')
for html_file in templates_dir.rglob('*.html'):
    convert_template(html_file)
```

---

## 📋 ADIM ADIM MIGRATION PLANI

### Faz 1: Base Template'i Güncelle ✅

1. ✅ `templates/base.html` - VB layout kullanıyor
2. ⚠️ `templates/tenant/base.html` - Tailwind CDN kullanıyor (GÜNCELLENMELİ)

### Faz 2: CSS Override'ları Ekle

Modern trendleri bastırmak için CSS override'ları ekleyin:

```css
/* static/css/vb-override-modern.css */

/* Tüm rounded-lg'leri 3px yap */
.rounded-lg {
    border-radius: 3px !important;
}

/* Shadow'ları minimal yap */
.shadow-lg, .shadow-xl, .shadow-2xl {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
}

/* Gradient'leri kaldır */
.bg-gradient-to-r, .bg-gradient-to-b {
    background: var(--vb-primary) !important;
}

/* Card'ları groupbox'a çevir */
.card, .card-body {
    border: 1px solid #d4d4d4 !important;
    border-radius: 4px !important;
    background: white !important;
    padding: 20px !important;
    box-shadow: none !important;
}
```

### Faz 3: Template'leri Kademeli Güncelle

Öncelik sırası:
1. **Yeni sayfalar** → Direkt VB tarzında yaz
2. **Sık kullanılan sayfalar** → Önce bunları güncelle
3. **Diğer sayfalar** → Kademeli olarak güncelle

---

## 🎯 HEMEN YAPILACAKLAR

### 1. tenant/base.html'i Güncelle

Tailwind CDN'i kaldır, VB layout CSS'i kullan:

```html
<!-- ÖNCE -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- SONRA -->
<link rel="stylesheet" href="{% static 'css/vb-layout.css' %}">
```

### 2. CSS Override Dosyası Ekle

`static/css/vb-override-modern.css` dosyası oluştur ve modern trendleri bastır.

### 3. CSS_STANDARTLARI.md'yi Güncelle

Tailwind kullanımını kaldır, VB tarzı standartlar ekle.

---

## 📚 REFERANS DOSYALAR

- ✅ `DESIGN_STANDARD.md` - VB tarzı standartlar
- ✅ `demo_layout.html` - Çalışır VB tarzı örnek
- ✅ `static/css/vb-layout.css` - VB layout CSS
- ✅ `templates/base.html` - VB tarzı base template

---

## ⚠️ ÖNEMLİ NOTLAR

1. **Tailwind'i tamamen kaldırmayın** - Sadece VB tarzı utility'ler kullanın
2. **Kademeli migration** - Tüm dosyaları bir anda değiştirmeyin
3. **Test edin** - Her değişiklikten sonra sayfayı kontrol edin
4. **Yeni sayfalar** - Yeni sayfalar direkt VB tarzında yazılmalı

---

**🎯 HEDEF:** Tüm proje Visual Basic masaüstü uygulama görünümünde olmalı!

