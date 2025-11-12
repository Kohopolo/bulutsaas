"""
Tur Yönetim Forms
"""
from django import forms
import json
from apps.core.widgets.json_widgets import KeyValueWidget, ObjectListWidget, ListWidget
from .models import (
    Tour, TourRegion, TourLocation, TourCity, TourType, TourVoucherTemplate,
    TourDate, TourProgram, TourImage, TourVideo, TourExtraService, TourRoute,
    TourReservation, TourGuest, TourReservationExtraService,
    TourGuide, TourVehicle, TourHotel, TourTransfer,
    TourCustomer, TourAgency, TourCampaign, TourPromoCode, TourNotificationTemplate
)


# ==================== TUR FORMS ====================

class TourForm(forms.ModelForm):
    """Tur ekleme/düzenleme formu"""
    
    class Meta:
        model = Tour
        fields = [
            'name', 'code', 'slug', 'description', 'status', 'is_active', 'is_featured', 'sort_order',
            'region', 'location', 'city', 'tour_type',
            'transport_type', 'duration_days', 'duration_nights', 'departure_time', 'return_time',
            'cities_to_visit', 'notes', 'hotels', 'price_includes', 'price_excludes',
            'max_adults', 'max_children', 'child_age_min', 'child_age_max',
            'adult_price', 'child_price', 'group_price', 'group_min_people',
            'campaign_price', 'campaign_start_date', 'campaign_end_date',
            'main_image', 'meta_title', 'meta_description', 'meta_keywords',
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Tur Adı'}),
            'code': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'tur-kodu'}),
            'slug': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'tur-url-slug'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'region': forms.Select(attrs={'class': 'form-control'}),
            'location': forms.Select(attrs={'class': 'form-control'}),
            'city': forms.Select(attrs={'class': 'form-control'}),
            'tour_type': forms.Select(attrs={'class': 'form-control'}),
            'transport_type': forms.Select(attrs={'class': 'form-control'}),
            'duration_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'duration_nights': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'departure_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'return_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'cities_to_visit': forms.Textarea(attrs={'class': 'form-control', 'rows': 2, 'placeholder': 'Virgülle ayrılmış şehir listesi'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'hotels': forms.Textarea(attrs={'class': 'form-control', 'rows': 3, 'placeholder': 'Her satıra bir otel'}),
            'price_includes': forms.Textarea(attrs={'class': 'form-control', 'rows': 5, 'placeholder': 'Her satıra bir madde'}),
            'price_excludes': forms.Textarea(attrs={'class': 'form-control', 'rows': 5, 'placeholder': 'Her satıra bir madde'}),
            'max_adults': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'max_children': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'child_age_min': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 18}),
            'child_age_max': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 18}),
            'adult_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'child_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'group_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'group_min_people': forms.NumberInput(attrs={'class': 'form-control', 'min': 2}),
            'campaign_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'campaign_start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'campaign_end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'main_image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'meta_title': forms.TextInput(attrs={'class': 'form-control'}),
            'meta_description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'meta_keywords': forms.TextInput(attrs={'class': 'form-control'}),
        }


# ==================== DİNAMİK YÖNETİM FORMS ====================

class TourRegionForm(forms.ModelForm):
    class Meta:
        model = TourRegion
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'fas fa-map'}),
        }


class TourLocationForm(forms.ModelForm):
    class Meta:
        model = TourLocation
        fields = ['name', 'code', 'location_type', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'location_type': forms.Select(attrs={'class': 'form-control'}),
        }


class TourCityForm(forms.ModelForm):
    class Meta:
        model = TourCity
        fields = ['name', 'code', 'country', 'latitude', 'longitude', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'country': forms.TextInput(attrs={'class': 'form-control'}),
            'latitude': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.000001'}),
            'longitude': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.000001'}),
        }


class TourTypeForm(forms.ModelForm):
    class Meta:
        model = TourType
        fields = ['name', 'code', 'description', 'icon', 'color', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control'}),
            'color': forms.TextInput(attrs={'class': 'form-control', 'type': 'color'}),
        }


