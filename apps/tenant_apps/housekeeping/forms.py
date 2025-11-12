"""
Kat Hizmetleri Modülü Forms
"""
from django import forms
from django.core.validators import MinValueValidator
from .models import (
    CleaningTask, CleaningChecklistItem, MissingItem,
    LaundryItem, MaintenanceRequest, HousekeepingSettings
)


# ==================== TEMİZLİK GÖREVİ FORMS ====================

class CleaningTaskForm(forms.ModelForm):
    """Temizlik Görevi Formu"""
    
    class Meta:
        model = CleaningTask
        fields = [
            'room_number', 'task_type', 'priority',
            'assigned_to', 'scheduled_time', 'estimated_duration',
            'notes', 'special_instructions'
        ]
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'task_type': forms.Select(attrs={'class': 'form-control'}),
            'priority': forms.Select(attrs={'class': 'form-control'}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
            'scheduled_time': forms.DateTimeInput(attrs={
                'class': 'form-control',
                'type': 'datetime-local'
            }),
            'estimated_duration': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3
            }),
            'special_instructions': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3
            }),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('number')
            
            # Reception modülü kaldırıldı - reservation field'ı kaldırıldı
            # from apps.tenant_apps.reception.models import Reservation
            # self.fields['reservation'].queryset = Reservation.objects.filter(
            #     hotel=hotel,
            #     is_deleted=False
            # ).order_by('-check_in_date')


class CleaningTaskStatusForm(forms.ModelForm):
    """Temizlik Görevi Durum Formu"""
    
    class Meta:
        model = CleaningTask
        fields = ['status', 'notes']
        widgets = {
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class CleaningTaskInspectionForm(forms.ModelForm):
    """Temizlik Görevi Kontrol Formu"""
    
    class Meta:
        model = CleaningTask
        fields = ['inspection_passed', 'inspection_notes']
        widgets = {
            'inspection_passed': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'inspection_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


# ==================== KONTROL LİSTESİ FORMS ====================

class CleaningChecklistItemForm(forms.ModelForm):
    """Kontrol Listesi Öğesi Formu"""
    
    class Meta:
        model = CleaningChecklistItem
        fields = ['item_name', 'category', 'is_checked', 'notes']
        widgets = {
            'item_name': forms.TextInput(attrs={'class': 'form-control'}),
            'category': forms.Select(attrs={'class': 'form-control'}),
            'is_checked': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
        }


# ==================== EKSİK MALZEME FORMS ====================

class MissingItemForm(forms.ModelForm):
    """Eksik Malzeme Formu"""
    
    class Meta:
        model = MissingItem
        fields = ['room_number', 'task', 'item_name', 'item_category', 'quantity', 'notes']
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'task': forms.Select(attrs={'class': 'form-control'}),
            'item_name': forms.TextInput(attrs={'class': 'form-control'}),
            'item_category': forms.Select(attrs={'class': 'form-control'}),
            'quantity': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('number')


class MissingItemStatusForm(forms.ModelForm):
    """Eksik Malzeme Durum Formu"""
    
    class Meta:
        model = MissingItem
        fields = ['status', 'notes']
        widgets = {
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


# ==================== ÇAMAŞIR FORMS ====================

class LaundryItemForm(forms.ModelForm):
    """Çamaşır Öğesi Formu"""
    
    class Meta:
        model = LaundryItem
        fields = ['room_number', 'task', 'item_type', 'quantity', 'status', 'notes']
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'task': forms.Select(attrs={'class': 'form-control'}),
            'item_type': forms.Select(attrs={'class': 'form-control'}),
            'quantity': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('number')


# ==================== BAKIM TALEBİ FORMS ====================

class MaintenanceRequestForm(forms.ModelForm):
    """Bakım Talebi Formu"""
    
    class Meta:
        model = MaintenanceRequest
        fields = [
            'room_number', 'task', 'request_type', 'priority',
            'description', 'assigned_to'
        ]
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'task': forms.Select(attrs={'class': 'form-control'}),
            'request_type': forms.Select(attrs={'class': 'form-control'}),
            'priority': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('number')


# ==================== AYARLAR FORMS ====================

class HousekeepingSettingsForm(forms.ModelForm):
    """Kat Hizmetleri Ayarları Formu"""
    
    class Meta:
        model = HousekeepingSettings
        fields = [
            'default_cleaning_duration',
            'default_checkout_cleaning_duration',
            'default_deep_cleaning_duration',
            'require_inspection',
            'inspection_timeout',
            'notify_on_overdue',
            'notify_on_completion',
            'auto_assign_tasks',
            'auto_create_checkout_tasks',
        ]
        widgets = {
            'default_cleaning_duration': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'default_checkout_cleaning_duration': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'default_deep_cleaning_duration': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'require_inspection': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'inspection_timeout': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1
            }),
            'notify_on_overdue': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notify_on_completion': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'auto_assign_tasks': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'auto_create_checkout_tasks': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }

