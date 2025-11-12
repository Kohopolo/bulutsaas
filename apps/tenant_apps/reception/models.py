"""
Resepsiyon (Ön Büro) Modelleri
Profesyonel otel resepsiyon yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from typing import Optional, Dict, List
from datetime import date, datetime
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== REZERVASYON MODELLERİ ====================

class Reservation(TimeStampedModel, SoftDeleteModel):
    """
    Otel Rezervasyon Modeli
    Tüm rezervasyon kaynaklarından gelen rezervasyonlar bu modelde saklanır
    """
    # Rezervasyon Kodu
    reservation_code = models.CharField(
        'Rezervasyon Kodu',
        max_length=50,
        unique=True,
        db_index=True,
        help_text='Otomatik oluşturulur (örn: RES-2025-001)'
    )
    
    # Otel ve Oda
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.PROTECT,
        related_name='reservations',
        verbose_name='Otel'
    )
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.PROTECT,
        related_name='reservations',
        verbose_name='Oda Tipi'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservations',
        verbose_name='Oda Numarası',
        help_text='Check-in sırasında atanır'
    )
    
    # Müşteri (Merkezi CRM entegrasyonu)
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='hotel_reservations',
        verbose_name='Müşteri Profili'
    )
    
    # Müşteri Bilgileri (Customer bulunamazsa manuel giriş için)
    customer_first_name = models.CharField('Müşteri Adı', max_length=100)
    customer_last_name = models.CharField('Müşteri Soyadı', max_length=100)
    customer_email = models.EmailField('E-posta', db_index=True)
    customer_phone = models.CharField('Telefon', max_length=20, db_index=True)
    customer_tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True, db_index=True)
    customer_address = models.TextField('Adres', blank=True)
    
    # Çocuk Bilgileri
    child_count = models.IntegerField('Çocuk Sayısı', default=0, validators=[MinValueValidator(0)])
    child_ages = models.JSONField(
        'Çocuk Yaşları',
        default=list,
        blank=True,
        help_text='[5, 8, 12] - Her çocuk için yaş bilgisi (0\'dan büyük olmalı)'
    )
    
    # Tarih Bilgileri
    check_in_date = models.DateField('Giriş Tarihi', db_index=True)
    check_out_date = models.DateField('Çıkış Tarihi', db_index=True)
    check_in_time = models.TimeField('Giriş Saati', null=True, blank=True)
    check_out_time = models.TimeField('Çıkış Saati', null=True, blank=True)
    nights = models.IntegerField('Gece Sayısı', validators=[MinValueValidator(1)])
    
    # Kişi Sayıları
    adult_count = models.IntegerField('Yetişkin Sayısı', validators=[MinValueValidator(1)])
    total_people = models.IntegerField('Toplam Kişi', validators=[MinValueValidator(1)])
    
    # Pansiyon Tipi
    board_type = models.ForeignKey(
        'hotels.BoardType',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservations',
        verbose_name='Pansiyon Tipi'
    )
    
    # Fiyatlandırma
    base_price = models.DecimalField('Temel Fiyat', max_digits=10, decimal_places=2)
    adult_price = models.DecimalField('Yetişkin Fiyatı', max_digits=10, decimal_places=2, default=0)
    child_price = models.DecimalField('Çocuk Fiyatı', max_digits=10, decimal_places=2, default=0)
    extra_services_total = models.DecimalField('Ekstra Hizmetler Toplam', max_digits=10, decimal_places=2, default=0)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, default=0)
    reception_discount_rate = models.DecimalField(
        'Ön Büro İndirim Oranı (%)',
        max_digits=5,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0')), MaxValueValidator(Decimal('100'))],
        help_text='Resepsiyon personeli tarafından uygulanan ekstra indirim oranı'
    )
    reception_discount_amount = models.DecimalField(
        'Ön Büro İndirim Tutarı',
        max_digits=10,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0'))],
        help_text='Resepsiyon personeli tarafından uygulanan ekstra indirim tutarı'
    )
    total_amount = models.DecimalField('Toplam Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Kaynak Bilgisi
    SOURCE_CHOICES = [
        ('reception', 'Resepsiyon'),
        ('sales', 'Satış'),
        ('call_center', 'Call Center'),
        ('agency', 'Acente'),
        ('web', 'Web'),
        ('channel', 'Kanal'),
    ]
    source = models.CharField(
        'Kaynak',
        max_length=20,
        choices=SOURCE_CHOICES,
        default='reception',
        db_index=True
    )
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='created_reservations',
        verbose_name='Oluşturan Kullanıcı'
    )
    
    # Acente Bilgisi (varsa)
    agency = models.ForeignKey(
        'tours.TourAgency',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='hotel_reservations',
        verbose_name='Acente',
        db_index=True
    )
    # agency_id otomatik olarak ForeignKey'den oluşturulur (agency_id)
    
    # Kanal Bilgisi (varsa)
    channel_id = models.IntegerField('Kanal ID', null=True, blank=True, db_index=True)
    channel_name = models.CharField('Kanal Adı', max_length=100, blank=True)
    
    # Web Rezervasyonu
    is_web_booking = models.BooleanField('Web Rezervasyonu mu?', default=False)
    web_booking_reference = models.CharField('Web Rezervasyon Referansı', max_length=100, blank=True)
    
    # Comp Rezervasyon
    is_complimentary = models.BooleanField('Comp Rezervasyon mu?', default=False)
    complimentary_reason = models.TextField('Comp Nedeni', blank=True)
    complimentary_approved_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='approved_comps',
        verbose_name='Comp Onaylayan'
    )
    complimentary_approved_at = models.DateTimeField('Comp Onay Tarihi', null=True, blank=True)
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('confirmed', 'Onaylandı'),
        ('checked_in', 'Check-in Yapıldı'),
        ('checked_out', 'Check-out Yapıldı'),
        ('cancelled', 'İptal Edildi'),
        ('no_show', 'Gelmedi (No-Show)'),
    ]
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=STATUS_CHOICES,
        default='pending',
        db_index=True
    )
    
    # Check-in/out Bilgileri
    checked_in_at = models.DateTimeField('Check-in Tarihi', null=True, blank=True)
    checked_out_at = models.DateTimeField('Check-out Tarihi', null=True, blank=True)
    checked_in_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='checked_in_reservations',
        verbose_name='Check-in Yapan'
    )
    checked_out_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='checked_out_reservations',
        verbose_name='Check-out Yapan'
    )
    
    # Erken/Geç Çıkış
    is_early_checkout = models.BooleanField('Erken Çıkış mı?', default=False)
    early_checkout_reason = models.TextField('Erken Çıkış Nedeni', blank=True)
    early_checkout_fee = models.DecimalField('Erken Çıkış Ücreti', max_digits=10, decimal_places=2, null=True, blank=True)
    is_late_checkout = models.BooleanField('Geç Çıkış mı?', default=False)
    late_checkout_reason = models.TextField('Geç Çıkış Nedeni', blank=True)
    late_checkout_fee = models.DecimalField('Geç Çıkış Ücreti', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Ödeme Garantisi
    is_guaranteed = models.BooleanField('Ödeme Garantili mi?', default=False)
    guarantee_type = models.CharField(
        'Garanti Tipi',
        max_length=20,
        choices=[
            ('credit_card', 'Kredi Kartı'),
            ('deposit', 'Depozito'),
            ('voucher', 'Voucher'),
            ('other', 'Diğer'),
        ],
        blank=True
    )
    advance_payment = models.DecimalField('Ön Ödeme', max_digits=10, decimal_places=2, default=0)
    total_paid = models.DecimalField('Toplam Ödenen', max_digits=10, decimal_places=2, default=0, help_text='Rezervasyon için ödenen toplam tutar')
    
    # Özel İstekler
    special_requests = models.JSONField(
        'Özel İstekler',
        default=dict,
        blank=True,
        help_text='{"extra_bed": true, "baby_cot": true, "accompaniateur": false}'
    )
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    internal_notes = models.TextField('İç Notlar', blank=True, help_text='Sadece personel görebilir')
    
    # Arşivleme
    archived_at = models.DateTimeField('Arşivlenme Tarihi', null=True, blank=True)
    archived_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='archived_reservations',
        verbose_name='Arşivleyen'
    )
    archive_reason = models.TextField('Arşivleme Nedeni', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon'
        verbose_name_plural = 'Rezervasyonlar'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['hotel', 'status', 'is_deleted']),
            models.Index(fields=['check_in_date', 'check_out_date']),
            models.Index(fields=['reservation_code']),
            models.Index(fields=['customer_email', 'customer_phone']),
            models.Index(fields=['source', 'agency_id', 'channel_id']),
            models.Index(fields=['is_complimentary']),
        ]
    
    def __str__(self):
        return f"{self.reservation_code} - {self.customer_first_name} {self.customer_last_name}"
    
    def save(self, *args, **kwargs):
        """Rezervasyon kodu otomatik oluştur"""
        if not self.reservation_code:
            from django.utils import timezone
            year = timezone.now().year
            last_reservation = Reservation.objects.filter(
                reservation_code__startswith=f'RES-{year}-'
            ).order_by('-reservation_code').first()
            
            if last_reservation:
                last_number = int(last_reservation.reservation_code.split('-')[-1])
                new_number = last_number + 1
            else:
                new_number = 1
            
            self.reservation_code = f'RES-{year}-{new_number:04d}'
        
        # Gece sayısını hesapla
        if self.check_in_date and self.check_out_date:
            self.nights = (self.check_out_date - self.check_in_date).days
        
        # Toplam kişi sayısını hesapla
        self.total_people = self.adult_count + self.child_count
        
        super().save(*args, **kwargs)
    
    def get_total_paid(self):
        """Toplam ödenen tutarı getir"""
        # Finance modülü entegrasyonu için hazır
        # Şimdilik advance_payment'ı döndür
        try:
            from apps.tenant_apps.finance.models import CashTransaction
            total = CashTransaction.objects.filter(
                source_module='reception',
                source_id=self.id,
                transaction_type='income',
                is_deleted=False
            ).aggregate(total=models.Sum('amount'))['total'] or Decimal('0')
            return total
        except:
            # Finance modülü yoksa advance_payment'ı döndür
            return self.advance_payment or Decimal('0')
    
    def get_total_refunded(self):
        """Toplam iade edilen tutarı getir"""
        # Refunds modülü entegrasyonu için hazır
        # Şimdilik 0 döndür
        try:
            from apps.tenant_apps.refunds.models import RefundTransaction
            total = RefundTransaction.objects.filter(
                source_module='reception',
                source_id=self.id,
                status='completed',
                is_deleted=False
            ).aggregate(total=models.Sum('refund_amount'))['total'] or Decimal('0')
            return total
        except:
            # Refunds modülü yoksa 0 döndür
            return Decimal('0')
    
    def get_balance(self):
        """Bakiye hesapla"""
        total_paid = self.get_total_paid()
        total_refunded = self.get_total_refunded()
        return self.total_amount - total_paid + total_refunded


class ReservationUpdate(TimeStampedModel):
    """
    Rezervasyon Güncelleme Kayıtları (Audit Log)
    Rezervasyonda yapılan tüm değişikliklerin kayıt altına alınması
    """
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='updates',
        verbose_name='Rezervasyon'
    )
    updated_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reservation_updates',
        verbose_name='Güncelleyen'
    )
    
    UPDATE_TYPE_CHOICES = [
        ('created', 'Oluşturuldu'),
        ('updated', 'Güncellendi'),
        ('cancelled', 'İptal Edildi'),
        ('checked_in', 'Check-in Yapıldı'),
        ('checked_out', 'Check-out Yapıldı'),
        ('room_changed', 'Oda Değiştirildi'),
        ('payment_added', 'Ödeme Eklendi'),
        ('refund_added', 'İade Eklendi'),
        ('archived', 'Arşivlendi'),
        ('restored', 'Geri Getirildi'),
    ]
    update_type = models.CharField(
        'Güncelleme Türü',
        max_length=20,
        choices=UPDATE_TYPE_CHOICES
    )
    
    field_name = models.CharField('Alan Adı', max_length=100, blank=True)
    old_value = models.TextField('Eski Değer', blank=True)
    new_value = models.TextField('Yeni Değer', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Güncelleme'
        verbose_name_plural = 'Rezervasyon Güncellemeleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['reservation', 'update_type']),
        ]
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.get_update_type_display()}"


class RoomChange(TimeStampedModel):
    """
    Oda Değişikliği Kayıtları
    """
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='room_changes',
        verbose_name='Rezervasyon'
    )
    old_room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.PROTECT,
        related_name='room_changes_from',
        verbose_name='Eski Oda'
    )
    new_room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.PROTECT,
        related_name='room_changes_to',
        verbose_name='Yeni Oda'
    )
    old_room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='room_changes_from',
        verbose_name='Eski Oda Numarası'
    )
    new_room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='room_changes_to',
        verbose_name='Yeni Oda Numarası'
    )
    changed_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='room_changes',
        verbose_name='Değiştiren'
    )
    reason = models.TextField('Neden', blank=True)
    price_difference = models.DecimalField(
        'Fiyat Farkı',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True,
        help_text='Yeni oda fiyatı - Eski oda fiyatı'
    )
    
    class Meta:
        verbose_name = 'Oda Değişikliği'
        verbose_name_plural = 'Oda Değişiklikleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.old_room.name} → {self.new_room.name}"


# ==================== CHECK-IN/OUT MODELLERİ ====================

class CheckIn(TimeStampedModel):
    """
    Check-In Kayıtları
    """
    reservation = models.OneToOneField(
        Reservation,
        on_delete=models.CASCADE,
        related_name='check_in_record',
        verbose_name='Rezervasyon'
    )
    checked_in_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='check_ins',
        verbose_name='Check-in Yapan'
    )
    check_in_datetime = models.DateTimeField('Check-in Tarihi', default=timezone.now)
    is_early_checkin = models.BooleanField('Erken Check-in mi?', default=False)
    early_checkin_reason = models.TextField('Erken Check-in Nedeni', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Check-in'
        verbose_name_plural = 'Check-in\'ler'
        ordering = ['-check_in_datetime']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.check_in_datetime}"


class CheckOut(TimeStampedModel):
    """
    Check-Out Kayıtları
    """
    reservation = models.OneToOneField(
        Reservation,
        on_delete=models.CASCADE,
        related_name='check_out_record',
        verbose_name='Rezervasyon'
    )
    checked_out_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='check_outs',
        verbose_name='Check-out Yapan'
    )
    check_out_datetime = models.DateTimeField('Check-out Tarihi', default=timezone.now)
    is_early_checkout = models.BooleanField('Erken Check-out mu?', default=False)
    early_checkout_reason = models.TextField('Erken Check-out Nedeni', blank=True)
    early_checkout_fee = models.DecimalField('Erken Check-out Ücreti', max_digits=10, decimal_places=2, null=True, blank=True)
    is_late_checkout = models.BooleanField('Geç Check-out mu?', default=False)
    late_checkout_reason = models.TextField('Geç Check-out Nedeni', blank=True)
    late_checkout_fee = models.DecimalField('Geç Check-out Ücreti', max_digits=10, decimal_places=2, null=True, blank=True)
    total_paid = models.DecimalField('Toplam Ödenen', max_digits=10, decimal_places=2, default=0)
    balance = models.DecimalField('Bakiye', max_digits=10, decimal_places=2, default=0)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Check-out'
        verbose_name_plural = 'Check-out\'lar'
        ordering = ['-check_out_datetime']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.check_out_datetime}"


# ==================== DİJİTAL ANAHTAR SİSTEMİ ====================

class KeyCard(TimeStampedModel, SoftDeleteModel):
    """
    Dijital Anahtar Kartı Modeli
    Check-in sırasında oluşturulur, check-out'ta iptal edilir
    """
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.CASCADE,
        related_name='key_cards',
        null=True,
        blank=True,
        verbose_name='Rezervasyon'
    )
    customer = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='key_cards',
        verbose_name='Müşteri'
    )
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.PROTECT,
        related_name='key_cards',
        verbose_name='Oda'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='key_cards',
        verbose_name='Oda Numarası'
    )
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.PROTECT,
        related_name='key_cards',
        verbose_name='Otel'
    )
    
    # Kart Bilgileri
    card_number = models.CharField('Kart Numarası', max_length=50, unique=True, db_index=True)
    card_code = models.CharField('Kart Kodu', max_length=100, help_text='Şifreli kod (RFID/NFC için)')
    
    ACCESS_LEVEL_CHOICES = [
        ('room_only', 'Sadece Oda'),
        ('hotel_access', 'Otel Genel'),
        ('full_access', 'Tam Erişim'),
    ]
    access_level = models.CharField(
        'Erişim Seviyesi',
        max_length=20,
        choices=ACCESS_LEVEL_CHOICES,
        default='room_only'
    )
    
    valid_from = models.DateTimeField('Geçerlilik Başlangıcı')
    valid_until = models.DateTimeField('Geçerlilik Bitişi')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_printed = models.BooleanField('Yazdırıldı mı?', default=False)
    printed_at = models.DateTimeField('Yazdırma Tarihi', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Anahtar Kartı'
        verbose_name_plural = 'Anahtar Kartları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['card_number']),
            models.Index(fields=['customer', 'room']),
            models.Index(fields=['reservation', 'is_active']),
        ]
    
    def __str__(self):
        customer_name = self.customer.get_full_name() if self.customer else 'Müşteri Yok'
        return f"{self.card_number} - {customer_name} - {self.room_number.number if self.room_number else 'Oda Yok'}"
    
    def save(self, *args, **kwargs):
        """Kart numarası otomatik oluştur"""
        if not self.card_number:
            import random
            import string
            # Benzersiz kart numarası oluştur (örn: KC-2025-ABC123)
            year = timezone.now().year
            random_part = ''.join(random.choices(string.ascii_uppercase + string.digits, k=6))
            self.card_number = f'KC-{year}-{random_part}'
        
        # Kart kodu oluştur (şifreli)
        if not self.card_code:
            import secrets
            self.card_code = secrets.token_urlsafe(32)
        
        super().save(*args, **kwargs)


# ==================== RESEPSİYON OTURUM VE AKTİVİTE ====================

class ReceptionSession(TimeStampedModel):
    """
    Resepsiyon Oturum Bilgileri (Vardiya Takibi)
    """
    user = models.ForeignKey(
        User,
        on_delete=models.CASCADE,
        related_name='reception_sessions',
        verbose_name='Kullanıcı'
    )
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='reception_sessions',
        verbose_name='Otel'
    )
    start_time = models.DateTimeField('Başlangıç Zamanı', default=timezone.now)
    end_time = models.DateTimeField('Bitiş Zamanı', null=True, blank=True)
    
    SHIFT_TYPE_CHOICES = [
        ('morning', 'Sabah (06:00-14:00)'),
        ('evening', 'Akşam (14:00-22:00)'),
        ('night', 'Gece (22:00-06:00)'),
        ('custom', 'Özel'),
    ]
    shift_type = models.CharField(
        'Vardiya Tipi',
        max_length=20,
        choices=SHIFT_TYPE_CHOICES,
        default='custom'
    )
    notes = models.TextField('Notlar', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Resepsiyon Oturumu'
        verbose_name_plural = 'Resepsiyon Oturumları'
        ordering = ['-start_time']
        indexes = [
            models.Index(fields=['user', 'hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.user.username} - {self.hotel.name} - {self.start_time}"


class ReceptionActivity(TimeStampedModel):
    """
    Resepsiyon İşlem Kayıtları (Audit Log)
    """
    session = models.ForeignKey(
        ReceptionSession,
        on_delete=models.CASCADE,
        related_name='activities',
        verbose_name='Oturum'
    )
    
    ACTIVITY_TYPE_CHOICES = [
        ('checkin', 'Check-in'),
        ('checkout', 'Check-out'),
        ('booking', 'Rezervasyon'),
        ('payment', 'Ödeme'),
        ('refund', 'İade'),
        ('room_change', 'Oda Değişikliği'),
        ('keycard_created', 'Anahtar Kartı Oluşturuldu'),
        ('keycard_deactivated', 'Anahtar Kartı İptal Edildi'),
    ]
    activity_type = models.CharField(
        'İşlem Türü',
        max_length=30,
        choices=ACTIVITY_TYPE_CHOICES
    )
    
    guest = models.ForeignKey(
        'tenant_core.Customer',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reception_activities',
        verbose_name='Müşteri'
    )
    reservation = models.ForeignKey(
        Reservation,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='activities',
        verbose_name='Rezervasyon'
    )
    room = models.ForeignKey(
        'hotels.Room',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='reception_activities',
        verbose_name='Oda'
    )
    amount = models.DecimalField('Tutar', max_digits=10, decimal_places=2, null=True, blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Resepsiyon İşlemi'
        verbose_name_plural = 'Resepsiyon İşlemleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['session', 'activity_type']),
            models.Index(fields=['guest', 'reservation']),
        ]
    
    def __str__(self):
        return f"{self.session.user.username} - {self.get_activity_type_display()} - {self.created_at}"


# ==================== RESEPSİYON AYARLARI ====================

class ReceptionSettings(TimeStampedModel):
    """
    Resepsiyon Ayarları
    Her otel için ayrı ayarlar
    """
    hotel = models.OneToOneField(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='reception_settings',
        verbose_name='Otel'
    )
    
    # Check-in/out Saatleri
    default_checkin_time = models.TimeField('Varsayılan Check-in Saati', default='14:00')
    default_checkout_time = models.TimeField('Varsayılan Check-out Saati', default='12:00')
    
    # Erken/Geç Çıkış
    early_checkin_allowed = models.BooleanField('Erken Check-in İzni', default=True)
    early_checkin_fee = models.DecimalField(
        'Erken Check-in Ücreti',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    late_checkout_allowed = models.BooleanField('Geç Check-out İzni', default=True)
    late_checkout_fee = models.DecimalField(
        'Geç Check-out Ücreti',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    late_checkout_hour_limit = models.IntegerField(
        'Geç Check-out Saat Limiti',
        default=12,
        help_text='Saat 12\'den sonra geç çıkış sayılır'
    )
    
    # Erken Check-out
    early_checkout_allowed = models.BooleanField('Erken Check-out İzni', default=True)
    early_checkout_fee = models.DecimalField(
        'Erken Check-out Ücreti',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    early_checkout_refund_rate = models.DecimalField(
        'Erken Check-out İade Oranı (%)',
        max_digits=5,
        decimal_places=2,
        null=True,
        blank=True,
        validators=[MinValueValidator(Decimal('0')), MaxValueValidator(Decimal('100'))]
    )
    
    # Otomatik İşlemler
    auto_checkout_time = models.TimeField(
        'Otomatik Check-out Saati',
        null=True,
        blank=True,
        help_text='Bu saatten sonra otomatik check-out yapılır'
    )
    print_receipt_auto = models.BooleanField('Otomatik Makbuz Yazdır', default=False)
    print_keycard_auto = models.BooleanField('Otomatik Anahtar Kartı Yazdır', default=False)
    
    # Ödeme Garantisi
    require_payment_guarantee = models.BooleanField('Ödeme Garantisi Zorunlu mu?', default=False)
    default_guarantee_type = models.CharField(
        'Varsayılan Garanti Tipi',
        max_length=20,
        choices=[
            ('credit_card', 'Kredi Kartı'),
            ('deposit', 'Depozito'),
            ('voucher', 'Voucher'),
        ],
        blank=True
    )
    
    # Overbooking
    allow_overbooking = models.BooleanField('Overbooking İzni', default=False)
    max_overbooking_limit = models.IntegerField(
        'Maksimum Overbooking Limiti',
        default=0,
        validators=[MinValueValidator(0)],
        help_text='Oda sayısından fazla rezervasyon limiti'
    )
    
    # Diğer Ayarlar
    auto_generate_reservation_code = models.BooleanField('Otomatik Rezervasyon Kodu Oluştur', default=True)
    reservation_code_prefix = models.CharField('Rezervasyon Kodu Öneki', max_length=10, default='RES')
    
    class Meta:
        verbose_name = 'Resepsiyon Ayarları'
        verbose_name_plural = 'Resepsiyon Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Resepsiyon Ayarları"


# ==================== HIZLI İŞLEM ŞABLONLARI ====================

class QuickAction(TimeStampedModel, SoftDeleteModel):
    """
    Hızlı İşlem Şablonları
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='quick_actions',
        verbose_name='Otel'
    )
    name = models.CharField('Şablon Adı', max_length=100)
    
    ACTION_TYPE_CHOICES = [
        ('quick_checkin', 'Hızlı Check-in'),
        ('quick_checkout', 'Hızlı Check-out'),
        ('walk_in', 'Kapı Müşterisi'),
        ('group_checkin', 'Grup Check-in'),
    ]
    action_type = models.CharField(
        'İşlem Tipi',
        max_length=30,
        choices=ACTION_TYPE_CHOICES
    )
    template_data = models.JSONField(
        'Şablon Verileri',
        default=dict,
        blank=True,
        help_text='Şablon için varsayılan değerler'
    )
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Hızlı İşlem Şablonu'
        verbose_name_plural = 'Hızlı İşlem Şablonları'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"

