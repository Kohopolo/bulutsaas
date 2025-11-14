"""
Bungalov Modülü Forms
Bungalov rezervasyon formları
"""
from django import forms
from django.core.validators import MinValueValidator
from decimal import Decimal
from .models import (
    Bungalov, BungalovType, BungalovFeature,
    BungalovReservation, ReservationStatus, ReservationSource,
    BungalovReservationGuest, BungalovReservationPayment,
    BungalovCleaning, BungalovMaintenance, BungalovEquipment,
    BungalovPrice, BungalovVoucherTemplate
)


class BungalovForm(forms.ModelForm):
    """Bungalov Formu"""
    
    class Meta:
        model = Bungalov
        fields = [
            'code', 'name', 'bungalov_type', 'location', 'floor_number',
            'position_x', 'position_y', 'features', 'status', 'is_active', 'notes'
        ]
        widgets = {
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'bungalov_type': forms.Select(attrs={'class': 'form-control'}),
            'location': forms.TextInput(attrs={'class': 'form-control'}),
            'floor_number': forms.NumberInput(attrs={'class': 'form-control'}),
            'position_x': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'position_y': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'features': forms.SelectMultiple(attrs={'class': 'form-control'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class BungalovTypeForm(forms.ModelForm):
    """Bungalov Tipi Formu"""
    
    class Meta:
        model = BungalovType
        fields = [
            'name', 'code', 'description',
            'max_adults', 'max_children', 'max_total',
            'total_area', 'indoor_area', 'outdoor_area',
            'bedroom_count', 'bathroom_count',
            'has_living_room', 'has_kitchen',
            'is_active', 'sort_order'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'max_adults': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'max_children': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'max_total': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'total_area': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'indoor_area': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'outdoor_area': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'bedroom_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'bathroom_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'has_living_room': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'has_kitchen': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
        }


class BungalovReservationForm(forms.ModelForm):
    """Bungalov Rezervasyon Formu"""
    
    # Müşteri Arama
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
        model = BungalovReservation
        fields = [
            'bungalov', 'customer',
            'check_in_date', 'check_out_date', 'check_in_time', 'check_out_time',
            'adult_count', 'child_count', 'infant_count', 'child_ages',
            'status', 'source', 'reservation_agent', 'reservation_channel',
            'nightly_rate', 'weekly_rate', 'monthly_rate', 'is_manual_price',
            'discount_type', 'discount_percentage', 'discount_amount',
            'cleaning_fee', 'extra_person_fee', 'pet_fee',
            'early_check_in_fee', 'late_check_out_fee',
            'tax_amount', 'currency',
            'is_comp', 'is_no_show', 'no_show_reason',
            'early_check_in', 'late_check_out',
            'deposit_amount',
            'special_requests', 'internal_notes'
        ]
        widgets = {
            'bungalov': forms.Select(attrs={'class': 'form-control', 'id': 'id_bungalov'}),
            'customer': forms.HiddenInput(),
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
            'infant_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_infant_count'
            }),
            'child_ages': forms.HiddenInput(),  # JavaScript ile yönetilecek
            'status': forms.Select(attrs={'class': 'form-control', 'id': 'id_status'}),
            'source': forms.Select(attrs={'class': 'form-control', 'id': 'id_source'}),
            'reservation_agent': forms.Select(attrs={'class': 'form-control', 'id': 'id_reservation_agent'}),
            'reservation_channel': forms.Select(attrs={'class': 'form-control', 'id': 'id_reservation_channel'}),
            'nightly_rate': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_nightly_rate'
            }),
            'weekly_rate': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_weekly_rate'
            }),
            'monthly_rate': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_monthly_rate'
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
            'cleaning_fee': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_cleaning_fee'
            }),
            'extra_person_fee': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_extra_person_fee'
            }),
            'pet_fee': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_pet_fee'
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
            'deposit_amount': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_deposit_amount'
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
        super().__init__(*args, **kwargs)
        
        # Bungalov queryset'i filtrele
        self.fields['bungalov'].queryset = Bungalov.objects.filter(
            is_active=True, is_deleted=False
        ).order_by('code')
        
        # Acente ve kanal queryset'leri
        from apps.tenant_apps.channels.models import Channel
        self.fields['reservation_channel'].queryset = Channel.objects.filter(
            is_active=True, is_deleted=False
        ).order_by('name')
        
        # Düzenleme modunda (instance varsa) initial değerleri set et
        if self.instance and self.instance.pk:
            # Mevcut toplam ödemeyi göster
            self.fields['advance_payment'].initial = self.instance.total_paid or Decimal('0')
            
            # Son ödeme yöntemini göster
            last_payment = self.instance.payments.filter(is_deleted=False).order_by('-payment_date').first()
            if last_payment:
                self.fields['payment_method'].initial = last_payment.payment_method
            
            # Tarih alanlarını instance değerleri ile doldur
            if self.instance.check_in_date:
                self.fields['check_in_date'].initial = self.instance.check_in_date.strftime('%Y-%m-%d')
            if self.instance.check_out_date:
                self.fields['check_out_date'].initial = self.instance.check_out_date.strftime('%Y-%m-%d')
            if self.instance.check_in_time:
                self.fields['check_in_time'].initial = self.instance.check_in_time.strftime('%H:%M')
            if self.instance.check_out_time:
                self.fields['check_out_time'].initial = self.instance.check_out_time.strftime('%H:%M')
            
            # Sayısal alanları instance değerleri ile doldur
            if self.instance.adult_count is not None:
                self.fields['adult_count'].initial = self.instance.adult_count
            if self.instance.child_count is not None:
                self.fields['child_count'].initial = self.instance.child_count
            if self.instance.infant_count is not None:
                self.fields['infant_count'].initial = self.instance.infant_count
            if self.instance.nightly_rate is not None:
                self.fields['nightly_rate'].initial = str(self.instance.nightly_rate)
            if self.instance.weekly_rate is not None:
                self.fields['weekly_rate'].initial = str(self.instance.weekly_rate)
            if self.instance.monthly_rate is not None:
                self.fields['monthly_rate'].initial = str(self.instance.monthly_rate)
            
            # Select alanlarını instance değerleri ile doldur
            if self.instance.bungalov_id:
                self.fields['bungalov'].initial = self.instance.bungalov_id
            if self.instance.status:
                self.fields['status'].initial = self.instance.status
            if self.instance.source:
                self.fields['source'].initial = self.instance.source
            if self.instance.discount_type:
                self.fields['discount_type'].initial = self.instance.discount_type
            if self.instance.currency:
                self.fields['currency'].initial = self.instance.currency
            
            # Boolean alanları
            if self.instance.is_comp is not None:
                self.fields['is_comp'].initial = self.instance.is_comp
            if self.instance.is_no_show is not None:
                self.fields['is_no_show'].initial = self.instance.is_no_show
            if self.instance.is_manual_price is not None:
                self.fields['is_manual_price'].initial = self.instance.is_manual_price
            if self.instance.early_check_in is not None:
                self.fields['early_check_in'].initial = self.instance.early_check_in
            if self.instance.late_check_out is not None:
                self.fields['late_check_out'].initial = self.instance.late_check_out


