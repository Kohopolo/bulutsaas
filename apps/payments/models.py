"""
Türk Ödeme Sistemleri Entegrasyonu
İyzico, PayTR, NestPay, PayU vb.
"""
from django.db import models
from django.conf import settings
from apps.core.models import TimeStampedModel, SoftDeleteModel
from apps.tenants.models import Tenant


class PaymentGateway(TimeStampedModel, SoftDeleteModel):
    """
    Ödeme Gateway Modeli
    Sistemdeki tüm ödeme gateway'lerini yönetir
    """
    GATEWAY_CHOICES = [
        # Gateway'ler
        ('iyzico', 'İyzico'),
        ('paytr', 'PayTR'),
        ('nestpay', 'NestPay'),
        ('payu', 'PayU'),
        ('paymes', 'Paymes'),
        ('stripe', 'Stripe'),
        # Türk Bankaları - Sanal Pos
        ('garanti', 'Garanti Sanal Pos'),
        ('isbank', 'İş Bankası Sanal Pos'),
        ('akbank', 'Akbank Sanal Pos'),
        ('ziraat', 'Ziraat Bankası Sanal Pos'),
        ('yapikredi', 'Yapı Kredi Sanal Pos'),
        ('denizbank', 'Denizbank Sanal Pos'),
        ('halkbank', 'Halkbank Sanal Pos'),
        ('qnbfinansbank', 'QNB Finansbank Sanal Pos'),
        ('teb', 'TEB Sanal Pos'),
        ('sekerbank', 'Şekerbank Sanal Pos'),
        ('ingbank', 'ING Bank Sanal Pos'),
        ('vakifbank', 'Vakıfbank Sanal Pos'),
        ('fibabanka', 'Fibabanka Sanal Pos'),
        ('albaraka', 'Albaraka Türk Sanal Pos'),
        ('kuveytturk', 'Kuveyt Türk Sanal Pos'),
        ('ziraatkatilim', 'Ziraat Katılım Sanal Pos'),
        ('vakifkatilim', 'Vakıf Katılım Sanal Pos'),
        ('other', 'Diğer'),
    ]
    
    name = models.CharField('Gateway Adı', max_length=100)
    code = models.SlugField('Gateway Kodu', max_length=50, unique=True)
    gateway_type = models.CharField('Gateway Tipi', max_length=50, choices=GATEWAY_CHOICES)
    description = models.TextField('Açıklama', blank=True)
    
    # API Ayarları (Genel)
    api_url = models.URLField('API URL', blank=True)
    test_api_url = models.URLField('Test API URL', blank=True)
    
    # Özellikler
    supports_3d_secure = models.BooleanField('3D Secure Destekli', default=True)
    supports_installment = models.BooleanField('Taksit Destekli', default=False)
    supports_refund = models.BooleanField('İade Destekli', default=True)
    supports_recurring = models.BooleanField('Otomatik Ödeme Destekli', default=False)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Gateway Ayarları', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Ödeme Gateway'
        verbose_name_plural = 'Ödeme Gateway\'leri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.name} ({self.get_gateway_type_display()})"


