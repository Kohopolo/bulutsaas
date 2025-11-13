"""
Modül sistemi modelleri
Sistem genelinde kullanılabilir modüller
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


class Module(TimeStampedModel):
    """
    Modül modeli
    Örnek: Rezervasyon, Housekeeping, Kanal Entegrasyonu
    """
    name = models.CharField('Modül Adı', max_length=100)
    code = models.SlugField('Modül Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, default='📦', help_text='Emoji veya Font Awesome class')
    
    # Kategorileme
    CATEGORY_CHOICES = [
        ('reservation', 'Rezervasyon'),
        ('housekeeping', 'Housekeeping'),
        ('channel', 'Kanal Entegrasyonu'),
        ('payment', 'Ödeme'),
        ('reporting', 'Raporlama'),
        ('other', 'Diğer'),
    ]
    category = models.CharField('Kategori', max_length=50, choices=CATEGORY_CHOICES, default='other')
    
    # Teknik Bilgiler
    app_name = models.CharField('Django App Adı', max_length=100, blank=True, help_text='apps.tenant_apps.reservations')
    url_prefix = models.CharField('URL Prefix', max_length=50, blank=True, help_text='/reservations/')
    
    # İzin Tanımları (JSON format)
    # Örnek: {"view": "Görüntüleme", "add": "Ekleme", "edit": "Düzenleme", "delete": "Silme"}
    available_permissions = models.JSONField('Mevcut İzinler', default=dict)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_core = models.BooleanField('Temel Modül mü?', default=False, help_text='Temel modüller tüm paketlerde zorunludur')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)

    class Meta:
        verbose_name = 'Modül'
        verbose_name_plural = 'Modüller'
        ordering = ['sort_order', 'name']

    def __str__(self):
        return f"{self.icon} {self.name}"

    def get_packages_count(self):
        """Bu modülü kullanan paket sayısı"""
        return self.packages.filter(is_active=True).count()
    get_packages_count.short_description = 'Paket Sayısı'


# ==================== KANAL ŞABLONLARI (Channel Management için) ====================

class ChannelTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Kanal Şablonu
    Booking.com, ETS, Tatilbudur vb. hazır kanal şablonları
    Public schema'da tutulur (tüm tenant'lar için ortak)
    """
    CHANNEL_TYPE_CHOICES = [
        ('ota', 'OTA (Online Travel Agency)'),
        ('gds', 'GDS (Global Distribution System)'),
        ('metasearch', 'Meta Arama Motoru'),
        ('direct', 'Direkt Rezervasyon'),
        ('other', 'Diğer'),
    ]
    
    name = models.CharField('Kanal Adı', max_length=200)
    code = models.SlugField('Kanal Kodu', max_length=50, unique=True, db_index=True)
    channel_type = models.CharField('Kanal Tipi', max_length=20, choices=CHANNEL_TYPE_CHOICES, default='ota')
    description = models.TextField('Açıklama', blank=True)
    
    api_type = models.CharField('API Tipi', max_length=50, default='xml',
                                help_text='xml, json, soap, rest vb.')
    api_documentation_url = models.URLField('API Dokümantasyon URL', blank=True)
    api_endpoint_template = models.CharField('API Endpoint Şablonu', max_length=500, blank=True,
                                            help_text='Örn: https://api.booking.com/v1/{endpoint}')
    
    required_fields = models.JSONField('Gerekli Alanlar', default=dict,
                                      help_text='{"api_key": "API Key", "api_secret": "API Secret"}')
    optional_fields = models.JSONField('Opsiyonel Alanlar', default=dict,
                                      help_text='{"hotel_id": "Otel ID", "username": "Kullanıcı Adı"}')
    
    supports_pricing = models.BooleanField('Fiyat Aktarımı Desteği', default=True)
    supports_availability = models.BooleanField('Müsaitlik Aktarımı Desteği', default=True)
    supports_reservations = models.BooleanField('Rezervasyon Desteği', default=True)
    supports_two_way = models.BooleanField('İki Yönlü Senkronizasyon', default=True,
                                          help_text='Hem push hem pull desteği')
    supports_commission = models.BooleanField('Komisyon Yönetimi', default=True)
    
    default_commission_rate = models.DecimalField('Varsayılan Komisyon Oranı (%)', 
                                                  max_digits=5, decimal_places=2,
                                                  default=Decimal('0.00'),
                                                  validators=[MinValueValidator(Decimal('0.00')),
                                                             MaxValueValidator(Decimal('100.00'))])
    
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_popular = models.BooleanField('Popüler mi?', default=False)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    icon = models.CharField('İkon', max_length=50, default='fas fa-globe',
                           help_text='Font Awesome class')
    logo_url = models.URLField('Logo URL', blank=True)
    
    class Meta:
        verbose_name = 'Kanal Şablonu'
        verbose_name_plural = 'Kanal Şablonları'
        ordering = ['sort_order', 'name']
        indexes = [
            models.Index(fields=['code']),
            models.Index(fields=['channel_type']),
            models.Index(fields=['is_active']),
        ]
    
    def __str__(self):
        return self.name

