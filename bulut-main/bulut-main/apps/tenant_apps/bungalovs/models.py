"""
Bungalov Yönetimi Modelleri
Profesyonel bungalov rezervasyon ve yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from datetime import date, timedelta
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== BUNGALOV DURUMLARI ====================

class BungalovStatus(models.TextChoices):
    """Bungalov Durumları"""
    AVAILABLE = 'available', 'Müsait'
    OCCUPIED = 'occupied', 'Dolu'
    CLEANING = 'cleaning', 'Temizlikte'
    MAINTENANCE = 'maintenance', 'Bakımda'
    OUT_OF_ORDER = 'out_of_order', 'Hizmet Dışı'


class ReservationStatus(models.TextChoices):
    """Rezervasyon Durumları"""
    PENDING = 'pending', 'Beklemede'
    CONFIRMED = 'confirmed', 'Onaylandı'
    CHECKED_IN = 'checked_in', 'Check-In Yapıldı'
    CHECKED_OUT = 'checked_out', 'Check-Out Yapıldı'
    CANCELLED = 'cancelled', 'İptal Edildi'
    NO_SHOW = 'no_show', 'Gelmedi'


class ReservationSource(models.TextChoices):
    """Rezervasyon Kaynakları"""
    DIRECT = 'direct', 'Direkt'
    ONLINE = 'online', 'Online'
    PHONE = 'phone', 'Telefon'
    EMAIL = 'email', 'E-posta'
    WALK_IN = 'walk_in', 'Walk-In'
    AGENCY = 'agency', 'Acente'
    BOOKING_COM = 'booking_com', 'Booking.com'
    AIRBNB = 'airbnb', 'Airbnb'
    VRBO = 'vrbo', 'VRBO'
    CORPORATE = 'corporate', 'Kurumsal'


class CleaningStatus(models.TextChoices):
    """Temizlik Durumları"""
    CLEAN = 'clean', 'Temiz'
    DIRTY = 'dirty', 'Kirli'
    PREPARING = 'preparing', 'Hazırlanıyor'
    INSPECTING = 'inspecting', 'Kontrol Ediliyor'


class CleaningType(models.TextChoices):
    """Temizlik Tipleri"""
    CHECKOUT = 'checkout', 'Check-Out Temizliği'
    WEEKLY = 'weekly', 'Haftalık Temizlik'
    DEEP = 'deep', 'Derinlemesine Temizlik'
    OPTIONAL = 'optional', 'İsteğe Bağlı Temizlik'


class MaintenanceStatus(models.TextChoices):
    """Bakım Durumları"""
    PLANNED = 'planned', 'Planlandı'
    IN_PROGRESS = 'in_progress', 'Devam Ediyor'
    COMPLETED = 'completed', 'Tamamlandı'
    CANCELLED = 'cancelled', 'İptal Edildi'


class MaintenanceType(models.TextChoices):
    """Bakım Tipleri"""
    ROUTINE = 'routine', 'Rutin Bakım'
    URGENT = 'urgent', 'Acil Onarım'
    RENOVATION = 'renovation', 'Yenileme'
    PAINTING = 'painting', 'Boyama'
    FURNITURE = 'furniture', 'Mobilya Değişimi'
    EQUIPMENT = 'equipment', 'Ekipman Değişimi'


# ==================== BUNGALOV TİPLERİ ====================

class BungalovType(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Tipi
    Standart, Deluxe, Suite vb.
    """
    name = models.CharField('Bungalov Tipi Adı', max_length=100)
    code = models.CharField('Kod', max_length=20, unique=True, db_index=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Kapasite
    max_adults = models.IntegerField('Maksimum Yetişkin', default=2, validators=[MinValueValidator(1)])
    max_children = models.IntegerField('Maksimum Çocuk', default=2, validators=[MinValueValidator(0)])
    max_total = models.IntegerField('Maksimum Toplam Kişi', default=4, validators=[MinValueValidator(1)])
    
    # Alan Bilgileri
    total_area = models.DecimalField('Toplam Alan (m²)', max_digits=8, decimal_places=2, default=0)
    indoor_area = models.DecimalField('İç Alan (m²)', max_digits=8, decimal_places=2, default=0)
    outdoor_area = models.DecimalField('Dış Alan/Bahçe (m²)', max_digits=8, decimal_places=2, default=0)
    
    # Oda Bilgileri
    bedroom_count = models.IntegerField('Yatak Odası Sayısı', default=1, validators=[MinValueValidator(1)])
    bathroom_count = models.IntegerField('Banyo Sayısı', default=1, validators=[MinValueValidator(1)])
    has_living_room = models.BooleanField('Oturma Odası Var mı?', default=True)
    has_kitchen = models.BooleanField('Mutfak Var mı?', default=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Bungalov Tipi'
        verbose_name_plural = 'Bungalov Tipleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


# ==================== BUNGALOV ÖZELLİKLERİ ====================

class BungalovFeature(TimeStampedModel):
    """
    Bungalov Özellikleri
    Deniz manzarası, jakuzi, şömine vb.
    """
    name = models.CharField('Özellik Adı', max_length=100, unique=True)
    code = models.CharField('Kod', max_length=50, unique=True, db_index=True)
    icon = models.CharField('İkon', max_length=50, blank=True, help_text='Font Awesome icon class')
    description = models.TextField('Açıklama', blank=True)
    category = models.CharField('Kategori', max_length=50, choices=[
        ('view', 'Manzara'),
        ('amenity', 'Olanak'),
        ('equipment', 'Ekipman'),
        ('outdoor', 'Dış Mekan'),
        ('kitchen', 'Mutfak'),
        ('other', 'Diğer'),
    ], default='amenity')
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Bungalov Özelliği'
        verbose_name_plural = 'Bungalov Özellikleri'
        ordering = ['category', 'sort_order', 'name']
    
    def __str__(self):
        return self.name


# ==================== BUNGALOV MODELİ ====================

class Bungalov(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Birimi
    Fiziksel bungalov birimleri
    """
    # Temel Bilgiler
    code = models.CharField('Bungalov Kodu', max_length=50, unique=True, db_index=True)
    name = models.CharField('Bungalov Adı', max_length=100)
    bungalov_type = models.ForeignKey(
        BungalovType,
        on_delete=models.PROTECT,
        related_name='bungalovs',
        verbose_name='Bungalov Tipi'
    )
    
    # Konum
    location = models.CharField('Konum', max_length=200, blank=True, help_text='Blok, Bölge vb.')
    floor_number = models.IntegerField('Kat Numarası', default=0, help_text='0 = Zemin kat')
    position_x = models.DecimalField('X Pozisyonu', max_digits=10, decimal_places=2, null=True, blank=True)
    position_y = models.DecimalField('Y Pozisyonu', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Özellikler (Many-to-Many)
    features = models.ManyToManyField(
        BungalovFeature,
        related_name='bungalovs',
        blank=True,
        verbose_name='Özellikler'
    )
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=BungalovStatus.choices,
        default=BungalovStatus.AVAILABLE,
        db_index=True
    )
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Bungalov'
        verbose_name_plural = 'Bungalovlar'
        ordering = ['code']
        indexes = [
            models.Index(fields=['status', 'is_active']),
            models.Index(fields=['bungalov_type', 'status']),
        ]
    
    def __str__(self):
        return f"{self.code} - {self.name}"
    
    def get_current_reservation(self):
        """Şu anki rezervasyonu getir"""
        today = date.today()
        return self.reservations.filter(
            check_in_date__lte=today,
            check_out_date__gte=today,
            status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
            is_deleted=False
        ).first()
    
    def is_available(self, check_in_date, check_out_date, exclude_reservation_id=None):
        """Belirtilen tarihlerde müsait mi?"""
        if self.status != BungalovStatus.AVAILABLE:
            return False
        
        # Rezervasyon kontrolü
        conflicting = self.reservations.filter(
            check_in_date__lt=check_out_date,
            check_out_date__gt=check_in_date,
            status__in=[ReservationStatus.PENDING, ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
            is_deleted=False
        )
        
        if exclude_reservation_id:
            conflicting = conflicting.exclude(pk=exclude_reservation_id)
        
        return not conflicting.exists()


# ==================== BUNGALOV REZERVASYONU ====================

class BungalovReservation(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Rezervasyonu
    Ana rezervasyon modeli
    """
    # Temel Bilgiler
    reservation_code = models.CharField('Rezervasyon Kodu', max_length=50, unique=True, db_index=True)
    bungalov = models.ForeignKey(
        Bungalov,
        on_delete=models.PROTECT,
        related_name='reservations',
        verbose_name='Bungalov'
    )
    
    # Müşteri Bilgileri
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.CASCADE,
        related_name='bungalov_reservations',
        verbose_name='Müşteri'
    )
    
    # Tarih Bilgileri
    check_in_date = models.DateField('Check-in Tarihi', db_index=True)
    check_out_date = models.DateField('Check-out Tarihi', db_index=True)
    check_in_time = models.TimeField('Check-in Saati', default='15:00')
    check_out_time = models.TimeField('Check-out Saati', default='11:00')
    
    # Misafir Bilgileri
    adult_count = models.IntegerField('Yetişkin Sayısı', default=2, validators=[MinValueValidator(1)])
    child_count = models.IntegerField('Çocuk Sayısı', default=0, validators=[MinValueValidator(0)])
    child_ages = models.JSONField('Çocuk Yaşları', default=list, blank=True, help_text='Örn: [5, 8]')
    infant_count = models.IntegerField('Bebek Sayısı', default=0, validators=[MinValueValidator(0)])
    
    # Rezervasyon Bilgileri
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=ReservationStatus.choices,
        default=ReservationStatus.PENDING,
        db_index=True
    )
    source = models.CharField(
        'Kaynak',
        max_length=20,
        choices=ReservationSource.choices,
        default=ReservationSource.DIRECT,
        db_index=True
    )
    
    # Rezervasyon Aracıları
    reservation_agent = models.ForeignKey(
        'sales.Agency',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='bungalov_reservations',
        verbose_name='Rezervasyon Acentesi'
    )
    reservation_channel = models.ForeignKey(
        'channels.Channel',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='bungalov_reservations',
        verbose_name='Rezervasyon Kanalı'
    )
    
    # Fiyatlandırma
    nightly_rate = models.DecimalField('Gecelik Fiyat', max_digits=10, decimal_places=2, default=0)
    weekly_rate = models.DecimalField('Haftalık Fiyat', max_digits=10, decimal_places=2, default=0, null=True, blank=True)
    monthly_rate = models.DecimalField('Aylık Fiyat', max_digits=10, decimal_places=2, default=0, null=True, blank=True)
    is_manual_price = models.BooleanField('Manuel Fiyat mı?', default=False)
    total_nights = models.IntegerField('Toplam Gece', default=1)
    total_amount = models.DecimalField('Toplam Tutar', max_digits=12, decimal_places=2, default=0)
    
    # İndirimler
    discount_type = models.CharField('İndirim Tipi', max_length=20, 
                                    choices=[('percentage', 'Yüzde'), ('fixed', 'Sabit Tutar')],
                                    blank=True, null=True)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, default=0)
    discount_percentage = models.DecimalField('İndirim Yüzdesi', max_digits=5, decimal_places=2, default=0,
                                            validators=[MinValueValidator(Decimal('0'))])
    
    # Ek Ücretler
    cleaning_fee = models.DecimalField('Temizlik Ücreti', max_digits=10, decimal_places=2, default=0)
    extra_person_fee = models.DecimalField('Ekstra Kişi Ücreti', max_digits=10, decimal_places=2, default=0)
    pet_fee = models.DecimalField('Evcil Hayvan Ücreti', max_digits=10, decimal_places=2, default=0)
    early_check_in_fee = models.DecimalField('Erken Check-in Ücreti', max_digits=10, decimal_places=2, default=0)
    late_check_out_fee = models.DecimalField('Geç Check-out Ücreti', max_digits=10, decimal_places=2, default=0)
    
    tax_amount = models.DecimalField('Vergi Tutarı', max_digits=10, decimal_places=2, default=0)
    total_paid = models.DecimalField('Ödenen Tutar', max_digits=12, decimal_places=2, default=0)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY',
                               choices=[('TRY', 'Türk Lirası'), ('USD', 'US Dollar'), 
                                       ('EUR', 'Euro'), ('GBP', 'British Pound')])
    
    # Özel Durumlar
    is_comp = models.BooleanField('Comp Rezervasyon mu?', default=False)
    is_no_show = models.BooleanField('No-Show mu?', default=False)
    no_show_reason = models.TextField('No-Show Nedeni', blank=True)
    
    # Özel İstekler ve Notlar
    special_requests = models.TextField('Özel İstekler', blank=True)
    internal_notes = models.TextField('İç Notlar', blank=True)
    
    # Durum Bilgileri
    is_checked_in = models.BooleanField('Check-In Yapıldı mı?', default=False)
    is_checked_out = models.BooleanField('Check-Out Yapıldı mı?', default=False)
    checked_in_at = models.DateTimeField('Check-In Tarihi', null=True, blank=True)
    checked_out_at = models.DateTimeField('Check-Out Tarihi', null=True, blank=True)
    early_check_in = models.BooleanField('Erken Check-in mi?', default=False)
    late_check_out = models.BooleanField('Geç Check-out mu?', default=False)
    
    # İptal Bilgileri
    is_cancelled = models.BooleanField('İptal Edildi mi?', default=False)
    cancelled_at = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    cancelled_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='cancelled_bungalov_reservations',
        verbose_name='İptal Eden Kullanıcı'
    )
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    cancellation_refund_amount = models.DecimalField('İptal İade Tutarı', max_digits=12, decimal_places=2, default=0)
    
    # Depozito
    deposit_amount = models.DecimalField('Depozito Tutarı', max_digits=10, decimal_places=2, default=0)
    deposit_paid = models.DecimalField('Ödenen Depozito', max_digits=10, decimal_places=2, default=0)
    deposit_returned = models.DecimalField('İade Edilen Depozito', max_digits=10, decimal_places=2, default=0)
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_bungalov_reservations',
        verbose_name='Oluşturan Kullanıcı'
    )
    updated_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='updated_bungalov_reservations',
        verbose_name='Güncelleyen Kullanıcı'
    )
    deleted_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='deleted_bungalov_reservations',
        verbose_name='Silen Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Bungalov Rezervasyonu'
        verbose_name_plural = 'Bungalov Rezervasyonları'
        ordering = ['-check_in_date', '-created_at']
        indexes = [
            models.Index(fields=['bungalov', 'status', 'check_in_date']),
            models.Index(fields=['customer', 'status']),
            models.Index(fields=['reservation_code']),
        ]
    
    def __str__(self):
        return f"{self.reservation_code} - {self.customer.first_name} {self.customer.last_name}"
    
    def save(self, *args, **kwargs):
        """Rezervasyon kaydedilirken otomatik hesaplamalar yap"""
        # Toplam gece sayısını hesapla
        if self.check_in_date and self.check_out_date:
            self.total_nights = (self.check_out_date - self.check_in_date).days
            if self.total_nights < 1:
                self.total_nights = 1
        
        # Comp rezervasyon ise toplam tutar 0
        if self.is_comp:
            self.total_amount = Decimal('0')
        else:
            # Toplam tutarı hesapla
            base_amount = Decimal('0')
            
            # Haftalık veya aylık fiyat varsa onu kullan
            if self.total_nights >= 30 and self.monthly_rate > 0:
                months = Decimal(str(self.total_nights)) / Decimal('30')
                base_amount = self.monthly_rate * months
            elif self.total_nights >= 7 and self.weekly_rate > 0:
                weeks = Decimal(str(self.total_nights)) / Decimal('7')
                base_amount = self.weekly_rate * weeks
            elif self.nightly_rate > 0:
                base_amount = Decimal(str(self.nightly_rate)) * Decimal(str(self.total_nights))
            
            # Ek ücretler
            extra_fees = (self.cleaning_fee + self.extra_person_fee + self.pet_fee + 
                         self.early_check_in_fee + self.late_check_out_fee)
            
            # İndirim hesaplama
            if self.discount_type == 'percentage' and self.discount_percentage > 0:
                discount = base_amount * (Decimal(str(self.discount_percentage)) / Decimal('100'))
                self.discount_amount = discount
            elif self.discount_type == 'fixed' and self.discount_amount > 0:
                pass  # discount_amount zaten set edilmiş
            
            self.total_amount = base_amount - self.discount_amount + extra_fees + self.tax_amount
        
        super().save(*args, **kwargs)
    
    def get_remaining_amount(self):
        """Kalan ödeme tutarını hesapla"""
        return self.total_amount - self.total_paid
    
    def is_paid(self):
        """Rezervasyon tamamen ödendi mi?"""
        return self.total_paid >= self.total_amount
    
    def can_check_in(self):
        """Check-in yapılabilir mi?"""
        return (self.status == ReservationStatus.CONFIRMED and 
                not self.is_checked_in and
                self.check_in_date <= date.today())
    
    def can_check_out(self):
        """Check-out yapılabilir mi?"""
        return (self.is_checked_in and 
                not self.is_checked_out and
                self.check_out_date <= date.today())
    
    def calculate_total_paid(self):
        """Toplam ödenen tutarı hesapla"""
        return self.payments.filter(is_deleted=False).aggregate(
            total=models.Sum('payment_amount')
        )['total'] or Decimal('0')
    
    def update_total_paid(self):
        """Toplam ödenen tutarı güncelle"""
        self.total_paid = self.calculate_total_paid()
        self.save(update_fields=['total_paid'])


