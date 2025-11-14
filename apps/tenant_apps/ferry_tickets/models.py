"""
Feribot Bileti Modelleri
Profesyonel feribot bilet satış ve yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from datetime import date, timedelta
import secrets
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== FERİBOT BİLET DURUMLARI ====================

class FerryTicketStatus(models.TextChoices):
    """Feribot Bileti Durumları"""
    PENDING = 'pending', 'Beklemede'
    CONFIRMED = 'confirmed', 'Onaylandı'
    CANCELLED = 'cancelled', 'İptal Edildi'
    USED = 'used', 'Kullanıldı'
    EXPIRED = 'expired', 'Süresi Doldu'
    REFUNDED = 'refunded', 'İade Edildi'


class FerryTicketSource(models.TextChoices):
    """Bilet Kaynakları"""
    DIRECT = 'direct', 'Direkt'
    ONLINE = 'online', 'Online'
    PHONE = 'phone', 'Telefon'
    EMAIL = 'email', 'E-posta'
    WALK_IN = 'walk_in', 'Gel-Al'
    AGENCY = 'agency', 'Acente'
    API = 'api', 'API Entegrasyonu'


class FerryTicketType(models.TextChoices):
    """Bilet Tipleri"""
    ADULT = 'adult', 'Yetişkin'
    CHILD = 'child', 'Çocuk'
    INFANT = 'infant', 'Bebek'
    STUDENT = 'student', 'Öğrenci'
    SENIOR = 'senior', 'Yaşlı'
    DISABLED = 'disabled', 'Engelli'


class FerryVehicleType(models.TextChoices):
    """Araç Tipleri"""
    NONE = 'none', 'Araçsız'
    CAR = 'car', 'Otomobil'
    MOTORCYCLE = 'motorcycle', 'Motosiklet'
    VAN = 'van', 'Minibüs'
    TRUCK = 'truck', 'Kamyon'
    BUS = 'bus', 'Otobüs'
    CARAVAN = 'caravan', 'Karavan'


# ==================== FERİBOT MODELİ ====================

class Ferry(TimeStampedModel, SoftDeleteModel):
    """
    Feribot Modeli
    Feribot şirketi ve gemi bilgileri
    """
    name = models.CharField('Feribot Adı', max_length=200)
    code = models.SlugField('Feribot Kodu', max_length=50, unique=True, db_index=True)
    company_name = models.CharField('Şirket Adı', max_length=200)
    company_code = models.CharField('Şirket Kodu', max_length=50, blank=True)
    
    # İletişim Bilgileri
    phone = models.CharField('Telefon', max_length=20, blank=True)
    email = models.EmailField('E-posta', blank=True)
    website = models.URLField('Web Sitesi', blank=True)
    
    # Gemi Bilgileri
    capacity_passengers = models.IntegerField('Yolcu Kapasitesi', default=0)
    capacity_vehicles = models.IntegerField('Araç Kapasitesi', default=0)
    vessel_type = models.CharField('Gemi Tipi', max_length=50, blank=True,
                                  help_text='Örn: Fast Ferry, Conventional Ferry')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Feribot'
        verbose_name_plural = 'Feribotlar'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.name} ({self.company_name})"


# ==================== FERİBOT ROTASI ====================

class FerryRoute(TimeStampedModel, SoftDeleteModel):
    """
    Feribot Rotası
    Örn: İstanbul-Bodrum, Çeşme-Brindisi
    """
    name = models.CharField('Rota Adı', max_length=200)
    code = models.SlugField('Rota Kodu', max_length=50, unique=True, db_index=True)
    
    # Kalkış ve Varış Limanları
    departure_port = models.CharField('Kalkış Limanı', max_length=200)
    departure_city = models.CharField('Kalkış Şehri', max_length=100)
    departure_country = models.CharField('Kalkış Ülkesi', max_length=100, default='Türkiye')
    departure_latitude = models.DecimalField('Kalkış Enlem', max_digits=9, decimal_places=6, null=True, blank=True)
    departure_longitude = models.DecimalField('Kalkış Boylam', max_digits=9, decimal_places=6, null=True, blank=True)
    
    arrival_port = models.CharField('Varış Limanı', max_length=200)
    arrival_city = models.CharField('Varış Şehri', max_length=100)
    arrival_country = models.CharField('Varış Ülkesi', max_length=100, default='Türkiye')
    arrival_latitude = models.DecimalField('Varış Enlem', max_digits=9, decimal_places=6, null=True, blank=True)
    arrival_longitude = models.DecimalField('Varış Boylam', max_digits=9, decimal_places=6, null=True, blank=True)
    
    # Rota Bilgileri
    distance_nautical_miles = models.DecimalField('Mesafe (Deniz Mili)', max_digits=8, decimal_places=2, null=True, blank=True)
    estimated_duration_hours = models.DecimalField('Tahmini Süre (Saat)', max_digits=5, decimal_places=2, null=True, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_international = models.BooleanField('Uluslararası Rota mu?', default=False)
    
    class Meta:
        verbose_name = 'Feribot Rotası'
        verbose_name_plural = 'Feribot Rotaları'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.departure_port} - {self.arrival_port}"


# ==================== FERİBOT SEFERİ ====================

class FerrySchedule(TimeStampedModel, SoftDeleteModel):
    """
    Feribot Seferi
    Belirli bir tarih ve saatteki sefer
    """
    ferry = models.ForeignKey(
        Ferry,
        on_delete=models.CASCADE,
        related_name='schedules',
        verbose_name='Feribot'
    )
    route = models.ForeignKey(
        FerryRoute,
        on_delete=models.CASCADE,
        related_name='schedules',
        verbose_name='Rota'
    )
    
    # Sefer Tarihi ve Saati
    departure_date = models.DateField('Kalkış Tarihi', db_index=True)
    departure_time = models.TimeField('Kalkış Saati')
    arrival_date = models.DateField('Varış Tarihi', db_index=True)
    arrival_time = models.TimeField('Varış Saati')
    
    # Fiyatlandırma
    adult_price = models.DecimalField('Yetişkin Fiyatı', max_digits=10, decimal_places=2, default=0)
    child_price = models.DecimalField('Çocuk Fiyatı', max_digits=10, decimal_places=2, default=0)
    infant_price = models.DecimalField('Bebek Fiyatı', max_digits=10, decimal_places=2, default=0)
    student_price = models.DecimalField('Öğrenci Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    senior_price = models.DecimalField('Yaşlı Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    disabled_price = models.DecimalField('Engelli Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Araç Fiyatları
    car_price = models.DecimalField('Otomobil Fiyatı', max_digits=10, decimal_places=2, default=0)
    motorcycle_price = models.DecimalField('Motosiklet Fiyatı', max_digits=10, decimal_places=2, default=0)
    van_price = models.DecimalField('Minibüs Fiyatı', max_digits=10, decimal_places=2, default=0)
    truck_price = models.DecimalField('Kamyon Fiyatı', max_digits=10, decimal_places=2, default=0)
    bus_price = models.DecimalField('Otobüs Fiyatı', max_digits=10, decimal_places=2, default=0)
    caravan_price = models.DecimalField('Karavan Fiyatı', max_digits=10, decimal_places=2, default=0)
    
    # Kapasite ve Durum
    available_passenger_seats = models.IntegerField('Müsait Yolcu Koltuğu', default=0)
    available_vehicle_spots = models.IntegerField('Müsait Araç Yeri', default=0)
    total_passenger_seats = models.IntegerField('Toplam Yolcu Koltuğu', default=0)
    total_vehicle_spots = models.IntegerField('Toplam Araç Yeri', default=0)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_cancelled = models.BooleanField('İptal Edildi mi?', default=False)
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    
    # API Entegrasyonu
    external_id = models.CharField('Harici ID', max_length=100, blank=True,
                                   help_text='API\'den gelen sefer ID\'si')
    external_data = models.JSONField('Harici Veri', default=dict, blank=True,
                                    help_text='API\'den gelen ek veriler')
    api_synced_at = models.DateTimeField('API Senkronizasyon Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Seferi'
        verbose_name_plural = 'Feribot Seferleri'
        ordering = ['departure_date', 'departure_time']
        indexes = [
            models.Index(fields=['departure_date']),
            models.Index(fields=['route', 'departure_date']),
            models.Index(fields=['is_active', 'departure_date']),
        ]
    
    def __str__(self):
        return f"{self.route} - {self.departure_date} {self.departure_time}"
    
    def get_price_by_ticket_type(self, ticket_type):
        """Bilet tipine göre fiyat döndür"""
        price_map = {
            FerryTicketType.ADULT: self.adult_price,
            FerryTicketType.CHILD: self.child_price,
            FerryTicketType.INFANT: self.infant_price,
            FerryTicketType.STUDENT: self.student_price or self.adult_price,
            FerryTicketType.SENIOR: self.senior_price or self.adult_price,
            FerryTicketType.DISABLED: self.disabled_price or self.adult_price,
        }
        return price_map.get(ticket_type, self.adult_price)
    
    def get_vehicle_price(self, vehicle_type):
        """Araç tipine göre fiyat döndür"""
        price_map = {
            FerryVehicleType.CAR: self.car_price,
            FerryVehicleType.MOTORCYCLE: self.motorcycle_price,
            FerryVehicleType.VAN: self.van_price,
            FerryVehicleType.TRUCK: self.truck_price,
            FerryVehicleType.BUS: self.bus_price,
            FerryVehicleType.CARAVAN: self.caravan_price,
        }
        return price_map.get(vehicle_type, Decimal('0'))


# ==================== FERİBOT BİLETİ ====================

class FerryTicket(TimeStampedModel, SoftDeleteModel):
    """
    Feribot Bileti
    Ana bilet kaydı
    """
    # Temel Bilgiler
    ticket_code = models.CharField('Bilet Kodu', max_length=50, unique=True, db_index=True)
    
    # Sefer Bilgileri
    schedule = models.ForeignKey(
        FerrySchedule,
        on_delete=models.CASCADE,
        related_name='tickets',
        verbose_name='Sefer'
    )
    
    # Müşteri Bilgileri
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.CASCADE,
        related_name='ferry_tickets',
        verbose_name='Müşteri'
    )
    
    # Yolcu ve Araç Bilgileri
    adult_count = models.IntegerField('Yetişkin Sayısı', default=1, validators=[MinValueValidator(0)])
    child_count = models.IntegerField('Çocuk Sayısı', default=0, validators=[MinValueValidator(0)])
    infant_count = models.IntegerField('Bebek Sayısı', default=0, validators=[MinValueValidator(0)])
    vehicle_type = models.CharField('Araç Tipi', max_length=20, choices=FerryVehicleType.choices,
                                   default=FerryVehicleType.NONE)
    vehicle_plate = models.CharField('Araç Plakası', max_length=20, blank=True)
    vehicle_brand = models.CharField('Araç Markası', max_length=50, blank=True)
    vehicle_model = models.CharField('Araç Modeli', max_length=50, blank=True)
    
    # Rezervasyon Bilgileri
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=FerryTicketStatus.choices,
        default=FerryTicketStatus.PENDING,
        db_index=True
    )
    source = models.CharField(
        'Kaynak',
        max_length=20,
        choices=FerryTicketSource.choices,
        default=FerryTicketSource.DIRECT,
        db_index=True
    )
    
    # Rezervasyon Aracıları
    reservation_agent = models.ForeignKey(
        'sales.Agency',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='ferry_tickets',
        verbose_name='Rezervasyon Acentesi'
    )
    
    # Fiyatlandırma
    adult_unit_price = models.DecimalField('Yetişkin Birim Fiyatı', max_digits=10, decimal_places=2, default=0)
    child_unit_price = models.DecimalField('Çocuk Birim Fiyatı', max_digits=10, decimal_places=2, default=0)
    infant_unit_price = models.DecimalField('Bebek Birim Fiyatı', max_digits=10, decimal_places=2, default=0)
    vehicle_price = models.DecimalField('Araç Fiyatı', max_digits=10, decimal_places=2, default=0)
    
    total_amount = models.DecimalField('Toplam Tutar', max_digits=12, decimal_places=2, default=0)
    discount_type = models.CharField('İndirim Tipi', max_length=20,
                                    choices=[('percentage', 'Yüzde'), ('fixed', 'Sabit Tutar')],
                                    blank=True, null=True)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, default=0)
    discount_percentage = models.DecimalField('İndirim Yüzdesi', max_digits=5, decimal_places=2, default=0,
                                            validators=[MinValueValidator(Decimal('0'))])
    tax_amount = models.DecimalField('Vergi Tutarı', max_digits=10, decimal_places=2, default=0)
    total_paid = models.DecimalField('Ödenen Tutar', max_digits=12, decimal_places=2, default=0)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY',
                               choices=[('TRY', 'Türk Lirası'), ('USD', 'US Dollar'),
                                       ('EUR', 'Euro'), ('GBP', 'British Pound')])
    
    # Özel Durumlar
    is_comp = models.BooleanField('Ücretsiz Bilet mi?', default=False)
    special_requests = models.TextField('Özel İstekler', blank=True)
    internal_notes = models.TextField('İç Notlar', blank=True, help_text='Personel için notlar')
    
    # İptal Bilgileri
    is_cancelled = models.BooleanField('İptal Edildi mi?', default=False)
    cancelled_at = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    cancelled_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='cancelled_ferry_tickets',
        verbose_name='İptal Eden Kullanıcı'
    )
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    cancellation_refund_amount = models.DecimalField('İptal İade Tutarı', max_digits=12, decimal_places=2, default=0)
    
    # Kullanım Bilgileri
    is_used = models.BooleanField('Kullanıldı mı?', default=False)
    used_at = models.DateTimeField('Kullanım Tarihi', null=True, blank=True)
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_ferry_tickets',
        verbose_name='Oluşturan Kullanıcı'
    )
    updated_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='updated_ferry_tickets',
        verbose_name='Güncelleyen Kullanıcı'
    )
    deleted_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='deleted_ferry_tickets',
        verbose_name='Silen Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Feribot Bileti'
        verbose_name_plural = 'Feribot Biletleri'
        ordering = ['-schedule__departure_date', '-created_at']
        indexes = [
            models.Index(fields=['ticket_code']),
            models.Index(fields=['schedule']),
            models.Index(fields=['status']),
            models.Index(fields=['customer']),
        ]
    
    def __str__(self):
        return f"{self.ticket_code} - {self.schedule}"
    
    @property
    def departure_date(self):
        """Kalkış tarihi (schedule'dan)"""
        return self.schedule.departure_date
    
    @property
    def departure_time(self):
        """Kalkış saati (schedule'dan)"""
        return self.schedule.departure_time
    
    @property
    def route(self):
        """Rota (schedule'dan)"""
        return self.schedule.route
    
    def save(self, *args, **kwargs):
        """Bilet kaydedilirken otomatik hesaplamalar yap"""
        # Comp bilet ise toplam tutar 0
        if self.is_comp:
            self.total_amount = Decimal('0')
        else:
            # Toplam tutarı hesapla
            total = Decimal('0')
            
            # Yolcu fiyatları
            total += Decimal(str(self.adult_unit_price)) * self.adult_count
            total += Decimal(str(self.child_unit_price)) * self.child_count
            total += Decimal(str(self.infant_unit_price)) * self.infant_count
            
            # Araç fiyatı
            if self.vehicle_type != FerryVehicleType.NONE:
                total += Decimal(str(self.vehicle_price))
            
            # İndirim
            if self.discount_type == 'percentage' and self.discount_percentage > 0:
                discount = total * (Decimal(str(self.discount_percentage)) / Decimal('100'))
                self.discount_amount = discount
            elif self.discount_type == 'fixed' and self.discount_amount > 0:
                pass  # discount_amount zaten set edilmiş
            
            self.total_amount = total - self.discount_amount + self.tax_amount
        
        super().save(*args, **kwargs)
    
    def calculate_total_amount(self):
        """Toplam tutarı hesapla"""
        return self.total_amount
    
    def get_remaining_amount(self):
        """Kalan tutarı hesapla"""
        return max(Decimal('0'), self.total_amount - self.total_paid)
    
    def update_total_paid(self):
        """Toplam ödenen tutarı güncelle"""
        total = self.payments.filter(is_deleted=False).aggregate(
            total=models.Sum('payment_amount')
        )['total'] or Decimal('0')
        self.total_paid = total
        self.save(update_fields=['total_paid'])
    
    def is_paid(self):
        """Bilet tamamen ödendi mi?"""
        return self.total_paid >= self.total_amount


# ==================== BİLET YOLCU BİLGİLERİ ====================

class FerryTicketGuest(TimeStampedModel, SoftDeleteModel):
    """
    Bilet Yolcu Bilgileri
    Her yolcu için ayrı kayıt
    """
    ticket = models.ForeignKey(
        FerryTicket,
        on_delete=models.CASCADE,
        related_name='guests',
        verbose_name='Bilet'
    )
    
    # Yolcu Bilgileri
    ticket_type = models.CharField('Bilet Tipi', max_length=20, choices=FerryTicketType.choices,
                                   default=FerryTicketType.ADULT)
    guest_order = models.IntegerField('Yolcu Sırası', default=1)
    
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    gender = models.CharField('Cinsiyet', max_length=10,
                             choices=[('male', 'Erkek'), ('female', 'Kadın'), ('other', 'Diğer')],
                             blank=True)
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    age = models.IntegerField('Yaş', null=True, blank=True)
    
    # Kimlik Bilgileri
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    passport_no = models.CharField('Pasaport No', max_length=50, blank=True)
    passport_serial_no = models.CharField('Pasaport Seri No', max_length=20, blank=True)
    id_serial_no = models.CharField('Kimlik Seri No', max_length=20, blank=True)
    nationality = models.CharField('Uyruk', max_length=100, default='Türkiye')
    
    # İletişim
    phone = models.CharField('Telefon', max_length=20, blank=True)
    email = models.EmailField('E-posta', blank=True)
    
    class Meta:
        verbose_name = 'Bilet Yolcusu'
        verbose_name_plural = 'Bilet Yolcuları'
        ordering = ['ticket', 'guest_order']
        unique_together = [['ticket', 'guest_order']]
    
    def __str__(self):
        return f"{self.ticket.ticket_code} - {self.first_name} {self.last_name}"


# ==================== BİLET ÖDEMELERİ ====================

class FerryTicketPayment(TimeStampedModel, SoftDeleteModel):
    """
    Bilet Ödemeleri
    Bilet için yapılan ödemeler
    """
    ticket = models.ForeignKey(
        FerryTicket,
        on_delete=models.CASCADE,
        related_name='payments',
        verbose_name='Bilet'
    )
    
    payment_date = models.DateField('Ödeme Tarihi', db_index=True)
    payment_amount = models.DecimalField('Ödeme Tutarı', max_digits=12, decimal_places=2)
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50,
                                     choices=[
                                         ('cash', 'Nakit'),
                                         ('credit_card', 'Kredi Kartı'),
                                         ('debit_card', 'Banka Kartı'),
                                         ('transfer', 'Havale/EFT'),
                                         ('check', 'Çek'),
                                         ('online', 'Online Ödeme'),
                                     ])
    payment_type = models.CharField('Ödeme Tipi', max_length=20,
                                   choices=[
                                       ('advance', 'Ön Ödeme'),
                                       ('full', 'Tam Ödeme'),
                                       ('refund', 'İade'),
                                   ],
                                   default='advance')
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Ödeme Detayları
    payment_reference = models.CharField('Ödeme Referansı', max_length=100, blank=True,
                                        help_text='Banka referans numarası, işlem ID vb.')
    payment_info = models.JSONField('Ödeme Bilgileri', default=dict, blank=True,
                                   help_text='Ek ödeme bilgileri')
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_ferry_ticket_payments',
        verbose_name='Oluşturan Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Bilet Ödemesi'
        verbose_name_plural = 'Bilet Ödemeleri'
        ordering = ['-payment_date', '-created_at']
        indexes = [
            models.Index(fields=['ticket']),
            models.Index(fields=['payment_date']),
        ]
    
    def __str__(self):
        return f"{self.ticket.ticket_code} - {self.payment_amount} {self.currency}"


