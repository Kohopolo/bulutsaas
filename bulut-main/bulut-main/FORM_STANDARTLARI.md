# Form Standartları - Rezervasyon Modülü Referansı

## 📋 Genel Bakış

Bu dokümantasyon, tüm modüllerde kullanılacak form standartlarını tanımlar. Rezervasyon modülü (`apps/tenant_apps/reception/forms.py`) referans alınarak oluşturulmuştur.

---

## 🎨 CSS Standartları

### Form Control Class'ı
**Tüm form elementleri için standart class: `form-control`**

```css
/* REZERVASYON STANDARDI - Tüm modüllerde kullanılacak */
.form-control,
input.form-control,
select.form-control,
textarea.form-control {
    padding: 6px 10px !important;
    border: 1px solid #6c757d !important;
    border-width: 1px !important;
    border-style: solid !important;
    border-color: #6c757d !important;
    border-radius: 3px !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
    font-size: 13px !important;
    font-family: var(--font-family) !important;
    color: #333 !important;
    width: 100% !important;
    display: block !important;
    box-sizing: border-box !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    min-height: 32px !important;
}
```

### Border Renkleri
- **Normal durum**: `#6c757d` (koyu gri)
- **Focus durumu**: `#3498db` (mavi)
- **Background**: `#ffffff` (beyaz)

---

## 📝 Django Form Widget Standartları

### 1. TextInput (Metin Girişi)

```python
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'name': forms.TextInput(attrs={
    'class': 'form-control',
    'id': 'id_name'
}),
```

### 2. NumberInput (Sayı Girişi)

```python
'field_name': forms.NumberInput(attrs={
    'class': 'form-control',
    'step': '0.01',  # Ondalıklı sayılar için
    'min': 0,        # Minimum değer
    'max': 100,      # Maksimum değer (opsiyonel)
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'commission_rate': forms.NumberInput(attrs={
    'class': 'form-control',
    'step': '0.01',
    'min': 0,
    'max': 100,
    'id': 'id_commission_rate'
}),
```

### 3. DateInput (Tarih Girişi)

```python
'field_name': forms.DateInput(attrs={
    'class': 'form-control',
    'type': 'date',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'check_in_date': forms.DateInput(format='%Y-%m-%d', attrs={
    'class': 'form-control',
    'type': 'date',
    'id': 'id_check_in_date'
}),
```

### 4. TimeInput (Saat Girişi)

```python
'field_name': forms.TimeInput(attrs={
    'class': 'form-control',
    'type': 'time',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'check_in_time': forms.TimeInput(attrs={
    'class': 'form-control',
    'type': 'time',
    'id': 'id_check_in_time'
}),
```

### 5. EmailInput (E-posta Girişi)

```python
'field_name': forms.EmailInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'guest_email': forms.EmailInput(attrs={
    'class': 'form-control',
    'id': 'id_guest_email'
}),
```

### 6. URLInput (URL Girişi)

```python
'field_name': forms.URLInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'api_endpoint': forms.URLInput(attrs={
    'class': 'form-control',
    'id': 'id_api_endpoint'
}),
```

### 7. PasswordInput (Şifre Girişi)

```python
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'type': 'password',
    'autocomplete': 'off',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'api_key': forms.TextInput(attrs={
    'class': 'form-control',
    'type': 'password',
    'autocomplete': 'off',
    'id': 'id_api_key'
}),
```

### 8. Select (Dropdown)

```python
'field_name': forms.Select(attrs={
    'class': 'form-control',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'status': forms.Select(attrs={
    'class': 'form-control',
    'id': 'id_status'
}),
```

### 9. Textarea (Çok Satırlı Metin)

```python
'field_name': forms.Textarea(attrs={
    'class': 'form-control',
    'rows': 3,  # Satır sayısı (opsiyonel)
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'notes': forms.Textarea(attrs={
    'class': 'form-control',
    'rows': 3,
    'id': 'id_notes'
}),
```

### 10. CheckboxInput (Onay Kutusu)

```python
'field_name': forms.CheckboxInput(attrs={
    'class': 'form-check-input',
    'id': 'id_field_name'
})
```

**Örnek:**
```python
'is_active': forms.CheckboxInput(attrs={
    'class': 'form-check-input',
    'id': 'id_is_active'
}),
```

---

## 🔧 Tam Form Örneği

