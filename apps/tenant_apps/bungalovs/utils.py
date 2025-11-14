"""
Bungalov Utilities
Bungalov rezervasyon işlemleri için yardımcı fonksiyonlar
"""
from datetime import date, timedelta
from decimal import Decimal
from django.utils import timezone
from django.db.models import Sum
from django.template.loader import render_to_string
import secrets
import logging

logger = logging.getLogger(__name__)


def calculate_nights(check_in_date, check_out_date):
    """Toplam gece sayısını hesapla"""
    if not check_in_date or not check_out_date:
        return 0
    
    nights = (check_out_date - check_in_date).days
    return max(1, nights)


def calculate_total_amount(nightly_rate, nights, weekly_rate=None, monthly_rate=None, 
                          discount_amount=0, extra_fees=0, tax_amount=0):
    """Toplam tutarı hesapla"""
    if not nightly_rate or not nights:
        return Decimal('0')
    
    # Haftalık veya aylık fiyat varsa onu kullan
    base_amount = Decimal('0')
    
    if nights >= 30 and monthly_rate and monthly_rate > 0:
        months = Decimal(str(nights)) / Decimal('30')
        base_amount = monthly_rate * months
    elif nights >= 7 and weekly_rate and weekly_rate > 0:
        weeks = Decimal(str(nights)) / Decimal('7')
        base_amount = weekly_rate * weeks
    else:
        base_amount = Decimal(str(nightly_rate)) * Decimal(str(nights))
    
    total = base_amount - Decimal(str(discount_amount)) + Decimal(str(extra_fees)) + Decimal(str(tax_amount))
    
    return max(Decimal('0'), total)


def generate_reservation_code(prefix='BUNG', year=None):
    """Bungalov rezervasyon kodu oluştur"""
    from .models import BungalovReservation
    
    if not year:
        year = timezone.now().year
    
    prefix = f'{prefix}-{year}-'
    
    # Son rezervasyon numarasını bul
    last_reservation = BungalovReservation.objects.filter(
        reservation_code__startswith=prefix
    ).order_by('-reservation_code').first()
    
    if last_reservation:
        try:
            last_number = int(last_reservation.reservation_code.split('-')[-1])
            new_number = last_number + 1
        except (ValueError, IndexError):
            new_number = 1
    else:
        new_number = 1
    
    return f'{prefix}{new_number:04d}'


def generate_voucher_code(reservation_code=None):
    """Voucher kodu oluştur"""
    from .models import BungalovVoucher
    
    if not reservation_code:
        reservation_code = 'VOUCHER'
    
    date_str = timezone.now().strftime('%Y%m%d')
    prefix = f'BVCH-{date_str}-'
    
    # Son voucher numarasını bul
    last_voucher = BungalovVoucher.objects.filter(
        voucher_code__startswith=prefix
    ).order_by('-voucher_code').first()
    
    if last_voucher:
        try:
            last_number = int(last_voucher.voucher_code.split('-')[-1])
            new_number = last_number + 1
        except (ValueError, IndexError):
            new_number = 1
    else:
        new_number = 1
    
    return f'{prefix}{new_number:04d}'


def generate_voucher_token():
    """Voucher erişim token'ı oluştur"""
    return secrets.token_urlsafe(32)  # 32 byte = 43 karakter URL-safe token