class BungalovReservationGuestForm(forms.ModelForm):
    """Bungalov Rezervasyon Misafir Formu"""
    
    class Meta:
        model = BungalovReservationGuest
        fields = [
            'guest_type', 'guest_order',
            'first_name', 'last_name', 'gender', 'birth_date', 'age',
            'tc_no', 'passport_no', 'nationality',
            'phone', 'email',
            'special_needs', 'notes'
        ]
        widgets = {
            'guest_type': forms.Select(attrs={'class': 'form-control'}),
            'guest_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'gender': forms.Select(attrs={'class': 'form-control'}),
            'birth_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'age': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'tc_no': forms.TextInput(attrs={'class': 'form-control', 'maxlength': 11}),
            'passport_no': forms.TextInput(attrs={'class': 'form-control'}),
            'nationality': forms.TextInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'special_needs': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
        }


BungalovReservationGuestFormSet = forms.inlineformset_factory(
    BungalovReservation,
    BungalovReservationGuest,
    form=BungalovReservationGuestForm,
    extra=0,
    can_delete=True,
    min_num=1,
    validate_min=True
)


class BungalovCleaningForm(forms.ModelForm):
    """Bungalov Temizlik Formu"""
    
    class Meta:
        model = BungalovCleaning
        fields = [
            'bungalov', 'reservation',
            'cleaning_type', 'cleaning_date', 'cleaning_time', 'status',
            'cleaning_notes', 'issues_found',
            'assigned_to', 'completed_by', 'completed_at',
            'inspected_by', 'inspected_at', 'inspection_notes'
        ]
        widgets = {
            'bungalov': forms.Select(attrs={'class': 'form-control'}),
            'reservation': forms.Select(attrs={'class': 'form-control'}),
            'cleaning_type': forms.Select(attrs={'class': 'form-control'}),
            'cleaning_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'cleaning_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'cleaning_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'issues_found': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
            'completed_by': forms.Select(attrs={'class': 'form-control'}),
            'completed_at': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'inspected_by': forms.Select(attrs={'class': 'form-control'}),
            'inspected_at': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'inspection_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class BungalovMaintenanceForm(forms.ModelForm):
    """Bungalov Bakım Formu"""
    
    class Meta:
        model = BungalovMaintenance
        fields = [
            'bungalov', 'maintenance_type', 'priority',
            'title', 'description',
            'planned_date', 'start_date', 'completed_date', 'status',
            'reported_by', 'assigned_to',
            'estimated_cost', 'actual_cost',
            'notes', 'completion_notes'
        ]
        widgets = {
            'bungalov': forms.Select(attrs={'class': 'form-control'}),
            'maintenance_type': forms.Select(attrs={'class': 'form-control'}),
            'priority': forms.Select(attrs={'class': 'form-control'}),
            'title': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'planned_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'completed_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'reported_by': forms.Select(attrs={'class': 'form-control'}),
            'assigned_to': forms.Select(attrs={'class': 'form-control'}),
            'estimated_cost': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'actual_cost': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'completion_notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class BungalovPriceForm(forms.ModelForm):
    """Bungalov Fiyatlandırma Formu"""
    
    class Meta:
        model = BungalovPrice
        fields = [
            'bungalov_type', 'price_type', 'season',
            'start_date', 'end_date',
            'base_price', 'weekend_price', 'holiday_price',
            'min_nights', 'min_nights_weekend', 'min_nights_holiday',
            'currency', 'is_active'
        ]
        widgets = {
            'bungalov_type': forms.Select(attrs={'class': 'form-control'}),
            'price_type': forms.Select(attrs={'class': 'form-control'}),
            'season': forms.Select(attrs={'class': 'form-control'}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'base_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'weekend_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'holiday_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'min_nights': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'min_nights_weekend': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'min_nights_holiday': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class BungalovVoucherTemplateForm(forms.ModelForm):
    """Bungalov Voucher Şablon Formu"""
    
    class Meta:
        model = BungalovVoucherTemplate
        fields = ['name', 'code', 'description', 'template_html', 'is_active', 'is_default']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'template_html': forms.Textarea(attrs={'class': 'form-control', 'rows': 20}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_default': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class BungalovFeatureForm(forms.ModelForm):
    """Bungalov Özellik Formu"""
    
    class Meta:
        model = BungalovFeature
        fields = ['name', 'code', 'icon', 'description', 'category', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'icon': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'fas fa-star'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'category': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class BungalovEquipmentForm(forms.ModelForm):
    """Bungalov Ekipman Formu"""
    
    class Meta:
        model = BungalovEquipment
        fields = ['bungalov', 'name', 'category', 'brand', 'model', 'serial_number', 'status', 'notes']
        widgets = {
            'bungalov': forms.Select(attrs={'class': 'form-control'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'category': forms.Select(attrs={'class': 'form-control'}),
            'brand': forms.TextInput(attrs={'class': 'form-control'}),
            'model': forms.TextInput(attrs={'class': 'form-control'}),
            'serial_number': forms.TextInput(attrs={'class': 'form-control'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }

