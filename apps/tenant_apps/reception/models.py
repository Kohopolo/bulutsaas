"""
Resepsiyon (Ön Büro) Modelleri
Rezervasyon odaklı profesyonel otel resepsiyon yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from decimal import Decimal
from datetime import date, timedelta
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== REZERVASYON DURUMLARI ====================

class ReservationStatus(models.TextChoices):
    """Rezervasyon Durumları"""
    PENDING = 'pending', 'Beklemede'
    CONFIRMED = 'confirmed', 'Onaylandı'
    CHECKED_IN = 'checked_in', 'Check-In Yapıldı'
    CHECKED_OUT = 'checked_out', 'Check-Out Yapıldı'
    CANCELLED = 'cancelled', 'İptal Edildi'
    NO_SHOW = 'no_show', 'Gelmedi'


class ReservationSource(models.TextChoices):
    """Rezervasyon Kaynakları"""
    DIRECT = 'direct', 'Direkt'
    ONLINE = 'online', 'Online'
    PHONE = 'phone', 'Telefon'
    EMAIL = 'email', 'E-posta'
    WALK_IN = 'walk_in', 'Walk-In'
    AGENCY = 'agency', 'Acente'
    CORPORATE = 'corporate', 'Kurumsal'


# ==================== REZERVASYON MODELİ ====================

class Reservation(TimeStampedModel, SoftDeleteModel):
    """
    Rezervasyon Modeli
    Otel rezervasyonlarını yönetir
    """
    # Temel Bilgiler
    reservation_code = models.CharField('Rezervasyon Kodu', max_length=50, unique=True, db_index=True)
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Otel'
    )
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Oda Tipi'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservations',
        verbose_name='Oda Numarası'
    )
    
    # Müşteri Bilgileri
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Müşteri'
    )
    
    # Tarih Bilgileri
    check_in_date = models.DateField('Check-in Tarihi', db_index=True)
    check_out_date = models.DateField('Check-out Tarihi', db_index=True)
    check_in_time = models.TimeField('Check-in Saati', default='14:00')
    check_out_time = models.TimeField('Check-out Saati', default='12:00')
    
    # Misafir Bilgileri
    adult_count = models.IntegerField('Yetişkin Sayısı', default=1, validators=[MinValueValidator(1)])
    child_count = models.IntegerField('Çocuk Sayısı', default=0, validators=[MinValueValidator(0)])
    child_ages = models.JSONField('Çocuk Yaşları', default=list, blank=True,
                                  help_text='Örn: [5, 8]')
    
    # Rezervasyon Bilgileri
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=ReservationStatus.choices,
        default=ReservationStatus.PENDING,
        db_index=True
    )
    source = models.CharField(
        'Kaynak',
        max_length=20,
        choices=ReservationSource.choices,
        default=ReservationSource.DIRECT,
        db_index=True
    )
    
    # Fiyatlandırma
    room_rate = models.DecimalField('Oda Fiyatı', max_digits=10, decimal_places=2, default=0)
    total_nights = models.IntegerField('Toplam Gece', default=1)
    total_amount = models.DecimalField('Toplam Tutar', max_digits=12, decimal_places=2, default=0)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, default=0)
    tax_amount = models.DecimalField('Vergi Tutarı', max_digits=10, decimal_places=2, default=0)
    total_paid = models.DecimalField('Ödenen Tutar', max_digits=12, decimal_places=2, default=0)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Özel İstekler ve Notlar
    special_requests = models.TextField('Özel İstekler', blank=True)
    internal_notes = models.TextField('İç Notlar', blank=True, help_text='Personel için notlar')
    
    # Durum Bilgileri
    is_checked_in = models.BooleanField('Check-In Yapıldı mı?', default=False)
    is_checked_out = models.BooleanField('Check-Out Yapıldı mı?', default=False)
    checked_in_at = models.DateTimeField('Check-In Tarihi', null=True, blank=True)
    checked_out_at = models.DateTimeField('Check-Out Tarihi', null=True, blank=True)
    
    # İptal Bilgileri
    cancelled_at = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon'
        verbose_name_plural = 'Rezervasyonlar'
        ordering = ['-check_in_date', '-created_at']
        indexes = [
            models.Index(fields=['hotel', 'status', 'check_in_date']),
            models.Index(fields=['customer', 'status']),
            models.Index(fields=['reservation_code']),
        ]
    
    def __str__(self):
        return f"{self.reservation_code} - {self.customer.first_name} {self.customer.last_name}"
    
    def save(self, *args, **kwargs):
        """Rezervasyon kaydedilirken otomatik hesaplamalar yap"""
        # Toplam gece sayısını hesapla
        if self.check_in_date and self.check_out_date:
            self.total_nights = (self.check_out_date - self.check_in_date).days
            if self.total_nights < 1:
                self.total_nights = 1
        
        # Toplam tutarı hesapla
        if self.room_rate and self.total_nights:
            self.total_amount = Decimal(str(self.room_rate)) * Decimal(str(self.total_nights))
            if self.discount_amount:
                self.total_amount -= Decimal(str(self.discount_amount))
            if self.tax_amount:
                self.total_amount += Decimal(str(self.tax_amount))
        
        super().save(*args, **kwargs)
    
    def get_remaining_amount(self):
        """Kalan ödeme tutarını hesapla"""
        return self.total_amount - self.total_paid
    
    def is_paid(self):
        """Rezervasyon tamamen ödendi mi?"""
        return self.total_paid >= self.total_amount
    
    def can_check_in(self):
        """Check-in yapılabilir mi?"""
        return (self.status == ReservationStatus.CONFIRMED and 
                not self.is_checked_in and
                self.check_in_date <= date.today())
    
    def can_check_out(self):
        """Check-out yapılabilir mi?"""
        return (self.is_checked_in and 
                not self.is_checked_out and
                self.check_out_date <= date.today())