def save_guest_information(reservation, post_data):
    """Bungalov rezervasyon misafir bilgilerini kaydet"""
    from .models import BungalovReservationGuest
    
    try:
        # Yetişkin misafirler
        adult_count = reservation.adult_count or 0
        logger.info(f'Yetişkin sayısı: {adult_count}')
        
        for i in range(1, adult_count + 1):
            first_name = post_data.get(f'adult_guest_{i}_first_name', '').strip()
            last_name = post_data.get(f'adult_guest_{i}_last_name', '').strip()
            
            if first_name and last_name:
                try:
                    BungalovReservationGuest.objects.create(
                        reservation=reservation,
                        guest_type='adult',
                        guest_order=i,
                        first_name=first_name,
                        last_name=last_name,
                        tc_no=post_data.get(f'adult_guest_{i}_tc_no', '').strip(),
                        gender=post_data.get(f'adult_guest_{i}_gender', '').strip(),
                        passport_no=post_data.get(f'adult_guest_{i}_passport_no', '').strip(),
                        nationality=post_data.get(f'adult_guest_{i}_nationality', '').strip(),
                        phone=post_data.get(f'adult_guest_{i}_phone', '').strip(),
                        email=post_data.get(f'adult_guest_{i}_email', '').strip(),
                    )
                    logger.info(f'Yetişkin {i} kaydedildi: {first_name} {last_name}')
                except Exception as e:
                    logger.error(f'Yetişkin {i} kaydedilirken hata: {str(e)}', exc_info=True)
        
        # Çocuk misafirler
        child_count = reservation.child_count or 0
        logger.info(f'Çocuk sayısı: {child_count}')
        child_ages = []
        
        for i in range(1, child_count + 1):
            first_name = post_data.get(f'child_guest_{i}_first_name', '').strip()
            last_name = post_data.get(f'child_guest_{i}_last_name', '').strip()
            age = post_data.get(f'child_guest_{i}_age', '').strip()
            
            if first_name and last_name:
                try:
                    age_int = None
                    if age:
                        try:
                            age_int = int(age)
                            child_ages.append(age_int)
                        except (ValueError, TypeError):
                            pass
                    
                    BungalovReservationGuest.objects.create(
                        reservation=reservation,
                        guest_type='child',
                        guest_order=i,
                        first_name=first_name,
                        last_name=last_name,
                        age=age_int,
                        gender=post_data.get(f'child_guest_{i}_gender', '').strip(),
                        tc_no=post_data.get(f'child_guest_{i}_tc_no', '').strip(),
                        passport_no=post_data.get(f'child_guest_{i}_passport_no', '').strip(),
                        nationality=post_data.get(f'child_guest_{i}_nationality', '').strip(),
                    )
                    logger.info(f'Çocuk {i} kaydedildi: {first_name} {last_name}, Yaş: {age_int}')
                except Exception as e:
                    logger.error(f'Çocuk {i} kaydedilirken hata: {str(e)}', exc_info=True)
        
        # Bebek misafirler
        infant_count = reservation.infant_count or 0
        logger.info(f'Bebek sayısı: {infant_count}')
        
        for i in range(1, infant_count + 1):
            first_name = post_data.get(f'infant_guest_{i}_first_name', '').strip()
            last_name = post_data.get(f'infant_guest_{i}_last_name', '').strip()
            
            if first_name and last_name:
                try:
                    BungalovReservationGuest.objects.create(
                        reservation=reservation,
                        guest_type='infant',
                        guest_order=i,
                        first_name=first_name,
                        last_name=last_name,
                        gender=post_data.get(f'infant_guest_{i}_gender', '').strip(),
                    )
                    logger.info(f'Bebek {i} kaydedildi: {first_name} {last_name}')
                except Exception as e:
                    logger.error(f'Bebek {i} kaydedilirken hata: {str(e)}', exc_info=True)
        
        # Çocuk yaşlarını güncelle
        if child_ages:
            reservation.child_ages = child_ages
            reservation.save(update_fields=['child_ages'])
            logger.info(f'Çocuk yaşları güncellendi: {child_ages}')
        
        logger.info(f'Misafir bilgileri kaydedildi - Toplam: {adult_count} yetişkin, {child_count} çocuk, {infant_count} bebek')
        
    except Exception as e:
        logger.error(f'Misafir bilgileri kaydedilirken genel hata: {str(e)}', exc_info=True)
        raise


def generate_reservation_voucher(reservation, template=None):
    """
    Bungalov rezervasyon voucher'ı oluştur (HTML)
    
    Args:
        reservation: BungalovReservation objesi
        template: BungalovVoucherTemplate objesi (None ise varsayılan kullanılır)
    
    Returns:
        tuple: (voucher_html, voucher_data)
    """
    from .models import BungalovVoucher, BungalovVoucherTemplate
    
    # Şablon seç
    if not template:
        template = BungalovVoucherTemplate.objects.filter(
            is_active=True, is_default=True, is_deleted=False
        ).first()
        if not template:
            template = BungalovVoucherTemplate.objects.filter(
                is_active=True, is_deleted=False
            ).first()
        logger.info(f'Şablon seçildi: {template.name if template else "Varsayılan"}')
    
    # Voucher verilerini hazırla
    customer = reservation.customer
    bungalov = reservation.bungalov
    
    voucher_data = {
        'reservation_code': reservation.reservation_code,
        'customer_name': f"{customer.first_name} {customer.last_name}" if customer else "Misafir",
        'customer_email': customer.email if customer else '',
        'customer_phone': customer.phone if customer else '',
        'bungalov_code': bungalov.code if bungalov else '',
        'bungalov_name': bungalov.name if bungalov else '',
        'bungalov_type': bungalov.bungalov_type.name if bungalov and bungalov.bungalov_type else '',
        'check_in_date': reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else '',
        'check_in_time': reservation.check_in_time.strftime('%H:%M') if reservation.check_in_time else '',
        'check_out_date': reservation.check_out_date.strftime('%d.%m.%Y') if reservation.check_out_date else '',
        'check_out_time': reservation.check_out_time.strftime('%H:%M') if reservation.check_out_time else '',
        'total_nights': reservation.total_nights,
        'adult_count': reservation.adult_count,
        'child_count': reservation.child_count,
        'infant_count': reservation.infant_count,
        'total_amount': str(reservation.total_amount),
        'currency': reservation.currency,
        'nightly_rate': str(reservation.nightly_rate),
        'weekly_rate': str(reservation.weekly_rate) if reservation.weekly_rate else '',
        'monthly_rate': str(reservation.monthly_rate) if reservation.monthly_rate else '',
        'discount_amount': str(reservation.discount_amount),
        'cleaning_fee': str(reservation.cleaning_fee),
        'extra_person_fee': str(reservation.extra_person_fee),
        'pet_fee': str(reservation.pet_fee),
        'tax_amount': str(reservation.tax_amount),
        'total_paid': str(reservation.total_paid),
        'remaining_amount': str(reservation.get_remaining_amount()),
        'deposit_amount': str(reservation.deposit_amount),
        'special_requests': reservation.special_requests or '',
        'created_at': reservation.created_at.strftime('%d.%m.%Y %H:%M') if reservation.created_at else '',
    }
    
    # Misafir bilgilerini ekle
    try:
        guests = reservation.guests.all().order_by('guest_type', 'guest_order')
        voucher_data['guests'] = [
            {
                'name': f"{g.first_name} {g.last_name}",
                'type': g.get_guest_type_display(),
                'age': g.age if g.age else '',
                'gender': g.get_gender_display() if g.gender else '',
            }
            for g in guests
        ]
    except Exception as e:
        logger.warning(f'Misafir bilgileri yüklenirken hata: {str(e)}')
        voucher_data['guests'] = []
    
    # Şablon varsa render et
    try:
        if template and template.template_html:
            voucher_html = template.template_html
            
            # Template değişkenlerini değiştir
            for key, value in voucher_data.items():
                if isinstance(value, list):
                    if key == 'guests':
                        guests_html = ''
                        for guest in value:
                            guests_html += f"<tr><td>{guest['name']}</td><td>{guest['type']}</td><td>{guest.get('age', '')}</td></tr>"
                        voucher_html = voucher_html.replace('{{guests}}', guests_html)
                        voucher_html = voucher_html.replace('{{guests_list}}', guests_html)
                else:
                    placeholder = f'{{{{{key}}}}}'
                    voucher_html = voucher_html.replace(placeholder, str(value or ''))
        else:
            # Varsayılan voucher şablonu
            context = {
                'reservation': reservation,
                'voucher_data': voucher_data,
            }
            voucher_html = render_to_string('bungalovs/vouchers/default.html', context)
    except Exception as e:
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        voucher_html = f"""
        <html>
        <head><title>Voucher - {reservation.reservation_code}</title></head>
        <body>
            <h1>Bungalov Rezervasyon Voucher</h1>
            <p>Rezervasyon Kodu: {reservation.reservation_code}</p>
            <p>Müşteri: {voucher_data.get('customer_name', 'N/A')}</p>
            <p>Bungalov: {voucher_data.get('bungalov_name', 'N/A')}</p>
            <p>Hata: Voucher şablonu render edilemedi. {str(e)}</p>
        </body>
        </html>
        """
    
    return voucher_html, voucher_data


