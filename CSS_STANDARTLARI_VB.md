# 🎨 CSS Standartları - VB Tarzı (GÜNCEL)

**⚠️ ÖNEMLİ:** Bu dosya **Visual Basic masaüstü uygulama** tarzı standartları içerir.

**Tarih:** 2025-11-12  
**Versiyon:** 2.0.0 (VB Tarzı)

---

## 📋 Genel Bakış

Bu dokümanda **VB tarzı masaüstü uygulama** görünümü için CSS standartları tanımlanmıştır.

**❌ YASAK:** Modern web trendleri (card, gradient, glassmorphism, rounded-lg, shadow-lg)

**✅ İZİN:** VB tarzı komponentler (groupbox, datagrid, vb-button, vb-textbox)

---

## ✅ VB TARZI STANDART CSS YAPISI

### 1. Form Template Yapısı

```html
{% extends "tenant/base.html" %}
{% load static %}

{% block title %}{{ title }} - Kiracı Üye Paneli{% endblock %}

{% block content %}
<div class="content-body">
    <!-- Geri Dön Butonu -->
    <div style="margin-bottom: 15px;">
        <a href="{% url 'app:list' %}" class="vb-button">
            <i class="fas fa-arrow-left"></i> Listeye Dön
        </a>
    </div>
    
    <!-- Form Container (GroupBox) -->
    <div class="groupbox">
        <div class="groupbox-header">📋 {{ title }}</div>
        <div class="groupbox-body">
            <!-- Form Hataları -->
            {% if form.errors %}
            <div style="background: #ffe6e6; border: 1px solid #d13438; padding: 12px; margin-bottom: 15px; border-radius: 3px;">
                <p style="color: #d13438; font-weight: 600; margin-bottom: 8px;">Lütfen hataları düzeltin:</p>
                <ul style="color: #a52a2d; font-size: 12px; margin-left: 20px;">
                    {% for field, errors in form.errors.items %}
                        {% for error in errors %}
                        <li>{{ field }}: {{ error }}</li>
                        {% endfor %}
                    {% endfor %}
                </ul>
            </div>
            {% endif %}
            
            <!-- Form -->
            <form method="post">
                {% csrf_token %}
                
                <!-- Form Grid (VB tarzı) -->
                <div class="form-grid">
                    <label class="form-label">Alan Adı:</label>
                    <input type="text" class="vb-textbox" name="field_name" required>
                    
                    <label class="form-label">Açıklama:</label>
                    <textarea class="vb-textbox" name="description" rows="3"></textarea>
                </div>
                
                <!-- Butonlar -->
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="vb-button primary">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                    <a href="{% url 'app:list' %}" class="vb-button">
                        <i class="fas fa-times"></i> İptal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
{% endblock %}
```

### 2. Liste Template Yapısı

```html
{% extends "tenant/base.html" %}
{% load static %}

{% block title %}Liste - Kiracı Üye Paneli{% endblock %}

{% block content %}
<div class="content-body">
    <!-- Başlık ve Butonlar -->
    <div class="content-header">
        <div class="content-title">📋 Liste Başlığı</div>
        <div style="margin-top: 15px;">
            <a href="{% url 'app:create' %}" class="vb-button primary">
                <i class="fas fa-plus"></i> Yeni Ekle
            </a>
        </div>
    </div>
    
    <!-- Tablo (DataGrid) -->
    <div class="datagrid">
        <div class="vb-datagrid-container">
            <table>
                <thead>
                    <tr>
                        <th>Sütun 1</th>
                        <th>Sütun 2</th>
                        <th style="text-align: center;">İşlemler</th>
                    </tr>
                    <tr class="filter-row">
                        <th><input type="text" class="filter-input" placeholder="Filtrele..."></th>
                        <th><input type="text" class="filter-input" placeholder="Filtrele..."></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {% for item in items %}
                    <tr>
                        <td>{{ item.field1 }}</td>
                        <td>{{ item.field2 }}</td>
                        <td style="text-align: center;">
                            <a href="{% url 'app:detail' item.pk %}" class="vb-button" style="padding: 4px 10px; font-size: 12px;">
                                <i class="fas fa-eye"></i> Detay
                            </a>
                            <a href="{% url 'app:update' item.pk %}" class="vb-button" style="padding: 4px 10px; font-size: 12px;">
                                <i class="fas fa-edit"></i> Düzenle
                            </a>
                        </td>
                    </tr>
                    {% empty %}
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 40px; color: #999;">
                            Veri bulunamadı.
                        </td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
```

---

## 🎨 VB TARZI KOMPONENTLER

### Butonlar

```html
<!-- Normal Buton -->
<button class="vb-button">Normal</button>

<!-- Primary Buton -->
<button class="vb-button primary">Primary</button>

<!-- Success Buton -->
<button class="vb-button success">Başarılı</button>

<!-- Danger Buton -->
<button class="vb-button danger">Sil</button>
```

**CSS:** `static/css/vb-layout.css` içinde tanımlı

### Form Elemanları

