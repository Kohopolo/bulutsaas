# JSON Form İyileştirme Planı

## Özet

Bu plan, tenant admin panelindeki tüm JSON form girişlerini kullanıcı dostu, pratik ve kodsuz hale getirmek için kapsamlı bir çözüm sunmaktadır.

---

## 1. MEVCUT DURUM ANALİZİ

### 1.1. Tespit Edilen JSON Alanları

#### Hotels Modülü:
1. **RoomPrice:**
   - `adult_multipliers`: `{"1": 1.0, "2": 1.8, "3": 2.5}`
   - `free_children_rules`: `[{"age_range": "0-6", "count": 2, "adult_required": 2}]`

2. **RoomSeasonalPrice:**
   - `weekday_prices`: Hafta içi günlük fiyatlar

3. **RoomCampaignPrice:**
   - `campaign_rules`: Kampanya kuralları

4. **Hotel:**
   - `social_media`: `{"facebook": "url", "instagram": "url"}`
   - `services`: Hizmetler listesi
   - `amenities`: Olanaklar listesi
   - `settings`: Otel ayarları

#### Customer Modülü:
- `special_dates`: Özel günler

#### Packages Modülü:
- `limits`: `{"max_hotels": 5, "max_room_numbers": 100}`
- `permissions`: `{"view": true, "add": true}`

#### Tenant Modülü:
- `settings`: Tenant ayarları

---

## 2. ÇÖZÜM MİMARİSİ

### 2.1. Genel Yaklaşım

**3 Katmanlı Çözüm:**

1. **Frontend Widget Sistemi:**
   - Her JSON tipi için özel widget
   - JavaScript ile dinamik form yönetimi
   - Kullanıcı dostu arayüz

2. **Backend Widget Sistemi:**
   - Django custom widget'ları
   - JSON'a otomatik dönüştürme
   - Validasyon

3. **Genel Kütüphane:**
   - Yeniden kullanılabilir widget'lar
   - Standart JSON form tipleri

---

## 3. WIDGET TİPLERİ

### 3.1. Key-Value Widget (Dictionary)

**Kullanım Alanları:**
- `adult_multipliers`: `{"1": 1.0, "2": 1.8}`
- `social_media`: `{"facebook": "url", "instagram": "url"}`
- `settings`: Genel ayarlar

**Arayüz:**
```
┌─────────────────────────────────────┐
│ Key-Value Çiftleri                 │
├─────────────────────────────────────┤
│ [Anahtar] [Değer]        [Sil]     │
│ ┌────────┐ ┌────────┐   [×]       │
│ │   1    │ │  1.0   │              │
│ └────────┘ └────────┘              │
│                                     │
│ [Anahtar] [Değer]        [Sil]     │
│ ┌────────┐ ┌────────┐   [×]       │
│ │   2    │ │  1.8   │              │
│ └────────┘ └────────┘              │
│                                     │
│ [+ Yeni Çift Ekle]                 │
└─────────────────────────────────────┘
```

### 3.2. List Widget (Array)

**Kullanım Alanları:**
- `services`: Hizmetler listesi
- `amenities`: Olanaklar listesi

**Arayüz:**
```
┌─────────────────────────────────────┐
│ Liste Öğeleri                      │
├─────────────────────────────────────┤
│ [Öğe 1]                    [Sil]    │
│ ┌──────────────────────┐   [×]     │
│ │ WiFi                 │           │
│ └──────────────────────┘           │
│                                     │
│ [Öğe 2]                    [Sil]   │
│ ┌──────────────────────┐   [×]     │
│ │ Otopark              │           │
│ └──────────────────────┘           │
│                                     │
│ [+ Yeni Öğe Ekle]                  │
└─────────────────────────────────────┘
```

### 3.3. Object List Widget (Array of Objects)

**Kullanım Alanları:**
- `free_children_rules`: `[{"age_range": "0-6", "count": 2, "adult_required": 2}]`
- `campaign_rules`: Kampanya kuralları

