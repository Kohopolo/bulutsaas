"""
Otel Yönetimi Forms
"""
from django import forms
import json
from apps.core.widgets.json_widgets import KeyValueWidget, ObjectListWidget, WeekdayPricesWidget, ListWidget
from apps.core.widgets.campaign_rules_widget import CampaignRulesWidget
from .models import (
    # Otel Ayarları
    HotelRegion, HotelCity, HotelType, RoomType, BoardType, BedType,
    RoomFeature, HotelFeature,
    # Otel
    Hotel, HotelImage, HotelExtraService,
    # Oda
    Room, RoomImage,
    # Fiyatlama
    RoomPrice, RoomSeasonalPrice, RoomSpecialPrice, RoomCampaignPrice,
    RoomAgencyPrice, RoomChannelPrice,
    # Oda Numaraları
    Floor, Block, RoomNumber,
)


# ==================== OTEL AYARLARI FORMS ====================

class HotelRegionForm(forms.ModelForm):
    """Otel Bölgesi Formu"""
    class Meta:
        model = HotelRegion
        fields = ['name', 'code', 'description', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class HotelCityForm(forms.ModelForm):
    """Otel Şehri Formu"""
    class Meta:
        model = HotelCity
        fields = ['name', 'code', 'region', 'description', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'region': forms.Select(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class HotelTypeForm(forms.ModelForm):
    """Otel Türü Formu"""
    class Meta:
        model = HotelType
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'fas fa-hotel'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class RoomTypeForm(forms.ModelForm):
    """Oda Tipi Formu - Otel Bazlı"""
    class Meta:
        model = RoomType
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        # Hotel otomatik olarak kayıt sırasında atanacak


class BoardTypeForm(forms.ModelForm):
    """Pansiyon Tipi Formu - Otel Bazlı"""
    class Meta:
        model = BoardType
        fields = ['name', 'code', 'description', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        # Hotel otomatik olarak kayıt sırasında atanacak


class BedTypeForm(forms.ModelForm):
    """Yatak Tipi Formu - Otel Bazlı"""
    class Meta:
        model = BedType
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        # Hotel otomatik olarak kayıt sırasında atanacak


class RoomFeatureForm(forms.ModelForm):
    """Oda Özelliği Formu - Otel Bazlı"""
    class Meta:
        model = RoomFeature
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        # Hotel otomatik olarak kayıt sırasında atanacak


class HotelFeatureForm(forms.ModelForm):
    """Otel Özelliği Formu"""
    class Meta:
        model = HotelFeature
        fields = ['name', 'code', 'description', 'icon', 'category', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'form-control'}),
            'category': forms.TextInput(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


# ==================== OTEL FORMS ====================

class JSONCharField(forms.CharField):
    """JSON değerleri için özel CharField - Model instance değerlerini JSON string'e dönüştürür"""
    def prepare_value(self, value):
        """Model instance değerlerini (dict/list) JSON string'e dönüştür"""
        if value is None:
            return ''
        if isinstance(value, str):
            return value
        # Dict veya list ise JSON string'e dönüştür
        try:
            return json.dumps(value, ensure_ascii=False)
        except:
            return ''


class HotelForm(forms.ModelForm):
    """Otel Ekleme/Düzenleme Formu - JSON Widget'larla"""
    # JSON widget'lar için ayrı alanlar
    social_media_json = JSONCharField(
        widget=KeyValueWidget(
            key_label='Platform',
            value_label='URL',
            key_type='text',
            value_type='text',
            attrs={'class': 'form-control', 'id': 'id_social_media_json'}
        ),
        required=False,
        label='Sosyal Medya Hesapları',
        help_text='Sosyal medya platformları ve URL\'leri (örn: facebook, instagram, twitter)'
    )
    
    services_json = JSONCharField(
        widget=ListWidget(
            item_label='Hizmet',
            attrs={'class': 'form-control', 'id': 'id_services_json'}
        ),
        required=False,
        label='Hizmetler',
        help_text='Otel hizmetlerini ekleyin (örn: Oda Servisi, Spa, Fitness)'
    )
    
    amenities_json = JSONCharField(
        widget=ListWidget(
            item_label='Olanak',
            attrs={'class': 'form-control', 'id': 'id_amenities_json'}
        ),
        required=False,
        label='Olanaklar',
        help_text='Otel olanaklarını ekleyin (örn: WiFi, Havuz, Otopark)'
    )
    
    class Meta:
        model = Hotel
        fields = [
            'name', 'code', 'description',
            'region', 'city', 'hotel_type', 'star_rating',
            'email', 'phone', 'whatsapp', 'telegram', 'website',
            'address', 'district', 'postal_code', 'country',
            'latitude', 'longitude',
            'total_rooms', 'total_beds',
            'check_in_time', 'check_out_time',
            'main_image', 'logo', 'favicon',
            'detail_description', 'policies', 'concept_description',
            'youtube_video_url', 'video_file',
            'social_media_json',
            'hotel_features',
            'services_json', 'amenities_json',
            'is_active', 'is_featured', 'is_homepage_visible', 'sort_order',
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'region': forms.Select(attrs={'class': 'form-control'}),
            'city': forms.Select(attrs={'class': 'form-control'}),
            'hotel_type': forms.Select(attrs={'class': 'form-control'}),
            'star_rating': forms.Select(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
            'whatsapp': forms.TextInput(attrs={'class': 'form-control'}),
            'telegram': forms.TextInput(attrs={'class': 'form-control'}),
            'website': forms.URLInput(attrs={'class': 'form-control'}),
            'address': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'district': forms.TextInput(attrs={'class': 'form-control'}),
            'postal_code': forms.TextInput(attrs={'class': 'form-control'}),
            'country': forms.TextInput(attrs={'class': 'form-control'}),
            'latitude': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.000001'}),
            'longitude': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.000001'}),
            'total_rooms': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'total_beds': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'check_in_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'check_out_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'main_image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'logo': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'favicon': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'detail_description': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'policies': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'concept_description': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'youtube_video_url': forms.URLInput(attrs={'class': 'form-control'}),
            'video_file': forms.FileInput(attrs={'class': 'form-control', 'accept': 'video/*'}),
            'hotel_features': forms.SelectMultiple(attrs={'class': 'form-control', 'size': 10}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_featured': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_homepage_visible': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # JSONCharField.prepare_value otomatik olarak dict/list değerlerini JSON string'e dönüştürecek
        # Bu yüzden burada initial set etmeye gerek yok
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Sosyal medya parse et
        social_media_str = self.cleaned_data.get('social_media_json', '{}')
        try:
            instance.social_media = json.loads(social_media_str)
        except:
            instance.social_media = {}
        
        # Hizmetler parse et
        services_str = self.cleaned_data.get('services_json', '[]')
        try:
            instance.services = json.loads(services_str)
        except:
            instance.services = []
        
        # Olanaklar parse et
        amenities_str = self.cleaned_data.get('amenities_json', '[]')
        try:
            instance.amenities = json.loads(amenities_str)
        except:
            instance.amenities = []
        
        if commit:
            instance.save()
        return instance


class HotelImageForm(forms.ModelForm):
    """Otel Resim Formu"""
    class Meta:
        model = HotelImage
        fields = ['image', 'title', 'description', 'is_active', 'sort_order']
        widgets = {
            'image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'title': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class ExtraServiceForm(forms.ModelForm):
    """Ekstra Hizmet Formu"""
    class Meta:
        model = HotelExtraService
        fields = ['name', 'code', 'description', 'price', 'price_type', 'currency', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Örn: Havaalanı Transfer Hizmeti'}),
            'code': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Otomatik oluşturulur (boş bırakılabilir)'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3, 'placeholder': 'Hizmet hakkında detaylı bilgi'}),
            'price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0.01', 'placeholder': '1000.00'}),
            'price_type': forms.Select(attrs={'class': 'form-control'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        # Para birimi seçenekleri - şu an standart TRY
        # TODO: Genel Ayarlar modülü oluşturulduğunda bu alan dinamik hale getirilecek
        self.fields['currency'] = forms.ChoiceField(
            choices=[('TRY', 'TRY - Türk Lirası')],
            initial='TRY',
            widget=forms.Select(attrs={'class': 'form-control'}),
            required=True,
            label='Para Birimi'
        )
        
        # Para birimi default TRY olarak ayarla
        if not self.instance.pk:
            self.fields['currency'].initial = 'TRY'
        elif self.instance.currency:
            self.fields['currency'].initial = self.instance.currency
        else:
            self.fields['currency'].initial = 'TRY'
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Para birimi default TRY olarak ayarla
        currency_value = self.cleaned_data.get('currency')
        if currency_value:
            instance.currency = currency_value
        elif not instance.currency:
            instance.currency = 'TRY'
        
        # Kod otomatik oluştur (eğer boşsa)
        if not instance.code and instance.name:
            from django.utils.text import slugify
            instance.code = slugify(instance.name)
            # Aynı kod varsa numara ekle
            base_code = instance.code
            counter = 1
            while HotelExtraService.objects.filter(hotel=instance.hotel, code=instance.code).exclude(pk=instance.pk if instance.pk else None).exists():
                instance.code = f"{base_code}-{counter}"
                counter += 1
        
        if commit:
            instance.save()
        return instance


# ==================== ODA FORMS ====================

class RoomForm(forms.ModelForm):
    """Oda Ekleme/Düzenleme Formu"""
    class Meta:
        model = Room
        fields = [
            'name', 'code', 'room_type', 'board_type',
            'max_adults', 'max_children', 'max_total_capacity',
            'room_features', 'area_sqm', 'bed_type', 'bed_count',
            'main_image', 'video_url', 'video_file', 'virtual_3d_url',
            'description',
            'is_active', 'is_featured', 'is_homepage_visible', 'sort_order',
            'room_count',
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'room_type': forms.Select(attrs={'class': 'form-control'}),
            'board_type': forms.Select(attrs={'class': 'form-control'}),
            'max_adults': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'max_children': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'max_total_capacity': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'room_features': forms.SelectMultiple(attrs={'class': 'form-control', 'size': 10}),
            'area_sqm': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'bed_type': forms.Select(attrs={'class': 'form-control'}),
            'bed_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'main_image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'video_url': forms.URLInput(attrs={'class': 'form-control'}),
            'video_file': forms.FileInput(attrs={'class': 'form-control', 'accept': 'video/*'}),
            'virtual_3d_url': forms.URLInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 5}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_featured': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_homepage_visible': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'room_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            from .models import RoomType, BoardType, BedType, RoomFeature
            self.fields['room_type'].queryset = RoomType.objects.filter(hotel=hotel, is_deleted=False, is_active=True)
            self.fields['board_type'].queryset = BoardType.objects.filter(hotel=hotel, is_deleted=False, is_active=True)
            self.fields['bed_type'].queryset = BedType.objects.filter(hotel=hotel, is_deleted=False, is_active=True)
            self.fields['room_features'].queryset = RoomFeature.objects.filter(hotel=hotel, is_deleted=False, is_active=True)


class RoomImageForm(forms.ModelForm):
    """Oda Resim Formu"""
    class Meta:
        model = RoomImage
        fields = ['image', 'title', 'description', 'is_active', 'sort_order']
        widgets = {
            'image': forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'}),
            'title': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


# ==================== ODA FİYATLAMA FORMS ====================

class JSONCharField(forms.CharField):
    """JSON değerleri için özel CharField - Model instance değerlerini JSON string'e dönüştürür"""
    def prepare_value(self, value):
        """Model instance değerlerini (dict/list) JSON string'e dönüştür"""
        if value is None:
            return ''
        if isinstance(value, str):
            return value
        # Dict veya list ise JSON string'e dönüştür
        try:
            return json.dumps(value, ensure_ascii=False)
        except:
            return ''


class RoomPriceForm(forms.ModelForm):
    """Oda Fiyatlandırma Formu - JSON Widget'larla"""
    # JSON widget'lar için ayrı alanlar
    adult_multipliers_json = JSONCharField(
        widget=KeyValueWidget(
            key_label='Kişi Sayısı',
            value_label='Çarpan',
            key_type='number',
            value_type='number',
            attrs={'class': 'form-control', 'id': 'id_adult_multipliers_json'}
        ),
        required=False,
        label='Yetişkin Çarpanları',
        help_text='Her kişi sayısı için çarpan değeri girin'
    )
    
    free_children_rules_json = JSONCharField(
        widget=ObjectListWidget(
            fields_config=[
                {'name': 'age_range', 'label': 'Çocuk Yaş Aralığı', 'type': 'text', 'placeholder': '0-6'},
                {'name': 'count', 'label': 'Ücretsiz Çocuk Sayısı', 'type': 'number', 'min': 0},
                {'name': 'adult_required', 'label': 'Minimum Yetişkin Sayısı', 'type': 'number', 'min': 1},
            ],
            attrs={'class': 'form-control', 'id': 'id_free_children_rules_json'}
        ),
        required=False,
        label='Ücretsiz Çocuk Kuralları',
        help_text='Örnek: 0-6 yaş arası 2 çocuk, en az 2 yetişkin yanında ücretsiz. Her kural için "Yeni Kural Ekle" butonuna tıklayın.'
    )
    
    class Meta:
        model = RoomPrice
        fields = [
            'pricing_type', 'basic_nightly_price', 'currency',
            'child_fixed_multiplier', 'child_age_range',
            'free_children_count',
            'total_discount_rate', 'is_active',
        ]
        widgets = {
            'pricing_type': forms.Select(attrs={'class': 'form-control'}),
            'basic_nightly_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'child_fixed_multiplier': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'child_age_range': forms.TextInput(attrs={'class': 'form-control', 'placeholder': '0-12'}),
            'free_children_count': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'total_discount_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 100}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    # Para birimi alanını override et - şu an standart TRY
    # TODO: Genel Ayarlar modülü oluşturulduğunda bu alan dinamik hale getirilecek
    currency = forms.ChoiceField(
        choices=[('TRY', 'TRY - Türk Lirası')],
        initial='TRY',
        widget=forms.Select(attrs={'class': 'form-control'}),
        required=True,  # Zorunlu yap
        label='Para Birimi'
    )
    
    def __init__(self, *args, **kwargs):
        room = kwargs.pop('room', None)
        super().__init__(*args, **kwargs)
        
        # Para birimi default TRY olarak ayarla
        if not self.instance.pk:
            # Yeni kayıt için TRY
            self.fields['currency'].initial = 'TRY'
        elif self.instance.currency:
            # Mevcut kayıt için mevcut değeri kullan
            self.fields['currency'].initial = self.instance.currency
        else:
            # Boşsa TRY
            self.fields['currency'].initial = 'TRY'
        
        # Oda bilgisini widget'lara geçir
        if room:
            max_adults = room.max_adults or 2
            # Widget'a max_adults bilgisini geçir
            self.fields['adult_multipliers_json'].widget.max_adults = max_adults
        
        # Mevcut instance için JSON alanlarını yükle
        if self.instance.pk:
            # Yetişkin çarpanlarını yükle
            if self.instance.adult_multipliers:
                # Dict'i JSON string'e dönüştür
                try:
                    multipliers_json = json.dumps(self.instance.adult_multipliers, ensure_ascii=False)
                    self.fields['adult_multipliers_json'].initial = multipliers_json
                except:
                    # Hata durumunda boş bırak
                    pass
            else:
                # Boşsa otomatik çarpanlar oluştur
                if room:
                    max_adults = room.max_adults or 2
                    auto_multipliers = {}
                    for i in range(1, max_adults + 1):
                        auto_multipliers[str(i)] = float(i)
                    self.fields['adult_multipliers_json'].initial = json.dumps(auto_multipliers, ensure_ascii=False)
            
            # Ücretsiz çocuk kurallarını yükle
            if self.instance.free_children_rules:
                # List'i JSON string'e dönüştür
                try:
                    rules_json = json.dumps(self.instance.free_children_rules, ensure_ascii=False)
                    self.fields['free_children_rules_json'].initial = rules_json
                except:
                    # Hata durumunda boş bırak
                    pass
        else:
            # Yeni kayıt için otomatik çarpanlar oluştur
            if room:
                max_adults = room.max_adults or 2
                auto_multipliers = {}
                for i in range(1, max_adults + 1):
                    auto_multipliers[str(i)] = float(i)
                self.fields['adult_multipliers_json'].initial = json.dumps(auto_multipliers, ensure_ascii=False)
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Para birimi default TRY olarak ayarla (eğer boşsa veya cleaned_data'da yoksa)
        currency_value = self.cleaned_data.get('currency')
        if currency_value:
            instance.currency = currency_value
        elif not instance.currency:
            instance.currency = 'TRY'
        
        # Yetişkin çarpanlarını parse et
        adult_multipliers_str = self.cleaned_data.get('adult_multipliers_json') or '{}'
        if isinstance(adult_multipliers_str, str):
            try:
                instance.adult_multipliers = json.loads(adult_multipliers_str)
            except:
                instance.adult_multipliers = {}
        else:
            instance.adult_multipliers = adult_multipliers_str or {}
        
        # Ücretsiz çocuk kurallarını parse et
        free_children_rules_str = self.cleaned_data.get('free_children_rules_json') or '[]'
        if isinstance(free_children_rules_str, str):
            try:
                instance.free_children_rules = json.loads(free_children_rules_str)
            except:
                instance.free_children_rules = []
        else:
            instance.free_children_rules = free_children_rules_str or []
        
        if commit:
            instance.save()
        return instance


class RoomSeasonalPriceForm(forms.ModelForm):
    """Sezonluk Fiyat Formu"""
    class Meta:
        model = RoomSeasonalPrice
        fields = ['start_date', 'end_date', 'price_per_person', 'fixed_room_price', 'is_active']
        widgets = {
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'fixed_room_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class RoomSpecialPriceForm(forms.ModelForm):
    """Özel Fiyat Formu - JSON Widget'larla"""
    weekday_prices_json = forms.CharField(
        widget=WeekdayPricesWidget(
            attrs={'class': 'form-control', 'id': 'id_weekday_prices_json'}
        ),
        required=False,
        label='Hafta İçi Günlük Fiyatlar',
        help_text='Her gün için özel fiyat girebilirsiniz'
    )
    
    class Meta:
        model = RoomSpecialPrice
        fields = [
            'start_date', 'end_date',
            'weekend_price_per_person', 'weekend_fixed_price',
            'weekday_price_per_person', 'weekday_fixed_price',
            'is_active',
        ]
        widgets = {
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'weekend_price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'weekend_fixed_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'weekday_price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'weekday_fixed_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.weekday_prices:
                self.fields['weekday_prices_json'].initial = json.dumps(
                    self.instance.weekday_prices
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Hafta içi fiyatlarını parse et
        weekday_prices_str = self.cleaned_data.get('weekday_prices_json', '{}')
        try:
            instance.weekday_prices = json.loads(weekday_prices_str)
        except:
            instance.weekday_prices = {}
        
        if commit:
            instance.save()
        return instance


class RoomCampaignPriceForm(forms.ModelForm):
    """Kampanya Fiyat Formu - JSON Widget'larla"""
    campaign_rules_json = forms.CharField(
        widget=CampaignRulesWidget(
            attrs={'class': 'form-control', 'id': 'id_campaign_rules_json'}
        ),
        required=False,
        label='Kampanya Kuralları',
        help_text='Kampanya tipine göre gerekli kuralları girin'
    )
    
    class Meta:
        model = RoomCampaignPrice
        fields = [
            'name', 'description', 'start_date', 'end_date', 'campaign_type',
            'price_per_person', 'fixed_room_price',
            'discount_percent', 'discount_amount', 'is_active',
        ]
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'campaign_type': forms.Select(attrs={'class': 'form-control'}),
            'price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'fixed_room_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'discount_percent': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 100}),
            'discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.campaign_rules:
                self.fields['campaign_rules_json'].initial = json.dumps(
                    self.instance.campaign_rules
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Kampanya kurallarını parse et
        campaign_rules_str = self.cleaned_data.get('campaign_rules_json', '{}')
        try:
            instance.campaign_rules = json.loads(campaign_rules_str)
        except:
            instance.campaign_rules = {}
        
        if commit:
            instance.save()
        return instance


class RoomAgencyPriceForm(forms.ModelForm):
    """Acente Fiyat Formu"""
    class Meta:
        model = RoomAgencyPrice
        fields = ['agency_id', 'agency_name', 'price_per_person', 'fixed_room_price', 'commission_rate', 'is_active']
        widgets = {
            'agency_id': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'agency_name': forms.TextInput(attrs={'class': 'form-control'}),
            'price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'fixed_room_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'commission_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 100}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }


class RoomChannelPriceForm(forms.ModelForm):
    """
    Kanal Fiyat Formu
    
    TODO: channel_management_integration
    İleride Channel Yönetimi modülü oluşturulduğunda:
    - channel_name alanını ModelChoiceField'a dönüştür
    - Channel modelinden kanalları çek (is_active=True, is_deleted=False)
    - channel_code alanını otomatik doldur (seçilen channel'dan)
    - İlgili dosya: apps/tenant_apps/hotels/forms.py - RoomChannelPriceForm
    """
    class Meta:
        model = RoomChannelPrice
        fields = ['channel_code', 'channel_name', 'price_per_person', 'fixed_room_price', 'markup_rate', 'is_active']
        widgets = {
            'channel_code': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Kanal kodu (örn: booking_com)'}),
            # TODO: channel_management_integration - channel_name'i ModelChoiceField'a dönüştür
            'channel_name': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Kanal adı (örn: Booking.com)'}),
            'price_per_person': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'fixed_room_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'markup_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # TODO: channel_management_integration
        # İleride Channel modeli eklendiğinde burada ModelChoiceField kullanılacak:
        # from apps.tenant_apps.channels.models import Channel
        # self.fields['channel_name'] = forms.ModelChoiceField(
        #     queryset=Channel.objects.filter(is_active=True, is_deleted=False),
        #     widget=forms.Select(attrs={'class': 'form-control'}),
        #     required=True,
        #     label='Kanal'
        # )
        # self.fields['channel_code'] = forms.CharField(widget=forms.HiddenInput())  # Otomatik doldurulacak


# ==================== ODA NUMARALARI FORMS ====================

class FloorForm(forms.ModelForm):
    """Kat Formu"""
    class Meta:
        model = Floor
        fields = ['floor_number', 'name', 'description', 'is_active', 'sort_order']
        widgets = {
            'floor_number': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class BlockForm(forms.ModelForm):
    """Blok Formu"""
    class Meta:
        model = Block
        fields = ['name', 'code', 'description', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }


class RoomNumberForm(forms.ModelForm):
    """Oda Numarası Formu"""
    class Meta:
        model = RoomNumber
        fields = ['room', 'floor', 'block', 'number', 'status', 'notes', 'is_active']
        widgets = {
            'room': forms.Select(attrs={'class': 'form-control'}),
            'floor': forms.Select(attrs={'class': 'form-control'}),
            'block': forms.Select(attrs={'class': 'form-control'}),
            'number': forms.TextInput(attrs={'class': 'form-control'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            self.fields['room'].queryset = Room.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('name')
            self.fields['room'].empty_label = 'Oda Tipi Seçin'
            self.fields['floor'].queryset = Floor.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('floor_number')
            self.fields['floor'].empty_label = 'Kat Seçin (Opsiyonel)'
            self.fields['block'].queryset = Block.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('name')
            self.fields['block'].empty_label = 'Blok Seçin (Opsiyonel)'
        else:
            # Hotel yoksa boş queryset
            self.fields['room'].queryset = Room.objects.none()
            self.fields['floor'].queryset = Floor.objects.none()
            self.fields['block'].queryset = Block.objects.none()


class BulkRoomNumberForm(forms.Form):
    """Toplu Oda Numarası Ekleme Formu"""
    floor = forms.ModelChoiceField(
        queryset=Floor.objects.none(),
        required=False,
        widget=forms.Select(attrs={'class': 'form-control'}),
        label='Kat'
    )
    block = forms.ModelChoiceField(
        queryset=Block.objects.none(),
        required=False,
        widget=forms.Select(attrs={'class': 'form-control'}),
        label='Blok'
    )
    start_number = forms.IntegerField(
        min_value=1,
        widget=forms.NumberInput(attrs={'class': 'form-control'}),
        label='Başlangıç Numarası'
    )
    end_number = forms.IntegerField(
        min_value=1,
        widget=forms.NumberInput(attrs={'class': 'form-control'}),
        label='Bitiş Numarası'
    )
    room = forms.ModelChoiceField(
        queryset=Room.objects.none(),
        widget=forms.Select(attrs={'class': 'form-control'}),
        label='Oda Tipi'
    )
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        if hotel:
            self.fields['floor'].queryset = Floor.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('floor_number')
            self.fields['floor'].empty_label = 'Kat Seçin (Opsiyonel)'
            self.fields['block'].queryset = Block.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('name')
            self.fields['block'].empty_label = 'Blok Seçin (Opsiyonel)'
            self.fields['room'].queryset = Room.objects.filter(hotel=hotel, is_deleted=False, is_active=True).order_by('name')
            self.fields['room'].empty_label = 'Oda Tipi Seçin'
        else:
            # Hotel yoksa boş queryset
            self.fields['floor'].queryset = Floor.objects.none()
            self.fields['block'].queryset = Block.objects.none()
            self.fields['room'].queryset = Room.objects.none()

