"""
Reception Utilities
Rezervasyon işlemleri için yardımcı fonksiyonlar
"""
from datetime import date, timedelta
from decimal import Decimal


def calculate_nights(check_in_date, check_out_date):
    """Toplam gece sayısını hesapla"""
    if not check_in_date or not check_out_date:
        return 0
    
    nights = (check_out_date - check_in_date).days
    return max(1, nights)


def calculate_total_amount(room_rate, nights, discount_amount=0, tax_amount=0):
    """Toplam tutarı hesapla"""
    if not room_rate or not nights:
        return Decimal('0')
    
    total = Decimal(str(room_rate)) * nights
    total -= Decimal(str(discount_amount))
    total += Decimal(str(tax_amount))
    
    return max(Decimal('0'), total)


def generate_reservation_code(hotel_code=None, year=None):
    """Rezervasyon kodu oluştur"""
    from django.utils import timezone
    
    if not year:
        year = timezone.now().year
    
    if hotel_code:
        prefix = f'{hotel_code}-{year}-'
    else:
        prefix = f'RES-{year}-'
    
    # Son rezervasyon numarasını bul
    from .models import Reservation
    last_reservation = Reservation.objects.filter(
        reservation_code__startswith=prefix
    ).order_by('-reservation_code').first()
    
    if last_reservation:
        last_number = int(last_reservation.reservation_code.split('-')[-1])
        new_number = last_number + 1
    else:
        new_number = 1
    
    return f'{prefix}{new_number:04d}'

