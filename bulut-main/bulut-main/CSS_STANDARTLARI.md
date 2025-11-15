# 🎨 CSS Standartları - Tenant Panel

**⚠️ DEPRECATED - Bu dosya eski Tailwind standartlarını içerir!**

**YENİ STANDART:** `CSS_STANDARTLARI_VB.md` dosyasını kullanın!

**Tarih:** 2025-01-XX  
**Versiyon:** 1.0.0 (ESKİ - Tailwind kullanıyor)

---

## 📋 Genel Bakış

**⚠️ UYARI:** Bu dosya **Tailwind CSS** standartlarını içerir ve **DEPRECATED** durumdadır.

**Yeni projeler için:** `CSS_STANDARTLARI_VB.md` dosyasını kullanın (VB tarzı masaüstü uygulama görünümü).

Bu dokümanda tenant panelindeki tüm modüller için uygulanması gereken CSS standartları tanımlanmıştır. Bu standartlar **Tur Yönetimi** modülünde kullanılan yapıya göre belirlenmiştir.

---

## ✅ Standart CSS Yapısı

### 1. Form Template Yapısı

#### Temel Yapı
```html
{% extends "tenant/base.html" %}
{% load static %}

{% block title %}{{ title }} - Kiracı Üye Paneli{% endblock %}
{% block page_title %}{{ title }}{% endblock %}

{% block content %}
<div class="p-6">
    <!-- Geri Dön Butonu -->
    <div class="mb-4">
        <a href="{% url 'app:list' %}" class="text-vb-primary hover:text-blue-600">
            <i class="fas fa-arrow-left mr-2"></i>
            Listeye Dön
        </a>
    </div>
    
    <!-- Form Container -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm max-w-3xl">
        <h2 class="text-2xl font-bold text-vb-navy mb-6">
            <i class="fas fa-icon mr-2 text-vb-primary"></i>
            {{ title }}
        </h2>
        
        <!-- Form Hataları -->
        {% if form.errors %}
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 font-semibold mb-2">Lütfen hataları düzeltin:</p>
            <ul class="list-disc list-inside text-red-700 text-sm">
                {% for field, errors in form.errors.items %}
                    {% for error in errors %}
                    <li>{{ field }}: {{ error }}</li>
                    {% endfor %}
                {% endfor %}
            </ul>
        </div>
        {% endif %}
        
        <!-- Form -->
        <form method="post" class="space-y-4">
            {% csrf_token %}
            
            <!-- Grid Layout (2 kolon) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Alan Adı <span class="text-red-500">*</span>
                    </label>
                    {{ form.field_name }}
                    {% if form.field_name.errors %}
                    <p class="text-red-600 text-xs mt-1">{{ form.field_name.errors.0 }}</p>
                    {% endif %}
                </div>
            </div>
            
            <!-- Butonlar -->
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <a href="{% url 'app:list' %}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                    İptal
                </a>
                <button type="submit" class="px-6 py-2 bg-vb-primary text-white rounded-lg hover:bg-blue-600 transition-colors font-semibold">
                    <i class="fas fa-save mr-2"></i>
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>
{% endblock %}
```

### 2. List Template Yapısı

#### Temel Yapı
```html
{% extends "tenant/base.html" %}
{% load static %}

{% block title %}Liste - Kiracı Üye Paneli{% endblock %}

{% block content %}
<div class="p-6">
    <!-- Başlık ve Buton -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-vb-navy-500">
            <i class="fas fa-icon mr-2 text-vb-primary"></i>
            Liste Başlığı
        </h1>
        <a href="{% url 'app:create' %}" class="px-4 py-2 bg-vb-primary text-white rounded-lg hover:bg-blue-600">
            <i class="fas fa-plus mr-2"></i>Yeni Ekle
        </a>
    </div>

    <!-- Filtreler -->
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filtre</label>
                <select name="filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Arama</label>
                <input type="text" name="search" value="{{ search }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="md:col-span-3 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-vb-primary text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-search mr-2"></i>Filtrele
                </button>
                <a href="{% url 'app:list' %}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-redo mr-2"></i>Temizle
                </a>
            </div>
        </form>
    </div>

    <!-- Tablo -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-vb-navy-500 text-white">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold">Sütun 1</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold">Sütun 2</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                {% for item in items %}
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">{{ item.field1 }}</td>
                    <td class="px-4 py-3">{{ item.field2 }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-2">
                            <a href="{% url 'app:detail' item.pk %}" class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-sm">
                                <i class="fas fa-eye mr-1"></i>Detay
                            </a>
                            <a href="{% url 'app:update' item.pk %}" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm">
                                <i class="fas fa-edit mr-1"></i>Düzenle
                            </a>
                        </div>
                    </td>
                </tr>
                {% empty %}
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">Henüz kayıt bulunmamaktadır.</td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
    
    <!-- Sayfalama -->
    {% if items.has_other_pages %}
    <div class="mt-6 flex justify-center">
        <div class="flex space-x-2">
            {% if items.has_previous %}
            <a href="?page={{ items.previous_page_number }}" class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                <i class="fas fa-chevron-left"></i>
            </a>
            {% endif %}
            
            <span class="px-4 py-2 bg-vb-primary text-white rounded">
                Sayfa {{ items.number }} / {{ items.paginator.num_pages }}
            </span>
            
            {% if items.has_next %}
            <a href="?page={{ items.next_page_number }}" class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                <i class="fas fa-chevron-right"></i>
            </a>
            {% endif %}
        </div>
    </div>
    {% endif %}
</div>
{% endblock %}
```

