"""
Resepsiyon (Ön Büro) Modelleri
Rezervasyon odaklı profesyonel otel resepsiyon yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from datetime import date, timedelta
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== REZERVASYON DURUMLARI ====================

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
    CORPORATE = 'corporate', 'Kurumsal'


# ==================== REZERVASYON MODELİ ====================

class Reservation(TimeStampedModel, SoftDeleteModel):
    """
    Rezervasyon Modeli
    Otel rezervasyonlarını yönetir
    """
    # Temel Bilgiler
    reservation_code = models.CharField('Rezervasyon Kodu', max_length=50, unique=True, db_index=True)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Otel'
    )
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Oda Tipi'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservations',
        verbose_name='Oda Numarası'
    )
    
    # Müşteri Bilgileri
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Müşteri'
    )
    
    # Tarih Bilgileri
    check_in_date = models.DateField('Check-in Tarihi', db_index=True)
    check_out_date = models.DateField('Check-out Tarihi', db_index=True)
    check_in_time = models.TimeField('Check-in Saati', default='14:00')
    check_out_time = models.TimeField('Check-out Saati', default='12:00')
    
    # Misafir Bilgileri
    adult_count = models.IntegerField('Yetişkin Sayısı', default=1, validators=[MinValueValidator(1)])
    child_count = models.IntegerField('Çocuk Sayısı', default=0, validators=[MinValueValidator(0)])
    child_ages = models.JSONField('Çocuk Yaşları', default=list, blank=True,
                                  help_text='Örn: [5, 8]')
    
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
        related_name='reservations',
        verbose_name='Rezervasyon Acentesi',
        help_text='Rezervasyonu yapan acente (varsa)'
    )
    reservation_channel = models.ForeignKey(
        'channels.Channel',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservations',
        verbose_name='Rezervasyon Kanalı',
        help_text='Booking.com, Expedia vb. (varsa)'
    )
    
    # Fiyatlandırma
    room_rate = models.DecimalField('Oda Fiyatı', max_digits=10, decimal_places=2, default=0,
                                   help_text='Gecelik oda fiyatı')
    is_manual_price = models.BooleanField('Manuel Fiyat mı?', default=False,
                                         help_text='Fiyat manuel olarak girildi mi?')
    total_nights = models.IntegerField('Toplam Gece', default=1)
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
    is_comp = models.BooleanField('Comp Rezervasyon mu?', default=False,
                                 help_text='Ücretsiz rezervasyon')
    is_no_show = models.BooleanField('No-Show mu?', default=False,
                                    help_text='Rezervasyon yapıp gelmeyen misafir')
    no_show_reason = models.TextField('No-Show Nedeni', blank=True)
    
    # Özel İstekler ve Notlar
    special_requests = models.TextField('Özel İstekler', blank=True)
    internal_notes = models.TextField('İç Notlar', blank=True, help_text='Personel için notlar')
    
    # Durum Bilgileri
    is_checked_in = models.BooleanField('Check-In Yapıldı mı?', default=False)
    is_checked_out = models.BooleanField('Check-Out Yapıldı mı?', default=False)
    checked_in_at = models.DateTimeField('Check-In Tarihi', null=True, blank=True)
    checked_out_at = models.DateTimeField('Check-Out Tarihi', null=True, blank=True)
    early_check_in = models.BooleanField('Erken Check-in mi?', default=False,
                                       help_text='Normal check-in saatinden önce giriş yapıldı')
    late_check_out = models.BooleanField('Geç Check-out mu?', default=False,
                                        help_text='Normal check-out saatinden sonra çıkış yapıldı')
    early_check_in_fee = models.DecimalField('Erken Check-in Ücreti', max_digits=10, decimal_places=2, default=0)
    late_check_out_fee = models.DecimalField('Geç Check-out Ücreti', max_digits=10, decimal_places=2, default=0)
    
    # İptal Bilgileri
    is_cancelled = models.BooleanField('İptal Edildi mi?', default=False)
    cancelled_at = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    cancellation_refund_amount = models.DecimalField('İptal İade Tutarı', max_digits=12, decimal_places=2, default=0)
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_reservations',
        verbose_name='Oluşturan Kullanıcı'
    )
    updated_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='updated_reservations',
        verbose_name='Güncelleyen Kullanıcı'
    )
    deleted_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='deleted_reservations',
        verbose_name='Silen Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Rezervasyon'
        verbose_name_plural = 'Rezervasyonlar'
        ordering = ['-check_in_date', '-created_at']
        indexes = [
            models.Index(fields=['hotel', 'status', 'check_in_date']),
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
            if not self.is_manual_price:
                self.room_rate = Decimal('0')
        else:
            # Toplam tutarı hesapla
            if self.room_rate and self.total_nights:
                base_amount = Decimal(str(self.room_rate)) * Decimal(str(self.total_nights))
                
                # İndirim hesaplama
                if self.discount_type == 'percentage' and self.discount_percentage > 0:
                    discount = base_amount * (Decimal(str(self.discount_percentage)) / Decimal('100'))
                    self.discount_amount = discount
                elif self.discount_type == 'fixed' and self.discount_amount > 0:
                    pass  # discount_amount zaten set edilmiş
                
                self.total_amount = base_amount - self.discount_amount + self.tax_amount
        
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
        """Toplam ödenen tutarı hesapla (payments tablosundan)"""
        return self.payments.filter(is_deleted=False).aggregate(
            total=models.Sum('payment_amount')
        )['total'] or Decimal('0')
    
    def update_total_paid(self):
        """Toplam ödenen tutarı güncelle"""
        self.total_paid = self.calculate_total_paid()
        self.save(update_fields=['total_paid'])


# ==================== REZERVASYON MİSAFİRLERİ ====================

class ReservationGuest(TimeStampedModel):
    """
    Rezervasyon Misafir Bilgileri
    Yetişkin ve çocuk misafirlerin detaylı bilgileri
    """
    GUEST_TYPE_CHOICES = [
        ('adult', 'Yetişkin'),
        ('child', 'Çocuk'),
    ]
    
    GENDER_CHOICES = [
        ('male', 'Erkek'),
        ('female', 'Kadın'),
        ('other', 'Diğer'),
    ]
    
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='guests',
        verbose_name='Rezervasyon'
    )
    
    guest_type = models.CharField('Misafir Tipi', max_length=20, choices=GUEST_TYPE_CHOICES)
    guest_order = models.IntegerField('Misafir Sırası', default=1,
                                     help_text='Misafir sırası (1, 2, 3...)')
    
    # Kişisel Bilgiler
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    gender = models.CharField('Cinsiyet', max_length=10, choices=GENDER_CHOICES, blank=True)
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    age = models.IntegerField('Yaş', null=True, blank=True,
                             help_text='Çocuklar için yaş bilgisi')
    
    # Kimlik Bilgileri
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    passport_no = models.CharField('Pasaport No', max_length=50, blank=True)
    passport_serial_no = models.CharField('Pasaport Seri No', max_length=20, blank=True)
    id_serial_no = models.CharField('Kimlik Seri No', max_length=20, blank=True,
                                   help_text='TC Kimlik seri no')
    nationality = models.CharField('Vatandaşlık', max_length=100, default='Türkiye')
    
    # İletişim (Opsiyonel - Ana müşteri bilgileri kullanılabilir)
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Misafiri'
        verbose_name_plural = 'Rezervasyon Misafirleri'
        ordering = ['reservation', 'guest_type', 'guest_order']
        indexes = [
            models.Index(fields=['reservation', 'guest_type']),
            models.Index(fields=['tc_no']),
            models.Index(fields=['passport_no']),
        ]
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.first_name} {self.last_name} ({self.get_guest_type_display()})"


# ==================== REZERVASYON ÖDEMELERİ ====================

class ReservationPayment(TimeStampedModel, SoftDeleteModel):
    """
    Rezervasyon Ödeme Kayıtları
    Rezervasyon üzerinden yapılan tüm ödemeler
    """
    PAYMENT_METHOD_CHOICES = [
        ('cash', 'Nakit'),
        ('credit_card', 'Kredi Kartı'),
        ('debit_card', 'Banka Kartı'),
        ('transfer', 'Havale/EFT'),
        ('check', 'Çek'),
        ('other', 'Diğer'),
    ]
    
    PAYMENT_TYPE_CHOICES = [
        ('advance', 'Ön Ödeme'),
        ('full', 'Tam Ödeme'),
        ('partial', 'Kısmi Ödeme'),
        ('refund', 'İade'),
    ]
    
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='payments',
        verbose_name='Rezervasyon'
    )
    
    payment_date = models.DateField('Ödeme Tarihi', default=date.today)
    payment_amount = models.DecimalField('Ödeme Tutarı', max_digits=12, decimal_places=2,
                                       validators=[MinValueValidator(Decimal('0.01'))])
    payment_method = models.CharField('Ödeme Yöntemi', max_length=20, choices=PAYMENT_METHOD_CHOICES)
    payment_type = models.CharField('Ödeme Tipi', max_length=20, choices=PAYMENT_TYPE_CHOICES)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Entegrasyon
    cash_transaction = models.ForeignKey(
        'finance.CashTransaction',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservation_payments',
        verbose_name='Kasa İşlemi'
    )
    accounting_payment = models.ForeignKey(
        'accounting.Payment',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservation_payments',
        verbose_name='Muhasebe Ödemesi'
    )
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    receipt_no = models.CharField('Fiş No', max_length=50, blank=True)
    
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_reservation_payments',
        verbose_name='Oluşturan Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Rezervasyon Ödemesi'
        verbose_name_plural = 'Rezervasyon Ödemeleri'
        ordering = ['-payment_date', '-created_at']
        indexes = [
            models.Index(fields=['reservation', 'payment_date']),
            models.Index(fields=['payment_method']),
        ]
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.payment_amount} {self.currency} ({self.get_payment_method_display()})"


# ==================== REZERVASYON ZAMAN ÇİZELGESİ ====================

class ReservationTimeline(TimeStampedModel):
    """
    Rezervasyon Güncelleme Geçmişi
    Rezervasyon üzerinde yapılan tüm değişikliklerin kaydı
    """
    ACTION_TYPE_CHOICES = [
        ('created', 'Oluşturuldu'),
        ('updated', 'Güncellendi'),
        ('checkin', 'Check-in Yapıldı'),
        ('checkout', 'Check-out Yapıldı'),
        ('payment', 'Ödeme Eklendi'),
        ('cancelled', 'İptal Edildi'),
        ('no_show', 'No-Show İşaretlendi'),
        ('comp', 'Comp Olarak İşaretlendi'),
        ('status_changed', 'Durum Değişti'),
    ]
    
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='timeline',
        verbose_name='Rezervasyon'
    )
    
    action_type = models.CharField('İşlem Tipi', max_length=50, choices=ACTION_TYPE_CHOICES)
    action_description = models.TextField('İşlem Açıklaması', blank=True)
    
    # Değişiklik Detayları (JSON)
    old_value = models.JSONField('Eski Değer', default=dict, blank=True,
                                 help_text='Değişiklik öncesi değerler')
    new_value = models.JSONField('Yeni Değer', default=dict, blank=True,
                                help_text='Değişiklik sonrası değerler')
    
    user = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservation_timeline_actions',
        verbose_name='Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Rezervasyon Zaman Çizelgesi'
        verbose_name_plural = 'Rezervasyon Zaman Çizelgeleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['reservation', 'action_type']),
            models.Index(fields=['created_at']),
        ]
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.get_action_type_display()} ({self.created_at})"


# ==================== REZERVASYON VOUCHER'LARI ====================

class ReservationVoucher(TimeStampedModel):
    """
    Rezervasyon Voucher'ları
    Dinamik şablonlarla voucher oluşturma
    Ödeme entegrasyonu ile online ödeme desteği
    """
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='vouchers',
        verbose_name='Rezervasyon'
    )
    
    voucher_template = models.ForeignKey(
        'reception.VoucherTemplate',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='vouchers',
        verbose_name='Voucher Şablonu'
    )
    
    voucher_code = models.CharField('Voucher Kodu', max_length=50, unique=True, db_index=True)
    voucher_data = models.JSONField('Voucher Verileri', default=dict, blank=True,
                                   help_text='Şablon için veri')
    
    # Token Link (Müşteri erişimi için)
    access_token = models.CharField('Erişim Token', max_length=64, unique=True, db_index=True,
                                   null=True, blank=True,
                                   help_text='Müşteriye gönderilecek token link için')
    token_expires_at = models.DateTimeField('Token Geçerlilik Tarihi', null=True, blank=True,
                                           help_text='Token ne zaman geçersiz olacak')
    
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
    payment_amount = models.DecimalField('Ödeme Tutarı', max_digits=12, decimal_places=2, default=0,
                                        help_text='Voucher için ödenecek tutar (0 ise rezervasyon kalan tutarı)')
    payment_currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, blank=True,
                                     help_text='Kredi kartı, havale vb.')
    
    # Ödeme İşlemi İlişkisi
    payment_transaction = models.ForeignKey(
        'payments.PaymentTransaction',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='vouchers',
        verbose_name='Ödeme İşlemi',
        help_text='Voucher için yapılan ödeme işlemi'
    )
    
    # Ödeme Bilgileri (JSON)
    payment_info = models.JSONField('Ödeme Bilgileri', default=dict, blank=True,
                                   help_text='Ödeme detayları, kart bilgileri vb.')
    
    # Ödeme Tarihleri
    payment_date = models.DateTimeField('Ödeme Tarihi', null=True, blank=True)
    payment_completed_at = models.DateTimeField('Ödeme Tamamlanma Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Voucher'
        verbose_name_plural = 'Rezervasyon Voucher\'ları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['reservation']),
            models.Index(fields=['voucher_code']),
            models.Index(fields=['access_token']),
            models.Index(fields=['payment_status']),
        ]
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.voucher_code}"
    
    def get_payment_url(self):
        """Voucher ödeme sayfası URL'i"""
        from django.urls import reverse
        return reverse('reception:voucher_payment', kwargs={'token': self.access_token})
    
    def get_public_url(self):
        """Voucher görüntüleme sayfası URL'i (token ile)"""
        from django.urls import reverse
        return reverse('reception:voucher_view', kwargs={'token': self.access_token})
    
    def get_whatsapp_url(self, phone=None):
        """WhatsApp gönderme URL'i (wa.me)"""
        if not phone:
            customer = self.reservation.customer
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
        
        message = f"Rezervasyon Voucher'ınız: {self.get_public_url()}"
        return f"https://wa.me/{phone}?text={message}"
    
    def get_email_subject(self):
        """Email konu başlığı"""
        return f"Rezervasyon Voucher - {self.reservation.reservation_code}"
    
    def get_email_body(self):
        """Email içeriği"""
        return f"""
        Merhaba,
        
        Rezervasyon voucher'ınız hazır!
        
        Rezervasyon Kodu: {self.reservation.reservation_code}
        Voucher Kodu: {self.voucher_code}
        
        Voucher'ınızı görüntülemek için: {self.get_public_url()}
        
        Ödeme yapmak için: {self.get_payment_url()}
        
        İyi günler dileriz.
        """
    
    def calculate_payment_amount(self):
        """Ödeme tutarını hesapla (rezervasyon kalan tutarı)"""
        if self.payment_amount and self.payment_amount > 0:
            return self.payment_amount
        return self.reservation.get_remaining_amount()


