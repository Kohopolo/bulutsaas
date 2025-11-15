"""
Paket Yönetim Formları
"""
from django import forms
from .models import Package, PackageModule
from apps.ai.models import PackageAI


class PackageForm(forms.ModelForm):
    """Paket Formu"""
    class Meta:
        model = Package
        fields = ['name', 'code', 'description', 'sort_order', 'price_monthly', 'price_yearly', 
                 'currency', 'trial_days',
                 'is_active', 'is_featured', 'is_deleted', 'settings']
        labels = {
            'name': 'Paket Adı',
            'code': 'Paket Kodu',
            'description': 'Açıklama',
            'sort_order': 'Sıralama',
            'price_monthly': 'Aylık Fiyat',
            'price_yearly': 'Yıllık Fiyat',
            'currency': 'Para Birimi',
            'trial_days': 'Deneme Süresi (Gün)',
            'is_active': 'Aktif mi?',
            'is_featured': 'Öne Çıkan mı?',
            'is_deleted': 'Silinmiş mi?',
            'settings': 'Ek Ayarlar (JSON)',
        }
        help_texts = {
            'name': 'Paket adı (örn: Temel Paket, Profesyonel Paket)',
            'code': 'Benzersiz paket kodu (otomatik oluşturulur)',
            'description': 'Paket hakkında açıklama',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
            'price_monthly': 'Aylık abonelik fiyatı',
            'price_yearly': 'Yıllık abonelik fiyatı (opsiyonel)',
            'currency': 'Para birimi (TRY, USD, EUR)',
            'trial_days': 'Deneme süresi gün sayısı',
            'is_active': 'Bu paket aktif mi?',
            'is_featured': 'Bu paket öne çıkan paket mi?',
            'is_deleted': 'Bu paket silinmiş mi? (Soft delete)',
            'settings': 'Ek ayarlar JSON formatında',
        }


class PackageModuleForm(forms.ModelForm):
    """Paket Modül Formu"""
    class Meta:
        model = PackageModule
        fields = ['package', 'module', 'permissions', 'limits', 'is_enabled', 'is_required']
        labels = {
            'package': 'Paket',
            'module': 'Modül',
            'permissions': 'Yetkiler (JSON)',
            'limits': 'Limitler (JSON)',
            'is_enabled': 'Aktif mi?',
            'is_required': 'Zorunlu mu?',
        }
        help_texts = {
            'package': 'Hangi pakete bu modül eklenecek?',
            'module': 'Hangi modül eklenecek?',
            'permissions': 'Modül yetkileri JSON formatında (örn: {"view": true, "add": true})',
            'limits': 'Modül limitleri JSON formatında. Hotels modülü için örnek: {"max_hotels": 5, "max_room_numbers": 100, "max_users": 10, "max_reservations": 500, "max_ai_credits": 1000}. Tur modülü için örnek: {"max_tours": 100}',
            'is_enabled': 'Bu modül bu pakette aktif mi?',
            'is_required': 'Bu modül bu pakette zorunlu mu?',
        }
        widgets = {
            'limits': forms.Textarea(attrs={'rows': 4, 'placeholder': '{"max_hotels": 5, "max_room_numbers": 100, "max_users": 10, "max_reservations": 500, "max_ai_credits": 1000}'}),
        }


class PackageAIInlineForm(forms.ModelForm):
    """Paket AI Inline Formu"""
    class Meta:
        model = PackageAI
        fields = ['ai_provider', 'ai_model', 'monthly_credit_limit', 'credit_renewal_type', 'is_enabled']
        labels = {
            'ai_provider': 'AI Sağlayıcı',
            'ai_model': 'AI Model',
            'monthly_credit_limit': 'Aylık Kredi Limiti',
            'credit_renewal_type': 'Kredi Yenileme Tipi',
            'is_enabled': 'Aktif mi?',
        }
        help_texts = {
            'ai_provider': 'AI sağlayıcıyı seçin',
            'ai_model': 'AI modelini seçin (sağlayıcıya göre filtrelenir)',
            'monthly_credit_limit': 'Aylık kredi limiti (-1 = sınırsız)',
            'credit_renewal_type': 'Kredilerin ne zaman yenileneceği (Aylık/Yıllık)',
            'is_enabled': 'Bu AI yapılandırması aktif mi?',
        }


class PackageModuleInlineForm(forms.ModelForm):
    """Paket Modül Inline Formu"""
    class Meta:
        model = PackageModule
        fields = ['module', 'permissions', 'limits', 'is_enabled', 'is_required']
        labels = {
            'module': 'Modül',
            'permissions': 'Yetkiler (JSON)',
            'limits': 'Limitler (JSON)',
            'is_enabled': 'Aktif mi?',
            'is_required': 'Zorunlu mu?',
        }
        help_texts = {
            'module': 'Hangi modül eklenecek?',
            'permissions': 'Modül yetkileri JSON formatında (örn: {"view": true, "add": true})',
            'limits': 'Modül limitleri JSON formatında. Hotels modülü için örnek: {"max_hotels": 5, "max_room_numbers": 100, "max_users": 10, "max_reservations": 500, "max_ai_credits": 1000}. Tur modülü için örnek: {"max_tours": 100}. Reception modülü için örnek: {"max_reservations": 1000, "max_reservations_per_month": 100}',
            'is_enabled': 'Bu modül bu pakette aktif mi?',
            'is_required': 'Bu modül bu pakette zorunlu mu?',
        }
        widgets = {
            'limits': forms.Textarea(attrs={'rows': 3, 'placeholder': '{"max_hotels": 5, "max_room_numbers": 100, "max_users": 10, "max_reservations": 500, "max_ai_credits": 1000}'}),
            'permissions': forms.Textarea(attrs={'rows': 2, 'placeholder': '{"view": true, "add": true, "edit": false, "delete": false}'}),
            'module': forms.Select(attrs={'class': 'form-control'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # Tüm aktif modülleri göster
        from apps.modules.models import Module
        self.fields['module'].queryset = Module.objects.filter(is_active=True).order_by('sort_order', 'name')
        
        # JSONField'lar için varsayılan değerleri string'e çevir (eğer instance varsa)
        if self.instance and self.instance.pk:
            import json
            if self.instance.permissions:
                self.initial['permissions'] = json.dumps(self.instance.permissions, ensure_ascii=False, indent=2)
            if self.instance.limits:
                self.initial['limits'] = json.dumps(self.instance.limits, ensure_ascii=False, indent=2)
    
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