# ==================== REZERVASYON MİSAFİRLERİ ====================

class BungalovReservationGuest(TimeStampedModel):
    """
    Bungalov Rezervasyon Misafir Bilgileri
    """
    GUEST_TYPE_CHOICES = [
        ('adult', 'Yetişkin'),
        ('child', 'Çocuk'),
        ('infant', 'Bebek'),
    ]
    
    GENDER_CHOICES = [
        ('male', 'Erkek'),
        ('female', 'Kadın'),
        ('other', 'Diğer'),
    ]
    
    reservation = models.ForeignKey(
        BungalovReservation,
        on_delete=models.CASCADE,
        related_name='guests',
        verbose_name='Rezervasyon'
    )
    
    guest_type = models.CharField('Misafir Tipi', max_length=20, choices=GUEST_TYPE_CHOICES)
    guest_order = models.IntegerField('Misafir Sırası', default=1)
    
    # Kişisel Bilgiler
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    gender = models.CharField('Cinsiyet', max_length=10, choices=GENDER_CHOICES, blank=True)
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    age = models.IntegerField('Yaş', null=True, blank=True, validators=[MinValueValidator(0)])
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    passport_no = models.CharField('Pasaport No', max_length=50, blank=True)
    nationality = models.CharField('Uyruk', max_length=50, blank=True)
    
    # İletişim
    phone = models.CharField('Telefon', max_length=20, blank=True)
    email = models.EmailField('E-posta', blank=True)
    
    # Özel Notlar
    special_needs = models.TextField('Özel İhtiyaçlar', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Misafiri'
        verbose_name_plural = 'Rezervasyon Misafirleri'
        ordering = ['guest_order']
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.get_guest_type_display()})"