# ==================== VOUCHER ŞABLONLARI ====================

class VoucherTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Voucher Şablonları
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
        verbose_name = 'Voucher Şablonu'
        verbose_name_plural = 'Voucher Şablonları'
        ordering = ['-is_default', 'name']
    
    def __str__(self):
        return self.name


# ==================== GÜN SONU İŞLEMLERİ (END OF DAY / NIGHT AUDIT) ====================

class EndOfDayOperationStatus(models.TextChoices):
    """Gün Sonu İşlem Durumları"""
    PENDING = 'pending', 'Beklemede'
    RUNNING = 'running', 'Çalışıyor'
    COMPLETED = 'completed', 'Tamamlandı'
    FAILED = 'failed', 'Başarısız'
    ROLLED_BACK = 'rolled_back', 'Geri Alındı'


class EndOfDayAutomationType(models.TextChoices):
    """Gün Sonu Otomasyon Türleri"""
    SCHEDULED = 'scheduled', 'Zaman Planlı'
    MANUAL = 'manual', 'Manuel'
    AUTOMATIC = 'automatic', 'Tam Otomatik'


class EndOfDayReportType(models.TextChoices):
    """Gün Sonu Rapor Türleri"""
    SUMMARY = 'summary', 'Özet'
    FINANCIAL = 'financial', 'Finansal'
    OPERATIONAL = 'operational', 'Operasyonel'
    GUEST = 'guest', 'Misafir'
    MANAGEMENT = 'management', 'Yönetim'