# ==================== BİLET VOUCHER'LARI ====================

class FerryTicketVoucher(TimeStampedModel):
    """
    Bilet Voucher'ları
    Dinamik şablonlarla voucher oluşturma
    Ödeme entegrasyonu ile online ödeme desteği
    """
    ticket = models.ForeignKey(
        FerryTicket,
        on_delete=models.CASCADE,
        related_name='vouchers',
        verbose_name='Bilet'
    )
    
    voucher_template = models.ForeignKey(
        'ferry_tickets.FerryTicketVoucherTemplate',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='vouchers',
        verbose_name='Voucher Şablonu'
    )
    
    voucher_code = models.CharField('Voucher Kodu', max_length=50, unique=True, db_index=True)
    voucher_data = models.JSONField('Voucher Verileri', default=dict, blank=True)
    
    # Token Link (Müşteri erişimi için)
    access_token = models.CharField('Erişim Token', max_length=64, unique=True, db_index=True,
                                   null=True, blank=True)
    token_expires_at = models.DateTimeField('Token Geçerlilik Tarihi', null=True, blank=True)
    
    # Durum
    is_sent = models.BooleanField('Gönderildi mi?', default=False)
    sent_at = models.DateTimeField('Gönderilme Tarihi', null=True, blank=True)
    sent_via = models.CharField('Gönderim Yöntemi', max_length=20, blank=True,
                               choices=[('email', 'E-posta'), ('whatsapp', 'WhatsApp'), ('sms', 'SMS'), ('link', 'Link')])
    
    # Ödeme Entegrasyonu
    PAYMENT_STATUS_CHOICES = [
        ('pending', 'Ödeme Bekliyor'),
        ('partial', 'Kısmi Ödendi'),
        ('paid', 'Ödendi'),
        ('failed', 'Ödeme Başarısız'),
        ('cancelled', 'İptal Edildi'),
        ('refunded', 'İade Edildi'),
    ]
    payment_status = models.CharField('Ödeme Durumu', max_length=20, choices=PAYMENT_STATUS_CHOICES,
                                      default='pending', db_index=True)
    payment_amount = models.DecimalField('Ödeme Tutarı', max_digits=12, decimal_places=2, default=0)
    payment_currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, blank=True)
    
    # Ödeme İşlemi İlişkisi
    payment_transaction = models.ForeignKey(
        'payments.PaymentTransaction',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='ferry_ticket_vouchers',
        verbose_name='Ödeme İşlemi'
    )
    
    # Ödeme Bilgileri
    payment_info = models.JSONField('Ödeme Bilgileri', default=dict, blank=True)
    payment_date = models.DateTimeField('Ödeme Tarihi', null=True, blank=True)
    payment_completed_at = models.DateTimeField('Ödeme Tamamlanma Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Bilet Voucher'
        verbose_name_plural = 'Bilet Voucher\'ları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['ticket']),
            models.Index(fields=['voucher_code']),
            models.Index(fields=['access_token']),
            models.Index(fields=['payment_status']),
        ]
    
    def __str__(self):
        return f"{self.ticket.ticket_code} - {self.voucher_code}"
    
    def get_payment_url(self):
        """Voucher ödeme sayfası URL'i"""
        from django.urls import reverse
        return reverse('ferry_tickets:voucher_payment', kwargs={'token': self.access_token})
    
    def get_public_url(self):
        """Voucher görüntüleme sayfası URL'i"""
        from django.urls import reverse
        return reverse('ferry_tickets:voucher_view', kwargs={'token': self.access_token})
    
    def calculate_payment_amount(self):
        """Ödeme tutarını hesapla"""
        if self.payment_amount and self.payment_amount > 0:
            return self.payment_amount
        return self.ticket.get_remaining_amount()
    
    def get_whatsapp_url(self, phone=None):
        """WhatsApp gönderme URL'i (wa.me)"""
        if not phone:
            customer = self.ticket.customer
            if customer and customer.phone:
                phone = customer.phone
            else:
                return None
        
        # Telefon numarasını temizle (sadece rakamlar)
        import re
        phone = re.sub(r'\D', '', phone)
        if phone.startswith('0'):
            phone = '90' + phone[1:]
        elif not phone.startswith('90'):
            phone = '90' + phone
        
        public_url = self.get_public_url()
        message = f"Feribot Bileti Voucher'ınız: {public_url}"
        return f"https://wa.me/{phone}?text={message}"
    
    def get_email_subject(self):
        """Email konu başlığı"""
        return f"Feribot Bileti Voucher - {self.ticket.ticket_code}"
    
    def get_email_body(self):
        """Email içeriği"""
        return f"""
        Merhaba,
        
        Feribot biletiniz için voucher hazır!
        
        Bilet Kodu: {self.ticket.ticket_code}
        Voucher Kodu: {self.voucher_code}
        
        Voucher'ınızı görüntülemek için: {self.get_public_url()}
        
        Ödeme yapmak için: {self.get_payment_url()}
        
        İyi yolculuklar dileriz.
        """


