"""
Tur Modülü - Müşteri CRM ve Sadakat Sistemi Modelleri
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


class TourCustomer(TimeStampedModel, SoftDeleteModel):
    """Tur Müşteri Profili - CRM ve Sadakat Sistemi"""
    
    # Temel Bilgiler
    customer_code = models.CharField('Müşteri Kodu', max_length=50, unique=True, db_index=True)
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    email = models.EmailField('E-posta', unique=True, db_index=True)
    phone = models.CharField('Telefon', max_length=20)
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    
    # İletişim Bilgileri
    address = models.TextField('Adres', blank=True)
    city = models.CharField('Şehir', max_length=100, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    postal_code = models.CharField('Posta Kodu', max_length=10, blank=True)
    
    # Doğum Tarihi ve Özel Günler
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    special_dates = models.JSONField('Özel Günler', default=list, blank=True,
                                     help_text='Örn: [{"date": "2024-12-25", "name": "Evlilik Yıldönümü"}]')
    
    # Sadakat Sistemi
    loyalty_points = models.IntegerField('Sadakat Puanı', default=0, validators=[MinValueValidator(0)])
    total_reservations = models.IntegerField('Toplam Rezervasyon', default=0)
    total_spent = models.DecimalField('Toplam Harcama', max_digits=12, decimal_places=2, default=0)
    
    # VIP Statüsü
    VIP_LEVEL_CHOICES = [
        ('regular', 'Normal'),
        ('silver', 'Gümüş (5+ rezervasyon)'),
        ('gold', 'Altın (10+ rezervasyon)'),
        ('platinum', 'Platin (20+ rezervasyon)'),
        ('diamond', 'Elmas (50+ rezervasyon)'),
    ]
    vip_level = models.CharField('VIP Seviyesi', max_length=20, choices=VIP_LEVEL_CHOICES, default='regular')
    
    # Tercihler
    preferred_regions = models.ManyToManyField('TourRegion', blank=True, related_name='preferred_customers', verbose_name='Tercih Edilen Bölgeler')
    preferred_tour_types = models.ManyToManyField('TourType', blank=True, related_name='preferred_customers', verbose_name='Tercih Edilen Tur Türleri')
    preferred_travel_months = models.JSONField('Tercih Edilen Seyahat Ayları', default=list, blank=True,
                                              help_text='Örn: [6, 7, 8] (Haziran, Temmuz, Ağustos)')
    
    # Notlar ve İstekler
    notes = models.TextField('Notlar', blank=True, help_text='Müşteri hakkında özel notlar')
    special_requests = models.TextField('Özel İstekler', blank=True, help_text='Müşterinin özel istekleri')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_vip = models.BooleanField('VIP Müşteri mi?', default=False)
    last_reservation_date = models.DateField('Son Rezervasyon Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Tur Müşterisi'
        verbose_name_plural = 'Tur Müşterileri'
        ordering = ['-total_spent', '-created_at']
        indexes = [
            models.Index(fields=['email']),
            models.Index(fields=['customer_code']),
            models.Index(fields=['vip_level', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.customer_code})"
    
    def save(self, *args, **kwargs):
        if not self.customer_code:
            import random
            import string
            self.customer_code = f"TC{''.join(random.choices(string.ascii_uppercase + string.digits, k=8))}"
        
        # VIP seviyesini güncelle
        if self.total_reservations >= 50:
            self.vip_level = 'diamond'
            self.is_vip = True
        elif self.total_reservations >= 20:
            self.vip_level = 'platinum'
            self.is_vip = True
        elif self.total_reservations >= 10:
            self.vip_level = 'gold'
            self.is_vip = True
        elif self.total_reservations >= 5:
            self.vip_level = 'silver'
            self.is_vip = True
        else:
            self.vip_level = 'regular'
            self.is_vip = False
        
        super().save(*args, **kwargs)
    
    def add_loyalty_points(self, points, reason=''):
        """Sadakat puanı ekle"""
        self.loyalty_points += points
        self.save()
        
        # Puan geçmişi kaydet
        TourLoyaltyHistory.objects.create(
            customer=self,
            points=points,
            reason=reason or 'Rezervasyon',
        )
    
    def use_loyalty_points(self, points):
        """Sadakat puanı kullan"""
        if self.loyalty_points >= points:
            self.loyalty_points -= points
            self.save()
            
            # Puan geçmişi kaydet
            TourLoyaltyHistory.objects.create(
                customer=self,
                points=-points,
                reason='Puan kullanımı',
            )
            return True
        return False
    
    def get_loyalty_discount(self):
        """Sadakat puanına göre indirim hesapla (100 puan = %1 indirim, max %10)"""
        discount_percentage = min(10, self.loyalty_points // 100)
        return discount_percentage
    
    def update_statistics(self):
        """Müşteri istatistiklerini güncelle"""
        from .models import TourReservation
        
        reservations = TourReservation.objects.filter(
            customer_email=self.email,
            status__in=['confirmed', 'completed']
        )
        
        self.total_reservations = reservations.count()
        self.total_spent = reservations.aggregate(
            total=models.Sum('total_amount')
        )['total'] or Decimal('0')
        
        last_reservation = reservations.order_by('-created_at').first()
        if last_reservation:
            self.last_reservation_date = last_reservation.created_at.date()
        
        self.save()


class TourLoyaltyHistory(TimeStampedModel):
    """Sadakat Puanı Geçmişi"""
    customer = models.ForeignKey(TourCustomer, on_delete=models.CASCADE, related_name='loyalty_history', verbose_name='Müşteri')
    points = models.IntegerField('Puan', help_text='Pozitif = Ekleme, Negatif = Kullanım')
    reason = models.CharField('Sebep', max_length=200, blank=True)
    reservation = models.ForeignKey('TourReservation', on_delete=models.SET_NULL, null=True, blank=True,
                                    related_name='loyalty_points', verbose_name='Rezervasyon')
    
    class Meta:
        verbose_name = 'Sadakat Puanı Geçmişi'
        verbose_name_plural = 'Sadakat Puanı Geçmişleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer.customer_code} - {self.points} puan ({self.reason})"


class TourCustomerNote(TimeStampedModel):
    """Müşteri Notları"""
    customer = models.ForeignKey(TourCustomer, on_delete=models.CASCADE, related_name='notes_history', verbose_name='Müşteri')
    note = models.TextField('Not')
    created_by = models.ForeignKey('tenant_core.TenantUser', on_delete=models.SET_NULL, null=True,
                                   related_name='customer_notes', verbose_name='Oluşturan')
    is_important = models.BooleanField('Önemli mi?', default=False)
    
    class Meta:
        verbose_name = 'Müşteri Notu'
        verbose_name_plural = 'Müşteri Notları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer.customer_code} - {self.note[:50]}"

