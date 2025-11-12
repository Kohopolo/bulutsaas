"""
İade Yönetimi (Refunds) Modelleri
Profesyonel iade yönetim sistemi - Tüm modüllerden kullanılabilir
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== İADE POLİTİKALARI ====================

class RefundPolicy(TimeStampedModel, SoftDeleteModel):
    """
    İade Politikaları
    Modül bazında iade kuralları tanımlanır
    """
    POLICY_TYPE_CHOICES = [
        ('percentage', 'Yüzde İadesi'),
        ('fixed', 'Sabit Tutar İadesi'),
        ('full', 'Tam İade'),
        ('no_refund', 'İade Yok'),
        ('custom', 'Özel Kural'),
    ]
    
    REFUND_METHOD_CHOICES = [
        ('original', 'Orijinal Ödeme Yöntemi'),
        ('cash', 'Nakit'),
        ('credit', 'Kredi/Hesap Bakiyesi'),
        ('voucher', 'Voucher/Hediye Çeki'),
    ]
    
    # Temel Bilgiler
    name = models.CharField('Politika Adı', max_length=200)
    code = models.SlugField('Politika Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Modül Bilgisi
    module = models.CharField('Modül', max_length=50, blank=True,
                             help_text='tours, reservations vb. (Boş = Tüm modüller)')
    
    # İade Tipi ve Kuralları
    policy_type = models.CharField('İade Tipi', max_length=20, choices=POLICY_TYPE_CHOICES, default='percentage')
    refund_percentage = models.DecimalField('İade Yüzdesi (%)', max_digits=5, decimal_places=2, 
                                           default=100, validators=[MinValueValidator(0), MaxValueValidator(100)],
                                           help_text='Yüzde iadesi için')
    refund_amount = models.DecimalField('Sabit İade Tutarı', max_digits=15, decimal_places=2, default=0,
                                       help_text='Sabit tutar iadesi için')
    
    # Zaman Kuralları
    days_before_start = models.IntegerField('Başlangıçtan Kaç Gün Önce', null=True, blank=True,
                                           help_text='Etkinlik/tur başlangıcından kaç gün önce iade yapılabilir')
    days_after_booking = models.IntegerField('Rezervasyondan Kaç Gün Sonra', null=True, blank=True,
                                            help_text='Rezervasyondan kaç gün sonra iade yapılabilir')
    max_refund_days = models.IntegerField('Maksimum İade Süresi (Gün)', null=True, blank=True,
                                         help_text='Rezervasyondan itibaren maksimum kaç gün içinde iade yapılabilir')
    
    # İade Yöntemi
    refund_method = models.CharField('İade Yöntemi', max_length=20, choices=REFUND_METHOD_CHOICES, default='original')
    
    # İşlem Ücreti
    processing_fee_percentage = models.DecimalField('İşlem Ücreti (%)', max_digits=5, decimal_places=2, default=0,
                                                    validators=[MinValueValidator(0), MaxValueValidator(100)])
    processing_fee_amount = models.DecimalField('Sabit İşlem Ücreti', max_digits=15, decimal_places=2, default=0)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Politika mı?', default=False)
    priority = models.IntegerField('Öncelik', default=0,
                                  help_text='Aynı modül için birden fazla politika varsa öncelik sırası')
    
    # Özel Kurallar (JSON)
    custom_rules = models.JSONField('Özel Kurallar', default=dict, blank=True,
                                   help_text='Özel iade kuralları JSON formatında')
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'İade Politikası'
        verbose_name_plural = 'İade Politikaları'
        ordering = ['priority', 'sort_order', 'name']
        indexes = [
            models.Index(fields=['module', 'is_active']),
            models.Index(fields=['is_default']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_policy_type_display()})"
    
    def calculate_refund_amount(self, original_amount, booking_date=None, start_date=None, current_date=None):
        """
        İade tutarını hesapla
        
        Args:
            original_amount: Orijinal ödeme tutarı
            booking_date: Rezervasyon tarihi
            start_date: Etkinlik/tur başlangıç tarihi
            current_date: İade talep tarihi (varsayılan: bugün)
        
        Returns:
            (refund_amount, processing_fee, net_refund)
        """
        if current_date is None:
            current_date = timezone.now().date()
        
        # Zaman kontrolü
        if booking_date:
            days_since_booking = (current_date - booking_date).days
            if self.max_refund_days and days_since_booking > self.max_refund_days:
                return Decimal('0'), Decimal('0'), Decimal('0')
        
        if start_date:
            days_before_start = (start_date - current_date).days
            if self.days_before_start and days_before_start < self.days_before_start:
                return Decimal('0'), Decimal('0'), Decimal('0')
        
        # İade tutarı hesapla
        if self.policy_type == 'no_refund':
            refund_amount = Decimal('0')
        elif self.policy_type == 'full':
            refund_amount = original_amount
        elif self.policy_type == 'percentage':
            refund_amount = original_amount * (self.refund_percentage / 100)
        elif self.policy_type == 'fixed':
            refund_amount = min(self.refund_amount, original_amount)
        else:  # custom
            # Özel kurallar uygulanır
            refund_amount = original_amount * (self.refund_percentage / 100)
        
        # İşlem ücreti hesapla
        if self.processing_fee_percentage > 0:
            processing_fee = refund_amount * (self.processing_fee_percentage / 100)
        else:
            processing_fee = self.processing_fee_amount
        
        net_refund = refund_amount - processing_fee
        
        return max(Decimal('0'), refund_amount), max(Decimal('0'), processing_fee), max(Decimal('0'), net_refund)


# ==================== İADE TALEPLERİ ====================

class RefundRequest(TimeStampedModel, SoftDeleteModel):
    """
    İade Talepleri
    Müşterilerden gelen iade talepleri
    """
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('approved', 'Onaylandı'),
        ('rejected', 'Reddedildi'),
        ('processing', 'İşleniyor'),
        ('completed', 'Tamamlandı'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    # Temel Bilgiler
    request_number = models.CharField('Talep No', max_length=50, unique=True,
                                     help_text='Otomatik oluşturulur')
    request_date = models.DateTimeField('Talep Tarihi', default=timezone.now)
    
    # Kaynak Bilgisi
    source_module = models.CharField('Kaynak Modül', max_length=50,
                                     help_text='tours, reservations vb.')
    source_id = models.IntegerField('Kaynak ID',
                                    help_text='Kaynak modülün kayıt ID\'si')
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True,
                                        help_text='Rezervasyon no, Tur adı vb.')
    
    # Müşteri (Merkezi CRM entegrasyonu)
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='refund_requests',
        verbose_name='Müşteri Profili'
    )
    
    # Müşteri Bilgileri (Customer bulunamazsa manuel giriş için)
    customer_name = models.CharField('Müşteri Adı', max_length=200)
    customer_email = models.EmailField('Müşteri E-posta')
    customer_phone = models.CharField('Müşteri Telefon', max_length=20, blank=True)
    
    # Orijinal Ödeme Bilgileri
    original_amount = models.DecimalField('Orijinal Tutar', max_digits=15, decimal_places=2)
    original_payment_method = models.CharField('Orijinal Ödeme Yöntemi', max_length=50, blank=True)
    original_payment_date = models.DateField('Orijinal Ödeme Tarihi', null=True, blank=True)
    
    # İade Politikası
    refund_policy = models.ForeignKey(
        RefundPolicy,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='refund_requests',
        verbose_name='İade Politikası'
    )
    
    # İade Hesaplaması
    refund_amount = models.DecimalField('İade Tutarı', max_digits=15, decimal_places=2, default=0)
    processing_fee = models.DecimalField('İşlem Ücreti', max_digits=15, decimal_places=2, default=0)
    net_refund = models.DecimalField('Net İade Tutarı', max_digits=15, decimal_places=2, default=0)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # İade Yöntemi
    refund_method = models.CharField('İade Yöntemi', max_length=20, 
                                    choices=RefundPolicy.REFUND_METHOD_CHOICES, default='original')
    
    # Talep Nedeni
    reason = models.TextField('İade Nedeni', help_text='Müşterinin iade talep nedeni')
    customer_notes = models.TextField('Müşteri Notları', blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    status_changed_at = models.DateTimeField('Durum Değişim Tarihi', null=True, blank=True)
    
    # Onay Bilgileri
    approved_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='approved_refund_requests',
        verbose_name='Onaylayan'
    )
    approved_at = models.DateTimeField('Onay Tarihi', null=True, blank=True)
    rejection_reason = models.TextField('Red Nedeni', blank=True,
                                       help_text='Reddedilme nedeni')
    
    # İşlem Bilgileri
    processed_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='processed_refund_requests',
        verbose_name='İşleyen'
    )
    processed_at = models.DateTimeField('İşlem Tarihi', null=True, blank=True)
    
    # Oluşturan
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_refund_requests',
        verbose_name='Oluşturan'
    )
    
    # Ek Bilgiler
    attachments = models.JSONField('Ekler', default=list, blank=True,
                                  help_text='Dosya URL\'leri listesi')
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'İade Talebi'
        verbose_name_plural = 'İade Talepleri'
        ordering = ['-request_date', '-request_number']
        indexes = [
            models.Index(fields=['source_module', 'source_id']),
            models.Index(fields=['status', 'request_date']),
            models.Index(fields=['request_number']),
        ]
    
    def __str__(self):
        return f"{self.request_number} - {self.customer_name} - {self.net_refund} {self.currency}"
    
    def save(self, *args, **kwargs):
        # Müşteri profilini eşleştir veya oluştur (Merkezi CRM entegrasyonu)
        if not self.customer and (self.customer_email or self.customer_phone):
            from apps.tenant_apps.core.models import Customer as CoreCustomer
            customer, created = CoreCustomer.get_or_create_by_identifier(
                email=self.customer_email,
                phone=self.customer_phone,
                defaults={
                    'first_name': self.customer_name.split()[0] if self.customer_name else '',
                    'last_name': ' '.join(self.customer_name.split()[1:]) if len(self.customer_name.split()) > 1 else '',
                }
            )
            self.customer = customer
            
            # Customer varsa bilgileri güncelle
            if self.customer:
                self.customer_name = self.customer.get_full_name()
                if self.customer.email:
                    self.customer_email = self.customer.email
                if self.customer.phone:
                    self.customer_phone = self.customer.phone
        
        if not self.request_number:
            # Otomatik talep numarası oluştur
            from datetime import datetime
            prefix = 'IDT'
            date_str = datetime.now().strftime('%Y%m%d')
            last_request = RefundRequest.objects.filter(
                request_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-request_number').first()
            
            if last_request:
                last_num = int(last_request.request_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.request_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        super().save(*args, **kwargs)
    
    def approve(self, user, notes=''):
        """İade talebini onayla"""
        if self.status == 'pending':
            self.status = 'approved'
            self.approved_by = user
            self.approved_at = timezone.now()
            self.status_changed_at = timezone.now()
            if notes:
                self.notes = f"{self.notes}\nOnay Notu: {notes}".strip()
            self.save()
    
    def reject(self, user, reason=''):
        """İade talebini reddet"""
        if self.status == 'pending':
            self.status = 'rejected'
            self.approved_by = user
            self.approved_at = timezone.now()
            self.status_changed_at = timezone.now()
            if reason:
                self.rejection_reason = reason
            self.save()
    
    def process(self, user, notes=''):
        """İade talebini işleme al"""
        if self.status == 'approved':
            self.status = 'processing'
            self.processed_by = user
            self.processed_at = timezone.now()
            self.status_changed_at = timezone.now()
            if notes:
                self.notes = f"{self.notes}\nİşlem Notu: {notes}".strip()
            self.save()
    
    def complete(self, user, notes=''):
        """İade talebini tamamla"""
        if self.status == 'processing':
            self.status = 'completed'
            self.status_changed_at = timezone.now()
            if notes:
                self.notes = f"{self.notes}\nTamamlanma Notu: {notes}".strip()
            self.save()


# ==================== İADE İŞLEMLERİ ====================

class RefundTransaction(TimeStampedModel, SoftDeleteModel):
    """
    İade İşlemleri
    Gerçekleştirilen iade işlemleri
    """
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('processing', 'İşleniyor'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    # İade Talebi İlişkisi
    refund_request = models.ForeignKey(
        RefundRequest,
        on_delete=models.CASCADE,
        related_name='transactions',
        verbose_name='İade Talebi'
    )
    
    # İşlem Bilgileri
    transaction_number = models.CharField('İşlem No', max_length=50, unique=True,
                                         help_text='Otomatik oluşturulur')
    transaction_date = models.DateTimeField('İşlem Tarihi', default=timezone.now)
    amount = models.DecimalField('İade Tutarı', max_digits=15, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # İade Yöntemi
    refund_method = models.CharField('İade Yöntemi', max_length=20,
                                     choices=RefundPolicy.REFUND_METHOD_CHOICES)
    
    # Ödeme Bilgileri (İade için)
    payment_reference = models.CharField('Ödeme Referansı', max_length=200, blank=True,
                                        help_text='İade ödeme referans numarası')
    payment_provider = models.CharField('Ödeme Sağlayıcı', max_length=100, blank=True,
                                       help_text='İyzico, PayTR vb.')
    
    # Kasa/Muhasebe Entegrasyonu
    cash_transaction_id = models.IntegerField('Kasa İşlemi ID', null=True, blank=True,
                                             help_text='Finance modülündeki CashTransaction ID')
    accounting_entry_id = models.IntegerField('Muhasebe Kaydı ID', null=True, blank=True,
                                            help_text='Accounting modülündeki JournalEntry ID')
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    status_changed_at = models.DateTimeField('Durum Değişim Tarihi', null=True, blank=True)
    
    # Hata Bilgisi
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    # İşlem Yapan
    processed_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='processed_refund_transactions',
        verbose_name='İşleyen'
    )
    
    # Ek Bilgiler
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'İade İşlemi'
        verbose_name_plural = 'İade İşlemleri'
        ordering = ['-transaction_date', '-transaction_number']
        indexes = [
            models.Index(fields=['refund_request', 'status']),
            models.Index(fields=['transaction_number']),
        ]
    
    def __str__(self):
        return f"{self.transaction_number} - {self.amount} {self.currency}"
    
    def save(self, *args, **kwargs):
        if not self.transaction_number:
            # Otomatik işlem numarası oluştur
            from datetime import datetime
            prefix = 'IDI'
            date_str = datetime.now().strftime('%Y%m%d')
            last_trans = RefundTransaction.objects.filter(
                transaction_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-transaction_number').first()
            
            if last_trans:
                last_num = int(last_trans.transaction_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.transaction_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        super().save(*args, **kwargs)
    
    def complete(self, user=None):
        """İade işlemini tamamla"""
        if self.status in ['pending', 'processing']:
            self.status = 'completed'
            self.status_changed_at = timezone.now()
            if user:
                self.processed_by = user
            self.save()
            
            # İade talebini tamamla
            if self.refund_request.status == 'processing':
                self.refund_request.complete(user=user)
    
    def fail(self, error_message=''):
        """İade işlemini başarısız olarak işaretle"""
        if self.status in ['pending', 'processing']:
            self.status = 'failed'
            self.status_changed_at = timezone.now()
            if error_message:
                self.error_message = error_message
            self.save()

