"""
Resepsiyon Modülü Forms
"""
from django import forms
from django.core.validators import MinValueValidator
from decimal import Decimal
from .models import (
    Reservation, CheckIn, CheckOut, KeyCard,
    ReceptionSession, ReceptionSettings, QuickAction
)


# ==================== REZERVASYON FORMS ====================

class ReservationForm(forms.ModelForm):
    """
    Rezervasyon Formu
    """
    # Müşteri seçimi (opsiyonel - yeni müşteri eklenebilir)
    customer_id = forms.IntegerField(
        required=False,
        widget=forms.HiddenInput()
    )
    
    # Çocuk yaşları (dinamik olarak eklenecek)
    child_ages_json = forms.CharField(
        required=False,
        widget=forms.HiddenInput(),
        help_text='JSON formatında çocuk yaşları: [5, 8, 12]'
    )
    
    class Meta:
        model = Reservation
        fields = [
            'room', 'check_in_date', 'check_out_date', 'check_in_time', 'check_out_time',
            'customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone',
            'customer_tc_no', 'customer_address',
            'adult_count', 'child_count', 'child_ages',
            'board_type',
            'base_price', 'adult_price', 'child_price', 'extra_services_total',
            'discount_amount', 'reception_discount_rate', 'reception_discount_amount', 'total_amount', 'currency',
            'source', 'agency', 'channel_id', 'channel_name',
            'is_web_booking', 'web_booking_reference',
            'is_complimentary', 'complimentary_reason',
            'is_guaranteed', 'guarantee_type', 'advance_payment', 'total_paid',
            'special_requests', 'notes', 'internal_notes'
        ]
        widgets = {
            'room': forms.Select(attrs={'class': 'form-select'}),
            'check_in_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'check_out_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'check_in_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'check_out_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'customer_first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_email': forms.EmailInput(attrs={'class': 'form-control'}),
            'customer_phone': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_tc_no': forms.TextInput(attrs={'class': 'form-control', 'maxlength': 11}),
            'customer_address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'adult_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'child_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'board_type': forms.Select(attrs={'class': 'form-select'}),
            'base_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True}),
            'adult_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True}),
            'child_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True}),
            'extra_services_total': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'reception_discount_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 100, 'placeholder': '0-100'}),
            'reception_discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'total_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True, 'id': 'id_total_amount'}),
            'currency': forms.Select(attrs={'class': 'form-select'}, choices=[('TRY', 'TRY'), ('USD', 'USD'), ('EUR', 'EUR')]),
            'source': forms.Select(attrs={'class': 'form-select'}),
            'agency': forms.Select(attrs={'class': 'form-select'}),
            'channel_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'channel_name': forms.TextInput(attrs={'class': 'form-control'}),
            'is_web_booking': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'web_booking_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'is_complimentary': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'complimentary_reason': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_guaranteed': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'guarantee_type': forms.Select(attrs={'class': 'form-select'}),
            'advance_payment': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'total_paid': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'internal_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        initial = kwargs.pop('initial', {})
        super().__init__(*args, initial=initial, **kwargs)
        
        if hotel:
            # Oda seçeneklerini otel bazlı filtrele
            from apps.tenant_apps.hotels.models import Room, BoardType
            self.fields['room'].queryset = Room.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('name')
            
            self.fields['board_type'].queryset = BoardType.objects.filter(
                hotel=hotel,
                is_active=True,
                is_deleted=False
            ).order_by('name')
        
        # Source alanını opsiyonel yap ve default değer ver
        self.fields['source'].required = False
        if not self.instance.pk:  # Yeni kayıt için
            self.fields['source'].initial = 'reception'
        
        # Advance payment alanını opsiyonel yap
        self.fields['advance_payment'].required = False
        if not self.instance.pk:  # Yeni kayıt için
            self.fields['advance_payment'].initial = 0
        
        # Total paid alanını opsiyonel yap
        self.fields['total_paid'].required = False
        if not self.instance.pk:  # Yeni kayıt için
            self.fields['total_paid'].initial = 0
        
        # Comp rezervasyon için onay gerektir
        if self.instance and self.instance.is_complimentary:
            self.fields['complimentary_reason'].required = True
    
    def clean(self):
        cleaned_data = super().clean()
        check_in_date = cleaned_data.get('check_in_date')
        check_out_date = cleaned_data.get('check_out_date')
        room = cleaned_data.get('room')
        adult_count = cleaned_data.get('adult_count', 0)
        child_count = cleaned_data.get('child_count', 0)
        child_ages_json = cleaned_data.get('child_ages_json')
        
        # Tarih kontrolü
        if check_in_date and check_out_date:
            if check_out_date <= check_in_date:
                raise forms.ValidationError('Çıkış tarihi giriş tarihinden sonra olmalıdır.')
        
        # Oda kapasitesi kontrolü
        if room:
            if adult_count > room.max_adults:
                raise forms.ValidationError(
                    f'Bu oda tipi maksimum {room.max_adults} yetişkin kabul edebilir. '
                    f'Girdiğiniz yetişkin sayısı: {adult_count}'
                )
            
            if child_count > room.max_children:
                raise forms.ValidationError(
                    f'Bu oda tipi maksimum {room.max_children} çocuk kabul edebilir. '
                    f'Girdiğiniz çocuk sayısı: {child_count}'
                )
            
            total_guests = adult_count + child_count
            if total_guests > room.max_total_capacity:
                raise forms.ValidationError(
                    f'Bu oda tipi maksimum {room.max_total_capacity} misafir kabul edebilir. '
                    f'Toplam misafir sayınız: {total_guests} (Yetişkin: {adult_count}, Çocuk: {child_count})'
                )
        
        # Çocuk yaş kontrolü
        if child_count > 0:
            import json
            try:
                if child_ages_json:
                    child_ages = json.loads(child_ages_json)
                else:
                    child_ages = cleaned_data.get('child_ages', [])
                
                if not child_ages or len(child_ages) != child_count:
                    raise forms.ValidationError('Çocuk sayısı kadar yaş bilgisi girilmelidir.')
                
                for age in child_ages:
                    if age <= 0:
                        raise forms.ValidationError('Çocuk yaşı 0\'dan büyük olmalıdır.')
                
                cleaned_data['child_ages'] = child_ages
            except (json.JSONDecodeError, TypeError):
                raise forms.ValidationError('Çocuk yaş bilgileri geçersiz format.')
        
        return cleaned_data


