"""
Kalite Kontrol Modülü Forms
"""
from django import forms
from .models import RoomQualityInspection, QualityChecklistItem, CustomerComplaint, QualityStandard, QualityControlSettings


class RoomQualityInspectionForm(forms.ModelForm):
    """Oda Kalite Kontrolü Formu"""
    class Meta:
        model = RoomQualityInspection
        fields = ['room_number', 'inspection_type', 'overall_score', 'cleanliness_score', 'maintenance_score', 'amenities_score', 'status', 'notes', 'action_required', 'action_taken']
        widgets = {
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'inspection_type': forms.Select(attrs={'class': 'form-control'}),
            'overall_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'cleanliness_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'maintenance_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'amenities_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'action_required': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'action_taken': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            from apps.tenant_apps.hotels.models import RoomNumber
            self.fields['room_number'].queryset = RoomNumber.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('number')
            # Reception modülü kaldırıldı - reservation field'ı kaldırıldı
            # from apps.tenant_apps.reception.models import Reservation
            # self.fields['reservation'].queryset = Reservation.objects.filter(hotel=hotel, is_deleted=False).order_by('-check_in_date')


class QualityChecklistItemForm(forms.ModelForm):
    """Kontrol Listesi Öğesi Formu"""
    class Meta:
        model = QualityChecklistItem
        fields = ['item_name', 'category', 'is_checked', 'score', 'notes']
        widgets = {
            'item_name': forms.TextInput(attrs={'class': 'form-control'}),
            'category': forms.Select(attrs={'class': 'form-control'}),
            'is_checked': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 10}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
        }


class CustomerComplaintForm(forms.ModelForm):
    """Müşteri Şikayeti Formu"""
    class Meta:
        model = CustomerComplaint
        fields = ['customer', 'complaint_type', 'priority', 'description']
        widgets = {
            'customer': forms.Select(attrs={'class': 'form-control'}),
            'complaint_type': forms.Select(attrs={'class': 'form-control'}),
            'priority': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            # Reception modülü kaldırıldı - reservation field'ı kaldırıldı
            # from apps.tenant_apps.reception.models import Reservation
            # self.fields['reservation'].queryset = Reservation.objects.filter(hotel=hotel, is_deleted=False).order_by('-check_in_date')
            from apps.tenant_apps.core.models import Customer
            self.fields['customer'].queryset = Customer.objects.filter(hotel=hotel, is_active=True).order_by('first_name', 'last_name')


class QualityStandardForm(forms.ModelForm):
    """Kalite Standardı Formu"""
    class Meta:
        model = QualityStandard
        fields = ['name', 'category', 'description', 'minimum_score', 'is_active']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'category': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'minimum_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class QualityControlSettingsForm(forms.ModelForm):
    """Kalite Kontrol Ayarları Formu"""
    class Meta:
        model = QualityControlSettings
        fields = ['require_pre_checkin_inspection', 'require_post_checkout_inspection', 'routine_inspection_frequency', 'minimum_overall_score', 'auto_escalate_low_scores']
        widgets = {
            'require_pre_checkin_inspection': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'require_post_checkout_inspection': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'routine_inspection_frequency': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'minimum_overall_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 100}),
            'auto_escalate_low_scores': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }

