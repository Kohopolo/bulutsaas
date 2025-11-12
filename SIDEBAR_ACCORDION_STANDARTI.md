# Sidebar Accordion Sistemi - Standart Kullanım Kılavuzu

**Tarih:** 12 Kasım 2025  
**Amaç:** Yeni modüller eklendiğinde accordion yapısını standart şekilde uygulamak

---

## 📋 Standart Yapı

### 1. Ana Modül Accordion

Her yeni modül için ana accordion yapısı:

```html
<!-- Modül Adı -->
{% if has_MODULE_module %}
<div class="mb-2">
    <button onclick="toggleModule('MODULE-module')" class="w-full flex items-center justify-between px-3 py-2 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
        <div class="flex items-center">
            <i class="fas fa-ICON w-5"></i>
            <span class="ml-3">Modül Adı</span>
        </div>
        <i id="MODULE-module-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
    </button>
    <div id="MODULE-module" class="hidden">
        <!-- Alt menüler buraya -->
    </div>
</div>
{% endif %}
```

### 2. Alt Modül Accordion (İsteğe Bağlı)

Eğer modül içinde gruplandırma gerekiyorsa:

```html
<!-- Grup Adı -->
<button onclick="toggleModule('MODULE-group')" class="w-full flex items-center justify-between px-3 py-2 pl-8 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
    <div class="flex items-center">
        <i class="fas fa-ICON w-4"></i>
        <span class="ml-3">Grup Adı</span>
    </div>
    <i id="MODULE-group-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
</button>
<div id="MODULE-group" class="hidden pl-8">
    <a href="{% url 'app:view' %}" class="flex items-center px-3 py-1.5 pl-10 text-gray-400 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-xs">
        <i class="fas fa-ICON w-3"></i>
        <span class="ml-2">Alt Menü Adı</span>
    </a>
</div>
```

### 3. Basit Link (Accordion Olmadan)

Eğer alt modül accordion gerektirmiyorsa:

```html
<a href="{% url 'app:view' %}" class="flex items-center px-3 py-2 pl-8 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-sm">
    <i class="fas fa-ICON w-4"></i>
    <span class="ml-3">Menü Adı</span>
</a>
```

---

## 🎨 Stil Standartları

### Icon Boyutları
- **Ana Modül:** `w-5` (20px)
- **Grup Başlığı:** `w-4` (16px)
- **Alt Menü:** `w-3` (12px) veya `w-4` (16px)

### Padding Standartları
- **Ana Modül Butonu:** `px-3 py-2`
- **Grup Butonu:** `px-3 py-2 pl-8`
- **Alt Menü Link:** `px-3 py-1.5 pl-10` (accordion içinde) veya `px-3 py-2 pl-8` (direkt)

### Text Boyutları
- **Ana Modül:** `text-sm`
- **Grup Başlığı:** `text-sm`
- **Alt Menü:** `text-xs` (accordion içinde) veya `text-sm` (direkt)

### Renk Standartları
- **Ana Modül/Grup Başlığı:** `text-gray-400` (normal), `hover:text-white`
- **Alt Menü Link:** `text-gray-300` (normal), `hover:text-white`
- **Accordion İçi Link:** `text-gray-400` (normal), `hover:text-white`

---

## 📝 Örnekler

### Örnek 1: Basit Modül (Accordion Yok)

```html
<!-- Basit Modül -->
{% if has_simple_module %}
<a href="{% url 'simple:list' %}" class="flex items-center px-3 py-2 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
    <i class="fas fa-simple w-5"></i>
    <span class="ml-3">Basit Modül</span>
</a>
{% endif %}
```

### Örnek 2: Modül + Alt Menüler (Accordion)

```html
<!-- Modül Adı -->
{% if has_complex_module %}
<div class="mb-2">
    <button onclick="toggleModule('complex-module')" class="w-full flex items-center justify-between px-3 py-2 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
        <div class="flex items-center">
            <i class="fas fa-complex w-5"></i>
            <span class="ml-3">Kompleks Modül</span>
        </div>
        <i id="complex-module-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
    </button>
    <div id="complex-module" class="hidden">
        <a href="{% url 'complex:list' %}" class="flex items-center px-3 py-2 pl-8 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-sm">
            <i class="fas fa-list w-4"></i>
            <span class="ml-3">Liste</span>
        </a>
        <a href="{% url 'complex:create' %}" class="flex items-center px-3 py-2 pl-8 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-sm">
            <i class="fas fa-plus w-4"></i>
            <span class="ml-3">Yeni Ekle</span>
        </a>
    </div>
</div>
{% endif %}
```

### Örnek 3: Modül + Gruplar (Çoklu Accordion)

```html
<!-- Modül Adı -->
{% if has_grouped_module %}
<div class="mb-2">
    <button onclick="toggleModule('grouped-module')" class="w-full flex items-center justify-between px-3 py-2 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
        <div class="flex items-center">
            <i class="fas fa-grouped w-5"></i>
            <span class="ml-3">Gruplu Modül</span>
        </div>
        <i id="grouped-module-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
    </button>
    <div id="grouped-module" class="hidden">
        <!-- Grup 1: İşlemler -->
        <button onclick="toggleModule('grouped-operations')" class="w-full flex items-center justify-between px-3 py-2 pl-8 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
            <div class="flex items-center">
                <i class="fas fa-exchange-alt w-4"></i>
                <span class="ml-3">İşlemler</span>
            </div>
            <i id="grouped-operations-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
        </button>
        <div id="grouped-operations" class="hidden pl-8">
            <a href="{% url 'grouped:operation_list' %}" class="flex items-center px-3 py-1.5 pl-10 text-gray-400 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-xs">
                <i class="fas fa-list w-3"></i>
                <span class="ml-2">İşlem Listesi</span>
            </a>
        </div>
        
        <!-- Grup 2: Raporlar -->
        <button onclick="toggleModule('grouped-reports')" class="w-full flex items-center justify-between px-3 py-2 pl-8 text-gray-400 text-sm font-semibold hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors">
            <div class="flex items-center">
                <i class="fas fa-chart-bar w-4"></i>
                <span class="ml-3">Raporlar</span>
            </div>
            <i id="grouped-reports-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
        </button>
        <div id="grouped-reports" class="hidden pl-8">
            <a href="{% url 'grouped:report_list' %}" class="flex items-center px-3 py-1.5 pl-10 text-gray-400 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-xs">
                <i class="fas fa-file-alt w-3"></i>
                <span class="ml-2">Rapor Listesi</span>
            </a>
        </div>
    </div>
</div>
{% endif %}
```

---

## ✅ Kontrol Listesi

Yeni modül eklerken:

- [ ] Ana modül accordion yapısı eklendi
- [ ] `toggleModule('MODULE-module')` fonksiyonu doğru ID ile çağrılıyor
- [ ] Icon ID'si `MODULE-module-icon` formatında
- [ ] Alt menüler `pl-8` padding ile hizalanmış
- [ ] Accordion içi linkler `pl-10` padding ve `text-xs` kullanıyor
- [ ] Hover efektleri doğru renklerle (`hover:bg-vb-navy-400 hover:text-white`)
- [ ] Icon boyutları standartlara uygun
- [ ] `{% if has_MODULE_module %}` kontrolü eklendi
- [ ] Context processor'da `has_MODULE_module` tanımlı

---

## 🔧 JavaScript Fonksiyonu

Accordion sistemi için gerekli JavaScript fonksiyonu zaten `templates/tenant/base.html` içinde tanımlı:

```javascript
function toggleModule(moduleId) {
    const module = document.getElementById(moduleId);
    const icon = document.getElementById(moduleId + '-icon');
    
    if (module && icon) {
        const isHidden = module.classList.contains('hidden');
        
        if (isHidden) {
            module.classList.remove('hidden');
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            module.classList.add('hidden');
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
}
```

Bu fonksiyon otomatik olarak çalışır, ek bir JavaScript eklemeye gerek yoktur.

---

## 📌 Notlar

1. **ID Formatı:** Her accordion için benzersiz ID kullanılmalı. Format: `MODULE-module`, `MODULE-group` gibi.
2. **Icon ID:** Her accordion butonunun icon'u için ID formatı: `MODULE-module-icon`
3. **Varsayılan Durum:** Tüm accordion'lar varsayılan olarak `hidden` class'ı ile kapalı gelir.
4. **Nested Accordion:** İç içe accordion'lar desteklenir (örnek: Tur Modülü > Ayarlar)
5. **Mobil Uyumluluk:** Accordion sistemi mobil uyumludur, ek bir işlem gerekmez.

---

**Son Güncelleme:** 12 Kasım 2025

