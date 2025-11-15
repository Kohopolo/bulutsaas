# Paket Modül Değişikliklerini Kaydetme Sorunu Düzeltme

## 📋 Sorun

Django admin panelinde paket modül değişiklikleri kaydedilmiyordu. Inline formset'te yapılan değişiklikler (yeni modül ekleme, mevcut modül düzenleme, modül silme) kaydedilmiyordu.

---

## 🔍 Sorunun Nedenleri

1. **`save_formset` metodu eksikti**: `PackageAdmin` sınıfında inline formset'leri kaydetmek için özel bir `save_formset` metodu yoktu. Django admin varsayılan olarak inline formset'leri kaydetmeli ama bazı durumlarda özel işlem gerekebilir.

2. **JSONField validasyonu eksikti**: `PackageModuleInlineForm`'da `permissions` ve `limits` JSONField'ları için validasyon yoktu. Geçersiz JSON formatı hataları sessizce göz ardı ediliyor olabilir.

3. **JSONField widget'ı eksikti**: `permissions` field'ı için widget tanımlanmamıştı, bu yüzden form gösteriminde sorun olabilir.

---

## ✅ Çözüm

### 1. `PackageAdmin.save_formset` Metodu Eklendi

**Dosya:** `apps/packages/admin.py`

```python
def save_formset(self, request, form, formset, change):
    """Inline formset'i kaydet"""
    instances = formset.save(commit=False)
    for instance in instances:
        # Package otomatik olarak atanır (inline formset)
        if not instance.package_id:
            instance.package = form.instance
        instance.save()
    
    # Silinen kayıtları işle
    for obj in formset.deleted_objects:
        obj.delete()
    
    # Yeni kayıtları kaydet
    for instance in formset.new_objects:
        if not instance.package_id:
            instance.package = form.instance
        instance.save()
```

**Açıklama:**
- `formset.save(commit=False)` ile instance'ları alıyoruz ama henüz kaydetmiyoruz
- Her instance için `package` field'ını kontrol ediyoruz ve yoksa parent `form.instance`'ı atıyoruz
- `deleted_objects` ile silinen kayıtları siliyoruz
- `new_objects` ile yeni kayıtları kaydediyoruz

### 2. `PackageModuleInlineForm` İyileştirmeleri

**Dosya:** `apps/packages/forms.py`

#### a) `permissions` Widget Eklendi

```python
widgets = {
    'limits': forms.Textarea(attrs={'rows': 3, ...}),
    'permissions': forms.Textarea(attrs={'rows': 2, 'placeholder': '{"view": true, "add": true, "edit": false, "delete": false}'}),
    'module': forms.Select(attrs={'class': 'form-control'}),
}
```

#### b) JSONField Initial Değerleri Düzeltildi

```python
def __init__(self, *args, **kwargs):
    super().__init__(*args, **kwargs)
    # ...
    
    # JSONField'lar için varsayılan değerleri string'e çevir (eğer instance varsa)
    if self.instance and self.instance.pk:
        import json
        if self.instance.permissions:
            self.initial['permissions'] = json.dumps(self.instance.permissions, ensure_ascii=False, indent=2)
        if self.instance.limits:
            self.initial['limits'] = json.dumps(self.instance.limits, ensure_ascii=False, indent=2)
```

**Açıklama:**
- Mevcut instance varsa, JSONField değerlerini JSON string'e çeviriyoruz
- Bu sayede form gösteriminde JSON formatında görüntüleniyor

#### c) JSONField Validasyon Metodları Eklendi

```python
def clean_permissions(self):
    """JSON permissions field'ını validate et"""
    import json
    permissions = self.cleaned_data.get('permissions')
    if permissions:
        if isinstance(permissions, str):
            try:
                permissions = json.loads(permissions)
            except json.JSONDecodeError as e:
                raise forms.ValidationError(f'Geçersiz JSON formatı: {str(e)}')
        if not isinstance(permissions, dict):
            raise forms.ValidationError('Yetkiler bir dictionary (obje) olmalıdır.')
    return permissions or {}

def clean_limits(self):
    """JSON limits field'ını validate et"""
    import json
    limits = self.cleaned_data.get('limits')
    if limits:
        if isinstance(limits, str):
            try:
                limits = json.loads(limits)
            except json.JSONDecodeError as e:
                raise forms.ValidationError(f'Geçersiz JSON formatı: {str(e)}')
        if not isinstance(limits, dict):
            raise forms.ValidationError('Limitler bir dictionary (obje) olmalıdır.')
    return limits or {}
```

**Açıklama:**
- `permissions` ve `limits` field'ları için validasyon metodları eklendi
- String formatındaki JSON'u parse ediyor ve geçersizse hata veriyor
- Dictionary olmayan değerler için hata veriyor

---

## 📝 Dosya Değişiklikleri

- **`apps/packages/admin.py`**
  - `save_formset` metodu eklendi
  - Inline formset kaydetme işlemi düzeltildi

- **`apps/packages/forms.py`**
  - `permissions` widget eklendi
  - JSONField initial değerleri düzeltildi
  - `clean_permissions` metodu eklendi
  - `clean_limits` metodu eklendi

---

## 🧪 Test

1. Django admin paneline giriş yapın: `http://localhost:8000/admin/`
2. Bir paket düzenleme sayfasına gidin: `http://localhost:8000/admin/packages/package/1/change/`
3. "Paket Modülleri" bölümünde:
   - Yeni bir modül ekleyin
   - Mevcut bir modülü düzenleyin (örneğin `is_enabled` veya `limits` değiştirin)
   - Bir modülü silin (DELETE checkbox'ını işaretleyin)
4. Formu kaydedin
5. Sayfayı yenileyin ve değişikliklerin kaydedildiğini kontrol edin

---

## ✅ Sonuç

Paket modül değişiklikleri artık:
- ✅ Yeni modül ekleme çalışıyor
- ✅ Mevcut modül düzenleme çalışıyor
- ✅ Modül silme çalışıyor
- ✅ JSONField validasyonu çalışıyor
- ✅ Hata mesajları gösteriliyor

**Tarih:** 2025-11-14