**Arayüz:**
```
┌─────────────────────────────────────┐
│ Kurallar                            │
├─────────────────────────────────────┤
│ Kural 1                    [Sil]    │
│ ┌─────────────────────────────────┐ │
│ │ Yaş Aralığı: [0-6]             │ │
│ │ Sayı:        [2]               │ │
│ │ Gerekli Yetişkin: [2]          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [+ Yeni Kural Ekle]                 │
└─────────────────────────────────────┘
```

### 3.4. Weekday Prices Widget

**Kullanım Alanları:**
- `weekday_prices`: Hafta içi günlük fiyatlar

**Arayüz:**
```
┌─────────────────────────────────────┐
│ Hafta İçi Günlük Fiyatlar          │
├─────────────────────────────────────┤
│ Pazartesi: [100.00 ₺]              │
│ Salı:      [100.00 ₺]              │
│ Çarşamba:  [120.00 ₺]              │
│ Perşembe:  [120.00 ₺]              │
│ Cuma:      [150.00 ₺]              │
│ Cumartesi: [200.00 ₺]              │
│ Pazar:     [200.00 ₺]              │
└─────────────────────────────────────┘
```

### 3.5. Limits Widget (Package Limits)

**Kullanım Alanları:**
- `limits`: Paket limitleri

**Arayüz:**
```
┌─────────────────────────────────────┐
│ Paket Limitleri                    │
├─────────────────────────────────────┤
│ Maksimum Otel:     [10]            │
│ Maksimum Oda:      [100]            │
│ Maksimum Kullanıcı: [20]            │
│ Maksimum Rezervasyon: [1000]        │
│ AI Kredisi:        [5000]           │
└─────────────────────────────────────┘
```

---

## 4. UYGULAMA PLANI

### 4.1. Adım 1: Genel Widget Kütüphanesi

**Dosya:** `apps/core/widgets/json_widgets.py`

```python
from django import forms
from django.utils.safestring import mark_safe
import json

class KeyValueWidget(forms.Widget):
    """Key-Value çiftleri için widget"""
    template_name = 'widgets/key_value_widget.html'
    
    def __init__(self, key_label='Anahtar', value_label='Değer', *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.key_label = key_label
        self.value_label = value_label
    
    def format_value(self, value):
        if isinstance(value, str):
            try:
                return json.loads(value)
            except:
                return {}
        return value or {}
    
    def value_from_datadict(self, data, files, name):
        # Form'dan gelen veriyi JSON'a dönüştür
        pairs = []
        i = 0
        while f'{name}_key_{i}' in data:
            key = data.get(f'{name}_key_{i}', '').strip()
            value = data.get(f'{name}_value_{i}', '').strip()
            if key:
                pairs.append((key, value))
            i += 1
        return json.dumps(dict(pairs)) if pairs else '{}'

class ObjectListWidget(forms.Widget):
    """Nesne listesi için widget"""
    template_name = 'widgets/object_list_widget.html'
    
    def __init__(self, fields_config, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields_config = fields_config  # [{'name': 'age_range', 'label': 'Yaş Aralığı', 'type': 'text'}]
```

### 4.2. Adım 2: JavaScript Kütüphanesi

**Dosya:** `static/js/json_form_widgets.js`

