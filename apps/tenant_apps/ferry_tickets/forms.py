"""
Feribot Bileti Forms
Rezervasyon form standartlarına uygun form yapısı
"""
from django import forms
from django.core.validators import MinValueValidator
from decimal import Decimal
from django.forms import inlineformset_factory
from .models import (
    FerryTicket, FerryTicketStatus, FerryTicketSource, FerryTicketType, FerryVehicleType,
    FerryTicketGuest, FerryTicketPayment, FerryTicketVoucherTemplate,
    Ferry, FerryRoute, FerrySchedule, FerryAPIConfiguration
)


# ==================== FERİBOT BİLETİ FORMU ====================

class FerryTicketForm(forms.ModelForm):
    """Feribot Bileti Formu - Popup Modal için"""
    
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
            ('online', 'Online Ödeme'),
        ],
        widget=forms.Select(attrs={
            'class': 'form-control',
            'id': 'payment_method'
        })
    )
    
    class Meta:
        model = FerryTicket
        fields = [
            'schedule', 'customer',
            'adult_count', 'child_count', 'infant_count',
            'vehicle_type', 'vehicle_plate', 'vehicle_brand', 'vehicle_model',
            'status', 'source', 'reservation_agent',
            'adult_unit_price', 'child_unit_price', 'infant_unit_price', 'vehicle_price',
            'discount_type', 'discount_percentage', 'discount_amount',
            'tax_amount', 'currency',
            'is_comp', 'special_requests', 'internal_notes'
        ]
        widgets = {
            'schedule': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_schedule'
            }),
            'customer': forms.HiddenInput(),
            'adult_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
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
            'vehicle_type': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_vehicle_type'
            }),
            'vehicle_plate': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_vehicle_plate',
                'placeholder': '34 ABC 123'
            }),
            'vehicle_brand': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_vehicle_brand',
                'placeholder': 'Örn: Toyota'
            }),
            'vehicle_model': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_vehicle_model',
                'placeholder': 'Örn: Corolla'
            }),
            'status': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_status'
            }),
            'source': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_source'
            }),
            'reservation_agent': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_reservation_agent'
            }),
            'adult_unit_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_adult_unit_price'
            }),
            'child_unit_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_child_unit_price'
            }),
            'infant_unit_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_infant_unit_price'
            }),
            'vehicle_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'readonly': True,
                'id': 'id_vehicle_price'
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
            'currency': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_currency'
            }),
            'is_comp': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_comp'
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
        
        # Düzenleme modunda (instance varsa) ön ödeme ve ödeme yöntemini ayarla
        if self.instance and self.instance.pk:
            # Mevcut toplam ödemeyi göster
            self.fields['advance_payment'].initial = self.instance.total_paid or Decimal('0')
            
            # Son ödeme yöntemini göster
            last_payment = self.instance.payments.filter(is_deleted=False).order_by('-payment_date').first()
            if last_payment:
                self.fields['payment_method'].initial = last_payment.payment_method
        
        # Status ve Source varsayılan değerleri (sadece yeni bilet için)
        if not self.instance or not self.instance.pk:
            self.fields['status'].initial = FerryTicketStatus.PENDING
            self.fields['source'].initial = FerryTicketSource.DIRECT
        
        # Currency varsayılan
        if not self.instance or not self.instance.currency:
            self.fields['currency'].initial = 'TRY'
        
        # Schedule queryset'i filtrele (sadece gelecek seferler)
        if not self.instance or not self.instance.pk:
            from django.utils import timezone
            today = timezone.now().date()
            self.fields['schedule'].queryset = FerrySchedule.objects.filter(
                departure_date__gte=today,
                is_active=True,
                is_cancelled=False
            ).select_related('route', 'ferry').order_by('departure_date', 'departure_time')
        
        # Reservation agent queryset'i filtrele
        from apps.tenant_apps.sales.models import Agency
        self.fields['reservation_agent'].queryset = Agency.objects.filter(
            is_active=True, is_deleted=False
        ).order_by('name')
    
    def clean(self):
        cleaned_data = super().clean()
        adult_count = cleaned_data.get('adult_count', 0)
        child_count = cleaned_data.get('child_count', 0)
        infant_count = cleaned_data.get('infant_count', 0)
        
        # En az bir yolcu olmalı
        if adult_count + child_count + infant_count == 0:
            raise forms.ValidationError('En az bir yolcu seçilmelidir.')
        
        return cleaned_data


