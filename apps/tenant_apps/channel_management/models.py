"""
Kanal Yönetimi Modelleri
OTA entegrasyonları için kapsamlı kanal yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel
from apps.modules.models import ChannelTemplate  # Public schema'dan import


# ==================== KANAL ŞABLONLARI ====================
# ChannelTemplate artık apps.modules.models içinde (public schema için)

# ==================== TENANT KANAL KONFİGÜRASYONU ====================

class ChannelConfiguration(TimeStampedModel, SoftDeleteModel):
    """
    Tenant Kanal Konfigürasyonu
    Her tenant'ın kendi kanal ayarları
    """
    tenant = models.ForeignKey(
        'tenants.Tenant',
        on_delete=models.CASCADE,
        related_name='channel_configurations',
        verbose_name='Tenant'
    )
    template = models.ForeignKey(
        ChannelTemplate,
        on_delete=models.CASCADE,
        related_name='configurations',
        verbose_name='Kanal Şablonu'
    )
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='channel_configurations',
        verbose_name='Otel',
        null=True,
        blank=True
    )
    
    # Kanal Adı (tenant özel)
    name = models.CharField('Kanal Adı', max_length=200,
                           help_text='Bu tenant için görünecek isim')
    
    # API Bilgileri (Şifrelenmiş olarak saklanmalı - production'da encryption kullanılmalı)
    api_credentials = models.JSONField('API Bilgileri', default=dict,
                                       help_text='API key, secret, username, password vb.')
    
    # API Ayarları
    api_endpoint = models.CharField('API Endpoint', max_length=500, blank=True)
    api_timeout = models.IntegerField('API Timeout (saniye)', default=30)
    api_retry_count = models.IntegerField('API Retry Sayısı', default=3)
    
    # Senkronizasyon Ayarları
    sync_enabled = models.BooleanField('Senkronizasyon Aktif', default=True)
    sync_interval = models.IntegerField('Senkronizasyon Aralığı (dakika)', default=60,
                                      help_text='Otomatik senkronizasyon için')
    last_sync_at = models.DateTimeField('Son Senkronizasyon', null=True, blank=True)
    next_sync_at = models.DateTimeField('Sonraki Senkronizasyon', null=True, blank=True)
    
    # Fiyat ve Müsaitlik Ayarları
    auto_sync_pricing = models.BooleanField('Otomatik Fiyat Senkronizasyonu', default=True)
    auto_sync_availability = models.BooleanField('Otomatik Müsaitlik Senkronizasyonu', default=True)
    price_markup_percent = models.DecimalField('Fiyat Artış Oranı (%)', 
                                              max_digits=5, decimal_places=2,
                                              default=Decimal('0.00'),
                                              help_text='Kanal fiyatına eklenecek yüzde')
    price_markup_amount = models.DecimalField('Fiyat Artış Tutarı', 
                                            max_digits=10, decimal_places=2,
                                            default=Decimal('0.00'),
                                            help_text='Kanal fiyatına eklenecek sabit tutar')
    
    # Komisyon Ayarları
    commission_rate = models.DecimalField('Komisyon Oranı (%)', 
                                         max_digits=5, decimal_places=2,
                                         default=Decimal('0.00'),
                                         validators=[MinValueValidator(Decimal('0.00')),
                                                    MaxValueValidator(Decimal('100.00'))])
    commission_calculation = models.CharField('Komisyon Hesaplama', max_length=20,
                                              choices=[
                                                  ('percentage', 'Yüzde'),
                                                  ('fixed', 'Sabit Tutar'),
                                                  ('tiered', 'Kademeli'),
                                              ],
                                              default='percentage')
    
    # Rezervasyon Ayarları
    auto_confirm_reservations = models.BooleanField('Otomatik Rezervasyon Onayı', default=False)
    reservation_timeout = models.IntegerField('Rezervasyon Timeout (dakika)', default=15,
                                             help_text='Rezervasyon onayı için süre')
    allow_modifications = models.BooleanField('Değişikliklere İzin Ver', default=True)
    allow_cancellations = models.BooleanField('İptallere İzin Ver', default=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True,
                                      help_text='Test ortamında çalıştır')
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Kanal Konfigürasyonu'
        verbose_name_plural = 'Kanal Konfigürasyonları'
        ordering = ['-created_at']
        unique_together = [['tenant', 'template', 'hotel']]
        indexes = [
            models.Index(fields=['tenant', 'template']),
            models.Index(fields=['hotel']),
            models.Index(fields=['is_active']),
            models.Index(fields=['sync_enabled']),
        ]
    
    def __str__(self):
        hotel_name = f" - {self.hotel.name}" if self.hotel else ""
        return f"{self.name} ({self.template.name}){hotel_name}"
    
    def get_effective_commission_rate(self):
        """Etkin komisyon oranını döndür"""
        if self.commission_rate > 0:
            return self.commission_rate
        return self.template.default_commission_rate


# ==================== KANAL SENKRONİZASYON KAYITLARI ====================

class ChannelSync(TimeStampedModel):
    """
    Kanal Senkronizasyon Kayıtları
    Her senkronizasyon işleminin log'u
    """
    SYNC_TYPE_CHOICES = [
        ('pricing', 'Fiyat Senkronizasyonu'),
        ('availability', 'Müsaitlik Senkronizasyonu'),
        ('reservation', 'Rezervasyon Senkronizasyonu'),
        ('inventory', 'Envanter Senkronizasyonu'),
        ('full', 'Tam Senkronizasyon'),
    ]
    
    SYNC_DIRECTION_CHOICES = [
        ('push', 'Push (Sistemden Kanala)'),
        ('pull', 'Pull (Kanaldan Sisteme)'),
        ('bidirectional', 'İki Yönlü'),
    ]
    
    SYNC_STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('running', 'Çalışıyor'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
        ('partial', 'Kısmi Başarılı'),
    ]
    
    configuration = models.ForeignKey(
        ChannelConfiguration,
        on_delete=models.CASCADE,
        related_name='syncs',
        verbose_name='Kanal Konfigürasyonu'
    )
    
    sync_type = models.CharField('Senkronizasyon Tipi', max_length=20,
                                 choices=SYNC_TYPE_CHOICES, default='full')
    direction = models.CharField('Yön', max_length=20,
                               choices=SYNC_DIRECTION_CHOICES, default='bidirectional')
    status = models.CharField('Durum', max_length=20,
                             choices=SYNC_STATUS_CHOICES, default='pending')
    
    # İstatistikler
    total_items = models.IntegerField('Toplam Öğe', default=0)
    successful_items = models.IntegerField('Başarılı Öğe', default=0)
    failed_items = models.IntegerField('Başarısız Öğe', default=0)
    
    # Zaman Bilgileri
    started_at = models.DateTimeField('Başlangıç Zamanı', null=True, blank=True)
    completed_at = models.DateTimeField('Bitiş Zamanı', null=True, blank=True)
    duration_seconds = models.IntegerField('Süre (saniye)', null=True, blank=True)
    
    # Hata Bilgileri
    error_message = models.TextField('Hata Mesajı', blank=True)
    error_details = models.JSONField('Hata Detayları', default=dict, blank=True)
    
    # Sonuç
    result_data = models.JSONField('Sonuç Verisi', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kanal Senkronizasyonu'
        verbose_name_plural = 'Kanal Senkronizasyonları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['configuration', '-created_at']),
            models.Index(fields=['status']),
            models.Index(fields=['sync_type']),
        ]
    
    def __str__(self):
        return f"{self.configuration.name} - {self.get_sync_type_display()} ({self.get_status_display()})"
    
    def calculate_duration(self):
        """Senkronizasyon süresini hesapla"""
        if self.started_at and self.completed_at:
            delta = self.completed_at - self.started_at
            self.duration_seconds = int(delta.total_seconds())
            return self.duration_seconds
        return None


# ==================== KANAL REZERVASYONLARI ====================

class ChannelReservation(TimeStampedModel, SoftDeleteModel):
    """
    Kanal Rezervasyonları
    Kanallardan gelen veya kanallara gönderilen rezervasyonlar
    """
    RESERVATION_STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('confirmed', 'Onaylandı'),
        ('cancelled', 'İptal Edildi'),
        ('modified', 'Değiştirildi'),
        ('no_show', 'Gelmedi'),
        ('checked_in', 'Check-In Yapıldı'),
        ('checked_out', 'Check-Out Yapıldı'),
    ]
    
    configuration = models.ForeignKey(
        ChannelConfiguration,
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Kanal Konfigürasyonu'
    )
    
    # Sistem Rezervasyonu (eğer varsa)
    system_reservation = models.ForeignKey(
        'reception.Reservation',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='channel_reservations',
        verbose_name='Sistem Rezervasyonu'
    )
    
    # Kanal Rezervasyon Bilgileri
    channel_reservation_id = models.CharField('Kanal Rezervasyon ID', max_length=200, db_index=True)
    channel_reservation_code = models.CharField('Kanal Rezervasyon Kodu', max_length=200, blank=True)
    
    # Rezervasyon Bilgileri
    guest_name = models.CharField('Misafir Adı', max_length=200)
    guest_email = models.EmailField('Misafir E-posta', blank=True)
    guest_phone = models.CharField('Misafir Telefon', max_length=50, blank=True)
    
    check_in_date = models.DateField('Check-in Tarihi')
    check_out_date = models.DateField('Check-out Tarihi')
    adult_count = models.IntegerField('Yetişkin Sayısı', default=1)
    child_count = models.IntegerField('Çocuk Sayısı', default=0)
    
    # Oda Bilgileri
    room_type_name = models.CharField('Oda Tipi Adı', max_length=200)
    room_number = models.CharField('Oda Numarası', max_length=50, blank=True)
    
    # Fiyat Bilgileri
    total_amount = models.DecimalField('Toplam Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    commission_amount = models.DecimalField('Komisyon Tutarı', max_digits=10, decimal_places=2,
                                           default=Decimal('0.00'))
    
    # Durum
    status = models.CharField('Durum', max_length=20,
                             choices=RESERVATION_STATUS_CHOICES, default='pending')
    
    # Zaman Bilgileri
    reservation_date = models.DateTimeField('Rezervasyon Tarihi', null=True, blank=True)
    confirmation_date = models.DateTimeField('Onay Tarihi', null=True, blank=True)
    cancellation_date = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    
    # Ek Bilgiler
    special_requests = models.TextField('Özel İstekler', blank=True)
    channel_data = models.JSONField('Kanal Verisi', default=dict, blank=True,
                                   help_text='Kanalın gönderdiği ham veri')
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Kanal Rezervasyonu'
        verbose_name_plural = 'Kanal Rezervasyonları'
        ordering = ['-created_at']
        unique_together = [['configuration', 'channel_reservation_id']]
        indexes = [
            models.Index(fields=['configuration', '-created_at']),
            models.Index(fields=['channel_reservation_id']),
            models.Index(fields=['status']),
            models.Index(fields=['check_in_date', 'check_out_date']),
        ]
    
    def __str__(self):
        return f"{self.configuration.name} - {self.guest_name} ({self.channel_reservation_code})"


# ==================== KANAL FİYATLARI ====================

class ChannelPricing(TimeStampedModel, SoftDeleteModel):
    """
    Kanal Fiyatları
    Kanallara gönderilen veya kanallardan alınan fiyat bilgileri
    """
    configuration = models.ForeignKey(
        ChannelConfiguration,
        on_delete=models.CASCADE,
        related_name='pricings',
        verbose_name='Kanal Konfigürasyonu'
    )
    
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.CASCADE,
        related_name='channel_pricings',
        verbose_name='Oda Tipi'
    )
    
    # RoomPrice ile bağlantı (opsiyonel - eğer RoomPrice kullanılıyorsa)
    room_price = models.ForeignKey(
        'hotels.RoomPrice',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='channel_pricings',
        verbose_name='Oda Fiyatı',
        help_text='Sistemdeki oda fiyatı ile bağlantı'
    )
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi', db_index=True)
    end_date = models.DateField('Bitiş Tarihi', db_index=True)
    
    # Fiyat Bilgileri
    base_price = models.DecimalField('Temel Fiyat', max_digits=10, decimal_places=2)
    channel_price = models.DecimalField('Kanal Fiyatı', max_digits=10, decimal_places=2,
                                       help_text='Kanala gönderilecek fiyat (markup dahil)')
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Müsaitlik
    availability = models.IntegerField('Müsaitlik', default=0,
                                      help_text='Müsait oda sayısı')
    min_stay = models.IntegerField('Minimum Kalış', default=1)
    max_stay = models.IntegerField('Maksimum Kalış', null=True, blank=True)
    
    # İptal Politikası
    cancellation_policy = models.CharField('İptal Politikası', max_length=50, blank=True)
    free_cancellation_until = models.DateField('Ücretsiz İptal Tarihi', null=True, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    last_synced_at = models.DateTimeField('Son Senkronizasyon', null=True, blank=True)
    
    # Ek Bilgiler
    channel_data = models.JSONField('Kanal Verisi', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kanal Fiyatı'
        verbose_name_plural = 'Kanal Fiyatları'
        ordering = ['-start_date']
        unique_together = [['configuration', 'room', 'start_date', 'end_date']]
        indexes = [
            models.Index(fields=['configuration', 'room']),
            models.Index(fields=['start_date', 'end_date']),
            models.Index(fields=['is_active']),
        ]
    
    def __str__(self):
        return f"{self.configuration.name} - {self.room.name} ({self.start_date} - {self.end_date})"


# ==================== KANAL KOMİSYONLARI ====================

class ChannelCommission(TimeStampedModel):
    """
    Kanal Komisyonları
    Kanallardan alınan komisyon kayıtları
    """
    configuration = models.ForeignKey(
        ChannelConfiguration,
        on_delete=models.CASCADE,
        related_name='commissions',
        verbose_name='Kanal Konfigürasyonu'
    )
    
    reservation = models.ForeignKey(
        ChannelReservation,
        on_delete=models.CASCADE,
        related_name='commissions',
        verbose_name='Rezervasyon',
        null=True,
        blank=True
    )
    
    # Komisyon Bilgileri
    commission_rate = models.DecimalField('Komisyon Oranı (%)', max_digits=5, decimal_places=2)
    base_amount = models.DecimalField('Temel Tutar', max_digits=10, decimal_places=2)
    commission_amount = models.DecimalField('Komisyon Tutarı', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Tarih Bilgileri
    commission_date = models.DateField('Komisyon Tarihi', default=timezone.now)
    payment_date = models.DateField('Ödeme Tarihi', null=True, blank=True)
    
    # Durum
    is_paid = models.BooleanField('Ödendi mi?', default=False)
    payment_reference = models.CharField('Ödeme Referansı', max_length=200, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Kanal Komisyonu'
        verbose_name_plural = 'Kanal Komisyonları'
        ordering = ['-commission_date']
        indexes = [
            models.Index(fields=['configuration', '-commission_date']),
            models.Index(fields=['is_paid']),
            models.Index(fields=['reservation']),
        ]
    
    def __str__(self):
        return f"{self.configuration.name} - {self.commission_amount} {self.currency} ({self.commission_date})"