```html
<!-- Text Input -->
<input type="text" class="vb-textbox" name="field_name">

<!-- Select -->
<select class="vb-textbox" name="field_name">
    <option>Seçenek 1</option>
</select>

<!-- Textarea -->
<textarea class="vb-textbox" rows="3" name="field_name"></textarea>
```

### GroupBox (Panel)

```html
<div class="groupbox">
    <div class="groupbox-header">📋 Başlık</div>
    <div class="groupbox-body">
        <!-- İçerik -->
    </div>
</div>
```

### DataGrid (Tablo)

```html
<div class="datagrid">
    <div class="vb-datagrid-container">
        <table>
            <thead>
                <tr>
                    <th>Sütun 1</th>
                    <th>Sütun 2</th>
                </tr>
                <tr class="filter-row">
                    <th><input type="text" class="filter-input" placeholder="Filtrele..."></th>
                    <th><input type="text" class="filter-input" placeholder="Filtrele..."></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Veri 1</td>
                    <td>Veri 2</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

### Form Grid

```html
<div class="form-grid">
    <label class="form-label">Alan Adı:</label>
    <input type="text" class="vb-textbox" name="field_name">
    
    <label class="form-label">Açıklama:</label>
    <textarea class="vb-textbox" name="description"></textarea>
</div>
```

---

## 🚫 YAPILMAMASI GEREKENLER

### ❌ Modern Web Trendleri

1. **Card-based Layout**
   ```html
   <!-- YANLIŞ -->
   <div class="card">
       <div class="card-body">...</div>
   </div>
   
   <!-- DOĞRU -->
   <div class="groupbox">
       <div class="groupbox-body">...</div>
   </div>
   ```

2. **Gradient Buttons**
   ```html
   <!-- YANLIŞ -->
   <button class="bg-gradient-to-r from-blue-500 to-blue-600">...</button>
   
   <!-- DOĞRU -->
   <button class="vb-button primary">...</button>
   ```

3. **Rounded Corners (Büyük)**
   ```html
   <!-- YANLIŞ -->
   <div class="rounded-lg">...</div>
   <div class="rounded-xl">...</div>
   
   <!-- DOĞRU -->
   <div style="border-radius: 3px;">...</div>
   <!-- veya border-radius yok -->
   ```

4. **Büyük Shadows**
   ```html
   <!-- YANLIŞ -->
   <div class="shadow-lg">...</div>
   <div class="shadow-xl">...</div>
   
   <!-- DOĞRU -->
   <div style="box-shadow: 0 1px 2px rgba(0,0,0,0.08);">...</div>
   <!-- veya shadow yok -->
   ```

5. **Glassmorphism**
   ```html
   <!-- YANLIŞ -->
   <div class="backdrop-blur bg-opacity-50">...</div>
   
   <!-- DOĞRU -->
   <div style="background: white;">...</div>
   ```

---

## ✅ YAPILMASI GEREKENLER

### 1. VB Layout Kullan

```html
{% extends "tenant/base.html" %}
<!-- veya -->
{% extends "base.html" %}
```

### 2. VB Komponentler Kullan

- `.groupbox` - Panel için
- `.datagrid` - Tablo için
- `.vb-button` - Buton için
- `.vb-textbox` - Input için
- `.form-grid` - Form layout için

### 3. Renk Paleti

- **Primary:** `#0078d4` (Mavi)
- **Success:** `#107c10` (Yeşil)
- **Danger:** `#d13438` (Kırmızı)
- **Background:** `#f5f5f5` (Açık gri)
- **Border:** `#d4d4d4` (Gri)

### 4. Border Radius

- **Maksimum:** 3-4px
- **Tercih:** 2px veya yok

### 5. Shadows

- **Maksimum:** `0 1px 2px rgba(0,0,0,0.08)`
- **Tercih:** Shadow yok

---

## 📚 REFERANS DOSYALAR

- ✅ `DESIGN_STANDARD.md` - VB tarzı standartlar (MUTLAKA OKU!)
- ✅ `demo_layout.html` - Çalışır VB tarzı örnek
- ✅ `static/css/vb-layout.css` - VB layout CSS
- ✅ `templates/base.html` - VB tarzı base template
- ✅ `VB_STIL_MIGRATION_REHBERI.md` - Migration rehberi

---

## 🎯 YENİ SAYFA OLUŞTURURKEN CHECKLIST

- [ ] `{% extends 'tenant/base.html' %}` veya `{% extends 'base.html' %}` kullan
- [ ] `.content-header` ve `.content-body` kullan
- [ ] `.groupbox` kullan (card değil!)
- [ ] `.datagrid` kullan (modern table değil!)
- [ ] `.vb-button` kullan (modern button değil!)
- [ ] `.vb-textbox` kullan (modern input değil!)
- [ ] `rounded-lg`, `shadow-lg` kullanma!
- [ ] Gradient kullanma!
- [ ] Glassmorphism kullanma!

---

**🎯 HEDEF:** Tüm proje Visual Basic masaüstü uygulama görünümünde olmalı!