def create_reservation_voucher(reservation, template=None):
    """
    Bungalov rezervasyon voucher'ı oluştur ve kaydet
    
    Args:
        reservation: BungalovReservation objesi
        template: BungalovVoucherTemplate objesi (None ise varsayılan kullanılır)
    
    Returns:
        BungalovVoucher objesi
    """
    from .models import BungalovVoucher
    
    # Voucher zaten varsa güncelle
    voucher, created = BungalovVoucher.objects.get_or_create(
        reservation=reservation,
        defaults={
            'voucher_code': generate_voucher_code(reservation.reservation_code),
            'voucher_token': generate_voucher_token(),
            'template': template,
        }
    )
    
    # Voucher HTML oluştur
    voucher_html, voucher_data = generate_reservation_voucher(reservation, template)
    voucher.voucher_html = voucher_html
    voucher.save()
    
    logger.info(f'Voucher oluşturuldu: {voucher.voucher_code} - {reservation.reservation_code}')
    
    return voucher


def check_bungalov_availability(bungalov, check_in_date, check_out_date, exclude_reservation_id=None):
    """
    Bungalov müsaitlik kontrolü
    
    Args:
        bungalov: Bungalov objesi
        check_in_date: Check-in tarihi
        check_out_date: Check-out tarihi
        exclude_reservation_id: Hariç tutulacak rezervasyon ID
    
    Returns:
        bool: Müsait ise True
    """
    return bungalov.is_available(check_in_date, check_out_date, exclude_reservation_id)


def get_available_bungalovs(check_in_date, check_out_date, bungalov_type=None):
    """
    Belirtilen tarihlerde müsait bungalovları getir
    
    Args:
        check_in_date: Check-in tarihi
        check_out_date: Check-out tarihi
        bungalov_type: BungalovType objesi (None ise tüm tipler)
    
    Returns:
        QuerySet: Müsait bungalovlar
    """
    from .models import Bungalov, BungalovReservation, ReservationStatus
    
    bungalovs = Bungalov.objects.filter(
        status='available',
        is_active=True,
        is_deleted=False
    )
    
    if bungalov_type:
        bungalovs = bungalovs.filter(bungalov_type=bungalov_type)
    
    # Çakışan rezervasyonları kontrol et
    conflicting_reservations = BungalovReservation.objects.filter(
        check_in_date__lt=check_out_date,
        check_out_date__gt=check_in_date,
        status__in=[ReservationStatus.PENDING, ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
        is_deleted=False
    ).values_list('bungalov_id', flat=True)
    
    return bungalovs.exclude(id__in=conflicting_reservations)

