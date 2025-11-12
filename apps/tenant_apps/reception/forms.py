"""
Resepsiyon Modülü Forms
Rezervasyon formları
"""
from django import forms
from django.core.validators import MinValueValidator
from .models import Reservation, ReservationStatus, ReservationSource


class ReservationForm(forms.ModelForm):
    """Rezervasyon Formu"""
    
    class Meta:
        model = Reservation
        fields = [
            'hotel', 'room', 'room_number', 'customer',
            'check_in_date', 'check_out_date', 'check_in_time', 'check_out_time',
            'adult_count', 'child_count', 'child_ages',
            'status', 'source',
            'room_rate', 'discount_amount', 'tax_amount', 'currency',
            'special_requests', 'internal_notes'
        ]
        widgets = {
            'hotel': forms.Select(attrs={'class': 'form-control'}),
            'room': forms.Select(attrs={'class': 'form-control'}),
            'room_number': forms.Select(attrs={'class': 'form-control'}),
            'customer': forms.Select(attrs={'class': 'form-control'}),
            'check_in_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'check_out_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'check_in_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'check_out_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'adult_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'child_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'source': forms.Select(attrs={'class': 'form-control'}),
            'room_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'tax_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'special_requests': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'internal_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import Room, RoomNumber
            self.fields['room'].queryset = Room.objects.filter(hotel=hotel, is_active=True, is_deleted=False)
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel, is_active=True, is_deleted=False
            ).order_by('number')
            
            from apps.tenant_apps.core.models import Customer
            self.fields['customer'].queryset = Customer.objects.filter(
                hotel=hotel, is_active=True
            ).order_by('first_name', 'last_name')
    
    def clean(self):
        cleaned_data = super().clean()
        check_in_date = cleaned_data.get('check_in_date')
        check_out_date = cleaned_data.get('check_out_date')
        
        if check_in_date and check_out_date:
            if check_out_date <= check_in_date:
                raise forms.ValidationError('Check-out tarihi check-in tarihinden sonra olmalıdır.')
        
        return cleaned_data

