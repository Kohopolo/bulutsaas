"""
Reception Utilities
Rezervasyon işlemleri için yardımcı fonksiyonlar
"""
from datetime import date, timedelta
from decimal import Decimal
from django.utils import timezone
from django.db.models import Sum
from django.template.loader import render_to_string
import re


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
    from .models import Reservation
    
    if not year:
        year = timezone.now().year
    
    if hotel_code:
        prefix = f'{hotel_code.upper()}-{year}-'
    else:
        prefix = f'RES-{year}-'
    
    # Son rezervasyon numarasını bul
    last_reservation = Reservation.objects.filter(
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
    from .models import ReservationVoucher
    
    if not reservation_code:
        reservation_code = 'VOUCHER'
    
    date_str = timezone.now().strftime('%Y%m%d')
    prefix = f'VCH-{date_str}-'
    
    # Son voucher numarasını bul
    last_voucher = ReservationVoucher.objects.filter(
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


def save_guest_information(reservation, post_data):
    """Rezervasyon misafir bilgilerini kaydet"""
    from .models import ReservationGuest
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        # Yetişkin misafirler
        adult_count = reservation.adult_count or 0
        logger.info(f'Yetişkin sayısı: {adult_count}')
        
        for i in range(1, adult_count + 1):
            first_name = post_data.get(f'adult_guest_{i}_first_name', '').strip()
            last_name = post_data.get(f'adult_guest_{i}_last_name', '').strip()
            
            if first_name and last_name:
                try:
                    ReservationGuest.objects.create(
                        reservation=reservation,
                        guest_type='adult',
                        guest_order=i,
                        first_name=first_name,
                        last_name=last_name,
                        tc_no=post_data.get(f'adult_guest_{i}_tc_no', '').strip(),
                        id_serial_no=post_data.get(f'adult_guest_{i}_id_serial_no', '').strip(),
                        gender=post_data.get(f'adult_guest_{i}_gender', '').strip(),
                        passport_no=post_data.get(f'adult_guest_{i}_passport_no', '').strip(),
                    )
                    logger.info(f'Yetişkin {i} kaydedildi: {first_name} {last_name}')
                except Exception as e:
                    logger.error(f'Yetişkin {i} kaydedilirken hata: {str(e)}', exc_info=True)
            else:
                logger.warning(f'Yetişkin {i} kaydedilmedi - Ad/Soyad eksik: {first_name}, {last_name}')
        
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
                    
                    ReservationGuest.objects.create(
                        reservation=reservation,
                        guest_type='child',
                        guest_order=i,
                        first_name=first_name,
                        last_name=last_name,
                        age=age_int,
                        gender=post_data.get(f'child_guest_{i}_gender', '').strip(),
                        tc_no=post_data.get(f'child_guest_{i}_tc_no', '').strip(),
                        passport_no=post_data.get(f'child_guest_{i}_passport_no', '').strip(),
                        passport_serial_no=post_data.get(f'child_guest_{i}_passport_serial_no', '').strip(),
                    )
                    logger.info(f'Çocuk {i} kaydedildi: {first_name} {last_name}, Yaş: {age_int}')
                except Exception as e:
                    logger.error(f'Çocuk {i} kaydedilirken hata: {str(e)}', exc_info=True)
            else:
                logger.warning(f'Çocuk {i} kaydedilmedi - Ad/Soyad eksik: {first_name}, {last_name}')
        
        # Çocuk yaşlarını güncelle
        if child_ages:
            reservation.child_ages = child_ages
            reservation.save(update_fields=['child_ages'])
            logger.info(f'Çocuk yaşları güncellendi: {child_ages}')
        
        logger.info(f'Misafir bilgileri kaydedildi - Toplam: {adult_count} yetişkin, {child_count} çocuk')
        
    except Exception as e:
        logger.error(f'Misafir bilgileri kaydedilirken genel hata: {str(e)}', exc_info=True)
        raise


def generate_reservation_voucher(reservation, template=None):
    """
    Rezervasyon voucher'ı oluştur (HTML)
    
    Args:
        reservation: Reservation objesi
        template: VoucherTemplate objesi (None ise varsayılan kullanılır)
    
    Returns:
        tuple: (voucher_html, voucher_data)
    """
    from .models import ReservationVoucher, VoucherTemplate
    
    # Şablon seç
    if not template:
        template = VoucherTemplate.objects.filter(is_default=True, is_active=True).first()
        if not template:
            template = VoucherTemplate.objects.filter(is_active=True).first()
    
    # Voucher verilerini hazırla
    customer = reservation.customer
    hotel = reservation.hotel
    
    voucher_data = {
        'reservation_code': reservation.reservation_code,
        'customer_name': f"{customer.first_name} {customer.last_name}" if customer else "Misafir",
        'customer_email': customer.email if customer else '',
        'customer_phone': customer.phone if customer else '',
        'hotel_name': hotel.name if hotel else '',
        'hotel_address': hotel.address if hotel and hasattr(hotel, 'address') else '',
        'hotel_phone': hotel.phone if hotel and hasattr(hotel, 'phone') else '',
        'room_name': reservation.room.name if reservation.room else '',
        'room_number': reservation.room_number.number if reservation.room_number else '',
        'check_in_date': reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else '',
        'check_in_time': reservation.check_in_time.strftime('%H:%M') if reservation.check_in_time else '',
        'check_out_date': reservation.check_out_date.strftime('%d.%m.%Y') if reservation.check_out_date else '',
        'check_out_time': reservation.check_out_time.strftime('%H:%M') if reservation.check_out_time else '',
        'total_nights': reservation.total_nights,
        'adult_count': reservation.adult_count,
        'child_count': reservation.child_count,
        'total_amount': str(reservation.total_amount),
        'currency': reservation.currency,
        'room_rate': str(reservation.room_rate),
        'discount_amount': str(reservation.discount_amount),
        'tax_amount': str(reservation.tax_amount),
        'total_paid': str(reservation.total_paid),
        'remaining_amount': str(reservation.get_remaining_amount()),
        'special_requests': reservation.special_requests or '',
        'created_at': reservation.created_at.strftime('%d.%m.%Y %H:%M') if reservation.created_at else '',
    }
    
    # Misafir bilgilerini ekle
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
    
    # Şablon varsa render et
    if template:
        voucher_html = template.template_html
        
        # Template değişkenlerini değiştir
        for key, value in voucher_data.items():
            if isinstance(value, list):
                # Liste için özel işleme
                if key == 'guests':
                    guests_html = ''
                    for guest in value:
                        guests_html += f"<tr><td>{guest['name']}</td><td>{guest['type']}</td><td>{guest.get('age', '')}</td></tr>"
                    voucher_html = voucher_html.replace('{{guests}}', guests_html)
                    voucher_html = voucher_html.replace('{{guests_list}}', guests_html)
            else:
                # Normal değişkenler
                placeholder = f'{{{{{key}}}}}'
                voucher_html = voucher_html.replace(placeholder, str(value))
        
        # CSS ekle
        if template.template_css:
            voucher_html = f'<style>{template.template_css}</style>\n{voucher_html}'
    else:
        # Varsayılan voucher şablonu
        context = {
            'reservation': reservation,
            'voucher_data': voucher_data,
        }
        voucher_html = render_to_string('reception/vouchers/default.html', context)
    
    return voucher_html, voucher_data


def create_reservation_voucher(reservation, template=None, save=True):
    """
    Rezervasyon voucher'ı oluştur ve kaydet
    
    Args:
        reservation: Reservation objesi
        template: VoucherTemplate objesi (None ise varsayılan kullanılır)
        save: Voucher'ı kaydet (True) veya sadece HTML döndür (False)
    
    Returns:
        ReservationVoucher objesi veya HTML string
    """
    from .models import ReservationVoucher
    
    # Voucher HTML ve verilerini oluştur
    voucher_html, voucher_data = generate_reservation_voucher(reservation, template)
    
    if not save:
        return voucher_html
    
    # Voucher kaydı oluştur
    voucher = ReservationVoucher.objects.create(
        reservation=reservation,
        voucher_template=template,
        voucher_code=generate_voucher_code(reservation.reservation_code),
        voucher_data=voucher_data,
    )
    
    return voucher


def calculate_room_price_with_utility(
    room,
    check_in_date,
    check_out_date,
    adult_count=1,
    child_count=0,
    child_ages=None,
    agency_id=None,
    channel_id=None
):
    """
    Oda fiyatını Global Pricing Utility ile hesapla
    
    Args:
        room: Room objesi
        check_in_date: Check-in tarihi (date)
        check_out_date: Check-out tarihi (date)
        adult_count: Yetişkin sayısı
        child_count: Çocuk sayısı
        child_ages: Çocuk yaşları listesi
        agency_id: Acente ID (varsa)
        channel_id: Kanal ID (varsa)
    
    Returns:
        Dict: {
            'success': bool,
            'avg_nightly_price': Decimal,
            'total_price': Decimal,
            'nights': int,
            'error': str (hata varsa)
        }
    """
    try:
        # Gece sayısını hesapla
        nights = calculate_nights(check_in_date, check_out_date)
        
        # Oda fiyatını bul
        from apps.tenant_apps.hotels.models import RoomPrice
        room_price = RoomPrice.objects.filter(
            room=room,
            is_active=True,
            is_deleted=False
        ).first()
        
        if not room_price:
            return {
                'success': False,
                'error': 'Oda için aktif fiyat bulunamadı'
            }
        
        # Channel ID'den channel name'e çevir (eğer varsa)
        channel_name = None
        if channel_id:
            try:
                from apps.tenant_apps.channels.models import Channel
                channel = Channel.objects.filter(id=channel_id).first()
                if channel:
                    channel_name = channel.name
            except:
                pass
        
        # Fiyat hesapla (RoomPrice.calculate_price metodunu kullan)
        price_result = room_price.calculate_price(
            check_date=check_in_date,
            adults=adult_count,
            children=child_count,
            child_ages=child_ages or [],
            agency_id=agency_id,
            channel_name=channel_name,
            nights=nights
        )
        
        # Gecelik ortalama fiyat
        total_price = price_result.get('total_price', Decimal('0'))
        avg_nightly_price = total_price / Decimal(str(nights)) if nights > 0 else Decimal('0')
        
        return {
            'success': True,
            'avg_nightly_price': avg_nightly_price,
            'total_price': total_price,
            'nights': nights,
            'breakdown': price_result.get('breakdown', {})
        }
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Fiyat hesaplama hatası: {str(e)}', exc_info=True)
        return {
            'success': False,
            'error': str(e)
        }
