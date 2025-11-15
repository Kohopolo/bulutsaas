from django.contrib import admin
from django import forms
from .models import PaymentGateway, TenantPaymentGateway, SuperAdminPaymentGateway, PaymentTransaction, PaymentWebhook
from .forms import PaymentGatewayForm, PaymentTransactionForm, PaymentWebhookForm


class TenantPaymentGatewayForm(forms.ModelForm):
    """Tenant Payment Gateway Form - Gateway'e göre dinamik alanlar"""
    
    class Meta:
        model = TenantPaymentGateway
        fields = '__all__'
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        # Gateway seçildiyse, o gateway'e özel alanları göster
        if self.instance and self.instance.gateway:
            gateway_code = self.instance.gateway.code
            
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
                self.fields['paytr_api_type'].label = 'API Tipi'
                self.fields['paytr_api_type'].help_text = 'PayTR için kullanılacak API tipi (Direkt API veya iFrame API)'
                self.fields['api_key'].widget = forms.HiddenInput()
            
            # NestPay için
            elif gateway_code in ['nestpay', 'garanti', 'akbank', 'isbank', 'ziraat', 'yapikredi']:
                self.fields['merchant_id'].label = 'Client ID / Store ID'
                self.fields['merchant_id'].help_text = 'Banka tarafından verilen Client ID veya Store ID'
                self.fields['store_key'].label = 'Store Key / Password'
                self.fields['store_key'].help_text = 'Banka tarafından verilen Store Key veya Password'
                self.fields['api_key'].widget = forms.HiddenInput()
                self.fields['secret_key'].widget = forms.HiddenInput()
                # PayTR dışındaki gateway'ler için paytr_api_type'ı gizle
                if 'paytr_api_type' in self.fields:
                    self.fields['paytr_api_type'].widget = forms.HiddenInput()
            
            # Diğer gateway'ler için genel
            else:
                self.fields['api_key'].label = 'API Key'
                self.fields['secret_key'].label = 'Secret Key'
                self.fields['merchant_id'].label = 'Merchant ID'
                self.fields['store_key'].label = 'Store Key'
                # PayTR dışındaki gateway'ler için paytr_api_type'ı gizle
                if 'paytr_api_type' in self.fields:
                    self.fields['paytr_api_type'].widget = forms.HiddenInput()


class SuperAdminPaymentGatewayForm(forms.ModelForm):
    """Super Admin Payment Gateway Form - Gateway'e göre dinamik alanlar"""
    
    class Meta:
        model = SuperAdminPaymentGateway
        fields = '__all__'
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        # Varsayılan label'ları ve help_text'leri ayarla
        self.fields['gateway'].label = 'Ödeme Gateway'
        self.fields['gateway'].help_text = 'Paket yenileme/yükseltme ödemeleri için kullanılacak gateway\'i seçin (Sadece Iyzico ve PayTR desteklenir)'
        
        self.fields['api_key'].label = 'API Key'
        self.fields['api_key'].help_text = 'Gateway API Key'
        
        self.fields['secret_key'].label = 'Secret Key'
        self.fields['secret_key'].help_text = 'Gateway Secret Key'
        
        self.fields['merchant_id'].label = 'Merchant ID'
        self.fields['merchant_id'].help_text = 'Gateway Merchant ID'
        
        self.fields['store_key'].label = 'Store Key'
        self.fields['store_key'].help_text = 'Gateway Store Key'
        
        self.fields['use_3d_secure'].label = '3D Secure Kullan'
        self.fields['use_3d_secure'].help_text = 'Güvenli ödeme için 3D Secure kullanılsın mı?'
        
        self.fields['callback_url'].label = 'Callback URL'
        self.fields['callback_url'].help_text = 'Ödeme sonrası yönlendirilecek URL'
        
        self.fields['enable_installment'].label = 'Taksit Aktif'
        self.fields['enable_installment'].help_text = 'Taksitli ödeme seçeneği sunulsun mu?'
        
        self.fields['max_installment'].label = 'Maksimum Taksit'
        self.fields['max_installment'].help_text = 'En fazla kaç taksit yapılabileceği'
        
        self.fields['is_active'].label = 'Aktif mi?'
        self.fields['is_active'].help_text = 'Gateway aktif ve kullanılabilir durumda mı?'
        
        self.fields['is_test_mode'].label = 'Test Modu'
        self.fields['is_test_mode'].help_text = 'Test ortamında çalışır (gerçek ödeme alınmaz)'
        
        self.fields['paytr_api_type'].label = 'PayTR API Tipi'
        self.fields['paytr_api_type'].help_text = 'PayTR için kullanılacak API tipi. PayTR hesabınızda hangi API yetkisi varsa onu seçin: Direkt API (varsayılan) veya iFrame API'
        # Varsayılan değeri 'direct' yap
        if not self.instance.pk:
            self.fields['paytr_api_type'].initial = 'direct'
        
        self.fields['settings'].label = 'Ek Ayarlar'
        self.fields['settings'].help_text = 'Ek ayarlar (JSON formatında)'
        
        # Gateway seçildiyse, o gateway'e özel alanları göster
        # Yeni kayıt oluştururken instance.pk yok, gateway de seçilmemiş olabilir
        if self.instance and self.instance.pk and hasattr(self.instance, 'gateway_id') and self.instance.gateway_id:
            try:
                gateway_code = self.instance.gateway.code
            except:
                gateway_code = None
        else:
            gateway_code = None
        
        if gateway_code:
            
            # İyzico için
            if gateway_code == 'iyzico':
                self.fields['api_key'].label = 'API Key'
                self.fields['api_key'].help_text = 'İyzico API Key (sandbox-api.iyzipay.com\'dan alın)'
                self.fields['secret_key'].label = 'Secret Key'
                self.fields['secret_key'].help_text = 'İyzico Secret Key'
                self.fields['merchant_id'].widget = forms.HiddenInput()
                self.fields['store_key'].widget = forms.HiddenInput()
                # PayTR dışındaki gateway'ler için paytr_api_type'ı gizle
                if 'paytr_api_type' in self.fields:
                    self.fields['paytr_api_type'].widget = forms.HiddenInput()
            
            # PayTR için
            elif gateway_code == 'paytr':
                self.fields['merchant_id'].label = 'Merchant ID'
                self.fields['merchant_id'].help_text = 'PayTR Merchant ID'
                self.fields['secret_key'].label = 'Merchant Key'
                self.fields['secret_key'].help_text = 'PayTR Merchant Key'
                self.fields['store_key'].label = 'Merchant Salt'
                self.fields['store_key'].help_text = 'PayTR Merchant Salt'
                self.fields['paytr_api_type'].label = 'API Tipi'
                self.fields['paytr_api_type'].help_text = 'PayTR için kullanılacak API tipi (Direkt API veya iFrame API)'
                self.fields['api_key'].widget = forms.HiddenInput()
            else:
                # PayTR dışındaki gateway'ler için paytr_api_type'ı gizle
                if 'paytr_api_type' in self.fields:
                    self.fields['paytr_api_type'].widget = forms.HiddenInput()