class TenantPaymentGateway(TimeStampedModel):
    """
    Tenant Bazlı Ödeme Gateway Ayarları
    Her tenant kendi ödeme gateway ayarlarını yönetir
    """
    tenant = models.ForeignKey(
        Tenant,
        on_delete=models.CASCADE,
        related_name='payment_gateways',
        verbose_name='Tenant'
    )
    gateway = models.ForeignKey(
        PaymentGateway,
        on_delete=models.CASCADE,
        related_name='tenant_configs',
        verbose_name='Gateway'
    )
    
    # API Credentials (Şifrelenmiş)
    api_key = models.CharField('API Key', max_length=255, blank=True)
    secret_key = models.CharField('Secret Key', max_length=255, blank=True)
    merchant_id = models.CharField('Merchant ID', max_length=100, blank=True)
    store_key = models.CharField('Store Key', max_length=255, blank=True)
    
    # 3D Secure Ayarları
    use_3d_secure = models.BooleanField('3D Secure Kullan', default=True)
    callback_url = models.URLField('Callback URL', blank=True)
    
    # Taksit Ayarları
    enable_installment = models.BooleanField('Taksit Aktif', default=False)
    max_installment = models.IntegerField('Maksimum Taksit', default=12)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True)
    
    # Ek Ayarlar (JSON)
    settings = models.JSONField('Ek Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Tenant Ödeme Gateway'
        verbose_name_plural = 'Tenant Ödeme Gateway\'leri'
        unique_together = ('tenant', 'gateway')
        ordering = ['tenant', 'gateway']
    
    def __str__(self):
        return f"{self.tenant.name} - {self.gateway.name}"


class PaymentTransaction(TimeStampedModel):
    """
    Ödeme İşlemi Modeli
    Tüm ödeme işlemlerini kaydeder
    """
    tenant = models.ForeignKey(
        Tenant,
        on_delete=models.CASCADE,
        related_name='payment_transactions',
        verbose_name='Tenant'
    )
    gateway = models.ForeignKey(
        PaymentGateway,
        on_delete=models.SET_NULL,
        null=True,
        related_name='transactions',
        verbose_name='Gateway'
    )
    
    # İşlem Bilgileri
    transaction_id = models.CharField('İşlem ID', max_length=100, unique=True)
    order_id = models.CharField('Sipariş ID', max_length=100, blank=True)
    reference_number = models.CharField('Referans No', max_length=100, blank=True)
    
    # Ödeme Bilgileri
    amount = models.DecimalField('Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('processing', 'İşleniyor'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
        ('cancelled', 'İptal Edildi'),
        ('refunded', 'İade Edildi'),
        ('partially_refunded', 'Kısmi İade'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    # Ödeme Yöntemi
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, default='credit_card')
    installment_count = models.IntegerField('Taksit Sayısı', default=1)
    
    # Gateway Response
    gateway_response = models.JSONField('Gateway Yanıtı', default=dict, blank=True)
    gateway_transaction_id = models.CharField('Gateway İşlem ID', max_length=100, blank=True)
    
    # Kart Bilgileri (Hash'lenmiş)
    card_bin = models.CharField('Kart BIN', max_length=6, blank=True)
    card_last_four = models.CharField('Son 4 Hane', max_length=4, blank=True)
    card_type = models.CharField('Kart Tipi', max_length=20, blank=True)
    
    # 3D Secure
    is_3d_secure = models.BooleanField('3D Secure', default=False)
    md_status = models.CharField('MD Status', max_length=10, blank=True)
    
    # Hata Bilgileri
    error_code = models.CharField('Hata Kodu', max_length=50, blank=True)
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    # Tarih
    payment_date = models.DateTimeField('Ödeme Tarihi', null=True, blank=True)
    
    # Müşteri Bilgileri (Tenant oluşturma için)
    customer_name = models.CharField('Müşteri Adı', max_length=100, blank=True)
    customer_surname = models.CharField('Müşteri Soyadı', max_length=100, blank=True)
    customer_email = models.EmailField('Müşteri E-posta', blank=True, db_index=True)
    customer_phone = models.CharField('Müşteri Telefon', max_length=20, blank=True)
    customer_address = models.TextField('Müşteri Adres', blank=True)
    customer_city = models.CharField('Müşteri Şehir', max_length=100, blank=True)
    customer_country = models.CharField('Müşteri Ülke', max_length=100, default='Türkiye')
    customer_zip_code = models.CharField('Müşteri Posta Kodu', max_length=10, blank=True)
    
    # Kaynak Bilgisi (Hangi modülden geldiği)
    source_module = models.CharField('Kaynak Modül', max_length=50, blank=True,
                                     help_text='reception, tours, sales, refunds vb.')
    source_id = models.IntegerField('Kaynak ID', null=True, blank=True,
                                    help_text='Kaynak modülün kayıt ID\'si')
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True,
                                        help_text='Rezervasyon no, Tur adı, Satış kaydı vb.')
    
    # Entegrasyon ID'leri (Diğer modüllerle bağlantı)
    cash_transaction_id = models.IntegerField('Kasa İşlemi ID', null=True, blank=True,
                                             help_text='Finance modülündeki CashTransaction ID')
    accounting_payment_id = models.IntegerField('Muhasebe Ödeme ID', null=True, blank=True,
                                               help_text='Accounting modülündeki Payment ID')
    sales_record_id = models.IntegerField('Satış Kaydı ID', null=True, blank=True,
                                         help_text='Sales modülündeki SalesRecord ID')
    refund_transaction_id = models.IntegerField('İade İşlemi ID', null=True, blank=True,
                                               help_text='Refunds modülündeki RefundTransaction ID')
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Ödeme İşlemi'
        verbose_name_plural = 'Ödeme İşlemleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['transaction_id']),
            models.Index(fields=['tenant', 'status']),
            models.Index(fields=['created_at']),
        ]
    
    def __str__(self):
        return f"{self.transaction_id} - {self.amount} {self.currency} ({self.get_status_display()})"


class PaymentWebhook(TimeStampedModel):
    """
    Webhook Log Modeli
    Gateway'lerden gelen webhook'ları kaydeder
    """
    gateway = models.ForeignKey(
        PaymentGateway,
        on_delete=models.CASCADE,
        related_name='webhooks',
        verbose_name='Gateway'
    )
    transaction = models.ForeignKey(
        PaymentTransaction,
        on_delete=models.SET_NULL,
        null=True,
        related_name='webhooks',
        verbose_name='İşlem'
    )
    
    # Webhook Bilgileri
    event_type = models.CharField('Event Tipi', max_length=50)
    payload = models.JSONField('Payload', default=dict)
    headers = models.JSONField('Headers', default=dict)
    
    # Durum
    is_processed = models.BooleanField('İşlendi mi?', default=False)
    processing_error = models.TextField('İşleme Hatası', blank=True)
    
    class Meta:
        verbose_name = 'Webhook'
        verbose_name_plural = 'Webhook\'lar'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.gateway.name} - {self.event_type} ({self.created_at})"
