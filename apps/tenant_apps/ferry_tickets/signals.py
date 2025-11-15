"""
Feribot Bileti Signals
Otomatik işlemler için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
import logging

logger = logging.getLogger(__name__)

# Bilet durumu değişikliği için eski durumu saklamak için
_ticket_status_cache = {}


@receiver(post_save, sender='ferry_tickets.FerryTicketPayment')
def update_ticket_total_paid(sender, instance, created, **kwargs):
    """Ödeme kaydedildiğinde bilet toplam ödenen tutarını güncelle ve durumu kontrol et"""
    if created and not instance.is_deleted:
        try:
            from .models import FerryTicketStatus
            ticket = instance.ticket
            ticket.update_total_paid()
            
            # Tam ödendiyse durumu CONFIRMED yap
            if ticket.is_paid() and ticket.status != FerryTicketStatus.CONFIRMED:
                ticket.status = FerryTicketStatus.CONFIRMED
                ticket.save(update_fields=['status'])
                logger.info(f'Bilet tamamen ödendi, durum CONFIRMED olarak güncellendi - Bilet: {ticket.ticket_code}')
            
            logger.info(f'Bilet toplam ödenen tutarı güncellendi - Bilet: {ticket.ticket_code}, Tutar: {ticket.total_paid}')
        except Exception as e:
            logger.error(f'Bilet toplam ödenen tutarı güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender='ferry_tickets.FerryTicketPayment')
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """Ödeme kaydedildiğinde Finance modülüne işlem oluştur"""
    if created and not instance.is_deleted:
        try:
            from apps.tenant_apps.finance.models import CashTransaction
            
            ticket = instance.ticket
            
            CashTransaction.objects.create(
                transaction_type='income',
                amount=instance.payment_amount,
                currency=instance.currency,
                description=f'Feribot Bileti Ödemesi - {ticket.ticket_code}',
                reference_code=ticket.ticket_code,
                transaction_date=instance.payment_date,
                created_by=instance.created_by,
            )
            logger.info(f'Finance işlemi oluşturuldu - Bilet: {ticket.ticket_code}')
        except Exception as e:
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender='ferry_tickets.FerryTicketPayment')
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """Ödeme kaydedildiğinde Accounting modülüne ödeme kaydı oluştur"""
    if created and not instance.is_deleted:
        try:
            from apps.tenant_apps.accounting.utils import create_payment
            
            ticket = instance.ticket
            
            # İlgili faturayı bul (varsa)
            invoice_id = None
            try:
                from apps.tenant_apps.accounting.models import Invoice
                invoice = Invoice.objects.filter(
                    source_module='ferry_tickets',
                    source_id=ticket.pk,
                    is_deleted=False
                ).first()
                if invoice:
                    invoice_id = invoice.pk
            except:
                pass
            
            # Accounting ödeme kaydı oluştur
            create_payment(
                amount=instance.payment_amount,
                invoice_id=invoice_id,
                source_module='ferry_tickets',
                source_id=ticket.pk,
                source_reference=f"Feribot Bileti: {ticket.ticket_code}",
                payment_date=instance.payment_date if hasattr(instance, 'payment_date') else timezone.now(),
                currency=instance.currency,
                payment_method=instance.payment_method,
                created_by=instance.created_by,
                hotel=None,  # Ferry Tickets modülünde otel bilgisi yok
                description=f"Feribot bileti ödemesi - {ticket.ticket_code}",
                auto_complete=True,
            )
            logger.info(f'Accounting ödeme kaydı oluşturuldu - Bilet: {ticket.ticket_code}')
        except Exception as e:
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(pre_save, sender='ferry_tickets.FerryTicket')
def cache_ticket_status(sender, instance, **kwargs):
    """Bilet kaydedilmeden önce eski durumu sakla"""
    if instance.pk:
        try:
            from .models import FerryTicket
            old_instance = FerryTicket.objects.get(pk=instance.pk)
            _ticket_status_cache[instance.pk] = old_instance.status
        except Exception:
            pass


@receiver(post_save, sender='ferry_tickets.FerryTicket')
def create_sales_record_on_ticket(sender, instance, created, **kwargs):
    """Bilet oluşturulduğunda Sales modülüne satış kaydı oluştur"""
    if created and not instance.is_deleted:
        try:
            from apps.tenant_apps.sales.models import SalesRecord
            
            SalesRecord.objects.create(
                customer=instance.customer,
                product_type='ferry_ticket',
                product_reference=instance.ticket_code,
                amount=instance.total_amount,
                currency=instance.currency,
                sale_date=instance.schedule.departure_date,
                description=f'Feribot Bileti - {instance.route}',
                agent=instance.reservation_agent,
                created_by=instance.created_by,
            )
            logger.info(f'Sales kaydı oluşturuldu - Bilet: {instance.ticket_code}')
        except Exception as e:
            logger.warning(f'Sales modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender='ferry_tickets.FerryTicket')
def send_ferry_ticket_confirmation_sms(sender, instance, created, **kwargs):
    """Bilet onaylandığında SMS gönder"""
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
        from .models import FerryTicketStatus
    except ImportError:
        return
    
    # Yeni bilet oluşturulduğunda ve onaylandığında SMS gönder
    if created and instance.status == FerryTicketStatus.CONFIRMED:
        try:
            phone = instance.customer.phone
            if not phone:
                return
            
            # İlk yolcu bilgisini al (varsayılan olarak)
            first_guest = instance.guests.first() if hasattr(instance, 'guests') else None
            passenger_name = first_guest.full_name if first_guest else (instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Yolcu')
            
            send_sms_by_template(
                template_code='ferry_ticket_confirmation',
                phone=phone,
                context={
                    'passenger_name': passenger_name,
                    'route_name': str(instance.schedule.route) if instance.schedule else 'Bilinmeyen Güzergah',
                    'departure_date': instance.schedule.departure_date.strftime('%d.%m.%Y') if instance.schedule else '',
                    'departure_time': instance.schedule.departure_time.strftime('%H:%M') if instance.schedule and instance.schedule.departure_time else '',
                    'ticket_number': instance.ticket_code,
                },
                related_module='ferry_tickets',
                related_object_id=instance.id,
                related_object_type='FerryTicket'
            )
        except Exception as e:
            logger.error(f'Feribot bileti onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
        
        # Email bildirimi gönder
        try:
            from apps.tenant_apps.settings.email_utils import send_email_by_template
            email = instance.customer.email
            if email:
                first_guest = instance.guests.first() if hasattr(instance, 'guests') else None
                passenger_name = first_guest.full_name if first_guest else (instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Yolcu')
                send_email_by_template(
                    template_code='ferry_ticket_confirmation',
                    to_email=email,
                    context={
                        'passenger_name': passenger_name,
                        'route_name': str(instance.schedule.route) if instance.schedule else 'Bilinmeyen Güzergah',
                        'departure_date': instance.schedule.departure_date.strftime('%d.%m.%Y') if instance.schedule else '',
                        'departure_time': instance.schedule.departure_time.strftime('%H:%M') if instance.schedule and instance.schedule.departure_time else '',
                        'ticket_number': instance.ticket_code,
                    },
                    to_name=passenger_name,
                    related_module='ferry_tickets',
                    related_object_id=instance.id,
                    related_object_type='FerryTicket'
                )
        except Exception as e:
            logger.error(f'Feribot bileti onayı email gönderilirken hata: {str(e)}', exc_info=True)
    
    # Bilet durumu CONFIRMED'e değiştiğinde SMS gönder
    elif not created:
        old_status = _ticket_status_cache.get(instance.pk)
        _ticket_status_cache.pop(instance.pk, None)  # Cache'i temizle
        
        if old_status == instance.status:
            return
        
        if instance.status == FerryTicketStatus.CONFIRMED and old_status != FerryTicketStatus.CONFIRMED:
            try:
                phone = instance.customer.phone
                if not phone:
                    return
                
                first_guest = instance.guests.first() if hasattr(instance, 'guests') else None
                passenger_name = first_guest.full_name if first_guest else (instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Yolcu')
                
                send_sms_by_template(
                    template_code='ferry_ticket_confirmation',
                    phone=phone,
                    context={
                        'passenger_name': passenger_name,
                        'route_name': str(instance.schedule.route) if instance.schedule else 'Bilinmeyen Güzergah',
                        'departure_date': instance.schedule.departure_date.strftime('%d.%m.%Y') if instance.schedule else '',
                        'departure_time': instance.schedule.departure_time.strftime('%H:%M') if instance.schedule and instance.schedule.departure_time else '',
                        'ticket_number': instance.ticket_code,
                    },
                    related_module='ferry_tickets',
                    related_object_id=instance.id,
                    related_object_type='FerryTicket'
                )
            except Exception as e:
                logger.error(f'Feribot bileti onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
            
            # Email bildirimi gönder
            try:
                from apps.tenant_apps.settings.email_utils import send_email_by_template
                email = instance.customer.email
                if email:
                    first_guest = instance.guests.first() if hasattr(instance, 'guests') else None
                    passenger_name = first_guest.full_name if first_guest else (instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Yolcu')
                    send_email_by_template(
                        template_code='ferry_ticket_confirmation',
                        to_email=email,
                        context={
                            'passenger_name': passenger_name,
                            'route_name': str(instance.schedule.route) if instance.schedule else 'Bilinmeyen Güzergah',
                            'departure_date': instance.schedule.departure_date.strftime('%d.%m.%Y') if instance.schedule else '',
                            'departure_time': instance.schedule.departure_time.strftime('%H:%M') if instance.schedule and instance.schedule.departure_time else '',
                            'ticket_number': instance.ticket_code,
                        },
                        to_name=passenger_name,
                        related_module='ferry_tickets',
                        related_object_id=instance.id,
                        related_object_type='FerryTicket'
                    )
            except Exception as e:
                logger.error(f'Feribot bileti onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender='ferry_tickets.FerryTicketPayment')
def send_ferry_ticket_payment_sms(sender, instance, created, **kwargs):
    """Feribot bileti ödemesi alındığında SMS gönder"""
    if not created:
        return
    
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        return
    
    try:
        ticket = instance.ticket
        phone = ticket.customer.phone
        if not phone:
            return
        
        send_sms_by_template(
            template_code='payment_confirmation',
            phone=phone,
            context={
                'guest_name': ticket.customer.get_full_name() or ticket.customer.first_name or 'Sayın Müşteri',
                'amount': str(instance.payment_amount),
                'currency': instance.currency or 'TL',
                'payment_number': instance.payment_reference or str(instance.id),
            },
            related_module='ferry_tickets',
            related_object_id=ticket.id,
            related_object_type='FerryTicket'
        )
    except Exception as e:
        logger.error(f'Feribot bileti ödeme onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
    
    # Email bildirimi gönder
    try:
        from apps.tenant_apps.settings.email_utils import send_email_by_template
        email = ticket.customer.email
        if email:
            send_email_by_template(
                template_code='payment_confirmation',
                to_email=email,
                context={
                    'guest_name': ticket.customer.get_full_name() or ticket.customer.first_name or 'Sayın Müşteri',
                    'amount': str(instance.payment_amount),
                    'currency': instance.currency or 'TL',
                    'payment_number': instance.payment_reference or str(instance.id),
                    'ticket_number': ticket.ticket_code,
                },
                to_name=ticket.customer.get_full_name() or ticket.customer.first_name,
                related_module='ferry_tickets',
                related_object_id=ticket.id,
                related_object_type='FerryTicket'
            )
    except Exception as e:
        logger.error(f'Feribot bileti ödeme onayı email gönderilirken hata: {str(e)}', exc_info=True)

