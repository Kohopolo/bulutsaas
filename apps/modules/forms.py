"""
Modül Yönetim Formları
"""
from django import forms
from .models import Module


class ModuleForm(forms.ModelForm):
    """Modül Formu"""
    class Meta:
        model = Module
        fields = ['name', 'code', 'description', 'icon', 'category', 'sort_order',
                 'app_name', 'url_prefix', 'available_permissions', 'is_active', 'is_core', 'settings']
        labels = {
            'name': 'Modül Adı',
            'code': 'Modül Kodu',
            'description': 'Açıklama',
            'icon': 'İkon',
            'category': 'Kategori',
            'sort_order': 'Sıralama',
            'app_name': 'Uygulama Adı',
            'url_prefix': 'URL Öneki',
            'available_permissions': 'Mevcut Yetkiler (JSON)',
            'is_active': 'Aktif mi?',
            'is_core': 'Çekirdek Modül mü?',
            'settings': 'Ayarlar (JSON)',
        }
        help_texts = {
            'name': 'Modül adı (örn: Tur Modülü, Rezervasyon Modülü)',
            'code': 'Benzersiz modül kodu (otomatik oluşturulur)',
            'description': 'Modül hakkında açıklama',
            'icon': 'Font Awesome icon class (örn: fa-route, fa-calendar)',
            'category': 'Modül kategorisi',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
            'app_name': 'Django app adı (örn: apps.tenant_apps.tours)',
            'url_prefix': 'URL öneki (örn: tours)',
            'available_permissions': 'Modülde kullanılabilir yetkiler JSON formatında',
            'is_active': 'Bu modül aktif mi?',
            'is_core': 'Bu modül çekirdek modül mü? (Tüm paketlerde zorunlu)',
            'settings': 'Modül özel ayarları JSON formatında',
        }

