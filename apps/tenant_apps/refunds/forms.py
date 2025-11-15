"""
İade Yönetimi Formları
"""
from django import forms
from .models import RefundPolicy, RefundRequest, RefundTransaction


class RefundPolicyForm(forms.ModelForm):
    """İade Politikası Formu"""
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel Politika (Tüm Oteller) ---',
        widget=forms.Select(attrs={'class': 'form-control'}),
        help_text='Boş bırakılırsa tüm oteller için genel politika olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = RefundPolicy
        fields = ['name', 'code', 'hotel', 'module', 'description', 'policy_type', 'refund_percentage',
                 'refund_amount', 'days_before_start', 'days_after_booking', 'max_refund_days',
                 'refund_method', 'processing_fee_percentage', 'processing_fee_amount',
                 'is_active', 'is_default', 'priority', 'custom_rules', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'module': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'policy_type': forms.Select(attrs={'class': 'form-control'}),
            'refund_percentage': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0', 'max': '100'}),
            'refund_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'days_before_start': forms.NumberInput(attrs={'class': 'form-control'}),
            'days_after_booking': forms.NumberInput(attrs={'class': 'form-control'}),
            'max_refund_days': forms.NumberInput(attrs={'class': 'form-control'}),
            'refund_method': forms.Select(attrs={'class': 'form-control'}),
            'processing_fee_percentage': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0', 'max': '100'}),
            'processing_fee_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_default': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'priority': forms.NumberInput(attrs={'class': 'form-control'}),
            'custom_rules': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
        }
        labels = {
            'name': 'Politika Adı',
            'code': 'Politika Kodu',
            'module': 'Modül',
            'description': 'Açıklama',
            'policy_type': 'İade Tipi',
            'refund_percentage': 'İade Yüzdesi (%)',
            'refund_amount': 'Sabit İade Tutarı',
            'days_before_start': 'Başlangıçtan Kaç Gün Önce',
            'days_after_booking': 'Rezervasyondan Kaç Gün Sonra',
            'max_refund_days': 'Maksimum İade Süresi (Gün)',
            'refund_method': 'İade Yöntemi',
            'processing_fee_percentage': 'İşlem Ücreti (%)',
            'processing_fee_amount': 'Sabit İşlem Ücreti',
            'is_active': 'Aktif mi?',
            'is_default': 'Varsayılan Politika mı?',
            'priority': 'Öncelik',
            'custom_rules': 'Özel Kurallar (JSON)',
            'sort_order': 'Sıralama',
        }


class RefundRequestForm(forms.ModelForm):
    """İade Talebi Formu"""
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel Talep ---',
        widget=forms.Select(attrs={'class': 'form-control'}),
        help_text='Boş bırakılırsa genel iade talebi olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = RefundRequest
        fields = ['source_module', 'source_id', 'source_reference', 'hotel', 'customer_name',
                 'customer_email', 'customer_phone', 'original_amount', 'original_payment_method',
                 'original_payment_date', 'refund_policy', 'refund_method', 'reason',
                 'customer_notes', 'status']
        widgets = {
            'source_module': forms.TextInput(attrs={'class': 'form-control'}),
            'source_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'hotel': forms.Select(attrs={'class': 'form-control'}),
            'customer_name': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_email': forms.EmailInput(attrs={'class': 'form-control'}),
            'customer_phone': forms.TextInput(attrs={'class': 'form-control'}),
            'original_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'original_payment_method': forms.TextInput(attrs={'class': 'form-control'}),
            'original_payment_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'refund_policy': forms.Select(attrs={'class': 'form-control'}),
            'refund_method': forms.Select(attrs={'class': 'form-control'}),
            'reason': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'customer_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'status': forms.Select(attrs={'class': 'form-control'}),
        }
        labels = {
            'source_module': 'Kaynak Modül',
            'source_id': 'Kaynak ID',
            'source_reference': 'Kaynak Referans',
            'hotel': 'Otel',
            'customer_name': 'Müşteri Adı',
            'customer_email': 'Müşteri E-posta',
            'customer_phone': 'Müşteri Telefon',
            'original_amount': 'Orijinal Tutar',
            'original_payment_method': 'Orijinal Ödeme Yöntemi',
            'original_payment_date': 'Orijinal Ödeme Tarihi',
            'refund_policy': 'İade Politikası',
            'refund_method': 'İade Yöntemi',
            'reason': 'İade Nedeni',
            'customer_notes': 'Müşteri Notları',
            'status': 'Durum',
        }

