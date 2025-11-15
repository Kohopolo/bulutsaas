"""
Resepsiyon Modülü Forms
Rezervasyon formları - Kapsamlı popup form
"""
from django import forms
from django.core.validators import MinValueValidator
from decimal import Decimal
from .models import (
    Reservation, ReservationStatus, ReservationSource,
    ReservationGuest, ReservationPayment,
    EndOfDaySettings, EndOfDayAutomationType, EndOfDayNoShowAction
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
        
        # Düzenleme modunda (instance varsa) tüm alanları instance değerleri ile doldur
        # ÖNEMLİ: Django ModelForm instance ile oluşturulduğunda otomatik olarak değerleri yükler
        # Ancak bazı widget'lar (özellikle DateInput) bu değerleri doğru render etmeyebilir
        # Bu yüzden initial değerlerini manuel olarak set ediyoruz
        if self.instance and self.instance.pk:
            # Mevcut toplam ödemeyi göster
            self.fields['advance_payment'].initial = self.instance.total_paid or Decimal('0')
            
            # Son ödeme yöntemini göster
            last_payment = self.instance.payments.filter(is_deleted=False).order_by('-payment_date').first()
            if last_payment:
                self.fields['payment_method'].initial = last_payment.payment_method
            
            # Tarih alanlarını instance değerleri ile doldur (ZORUNLU - Django otomatik yüklemiyor)
            if self.instance.check_in_date:
                # DateInput widget için format: YYYY-MM-DD
                self.fields['check_in_date'].initial = self.instance.check_in_date.strftime('%Y-%m-%d') if hasattr(self.instance.check_in_date, 'strftime') else str(self.instance.check_in_date)
            if self.instance.check_out_date:
                self.fields['check_out_date'].initial = self.instance.check_out_date.strftime('%Y-%m-%d') if hasattr(self.instance.check_out_date, 'strftime') else str(self.instance.check_out_date)
            if self.instance.check_in_time:
                self.fields['check_in_time'].initial = self.instance.check_in_time.strftime('%H:%M') if hasattr(self.instance.check_in_time, 'strftime') else str(self.instance.check_in_time)
            if self.instance.check_out_time:
                self.fields['check_out_time'].initial = self.instance.check_out_time.strftime('%H:%M') if hasattr(self.instance.check_out_time, 'strftime') else str(self.instance.check_out_time)
            
            # Sayısal alanları instance değerleri ile doldur
            if self.instance.adult_count is not None:
                self.fields['adult_count'].initial = self.instance.adult_count
            if self.instance.child_count is not None:
                self.fields['child_count'].initial = self.instance.child_count
            if self.instance.room_rate is not None:
                self.fields['room_rate'].initial = str(self.instance.room_rate)  # Decimal için string'e çevir
            if self.instance.discount_amount is not None:
                self.fields['discount_amount'].initial = str(self.instance.discount_amount)
            if self.instance.discount_percentage is not None:
                self.fields['discount_percentage'].initial = str(self.instance.discount_percentage)
            if self.instance.tax_amount is not None:
                self.fields['tax_amount'].initial = str(self.instance.tax_amount)
            
            # Select alanlarını instance değerleri ile doldur (ZORUNLU - Django otomatik yüklemiyor)
            if self.instance.room_id:
                self.fields['room'].initial = self.instance.room_id
            if self.instance.room_number_id:
                self.fields['room_number'].initial = self.instance.room_number_id
            if self.instance.status:
                self.fields['status'].initial = self.instance.status
            if self.instance.source:
                self.fields['source'].initial = self.instance.source
            if self.instance.reservation_agent_id:
                self.fields['reservation_agent'].initial = self.instance.reservation_agent_id
            if self.instance.reservation_channel_id:
                self.fields['reservation_channel'].initial = self.instance.reservation_channel_id
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
            
            # Textarea alanları
            if self.instance.no_show_reason:
                self.fields['no_show_reason'].initial = self.instance.no_show_reason
            if self.instance.special_requests:
                self.fields['special_requests'].initial = self.instance.special_requests
            if self.instance.internal_notes:
                self.fields['internal_notes'].initial = self.instance.internal_notes
            
            # Ücret alanları
            if self.instance.early_check_in_fee is not None:
                self.fields['early_check_in_fee'].initial = str(self.instance.early_check_in_fee)
            if self.instance.late_check_out_fee is not None:
                self.fields['late_check_out_fee'].initial = str(self.instance.late_check_out_fee)
            
            # Debug: Initial değerlerini logla
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f'[DEBUG] Form initial değerleri set edildi - Rezervasyon ID: {self.instance.pk}')
            logger.info(f'[DEBUG] check_in_date initial: {self.fields["check_in_date"].initial}')
            logger.info(f'[DEBUG] check_out_date initial: {self.fields["check_out_date"].initial}')
            logger.info(f'[DEBUG] room initial: {self.fields["room"].initial}')
            logger.info(f'[DEBUG] status initial: {self.fields["status"].initial}')
            logger.info(f'[DEBUG] room_rate initial: {self.fields["room_rate"].initial}')
        
        # Status ve Source varsayılan değerleri (sadece yeni rezervasyon için)
        if not self.instance or not self.instance.pk:
            self.fields['status'].initial = ReservationStatus.PENDING
            self.fields['source'].initial = ReservationSource.DIRECT
        
        # Currency varsayılan
        if not self.instance or not self.instance.currency:
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
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # nationality alanını opsiyonel yap (default değer varsa)
        self.fields['nationality'].required = False
        self.fields['nationality'].initial = 'Türkiye'  # Varsayılan değer


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
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'style': 'width: 100%; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px;'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'style': 'width: 100%; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px;'
            }),
            'description': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'style': 'width: 100%; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px; resize: vertical;'
            }),
            'template_html': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 20,
                'style': 'width: 100%; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px; font-family: monospace; resize: vertical;'
            }),
            'template_css': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 10,
                'style': 'width: 100%; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 3px; font-size: 13px; font-family: monospace; resize: vertical;'
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


# ==================== GÜN SONU İŞLEMLERİ FORMLARI ====================

class EndOfDaySettingsForm(forms.ModelForm):
    """Gün Sonu Ayarları Formu"""
    
    class Meta:
        model = EndOfDaySettings
        fields = [
            'stop_if_room_price_zero',
            'stop_if_advance_folio_balance_not_zero',
            'check_checkout_folios',
            'cancel_no_show_reservations',
            'no_show_action',
            'extend_non_checkout_reservations',
            'extend_days',
            'cancel_room_change_plans',
            'auto_run_time',
            'automation_type',
            'is_active',
            'enable_rollback',
            'notes',
        ]
        widgets = {
            'stop_if_room_price_zero': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'stop_if_advance_folio_balance_not_zero': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'check_checkout_folios': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'cancel_no_show_reservations': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'no_show_action': forms.Select(attrs={'class': 'form-control'}),
            'extend_non_checkout_reservations': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'extend_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'cancel_room_change_plans': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'auto_run_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'automation_type': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'enable_rollback': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
        }
        labels = {
            'stop_if_room_price_zero': 'Oda Fiyatı SIFIR İse Durdur!',
            'stop_if_advance_folio_balance_not_zero': 'Peşin Folyo Balansı Sıfır Değilse Durdur!',
            'check_checkout_folios': 'Checkout Olmuş Folyoları Kontrol Et!',
            'cancel_no_show_reservations': 'Gelmeyen Rezervasyonları İptal Et veya Yarına Al!',
            'no_show_action': 'No-Show İşlemi',
            'extend_non_checkout_reservations': 'CheckOut Olmamış Konaklayanları UZAT!',
            'extend_days': 'Uzatma Gün Sayısı',
            'cancel_room_change_plans': 'Oda Değişim Planlarını İPTAL Et!',
            'auto_run_time': 'Otomatik Çalışma Saati',
            'automation_type': 'Otomasyon Türü',
            'is_active': 'Aktif mi?',
            'enable_rollback': 'Rollback Aktif mi?',
            'notes': 'Notlar',
        }
    
    def __init__(self, *args, **kwargs):
        self.hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        
        # Otel bilgisi yoksa hata
        if not self.hotel and not self.instance.pk:
            raise ValueError("Hotel bilgisi gerekli")
        
        # Otel bilgisi varsa ve yeni kayıt oluşturuluyorsa
        if self.hotel and not self.instance.pk:
            self.instance.hotel = self.hotel