class EndOfDayStepStatus(models.TextChoices):
    """Gün Sonu Adım Durumları"""
    PENDING = 'pending', 'Beklemede'
    RUNNING = 'running', 'Çalışıyor'
    COMPLETED = 'completed', 'Tamamlandı'
    FAILED = 'failed', 'Başarısız'


class EndOfDayNoShowAction(models.TextChoices):
    """No-Show İşlem Türleri"""
    CANCEL = 'cancel', 'İptal Et'
    MOVE_TO_TOMORROW = 'move_to_tomorrow', 'Yarına Al'


class EndOfDayOperation(TimeStampedModel, SoftDeleteModel):
    """
    Gün Sonu İşlemi (Night Audit / End of Day)
    Her otel için ayrı gün sonu işlemleri yapılır
    """
    # Otel Bağlantısı (ZORUNLU - Hotel bazlı çalışır)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='end_of_day_operations',
        verbose_name='Otel'
    )
    
    # Tarih Bilgileri
    operation_date = models.DateField('İşlem Tarihi', db_index=True,
                                      help_text='Gün sonu işleminin yapıldığı tarih')
    program_date = models.DateField('Program Tarihi', db_index=True,
                                   help_text='İşlemin yapıldığı günün tarihi')
    
    # Durum Bilgileri
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=EndOfDayOperationStatus.choices,
        default=EndOfDayOperationStatus.PENDING,
        db_index=True
    )
    
    # İşlem Türü
    is_async = models.BooleanField('Asenkron mu?', default=False,
                                   help_text='Asenkron olarak çalıştırılsın mı?')
    automation_type = models.CharField(
        'Otomasyon Türü',
        max_length=20,
        choices=EndOfDayAutomationType.choices,
        default=EndOfDayAutomationType.MANUAL,
        db_index=True
    )
    
    # Ayarlar ve Sonuçlar
    settings = models.JSONField('İşlem Ayarları', default=dict, blank=True,
                               help_text='İşlem sırasında kullanılan ayarlar')
    results = models.JSONField('İşlem Sonuçları', default=dict, blank=True,
                              help_text='İşlem sonuçları ve istatistikler')
    
    # Zaman Bilgileri
    started_at = models.DateTimeField('Başlangıç Zamanı', null=True, blank=True)
    completed_at = models.DateTimeField('Bitiş Zamanı', null=True, blank=True)
    
    # Hata ve Rollback
    error_message = models.TextField('Hata Mesajı', blank=True,
                                    help_text='İşlem sırasında oluşan hatalar')
    rollback_data = models.JSONField('Rollback Verileri', default=dict, blank=True,
                                    help_text='Geri alma işlemi için gerekli veriler')
    
    # Kullanıcı Takibi
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_end_of_day_operations',
        verbose_name='Oluşturan Kullanıcı'
    )
    
    # Ek Bilgiler
    notes = models.TextField('Notlar', blank=True)
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Gün Sonu İşlemi'
        verbose_name_plural = 'Gün Sonu İşlemleri'
        ordering = ['-operation_date', '-created_at']
        indexes = [
            models.Index(fields=['hotel', 'operation_date']),
            models.Index(fields=['hotel', 'status']),
            models.Index(fields=['operation_date', 'status']),
            models.Index(fields=['automation_type']),
        ]
        unique_together = [('hotel', 'operation_date')]  # Her otel için günde bir işlem
    
    def __str__(self):
        return f"{self.hotel.name} - {self.operation_date.strftime('%d.%m.%Y')} - {self.get_status_display()}"
    
    def can_rollback(self):
        """Rollback yapılabilir mi?"""
        return (
            self.status in [EndOfDayOperationStatus.COMPLETED, EndOfDayOperationStatus.FAILED] and
            self.rollback_data and
            len(self.rollback_data) > 0
        )
    
    def get_progress_percentage(self):
        """İlerleme yüzdesini hesapla"""
        if self.status == EndOfDayOperationStatus.COMPLETED:
            return 100
        elif self.status == EndOfDayOperationStatus.FAILED:
            return 0
        
        # Adımların durumuna göre hesapla
        total_steps = self.steps.count()
        if total_steps == 0:
            return 0
        
        completed_steps = self.steps.filter(status=EndOfDayStepStatus.COMPLETED).count()
        return int((completed_steps / total_steps) * 100) if total_steps > 0 else 0
    
    def get_duration(self):
        """İşlem süresini hesapla"""
        if self.started_at and self.completed_at:
            return self.completed_at - self.started_at
        return None