class TourVoucherTemplateForm(forms.ModelForm):
    class Meta:
        model = TourVoucherTemplate
        fields = ['name', 'code', 'template_html', 'template_css', 'is_default', 'is_active']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'template_html': forms.Textarea(attrs={'class': 'form-control', 'rows': 15}),
            'template_css': forms.Textarea(attrs={'class': 'form-control', 'rows': 10}),
        }


# ==================== TUR DETAY FORMS ====================

class TourDateForm(forms.ModelForm):
    class Meta:
        model = TourDate
        fields = ['date', 'adult_price', 'child_price', 'group_price', 'max_adults', 'max_children', 'is_active']
        widgets = {
            'date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'adult_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'child_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'group_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'max_adults': forms.NumberInput(attrs={'class': 'form-control'}),
            'max_children': forms.NumberInput(attrs={'class': 'form-control'}),
        }


class TourProgramForm(forms.ModelForm):
    class Meta:
        model = TourProgram
        fields = ['day_number', 'title', 'description', 'activities', 'meals', 'accommodation', 'sort_order']
        widgets = {
            'day_number': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'title': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Örn: 1. Gün: İstanbul - İzmir'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'activities': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'meals': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Örn: Kahvaltı, Öğle Yemeği'}),
            'accommodation': forms.TextInput(attrs={'class': 'form-control'}),
        }


class TourImageForm(forms.ModelForm):
    class Meta:
        model = TourImage
        fields = ['image', 'title', 'alt_text', 'is_active', 'sort_order']
        widgets = {
            'image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'title': forms.TextInput(attrs={'class': 'form-control'}),
            'alt_text': forms.TextInput(attrs={'class': 'form-control'}),
        }


class TourVideoForm(forms.ModelForm):
    class Meta:
        model = TourVideo
        fields = ['video_type', 'video_url', 'video_id', 'title', 'is_active', 'sort_order']
        widgets = {
            'video_type': forms.Select(attrs={'class': 'form-control'}),
            'video_url': forms.URLInput(attrs={'class': 'form-control', 'placeholder': 'https://...'}),
            'video_id': forms.TextInput(attrs={'class': 'form-control'}),
            'title': forms.TextInput(attrs={'class': 'form-control'}),
        }


class TourExtraServiceForm(forms.ModelForm):
    class Meta:
        model = TourExtraService
        fields = ['name', 'description', 'price_per_person', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
        }


class TourRouteForm(forms.ModelForm):
    class Meta:
        model = TourRoute
        fields = ['city', 'order', 'is_departure', 'is_destination', 'stay_duration']
        widgets = {
            'city': forms.Select(attrs={'class': 'form-control'}),
            'order': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'stay_duration': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Örn: 2 gün, 1 gece'}),
        }


# ==================== REZERVASYON FORMS ====================

class TourReservationForm(forms.ModelForm):
    class Meta:
        model = TourReservation
        fields = [
            'tour', 'tour_date', 'customer_name', 'customer_surname', 'customer_email',
            'customer_phone', 'customer_tc', 'customer_address', 'adult_count', 'child_count',
            'adult_price', 'child_price', 'discount_amount', 'notes', 'sales_person'
        ]
        widgets = {
            'tour': forms.Select(attrs={'class': 'form-control'}),
            'tour_date': forms.Select(attrs={'class': 'form-control'}),
            'customer_name': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_surname': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_email': forms.EmailInput(attrs={'class': 'form-control'}),
            'customer_phone': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_tc': forms.TextInput(attrs={'class': 'form-control', 'maxlength': 11}),
            'customer_address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'adult_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'child_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'adult_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True}),
            'child_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'readonly': True}),
            'discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'sales_person': forms.Select(attrs={'class': 'form-control'}),
        }


class TourGuestForm(forms.ModelForm):
    class Meta:
        model = TourGuest
        fields = ['first_name', 'last_name', 'is_adult', 'age', 'tc_no', 'passport_no']
        widgets = {
            'first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'is_adult': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'age': forms.NumberInput(attrs={'class': 'form-control', 'min': 0, 'max': 120}),
            'tc_no': forms.TextInput(attrs={'class': 'form-control', 'maxlength': 11}),
            'passport_no': forms.TextInput(attrs={'class': 'form-control'}),
        }


TourGuestFormSet = forms.formset_factory(TourGuestForm, extra=1, can_delete=True)


# ==================== OPERASYONEL YÖNETİM FORMS ====================