```python
"""
Modül Adı Forms
Rezervasyon form standartlarına uygun form yapısı
"""
from django import forms
from .models import YourModel


class YourModelForm(forms.ModelForm):
    """Form Açıklaması"""
    
    class Meta:
        model = YourModel
        fields = [
            'field1', 'field2', 'field3'
        ]
        widgets = {
            # TextInput
            'field1': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_field1'
            }),
            
            # NumberInput
            'field2': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_field2'
            }),
            
            # Select
            'field3': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_field3'
            }),
            
            # Textarea
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_notes'
            }),
            
            # Checkbox
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
        }
    
    def __init__(self, *args, **kwargs):
        tenant = kwargs.pop('tenant', None)
        super().__init__(*args, **kwargs)
        
        if tenant:
            # Tenant'a özel queryset'ler buraya
            pass
```

---

## ✅ Zorunlu Kurallar

### 1. Class Attribute
- **Tüm input, select, textarea**: `class='form-control'` (zorunlu)
- **Tüm checkbox**: `class='form-check-input'` (zorunlu)

### 2. ID Attribute
- **Her widget için**: `id='id_field_name'` formatında ID tanımlanmalı (zorunlu)
- **Format**: `id_` + field adı (Django'nun otomatik ID formatı)

### 3. Border ve Background
- **Border**: `1px solid #6c757d` (CSS'te tanımlı, form'da belirtmeye gerek yok)
- **Background**: `#ffffff` (CSS'te tanımlı, form'da belirtmeye gerek yok)

### 4. Padding ve Spacing
- **Padding**: `6px 10px` (CSS'te tanımlı)
- **Min-height**: Input ve Select için `32px`, Textarea için `80px` (CSS'te tanımlı)

---

## 🚫 Yapılmaması Gerekenler

### ❌ YANLIŞ
```python
# Inline style kullanmayın
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'style': 'border: 1px solid #ced4da; padding: 5px;'  # ❌
})

# ID eksik
'field_name': forms.TextInput(attrs={
    'class': 'form-control'  # ❌ ID yok
})

# Farklı class kullanmayın
'field_name': forms.TextInput(attrs={
    'class': 'custom-input'  # ❌ Standart dışı
})
```

### ✅ DOĞRU
```python
# Sadece class ve id kullanın
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name'  # ✅
})
```

---

## 📦 Özel Durumlar

### 1. HiddenInput
```python
'hidden_field': forms.HiddenInput()  # ID ve class gerekmez
```

### 2. Placeholder
```python
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name',
    'placeholder': 'Örnek metin...'  # ✅ Opsiyonel
})
```

### 3. Maxlength
```python
'field_name': forms.TextInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name',
    'maxlength': 11  # ✅ Opsiyonel
})
```

### 4. Readonly
```python
'field_name': forms.NumberInput(attrs={
    'class': 'form-control',
    'id': 'id_field_name',
    'readonly': True  # ✅ Opsiyonel
})
```

---

## 🎯 Uygulama Kontrol Listesi

Yeni bir form oluştururken veya mevcut bir formu güncellerken:

- [ ] Tüm input'lar `class='form-control'` kullanıyor mu?
- [ ] Tüm select'ler `class='form-control'` kullanıyor mu?
- [ ] Tüm textarea'lar `class='form-control'` kullanıyor mu?
- [ ] Tüm checkbox'lar `class='form-check-input'` kullanıyor mu?
- [ ] Her widget için `id='id_field_name'` tanımlı mı?
- [ ] Inline style kullanılmamış mı?
- [ ] Standart dışı class kullanılmamış mı?
- [ ] NumberInput'larda `step`, `min`, `max` uygun mu?
- [ ] Textarea'larda `rows` belirtilmiş mi?

---

## 📚 Referans Dosyalar

- **CSS Standartları**: `static/vb_theme/css/vb-style.css` (satır 613-772)
- **Rezervasyon Form Örneği**: `apps/tenant_apps/reception/forms.py`
- **Channel Management Form Örneği**: `apps/tenant_apps/channel_management/forms.py`
- **Payment Management Form Örneği**: `apps/tenant_apps/payment_management/forms.py`

---

## 🔄 Güncelleme Notları

- **Oluşturulma Tarihi**: 2025-01-XX
- **Son Güncelleme**: 2025-01-XX
- **Versiyon**: 1.0
- **Referans Modül**: Reception (Rezervasyon)

---

## 💡 İpuçları

1. **Yeni form oluştururken**: Bu dokümantasyonu referans alın
2. **Mevcut formları güncellerken**: Standartlara uygun hale getirin
3. **CSS değişiklikleri**: Sadece `vb-style.css` dosyasında yapın, form dosyalarında inline style kullanmayın
4. **Test**: Her form değişikliğinden sonra sayfayı yenileyip (Ctrl+F5) border'ların görünür olduğunu kontrol edin

---

**Not**: Bu standartlar tüm modüllerde zorunludur. Yeni form geliştirmelerinde bu dokümantasyona uyulmalıdır.

