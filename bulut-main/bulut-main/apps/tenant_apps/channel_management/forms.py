"""
Kanal Yönetimi Forms
Rezervasyon form standartlarına uygun form yapısı
"""
from django import forms
from django.core.validators import MinValueValidator, MaxValueValidator
from decimal import Decimal
from .models import (
    ChannelConfiguration, ChannelReservation,
    ChannelPricing, ChannelCommission
)
from apps.modules.models import ChannelTemplate  # Public schema'dan import
from apps.tenants.models import Tenant
from apps.tenant_apps.hotels.models import Hotel, Room


class ChannelConfigurationForm(forms.ModelForm):
    """Kanal Konfigürasyon Formu"""
    
    class Meta:
        model = ChannelConfiguration
        fields = [
            'template', 'hotel', 'name', 'api_credentials', 'api_endpoint',
            'api_timeout', 'api_retry_count', 'sync_enabled', 'sync_interval',
            'auto_sync_pricing', 'auto_sync_availability',
            'price_markup_percent', 'price_markup_amount',
            'commission_rate', 'commission_calculation',
            'auto_confirm_reservations', 'reservation_timeout',
            'allow_modifications', 'allow_cancellations',
            'is_active', 'is_test_mode', 'notes'
        ]
        widgets = {
            'template': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_template'
            }),
            'hotel': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_hotel'
            }),
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'api_credentials': forms.HiddenInput(attrs={
                'id': 'id_api_credentials',
            }),
            'api_endpoint': forms.URLInput(attrs={
                'class': 'form-control',
                'id': 'id_api_endpoint'
            }),
            'api_timeout': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 300,
                'id': 'id_api_timeout'
            }),
            'api_retry_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'max': 10,
                'id': 'id_api_retry_count'
            }),
            'sync_interval': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 1440,
                'id': 'id_sync_interval'
            }),
            'price_markup_percent': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_price_markup_percent'
            }),
            'price_markup_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_price_markup_amount'
            }),
            'commission_rate': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'max': 100,
                'id': 'id_commission_rate'
            }),
            'commission_calculation': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_commission_calculation'
            }),
            'reservation_timeout': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 1440,
                'id': 'id_reservation_timeout'
            }),
            'sync_enabled': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_sync_enabled'
            }),
            'auto_sync_pricing': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_auto_sync_pricing'
            }),
            'auto_sync_availability': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_auto_sync_availability'
            }),
            'auto_confirm_reservations': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_auto_confirm_reservations'
            }),
            'allow_modifications': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_allow_modifications'
            }),
            'allow_cancellations': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_allow_cancellations'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_test_mode': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_test_mode'
            }),
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_notes'
            }),
        }
    
    def __init__(self, *args, **kwargs):
        tenant = kwargs.pop('tenant', None)
        super().__init__(*args, **kwargs)
        
        if tenant:
            # Sadece aktif şablonları göster
            self.fields['template'].queryset = ChannelTemplate.objects.filter(
                is_active=True,
                is_deleted=False
            ).order_by('sort_order', 'name')
            
            # Sadece bu tenant'ın otellerini göster (django-tenants kullanıldığı için tenant field'ı yok)
            self.fields['hotel'].queryset = Hotel.objects.filter(
                is_deleted=False
            ).order_by('name')
            
            # Hotel alanını opsiyonel yap
            self.fields['hotel'].required = False
    
    def clean_api_credentials(self):
        """API credentials JSON formatını kontrol et"""
        import json
        credentials = self.cleaned_data.get('api_credentials')
        if credentials:
            try:
                if isinstance(credentials, str):
                    # String ise JSON'a parse et
                    parsed = json.loads(credentials)
                    return parsed
                elif isinstance(credentials, dict):
                    # Dict ise olduğu gibi döndür
                    return credentials
                else:
                    raise forms.ValidationError('API bilgileri geçerli bir JSON formatında olmalıdır.')
            except (json.JSONDecodeError, TypeError) as e:
                raise forms.ValidationError('API bilgileri geçerli bir JSON formatında olmalıdır.')
        return credentials or {}
    
    def clean_commission_rate(self):
        """Komisyon oranını kontrol et"""
        rate = self.cleaned_data.get('commission_rate')
        if rate and (rate < 0 or rate > 100):
            raise forms.ValidationError('Komisyon oranı 0-100 arasında olmalıdır.')
        return rate