# ==================== REZERVASYON ÖDEMELERİ ====================

class BungalovReservationPayment(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Rezervasyon Ödemeleri
    """
    PAYMENT_TYPE_CHOICES = [
        ('deposit', 'Depozito'),
        ('payment', 'Ödeme'),
        ('refund', 'İade'),
        ('deposit_return', 'Depozito İadesi'),
    ]
    
    PAYMENT_METHOD_CHOICES = [
        ('cash', 'Nakit'),
        ('credit_card', 'Kredi Kartı'),
        ('debit_card', 'Banka Kartı'),
        ('bank_transfer', 'Havale/EFT'),
        ('check', 'Çek'),
        ('iyzico', 'İyzico'),
        ('paytr', 'PayTR'),
        ('other', 'Diğer'),
    ]
    
    reservation = models.ForeignKey(
        BungalovReservation,
        on_delete=models.CASCADE,
        related_name='payments',
        verbose_name='Rezervasyon'
    )
    
    payment_type = models.CharField('Ödeme Tipi', max_length=20, choices=PAYMENT_TYPE_CHOICES, default='payment')
    payment_date = models.DateField('Ödeme Tarihi', default=date.today)
    payment_amount = models.DecimalField('Ödeme Tutarı', max_digits=12, decimal_places=2)
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, choices=PAYMENT_METHOD_CHOICES)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Ödeme Detayları
    payment_reference = models.CharField('Ödeme Referansı', max_length=200, blank=True)
    transaction_id = models.CharField('İşlem ID', max_length=100, blank=True)
    payment_info = models.JSONField('Ödeme Bilgileri', default=dict, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_bungalov_payments',
        verbose_name='Oluşturan Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Rezervasyon Ödemesi'
        verbose_name_plural = 'Rezervasyon Ödemeleri'
        ordering = ['-payment_date', '-created_at']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.payment_amount} {self.currency}"


# ==================== TEMİZLİK YÖNETİMİ ====================

class BungalovCleaning(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Temizlik Kayıtları
    """
    bungalov = models.ForeignKey(
        Bungalov,
        on_delete=models.CASCADE,
        related_name='cleanings',
        verbose_name='Bungalov'
    )
    reservation = models.ForeignKey(
        BungalovReservation,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='cleanings',
        verbose_name='Rezervasyon'
    )
    
    cleaning_type = models.CharField('Temizlik Tipi', max_length=20, choices=CleaningType.choices, default=CleaningType.CHECKOUT)
    cleaning_date = models.DateField('Temizlik Tarihi', default=date.today)
    cleaning_time = models.TimeField('Temizlik Saati', null=True, blank=True)
    status = models.CharField('Durum', max_length=20, choices=CleaningStatus.choices, default=CleaningStatus.DIRTY)
    
    # Temizlik Detayları
    cleaning_notes = models.TextField('Temizlik Notları', blank=True)
    issues_found = models.TextField('Tespit Edilen Sorunlar', blank=True)
    
    # Personel
    assigned_to = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_bungalov_cleanings',
        verbose_name='Atanan Personel'
    )
    completed_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='completed_bungalov_cleanings',
        verbose_name='Tamamlayan Personel'
    )
    completed_at = models.DateTimeField('Tamamlanma Tarihi', null=True, blank=True)
    
    # Kontrol
    inspected_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='inspected_bungalov_cleanings',
        verbose_name='Kontrol Eden Personel'
    )
    inspected_at = models.DateTimeField('Kontrol Tarihi', null=True, blank=True)
    inspection_notes = models.TextField('Kontrol Notları', blank=True)
    
    class Meta:
        verbose_name = 'Bungalov Temizliği'
        verbose_name_plural = 'Bungalov Temizlikleri'
        ordering = ['-cleaning_date', '-created_at']
    
    def __str__(self):
        return f"{self.bungalov.code} - {self.get_cleaning_type_display()} - {self.cleaning_date}"


# ==================== BAKIM YÖNETİMİ ====================

class BungalovMaintenance(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Bakım Kayıtları
    """
    bungalov = models.ForeignKey(
        Bungalov,
        on_delete=models.CASCADE,
        related_name='maintenances',
        verbose_name='Bungalov'
    )
    
    maintenance_type = models.CharField('Bakım Tipi', max_length=20, choices=MaintenanceType.choices, default=MaintenanceType.ROUTINE)
    priority = models.CharField('Öncelik', max_length=20, choices=[
        ('low', 'Düşük'),
        ('normal', 'Normal'),
        ('high', 'Yüksek'),
        ('urgent', 'Acil'),
    ], default='normal', db_index=True)
    
    title = models.CharField('Başlık', max_length=200)
    description = models.TextField('Açıklama')
    
    # Tarihler
    planned_date = models.DateField('Planlanan Tarih', null=True, blank=True)
    start_date = models.DateField('Başlangıç Tarihi', null=True, blank=True)
    completed_date = models.DateField('Tamamlanma Tarihi', null=True, blank=True)
    
    status = models.CharField('Durum', max_length=20, choices=MaintenanceStatus.choices, default=MaintenanceStatus.PLANNED)
    
    # Personel
    reported_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reported_bungalov_maintenances',
        verbose_name='Bildiren'
    )
    assigned_to = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_bungalov_maintenances',
        verbose_name='Atanan Personel'
    )
    
    # Maliyet
    estimated_cost = models.DecimalField('Tahmini Maliyet', max_digits=10, decimal_places=2, default=0)
    actual_cost = models.DecimalField('Gerçek Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    completion_notes = models.TextField('Tamamlanma Notları', blank=True)
    
    class Meta:
        verbose_name = 'Bungalov Bakımı'
        verbose_name_plural = 'Bungalov Bakımları'
        ordering = ['-planned_date', '-created_at']
    
    def __str__(self):
        return f"{self.bungalov.code} - {self.title}"


# ==================== EKİPMAN YÖNETİMİ ====================

class BungalovEquipment(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Ekipmanları
    """
    EQUIPMENT_CATEGORY_CHOICES = [
        ('kitchen', 'Mutfak'),
        ('electronic', 'Elektronik'),
        ('furniture', 'Mobilya'),
        ('outdoor', 'Dış Mekan'),
        ('safety', 'Güvenlik'),
        ('cleaning', 'Temizlik'),
        ('other', 'Diğer'),
    ]
    
    EQUIPMENT_STATUS_CHOICES = [
        ('available', 'Mevcut'),
        ('missing', 'Eksik'),
        ('broken', 'Arızalı'),
        ('replaced', 'Değiştirildi'),
    ]
    
    bungalov = models.ForeignKey(
        Bungalov,
        on_delete=models.CASCADE,
        related_name='equipments',
        verbose_name='Bungalov'
    )
    
    name = models.CharField('Ekipman Adı', max_length=200)
    category = models.CharField('Kategori', max_length=20, choices=EQUIPMENT_CATEGORY_CHOICES, default='other')
    brand = models.CharField('Marka', max_length=100, blank=True)
    model = models.CharField('Model', max_length=100, blank=True)
    serial_number = models.CharField('Seri No', max_length=100, blank=True)
    
    status = models.CharField('Durum', max_length=20, choices=EQUIPMENT_STATUS_CHOICES, default='available')
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Bungalov Ekipmanı'
        verbose_name_plural = 'Bungalov Ekipmanları'
        ordering = ['category', 'name']
    
    def __str__(self):
        return f"{self.bungalov.code} - {self.name}"


# ==================== FİYATLANDIRMA ====================

class BungalovPrice(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Fiyatlandırması
    Sezonluk, haftalık, aylık fiyatlandırma
    """
    PRICE_TYPE_CHOICES = [
        ('nightly', 'Gecelik'),
        ('weekly', 'Haftalık'),
        ('monthly', 'Aylık'),
    ]
    
    SEASON_CHOICES = [
        ('low', 'Düşük Sezon'),
        ('mid', 'Orta Sezon'),
        ('high', 'Yüksek Sezon'),
        ('peak', 'Pik Sezon'),
    ]
    
    bungalov_type = models.ForeignKey(
        BungalovType,
        on_delete=models.CASCADE,
        related_name='prices',
        verbose_name='Bungalov Tipi'
    )
    
    price_type = models.CharField('Fiyat Tipi', max_length=20, choices=PRICE_TYPE_CHOICES, default='nightly')
    season = models.CharField('Sezon', max_length=20, choices=SEASON_CHOICES, default='mid')
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi')
    end_date = models.DateField('Bitiş Tarihi')
    
    # Fiyatlar
    base_price = models.DecimalField('Temel Fiyat', max_digits=10, decimal_places=2)
    weekend_price = models.DecimalField('Hafta Sonu Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    holiday_price = models.DecimalField('Bayram Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Minimum Konaklama
    min_nights = models.IntegerField('Minimum Gece', default=2, validators=[MinValueValidator(1)])
    min_nights_weekend = models.IntegerField('Hafta Sonu Minimum Gece', default=3, validators=[MinValueValidator(1)])
    min_nights_holiday = models.IntegerField('Bayram Minimum Gece', default=7, validators=[MinValueValidator(1)])
    
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Bungalov Fiyatı'
        verbose_name_plural = 'Bungalov Fiyatları'
        ordering = ['-start_date']
    
    def __str__(self):
        return f"{self.bungalov_type.name} - {self.get_price_type_display()} - {self.start_date}"


# ==================== VOUCHER ŞABLONLARI ====================

class BungalovVoucherTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Voucher Şablonları
    """
    name = models.CharField('Şablon Adı', max_length=200)
    code = models.CharField('Kod', max_length=50, unique=True, db_index=True)
    description = models.TextField('Açıklama', blank=True)
    
    # HTML Template
    template_html = models.TextField('HTML Şablon', help_text='Jinja2 template syntax kullanın')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan mı?', default=False)
    
    class Meta:
        verbose_name = 'Voucher Şablonu'
        verbose_name_plural = 'Voucher Şablonları'
        ordering = ['name']
    
    def __str__(self):
        return self.name


# ==================== VOUCHER'LAR ====================

class BungalovVoucher(TimeStampedModel, SoftDeleteModel):
    """
    Bungalov Rezervasyon Voucher'ları
    """
    reservation = models.OneToOneField(
        BungalovReservation,
        on_delete=models.CASCADE,
        related_name='voucher',
        verbose_name='Rezervasyon'
    )
    
    voucher_code = models.CharField('Voucher Kodu', max_length=50, unique=True, db_index=True)
    voucher_token = models.CharField('Voucher Token', max_length=100, unique=True, db_index=True)
    
    template = models.ForeignKey(
        BungalovVoucherTemplate,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='vouchers',
        verbose_name='Şablon'
    )
    
    voucher_html = models.TextField('Voucher HTML', blank=True)
    voucher_pdf = models.FileField('Voucher PDF', upload_to='bungalovs/vouchers/', null=True, blank=True)
    
    # Durum
    is_sent = models.BooleanField('Gönderildi mi?', default=False)
    sent_at = models.DateTimeField('Gönderilme Tarihi', null=True, blank=True)
    sent_method = models.CharField('Gönderim Yöntemi', max_length=20, blank=True, choices=[
        ('email', 'E-posta'),
        ('sms', 'SMS'),
        ('whatsapp', 'WhatsApp'),
    ])
    
    # Ödeme Linki
    payment_link = models.URLField('Ödeme Linki', blank=True)
    payment_token = models.CharField('Ödeme Token', max_length=100, blank=True)
    payment_expires_at = models.DateTimeField('Ödeme Linki Son Geçerlilik', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Bungalov Voucher'
        verbose_name_plural = 'Bungalov Voucher\'ları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.voucher_code} - {self.reservation.reservation_code}"

