"""
Bildirim Sistemi Modelleri
Email, SMS, WhatsApp entegrasyonları için
"""
from django.db import models
from django.core.validators import MinValueValidator
from apps.core.models import TimeStampedModel, SoftDeleteModel


class NotificationProvider(TimeStampedModel, SoftDeleteModel):
    """
    Bildirim Sağlayıcı Modeli
    Email, SMS, WhatsApp sağlayıcılarını yönetir
    """
    PROVIDER_TYPE_CHOICES = [
        ('email', 'E-posta'),
        ('sms', 'SMS'),
        ('whatsapp', 'WhatsApp'),
    ]
    
    name = models.CharField('Sağlayıcı Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    provider_type = models.CharField('Sağlayıcı Tipi', max_length=20, choices=PROVIDER_TYPE_CHOICES)
    description = models.TextField('Açıklama', blank=True)
    
    # API Ayarları
    api_url = models.URLField('API URL', blank=True)
    test_api_url = models.URLField('Test API URL', blank=True)
    
    # Özellikler
    supports_bulk = models.BooleanField('Toplu Gönderim Destekli', default=False)
    supports_template = models.BooleanField('Şablon Destekli', default=True)
    supports_unicode = models.BooleanField('Türkçe Karakter Destekli', default=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Sağlayıcı Ayarları', default=dict, blank=True,
                               help_text='API endpoint\'leri, parametreler vb.')
    
    class Meta:
        verbose_name = 'Bildirim Sağlayıcı'
        verbose_name_plural = 'Bildirim Sağlayıcıları'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.name} ({self.get_provider_type_display()})"