# ==================== CHECK-IN/OUT FORMS ====================

class CheckInForm(forms.ModelForm):
    """
    Check-In Formu
    """
    room_number_id = forms.IntegerField(
        required=False,
        widget=forms.HiddenInput()
    )
    
    class Meta:
        model = CheckIn
        fields = ['check_in_datetime', 'is_early_checkin', 'early_checkin_reason', 'notes']
        widgets = {
            'check_in_datetime': forms.DateTimeInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'type': 'datetime-local'}),
            'is_early_checkin': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'early_checkin_reason': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
        }


class CheckOutForm(forms.ModelForm):
    """
    Check-Out Formu
    """
    class Meta:
        model = CheckOut
        fields = [
            'check_out_datetime',
            'is_early_checkout', 'early_checkout_reason', 'early_checkout_fee',
            'is_late_checkout', 'late_checkout_reason', 'late_checkout_fee',
            'notes'
        ]
        widgets = {
            'check_out_datetime': forms.DateTimeInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'type': 'datetime-local'}),
            'is_early_checkout': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'early_checkout_reason': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'early_checkout_fee': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'step': '0.01', 'min': 0}),
            'is_late_checkout': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'late_checkout_reason': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'late_checkout_fee': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'step': '0.01', 'min': 0}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
        }


# ==================== DİJİTAL ANAHTAR FORMS ====================

class KeyCardForm(forms.ModelForm):
    """
    Anahtar Kartı Formu
    """
    class Meta:
        model = KeyCard
        fields = ['access_level', 'valid_from', 'valid_until', 'notes']
        widgets = {
            'access_level': forms.Select(attrs={'class': 'form-control'}),
            'valid_from': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'valid_until': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


# ==================== RESEPSİYON AYARLARI FORMS ====================

class ReceptionSettingsForm(forms.ModelForm):
    """
    Resepsiyon Ayarları Formu
    """
    class Meta:
        model = ReceptionSettings
        fields = [
            'default_checkin_time', 'default_checkout_time',
            'early_checkin_allowed', 'early_checkin_fee',
            'late_checkout_allowed', 'late_checkout_fee', 'late_checkout_hour_limit',
            'early_checkout_allowed', 'early_checkout_fee', 'early_checkout_refund_rate',
            'auto_checkout_time',
            'print_receipt_auto', 'print_keycard_auto',
            'require_payment_guarantee', 'default_guarantee_type',
            'allow_overbooking', 'max_overbooking_limit',
            'auto_generate_reservation_code', 'reservation_code_prefix'
        ]
        widgets = {
            'default_checkin_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'default_checkout_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'early_checkin_allowed': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'early_checkin_fee': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'late_checkout_allowed': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'late_checkout_fee': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'late_checkout_hour_limit': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 23}),
            'early_checkout_allowed': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'early_checkout_fee': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'early_checkout_refund_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 100}),
            'auto_checkout_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'print_receipt_auto': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'print_keycard_auto': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'require_payment_guarantee': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'default_guarantee_type': forms.Select(attrs={'class': 'form-control'}),
            'allow_overbooking': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'max_overbooking_limit': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'auto_generate_reservation_code': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'reservation_code_prefix': forms.TextInput(attrs={'class': 'form-control'}),
        }


# ==================== HIZLI İŞLEM ŞABLONLARI FORMS ====================

class QuickActionForm(forms.ModelForm):
    """
    Hızlı İşlem Şablonu Formu
    """
    class Meta:
        model = QuickAction
        fields = ['name', 'action_type', 'template_data', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'action_type': forms.Select(attrs={'class': 'form-control'}),
            'template_data': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }

