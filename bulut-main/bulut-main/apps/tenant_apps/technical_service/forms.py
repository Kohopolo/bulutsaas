"""
Teknik Servis Modülü Forms
"""
from django import forms
from .models import MaintenanceRequest, MaintenanceRecord, Equipment, TechnicalServiceSettings


class MaintenanceRequestForm(forms.ModelForm):
    """Bakım Talebi Formu"""
    class Meta:
        model = MaintenanceRequest
        fields = ['room_number', 'request_type', 'priority', 'description', 'assigned_to', 'estimated_cost']
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'request_type': forms.Select(attrs={'class': 'form-control'}),
            'priority': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
            'estimated_cost': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('number')


class MaintenanceRecordForm(forms.ModelForm):
    """Bakım Kaydı Formu"""
    class Meta:
        model = MaintenanceRecord
        fields = ['request', 'equipment_name', 'equipment_type', 'maintenance_type', 'performed_by', 'performed_at', 'description', 'parts_used', 'cost', 'next_maintenance_date']
        widgets = {
            'request': forms.Select(attrs={'class': 'form-control'}),
            'equipment_name': forms.TextInput(attrs={'class': 'form-control'}),
            'equipment_type': forms.Select(attrs={'class': 'form-control'}),
            'maintenance_type': forms.Select(attrs={'class': 'form-control'}),
            'performed_by': forms.Select(attrs={'class': 'form-control'}),
            'performed_at': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'parts_used': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'cost': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'next_maintenance_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
        }


class EquipmentForm(forms.ModelForm):
    """Ekipman Formu"""
    class Meta:
        model = Equipment
        fields = ['room_number', 'name', 'equipment_type', 'brand', 'model', 'serial_number', 'purchase_date', 'warranty_expiry', 'status', 'next_maintenance_date', 'notes']
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'equipment_type': forms.Select(attrs={'class': 'form-control'}),
            'brand': forms.TextInput(attrs={'class': 'form-control'}),
            'model': forms.TextInput(attrs={'class': 'form-control'}),
            'serial_number': forms.TextInput(attrs={'class': 'form-control'}),
            'purchase_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'warranty_expiry': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'next_maintenance_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('number')


class TechnicalServiceSettingsForm(forms.ModelForm):
    """Teknik Servis Ayarları Formu"""
    class Meta:
        model = TechnicalServiceSettings
        fields = ['auto_assign_requests', 'default_priority', 'maintenance_reminder_days']
        widgets = {
            'auto_assign_requests': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'default_priority': forms.Select(attrs={'class': 'form-control'}),
            'maintenance_reminder_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
        }

