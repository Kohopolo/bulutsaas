"""
Ayarlar Modülü Modelleri
SMS Gateway konfigürasyonları ve SMS şablonları
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from apps.core.models import TimeStampedModel, SoftDeleteModel


class SMSGateway(TimeStampedModel, SoftDeleteModel):
    """
    SMS Gateway Konfigürasyonu
    Twilio, NetGSM, Verimor gibi SMS sağlayıcıları için konfigürasyon
    Otel bazlı veya genel gateway olabilir
    """
    GATEWAY_TYPE_CHOICES = [
        ('twilio', 'Twilio'),
        ('netgsm', 'NetGSM'),
        ('verimor', 'Verimor'),
    ]
    
    # Otel Bağlantısı (null ise genel gateway)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='sms_gateways',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa tüm oteller için genel gateway olur'
    )
    
    name = models.CharField('Gateway Adı', max_length=200,
                           help_text='Örn: Twilio Production, NetGSM Ana Hesap')
    gateway_type = models.CharField('Gateway Tipi', max_length=20,
                                    choices=GATEWAY_TYPE_CHOICES)
    
    # API Bilgileri (JSON formatında saklanır)
    api_credentials = models.JSONField('API Bilgileri', default=dict,
                                       help_text='API key, secret, username, password vb.')
    
    # API Ayarları
    api_endpoint = models.CharField('API Endpoint', max_length=500, blank=True)
    api_timeout = models.IntegerField('API Timeout (saniye)', default=30,
                                     validators=[MinValueValidator(1), MaxValueValidator(300)])
    api_retry_count = models.IntegerField('API Retry Sayısı', default=3,
                                         validators=[MinValueValidator(0), MaxValueValidator(10)])
    
    # Gönderim Ayarları
    sender_id = models.CharField('Gönderen ID', max_length=20, blank=True,
                                help_text='SMS gönderen numarası veya başlık')
    default_country_code = models.CharField('Varsayılan Ülke Kodu', max_length=5, default='+90',
                                           help_text='Örn: +90, +1')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Gateway mi?', default=False,
                                    help_text='Sadece bir gateway varsayılan olabilir')
    is_test_mode = models.BooleanField('Test Modu', default=False,
                                      help_text='Test ortamında çalıştır')
    
    # İstatistikler
    total_sent = models.IntegerField('Toplam Gönderilen', default=0)
    total_failed = models.IntegerField('Toplam Başarısız', default=0)
    last_sent_at = models.DateTimeField('Son Gönderim', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'SMS Gateway'
        verbose_name_plural = 'SMS Gateway\'ler'
        ordering = ['-is_default', '-is_active', 'name']
        indexes = [
            models.Index(fields=['gateway_type', 'is_active']),
            models.Index(fields=['hotel', 'is_default']),
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_gateway_type_display()})"
    
    def save(self, *args, **kwargs):
        # Otel bazlı varsayılan gateway: Her otel için sadece bir gateway varsayılan olabilir
        if self.is_default:
            # Aynı otel için diğer gateway'leri varsayılan olmaktan çıkar
            filter_kwargs = {'is_default': True}
            if self.hotel:
                filter_kwargs['hotel'] = self.hotel
            else:
                filter_kwargs['hotel__isnull'] = True
            SMSGateway.objects.filter(**filter_kwargs).exclude(pk=self.pk).update(is_default=False)
        super().save(*args, **kwargs)


class SMSTemplate(TimeStampedModel, SoftDeleteModel):
    """
    SMS Mesaj Şablonu
    Dinamik değişkenler içeren SMS şablonları
    """
    name = models.CharField('Şablon Adı', max_length=200,
                           help_text='Örn: Rezervasyon Onayı, Check-in Hatırlatma')
    code = models.SlugField('Şablon Kodu', max_length=100, unique=True,
                            help_text='Benzersiz kod: reservation_confirmation')
    
    # Kategorileme
    CATEGORY_CHOICES = [
        ('reservation', 'Rezervasyon'),
        ('checkin', 'Check-in'),
        ('checkout', 'Check-out'),
        ('payment', 'Ödeme'),
        ('notification', 'Bildirim'),
        ('marketing', 'Pazarlama'),
        ('other', 'Diğer'),
    ]
    category = models.CharField('Kategori', max_length=50, choices=CATEGORY_CHOICES, default='other')
    
    # Şablon İçeriği
    template_text = models.TextField('Şablon Metni', max_length=1000,
                                    help_text='Değişkenler: {{guest_name}}, {{check_in_date}} vb.')
    
    # Değişkenler (JSON formatında saklanır)
    # Örnek: {"guest_name": "Misafir Adı", "check_in_date": "Check-in Tarihi"}
    available_variables = models.JSONField('Kullanılabilir Değişkenler', default=dict,
                                         help_text='Şablonda kullanılabilecek değişkenler ve açıklamaları')
    
    # Kullanım Bilgileri
    module_usage = models.CharField('Kullanıldığı Modül', max_length=100, blank=True,
                                   help_text='Örn: reception, ferry_tickets, tours')
    description = models.TextField('Açıklama', blank=True,
                                 help_text='Şablonun ne zaman ve nasıl kullanılacağı')
    
    # Ayarlar
    max_length = models.IntegerField('Maksimum Uzunluk', default=160,
                                    help_text='SMS karakter limiti (varsayılan 160)')
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system_template = models.BooleanField('Sistem Şablonu mu?', default=False,
                                           help_text='Sistem şablonları silinemez')
    
    # İstatistikler
    usage_count = models.IntegerField('Kullanım Sayısı', default=0)
    last_used_at = models.DateTimeField('Son Kullanım', null=True, blank=True)
    
    class Meta:
        verbose_name = 'SMS Şablonu'
        verbose_name_plural = 'SMS Şablonları'
        ordering = ['category', 'name']
        indexes = [
            models.Index(fields=['code']),
            models.Index(fields=['category', 'is_active']),
            models.Index(fields=['module_usage']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_category_display()})"
    
    def render(self, context: dict) -> str:
        """
        Şablonu verilen context ile render et
        
        Args:
            context: Değişken değerleri içeren dict
                    Örn: {'guest_name': 'Ahmet Yılmaz', 'check_in_date': '2025-11-20'}
        
        Returns:
            Render edilmiş SMS metni
        """
        text = self.template_text
        for key, value in context.items():
            placeholder = f"{{{{{key}}}}}"
            text = text.replace(placeholder, str(value))
        return text
    
    def get_preview(self, sample_context: dict = None) -> str:
        """
        Örnek context ile şablon önizlemesi
        
        Args:
            sample_context: Örnek değerler (varsayılan değerler kullanılır)
        
        Returns:
            Önizleme metni
        """
        if sample_context is None:
            # Varsayılan örnek değerler
            sample_context = {}
            for key in self.available_variables.keys():
                if 'name' in key.lower():
                    sample_context[key] = 'Ahmet Yılmaz'
                elif 'date' in key.lower():
                    sample_context[key] = '20.11.2025'
                elif 'phone' in key.lower():
                    sample_context[key] = '0555 123 45 67'
                elif 'amount' in key.lower() or 'price' in key.lower():
                    sample_context[key] = '1.500,00 ₺'
                else:
                    sample_context[key] = f'[{key}]'
        
        return self.render(sample_context)
    
    def validate_length(self, context: dict) -> tuple[bool, int]:
        """
        Render edilmiş metnin uzunluğunu kontrol et
        
        Returns:
            (is_valid, length)
        """
        rendered = self.render(context)
        length = len(rendered)
        return length <= self.max_length, length


class SMSSentLog(TimeStampedModel):
    """
    Gönderilen SMS Logları
    Tüm SMS gönderimlerinin kaydı
    """
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('sent', 'Gönderildi'),
        ('delivered', 'Teslim Edildi'),
        ('failed', 'Başarısız'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    gateway = models.ForeignKey(
        SMSGateway,
        on_delete=models.SET_NULL,
        null=True,
        related_name='sent_logs',
        verbose_name='Gateway'
    )
    
    template = models.ForeignKey(
        SMSTemplate,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='sent_logs',
        verbose_name='Şablon'
    )
    
    # Alıcı Bilgileri
    recipient_phone = models.CharField('Alıcı Telefon', max_length=20)
    recipient_name = models.CharField('Alıcı Adı', max_length=200, blank=True)
    
    # Mesaj Bilgileri
    message_text = models.TextField('Mesaj Metni', max_length=1000)
    message_length = models.IntegerField('Mesaj Uzunluğu')
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    # Gateway Yanıtı
    gateway_response = models.JSONField('Gateway Yanıtı', default=dict, blank=True)
    gateway_message_id = models.CharField('Gateway Mesaj ID', max_length=200, blank=True)
    
    # Zaman Bilgileri
    sent_at = models.DateTimeField('Gönderim Zamanı', null=True, blank=True)
    delivered_at = models.DateTimeField('Teslim Zamanı', null=True, blank=True)
    
    # İlişkili Kayıt
    related_module = models.CharField('İlişkili Modül', max_length=100, blank=True)
    related_object_id = models.IntegerField('İlişkili Kayıt ID', null=True, blank=True)
    related_object_type = models.CharField('İlişkili Kayıt Tipi', max_length=100, blank=True)
    
    # Ek Bilgiler
    context_data = models.JSONField('Context Verisi', default=dict, blank=True,
                                   help_text='Şablon render için kullanılan değişkenler')
    
    class Meta:
        verbose_name = 'SMS Gönderim Logu'
        verbose_name_plural = 'SMS Gönderim Logları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['gateway', '-created_at']),
            models.Index(fields=['status', '-created_at']),
            models.Index(fields=['recipient_phone', '-created_at']),
            models.Index(fields=['related_module', 'related_object_id']),
        ]
    
    def __str__(self):
        return f"{self.recipient_phone} - {self.get_status_display()} ({self.created_at.strftime('%Y-%m-%d %H:%M')})"


class EmailGateway(TimeStampedModel, SoftDeleteModel):
    """
    Email Gateway Konfigürasyonu (SMTP)
    Gmail, Outlook, Custom SMTP gibi email sağlayıcıları için konfigürasyon
    Otel bazlı veya genel gateway olabilir
    """
    GATEWAY_TYPE_CHOICES = [
        ('gmail', 'Gmail'),
        ('outlook', 'Outlook / Office 365'),
        ('custom', 'Custom SMTP'),
    ]
    
    # Otel Bağlantısı (null ise genel gateway)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='email_gateways',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa tüm oteller için genel gateway olur'
    )
    
    name = models.CharField('Gateway Adı', max_length=200,
                           help_text='Örn: Gmail Production, Outlook Ana Hesap')
    gateway_type = models.CharField('Gateway Tipi', max_length=20,
                                    choices=GATEWAY_TYPE_CHOICES)
    
    # SMTP Bilgileri (JSON formatında saklanır)
    smtp_credentials = models.JSONField('SMTP Bilgileri', default=dict,
                                       help_text='SMTP host, port, username, password vb.')
    
    # SMTP Ayarları
    smtp_host = models.CharField('SMTP Host', max_length=500, blank=True,
                                help_text='Örn: smtp.gmail.com')
    smtp_port = models.IntegerField('SMTP Port', default=587,
                                    validators=[MinValueValidator(1), MaxValueValidator(65535)])
    use_tls = models.BooleanField('TLS Kullan', default=True)
    use_ssl = models.BooleanField('SSL Kullan', default=False)
    smtp_timeout = models.IntegerField('SMTP Timeout (saniye)', default=30,
                                     validators=[MinValueValidator(1), MaxValueValidator(300)])
    
    # Gönderim Ayarları
    from_email = models.EmailField('Gönderen Email', max_length=200,
                                   help_text='Varsayılan gönderen email adresi')
    from_name = models.CharField('Gönderen Adı', max_length=200, blank=True,
                                help_text='Örn: Otel Adı')
    reply_to = models.EmailField('Yanıt Adresi', max_length=200, blank=True,
                                 help_text='Yanıtların gönderileceği email adresi')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Gateway mi?', default=False,
                                    help_text='Otel bazlı varsayılan gateway (her otel için ayrı olabilir)')
    is_test_mode = models.BooleanField('Test Modu', default=False,
                                      help_text='Test ortamında çalıştır')
    
    # İstatistikler
    total_sent = models.IntegerField('Toplam Gönderilen', default=0)
    total_failed = models.IntegerField('Toplam Başarısız', default=0)
    last_sent_at = models.DateTimeField('Son Gönderim', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Email Gateway'
        verbose_name_plural = 'Email Gateway\'ler'
        ordering = ['-is_default', '-is_active', 'name']
        indexes = [
            models.Index(fields=['gateway_type', 'is_active']),
            models.Index(fields=['hotel', 'is_default']),
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_gateway_type_display()})"
    
    def save(self, *args, **kwargs):
        # Otel bazlı varsayılan gateway: Her otel için sadece bir gateway varsayılan olabilir
        if self.is_default:
            # Aynı otel için diğer gateway'leri varsayılan olmaktan çıkar
            filter_kwargs = {'is_default': True}
            if self.hotel:
                filter_kwargs['hotel'] = self.hotel
            else:
                filter_kwargs['hotel__isnull'] = True
            EmailGateway.objects.filter(**filter_kwargs).exclude(pk=self.pk).update(is_default=False)
        super().save(*args, **kwargs)


class EmailTemplate(TimeStampedModel, SoftDeleteModel):
    """
    Email Mesaj Şablonu
    Dinamik değişkenler içeren HTML email şablonları
    """
    name = models.CharField('Şablon Adı', max_length=200,
                           help_text='Örn: Rezervasyon Onayı, Check-in Hatırlatma')
    code = models.SlugField('Şablon Kodu', max_length=100, unique=True,
                            help_text='Benzersiz kod: reservation_confirmation')
    
    # Kategorileme
    CATEGORY_CHOICES = [
        ('reservation', 'Rezervasyon'),
        ('checkin', 'Check-in'),
        ('checkout', 'Check-out'),
        ('payment', 'Ödeme'),
        ('notification', 'Bildirim'),
        ('marketing', 'Pazarlama'),
        ('other', 'Diğer'),
    ]
    category = models.CharField('Kategori', max_length=50, choices=CATEGORY_CHOICES, default='other')
    
    # Şablon İçeriği
    subject = models.CharField('Email Konusu', max_length=200,
                              help_text='Değişkenler: {{guest_name}}, {{check_in_date}} vb.')
    template_html = models.TextField('HTML Şablon', blank=True,
                                    help_text='HTML formatında email şablonu')
    template_text = models.TextField('Metin Şablonu', blank=True,
                                    help_text='Plain text alternatifi (HTML yoksa kullanılır)')
    
    # Değişkenler (JSON formatında saklanır)
    available_variables = models.JSONField('Kullanılabilir Değişkenler', default=dict,
                                         help_text='Şablonda kullanılabilecek değişkenler ve açıklamaları')
    
    # Kullanım Bilgileri
    module_usage = models.CharField('Kullanıldığı Modül', max_length=100, blank=True,
                                   help_text='Örn: reception, ferry_tickets, tours')
    description = models.TextField('Açıklama', blank=True,
                                 help_text='Şablonun ne zaman ve nasıl kullanılacağı')
    
    # Ayarlar
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system_template = models.BooleanField('Sistem Şablonu mu?', default=False,
                                           help_text='Sistem şablonları silinemez')
    
    # İstatistikler
    usage_count = models.IntegerField('Kullanım Sayısı', default=0)
    last_used_at = models.DateTimeField('Son Kullanım', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Email Şablonu'
        verbose_name_plural = 'Email Şablonları'
        ordering = ['category', 'name']
        indexes = [
            models.Index(fields=['code']),
            models.Index(fields=['category', 'is_active']),
            models.Index(fields=['module_usage']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_category_display()})"
    
    def render(self, context: dict) -> tuple[str, str, str]:
        """
        Şablonu verilen context ile render et
        
        Args:
            context: Değişken değerleri içeren dict
        
        Returns:
            (rendered_subject, rendered_html, rendered_text)
        """
        subject = self.subject
        html = self.template_html
        text = self.template_text
        
        for key, value in context.items():
            placeholder = f"{{{{{key}}}}}"
            subject = subject.replace(placeholder, str(value))
            html = html.replace(placeholder, str(value))
            text = text.replace(placeholder, str(value))
        
        return subject, html, text
    
    def get_preview(self, sample_context: dict = None) -> dict:
        """
        Örnek context ile şablon önizlemesi
        
        Args:
            sample_context: Örnek değerler (varsayılan değerler kullanılır)
        
        Returns:
            {'subject': str, 'html': str, 'text': str}
        """
        if sample_context is None:
            sample_context = {}
            for key in self.available_variables.keys():
                if 'name' in key.lower():
                    sample_context[key] = 'Ahmet Yılmaz'
                elif 'date' in key.lower():
                    sample_context[key] = '20.11.2025'
                elif 'phone' in key.lower():
                    sample_context[key] = '0555 123 45 67'
                elif 'amount' in key.lower() or 'price' in key.lower():
                    sample_context[key] = '1.500,00 ₺'
                else:
                    sample_context[key] = f'[{key}]'
        
        subject, html, text = self.render(sample_context)
        return {
            'subject': subject,
            'html': html,
            'text': text
        }


class EmailSentLog(TimeStampedModel):
    """
    Gönderilen Email Logları
    Tüm email gönderimlerinin kaydı
    """
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('sent', 'Gönderildi'),
        ('delivered', 'Teslim Edildi'),
        ('failed', 'Başarısız'),
        ('bounced', 'Geri Döndü'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    gateway = models.ForeignKey(
        EmailGateway,
        on_delete=models.SET_NULL,
        null=True,
        related_name='sent_logs',
        verbose_name='Gateway'
    )
    
    template = models.ForeignKey(
        EmailTemplate,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='sent_logs',
        verbose_name='Şablon'
    )
    
    # Alıcı Bilgileri
    recipient_email = models.EmailField('Alıcı Email', max_length=200)
    recipient_name = models.CharField('Alıcı Adı', max_length=200, blank=True)
    
    # Email Bilgileri
    subject = models.CharField('Konu', max_length=200)
    message_html = models.TextField('HTML İçerik', blank=True)
    message_text = models.TextField('Metin İçerik', blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    # SMTP Yanıtı
    smtp_response = models.JSONField('SMTP Yanıtı', default=dict, blank=True)
    message_id = models.CharField('Email Mesaj ID', max_length=200, blank=True)
    
    # Zaman Bilgileri
    sent_at = models.DateTimeField('Gönderim Zamanı', null=True, blank=True)
    delivered_at = models.DateTimeField('Teslim Zamanı', null=True, blank=True)
    
    # İlişkili Kayıt
    related_module = models.CharField('İlişkili Modül', max_length=100, blank=True)
    related_object_id = models.IntegerField('İlişkili Kayıt ID', null=True, blank=True)
    related_object_type = models.CharField('İlişkili Kayıt Tipi', max_length=100, blank=True)
    
    # Ek Bilgiler
    context_data = models.JSONField('Context Verisi', default=dict, blank=True,
                                   help_text='Şablon render için kullanılan değişkenler')
    
    class Meta:
        verbose_name = 'Email Gönderim Logu'
        verbose_name_plural = 'Email Gönderim Logları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['gateway', '-created_at']),
            models.Index(fields=['status', '-created_at']),
            models.Index(fields=['recipient_email', '-created_at']),
            models.Index(fields=['related_module', 'related_object_id']),
        ]
    
    def __str__(self):
        return f"{self.recipient_email} - {self.get_status_display()} ({self.created_at.strftime('%Y-%m-%d %H:%M')})"

