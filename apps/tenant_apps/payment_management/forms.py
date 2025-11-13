"""
Ödeme Yönetimi Modülü Forms
"""
from django import forms
from apps.payments.models import PaymentGateway, TenantPaymentGateway
from apps.tenants.models import Tenant


class TenantPaymentGatewayForm(forms.ModelForm):
    """Tenant Gateway Yapılandırma Formu"""
    
    class Meta:
        model = TenantPaymentGateway
        fields = [
            'gateway', 'api_key', 'secret_key', 'merchant_id', 'store_key',
            'use_3d_secure', 'callback_url', 'enable_installment', 
            'max_installment', 'is_active', 'is_test_mode', 'settings'
        ]
        widgets = {
            'api_key': forms.TextInput(attrs={'class': 'form-control', 'type': 'password', 'autocomplete': 'off'}),
            'secret_key': forms.TextInput(attrs={'class': 'form-control', 'type': 'password', 'autocomplete': 'off'}),
            'merchant_id': forms.TextInput(attrs={'class': 'form-control'}),
            'store_key': forms.TextInput(attrs={'class': 'form-control', 'type': 'password', 'autocomplete': 'off'}),
            'gateway': forms.Select(attrs={'class': 'form-control'}),
            'use_3d_secure': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'callback_url': forms.URLInput(attrs={'class': 'form-control'}),
            'enable_installment': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'max_installment': forms.NumberInput(attrs={'class': 'form-control', 'min': 1, 'max': 12}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_test_mode': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'settings': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        tenant = kwargs.pop('tenant', None)
        super().__init__(*args, **kwargs)
        
        if tenant:
            # Sadece aktif gateway'leri göster
            self.fields['gateway'].queryset = PaymentGateway.objects.filter(
                is_active=True,
                is_deleted=False
            ).order_by('sort_order', 'name')
        
        # Gateway seçildiyse, o gateway'e özel alanları göster
        gateway = None
        
        # Önce instance'dan gateway'i almaya çalış (kaydedilmiş instance için)
        if self.instance and self.instance.pk:
            try:
                gateway = self.instance.gateway
            except (AttributeError, PaymentGateway.DoesNotExist):
                pass
        
        # Eğer instance'da gateway yoksa, initial değerden veya POST data'dan al
        if not gateway:
            gateway_id = None
            if self.initial.get('gateway'):
                gateway_id = self.initial.get('gateway')
            elif self.data.get('gateway'):
                gateway_id = self.data.get('gateway')
            
            if gateway_id:
                try:
                    gateway = PaymentGateway.objects.get(pk=gateway_id)
                except (PaymentGateway.DoesNotExist, ValueError, TypeError):
                    pass
        
        if gateway:
            gateway_code = gateway.code
            gateway_type = gateway.gateway_type
            
            # İyzico için
            if gateway_code == 'iyzico':
                self.fields['api_key'].label = 'API Key'
                self.fields['secret_key'].label = 'Secret Key'
                self.fields['api_key'].help_text = 'İyzico API Key (sandbox-api.iyzipay.com\'dan alın)'
                self.fields['secret_key'].help_text = 'İyzico Secret Key'
                self.fields['merchant_id'].widget = forms.HiddenInput()
                self.fields['store_key'].widget = forms.HiddenInput()
            
            # PayTR için
            elif gateway_code == 'paytr':
                self.fields['merchant_id'].label = 'Merchant ID'
                self.fields['merchant_id'].help_text = 'PayTR Merchant ID'
                self.fields['secret_key'].label = 'Merchant Key'
                self.fields['secret_key'].help_text = 'PayTR Merchant Key'
                self.fields['store_key'].label = 'Merchant Salt'
                self.fields['store_key'].help_text = 'PayTR Merchant Salt'
                self.fields['api_key'].widget = forms.HiddenInput()
            
            # NestPay ve Banka Sanal Pos için
            elif gateway_type in ['nestpay', 'garanti', 'isbank', 'akbank', 'ziraat', 
                                 'yapikredi', 'denizbank', 'halkbank', 'qnbfinansbank', 
                                 'teb', 'sekerbank', 'ingbank', 'vakifbank', 'fibabanka',
                                 'albaraka', 'kuveytturk', 'ziraatkatilim', 'vakifkatilim']:
                self.fields['merchant_id'].label = 'Client ID / Store ID'
                self.fields['merchant_id'].help_text = 'Banka tarafından verilen Client ID veya Store ID'
                self.fields['store_key'].label = 'Store Key / Password'
                self.fields['store_key'].help_text = 'Banka tarafından verilen Store Key veya Password'
                self.fields['api_key'].widget = forms.HiddenInput()
                self.fields['secret_key'].widget = forms.HiddenInput()
            
            # Diğer gateway'ler için genel
            else:
                self.fields['api_key'].label = 'API Key'
                self.fields['secret_key'].label = 'Secret Key'
                self.fields['merchant_id'].label = 'Merchant ID'
                self.fields['store_key'].label = 'Store Key'

