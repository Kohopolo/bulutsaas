"""
Resepsiyon Modülü Yardımcı Fonksiyonlar
"""
from django.utils import timezone
from datetime import date, datetime, timedelta
from decimal import Decimal
from typing import Dict, List, Optional
from .models import Reservation, ReceptionSettings


def calculate_nights(check_in_date: date, check_out_date: date) -> int:
    """
    Gece sayısını hesapla
    """
    return (check_out_date - check_in_date).days


def is_early_checkout(reservation: Reservation, check_out_date: date) -> bool:
    """
    Erken çıkış kontrolü
    """
    if reservation.check_out_date and check_out_date < reservation.check_out_date:
        return True
    return False


def is_late_checkout(reservation: Reservation, check_out_time: datetime) -> bool:
    """
    Geç çıkış kontrolü
    """
    try:
        settings = ReceptionSettings.objects.get(hotel=reservation.hotel)
        default_checkout_time = settings.default_checkout_time
        
        if check_out_time.time() > default_checkout_time:
            return True
    except ReceptionSettings.DoesNotExist:
        pass
    
    return False


def calculate_early_checkout_fee(reservation: Reservation, check_out_date: date) -> Decimal:
    """
    Erken çıkış ücretini hesapla
    """
    try:
        settings = ReceptionSettings.objects.get(hotel=reservation.hotel)
        
        if settings.early_checkout_allowed and settings.early_checkout_fee:
            if is_early_checkout(reservation, check_out_date):
                return settings.early_checkout_fee
    except ReceptionSettings.DoesNotExist:
        pass
    
    return Decimal('0')


def calculate_late_checkout_fee(reservation: Reservation, check_out_time: datetime) -> Decimal:
    """
    Geç çıkış ücretini hesapla
    """
    try:
        settings = ReceptionSettings.objects.get(hotel=reservation.hotel)
        
        if settings.late_checkout_allowed and settings.late_checkout_fee:
            if is_late_checkout(reservation, check_out_time):
                return settings.late_checkout_fee
    except ReceptionSettings.DoesNotExist:
        pass
    
    return Decimal('0')


def get_room_availability(hotel, check_in_date: date, check_out_date: date) -> Dict:
    """
    Oda müsaitlik durumunu getir
    """
    from apps.tenant_apps.hotels.models import Room, RoomNumber
    
    # Rezervasyonlu odaları bul
    reserved_room_numbers = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__lt=check_out_date,
        check_out_date__gt=check_in_date,
        status__in=['confirmed', 'checked_in'],
        is_deleted=False
    ).values_list('room_number_id', flat=True)
    
    # Müsait oda numaraları
    available_room_numbers = RoomNumber.objects.filter(
        hotel=hotel,
        is_active=True,
        is_deleted=False
    ).exclude(id__in=reserved_room_numbers)
    
    # Oda tipine göre grupla
    availability = {}
    for room_number in available_room_numbers:
        room_type = room_number.room
        if room_type not in availability:
            availability[room_type] = []
        availability[room_type].append(room_number)
    
    return availability


def generate_reservation_code(hotel, prefix: str = 'RES') -> str:
    """
    Rezervasyon kodu oluştur
    """
    year = timezone.now().year
    last_reservation = Reservation.objects.filter(
        hotel=hotel,
        reservation_code__startswith=f'{prefix}-{year}-'
    ).order_by('-reservation_code').first()
    
    if last_reservation:
        last_number = int(last_reservation.reservation_code.split('-')[-1])
        new_number = last_number + 1
    else:
        new_number = 1
    
    return f'{prefix}-{year}-{new_number:04d}'

