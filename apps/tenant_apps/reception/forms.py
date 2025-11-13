"""
Resepsiyon Modülü Forms
Rezervasyon formları - Kapsamlı popup form
"""
from django import forms
from django.core.validators import MinValueValidator
from decimal import Decimal
from .models import (
    Reservation, ReservationStatus, ReservationSource,
    ReservationGuest, ReservationPayment
)


class ReservationForm(forms.ModelForm):
    """Rezervasyon Formu - Popup Modal için"""
    
    # Müşteri Arama (TC No, Email, Telefon)
    customer_search = forms.CharField(
        label='Müşteri Ara',
        required=False,
        widget=forms.TextInput(attrs={
            'class': 'form-control',
            'placeholder': 'TC No, Email veya Telefon ile ara...',
            'id': 'customer_search'
        })
    )
    
    # Ön Ödeme
    advance_payment = forms.DecimalField(
        label='Ön Ödeme',
        required=False,
        max_digits=12,
        decimal_places=2,
        initial=Decimal('0'),
        widget=forms.NumberInput(attrs={
            'class': 'form-control',
            'step': '0.01',
            'min': 0,
            'id': 'advance_payment'
        })
    )
    
    payment_method = forms.ChoiceField(
        label='Ödeme Yöntemi',
        required=False,
        choices=[
            ('', 'Seçiniz...'),
            ('cash', 'Nakit'),
            ('credit_card', 'Kredi Kartı'),
            ('debit_card', 'Banka Kartı'),
            ('transfer', 'Havale/EFT'),
            ('check', 'Çek'),
        ],
        widget=forms.Select(attrs={
            'class': 'form-control',
            'id': 'payment_method'
        })
    )
    
    class Meta:
        model = Reservation
        fields = [
            'hotel', 'room', 'room_number', 'customer',
            'check_in_date', 'check_out_date', 'check_in_time', 'check_out_time',
            'adult_count', 'child_count', 'child_ages',
            'status', 'source', 'reservation_agent', 'reservation_channel',
            'room_rate', 'is_manual_price', 'discount_type', 'discount_percentage', 
            'discount_amount', 'tax_amount', 'currency',
            'is_comp', 'is_no_show', 'no_show_reason',
            'early_check_in', 'late_check_out', 'early_check_in_fee', 'late_check_out_fee',
            'special_requests', 'internal_notes'
        ]
        widgets = {
            'hotel': forms.HiddenInput(),  # Otel zaten seçili
            'room': forms.Select(attrs={'class': 'form-control', 'id': 'id_room'}),
            'room_number': forms.Select(attrs={'class': 'form-control', 'id': 'id_room_number'}),
            'customer': forms.HiddenInput(),  # Müşteri arama ile seçilecek
            'check_in_date': forms.DateInput(format='%Y-%m-%d', attrs={
                'class': 'form-control',
                'type': 'date',
                'id': 'id_check_in_date'
            }),
            'check_out_date': forms.DateInput(format='%Y-%m-%d', attrs={
                'class': 'form-control',
                'type': 'date',
                'id': 'id_check_out_date'
            }),
            'check_in_time': forms.TimeInput(attrs={
                'class': 'form-control',
                'type': 'time',
                'id': 'id_check_in_time'
            }),
            'check_out_time': forms.TimeInput(attrs={
                'class': 'form-control',
                'type': 'time',
                'id': 'id_check_out_time'
            }),
            'adult_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'id': 'id_adult_count'
            }),
            'child_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_child_count'
            }),
            'child_ages': forms.HiddenInput(),  # JavaScript ile yönetilecek
            'status': forms.Select(attrs={'class': 'form-control', 'id': 'id_status'}),
            'source': forms.Select(attrs={'class': 'form-control', 'id': 'id_source'}),
            'reservation_agent': forms.Select(attrs={'class': 'form-control', 'id': 'id_reservation_agent'}),
            'reservation_channel': forms.Select(attrs={'class': 'form-control', 'id': 'id_reservation_channel'}),
            'room_rate': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_room_rate'
            }),
            'is_manual_price': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_manual_price'
            }),
            'discount_type': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_discount_type'
            }),
            'discount_percentage': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'max': 100,
                'id': 'id_discount_percentage'
            }),
            'discount_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_discount_amount'
            }),
            'tax_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_tax_amount'
            }),
            'currency': forms.Select(attrs={'class': 'form-control', 'id': 'id_currency'}),
            'is_comp': forms.CheckboxInput(attrs={'class': 'form-check-input', 'id': 'id_is_comp'}),
            'is_no_show': forms.CheckboxInput(attrs={'class': 'form-check-input', 'id': 'id_is_no_show'}),
            'no_show_reason': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 2,
                'id': 'id_no_show_reason'
            }),
            'early_check_in': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_early_check_in'
            }),
            'late_check_out': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_late_check_out'
            }),
            'early_check_in_fee': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_early_check_in_fee'
            }),
            'late_check_out_fee': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_late_check_out_fee'
            }),
            'special_requests': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_special_requests'
            }),
            'internal_notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_internal_notes'
            }),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from apps.tenant_apps.hotels.models import Room, RoomNumber
            self.fields['room'].queryset = Room.objects.filter(
                hotel=hotel, is_active=True, is_deleted=False
            ).order_by('name')
            self.fields['room_number'].queryset = RoomNumber.objects.filter(
                hotel=hotel, is_active=True, is_deleted=False
            ).order_by('number')
            
            from apps.tenant_apps.sales.models import Agency
            self.fields['reservation_agent'].queryset = Agency.objects.filter(
                hotel=hotel, is_active=True, is_deleted=False
            ).order_by('name')
            
            from apps.tenant_apps.channels.models import Channel
            self.fields['reservation_channel'].queryset = Channel.objects.filter(
                is_active=True, is_deleted=False
            ).order_by('name')
            
            # Otel bilgisini set et
            self.fields['hotel'].initial = hotel.id
        
        # Status ve Source varsayılan değerleri
        self.fields['status'].initial = ReservationStatus.PENDING
        self.fields['source'].initial = ReservationSource.DIRECT
        
        # Currency varsayılan
        self.fields['currency'].initial = 'TRY'
    
    def clean(self):
        cleaned_data = super().clean()
        check_in_date = cleaned_data.get('check_in_date')
        check_out_date = cleaned_data.get('check_out_date')
        
        if check_in_date and check_out_date:
            if check_out_date <= check_in_date:
                raise forms.ValidationError('Check-out tarihi check-in tarihinden sonra olmalıdır.')
        
        # Comp rezervasyon kontrolü
        if cleaned_data.get('is_comp') and cleaned_data.get('room_rate', 0) > 0:
            cleaned_data['room_rate'] = Decimal('0')
            cleaned_data['total_amount'] = Decimal('0')
        
        return cleaned_data


class ReservationGuestForm(forms.ModelForm):
    """Rezervasyon Misafir Formu (Formset için)"""
    
    class Meta:
        model = ReservationGuest
        fields = [
            'guest_type', 'guest_order', 'first_name', 'last_name', 'gender',
            'birth_date', 'age', 'tc_no', 'passport_no', 'passport_serial_no',
            'id_serial_no', 'nationality', 'email', 'phone'
        ]
        widgets = {
            'guest_type': forms.HiddenInput(),
            'guest_order': forms.HiddenInput(),
            'first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'gender': forms.Select(attrs={'class': 'form-control'}),
            'birth_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'age': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 120}),
            'tc_no': forms.TextInput(attrs={'class': 'form-control', 'maxlength': 11}),
            'passport_no': forms.TextInput(attrs={'class': 'form-control'}),
            'passport_serial_no': forms.TextInput(attrs={'class': 'form-control'}),
            'id_serial_no': forms.TextInput(attrs={'class': 'form-control'}),
            'nationality': forms.TextInput(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
        }


# Formset Factory
from django.forms import inlineformset_factory

ReservationGuestFormSet = inlineformset_factory(
    Reservation,
    ReservationGuest,
    form=ReservationGuestForm,
    extra=0,  # Sadece mevcut misafirleri göster, ekstra boş form ekleme
    can_delete=True,
    can_order=False,
    min_num=0,
    max_num=None,  # Sınırsız misafir
    fields=[
        'guest_type', 'guest_order', 'first_name', 'last_name', 'gender',
        'birth_date', 'age', 'tc_no', 'passport_no', 'passport_serial_no',
        'id_serial_no', 'nationality', 'email', 'phone'
    ]
)


class ReservationPaymentForm(forms.ModelForm):
    """Rezervasyon Ödeme Formu"""
    
    class Meta:
        model = ReservationPayment
        fields = [
            'payment_date', 'payment_amount', 'payment_method', 'payment_type',
            'currency', 'notes', 'receipt_no'
        ]
        widgets = {
            'payment_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date'
            }),
            'payment_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0.01
            }),
            'payment_method': forms.Select(attrs={'class': 'form-control'}),
            'payment_type': forms.Select(attrs={'class': 'form-control'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'receipt_no': forms.TextInput(attrs={'class': 'form-control'}),
        }


class VoucherTemplateForm(forms.ModelForm):
    """Voucher Şablon Formu"""
    
    class Meta:
        from .models import VoucherTemplate
        model = VoucherTemplate
        fields = [
            'name', 'code', 'description',
            'template_html', 'template_css',
            'is_active', 'is_default'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'template_html': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 20,
                'style': 'font-family: monospace;'
            }),
            'template_css': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 10,
                'style': 'font-family: monospace;'
            }),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_default': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def clean(self):
        cleaned_data = super().clean()
        is_default = cleaned_data.get('is_default')
        
        if is_default:
            # Varsayılan şablon sadece bir tane olabilir
            from .models import VoucherTemplate
            instance = self.instance
            existing_default = VoucherTemplate.objects.filter(
                is_default=True,
                is_active=True,
                is_deleted=False
            ).exclude(pk=instance.pk if instance.pk else None).first()
            
            if existing_default:
                existing_default.is_default = False
                existing_default.save()
        
        return cleaned_data