```javascript
// Key-Value Widget
class KeyValueWidget {
    constructor(containerId, fieldName) {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.pairs = [];
        this.init();
    }
    
    init() {
        // Mevcut veriyi yükle
        const hiddenInput = document.getElementById(`${this.fieldName}_json`);
        if (hiddenInput && hiddenInput.value) {
            try {
                this.pairs = Object.entries(JSON.parse(hiddenInput.value));
            } catch (e) {
                this.pairs = [];
            }
        }
        this.render();
    }
    
    addPair(key = '', value = '') {
        this.pairs.push([key, value]);
        this.render();
        this.updateHiddenInput();
    }
    
    removePair(index) {
        this.pairs.splice(index, 1);
        this.render();
        this.updateHiddenInput();
    }
    
    updatePair(index, key, value) {
        this.pairs[index] = [key, value];
        this.updateHiddenInput();
    }
    
    updateHiddenInput() {
        const obj = Object.fromEntries(this.pairs.filter(p => p[0]));
        document.getElementById(`${this.fieldName}_json`).value = JSON.stringify(obj);
    }
    
    render() {
        // HTML render
    }
}

// Object List Widget
class ObjectListWidget {
    constructor(containerId, fieldName, fieldsConfig) {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.fieldsConfig = fieldsConfig;
        this.objects = [];
        this.init();
    }
    
    // Benzer metodlar...
}
```

### 4.3. Adım 3: Template Widget'ları

**Dosya:** `templates/widgets/key_value_widget.html`

```html
<div id="{{ widget.attrs.id }}_container" class="json-widget key-value-widget">
    <div class="pairs-container">
        <!-- Dinamik olarak eklenecek -->
    </div>
    <button type="button" class="add-pair-btn" onclick="addKeyValuePair('{{ widget.attrs.id }}')">
        <i class="fas fa-plus"></i> Yeni Çift Ekle
    </button>
    <input type="hidden" id="{{ widget.attrs.id }}_json" name="{{ widget.name }}" value="{{ widget.value|default:'{}' }}">
</div>
```

### 4.4. Adım 4: Form Güncellemeleri

**Örnek: RoomPriceForm**

```python
class RoomPriceForm(forms.ModelForm):
    adult_multipliers_json = forms.CharField(
        widget=KeyValueWidget(
            key_label='Kişi Sayısı',
            value_label='Çarpan',
            attrs={'class': 'form-control'}
        ),
        required=False,
        label='Yetişkin Çarpanları'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            self.fields['adult_multipliers_json'].initial = json.dumps(
                self.instance.adult_multipliers or {}
            )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        # JSON string'i parse et ve kaydet
        adult_multipliers_str = self.cleaned_data.get('adult_multipliers_json', '{}')
        try:
            instance.adult_multipliers = json.loads(adult_multipliers_str)
        except:
            instance.adult_multipliers = {}
        if commit:
            instance.save()
        return instance
```

---

## 5. ÖRNEK UYGULAMALAR

### 5.1. Yetişkin Çarpanları (adult_multipliers)

**Önceki Durum:**
```html
<textarea name="adult_multipliers" placeholder='JSON format: {"1": 1.0, "2": 1.8, "3": 2.5}'></textarea>
```

**Yeni Durum:**
```
┌─────────────────────────────────────┐
│ Yetişkin Çarpanları                │
├─────────────────────────────────────┤
│ Kişi Sayısı │ Çarpan      │ [Sil]  │
│ ┌─────────┐ │ ┌─────────┐ │  [×]   │
│ │    1    │ │ │  1.0    │ │        │
│ └─────────┘ │ └─────────┘ │        │
│                                     │
│ Kişi Sayısı │ Çarpan      │ [Sil]  │
│ ┌─────────┐ │ ┌─────────┐ │  [×]   │
│ │    2    │ │ │  1.8    │ │        │
│ └─────────┘ │ └─────────┘ │        │
│                                     │
│ [+ Yeni Çift Ekle]                 │
└─────────────────────────────────────┘
```

### 5.2. Ücretsiz Çocuk Kuralları (free_children_rules)

**Önceki Durum:**
```html
<textarea name="free_children_rules" placeholder='JSON format: [{"age_range": "0-6", "count": 2, "adult_required": 2}]'></textarea>
```

**Yeni Durum:**
```
┌─────────────────────────────────────┐
│ Ücretsiz Çocuk Kuralları           │
├─────────────────────────────────────┤
│ Kural 1                    [Sil]    │
│ ┌─────────────────────────────────┐ │
│ │ Yaş Aralığı: [0-6]             │ │
│ │ Sayı:        [2]               │ │
│ │ Gerekli Yetişkin: [2]          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Kural 2                    [Sil]   │
│ ┌─────────────────────────────────┐ │
│ │ Yaş Aralığı: [7-12]            │ │
│ │ Sayı:        [1]               │ │
│ │ Gerekli Yetişkin: [2]          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [+ Yeni Kural Ekle]                 │
└─────────────────────────────────────┘
```

### 5.3. Sosyal Medya (social_media)

**Önceki Durum:**
```html
<textarea name="social_media" placeholder='JSON format: {"facebook": "url", "instagram": "url"}'></textarea>
```

**Yeni Durum:**
```
┌─────────────────────────────────────┐
│ Sosyal Medya Hesapları             │
├─────────────────────────────────────┤
│ Platform │ URL              │ [Sil] │
│ ┌───────┐ │ ┌──────────────┐ │  [×]  │
│ │Facebook│ │ │https://...   │ │       │
│ └───────┘ │ └──────────────┘ │       │
│                                     │
│ Platform │ URL              │ [Sil] │
│ ┌───────┐ │ ┌──────────────┐ │  [×]  │
│ │Instagram│ │ │https://...   │ │       │
│ └───────┘ │ └──────────────┘ │       │
│                                     │
│ [+ Yeni Hesap Ekle]                 │
└─────────────────────────────────────┘
```

---

## 6. AVANTAJLAR

### 6.1. Kullanıcı Deneyimi
- ✅ Kod bilgisi gerektirmez
- ✅ Görsel ve sezgisel arayüz
- ✅ Hata yapma riski azalır
- ✅ Anında geri bildirim

### 6.2. Teknik Avantajlar
- ✅ Otomatik JSON validasyonu
- ✅ Tip kontrolü
- ✅ Yeniden kullanılabilir widget'lar
- ✅ Kolay bakım

### 6.3. İş Avantajları
- ✅ Eğitim süresi kısalır
- ✅ Kullanıcı memnuniyeti artar
- ✅ Hata oranı düşer
- ✅ Destek talepleri azalır

---

## 7. UYGULAMA SIRASI

### Faz 1: Temel Altyapı (1-2 gün)
1. Widget kütüphanesi oluştur
2. JavaScript kütüphanesi
3. Template widget'ları
4. Test widget'ları

### Faz 2: Hotels Modülü (2-3 gün)
1. `adult_multipliers` widget'ı
2. `free_children_rules` widget'ı
3. `weekday_prices` widget'ı
4. `campaign_rules` widget'ı
5. `social_media` widget'ı

### Faz 3: Diğer Modüller (1-2 gün)
1. Customer modülü
2. Packages modülü
3. Tenant modülü

### Faz 4: Test ve İyileştirme (1 gün)
1. Tüm widget'ları test et
2. Kullanıcı geri bildirimleri
3. İyileştirmeler

---

## 8. TEKNİK DETAYLAR

### 8.1. Widget Yapısı

```python
# apps/core/widgets/json_widgets.py
class BaseJSONWidget(forms.Widget):
    """Tüm JSON widget'larının temel sınıfı"""
    def format_value(self, value):
        """Model'den gelen değeri formatla"""
        pass
    
    def value_from_datadict(self, data, files, name):
        """Form'dan gelen veriyi JSON'a dönüştür"""
        pass
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/json_form_widgets.js',)
```

### 8.2. JavaScript Yapısı

```javascript
// static/js/json_form_widgets.js
class BaseJSONWidget {
    constructor(containerId, fieldName) {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.hiddenInput = document.getElementById(`${fieldName}_json`);
    }
    
    init() {
        // Mevcut veriyi yükle
    }
    
    render() {
        // HTML render
    }
    
    updateHiddenInput() {
        // Hidden input'u güncelle
    }
    
    validate() {
        // Validasyon
    }
}
```