class NotificationProviderConfig(TimeStampedModel):
    """
    Bildirim Sağlayıcı Yapılandırması
    Her sağlayıcı için API bilgileri ve ayarlar
    """
    provider = models.ForeignKey(
        NotificationProvider,
        on_delete=models.CASCADE,
        related_name='configs',
        verbose_name='Sağlayıcı'
    )
    
    # API Credentials (Şifrelenmiş)
    api_key = models.CharField('API Key', max_length=255, blank=True)
    api_secret = models.CharField('API Secret', max_length=255, blank=True)
    username = models.CharField('Kullanıcı Adı', max_length=100, blank=True)
    password = models.CharField('Şifre', max_length=255, blank=True)
    sender_id = models.CharField('Gönderen ID', max_length=50, blank=True,
                                help_text='SMS için gönderen numara, Email için gönderen adres')
    
    # WhatsApp Özel Ayarlar
    whatsapp_business_id = models.CharField('WhatsApp Business ID', max_length=100, blank=True)
    whatsapp_phone_number_id = models.CharField('WhatsApp Phone Number ID', max_length=100, blank=True)
    whatsapp_access_token = models.CharField('WhatsApp Access Token', max_length=500, blank=True)
    whatsapp_verify_token = models.CharField('WhatsApp Verify Token', max_length=100, blank=True)
    
    # SMS Özel Ayarlar (NetGSM, Verimor)
    sms_username = models.CharField('SMS Kullanıcı Adı', max_length=100, blank=True)
    sms_password = models.CharField('SMS Şifre', max_length=255, blank=True)
    sms_header = models.CharField('SMS Başlık', max_length=11, blank=True,
                               help_text='SMS gönderen başlığı (max 11 karakter)')
    
    # Email Özel Ayarlar
    email_host = models.CharField('SMTP Host', max_length=200, blank=True)
    email_port = models.IntegerField('SMTP Port', default=587, validators=[MinValueValidator(1)])
    email_use_tls = models.BooleanField('TLS Kullan', default=True)
    email_use_ssl = models.BooleanField('SSL Kullan', default=False)
    email_from = models.EmailField('Gönderen E-posta', blank=True)
    email_from_name = models.CharField('Gönderen Adı', max_length=100, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True)
    
    # Kullanım İstatistikleri
    total_sent = models.IntegerField('Toplam Gönderim', default=0)
    total_failed = models.IntegerField('Toplam Başarısız', default=0)
    last_used_at = models.DateTimeField('Son Kullanım', null=True, blank=True)
    
    # Ek Ayarlar (JSON)
    settings = models.JSONField('Ek Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Bildirim Sağlayıcı Yapılandırması'
        verbose_name_plural = 'Bildirim Sağlayıcı Yapılandırmaları'
        ordering = ['provider', '-is_active']
    
    def __str__(self):
        return f"{self.provider.name} - {self.sender_id or 'Yapılandırılmamış'}"


class NotificationTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Bildirim Şablonu
    Email, SMS, WhatsApp şablonları
    """
    TEMPLATE_TYPE_CHOICES = [
        ('email', 'E-posta'),
        ('sms', 'SMS'),
        ('whatsapp', 'WhatsApp'),
    ]
    
    TRIGGER_EVENT_CHOICES = [
        # Ödeme İşlemleri
        ('payment_success', 'Ödeme Başarılı'),
        ('payment_failed', 'Ödeme Başarısız'),
        ('payment_pending', 'Ödeme Beklemede'),
        ('subscription_created', 'Abonelik Oluşturuldu'),
        ('subscription_renewed', 'Abonelik Yenilendi'),
        ('subscription_expiring', 'Abonelik Sona Eriyor'),
        ('subscription_expired', 'Abonelik Sona Erdi'),
        
        # Paket İşlemleri
        ('package_purchased', 'Paket Satın Alındı'),
        ('package_upgraded', 'Paket Yükseltildi'),
        ('package_downgraded', 'Paket Düşürüldü'),
        
        # Kullanıcı İşlemleri
        ('user_created', 'Kullanıcı Oluşturuldu'),
        ('user_password_reset', 'Şifre Sıfırlama'),
        ('user_login', 'Kullanıcı Girişi'),
        
        # Hatırlatmalar
        ('reminder_payment_due', 'Ödeme Vadesi Hatırlatması'),
        ('reminder_subscription_expiring', 'Abonelik Sona Eriyor Hatırlatması'),
        ('reminder_trial_ending', 'Deneme Süresi Bitiyor Hatırlatması'),
        
        # Diğer
        ('custom', 'Özel'),
    ]
    
    name = models.CharField('Şablon Adı', max_length=200)
    code = models.SlugField('Şablon Kodu', max_length=50, unique=True)
    template_type = models.CharField('Şablon Tipi', max_length=20, choices=TEMPLATE_TYPE_CHOICES)
    trigger_event = models.CharField('Tetikleyici Olay', max_length=50, choices=TRIGGER_EVENT_CHOICES)
    
    # İçerik
    subject = models.CharField('Konu', max_length=200, blank=True,
                              help_text='Email ve WhatsApp için')
    content = models.TextField('İçerik', help_text='Şablon içeriği ({{variable}} formatında)')
    content_html = models.TextField('HTML İçerik', blank=True,
                                   help_text='Email için HTML formatında')
    
    # Ayarlar
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system = models.BooleanField('Sistem Şablonu', default=False,
                                    help_text='Sistem şablonları silinemez')
    priority = models.IntegerField('Öncelik', default=0,
                                  help_text='Yüksek öncelik önce gönderilir')
    
    # Değişkenler (JSON)
    variables = models.JSONField('Kullanılabilir Değişkenler', default=dict, blank=True,
                                help_text='Şablonda kullanılabilecek değişkenler ve açıklamaları')
    
    class Meta:
        verbose_name = 'Bildirim Şablonu'
        verbose_name_plural = 'Bildirim Şablonları'
        ordering = ['-priority', 'name']
    
    def __str__(self):
        return f"{self.name} ({self.get_template_type_display()})"


class NotificationLog(TimeStampedModel):
    """
    Bildirim Log Modeli
    Gönderilen tüm bildirimleri kaydeder
    """
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('sent', 'Gönderildi'),
        ('failed', 'Başarısız'),
        ('delivered', 'Teslim Edildi'),
        ('read', 'Okundu'),
    ]
    
    provider = models.ForeignKey(
        NotificationProvider,
        on_delete=models.SET_NULL,
        null=True,
        related_name='logs',
        verbose_name='Sağlayıcı'
    )
    template = models.ForeignKey(
        NotificationTemplate,
        on_delete=models.SET_NULL,
        null=True,
        related_name='logs',
        verbose_name='Şablon'
    )
    
    # Alıcı Bilgileri
    recipient_type = models.CharField('Alıcı Tipi', max_length=20,
                                     choices=[('email', 'E-posta'), ('phone', 'Telefon'), ('user', 'Kullanıcı')])
    recipient_email = models.EmailField('Alıcı E-posta', blank=True)
    recipient_phone = models.CharField('Alıcı Telefon', max_length=20, blank=True)
    recipient_name = models.CharField('Alıcı Adı', max_length=200, blank=True)
    
    # İçerik
    subject = models.CharField('Konu', max_length=200, blank=True)
    content = models.TextField('İçerik')
    content_html = models.TextField('HTML İçerik', blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    sent_at = models.DateTimeField('Gönderim Tarihi', null=True, blank=True)
    delivered_at = models.DateTimeField('Teslim Tarihi', null=True, blank=True)
    read_at = models.DateTimeField('Okunma Tarihi', null=True, blank=True)
    
    # Provider Response
    provider_response = models.JSONField('Sağlayıcı Yanıtı', default=dict, blank=True)
    provider_message_id = models.CharField('Sağlayıcı Mesaj ID', max_length=100, blank=True)
    
    # Hata Bilgileri
    error_message = models.TextField('Hata Mesajı', blank=True)
    retry_count = models.IntegerField('Tekrar Deneme Sayısı', default=0)
    
    # İlişkili Kayıtlar
    related_model = models.CharField('İlişkili Model', max_length=100, blank=True)
    related_id = models.IntegerField('İlişkili ID', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Bildirim Log'
        verbose_name_plural = 'Bildirim Logları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['status', 'created_at']),
            models.Index(fields=['recipient_email']),
            models.Index(fields=['recipient_phone']),
        ]
    
    def __str__(self):
        return f"{self.get_status_display()} - {self.recipient_email or self.recipient_phone} ({self.created_at})"