class EndOfDaySettings(TimeStampedModel):
    """
    Gün Sonu Ayarları
    Her otel için ayrı ayarlar (unique hotel)
    """
    # Otel Bağlantısı (ZORUNLU ve UNIQUE - Her otel için tek ayar)
    hotel = models.OneToOneField(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='end_of_day_settings',
        verbose_name='Otel',
        unique=True
    )
    
    # Pre-Audit Kontrol Ayarları
    stop_if_room_price_zero = models.BooleanField(
        'Oda Fiyatı SIFIR İse Durdur!',
        default=True,
        help_text='Sıfır fiyatlı oda varsa gün sonu işlemini durdur'
    )
    stop_if_advance_folio_balance_not_zero = models.BooleanField(
        'Peşin Folyo Balansı Sıfır Değilse Durdur!',
        default=True,
        help_text='Peşin ödemeli rezervasyonda bakiye varsa durdur'
    )
    check_checkout_folios = models.BooleanField(
        'Checkout Olmuş Folyoları Kontrol Et!',
        default=True,
        help_text='Check-out yapılmış folyoları kontrol et'
    )
    
    # Otomatik İşlem Ayarları
    cancel_no_show_reservations = models.BooleanField(
        'Gelmeyen Rezervasyonları İptal Et veya Yarına Al!',
        default=False,
        help_text='No-show rezervasyonları iptal et veya yarına al'
    )
    no_show_action = models.CharField(
        'No-Show İşlemi',
        max_length=20,
        choices=EndOfDayNoShowAction.choices,
        default=EndOfDayNoShowAction.CANCEL,
        help_text='No-show rezervasyonları için yapılacak işlem'
    )
    
    extend_non_checkout_reservations = models.BooleanField(
        'CheckOut Olmamış Konaklayanları UZAT!',
        default=False,
        help_text='Check-out yapılmamış rezervasyonları otomatik uzat'
    )
    extend_days = models.IntegerField(
        'Uzatma Gün Sayısı',
        default=1,
        validators=[MinValueValidator(1)],
        help_text='Kaç gün uzatılacak?'
    )
    
    cancel_room_change_plans = models.BooleanField(
        'Oda Değişim Planlarını İPTAL Et!',
        default=False,
        help_text='Planlanmış ama gerçekleşmemiş oda değişimlerini iptal et'
    )
    
    # Otomasyon Ayarları
    auto_run_time = models.TimeField(
        'Otomatik Çalışma Saati',
        null=True,
        blank=True,
        help_text='Gün sonu işleminin otomatik başlatılacağı saat (örn: 02:00)'
    )
    automation_type = models.CharField(
        'Otomasyon Türü',
        max_length=20,
        choices=EndOfDayAutomationType.choices,
        default=EndOfDayAutomationType.MANUAL,
        help_text='Gün sonu işleminin nasıl çalıştırılacağı'
    )
    
    # Genel Ayarlar
    is_active = models.BooleanField('Aktif mi?', default=True)
    enable_rollback = models.BooleanField(
        'Rollback Aktif mi?',
        default=True,
        help_text='Hata durumunda geri alma işlemi yapılabilsin mi?'
    )
    
    # Ek Bilgiler
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Gün Sonu Ayarı'
        verbose_name_plural = 'Gün Sonu Ayarları'
        ordering = ['hotel__name']
    
    def __str__(self):
        return f"{self.hotel.name} - Gün Sonu Ayarları"
    
    @classmethod
    def get_or_create_for_hotel(cls, hotel):
        """Otel için ayarları al veya oluştur"""
        settings, created = cls.objects.get_or_create(
            hotel=hotel,
            defaults={
                'stop_if_room_price_zero': True,
                'stop_if_advance_folio_balance_not_zero': True,
                'check_checkout_folios': True,
                'cancel_no_show_reservations': False,
                'extend_non_checkout_reservations': False,
                'cancel_room_change_plans': False,
                'automation_type': EndOfDayAutomationType.MANUAL,
                'is_active': True,
                'enable_rollback': True,
            }
        )
        return settings


