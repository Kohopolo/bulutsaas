"""
AI Yönetim Formları
"""
from django import forms
from .models import AIProvider, AIModel, PackageAI


class APIKeyForm(forms.Form):
    """API Key Formu"""
    api_key = forms.CharField(
        label='API Key',
        widget=forms.PasswordInput(attrs={'class': 'form-control', 'placeholder': 'API Key girin'}),
        required=False,
        help_text='API key şifreli olarak saklanacaktır.'
    )


class AIProviderForm(forms.ModelForm):
    """AI Sağlayıcı Formu"""
    class Meta:
        model = AIProvider
        fields = ['name', 'code', 'provider_type', 'description', 'icon', 'api_base_url', 
                 'api_key_label', 'is_active', 'sort_order', 'settings']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'provider_type': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control', 'placeholder': '🤖 veya fa-robot'}),
            'api_base_url': forms.URLInput(attrs={'class': 'form-control'}),
            'api_key_label': forms.TextInput(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
            'settings': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
        }
        labels = {
            'name': 'Sağlayıcı Adı',
            'code': 'Kod',
            'provider_type': 'Sağlayıcı Tipi',
            'description': 'Açıklama',
            'icon': 'İkon',
            'api_base_url': 'API Base URL',
            'api_key_label': 'API Key Etiketi',
            'is_active': 'Aktif mi?',
            'sort_order': 'Sıralama',
            'settings': 'Ayarlar (JSON)',
        }
        help_texts = {
            'name': 'AI sağlayıcısının adı (örn: OpenAI, Anthropic)',
            'code': 'Benzersiz kod (otomatik oluşturulur)',
            'provider_type': 'Sağlayıcı tipini seçin',
            'description': 'Sağlayıcı hakkında açıklama',
            'icon': 'Emoji (🤖) veya Font Awesome class (fa-robot)',
            'api_base_url': 'API endpoint URL (örn: https://api.openai.com/v1)',
            'api_key_label': 'Formda gösterilecek API key etiketi',
            'is_active': 'Bu sağlayıcı aktif mi?',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
            'settings': 'Ek ayarlar JSON formatında (timeout, retry vb.)',
        }


class AIModelForm(forms.ModelForm):
    """AI Model Formu"""
    class Meta:
        model = AIModel
        fields = ['provider', 'name', 'code', 'model_id', 'description', 'credit_cost',
                 'max_tokens', 'supports_streaming', 'supports_function_calling',
                 'is_active', 'is_default', 'sort_order', 'settings']
        widgets = {
            'provider': forms.Select(attrs={'class': 'form-control'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'model_id': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'gpt-4, claude-3-opus vb.'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'credit_cost': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.1', 'min': '0'}),
            'max_tokens': forms.NumberInput(attrs={'class': 'form-control'}),
            'supports_streaming': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'supports_function_calling': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_default': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
            'settings': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
        }
        labels = {
            'provider': 'AI Sağlayıcı',
            'name': 'Model Adı',
            'code': 'Kod',
            'model_id': 'Model ID',
            'description': 'Açıklama',
            'credit_cost': 'Kredi Maliyeti',
            'max_tokens': 'Maksimum Token',
            'supports_streaming': 'Streaming Desteği',
            'supports_function_calling': 'Function Calling Desteği',
            'is_active': 'Aktif mi?',
            'is_default': 'Varsayılan Model mi?',
            'sort_order': 'Sıralama',
            'settings': 'Ayarlar (JSON)',
        }
        help_texts = {
            'provider': 'Bu modelin ait olduğu AI sağlayıcıyı seçin',
            'name': 'Model adı (örn: GPT-4, Claude 3 Opus)',
            'code': 'Benzersiz kod (otomatik oluşturulur)',
            'model_id': 'API\'de kullanılan gerçek model ID (örn: gpt-4, claude-3-opus-20240229)',
            'description': 'Model hakkında açıklama',
            'credit_cost': 'Bu modeli kullanmak için düşülecek kredi miktarı (örn: 1.0, 2.5)',
            'max_tokens': 'Maksimum token sayısı (opsiyonel)',
            'supports_streaming': 'Bu model streaming destekliyor mu?',
            'supports_function_calling': 'Bu model function calling destekliyor mu?',
            'is_active': 'Bu model aktif mi?',
            'is_default': 'Bu sağlayıcı için varsayılan model mi? (Sadece bir tane olabilir)',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
            'settings': 'Model özel ayarları JSON formatında',
        }


class PackageAIForm(forms.ModelForm):
    """Paket AI Formu"""
    class Meta:
        model = PackageAI
        fields = ['package', 'ai_provider', 'ai_model', 'monthly_credit_limit', 'credit_renewal_type', 'is_enabled']
        widgets = {
            'package': forms.Select(attrs={'class': 'form-control'}),
            'ai_provider': forms.Select(attrs={'class': 'form-control'}),
            'ai_model': forms.Select(attrs={'class': 'form-control'}),
            'monthly_credit_limit': forms.NumberInput(attrs={'class': 'form-control', 'placeholder': '-1 = sınırsız'}),
            'credit_renewal_type': forms.Select(attrs={'class': 'form-control'}),
            'is_enabled': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
        labels = {
            'package': 'Paket',
            'ai_provider': 'AI Sağlayıcı',
            'ai_model': 'AI Model',
            'monthly_credit_limit': 'Aylık Kredi Limiti',
            'credit_renewal_type': 'Kredi Yenileme Tipi',
            'is_enabled': 'Aktif mi?',
        }
        help_texts = {
            'package': 'Hangi pakete bu AI tanımlanacak?',
            'ai_provider': 'AI sağlayıcıyı seçin',
            'ai_model': 'AI modelini seçin (sağlayıcıya göre filtrelenir)',
            'monthly_credit_limit': 'Paket ile birlikte verilen aylık kredi miktarı (-1 = sınırsız)',
            'credit_renewal_type': 'Kredilerin ne zaman yenileneceği (Aylık/Yıllık)',
            'is_enabled': 'Bu AI yapılandırması aktif mi?',
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # Provider seçildiğinde sadece o provider'ın modelleri gösterilsin (AJAX ile yapılabilir)
        if 'ai_provider' in self.data:
            try:
                provider_id = int(self.data.get('ai_provider'))
                self.fields['ai_model'].queryset = AIModel.objects.filter(provider_id=provider_id, is_active=True, is_deleted=False)
            except (ValueError, TypeError):
                pass
        elif self.instance.pk:
            self.fields['ai_model'].queryset = self.instance.ai_provider.models.filter(is_active=True, is_deleted=False)