# ==================== BİLET YOLCU FORMU ====================

class FerryTicketGuestForm(forms.ModelForm):
    """Bilet Yolcu Formu"""
    
    class Meta:
        model = FerryTicketGuest
        fields = [
            'ticket_type', 'guest_order',
            'first_name', 'last_name', 'gender', 'birth_date', 'age',
            'tc_no', 'passport_no', 'passport_serial_no', 'id_serial_no',
            'nationality', 'phone', 'email'
        ]
        widgets = {
            'ticket_type': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_ticket_type'
            }),
            'guest_order': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'id': 'id_guest_order'
            }),
            'first_name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_first_name'
            }),
            'last_name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_last_name'
            }),
            'gender': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_gender'
            }),
            'birth_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date',
                'id': 'id_birth_date'
            }),
            'age': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'max': 150,
                'id': 'id_age'
            }),
            'tc_no': forms.TextInput(attrs={
                'class': 'form-control',
                'maxlength': 11,
                'id': 'id_tc_no'
            }),
            'passport_no': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_passport_no'
            }),
            'passport_serial_no': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_passport_serial_no'
            }),
            'id_serial_no': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_id_serial_no'
            }),
            'nationality': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_nationality'
            }),
            'phone': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_phone'
            }),
            'email': forms.EmailInput(attrs={
                'class': 'form-control',
                'id': 'id_email'
            }),
        }


# Formset Factory
FerryTicketGuestFormSet = inlineformset_factory(
    FerryTicket,
    FerryTicketGuest,
    form=FerryTicketGuestForm,
    extra=0,
    can_delete=True,
    can_order=False,
    min_num=0,
    max_num=None,
)


# ==================== FERİBOT FORMU ====================

class FerryForm(forms.ModelForm):
    """Feribot Formu"""
    
    class Meta:
        model = Ferry
        fields = [
            'name', 'code', 'company_name', 'company_code',
            'phone', 'email', 'website',
            'capacity_passengers', 'capacity_vehicles', 'vessel_type',
            'is_active'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code'
            }),
            'company_name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_company_name'
            }),
            'company_code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_company_code'
            }),
            'phone': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_phone'
            }),
            'email': forms.EmailInput(attrs={
                'class': 'form-control',
                'id': 'id_email'
            }),
            'website': forms.URLInput(attrs={
                'class': 'form-control',
                'id': 'id_website'
            }),
            'capacity_passengers': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_capacity_passengers'
            }),
            'capacity_vehicles': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_capacity_vehicles'
            }),
            'vessel_type': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_vessel_type'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
        }


# ==================== FERİBOT ROTASI FORMU ====================

class FerryRouteForm(forms.ModelForm):
    """Feribot Rotası Formu"""
    
    class Meta:
        model = FerryRoute
        fields = [
            'name', 'code',
            'departure_port', 'departure_city', 'departure_country',
            'departure_latitude', 'departure_longitude',
            'arrival_port', 'arrival_city', 'arrival_country',
            'arrival_latitude', 'arrival_longitude',
            'distance_nautical_miles', 'estimated_duration_hours',
            'is_active', 'is_international'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code'
            }),
            'departure_port': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_departure_port'
            }),
            'departure_city': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_departure_city'
            }),
            'departure_country': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_departure_country'
            }),
            'departure_latitude': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.000001',
                'id': 'id_departure_latitude'
            }),
            'departure_longitude': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.000001',
                'id': 'id_departure_longitude'
            }),
            'arrival_port': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_arrival_port'
            }),
            'arrival_city': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_arrival_city'
            }),
            'arrival_country': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_arrival_country'
            }),
            'arrival_latitude': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.000001',
                'id': 'id_arrival_latitude'
            }),
            'arrival_longitude': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.000001',
                'id': 'id_arrival_longitude'
            }),
            'distance_nautical_miles': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_distance_nautical_miles'
            }),
            'estimated_duration_hours': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_estimated_duration_hours'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_international': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_international'
            }),
        }


# ==================== FERİBOT SEFERİ FORMU ====================