class ChannelReservationForm(forms.ModelForm):
    """Kanal Rezervasyon Formu"""
    
    class Meta:
        model = ChannelReservation
        fields = [
            'configuration', 'guest_name', 'guest_email', 'guest_phone',
            'check_in_date', 'check_out_date', 'adult_count', 'child_count',
            'room_type_name', 'room_number', 'total_amount', 'currency',
            'status', 'special_requests', 'notes'
        ]
        widgets = {
            'configuration': forms.Select(attrs={'class': 'form-control'}),
            'guest_name': forms.TextInput(attrs={'class': 'form-control'}),
            'guest_email': forms.EmailInput(attrs={'class': 'form-control'}),
            'guest_phone': forms.TextInput(attrs={'class': 'form-control'}),
            'check_in_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'check_out_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'adult_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'child_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0
            }),
            'room_type_name': forms.TextInput(attrs={'class': 'form-control'}),
            'room_number': forms.TextInput(attrs={'class': 'form-control'}),
            'total_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0
            }),
            'currency': forms.TextInput(attrs={
                'class': 'form-control',
                'maxlength': 3
            }),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'special_requests': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3
            }),
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3
            }),
        }
    
    def __init__(self, *args, **kwargs):
        tenant = kwargs.pop('tenant', None)
        super().__init__(*args, **kwargs)
        
        if tenant:
            # Sadece bu tenant'ın konfigürasyonlarını göster
            self.fields['configuration'].queryset = ChannelConfiguration.objects.filter(
                tenant=tenant,
                is_active=True,
                is_deleted=False
            ).order_by('name')


class ChannelPricingForm(forms.ModelForm):
    """Kanal Fiyat Formu"""
    
    class Meta:
        model = ChannelPricing
        fields = [
            'configuration', 'room', 'start_date', 'end_date',
            'base_price', 'channel_price', 'currency',
            'availability', 'min_stay', 'max_stay',
            'cancellation_policy', 'free_cancellation_until',
            'is_active'
        ]
        widgets = {
            'configuration': forms.Select(attrs={'class': 'form-control'}),
            'room': forms.Select(attrs={'class': 'form-control'}),
            'start_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'end_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'base_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0
            }),
            'channel_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0
            }),
            'currency': forms.TextInput(attrs={
                'class': 'form-control',
                'maxlength': 3
            }),
            'availability': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0
            }),
            'min_stay': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'max_stay': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'cancellation_policy': forms.TextInput(attrs={'class': 'form-control'}),
            'free_cancellation_until': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        tenant = kwargs.pop('tenant', None)
        super().__init__(*args, **kwargs)
        
        if tenant:
            # Sadece bu tenant'ın konfigürasyonlarını göster
            self.fields['configuration'].queryset = ChannelConfiguration.objects.filter(
                tenant=tenant,
                is_active=True,
                is_deleted=False
            ).order_by('name')
            
            # Sadece bu tenant'ın odalarını göster (django-tenants kullanıldığı için hotel__tenant yok)
            self.fields['room'].queryset = Room.objects.filter(
                is_deleted=False
            ).order_by('name')
    
    def clean(self):
        cleaned_data = super().clean()
        start_date = cleaned_data.get('start_date')
        end_date = cleaned_data.get('end_date')
        
        if start_date and end_date and start_date >= end_date:
            raise forms.ValidationError({
                'end_date': 'Bitiş tarihi başlangıç tarihinden sonra olmalıdır.'
            })
        
        return cleaned_data

