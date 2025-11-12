"""
Satış Yönetimi Modülü Forms
"""
from django import forms
from .models import Agency, SalesRecord, SalesTarget, SalesSettings


class AgencyForm(forms.ModelForm):
    """Acente Formu"""
    class Meta:
        model = Agency
        fields = ['name', 'code', 'contact_person', 'email', 'phone', 'address', 'commission_rate', 'commission_type', 'is_active', 'contract_start_date', 'contract_end_date', 'notes']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'contact_person': forms.TextInput(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
            'address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'commission_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'commission_type': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'contract_start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'contract_end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class SalesRecordForm(forms.ModelForm):
    """Satış Kaydı Formu"""
    class Meta:
        model = SalesRecord
        fields = ['agency', 'sales_type', 'sales_date', 'sales_amount', 'currency', 'commission_amount', 'sales_person', 'notes']
        widgets = {
            'agency': forms.Select(attrs={'class': 'form-control'}),
            'sales_type': forms.Select(attrs={'class': 'form-control'}),
            'sales_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'sales_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'commission_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'sales_person': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            # Reception modülü kaldırıldı - reservation field'ı kaldırıldı
            # from apps.tenant_apps.reception.models import Reservation
            # self.fields['reservation'].queryset = Reservation.objects.filter(hotel=hotel, is_deleted=False).order_by('-check_in_date')
            self.fields['agency'].queryset = Agency.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('name')


class SalesTargetForm(forms.ModelForm):
    """Satış Hedefi Formu"""
    class Meta:
        model = SalesTarget
        fields = ['target_name', 'target_type', 'target_amount', 'target_count', 'period_type', 'start_date', 'end_date', 'assigned_to', 'is_active']
        widgets = {
            'target_name': forms.TextInput(attrs={'class': 'form-control'}),
            'target_type': forms.Select(attrs={'class': 'form-control'}),
            'target_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'target_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'period_type': forms.Select(attrs={'class': 'form-control'}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class SalesSettingsForm(forms.ModelForm):
    """Satış Yönetimi Ayarları Formu"""
    class Meta:
        model = SalesSettings
        fields = ['default_commission_rate', 'auto_calculate_commission', 'sales_target_enabled', 'default_target_period']
        widgets = {
            'default_commission_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'auto_calculate_commission': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sales_target_enabled': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'default_target_period': forms.Select(attrs={'class': 'form-control'}),
        }

