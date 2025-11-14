"""
Abonelik yönetimi modelleri
Otomatik takip ve ödeme sistemi
"""
from django.db import models
from django.utils import timezone
from datetime import timedelta
from apps.core.models import TimeStampedModel


class Subscription(TimeStampedModel):
    """
    Abonelik modeli
    Tenant'ların paket abonelikleri
    """
    tenant = models.ForeignKey(
        'tenants.Tenant',
        on_delete=models.CASCADE,
        related_name='subscriptions',
        verbose_name='Tenant'
    )
    package = models.ForeignKey(
        'packages.Package',
        on_delete=models.PROTECT,
        related_name='subscriptions',
        verbose_name='Paket'
    )
    
    # Abonelik Dönemi
    PERIOD_CHOICES = [
        ('monthly', 'Aylık'),
        ('yearly', 'Yıllık'),
        ('trial', 'Deneme'),
    ]
    period = models.CharField('Dönem', max_length=20, choices=PERIOD_CHOICES, default='monthly')
    
    # Tarihler
    start_date = models.DateField('Başlangıç Tarihi')
    end_date = models.DateField('Bitiş Tarihi')
    next_billing_date = models.DateField('Sonraki Fatura Tarihi', null=True, blank=True)
    
    # Fiyat (Abonelik anındaki fiyat)
    amount = models.DecimalField('Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    STATUS_CHOICES = [
        ('active', 'Aktif'),
        ('expired', 'Süresi Dolmuş'),
        ('cancelled', 'İptal Edilmiş'),
        ('pending', 'Beklemede'),
        ('trial', 'Deneme'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    # Otomatik Yenileme
    auto_renew = models.BooleanField('Otomatik Yenileme', default=True)
    
    # Ödeme Bilgileri
    stripe_subscription_id = models.CharField('Stripe Subscription ID', max_length=100, blank=True)
    stripe_customer_id = models.CharField('Stripe Customer ID', max_length=100, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)

    class Meta:
        verbose_name = 'Abonelik'
        verbose_name_plural = 'Abonelikler'
        ordering = ['-created_at']

    def __str__(self):
        return f"{self.tenant.name} - {self.package.name} ({self.get_status_display()})"

    def is_active(self):
        """Abonelik aktif mi?"""
        return self.status == 'active' and self.end_date >= timezone.now().date()

    def days_remaining(self):
        """Kalan gün sayısı"""
        if self.end_date:
            delta = self.end_date - timezone.now().date()
            return max(0, delta.days)
        return 0

    def check_expiry(self):
        """Abonelik süresini kontrol et ve gerekirse pasif yap"""
        if self.end_date < timezone.now().date() and self.status == 'active':
            self.status = 'expired'
            self.tenant.is_active = False
            self.tenant.save()
            self.save()
            return True
        return False

    def renew(self, period=None):
        """Aboneliği yenile - Bitiş tarihinden sonra ekle"""
        if period:
            self.period = period
        
        # start_date'i değiştirme, sadece end_date'i uzat
        # Eski paketin bitiş tarihinden itibaren uzat (bitiş tarihi geçmişse bile bitiş tarihinden itibaren)
        # Eğer bitiş tarihi geçmişse bugünden başlat, değilse bitiş tarihinden sonra ekle
        base_date = max(self.end_date, timezone.now().date())
        
        if self.period == 'monthly':
            # Bitiş tarihinden itibaren 1 ay ekle
            self.end_date = base_date + timedelta(days=30)
            self.amount = self.package.price_monthly
        elif self.period == 'yearly':
            # Bitiş tarihinden itibaren 1 yıl ekle
            self.end_date = base_date + timedelta(days=365)
            self.amount = self.package.price_yearly or (self.package.price_monthly * 12)
        
        self.next_billing_date = self.end_date
        self.status = 'active'
        self.save()


class Payment(TimeStampedModel):
    """
    Ödeme modeli
    Abonelik ödemeleri
    """
    subscription = models.ForeignKey(
        Subscription,
        on_delete=models.CASCADE,
        related_name='payments',
        verbose_name='Abonelik'
    )
    
    # Ödeme Bilgileri
    amount = models.DecimalField('Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
        ('refunded', 'İade Edildi'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    # Ödeme Yöntemi
    PAYMENT_METHOD_CHOICES = [
        ('credit_card', 'Kredi Kartı'),
        ('bank_transfer', 'Banka Havalesi'),
        ('stripe', 'Stripe'),
        ('paypal', 'PayPal'),
        ('other', 'Diğer'),
    ]
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, choices=PAYMENT_METHOD_CHOICES)
    
    # Stripe
    stripe_payment_intent_id = models.CharField('Stripe Payment Intent ID', max_length=100, blank=True)
    stripe_charge_id = models.CharField('Stripe Charge ID', max_length=100, blank=True)
    
    # Fatura
    invoice_number = models.CharField('Fatura No', max_length=50, blank=True)
    invoice_url = models.URLField('Fatura URL', blank=True)
    
    # Tarih
    payment_date = models.DateTimeField('Ödeme Tarihi', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)

    class Meta:
        verbose_name = 'Ödeme'
        verbose_name_plural = 'Ödemeler'
        ordering = ['-created_at']

    def __str__(self):
        return f"{self.subscription.tenant.name} - {self.amount} {self.currency} ({self.get_status_display()})"