class TourGuideForm(forms.ModelForm):
    """Rehber ekleme/düzenleme formu"""
    
    class Meta:
        model = TourGuide
        fields = [
            'name', 'surname', 'email', 'phone', 'license_number',
            'languages', 'specialties', 'hourly_rate', 'daily_rate',
            'is_active', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'surname': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'email': forms.EmailInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'license_number': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'languages': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'placeholder': 'Virgülle ayrılmış: Türkçe, İngilizce, Almanca'}),
            'specialties': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'hourly_rate': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'daily_rate': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
        }
    
    def clean_languages(self):
        languages = self.cleaned_data.get('languages')
        if languages:
            # Eğer string ise (form'dan geliyorsa) listeye çevir
            if isinstance(languages, str):
                return [lang.strip() for lang in languages.split(',') if lang.strip()]
            # Eğer zaten liste ise olduğu gibi döndür
            elif isinstance(languages, list):
                return languages
        return []
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # Eğer instance varsa ve languages bir liste ise, string'e çevir
        if self.instance and self.instance.pk and self.instance.languages:
            if isinstance(self.instance.languages, list):
                self.initial['languages'] = ', '.join(self.instance.languages)


class TourVehicleForm(forms.ModelForm):
    """Araç ekleme/düzenleme formu"""
    
    class Meta:
        model = TourVehicle
        fields = [
            'plate_number', 'brand', 'model', 'year', 'capacity',
            'vehicle_type', 'driver_name', 'driver_phone', 'daily_rate',
            'is_active', 'notes'
        ]
        widgets = {
            'plate_number': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'placeholder': '34 ABC 123'}),
            'brand': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'model': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'year': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 1900, 'max': 2100}),
            'capacity': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 1}),
            'vehicle_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'driver_name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'driver_phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'daily_rate': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
        }


class TourHotelForm(forms.ModelForm):
    """Otel ekleme/düzenleme formu - JSON Widget'larla"""
    room_types_json = forms.CharField(
        widget=ObjectListWidget(
            fields_config=[
                {'name': 'type', 'label': 'Oda Tipi', 'type': 'text', 'placeholder': 'Standart'},
                {'name': 'capacity', 'label': 'Kapasite', 'type': 'number', 'min': 1},
            ],
            attrs={'class': 'form-control', 'id': 'id_room_types_json'}
        ),
        required=False,
        label='Oda Tipleri',
        help_text='Otel oda tiplerini ekleyin'
    )
    
    class Meta:
        model = TourHotel
        fields = [
            'name', 'city', 'address', 'phone', 'email',
            'star_rating', 'room_types_json', 'daily_rate_per_person',
            'is_active', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'city': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'address': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 2}),
            'phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'email': forms.EmailInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'star_rating': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 1, 'max': 5}),
            'daily_rate_per_person': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.room_types:
                self.fields['room_types_json'].initial = json.dumps(
                    self.instance.room_types
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Oda tiplerini parse et
        room_types_str = self.cleaned_data.get('room_types_json', '[]')
        try:
            instance.room_types = json.loads(room_types_str)
        except:
            instance.room_types = []
        
        if commit:
            instance.save()
        return instance


class TourTransferForm(forms.ModelForm):
    """Transfer ekleme/düzenleme formu"""
    
    class Meta:
        model = TourTransfer
        fields = [
            'name', 'transfer_type', 'from_location', 'to_location',
            'distance_km', 'duration_minutes', 'price_per_person',
            'price_per_vehicle', 'is_active', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'transfer_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'from_location': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'to_location': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'distance_km': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'duration_minutes': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 0}),
            'price_per_person': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'price_per_vehicle': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
        }


# ==================== CRM FORMS ====================

class TourCustomerForm(forms.ModelForm):
    """Müşteri ekleme/düzenleme formu"""
    
    class Meta:
        model = TourCustomer
        fields = [
            'first_name', 'last_name', 'email', 'phone', 'tc_no',
            'address', 'city', 'country', 'postal_code', 'birth_date',
            'preferred_regions', 'preferred_tour_types', 'preferred_travel_months',
            'notes', 'special_requests', 'is_active', 'is_vip'
        ]
        widgets = {
            'first_name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'last_name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'email': forms.EmailInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'tc_no': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'maxlength': 11}),
            'address': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'city': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'country': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'postal_code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'birth_date': forms.DateInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'type': 'date'}),
            'preferred_regions': forms.SelectMultiple(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'preferred_tour_types': forms.SelectMultiple(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'preferred_travel_months': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'placeholder': 'Virgülle ayrılmış: 6,7,8 (Haziran, Temmuz, Ağustos)'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'special_requests': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_vip': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def clean_preferred_travel_months(self):
        months = self.cleaned_data.get('preferred_travel_months')
        if months:
            if isinstance(months, str):
                try:
                    return [int(m.strip()) for m in months.split(',') if m.strip() and m.strip().isdigit()]
                except ValueError:
                    return []
            elif isinstance(months, list):
                return months
        return []


# ==================== ACENTE FORMS ====================

class TourAgencyForm(forms.ModelForm):
    """Acente ekleme/düzenleme formu"""
    
    class Meta:
        model = TourAgency
        fields = [
            'name', 'code', 'contact_person', 'email', 'phone',
            'address', 'tax_number', 'tax_office',
            'commission_type', 'commission_rate', 'is_active', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'contact_person': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'email': forms.EmailInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'address': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'tax_number': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'tax_office': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'commission_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'commission_rate': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
        }


# ==================== KAMPANYA FORMS ====================

class TourCampaignForm(forms.ModelForm):
    """Kampanya ekleme/düzenleme formu"""
    
    class Meta:
        model = TourCampaign
        fields = [
            'name', 'code', 'description', 'campaign_type',
            'discount_percentage', 'discount_amount', 'min_purchase_amount', 'max_discount_amount',
            'start_date', 'end_date', 'applicable_tours', 'applicable_tour_types',
            'usage_limit', 'per_customer_limit', 'is_active', 'is_featured', 'image'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'description': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 3}),
            'campaign_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'discount_percentage': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0, 'max': 100}),
            'discount_amount': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'min_purchase_amount': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'max_discount_amount': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'step': '0.01', 'min': 0}),
            'start_date': forms.DateInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'type': 'date'}),
            'applicable_tours': forms.SelectMultiple(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'applicable_tour_types': forms.SelectMultiple(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'usage_limit': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 0}),
            'per_customer_limit': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 1}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_featured': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'image': forms.FileInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'accept': 'image/*'}),
        }


class TourPromoCodeForm(forms.ModelForm):
    """Promosyon kodu ekleme/düzenleme formu"""
    
    class Meta:
        model = TourPromoCode
        fields = [
            'code', 'campaign', 'description',
            'usage_limit', 'per_customer_limit',
            'start_date', 'end_date', 'is_active'
        ]
        widgets = {
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'placeholder': 'Örn: YAZ2024'}),
            'campaign': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'description': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 2}),
            'usage_limit': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 0}),
            'per_customer_limit': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'min': 1}),
            'start_date': forms.DateInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'type': 'date'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


# ==================== BİLDİRİM ŞABLON FORMS ====================

class TourNotificationTemplateForm(forms.ModelForm):
    """Bildirim şablon ekleme/düzenleme formu - JSON Widget'larla"""
    variables_json = forms.CharField(
        widget=KeyValueWidget(
            key_label='Değişken Adı',
            value_label='Açıklama',
            key_type='text',
            value_type='text',
            attrs={'class': 'form-control', 'id': 'id_variables_json'}
        ),
        required=False,
        label='Kullanılabilir Değişkenler',
        help_text='Şablonda kullanılabilir değişkenler (örn: customer_name: Müşteri Adı)'
    )
    
    class Meta:
        model = TourNotificationTemplate
        fields = [
            'name', 'code', 'notification_type', 'trigger_event',
            'subject', 'message', 'is_active', 'variables_json'
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'notification_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'trigger_event': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'subject': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary'}),
            'message': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-vb-primary', 'rows': 8}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.variables:
                self.fields['variables_json'].initial = json.dumps(
                    self.instance.variables
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Değişkenleri parse et
        variables_str = self.cleaned_data.get('variables_json', '{}')
        try:
            instance.variables = json.loads(variables_str)
        except:
            instance.variables = {}
        
        if commit:
            instance.save()
        return instance

