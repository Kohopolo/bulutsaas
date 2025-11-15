"""
Reception Bildirim Utilities
Rezervasyon bildirimleri için WhatsApp ve SMS gönderimi
"""
import logging
from django.utils import timezone
from typing import Dict, Any, Optional
from .models import Reservation, ReservationVoucher

logger = logging.getLogger(__name__)


def send_reservation_notification(
    reservation: Reservation,
    notification_type: str,
    template_code: Optional[str] = None,
    custom_message: Optional[str] = None,
    recipient_phone: Optional[str] = None,
    recipient_email: Optional[str] = None
):
    """
    Rezervasyon bildirimi gönder (WhatsApp, SMS, Email)
    
    Args:
        reservation: Reservation objesi
        notification_type: 'whatsapp', 'sms', 'email'
        template_code: Bildirim şablonu kodu (opsiyonel)
        custom_message: Özel mesaj (opsiyonel)
        recipient_phone: Alıcı telefon (opsiyonel, müşteri telefonu kullanılır)
        recipient_email: Alıcı e-posta (opsiyonel, müşteri e-postası kullanılır)
    
    Returns:
        bool: Başarılı ise True
    """
    try:
        from apps.notifications.services import send_notification
        
        # Alıcı bilgilerini al
        customer = reservation.customer
        if not recipient_phone and customer:
            recipient_phone = customer.phone
        if not recipient_email and customer:
            recipient_email = customer.email
        
        if not recipient_phone and notification_type in ['whatsapp', 'sms']:
            logger.warning(f"Rezervasyon {reservation.reservation_code} için telefon numarası bulunamadı")
            return False
        
        if not recipient_email and notification_type == 'email':
            logger.warning(f"Rezervasyon {reservation.reservation_code} için e-posta bulunamadı")
            return False
        
        # Mesaj içeriğini hazırla
        if custom_message:
            message = custom_message
        elif template_code:
            # Şablon kullan
            message = get_notification_template_message(reservation, template_code, notification_type)
        else:
            # Varsayılan mesaj
            message = get_default_notification_message(reservation, notification_type)
        
        # Değişkenleri doldur
        message = fill_notification_variables(message, reservation)
        
        # Bildirim gönder
        provider_code = f'{notification_type}_netgsm' if notification_type == 'sms' else notification_type
        recipient = recipient_phone if notification_type in ['whatsapp', 'sms'] else recipient_email
        
        result = send_notification(
            provider_code=provider_code,
            recipient=recipient,
            template_code=template_code,
            subject=f"Rezervasyon: {reservation.reservation_code}" if notification_type == 'email' else '',
            content=message,
            variables=get_reservation_variables(reservation),
        )
        
        if result and result.get('success'):
            logger.info(f"Rezervasyon bildirimi gönderildi: {reservation.reservation_code} - {notification_type}")
            return True
        else:
            error_msg = result.get('error', 'Bilinmeyen hata') if result else 'Bildirim gönderilemedi'
            logger.error(f"Rezervasyon bildirimi gönderilemedi: {reservation.reservation_code} - {notification_type} - {error_msg}")
            return False
            
    except Exception as e:
        logger.error(f"Rezervasyon bildirimi hatası: {str(e)}")
        return False


def send_reservation_whatsapp(reservation: Reservation, message: Optional[str] = None):
    """Rezervasyon WhatsApp bildirimi gönder"""
    return send_reservation_notification(
        reservation=reservation,
        notification_type='whatsapp',
        custom_message=message,
    )


def send_reservation_sms(reservation: Reservation, message: Optional[str] = None):
    """Rezervasyon SMS bildirimi gönder"""
    return send_reservation_notification(
        reservation=reservation,
        notification_type='sms',
        custom_message=message,
    )


def send_reservation_email(reservation: Reservation, subject: Optional[str] = None, message: Optional[str] = None):
    """Rezervasyon E-posta bildirimi gönder"""
    return send_reservation_notification(
        reservation=reservation,
        notification_type='email',
        custom_message=message,
    )


