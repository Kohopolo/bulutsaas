"""
Kasa (Finance) Yönetim Modelleri
Profesyonel kasa yönetim sistemi - Tüm modüllerden kullanılabilir
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== KASA HESAPLARI ====================

class CashAccount(TimeStampedModel, SoftDeleteModel):
    """
    Kasa Hesapları
    Nakit, Banka, Kredi Kartı, Dijital Cüzdan vb.
    Otel bazlı veya genel kasa hesapları olabilir
    """
    ACCOUNT_TYPE_CHOICES = [
        ('cash', 'Nakit Kasa'),
        ('bank', 'Banka Hesabı'),
        ('credit_card', 'Kredi Kartı'),
        ('digital_wallet', 'Dijital Cüzdan'),
        ('check', 'Çek'),
        ('other', 'Diğer'),
    ]
    
    CURRENCY_CHOICES = [
        ('TRY', 'Türk Lirası'),
        ('USD', 'US Dollar'),
        ('EUR', 'Euro'),
        ('GBP', 'British Pound'),
    ]
    
    # Otel Bağlantısı (null ise genel kasa hesabı)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='cash_accounts',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa tüm oteller için genel kasa hesabı olur'
    )
    
    name = models.CharField('Hesap Adı', max_length=100)
    code = models.SlugField('Hesap Kodu', max_length=50)
    account_type = models.CharField('Hesap Tipi', max_length=20, choices=ACCOUNT_TYPE_CHOICES, default='cash')
    currency = models.CharField('Para Birimi', max_length=3, choices=CURRENCY_CHOICES, default='TRY')
    
    # Banka Bilgileri (Banka hesabı ise)
    bank_name = models.CharField('Banka Adı', max_length=100, blank=True)
    branch_name = models.CharField('Şube Adı', max_length=100, blank=True)
    account_number = models.CharField('Hesap No', max_length=50, blank=True)
    iban = models.CharField('IBAN', max_length=34, blank=True)
    
    # Başlangıç Bakiyesi
    initial_balance = models.DecimalField('Başlangıç Bakiyesi', max_digits=15, decimal_places=2, default=0)
    current_balance = models.DecimalField('Güncel Bakiye', max_digits=15, decimal_places=2, default=0,
                                          help_text='Otomatik hesaplanır')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Hesap mı?', default=False,
                                     help_text='Varsayılan hesap olarak kullanılır')
    
    # Ayarlar
    description = models.TextField('Açıklama', blank=True)
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Kasa Hesabı'
        verbose_name_plural = 'Kasa Hesapları'
        ordering = ['sort_order', 'name']
        unique_together = [('hotel', 'code')]  # Aynı otel için kod benzersiz olmalı
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
            models.Index(fields=['hotel', 'is_default']),
        ]
    
    def __str__(self):
        return f"{self.name} ({self.get_account_type_display()})"
    
    def calculate_balance(self):
        """Hesap bakiyesini hesapla"""
        total_income = self.transactions.filter(
            transaction_type='income',
            status='completed'
        ).aggregate(total=models.Sum('amount'))['total'] or Decimal('0')
        
        total_expense = self.transactions.filter(
            transaction_type='expense',
            status='completed'
        ).aggregate(total=models.Sum('amount'))['total'] or Decimal('0')
        
        self.current_balance = self.initial_balance + total_income - total_expense
        self.save(update_fields=['current_balance'])
        return self.current_balance
    
    def get_balance(self):
        """Güncel bakiyeyi döndür (hesaplamadan)"""
        return self.current_balance
    
    def save(self, *args, **kwargs):
        # Otel bazlı varsayılan hesap: Her otel için sadece bir hesap varsayılan olabilir
        if self.is_default:
            # Aynı otel ve para birimi için diğer hesapları varsayılan olmaktan çıkar
            filter_kwargs = {'is_default': True, 'currency': self.currency}
            if self.hotel:
                filter_kwargs['hotel'] = self.hotel
            else:
                filter_kwargs['hotel__isnull'] = True
            CashAccount.objects.filter(**filter_kwargs).exclude(pk=self.pk).update(is_default=False)
        super().save(*args, **kwargs)


# ==================== KASA İŞLEMLERİ ====================

class CashTransaction(TimeStampedModel, SoftDeleteModel):
    """
    Kasa İşlemleri
    Tüm modüllerden (Tur, Rezervasyon, vb.) kasa işlemleri buradan yönetilir
    Otel bazlı veya genel kasa işlemleri olabilir
    """
    TRANSACTION_TYPE_CHOICES = [
        ('income', 'Gelir'),
        ('expense', 'Gider'),
        ('transfer', 'Transfer'),
        ('adjustment', 'Düzeltme'),
    ]
    
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('completed', 'Tamamlandı'),
        ('cancelled', 'İptal Edildi'),
        ('reversed', 'Geri Alındı'),
    ]
    
    PAYMENT_METHOD_CHOICES = [
        ('cash', 'Nakit'),
        ('bank_transfer', 'Banka Havalesi'),
        ('credit_card', 'Kredi Kartı'),
        ('check', 'Çek'),
        ('digital_wallet', 'Dijital Cüzdan'),
        ('other', 'Diğer'),
    ]
    
    # Otel Bağlantısı (null ise genel işlem)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.SET_NULL,
        related_name='cash_transactions',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa genel kasa işlemi olur'
    )
    
    # Temel Bilgiler
    transaction_number = models.CharField('İşlem No', max_length=50, unique=True,
                                          help_text='Otomatik oluşturulur')
    account = models.ForeignKey(
        CashAccount,
        on_delete=models.PROTECT,
        related_name='transactions',
        verbose_name='Kasa Hesabı'
    )
    transaction_type = models.CharField('İşlem Tipi', max_length=20, choices=TRANSACTION_TYPE_CHOICES)
    amount = models.DecimalField('Tutar', max_digits=15, decimal_places=2, validators=[MinValueValidator(0)])
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Transfer İşlemleri için
    to_account = models.ForeignKey(
        CashAccount,
        on_delete=models.PROTECT,
        related_name='incoming_transfers',
        null=True,
        blank=True,
        verbose_name='Hedef Hesap',
        help_text='Transfer işlemleri için'
    )
    
    # Kaynak Bilgisi (Hangi modülden geldiği)
    source_module = models.CharField('Kaynak Modül', max_length=50, blank=True,
                                     help_text='tours, reservations, vb.')
    source_id = models.IntegerField('Kaynak ID', null=True, blank=True,
                                     help_text='Kaynak modülün kayıt ID\'si')
    source_reference = models.CharField('Kaynak Referans', max_length=200, blank=True,
                                        help_text='Rezervasyon no, Tur adı vb.')
    
    # Ödeme Bilgileri
    payment_method = models.CharField('Ödeme Yöntemi', max_length=20, choices=PAYMENT_METHOD_CHOICES, default='cash')
    payment_date = models.DateTimeField('Ödeme Tarihi', default=timezone.now)
    due_date = models.DateField('Vade Tarihi', null=True, blank=True,
                                help_text='Vadeli ödemeler için')
    
    # Açıklama
    description = models.TextField('Açıklama', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    is_reconciled = models.BooleanField('Mutabakat Yapıldı mı?', default=False)
    reconciled_at = models.DateTimeField('Mutabakat Tarihi', null=True, blank=True)
    reconciled_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reconciled_transactions',
        verbose_name='Mutabakat Yapan'
    )
    
    # İşlem Yapan
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_cash_transactions',
        verbose_name='İşlem Yapan'
    )
    approved_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='approved_cash_transactions',
        verbose_name='Onaylayan'
    )
    approved_at = models.DateTimeField('Onay Tarihi', null=True, blank=True)
    
    # Ek Bilgiler
    attachments = models.JSONField('Ekler', default=list, blank=True,
                                   help_text='Dosya URL\'leri listesi')
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kasa İşlemi'
        verbose_name_plural = 'Kasa İşlemleri'
        ordering = ['-payment_date', '-created_at']
        indexes = [
            models.Index(fields=['account', '-payment_date']),
            models.Index(fields=['hotel', '-payment_date']),
            models.Index(fields=['source_module', 'source_id']),
            models.Index(fields=['transaction_type', 'status']),
            models.Index(fields=['transaction_number']),
        ]
    
    def __str__(self):
        return f"{self.transaction_number} - {self.get_transaction_type_display()} - {self.amount} {self.currency}"
    
    def save(self, *args, **kwargs):
        if not self.transaction_number:
            # Otomatik işlem numarası oluştur
            from datetime import datetime
            prefix = 'KAS'
            date_str = datetime.now().strftime('%Y%m%d')
            last_trans = CashTransaction.objects.filter(
                transaction_number__startswith=f'{prefix}-{date_str}'
            ).order_by('-transaction_number').first()
            
            if last_trans:
                last_num = int(last_trans.transaction_number.split('-')[-1])
                new_num = last_num + 1
            else:
                new_num = 1
            
            self.transaction_number = f'{prefix}-{date_str}-{new_num:04d}'
        
        super().save(*args, **kwargs)
        
        # Bakiye güncelle
        if self.status == 'completed' and not self.is_deleted:
            self.account.calculate_balance()
            if self.to_account:
                self.to_account.calculate_balance()
    
    def complete(self, user=None):
        """İşlemi tamamla"""
        if self.status == 'pending':
            self.status = 'completed'
            if user:
                self.approved_by = user
                self.approved_at = timezone.now()
            self.save()
            self.account.calculate_balance()
            if self.to_account:
                self.to_account.calculate_balance()
    
    def cancel(self, reason=''):
        """İşlemi iptal et"""
        if self.status in ['pending', 'completed']:
            self.status = 'cancelled'
            if reason:
                self.notes = f"{self.notes}\nİptal Nedeni: {reason}".strip()
            self.save()
            if self.status == 'completed':
                self.account.calculate_balance()
                if self.to_account:
                    self.to_account.calculate_balance()
    
    def reverse(self, reason=''):
        """İşlemi geri al"""
        if self.status == 'completed':
            self.status = 'reversed'
            if reason:
                self.notes = f"{self.notes}\nGeri Alma Nedeni: {reason}".strip()
            self.save()
            self.account.calculate_balance()
            if self.to_account:
                self.to_account.calculate_balance()


# ==================== NAKİT AKIŞI ====================

class CashFlow(TimeStampedModel):
    """
    Nakit Akışı Takibi
    Günlük, haftalık, aylık nakit akışı özetleri
    Otel bazlı veya genel nakit akışı olabilir
    """
    PERIOD_TYPE_CHOICES = [
        ('daily', 'Günlük'),
        ('weekly', 'Haftalık'),
        ('monthly', 'Aylık'),
        ('yearly', 'Yıllık'),
    ]
    
    # Otel Bağlantısı (null ise genel nakit akışı)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='cash_flows',
        null=True,
        blank=True,
        verbose_name='Otel',
        help_text='Boş bırakılırsa genel nakit akışı olur'
    )
    
    account = models.ForeignKey(
        CashAccount,
        on_delete=models.CASCADE,
        related_name='cash_flows',
        verbose_name='Kasa Hesabı'
    )
    period_type = models.CharField('Dönem Tipi', max_length=20, choices=PERIOD_TYPE_CHOICES)
    period_start = models.DateField('Dönem Başlangıcı')
    period_end = models.DateField('Dönem Bitişi')
    
    # Başlangıç ve Bitiş Bakiyeleri
    opening_balance = models.DecimalField('Açılış Bakiyesi', max_digits=15, decimal_places=2, default=0)
    closing_balance = models.DecimalField('Kapanış Bakiyesi', max_digits=15, decimal_places=2, default=0)
    
    # Gelir ve Gider Özetleri
    total_income = models.DecimalField('Toplam Gelir', max_digits=15, decimal_places=2, default=0)
    total_expense = models.DecimalField('Toplam Gider', max_digits=15, decimal_places=2, default=0)
    net_flow = models.DecimalField('Net Akış', max_digits=15, decimal_places=2, default=0,
                                   help_text='Gelir - Gider')
    
    # İşlem Sayıları
    income_count = models.IntegerField('Gelir İşlem Sayısı', default=0)
    expense_count = models.IntegerField('Gider İşlem Sayısı', default=0)
    
    # Hesaplanma Tarihi
    calculated_at = models.DateTimeField('Hesaplanma Tarihi', auto_now=True)
    
    class Meta:
        verbose_name = 'Nakit Akışı'
        verbose_name_plural = 'Nakit Akışları'
        unique_together = ('account', 'hotel', 'period_type', 'period_start', 'period_end')
        ordering = ['-period_start', '-period_end']
        indexes = [
            models.Index(fields=['account', 'period_type', '-period_start']),
            models.Index(fields=['hotel', 'period_type', '-period_start']),
        ]
    
    def __str__(self):
        return f"{self.account.name} - {self.get_period_type_display()} ({self.period_start} - {self.period_end})"
    
    def calculate(self):
        """Nakit akışını hesapla"""
        transactions = CashTransaction.objects.filter(
            account=self.account,
            payment_date__date__gte=self.period_start,
            payment_date__date__lte=self.period_end,
            status='completed',
            is_deleted=False
        )
        
        # Başlangıç bakiyesi (önceki dönem kapanış bakiyesi)
        prev_flow = CashFlow.objects.filter(
            account=self.account,
            period_end__lt=self.period_start
        ).order_by('-period_end').first()
        
        if prev_flow:
            self.opening_balance = prev_flow.closing_balance
        else:
            self.opening_balance = self.account.initial_balance
        
        # Gelir ve gider hesapla
        income_trans = transactions.filter(transaction_type='income')
        expense_trans = transactions.filter(transaction_type='expense')
        
        self.total_income = income_trans.aggregate(total=models.Sum('amount'))['total'] or Decimal('0')
        self.total_expense = expense_trans.aggregate(total=models.Sum('amount'))['total'] or Decimal('0')
        self.net_flow = self.total_income - self.total_expense
        self.closing_balance = self.opening_balance + self.net_flow
        
        self.income_count = income_trans.count()
        self.expense_count = expense_trans.count()
        
        self.save()
        return self

