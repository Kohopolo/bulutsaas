# 📦 Modül Ekleme Standartları

**Tarih:** 2025-01-XX  
**Versiyon:** 1.0.0

---

## 📋 Genel Bakış

Bu dokümanda yeni bir modül eklendiğinde yapılması gereken tüm işlemler listelenmiştir. **Bu işlemler unutulmamalı ve her modül ekleme işleminde takip edilmelidir.**

---

## ✅ Modül Ekleme Checklist

### 1. Model ve Veritabanı İşlemleri

- [ ] Model'ler oluşturuldu (`models.py`)
- [ ] Form'lar oluşturuldu (`forms.py`)
- [ ] Admin kayıtları yapıldı (`admin.py`)
- [ ] Migration'lar oluşturuldu (`python manage.py makemigrations`)
- [ ] Migration'lar uygulandı (`python manage.py migrate`)

### 2. View ve URL İşlemleri

- [ ] View'lar oluşturuldu (`views.py`)
- [ ] URL pattern'leri tanımlandı (`urls.py`)
- [ ] Decorator'lar eklendi (`@login_required`, `@require_module_permission`)
- [ ] Error handling yapıldı

### 3. Template İşlemleri

- [ ] List template oluşturuldu (`list.html`)
- [ ] Form template oluşturuldu (`form.html`)
- [ ] Detail template oluşturuldu (`detail.html`)
- [ ] Delete template oluşturuldu (`delete.html`) - gerekirse
- [ ] **CSS standartlarına uyuldu** (Aşağıdaki CSS Standartları bölümüne bakın)

#### CSS Standartları (ZORUNLU)

**Form Template Standartları:**

1. **Geri Dön Butonu:** Her form template'inin en üstünde "Geri Dön" butonu olmalıdır:
```html
<div class="mb-4">
    <a href="{% url 'module:list' %}" class="text-vb-primary hover:text-blue-600">
        <i class="fas fa-arrow-left mr-2"></i>
        Listeye Dön
    </a>
</div>
```

2. **Form Container:** Form container'ı şu yapıda olmalıdır:
```html
<div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm max-w-3xl">
    <h2 class="text-2xl font-bold text-vb-navy mb-6">
        <i class="fas fa-icon mr-2 text-vb-primary"></i>
        Form Başlığı
    </h2>
    <!-- Form içeriği -->
</div>
```

3. **Label Yapısı:** Tüm label'lar input'un üstünde, yıldız işareti yanında olmalıdır:
```html
<div>
    <label class="block text-sm font-semibold text-gray-700 mb-1">
        Alan Adı <span class="text-red-500">*</span>
    </label>
    {{ form.field_name }}
    {% if form.field_name.errors %}
    <p class="text-red-600 text-xs mt-1">{{ form.field_name.errors.0 }}</p>
    {% endif %}
</div>
```

4. **Grid Layout:** Form alanları grid yapısında yan yana olmalıdır:
```html
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <!-- Form alanları -->
</div>
```

5. **Hata Gösterimi:** Form hataları üstte gösterilmelidir:
```html
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
```

6. **Buton Yapısı:** Form butonları şu yapıda olmalıdır:
```html
<div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
    <a href="{% url 'module:list' %}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
        İptal
    </a>
    <button type="submit" class="px-6 py-2 bg-vb-primary text-white rounded-lg hover:bg-blue-600 transition-colors font-semibold">
        <i class="fas fa-save mr-2"></i>
        Kaydet
    </button>
</div>
```

7. **Input Stilleri:** Form input'ları için Tailwind CSS sınıfları kullanılmalıdır (form.py'da tanımlanmalı):
```python
# forms.py
class MyForm(forms.ModelForm):
    class Meta:
        widgets = {
            'field_name': forms.TextInput(attrs={
                'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'
            }),
        }
```

**Önemli:** Tüm modüller için bu CSS standartları **ZORUNLUDUR** ve tur modülündeki form template'leri referans alınmalıdır.

### 4. Sidebar Entegrasyonu

- [ ] `templates/tenant/base.html` dosyasına modül linki eklendi
- [ ] Modül başlığı eklendi (icon + isim)
- [ ] Alt modül linkleri eklendi (gerekirse)
- [ ] Conditional rendering yapıldı (`{% if has_module %}`)
- [ ] Icon seçildi (Font Awesome)