def send_voucher_notification(
    voucher: ReservationVoucher,
    notification_type: str,
    recipient_phone: Optional[str] = None,
    recipient_email: Optional[str] = None
):
    """
    Voucher bildirimi gönder
    
    Args:
        voucher: ReservationVoucher objesi
        notification_type: 'whatsapp', 'sms', 'email'
        recipient_phone: Alıcı telefon
        recipient_email: Alıcı e-posta
    """
    try:
        from apps.notifications.services import send_notification
        
        reservation = voucher.reservation
        customer = reservation.customer
        
        if not recipient_phone and customer:
            recipient_phone = customer.phone
        if not recipient_email and customer:
            recipient_email = customer.email
        
        # Voucher mesajı
        message = f"""
Rezervasyon Voucher'ınız hazır!

Rezervasyon Kodu: {reservation.reservation_code}
Otel: {reservation.hotel.name if reservation.hotel else ''}
Check-in: {reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else ''}
Check-out: {reservation.check_out_date.strftime('%d.%m.%Y') if reservation.check_out_date else ''}

Voucher kodunuz: {voucher.voucher_code}

Detaylar için lütfen otelimizle iletişime geçin.
        """.strip()
        
        # Bildirim gönder
        provider_code = f'{notification_type}_netgsm' if notification_type == 'sms' else notification_type
        recipient = recipient_phone if notification_type in ['whatsapp', 'sms'] else recipient_email
        
        result = send_notification(
            provider_code=provider_code,
            recipient=recipient,
            subject=f"Rezervasyon Voucher: {reservation.reservation_code}" if notification_type == 'email' else '',
            content=message,
            variables=get_reservation_variables(reservation),
        )
        
        if result and result.get('success'):
            # Voucher gönderim durumunu güncelle
            voucher.is_sent = True
            voucher.sent_at = timezone.now()
            voucher.sent_via = notification_type
            voucher.save(update_fields=['is_sent', 'sent_at', 'sent_via'])
            
            logger.info(f"Voucher bildirimi gönderildi: {voucher.voucher_code} - {notification_type}")
            return True
        else:
            error_msg = result.get('error', 'Bilinmeyen hata') if result else 'Bildirim gönderilemedi'
            logger.error(f"Voucher bildirimi gönderilemedi: {voucher.voucher_code} - {notification_type} - {error_msg}")
            return False
            
    except Exception as e:
        logger.error(f"Voucher bildirimi hatası: {str(e)}")
        return False


def get_reservation_variables(reservation: Reservation) -> Dict[str, Any]:
    """Rezervasyon değişkenlerini hazırla"""
    customer = reservation.customer
    hotel = reservation.hotel
    
    return {
        'reservation_code': reservation.reservation_code,
        'customer_name': f"{customer.first_name} {customer.last_name}" if customer else "Misafir",
        'customer_phone': customer.phone if customer else '',
        'customer_email': customer.email if customer else '',
        'hotel_name': hotel.name if hotel else '',
        'hotel_phone': hotel.phone if hotel and hasattr(hotel, 'phone') else '',
        'hotel_address': hotel.address if hotel and hasattr(hotel, 'address') else '',
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
        'total_paid': str(reservation.total_paid),
        'remaining_amount': str(reservation.get_remaining_amount()),
    }


def fill_notification_variables(message: str, reservation: Reservation) -> str:
    """Mesaj içindeki değişkenleri doldur"""
    variables = get_reservation_variables(reservation)
    
    for key, value in variables.items():
        placeholder = f'{{{{{key}}}}}'
        message = message.replace(placeholder, str(value))
    
    return message


def get_default_notification_message(reservation: Reservation, notification_type: str) -> str:
    """Varsayılan bildirim mesajı"""
    customer = reservation.customer
    hotel = reservation.hotel
    
    if notification_type == 'whatsapp':
        return f"""
Merhaba {customer.first_name if customer else 'Sayın Misafir'},

Rezervasyonunuz başarıyla oluşturuldu!

📋 Rezervasyon Kodu: {reservation.reservation_code}
🏨 Otel: {hotel.name if hotel else ''}
🛏️ Oda: {reservation.room.name if reservation.room else ''}
📅 Check-in: {reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else ''}
📅 Check-out: {reservation.check_out_date.strftime('%d.%m.%Y') if reservation.check_out_date else ''}
💰 Toplam Tutar: {reservation.total_amount} {reservation.currency}

Detaylar için lütfen otelimizle iletişime geçin.

İyi günler dileriz.
        """.strip()
    
    elif notification_type == 'sms':
        return f"""
Rezervasyonunuz oluşturuldu. Kod: {reservation.reservation_code}
Otel: {hotel.name if hotel else ''}
Check-in: {reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else ''}
Tutar: {reservation.total_amount} {reservation.currency}
        """.strip()
    
    else:  # email
        return f"""
Sayın {customer.first_name if customer else 'Misafir'},

Rezervasyonunuz başarıyla oluşturulmuştur.

Rezervasyon Detayları:
- Rezervasyon Kodu: {reservation.reservation_code}
- Otel: {hotel.name if hotel else ''}
- Oda: {reservation.room.name if reservation.room else ''}
- Check-in: {reservation.check_in_date.strftime('%d.%m.%Y') if reservation.check_in_date else ''}
- Check-out: {reservation.check_out_date.strftime('%d.%m.%Y') if reservation.check_out_date else ''}
- Toplam Tutar: {reservation.total_amount} {reservation.currency}

Detaylar için lütfen otelimizle iletişime geçin.

İyi günler dileriz.
        """.strip()


def get_notification_template_message(reservation: Reservation, template_code: str, notification_type: str) -> str:
    """Şablon mesajını al"""
    try:
        from apps.notifications.models import NotificationTemplate
        
        template = NotificationTemplate.objects.filter(
            code=template_code,
            template_type=notification_type,
            is_active=True
        ).first()
        
        if template:
            return template.message
        else:
            return get_default_notification_message(reservation, notification_type)
    except:
        return get_default_notification_message(reservation, notification_type)