### 3. Detail Template Yapısı

#### Temel Yapı
```html
{% extends "tenant/base.html" %}
{% load static %}

{% block title %}Detay - Kiracı Üye Paneli{% endblock %}

{% block content %}
<div class="p-6">
    <!-- Geri Dön Butonu -->
    <div class="mb-4">
        <a href="{% url 'app:list' %}" class="text-vb-primary hover:text-blue-600">
            <i class="fas fa-arrow-left mr-2"></i>
            Listeye Dön
        </a>
    </div>
    
    <!-- Detay Container -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-vb-navy">
                <i class="fas fa-icon mr-2 text-vb-primary"></i>
                {{ object.name }}
            </h2>
            <div class="flex gap-2">
                <a href="{% url 'app:update' object.pk %}" class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200">
                    <i class="fas fa-edit mr-2"></i>Düzenle
                </a>
            </div>
        </div>
        
        <!-- Bilgiler -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-500 mb-1">Alan 1</label>
                <p class="text-gray-900">{{ object.field1 }}</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-500 mb-1">Alan 2</label>
                <p class="text-gray-900">{{ object.field2 }}</p>
            </div>
        </div>
    </div>
</div>
{% endblock %}
```

---

## 🎨 CSS Sınıfları

### Renkler
- **Primary:** `bg-vb-primary` / `text-vb-primary` (#3498db)
- **Navy:** `bg-vb-navy-500` / `text-vb-navy-500` (#2d3e50)
- **Navy Dark:** `bg-vb-navy-600` / `text-vb-navy-600` (#1e2935)
- **Gray:** `bg-gray-50`, `bg-gray-100`, `bg-gray-200`, `text-gray-700`

### Butonlar
- **Primary:** `px-4 py-2 bg-vb-primary text-white rounded-lg hover:bg-blue-600`
- **Secondary:** `px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300`
- **Success:** `px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-sm`
- **Warning:** `px-3 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm`
- **Danger:** `px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm`
- **Info:** `px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-sm`

### Form Elemanları
- **Input:** `w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent`
- **Select:** `w-full px-3 py-2 border border-gray-300 rounded-lg`
- **Textarea:** `w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent`
- **Checkbox:** `w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary`

### Layout
- **Container:** `p-6`
- **Card:** `bg-white rounded-lg border border-gray-200 p-6 shadow-sm`
- **Grid 2 Kolon:** `grid grid-cols-1 md:grid-cols-2 gap-4`
- **Grid 3 Kolon:** `grid grid-cols-1 md:grid-cols-3 gap-4`

### Typography
- **Başlık 1:** `text-2xl font-bold text-vb-navy-500`
- **Başlık 2:** `text-xl font-semibold text-gray-900`
- **Label:** `block text-sm font-semibold text-gray-700 mb-1`
- **Label (Zorunlu):** `block text-sm font-semibold text-gray-700 mb-1` + `<span class="text-red-500">*</span>`

---

## ✅ Uygulama Kuralları

1. **Tüm form template'leri** yukarıdaki yapıya uymalıdır
2. **Tüm list template'leri** yukarıdaki yapıya uymalıdır
3. **Tüm detail template'leri** yukarıdaki yapıya uymalıdır
4. **Grid layout** kullanılmalı (2 veya 3 kolon)
5. **Hover efektleri** tüm butonlarda olmalı
6. **Error mesajları** kırmızı renkte gösterilmeli
7. **Icon'lar** Font Awesome kullanılmalı
8. **Responsive** tasarım olmalı (`md:` prefix'i ile)

---

## 📝 Notlar

- Bu standartlar **Tur Yönetimi** modülünde kullanılan yapıya göre belirlenmiştir
- Yeni modül eklerken bu standartlara uyulmalıdır
- Mevcut modüller (Finance, Accounting, Refunds) bu standartlara göre güncellenmelidir

---

**📅 Son Güncelleme:** 2025-01-XX  
**👤 Geliştirici:** AI Assistant  
**📝 Versiyon:** 1.0.0

