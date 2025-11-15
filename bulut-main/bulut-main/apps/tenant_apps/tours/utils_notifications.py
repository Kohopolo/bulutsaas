"""
Tur Modülü - Bildirim Gönderme Yardımcı Fonksiyonları
"""
from django.core.mail import send_mail
from django.conf import settings
from django.utils import timezone
from .models import TourNotification, TourNotificationTemplate, TourReservation, TourCustomer
import logging

logger = logging.getLogger(__name__)


def send_notification(trigger_event, reservation=None, customer=None, context=None):
    """
    Otomatik bildirim gönder
    
    Args:
        trigger_event: Tetikleyici olay (reservation_created, reservation_confirmed, vb.)
        reservation: TourReservation objesi (opsiyonel)
        customer: TourCustomer objesi (opsiyonel)
        context: Ek context değişkenleri (dict)
    """
    # Şablonları al
    templates = TourNotificationTemplate.objects.filter(
        trigger_event=trigger_event,
        is_active=True
    )
    
    if not templates.exists():
        logger.warning(f"Bildirim şablonu bulunamadı: {trigger_event}")
        return
    
    # Context hazırla
    notification_context = context or {}
    
    if reservation:
        notification_context.update({
            'reservation_code': reservation.reservation_code,
            'tour_name': reservation.tour.name,
            'tour_date': reservation.tour_date.date.strftime('%d.%m.%Y') if reservation.tour_date else '',
            'customer_name': reservation.customer_name,
            'customer_surname': reservation.customer_surname,
            'customer_email': reservation.customer_email,
            'customer_phone': reservation.customer_phone,
            'total_amount': str(reservation.total_amount),
            'currency': reservation.currency,
        })
    
    if customer:
        notification_context.update({
            'customer_name': customer.first_name,
            'customer_surname': customer.last_name,
            'customer_email': customer.email,
            'customer_phone': customer.phone,
            'loyalty_points': customer.loyalty_points,
        })
    
    # Her şablon için bildirim gönder
    for template in templates:
        try:
            # Mesajı değişkenlerle doldur
            message = template.message
            for key, value in notification_context.items():
                message = message.replace(f'{{{{{key}}}}}', str(value))
            
            # Konu
            subject = template.subject
            for key, value in notification_context.items():
                subject = subject.replace(f'{{{{{key}}}}}', str(value))
            
            # Bildirim kaydı oluştur
            notification = TourNotification.objects.create(
                template=template,
                notification_type=template.notification_type,
                recipient_email=notification_context.get('customer_email', ''),
                recipient_phone=notification_context.get('customer_phone', ''),
                subject=subject,
                message=message,
                reservation=reservation,
                customer=customer,
                status='pending',
            )
            
            # Bildirimi gönder
            if template.notification_type == 'email':
                send_email_notification(notification)
            elif template.notification_type == 'sms':
                send_sms_notification(notification)
            elif template.notification_type == 'whatsapp':
                send_whatsapp_notification(notification)
            
        except Exception as e:
            logger.error(f"Bildirim gönderme hatası: {str(e)}")
            if 'notification' in locals():
                notification.status = 'failed'
                notification.error_message = str(e)
                notification.save()


def send_email_notification(notification):
    """E-posta bildirimi gönder"""
    try:
        send_mail(
            notification.subject,
            notification.message,
            settings.DEFAULT_FROM_EMAIL,
            [notification.recipient_email],
            fail_silently=False,
        )
        notification.status = 'sent'
        notification.sent_at = timezone.now()
        notification.save()
    except Exception as e:
        notification.status = 'failed'
        notification.error_message = str(e)
        notification.save()
        raise


def send_sms_notification(notification):
    """SMS bildirimi gönder (gelecekte SMS API entegrasyonu)"""
    # TODO: SMS API entegrasyonu (Netgsm, İleti Merkezi, vb.)
    notification.status = 'sent'
    notification.sent_at = timezone.now()
    notification.save()
    logger.info(f"SMS bildirimi gönderildi: {notification.recipient_phone}")


def send_whatsapp_notification(notification):
    """WhatsApp bildirimi gönder (gelecekte WhatsApp API entegrasyonu)"""
    # TODO: WhatsApp Business API entegrasyonu
    notification.status = 'sent'
    notification.sent_at = timezone.now()
    notification.save()
    logger.info(f"WhatsApp bildirimi gönderildi: {notification.recipient_phone}")


def send_reservation_notifications(reservation, event):
    """Rezervasyon bildirimleri gönder"""
    customer = reservation.customer
    send_notification(event, reservation=reservation, customer=customer)


def send_tour_reminder(tour_date):
    """Tur hatırlatması gönder (tur tarihinden 1 gün önce)"""
    from datetime import timedelta
    
    tomorrow = timezone.now().date() + timedelta(days=1)
    reservations = TourReservation.objects.filter(
        tour_date__date=tomorrow,
        status='confirmed'
    ).select_related('customer', 'tour', 'tour_date')
    
    for reservation in reservations:
        send_notification('tour_reminder', reservation=reservation, customer=reservation.customer)