### 8.3. CSS Stil

```css
/* static/css/json_widgets.css */
.json-widget {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
}

.json-widget .pair-item,
.json-widget .object-item {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.json-widget .remove-btn {
    color: #dc3545;
    cursor: pointer;
}

.json-widget .add-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}
```

---

## 9. ÖRNEK KOD

### 9.1. RoomPriceForm Güncellemesi

```python
# apps/tenant_apps/hotels/forms.py
from apps.core.widgets.json_widgets import KeyValueWidget, ObjectListWidget

class RoomPriceForm(forms.ModelForm):
    # Yetişkin Çarpanları için widget
    adult_multipliers_json = forms.CharField(
        widget=KeyValueWidget(
            key_label='Kişi Sayısı',
            value_label='Çarpan',
            key_type='number',
            value_type='number',
            attrs={'class': 'form-control'}
        ),
        required=False,
        label='Yetişkin Çarpanları',
        help_text='Her kişi sayısı için çarpan değeri girin'
    )
    
    # Ücretsiz Çocuk Kuralları için widget
    free_children_rules_json = forms.CharField(
        widget=ObjectListWidget(
            fields_config=[
                {'name': 'age_range', 'label': 'Yaş Aralığı', 'type': 'text', 'placeholder': '0-6'},
                {'name': 'count', 'label': 'Sayı', 'type': 'number', 'min': 0},
                {'name': 'adult_required', 'label': 'Gerekli Yetişkin', 'type': 'number', 'min': 0},
            ],
            attrs={'class': 'form-control'}
        ),
        required=False,
        label='Ücretsiz Çocuk Kuralları'
    )
    
    class Meta:
        model = RoomPrice
        fields = [
            'pricing_type', 'currency', 'basic_nightly_price',
            'total_discount_rate',
            'adult_multipliers_json',  # JSON widget
            'child_fixed_multiplier', 'child_age_range',
            'free_children_count',
            'free_children_rules_json',  # JSON widget
        ]
        # adult_multipliers ve free_children_rules artık widget'tan gelecek
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.adult_multipliers:
                self.fields['adult_multipliers_json'].initial = json.dumps(
                    self.instance.adult_multipliers
                )
            if self.instance.free_children_rules:
                self.fields['free_children_rules_json'].initial = json.dumps(
                    self.instance.free_children_rules
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Yetişkin çarpanlarını parse et
        adult_multipliers_str = self.cleaned_data.get('adult_multipliers_json', '{}')
        try:
            instance.adult_multipliers = json.loads(adult_multipliers_str)
        except:
            instance.adult_multipliers = {}
        
        # Ücretsiz çocuk kurallarını parse et
        free_children_rules_str = self.cleaned_data.get('free_children_rules_json', '[]')
        try:
            instance.free_children_rules = json.loads(free_children_rules_str)
        except:
            instance.free_children_rules = []
        
        if commit:
            instance.save()
        return instance
```

---

## 10. SONUÇ

Bu plan, tüm JSON form girişlerini kullanıcı dostu hale getirecek kapsamlı bir çözüm sunmaktadır. Uygulandığında:

- ✅ Kullanıcılar kod yazmadan form doldurabilecek
- ✅ Hata oranı önemli ölçüde azalacak
- ✅ Kullanıcı deneyimi çok daha iyi olacak
- ✅ Sistem daha profesyonel görünecek
- ✅ Eğitim ve destek maliyetleri düşecek

**Tahmini Süre:** 5-8 gün  
**Öncelik:** Yüksek  
**Etki:** Çok Yüksek

---

**Hazırlayan:** AI Assistant  
**Tarih:** 11 Kasım 2025  
**Versiyon:** 1.0  
**Durum:** Planlama Aşaması