class EndOfDayOperationStep(TimeStampedModel):
    """
    Gün Sonu İşlem Adımları
    Her işlem için adım adım takip
    """
    # İşlem Bağlantısı
    operation = models.ForeignKey(
        EndOfDayOperation,
        on_delete=models.CASCADE,
        related_name='steps',
        verbose_name='Gün Sonu İşlemi'
    )
    
    # Adım Bilgileri
    step_name = models.CharField('Adım Adı', max_length=200,
                                help_text='İşlem adımının adı')
    step_order = models.IntegerField('Sıra Numarası',
                                     help_text='Adımın sırası (1, 2, 3, ...)')
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=EndOfDayStepStatus.choices,
        default=EndOfDayStepStatus.PENDING,
        db_index=True
    )
    
    # Zaman Bilgileri
    started_at = models.DateTimeField('Başlangıç Zamanı', null=True, blank=True)
    completed_at = models.DateTimeField('Bitiş Zamanı', null=True, blank=True)
    
    # Sonuç ve Hata
    result_data = models.JSONField('Sonuç Verileri', default=dict, blank=True,
                                  help_text='Adımın sonuç verileri')
    error_message = models.TextField('Hata Mesajı', blank=True)
    rollback_data = models.JSONField('Rollback Verileri', default=dict, blank=True)
    
    # Ek Bilgiler
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Gün Sonu İşlem Adımı'
        verbose_name_plural = 'Gün Sonu İşlem Adımları'
        ordering = ['operation', 'step_order']
        indexes = [
            models.Index(fields=['operation', 'step_order']),
            models.Index(fields=['operation', 'status']),
        ]
        unique_together = [('operation', 'step_order')]  # Her işlem için adım sırası benzersiz
    
    def __str__(self):
        return f"{self.operation} - {self.step_order}. {self.step_name}"
    
    def get_execution_time(self):
        """Çalışma süresini hesapla"""
        if self.started_at and self.completed_at:
            return self.completed_at - self.started_at
        return None