@admin.register(PaymentGateway)
class PaymentGatewayAdmin(admin.ModelAdmin):
    form = PaymentGatewayForm
    list_display = ['name', 'code', 'gateway_type', 'is_active', 'is_test_mode', 'sort_order']
    list_filter = ['gateway_type', 'is_active', 'is_test_mode']
    search_fields = ['name', 'code']
    ordering = ['sort_order', 'name']
    readonly_fields = ['code']  # Code değiştirilemez
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'gateway_type', 'description')
        }),
        ('API Ayarları', {
            'fields': ('api_url', 'test_api_url'),
            'classes': ('collapse',)
        }),
        ('Özellikler', {
            'fields': ('supports_3d_secure', 'supports_installment', 'supports_refund', 'supports_recurring'),
            'description': 'Bu gateway\'in desteklediği özellikleri seçin: 3D Secure (güvenli ödeme), Taksit desteği, İade desteği, Otomatik ödeme desteği.'
        }),
        ('Durum', {
            'fields': ('is_active', 'is_test_mode', 'sort_order'),
            'description': 'Aktif: Gateway kullanılabilir durumda. Test Modu: Test ortamında çalışır (gerçek ödeme alınmaz).'
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )
    
    def get_readonly_fields(self, request, obj=None):
        if obj:  # Düzenleme modunda
            return self.readonly_fields + ['code']
        return self.readonly_fields


@admin.register(TenantPaymentGateway)
class TenantPaymentGatewayAdmin(admin.ModelAdmin):
    form = TenantPaymentGatewayForm
    list_display = ['tenant', 'gateway', 'is_active', 'is_test_mode', 'use_3d_secure', 'get_paytr_api_type']
    list_filter = ['gateway', 'is_active', 'is_test_mode', 'use_3d_secure']
    search_fields = ['tenant__name', 'gateway__name']
    raw_id_fields = ['tenant', 'gateway']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('tenant', 'gateway', 'is_active', 'is_test_mode')
        }),
        ('API Credentials', {
            'fields': ('api_key', 'secret_key', 'merchant_id', 'store_key', 'paytr_api_type'),
            'description': 'Gateway\'e özel API bilgilerini girin. Gateway seçildikten sonra hangi alanların doldurulması gerektiği gösterilecektir. PayTR API Tipi: PayTR için Direkt API veya iFrame API seçin.'
        }),
        ('3D Secure', {
            'fields': ('use_3d_secure', 'callback_url'),
            'description': '3D Secure: Güvenli ödeme için 3D Secure kullanılsın mı? Callback URL: Ödeme sonrası yönlendirilecek URL.'
        }),
        ('Taksit Ayarları', {
            'fields': ('enable_installment', 'max_installment'),
            'description': 'Taksit Aktif: Taksitli ödeme seçeneği sunulsun mu? Maksimum Taksit: En fazla kaç taksit yapılabileceği.'
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )
    
    def get_form(self, request, obj=None, **kwargs):
        form = super().get_form(request, obj, **kwargs)
        
        # Gateway seçildiyse bilgilendirme mesajı ekle
        if obj and obj.gateway:
            gateway_code = obj.gateway.code
            help_texts = {
                'iyzico': 'İyzico için API Key ve Secret Key gereklidir. Test modu için sandbox-api.iyzipay.com\'dan alın.',
                'paytr': 'PayTR için Merchant ID, Merchant Key ve Merchant Salt gereklidir.',
                'nestpay': 'NestPay için Client ID ve Store Key gereklidir. Banka tarafından verilen bilgileri girin.',
            }
            
            if gateway_code in help_texts:
                form.base_fields['api_key'].help_text = help_texts.get(gateway_code, '')
        
        return form
    
    def get_paytr_api_type(self, obj):
        """PayTR API Tipi gösterimi"""
        if obj.gateway and obj.gateway.code == 'paytr':
            return obj.get_paytr_api_type_display() if hasattr(obj, 'paytr_api_type') else '-'
        return '-'
    get_paytr_api_type.short_description = 'PayTR API Tipi'


@admin.register(SuperAdminPaymentGateway)
class SuperAdminPaymentGatewayAdmin(admin.ModelAdmin):
    form = SuperAdminPaymentGatewayForm
    list_display = ['gateway', 'is_active', 'is_test_mode', 'use_3d_secure', 'paytr_api_type']
    list_filter = ['gateway', 'is_active', 'is_test_mode', 'use_3d_secure']
    search_fields = ['gateway__name', 'gateway__code']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('gateway', 'is_active', 'is_test_mode'),
            'description': 'Paket yenileme/yükseltme ödemeleri için kullanılacak gateway ayarları.'
        }),
        ('API Credentials', {
            'fields': ('api_key', 'secret_key', 'merchant_id', 'store_key', 'paytr_api_type'),
            'description': 'Gateway\'e özel API bilgilerini girin. Gateway seçildikten sonra hangi alanların doldurulması gerektiği gösterilecektir. PayTR API Tipi: PayTR için Direkt API veya iFrame API seçin.'
        }),
        ('3D Secure', {
            'fields': ('use_3d_secure', 'callback_url'),
            'description': '3D Secure: Güvenli ödeme için 3D Secure kullanılsın mı? Callback URL: Ödeme sonrası yönlendirilecek URL.'
        }),
        ('Taksit Ayarları', {
            'fields': ('enable_installment', 'max_installment'),
            'description': 'Taksit Aktif: Taksitli ödeme seçeneği sunulsun mu? Maksimum Taksit: En fazla kaç taksit yapılabileceği.'
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )
    
    def get_form(self, request, obj=None, **kwargs):
        form = super().get_form(request, obj, **kwargs)
        
        # Gateway seçildiyse bilgilendirme mesajı ekle
        if obj and obj.gateway:
            gateway_code = obj.gateway.code
            help_texts = {
                'iyzico': 'İyzico için API Key ve Secret Key gereklidir. Test modu için sandbox-api.iyzipay.com\'dan alın.',
                'paytr': 'PayTR için Merchant ID, Merchant Key ve Merchant Salt gereklidir. API Tipi: PayTR hesabınızda hangi API yetkisi varsa onu seçin (Direkt API veya iFrame API).',
            }
            
            if gateway_code in help_texts:
                if 'api_key' in form.base_fields:
                    form.base_fields['api_key'].help_text = help_texts.get(gateway_code, '')
        
        # Yeni kayıt için PayTR API tipi varsayılanını 'direct' yap
        if not obj and 'paytr_api_type' in form.base_fields:
            form.base_fields['paytr_api_type'].initial = 'direct'
        
        return form


@admin.register(PaymentTransaction)
class PaymentTransactionAdmin(admin.ModelAdmin):
    form = PaymentTransactionForm
    list_display = ['transaction_id', 'tenant', 'gateway', 'amount', 'currency', 'status', 'source_module', 'payment_date', 'created_at']
    list_filter = ['status', 'gateway', 'currency', 'is_3d_secure', 'source_module', 'created_at']
    search_fields = ['transaction_id', 'order_id', 'reference_number', 'tenant__name', 'source_reference', 'customer_email', 'customer_name']
    readonly_fields = ['transaction_id', 'created_at', 'updated_at', 'gateway_response', 'cash_transaction_id', 'accounting_payment_id', 'sales_record_id', 'refund_transaction_id']
    raw_id_fields = ['tenant', 'gateway']
    date_hierarchy = 'created_at'
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('transaction_id', 'tenant', 'gateway', 'order_id', 'reference_number')
        }),
        ('Ödeme Bilgileri', {
            'fields': ('amount', 'currency', 'status', 'payment_method', 'installment_count')
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference'),
            'description': 'Bu ödeme işleminin hangi modülden geldiğini belirtir (reception, tours, sales, refunds vb.)'
        }),
        ('Entegrasyon ID\'leri', {
            'fields': ('cash_transaction_id', 'accounting_payment_id', 'sales_record_id', 'refund_transaction_id'),
            'description': 'Diğer modüllerle entegrasyon ID\'leri (otomatik oluşturulur)',
            'classes': ('collapse',)
        }),
        ('Kart Bilgileri', {
            'fields': ('card_bin', 'card_last_four', 'card_type', 'is_3d_secure', 'md_status'),
            'classes': ('collapse',)
        }),
        ('Gateway Bilgileri', {
            'fields': ('gateway_transaction_id', 'gateway_response'),
            'classes': ('collapse',)
        }),
        ('Hata Bilgileri', {
            'fields': ('error_code', 'error_message'),
            'classes': ('collapse',)
        }),
        ('Müşteri Bilgileri', {
            'fields': ('customer_name', 'customer_surname', 'customer_email', 'customer_phone', 
                      'customer_address', 'customer_city', 'customer_country', 'customer_zip_code'),
            'classes': ('collapse',)
        }),
        ('Tarih', {
            'fields': ('payment_date', 'created_at', 'updated_at')
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
    )


@admin.register(PaymentWebhook)
class PaymentWebhookAdmin(admin.ModelAdmin):
    form = PaymentWebhookForm
    list_display = ['gateway', 'event_type', 'transaction', 'is_processed', 'created_at']
    list_filter = ['gateway', 'event_type', 'is_processed', 'created_at']
    search_fields = ['event_type', 'transaction__transaction_id']
    readonly_fields = ['created_at', 'updated_at', 'payload', 'headers']
    raw_id_fields = ['gateway', 'transaction']
    date_hierarchy = 'created_at'
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('gateway', 'transaction', 'event_type', 'is_processed')
        }),
        ('Webhook Data', {
            'fields': ('payload', 'headers'),
            'classes': ('collapse',)
        }),
        ('Hata', {
            'fields': ('processing_error',),
            'classes': ('collapse',)
        }),
        ('Tarih', {
            'fields': ('created_at', 'updated_at')
        }),
    )
