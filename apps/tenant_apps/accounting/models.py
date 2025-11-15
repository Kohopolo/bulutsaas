"""
Muhasebe (Accounting) Yönetim Modelleri
Profesyonel muhasebe sistemi - Hesap planı, yevmiye, fatura, ödeme
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.db.models import Sum
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== HESAP PLANI ====================

class Account(TimeStampedModel, SoftDeleteModel):
    """
    Hesap Planı
    Muhasebe hesap planı (Aktif, Pasif, Gelir, Gider hesapları)
    Otel bazlı veya genel hesap planı olabilir
    """
    ACCOUNT_TYPE_CHOICES = [
        ('asset', 'Aktif'),
        ('liability', 'Pasif'),
        ('equity', 'Özsermaye'),
        ('revenue', 'Gelir'),
        ('expense', 'Gider'),
    ]
    
    CURRENCY_CHOICES = [
        ('TRY', 'Türk Lirası'),
        ('USD', 'US Dollar'),
        ('EUR', 'Euro'),
        ('GBP', 'British Pound'),
    ]
    
    # Otel Bağlantısı (null ise genel hesap)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='accounts',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa tüm oteller için genel hesap olur'
    )
    
    # Hesap Bilgileri
    code = models.CharField('Hesap Kodu', max_length=20,
                           help_text='Hesap planı kodu (örn: 100, 120, 600)')
    name = models.CharField('Hesap Adı', max_length=200)
    account_type = models.CharField('Hesap Tipi', max_length=20, choices=ACCOUNT_TYPE_CHOICES)
    currency = models.CharField('Para Birimi', max_length=3, choices=CURRENCY_CHOICES, default='TRY')
    
    # Hiyerarşi
    parent = models.ForeignKey(
        'self',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='children',
        verbose_name='Üst Hesap',
        help_text='Hesap hiyerarşisi için'
    )
    level = models.IntegerField('Seviye', default=1,
                               help_text='Hesap hiyerarşi seviyesi (1, 2, 3, ...)')
    
    # Bakiye Bilgileri
    opening_balance = models.DecimalField('Açılış Bakiyesi', max_digits=15, decimal_places=2, default=0)
    debit_balance = models.DecimalField('Borç Bakiyesi', max_digits=15, decimal_places=2, default=0,
                                       help_text='Otomatik hesaplanır')
    credit_balance = models.DecimalField('Alacak Bakiyesi', max_digits=15, decimal_places=2, default=0,
                                        help_text='Otomatik hesaplanır')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system = models.BooleanField('Sistem Hesabı mı?', default=False,
                                   help_text='Sistem hesapları silinemez')
    
    # Açıklama
    description = models.TextField('Açıklama', blank=True)
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Hesap'
        verbose_name_plural = 'Hesap Planı'
        ordering = ['code', 'name']
        unique_together = [('hotel', 'code')]  # Aynı otel için kod benzersiz olmalı
        indexes = [
            models.Index(fields=['code']),
            models.Index(fields=['hotel', 'account_type']),
            models.Index(fields=['account_type']),
            models.Index(fields=['parent']),
        ]
    
    def __str__(self):
        return f"{self.code} - {self.name}"
    
    def get_balance(self):
        """Hesap bakiyesini döndür"""
        if self.account_type in ['asset', 'expense']:
            # Aktif ve Gider hesapları: Borç - Alacak
            return self.debit_balance - self.credit_balance
        else:
            # Pasif, Özsermaye, Gelir hesapları: Alacak - Borç
            return self.credit_balance - self.debit_balance
    
    def calculate_balance(self):
        """Hesap bakiyesini hesapla"""
        # Yevmiye kayıtlarından borç/alacak toplamlarını hesapla
        debit_total = JournalEntryLine.objects.filter(
            account=self,
            journal_entry__status='posted',
            journal_entry__is_deleted=False
        ).aggregate(total=Sum('debit'))['total'] or Decimal('0')
        
        credit_total = JournalEntryLine.objects.filter(
            account=self,
            journal_entry__status='posted',
            journal_entry__is_deleted=False
        ).aggregate(total=Sum('credit'))['total'] or Decimal('0')
        
        self.debit_balance = self.opening_balance + debit_total
        self.credit_balance = credit_total
        self.save(update_fields=['debit_balance', 'credit_balance'])
        
        return self.get_balance()


# ==================== YEVMİYE KAYITLARI ====================

class JournalEntry(TimeStampedModel, SoftDeleteModel):
    """
    Yevmiye Kayıtları
    Çift taraflı muhasebe kayıtları
    Otel bazlı veya genel yevmiye kayıtları olabilir
    """
    STATUS_CHOICES = [
        ('draft', 'Taslak'),
        ('posted', 'Kaydedildi'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    # Otel Bağlantısı (null ise genel kayıt)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.SET_NULL,
        related_name='journal_entries',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa genel yevmiye kaydı olur'
    )
    
    # Temel Bilgiler
    entry_number = models.CharField('Yevmiye No', max_length=50, unique=True,
                                   help_text='Otomatik oluşturulur')
    entry_date = models.DateField('Kayıt Tarihi', default=timezone.now)
    description = models.TextField('Açıklama')
    
    # Kaynak Bilgisi
    source_module = models.CharField('Kaynak Modül', max_length=50, blank=True,
                                    help_text='tours, reservations, finance vb.')
    source_id = models.IntegerField('Kaynak ID', null=True, blank=True)
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='draft')
    posted_at = models.DateTimeField('Kayıt Tarihi', null=True, blank=True)
    posted_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='posted_journal_entries',
        verbose_name='Kaydeden'
    )
    
    # Oluşturan
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_journal_entries',
        verbose_name='Oluşturan'
    )
    
    # Ek Bilgiler
    notes = models.TextField('Notlar', blank=True)
    attachments = models.JSONField('Ekler', default=list, blank=True)
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Yevmiye Kaydı'
        verbose_name_plural = 'Yevmiye Kayıtları'
        ordering = ['-entry_date', '-entry_number']
        indexes = [
            models.Index(fields=['entry_date', 'status']),
            models.Index(fields=['hotel', 'entry_date']),
            models.Index(fields=['source_module', 'source_id']),
            models.Index(fields=['entry_number']),
        ]
    
    def __str__(self):
        return f"{self.entry_number} - {self.entry_date}"
    
    def save(self, *args, **kwargs):
        if not self.entry_number:
            # Otomatik yevmiye numarası oluştur
            from datetime import datetime
            prefix = 'YEV'
            date_str = datetime.now().strftime('%Y%m%d')
            last_entry = JournalEntry.objects.filter(
                entry_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-entry_number').first()
            
            if last_entry:
                last_num = int(last_entry.entry_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.entry_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        super().save(*args, **kwargs)
    
    def get_total_debit(self):
        """Toplam borç"""
        return self.lines.aggregate(total=Sum('debit'))['total'] or Decimal('0')
    
    def get_total_credit(self):
        """Toplam alacak"""
        return self.lines.aggregate(total=Sum('credit'))['total'] or Decimal('0')
    
    def is_balanced(self):
        """Borç ve alacak eşit mi?"""
        return self.get_total_debit() == self.get_total_credit()
    
    def post(self, user=None):
        """Yevmiye kaydını kaydet"""
        if not self.is_balanced():
            raise ValueError('Borç ve alacak toplamları eşit değil!')
        
        if self.status == 'draft':
            self.status = 'posted'
            self.posted_at = timezone.now()
            if user:
                self.posted_by = user
            self.save()
            
            # Hesapların bakiyelerini güncelle
            for line in self.lines.all():
                line.account.calculate_balance()
    
    def cancel(self, reason=''):
        """Yevmiye kaydını iptal et"""
        if self.status == 'posted':
            self.status = 'cancelled'
            if reason:
                self.notes = f"{self.notes}\nİptal Nedeni: {reason}".strip()
            self.save()
            
            # Hesapların bakiyelerini güncelle
            for line in self.lines.all():
                line.account.calculate_balance()


class JournalEntryLine(TimeStampedModel):
    """
    Yevmiye Kayıt Satırları
    Her yevmiye kaydı birden fazla hesap içerebilir
    """
    journal_entry = models.ForeignKey(
        JournalEntry,
        on_delete=models.CASCADE,
        related_name='lines',
        verbose_name='Yevmiye Kaydı'
    )
    account = models.ForeignKey(
        Account,
        on_delete=models.PROTECT,
        related_name='journal_entry_lines',
        verbose_name='Hesap'
    )
    
    # Borç ve Alacak
    debit = models.DecimalField('Borç', max_digits=15, decimal_places=2, default=0,
                               validators=[MinValueValidator(0)])
    credit = models.DecimalField('Alacak', max_digits=15, decimal_places=2, default=0,
                                validators=[MinValueValidator(0)])
    
    # Açıklama
    description = models.CharField('Açıklama', max_length=500, blank=True)
    
    class Meta:
        verbose_name = 'Yevmiye Kayıt Satırı'
        verbose_name_plural = 'Yevmiye Kayıt Satırları'
        ordering = ['journal_entry', 'id']
    
    def __str__(self):
        return f"{self.journal_entry.entry_number} - {self.account.code} (Borç: {self.debit}, Alacak: {self.credit})"
    
    def clean(self):
        """Borç ve alacak aynı anda dolu olamaz"""
        from django.core.exceptions import ValidationError
        if self.debit > 0 and self.credit > 0:
            raise ValidationError('Borç ve alacak aynı anda dolu olamaz!')


# ==================== FATURA ====================

class Invoice(TimeStampedModel, SoftDeleteModel):
    """
    Fatura
    Gelir ve gider faturaları
    Otel bazlı veya genel faturalar olabilir
    """
    INVOICE_TYPE_CHOICES = [
        ('sales', 'Satış Faturası'),
        ('purchase', 'Alış Faturası'),
        ('expense', 'Gider Faturası'),
    ]
    
    STATUS_CHOICES = [
        ('draft', 'Taslak'),
        ('sent', 'Gönderildi'),
        ('paid', 'Ödendi'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    # Otel Bağlantısı (null ise genel fatura)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.SET_NULL,
        related_name='invoices',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa genel fatura olur'
    )
    
    # Temel Bilgiler
    invoice_number = models.CharField('Fatura No', max_length=50, unique=True,
                                      help_text='Otomatik oluşturulur')
    invoice_type = models.CharField('Fatura Tipi', max_length=20, choices=INVOICE_TYPE_CHOICES)
    invoice_date = models.DateField('Fatura Tarihi', default=timezone.now)
    due_date = models.DateField('Vade Tarihi', null=True, blank=True)
    
    # Müşteri/Tedarikçi
    # Müşteri (Merkezi CRM entegrasyonu)
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='invoices',
        verbose_name='Müşteri Profili'
    )
    
    # Müşteri Bilgileri (Customer bulunamazsa manuel giriş için)
    customer_name = models.CharField('Müşteri/Tedarikçi Adı', max_length=200)
    customer_tax_id = models.CharField('Vergi No/TC', max_length=50, blank=True)
    customer_address = models.TextField('Adres', blank=True)
    customer_email = models.EmailField('E-posta', blank=True)
    customer_phone = models.CharField('Telefon', max_length=20, blank=True)
    
    # Tutar Bilgileri
    subtotal = models.DecimalField('Ara Toplam', max_digits=15, decimal_places=2, default=0)
    tax_rate = models.DecimalField('KDV Oranı (%)', max_digits=5, decimal_places=2, default=20)
    tax_amount = models.DecimalField('KDV Tutarı', max_digits=15, decimal_places=2, default=0)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=15, decimal_places=2, default=0)
    total_amount = models.DecimalField('Toplam Tutar', max_digits=15, decimal_places=2, default=0)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Ödeme Bilgileri
    paid_amount = models.DecimalField('Ödenen Tutar', max_digits=15, decimal_places=2, default=0)
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='draft')
    
    # Kaynak Bilgisi
    source_module = models.CharField('Kaynak Modül', max_length=50, blank=True)
    source_id = models.IntegerField('Kaynak ID', null=True, blank=True)
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True)
    
    # Açıklama
    description = models.TextField('Açıklama', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    # Oluşturan
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_invoices',
        verbose_name='Oluşturan'
    )
    
    # Ek Bilgiler
    attachments = models.JSONField('Ekler', default=list, blank=True)
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Fatura'
        verbose_name_plural = 'Faturalar'
        ordering = ['-invoice_date', '-invoice_number']
        indexes = [
            models.Index(fields=['invoice_date', 'status']),
            models.Index(fields=['hotel', 'invoice_date']),
            models.Index(fields=['invoice_number']),
            models.Index(fields=['source_module', 'source_id']),
        ]
    
    def __str__(self):
        return f"{self.invoice_number} - {self.customer_name} - {self.total_amount} {self.currency}"
    
    def save(self, *args, **kwargs):
        # Müşteri profilini eşleştir veya oluştur (Merkezi CRM entegrasyonu)
        if not self.customer and (self.customer_email or self.customer_phone or self.customer_tax_id):
            from apps.tenant_apps.core.models import Customer as CoreCustomer
            customer, created = CoreCustomer.get_or_create_by_identifier(
                email=self.customer_email,
                phone=self.customer_phone,
                tc_no=self.customer_tax_id,
                defaults={
                    'first_name': self.customer_name.split()[0] if self.customer_name else '',
                    'last_name': ' '.join(self.customer_name.split()[1:]) if len(self.customer_name.split()) > 1 else '',
                    'address': self.customer_address,
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
                if self.customer.tc_no:
                    self.customer_tax_id = self.customer.tc_no
                if self.customer.address:
                    self.customer_address = self.customer.address
        
        if not self.invoice_number:
            # Otomatik fatura numarası oluştur
            from datetime import datetime
            prefix = 'FAT'
            date_str = datetime.now().strftime('%Y%m%d')
            last_invoice = Invoice.objects.filter(
                invoice_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-invoice_number').first()
            
            if last_invoice:
                last_num = int(last_invoice.invoice_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.invoice_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        # KDV ve toplam hesapla
        self.tax_amount = (self.subtotal - self.discount_amount) * (self.tax_rate / 100)
        self.total_amount = self.subtotal - self.discount_amount + self.tax_amount
        
        # Ödeme durumu güncelle
        if self.paid_amount >= self.total_amount:
            self.status = 'paid'
        elif self.paid_amount > 0:
            self.status = 'sent'
        
        super().save(*args, **kwargs)
    
    def get_remaining_amount(self):
        """Kalan tutar"""
        return self.total_amount - self.paid_amount


class InvoiceLine(TimeStampedModel):
    """
    Fatura Satırları
    """
    invoice = models.ForeignKey(
        Invoice,
        on_delete=models.CASCADE,
        related_name='lines',
        verbose_name='Fatura'
    )
    
    # Ürün/Hizmet Bilgileri
    item_name = models.CharField('Ürün/Hizmet Adı', max_length=200)
    item_code = models.CharField('Ürün/Hizmet Kodu', max_length=50, blank=True)
    quantity = models.DecimalField('Miktar', max_digits=10, decimal_places=2, default=1)
    unit_price = models.DecimalField('Birim Fiyat', max_digits=15, decimal_places=2)
    discount_rate = models.DecimalField('İndirim Oranı (%)', max_digits=5, decimal_places=2, default=0)
    line_total = models.DecimalField('Satır Toplamı', max_digits=15, decimal_places=2)
    
    # Açıklama
    description = models.TextField('Açıklama', blank=True)
    
    class Meta:
        verbose_name = 'Fatura Satırı'
        verbose_name_plural = 'Fatura Satırları'
        ordering = ['invoice', 'id']
    
    def __str__(self):
        return f"{self.invoice.invoice_number} - {self.item_name}"
    
    def save(self, *args, **kwargs):
        # Satır toplamı hesapla
        discount = (self.unit_price * self.quantity) * (self.discount_rate / 100)
        self.line_total = (self.unit_price * self.quantity) - discount
        
        super().save(*args, **kwargs)
        
        # Fatura toplamını güncelle
        self.invoice.subtotal = self.invoice.lines.aggregate(total=Sum('line_total'))['total'] or Decimal('0')
        self.invoice.save()


# ==================== ÖDEME KAYITLARI ====================

class Payment(TimeStampedModel, SoftDeleteModel):
    """
    Ödeme Kayıtları
    Fatura ödemeleri ve diğer ödemeler
    Otel bazlı veya genel ödemeler olabilir
    """
    PAYMENT_METHOD_CHOICES = [
        ('cash', 'Nakit'),
        ('bank_transfer', 'Banka Havalesi'),
        ('credit_card', 'Kredi Kartı'),
        ('check', 'Çek'),
        ('digital_wallet', 'Dijital Cüzdan'),
        ('other', 'Diğer'),
    ]
    
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('completed', 'Tamamlandı'),
        ('cancelled', 'İptal Edildi'),
    ]
    
    # Otel Bağlantısı (null ise genel ödeme)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.SET_NULL,
        related_name='payments',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa genel ödeme olur'
    )
    
    # Temel Bilgiler
    payment_number = models.CharField('Ödeme No', max_length=50, unique=True,
                                     help_text='Otomatik oluşturulur')
    payment_date = models.DateTimeField('Ödeme Tarihi', default=timezone.now)
    amount = models.DecimalField('Tutar', max_digits=15, decimal_places=2, validators=[MinValueValidator(0)])
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    payment_method = models.CharField('Ödeme Yöntemi', max_length=20, choices=PAYMENT_METHOD_CHOICES, default='cash')
    
    # Fatura İlişkisi
    invoice = models.ForeignKey(
        Invoice,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='payments',
        verbose_name='Fatura'
    )
    
    # Kasa Hesabı (Finance modülünden)
    cash_account_id = models.IntegerField('Kasa Hesabı ID', null=True, blank=True,
                                         help_text='Finance modülündeki CashAccount ID')
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    # Kaynak Bilgisi
    source_module = models.CharField('Kaynak Modül', max_length=50, blank=True)
    source_id = models.IntegerField('Kaynak ID', null=True, blank=True)
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True)
    
    # Açıklama
    description = models.TextField('Açıklama', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    # Oluşturan
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_payments',
        verbose_name='Oluşturan'
    )
    
    # Ek Bilgiler
    attachments = models.JSONField('Ekler', default=list, blank=True)
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Ödeme'
        verbose_name_plural = 'Ödemeler'
        ordering = ['-payment_date', '-payment_number']
        indexes = [
            models.Index(fields=['payment_date', 'status']),
            models.Index(fields=['hotel', 'payment_date']),
            models.Index(fields=['invoice']),
            models.Index(fields=['source_module', 'source_id']),
        ]
    
    def __str__(self):
        return f"{self.payment_number} - {self.amount} {self.currency}"
    
    def save(self, *args, **kwargs):
        if not self.payment_number:
            # Otomatik ödeme numarası oluştur
            from datetime import datetime
            prefix = 'ODM'
            date_str = datetime.now().strftime('%Y%m%d')
            last_payment = Payment.objects.filter(
                payment_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-payment_number').first()
            
            if last_payment:
                last_num = int(last_payment.payment_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.payment_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        super().save(*args, **kwargs)
        
        # Fatura ödeme durumunu güncelle
        if self.invoice:
            total_paid = self.invoice.payments.filter(
                status='completed',
                is_deleted=False
            ).exclude(pk=self.pk).aggregate(total=Sum('amount'))['total'] or Decimal('0')
            
            if self.status == 'completed':
                total_paid += self.amount
            
            self.invoice.paid_amount = total_paid
            self.invoice.save()
    
    def complete(self):
        """Ödemeyi tamamla"""
        if self.status == 'pending':
            self.status = 'completed'
            self.save()