**Örnek:**
```html
<!-- Modül Başlığı -->
{% if has_module_name %}
<div class="mb-2">
    <div class="flex items-center px-3 py-2 text-gray-400 text-sm font-semibold">
        <i class="fas fa-icon w-5"></i>
        <span class="ml-3">Modül Adı</span>
    </div>
    <!-- Alt Modüller -->
    <a href="{% url 'module:list' %}" class="flex items-center px-3 py-2 pl-8 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-sm">
        <i class="fas fa-list w-4"></i>
        <span class="ml-3">Liste</span>
    </a>
</div>
{% endif %}
```

### 5. Context Processor Güncelleme

- [ ] `apps/tenant_apps/core/context_processors.py` dosyasına modül eklendi
- [ ] `has_module_name` boolean değişkeni eklendi
- [ ] `enabled_module_codes` listesine modül kodu eklendi

**Örnek:**
```python
def tenant_modules(request):
    # ...
    enabled_module_codes = [m['code'] for m in enabled_modules]
    
    return {
        'enabled_modules': enabled_modules,
        'enabled_module_codes': enabled_module_codes,
        'has_module_name': 'module_name' in enabled_module_codes,
        # ...
    }
```

### 6. Modül Kayıt İşlemleri

- [ ] `Module` modeline kayıt eklendi
- [ ] Management komutu oluşturuldu (`create_module_name_module.py`)
- [ ] Komut çalıştırıldı (`python manage.py create_module_name_module`)

**Örnek:**
```python
# apps/modules/management/commands/create_module_name_module.py
from django.core.management.base import BaseCommand
from apps.modules.models import Module

class Command(BaseCommand):
    help = 'Module Name modülünü oluşturur'
    
    def handle(self, *args, **kwargs):
        module, created = Module.objects.get_or_create(
            code='module_name',
            defaults={
                'name': 'Modül Adı',
                'description': 'Modül açıklaması',
                'icon': 'fas fa-icon',
                'category': 'category',
                'app_name': 'apps.tenant_apps.module_name',
                'url_prefix': 'module_name',
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 10,
            }
        )
```

### 7. Paket Entegrasyonu

- [ ] `PackageModule` kayıtları oluşturuldu
- [ ] Management komutu oluşturuldu (`add_module_name_to_packages.py`)
- [ ] Komut çalıştırıldı (`python manage.py add_module_name_to_packages`)
- [ ] Varsayılan yetkiler ve limitler tanımlandı

**Örnek:**
```python
# apps/packages/management/commands/add_module_name_to_packages.py
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule

class Command(BaseCommand):
    help = 'Module Name modülünü tüm paketlere ekler'
    
    def handle(self, *args, **kwargs):
        module = Module.objects.get(code='module_name')
        
        for package in Package.objects.filter(is_active=True):
            PackageModule.objects.get_or_create(
                package=package,
                module=module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': True,
                    },
                    'limits': {
                        'max_items': package.max_items,
                    },
                }
            )
```

### 8. Yetki Sistemi Entegrasyonu

- [ ] Permission kayıtları oluşturuldu
- [ ] Management komutu oluşturuldu (`create_module_name_permissions.py`)
- [ ] **Admin rolüne otomatik yetki atama eklendi** (ZORUNLU - Aşağıdaki bölüme bakın)
- [ ] Komut tüm tenant'lar için çalıştırıldı (`create_module_name_permissions_all_tenants.py`)
- [ ] View'larda `@require_module_permission` decorator'ı kullanıldı

#### 8.1. Admin Rolüne Otomatik Yetki Atama (ZORUNLU)

**Her yetki oluşturma komutunun sonuna admin rolüne otomatik yetki atama eklenmelidir:**

```python
# apps/tenant_apps/[modul]/management/commands/create_[modul]_permissions.py

# Yetkileri oluşturduktan sonra:
try:
    from django.core.management import call_command
    call_command('assign_module_permissions_to_admin', '--module-code', '[modul_kodu]', verbosity=0)
    self.stdout.write(self.style.SUCCESS('[OK] [Modul] modulu yetkileri admin rolune otomatik atandi'))
except Exception as e:
    self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))
```

**Örnek:**
```python
# create_customer_permissions.py sonunda
self.stdout.write(self.style.SUCCESS(f'[OK] Customer modulu izinleri olusturuldu: {created_count} yeni, {updated_count} guncellendi'))

# Admin rolüne otomatik yetki atama
try:
    from django.core.management import call_command
    call_command('assign_module_permissions_to_admin', '--module-code', 'customers', verbosity=0)
    self.stdout.write(self.style.SUCCESS('[OK] Customer modulu yetkileri admin rolune otomatik atandi'))
except Exception as e:
    self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))
```