class FerryScheduleForm(forms.ModelForm):
    """Feribot Seferi Formu"""
    
    class Meta:
        model = FerrySchedule
        fields = [
            'ferry', 'route',
            'departure_date', 'departure_time', 'arrival_date', 'arrival_time',
            'adult_price', 'child_price', 'infant_price',
            'student_price', 'senior_price', 'disabled_price',
            'car_price', 'motorcycle_price', 'van_price',
            'truck_price', 'bus_price', 'caravan_price',
            'total_passenger_seats', 'total_vehicle_spots',
            'available_passenger_seats', 'available_vehicle_spots',
            'is_active', 'is_cancelled', 'cancellation_reason',
            'external_id', 'external_data'
        ]
        widgets = {
            'ferry': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_ferry'
            }),
            'route': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_route'
            }),
            'departure_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date',
                'id': 'id_departure_date'
            }),
            'departure_time': forms.TimeInput(attrs={
                'class': 'form-control',
                'type': 'time',
                'id': 'id_departure_time'
            }),
            'arrival_date': forms.DateInput(attrs={
                'class': 'form-control',
                'type': 'date',
                'id': 'id_arrival_date'
            }),
            'arrival_time': forms.TimeInput(attrs={
                'class': 'form-control',
                'type': 'time',
                'id': 'id_arrival_time'
            }),
            'adult_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_adult_price'
            }),
            'child_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_child_price'
            }),
            'infant_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_infant_price'
            }),
            'student_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_student_price'
            }),
            'senior_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_senior_price'
            }),
            'disabled_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_disabled_price'
            }),
            'car_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_car_price'
            }),
            'motorcycle_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_motorcycle_price'
            }),
            'van_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_van_price'
            }),
            'truck_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_truck_price'
            }),
            'bus_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_bus_price'
            }),
            'caravan_price': forms.NumberInput(attrs={
                'class': 'form-control',
                'step': '0.01',
                'min': 0,
                'id': 'id_caravan_price'
            }),
            'total_passenger_seats': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_total_passenger_seats'
            }),
            'total_vehicle_spots': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_total_vehicle_spots'
            }),
            'available_passenger_seats': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_available_passenger_seats'
            }),
            'available_vehicle_spots': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'id': 'id_available_vehicle_spots'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_cancelled': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_cancelled'
            }),
            'cancellation_reason': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_cancellation_reason'
            }),
            'external_id': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_external_id'
            }),
            'external_data': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 5,
                'id': 'id_external_data'
            }),
        }


# ==================== VOUCHER ŞABLON FORMU ====================

class FerryTicketVoucherTemplateForm(forms.ModelForm):
    """Bilet Voucher Şablon Formu"""
    
    class Meta:
        model = FerryTicketVoucherTemplate
        fields = [
            'name', 'code', 'description',
            'template_html', 'template_css',
            'is_active', 'is_default'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code'
            }),
            'description': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_description'
            }),
            'template_html': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 20,
                'id': 'id_template_html',
                'style': 'font-family: monospace;'
            }),
            'template_css': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 10,
                'id': 'id_template_css',
                'style': 'font-family: monospace;'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_default': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_default'
            }),
        }


# ==================== API KONFİGÜRASYON FORMU ====================

class FerryAPIConfigurationForm(forms.ModelForm):
    """Feribot API Konfigürasyon Formu"""
    
    class Meta:
        model = FerryAPIConfiguration
        fields = [
            'name', 'code', 'provider',
            'api_url', 'api_key', 'api_secret', 'username', 'password',
            'api_settings',
            'auto_sync_schedules', 'sync_frequency_hours',
            'is_active', 'is_test_mode'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code'
            }),
            'provider': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_provider'
            }),
            'api_url': forms.URLInput(attrs={
                'class': 'form-control',
                'id': 'id_api_url'
            }),
            'api_key': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_api_key'
            }),
            'api_secret': forms.TextInput(attrs={
                'class': 'form-control',
                'type': 'password',
                'id': 'id_api_secret'
            }),
            'username': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_username'
            }),
            'password': forms.TextInput(attrs={
                'class': 'form-control',
                'type': 'password',
                'id': 'id_password'
            }),
            'api_settings': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 5,
                'id': 'id_api_settings',
                'placeholder': 'JSON formatında ek ayarlar'
            }),
            'auto_sync_schedules': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_auto_sync_schedules'
            }),
            'sync_frequency_hours': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'id': 'id_sync_frequency_hours'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_test_mode': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_test_mode'
            }),
        }