class EndOfDayReport(TimeStampedModel):
    """
    Gün Sonu Raporları
    Her işlem için oluşturulan raporlar
    """
    # İşlem Bağlantısı
    operation = models.ForeignKey(
        EndOfDayOperation,
        on_delete=models.CASCADE,
        related_name='reports',
        verbose_name='Gün Sonu İşlemi'
    )
    
    # Rapor Bilgileri
    report_type = models.CharField(
        'Rapor Türü',
        max_length=20,
        choices=EndOfDayReportType.choices,
        db_index=True
    )
    report_data = models.JSONField('Rapor Verileri', default=dict,
                                  help_text='Rapor içeriği (JSON)')
    
    # Dosya
    report_file = models.FileField(
        'Rapor Dosyası',
        upload_to='reception/end_of_day/reports/%Y/%m/',
        null=True,
        blank=True,
        help_text='PDF/Excel rapor dosyası'
    )
    export_format = models.CharField(
        'Export Formatı',
        max_length=10,
        choices=[('pdf', 'PDF'), ('excel', 'Excel'), ('json', 'JSON'), ('csv', 'CSV')],
        default='pdf'
    )
    
    # Zaman ve Export
    generated_at = models.DateTimeField('Oluşturulma Zamanı', auto_now_add=True)
    exported_to = models.JSONField('Gönderildiği Sistemler', default=list, blank=True,
                                  help_text='Hangi sistemlere gönderildi?')
    
    # Ek Bilgiler
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Gün Sonu Raporu'
        verbose_name_plural = 'Gün Sonu Raporları'
        ordering = ['-generated_at']
        indexes = [
            models.Index(fields=['operation', 'report_type']),
            models.Index(fields=['generated_at']),
        ]
    
    def __str__(self):
        return f"{self.operation} - {self.get_report_type_display()} - {self.generated_at.strftime('%d.%m.%Y %H:%M')}"


class EndOfDayJournalEntry(TimeStampedModel):
    """
    Gün Sonu Muhasebe Fişleri
    Muhasebe modülü ile entegrasyon
    """
    # İşlem Bağlantısı
    operation = models.ForeignKey(
        EndOfDayOperation,
        on_delete=models.CASCADE,
        related_name='journal_entries',
        verbose_name='Gün Sonu İşlemi'
    )
    
    # Muhasebe Bağlantısı
    journal_entry = models.ForeignKey(
        'accounting.JournalEntry',
        on_delete=models.CASCADE,
        related_name='end_of_day_operations',
        verbose_name='Yevmiye Kaydı'
    )
    
    # Fiş Bilgileri
    entry_type = models.CharField(
        'Fiş Türü',
        max_length=20,
        choices=[
            ('revenue', 'Gelir'),
            ('expense', 'Gider'),
            ('transfer', 'Transfer'),
        ],
        db_index=True
    )
    department = models.CharField(
        'Departman',
        max_length=20,
        choices=[
            ('room', 'Konaklama'),
            ('f&b', 'Yiyecek-İçecek'),
            ('spa', 'Spa'),
            ('extra', 'Ek Hizmetler'),
        ],
        db_index=True
    )
    market_segment = models.CharField(
        'Pazar Segmenti',
        max_length=20,
        choices=[
            ('direct', 'Direkt'),
            ('online', 'Online'),
            ('agency', 'Acente'),
            ('corporate', 'Kurumsal'),
            ('group', 'Grup'),
            ('walk_in', 'Gel-Al'),
        ],
        db_index=True,
        blank=True
    )
    
    # Finansal Bilgiler
    amount = models.DecimalField('Tutar', max_digits=15, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    class Meta:
        verbose_name = 'Gün Sonu Muhasebe Fişi'
        verbose_name_plural = 'Gün Sonu Muhasebe Fişleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['operation', 'entry_type']),
            models.Index(fields=['operation', 'department']),
            models.Index(fields=['operation', 'market_segment']),
        ]
    
    def __str__(self):
        return f"{self.operation} - {self.get_entry_type_display()} - {self.amount} {self.currency}"