# ==================== VOUCHER ŞABLONLARI ====================

class FerryTicketVoucherTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Bilet Voucher Şablonları
    Dinamik voucher şablonları
    """
    name = models.CharField('Şablon Adı', max_length=200)
    code = models.SlugField('Şablon Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Şablon İçeriği
    template_html = models.TextField('HTML Şablon', help_text='HTML şablon içeriği')
    template_css = models.TextField('CSS Stilleri', blank=True, help_text='Özel CSS stilleri')
    
    # Ayarlar
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Şablon mu?', default=False)
    
    class Meta:
        verbose_name = 'Bilet Voucher Şablonu'
        verbose_name_plural = 'Bilet Voucher Şablonları'
        ordering = ['-is_default', 'name']
    
    def __str__(self):
        return self.name


# ==================== FERİBOT API KONFİGÜRASYONLARI ====================

class FerryAPIConfiguration(TimeStampedModel, SoftDeleteModel):
    """
    Feribot API Konfigürasyonları
    FerryOS ve diğer feribot API'leri için dinamik yönetim
    """
    name = models.CharField('API Adı', max_length=200)
    code = models.SlugField('API Kodu', max_length=50, unique=True, db_index=True)
    provider = models.CharField('Sağlayıcı', max_length=100,
                               choices=[
                                   ('ferryos', 'FerryOS'),
                                   ('custom', 'Özel API'),
                                   ('other', 'Diğer'),
                               ])
    
    # API Ayarları
    api_url = models.URLField('API URL', help_text='API endpoint URL\'si')
    api_key = models.CharField('API Key', max_length=200, blank=True)
    api_secret = models.CharField('API Secret', max_length=200, blank=True)
    username = models.CharField('Kullanıcı Adı', max_length=100, blank=True)
    password = models.CharField('Şifre', max_length=200, blank=True)
    
    # API Ayarları (JSON)
    api_settings = models.JSONField('API Ayarları', default=dict, blank=True,
                                   help_text='Ek API ayarları (timeout, retry vb.)')
    
    # Senkronizasyon Ayarları
    auto_sync_schedules = models.BooleanField('Seferleri Otomatik Senkronize Et', default=False)
    sync_frequency_hours = models.IntegerField('Senkronizasyon Sıklığı (Saat)', default=24,
                                              help_text='Kaç saatte bir senkronize edilecek')
    last_sync_at = models.DateTimeField('Son Senkronizasyon', null=True, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu mu?', default=True)
    
    class Meta:
        verbose_name = 'Feribot API Konfigürasyonu'
        verbose_name_plural = 'Feribot API Konfigürasyonları'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.name} ({self.provider})"


# ==================== API SENKRONİZASYON KAYITLARI ====================

class FerryAPISync(TimeStampedModel):
    """
    API Senkronizasyon Kayıtları
    API'den çekilen seferlerin kayıtları
    """
    api_config = models.ForeignKey(
        FerryAPIConfiguration,
        on_delete=models.CASCADE,
        related_name='syncs',
        verbose_name='API Konfigürasyonu'
    )
    
    sync_type = models.CharField('Senkronizasyon Tipi', max_length=20,
                                 choices=[
                                     ('schedules', 'Seferler'),
                                     ('prices', 'Fiyatlar'),
                                     ('availability', 'Müsaitlik'),
                                     ('full', 'Tam Senkronizasyon'),
                                 ])
    
    # Sonuçlar
    status = models.CharField('Durum', max_length=20,
                             choices=[
                                 ('running', 'Çalışıyor'),
                                 ('completed', 'Tamamlandı'),
                                 ('success', 'Başarılı'),
                                 ('failed', 'Başarısız'),
                                 ('partial', 'Kısmi Başarılı'),
                             ],
                             default='running')
    schedules_fetched = models.IntegerField('Çekilen Sefer Sayısı', default=0)
    schedules_created = models.IntegerField('Oluşturulan Sefer Sayısı', default=0)
    schedules_updated = models.IntegerField('Güncellenen Sefer Sayısı', default=0)
    schedules_failed = models.IntegerField('Başarısız Sefer Sayısı', default=0)
    
    # Hata Bilgileri
    error_message = models.TextField('Hata Mesajı', blank=True)
    error_details = models.JSONField('Hata Detayları', default=dict, blank=True)
    
    # Zamanlama
    started_at = models.DateTimeField('Başlangıç Tarihi', auto_now_add=True)
    completed_at = models.DateTimeField('Tamamlanma Tarihi', null=True, blank=True)
    duration_seconds = models.IntegerField('Süre (Saniye)', null=True, blank=True)
    
    # Kullanıcı Takibi
    started_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='ferry_api_syncs',
        verbose_name='Başlatan Kullanıcı'
    )
    
    # Senkronizasyon Verileri
    sync_data = models.JSONField('Senkronizasyon Verileri', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'API Senkronizasyon Kaydı'
        verbose_name_plural = 'API Senkronizasyon Kayıtları'
        ordering = ['-started_at']
        indexes = [
            models.Index(fields=['api_config', '-started_at']),
            models.Index(fields=['status']),
        ]
    
    def __str__(self):
        return f"{self.api_config.name} - {self.sync_type} ({self.started_at})"

