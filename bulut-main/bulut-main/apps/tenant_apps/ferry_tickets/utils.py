"""
Feribot Bileti Utilities
Feribot bilet işlemleri için yardımcı fonksiyonlar
"""
from datetime import date, timedelta
from decimal import Decimal
from django.utils import timezone
from django.db.models import Sum
from django.template.loader import render_to_string
import secrets


def generate_ticket_code(prefix='FERRY', year=None):
    """Feribot bileti kodu oluştur"""
    from .models import FerryTicket
    
    if not year:
        year = timezone.now().year
    
    prefix_str = f'{prefix}-{year}-'
    
    # Son bilet numarasını bul
    last_ticket = FerryTicket.objects.filter(
        ticket_code__startswith=prefix_str
    ).order_by('-ticket_code').first()
    
    if last_ticket:
        try:
            last_number = int(last_ticket.ticket_code.split('-')[-1])
            new_number = last_number + 1
        except (ValueError, IndexError):
            new_number = 1
    else:
        new_number = 1
    
    return f'{prefix_str}{new_number:04d}'


def generate_voucher_code(ticket_code=None):
    """Voucher kodu oluştur"""
    from .models import FerryTicketVoucher
    
    if not ticket_code:
        ticket_code = 'VOUCHER'
    
    date_str = timezone.now().strftime('%Y%m%d')
    prefix = f'FVCH-{date_str}-'
    
    # Son voucher numarasını bul
    last_voucher = FerryTicketVoucher.objects.filter(
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


def calculate_ticket_total_amount(
    adult_count, child_count, infant_count,
    adult_unit_price, child_unit_price, infant_unit_price,
    vehicle_type, vehicle_price,
    discount_type=None, discount_percentage=0, discount_amount=0,
    tax_amount=0
):
    """Bilet toplam tutarını hesapla"""
    total = Decimal('0')
    
    # Yolcu fiyatları
    total += Decimal(str(adult_unit_price)) * adult_count
    total += Decimal(str(child_unit_price)) * child_count
    total += Decimal(str(infant_unit_price)) * infant_count
    
    # Araç fiyatı
    if vehicle_type and vehicle_type != 'none':
        total += Decimal(str(vehicle_price))
    
    # İndirim
    if discount_type == 'percentage' and discount_percentage > 0:
        discount = total * (Decimal(str(discount_percentage)) / Decimal('100'))
        total -= discount
    elif discount_type == 'fixed' and discount_amount > 0:
        total -= Decimal(str(discount_amount))
    
    # Vergi
    total += Decimal(str(tax_amount))
    
    return max(Decimal('0'), total)


def save_guest_information(ticket, post_data):
    """Bilet yolcu bilgilerini kaydet"""
    from .models import FerryTicketGuest
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        # Mevcut yolcuları sil (yeniden kaydetmek için)
        FerryTicketGuest.objects.filter(ticket=ticket, is_deleted=False).delete()
        
        # Yolcu sayıları
        adult_count = ticket.adult_count or 0
        child_count = ticket.child_count or 0
        infant_count = ticket.infant_count or 0
        total_guests = adult_count + child_count + infant_count
        
        if total_guests == 0:
            logger.warning(f'Bilet için yolcu sayısı 0 - Bilet: {ticket.ticket_code}')
            return
        
        guest_order = 1
        
        # Yetişkinler
        for i in range(adult_count):
            guest = FerryTicketGuest.objects.create(
                ticket=ticket,
                ticket_type='adult',
                guest_order=guest_order,
                first_name=post_data.get(f'guest_{guest_order}_first_name', ''),
                last_name=post_data.get(f'guest_{guest_order}_last_name', ''),
                gender=post_data.get(f'guest_{guest_order}_gender', ''),
                birth_date=post_data.get(f'guest_{guest_order}_birth_date') or None,
                age=post_data.get(f'guest_{guest_order}_age') or None,
                tc_no=post_data.get(f'guest_{guest_order}_tc_no', ''),
                passport_no=post_data.get(f'guest_{guest_order}_passport_no', ''),
                passport_serial_no=post_data.get(f'guest_{guest_order}_passport_serial_no', ''),
                id_serial_no=post_data.get(f'guest_{guest_order}_id_serial_no', ''),
                nationality=post_data.get(f'guest_{guest_order}_nationality', 'Türkiye'),
                phone=post_data.get(f'guest_{guest_order}_phone', ''),
                email=post_data.get(f'guest_{guest_order}_email', ''),
            )
            guest_order += 1
            logger.info(f'Yetişkin yolcu kaydedildi - Bilet: {ticket.ticket_code}, Yolcu: {guest.first_name} {guest.last_name}')
        
        # Çocuklar
        for i in range(child_count):
            guest = FerryTicketGuest.objects.create(
                ticket=ticket,
                ticket_type='child',
                guest_order=guest_order,
                first_name=post_data.get(f'guest_{guest_order}_first_name', ''),
                last_name=post_data.get(f'guest_{guest_order}_last_name', ''),
                gender=post_data.get(f'guest_{guest_order}_gender', ''),
                birth_date=post_data.get(f'guest_{guest_order}_birth_date') or None,
                age=post_data.get(f'guest_{guest_order}_age') or None,
                tc_no=post_data.get(f'guest_{guest_order}_tc_no', ''),
                passport_no=post_data.get(f'guest_{guest_order}_passport_no', ''),
                passport_serial_no=post_data.get(f'guest_{guest_order}_passport_serial_no', ''),
                id_serial_no=post_data.get(f'guest_{guest_order}_id_serial_no', ''),
                nationality=post_data.get(f'guest_{guest_order}_nationality', 'Türkiye'),
                phone=post_data.get(f'guest_{guest_order}_phone', ''),
                email=post_data.get(f'guest_{guest_order}_email', ''),
            )
            guest_order += 1
            logger.info(f'Çocuk yolcu kaydedildi - Bilet: {ticket.ticket_code}, Yolcu: {guest.first_name} {guest.last_name}')
        
        # Bebekler
        for i in range(infant_count):
            guest = FerryTicketGuest.objects.create(
                ticket=ticket,
                ticket_type='infant',
                guest_order=guest_order,
                first_name=post_data.get(f'guest_{guest_order}_first_name', ''),
                last_name=post_data.get(f'guest_{guest_order}_last_name', ''),
                gender=post_data.get(f'guest_{guest_order}_gender', ''),
                birth_date=post_data.get(f'guest_{guest_order}_birth_date') or None,
                age=post_data.get(f'guest_{guest_order}_age') or None,
                tc_no=post_data.get(f'guest_{guest_order}_tc_no', ''),
                passport_no=post_data.get(f'guest_{guest_order}_passport_no', ''),
                passport_serial_no=post_data.get(f'guest_{guest_order}_passport_serial_no', ''),
                id_serial_no=post_data.get(f'guest_{guest_order}_id_serial_no', ''),
                nationality=post_data.get(f'guest_{guest_order}_nationality', 'Türkiye'),
                phone=post_data.get(f'guest_{guest_order}_phone', ''),
                email=post_data.get(f'guest_{guest_order}_email', ''),
            )
            guest_order += 1
            logger.info(f'Bebek yolcu kaydedildi - Bilet: {ticket.ticket_code}, Yolcu: {guest.first_name} {guest.last_name}')
        
        logger.info(f'Toplam {total_guests} yolcu kaydedildi - Bilet: {ticket.ticket_code}')
        
    except Exception as e:
        logger.error(f'Yolcu bilgileri kaydedilirken hata: {str(e)}', exc_info=True)
        raise


def generate_ticket_voucher(ticket, template=None):
    """
    Bilet voucher HTML'i oluştur
    
    Args:
        ticket: FerryTicket objesi
        template: FerryTicketVoucherTemplate objesi (None ise varsayılan kullanılır)
    
    Returns:
        (voucher_html, voucher_data) tuple
    """
    from .models import FerryTicketVoucherTemplate
    
    # Varsayılan template'i bul
    if not template:
        template = FerryTicketVoucherTemplate.objects.filter(
            is_active=True, is_default=True, is_deleted=False
        ).first()
        if not template:
            template = FerryTicketVoucherTemplate.objects.filter(
                is_active=True, is_deleted=False
            ).first()
    
    # Template yoksa basit HTML oluştur (Türkçe karakter desteği ile)
    if not template:
        route_name = str(ticket.schedule.route) if ticket.schedule and ticket.schedule.route else ''
        departure_date = ticket.schedule.departure_date.strftime('%d.%m.%Y') if ticket.schedule and ticket.schedule.departure_date else ''
        departure_time = ticket.schedule.departure_time.strftime('%H:%M') if ticket.schedule and ticket.schedule.departure_time else ''
        customer_name = ticket.customer.get_full_name() if ticket.customer else ''
        
        voucher_html = f"""<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feribot Bileti - {ticket.ticket_code}</title>
    <style>
        @charset "UTF-8";
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ 
            font-family: Arial, "DejaVu Sans", "Liberation Sans", sans-serif; 
            padding: 20px; 
            background: #f5f5f5;
        }}
        .voucher {{ 
            border: 2px solid #333; 
            padding: 30px; 
            max-width: 600px; 
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }}
        .header {{ 
            text-align: center; 
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }}
        .header h1 {{
            font-size: 28px;
            color: #2d3e50;
            margin-bottom: 10px;
        }}
        .header p {{
            font-size: 16px;
            color: #666;
        }}
        .section {{ 
            margin-bottom: 15px; 
            padding: 10px;
            border-bottom: 1px solid #eee;
        }}
        .section:last-child {{
            border-bottom: none;
        }}
        .label {{ 
            font-weight: bold; 
            color: #2d3e50;
            display: inline-block;
            min-width: 120px;
        }}
        .value {{
            color: #333;
        }}
    </style>
</head>
<body>
    <div class="voucher">
        <div class="header">
            <h1>Feribot Bileti</h1>
            <p>Bilet Kodu: {ticket.ticket_code}</p>
        </div>
        <div class="section">
            <span class="label">Rota:</span>
            <span class="value">{route_name}</span>
        </div>
        <div class="section">
            <span class="label">Kalkış:</span>
            <span class="value">{departure_date} {departure_time}</span>
        </div>
        <div class="section">
            <span class="label">Müşteri:</span>
            <span class="value">{customer_name}</span>
        </div>
        <div class="section">
            <span class="label">Toplam Tutar:</span>
            <span class="value">{ticket.total_amount} {ticket.currency}</span>
        </div>
    </div>
</body>
</html>
"""
        voucher_data = {
            'ticket_code': ticket.ticket_code,
            'route': str(ticket.schedule.route) if ticket.schedule else '',
            'departure_date': ticket.schedule.departure_date.strftime('%d.%m.%Y') if ticket.schedule and ticket.schedule.departure_date else '',
            'departure_time': ticket.schedule.departure_time.strftime('%H:%M') if ticket.schedule and ticket.schedule.departure_time else '',
            'customer': ticket.customer.get_full_name() if ticket.customer else '',
            'total_amount': str(ticket.total_amount),
            'currency': ticket.currency,
        }
        return voucher_html, voucher_data
    
    # Template varsa kullan
    template_html = template.template_html
    template_css = template.template_css or ''
    
    # Template değişkenlerini doldur
    schedule = ticket.schedule
    route = schedule.route if schedule else None
    
    voucher_data = {
        'ticket_code': ticket.ticket_code,
        'route': str(route) if route else '',
        'departure_port': route.departure_port if route else '',
        'arrival_port': route.arrival_port if route else '',
        'departure_date': schedule.departure_date.strftime('%d.%m.%Y') if schedule and schedule.departure_date else '',
        'departure_time': schedule.departure_time.strftime('%H:%M') if schedule and schedule.departure_time else '',
        'arrival_date': schedule.arrival_date.strftime('%d.%m.%Y') if schedule and schedule.arrival_date else '',
        'arrival_time': schedule.arrival_time.strftime('%H:%M') if schedule and schedule.arrival_time else '',
        'customer_name': ticket.customer.get_full_name() if ticket.customer else '',
        'customer_phone': ticket.customer.phone or '' if ticket.customer else '',
        'customer_email': ticket.customer.email or '' if ticket.customer else '',
        'adult_count': ticket.adult_count,
        'child_count': ticket.child_count,
        'infant_count': ticket.infant_count,
        'vehicle_type': ticket.get_vehicle_type_display() if ticket.vehicle_type != 'none' else 'Araçsız',
        'vehicle_plate': ticket.vehicle_plate or '',
        'total_amount': str(ticket.total_amount),
        'total_paid': str(ticket.total_paid),
        'remaining_amount': str(ticket.get_remaining_amount()),
        'currency': ticket.currency,
        'status': ticket.get_status_display(),
    }
    
    # Template'i render et
    voucher_html = template_html
    for key, value in voucher_data.items():
        voucher_html = voucher_html.replace(f'{{{{ {key} }}}}', str(value))
        voucher_html = voucher_html.replace(f'{{{{{key}}}}}', str(value))
    
    # UTF-8 meta tag'i ve CSS ekle (eğer yoksa)
    if '<meta charset="UTF-8">' not in voucher_html and '<meta charset="utf-8">' not in voucher_html:
        # HTML başına meta tag ekle
        if '<head>' in voucher_html:
            voucher_html = voucher_html.replace('<head>', '<head>\n    <meta charset="UTF-8">')
        elif '<html>' in voucher_html:
            voucher_html = voucher_html.replace('<html>', '<html>\n<head>\n    <meta charset="UTF-8">\n</head>')
        else:
            voucher_html = '<!DOCTYPE html>\n<html lang="tr">\n<head>\n    <meta charset="UTF-8">\n</head>\n<body>\n' + voucher_html + '\n</body>\n</html>'
    
    # CSS ekle (charset ile)
    if template_css:
        if '<style>' not in voucher_html:
            css_with_charset = f'<style>\n@charset "UTF-8";\n{template_css}\n</style>'
        else:
            css_with_charset = f'@charset "UTF-8";\n{template_css}'
            voucher_html = voucher_html.replace('<style>', f'<style>\n{css_with_charset}', 1)
    
    # Font-family ekle (Türkçe karakter desteği için)
    if 'font-family' not in voucher_html.lower():
        if '<style>' in voucher_html:
            voucher_html = voucher_html.replace('<style>', '<style>\nbody, * { font-family: Arial, "DejaVu Sans", "Liberation Sans", sans-serif; }')
    
    return voucher_html, voucher_data


def create_ticket_voucher(ticket, template=None, save=True):
    """
    Bilet voucher'ı oluştur ve kaydet
    
    Args:
        ticket: FerryTicket objesi
        template: FerryTicketVoucherTemplate objesi (None ise varsayılan kullanılır)
        save: Voucher'ı kaydet (True) veya sadece HTML döndür (False)
    
    Returns:
        FerryTicketVoucher objesi veya HTML string
    """
    from .models import FerryTicketVoucher
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        # Voucher HTML ve verilerini oluştur
        logger.info(f'Voucher HTML ve verileri oluşturuluyor - Bilet: {ticket.ticket_code}')
        voucher_html, voucher_data = generate_ticket_voucher(ticket, template)
        
        if not save:
            return voucher_html
        
        # Voucher kodu oluştur
        voucher_code = generate_voucher_code(ticket.ticket_code)
        logger.info(f'Voucher kodu oluşturuldu: {voucher_code}')
        
        # Token oluştur
        access_token = generate_voucher_token()
        logger.info(f'Voucher token oluşturuldu: {access_token[:10]}...')
        
        # Token geçerlilik tarihi (30 gün sonra)
        from datetime import timedelta
        token_expires_at = timezone.now() + timedelta(days=30)
        
        # Ödeme tutarını hesapla (bilet kalan tutarı)
        payment_amount = ticket.get_remaining_amount()
        
        # Voucher kaydı oluştur
        voucher = FerryTicketVoucher.objects.create(
            ticket=ticket,
            voucher_template=template,
            voucher_code=voucher_code,
            voucher_data=voucher_data,
            access_token=access_token,
            token_expires_at=token_expires_at,
            payment_amount=payment_amount,
            payment_currency=ticket.currency,
            payment_status='pending',
        )
        
        logger.info(f'Voucher kaydı oluşturuldu - ID: {voucher.pk}, Kod: {voucher.voucher_code}, Token: {access_token[:10]}...')
        return voucher
        
    except Exception as e:
        logger.error(f'create_ticket_voucher hatası: {str(e)}', exc_info=True)
        raise