**Örnek:**
```python
# apps/tenant_apps/core/management/commands/create_module_name_permissions.py
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission

class Command(BaseCommand):
    help = 'Module Name modülü için permission kayıtları oluşturur'
    
    def handle(self, *args, **kwargs):
        # Public schema'dan modülü al
        with schema_context(get_public_schema_name()):
            module = Module.objects.get(code='module_name')
        
        # Tenant schema'da permission'ları oluştur
        permissions = [
            {'code': 'view', 'name': 'Görüntüleme'},
            {'code': 'add', 'name': 'Ekleme'},
            {'code': 'edit', 'name': 'Düzenleme'},
            {'code': 'delete', 'name': 'Silme'},
        ]
        
        for perm_data in permissions:
            Permission.objects.get_or_create(
                module=module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'permission_type': perm_data['code'],
                    'is_active': True,
                }
            )
```

### 9. Raporlama (Opsiyonel)

- [ ] Rapor view'ları oluşturuldu (`views_reports.py`)
- [ ] Rapor URL'leri eklendi (`urls.py`)
- [ ] Rapor template'leri oluşturuldu (`reports/*.html`)
- [ ] Sidebar'a raporlama linki eklendi

**Örnek:**
```html
<!-- Sidebar'da -->
<a href="{% url 'module:report_list' %}" class="flex items-center px-3 py-2 pl-8 text-gray-300 hover:bg-vb-navy-400 hover:text-white rounded-vb transition-colors text-sm">
    <i class="fas fa-chart-bar w-4"></i>
    <span class="ml-3">Raporlama</span>
</a>
```

### 10. Test ve Doğrulama

- [ ] Modül sidebar'da görünüyor mu?
- [ ] Modül linkleri çalışıyor mu?
- [ ] Yetki kontrolü çalışıyor mu?
- [ ] Form'lar çalışıyor mu?
- [ ] List/Detail sayfaları çalışıyor mu?
- [ ] CSS standartlarına uyuldu mu?
- [ ] Migration'lar uygulandı mı?

---

## 📝 Önemli Notlar

1. **CSS Standartları:** Tüm template'ler `CSS_STANDARTLARI.md` dosyasındaki standartlara uymalıdır
2. **Yetki Sistemi:** Tüm view'lar `@require_module_permission` decorator'ı ile korunmalıdır
3. **Admin Otomatik Yetki Atama:** Her yeni modül için admin rolüne otomatik yetki atama **ZORUNLUDUR**
4. **Sidebar:** Modül linkleri sidebar'a eklenmeli ve conditional rendering yapılmalıdır
5. **Context Processor:** `has_module_name` değişkeni context processor'a eklenmelidir
6. **Paket Entegrasyonu:** Modül tüm paketlere eklenmeli ve yetkiler tanımlanmalıdır
7. **Permission:** Permission kayıtları tüm tenant'larda oluşturulmalıdır
8. **Modül ve Paket Entegrasyonu:** Her modül hem modül sistemine hem de paket sistemine entegre edilmelidir

---

## 🔄 Modül Ekleme Sırası

1. Model ve Form oluştur
2. View ve URL tanımla
3. Template'leri oluştur (CSS standartlarına uy)
4. Context processor'ı güncelle
5. Module kaydı yap (public schema)
6. Paket entegrasyonu yap (tüm paketlere ekle)
7. Permission kayıtları oluştur (tenant schema)
8. **Admin rolüne otomatik yetki atama ekle** (ZORUNLU)
9. Sidebar'a ekle
10. Test et

---

## ✅ Son Kontrol Listesi

- [ ] Tüm checklist maddeleri tamamlandı
- [ ] CSS standartlarına uyuldu
- [ ] Sidebar'a eklendi
- [ ] Yetki sistemi entegre edildi
- [ ] **Admin rolüne otomatik yetki atama eklendi** (ZORUNLU)
- [ ] Paket yönetimi entegre edildi
- [ ] Modül kayıt sistemi entegre edildi
- [ ] Migration'lar uygulandı
- [ ] Test edildi

---

**📅 Son Güncelleme:** 2025-01-XX  
**👤 Geliştirici:** AI Assistant  
**📝 Versiyon:** 1.0.0

