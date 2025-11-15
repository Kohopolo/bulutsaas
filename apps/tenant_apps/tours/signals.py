"""
Tur Modülü Signals
Finance, Accounting ve Refunds modülleriyle entegrasyon
SMS bildirimleri için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.db import transaction
from django.utils import timezone
from .models import TourPayment, TourReservation
import logging

logger = logging.getLogger(__name__)

# Rezervasyon durumu değişikliği için eski durumu saklamak için
_tour_reservation_status_cache = {}


@receiver(pre_save, sender=TourReservation)
def cache_tour_reservation_status(sender, instance, **kwargs):
    """Rezervasyon kaydedilmeden önce eski durumu sakla"""
    if instance.pk:
        try:
            old_instance = TourReservation.objects.get(pk=instance.pk)
            _tour_reservation_status_cache[instance.pk] = old_instance.status
        except TourReservation.DoesNotExist:
            pass


@receiver(post_save, sender=TourReservation)
def send_tour_reservation_sms_notification(sender, instance, created, **kwargs):
    """Tur rezervasyonu onaylandığında SMS gönder"""
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        return
    
    # Yeni rezervasyon oluşturulduğunda ve onaylandığında SMS gönder
    if created and instance.status == 'confirmed':
        try:
            phone = instance.customer_phone
            if not phone:
                return
            
            customer_name = f"{instance.customer_name} {instance.customer_surname}".strip() or 'Sayın Müşteri'
            
            send_sms_by_template(
                template_code='reservation_confirmation',
                phone=phone,
                context={
                    'guest_name': customer_name,
                    'check_in_date': instance.tour_date.date.strftime('%d.%m.%Y') if instance.tour_date else '',
                    'reservation_number': instance.reservation_code,
                },
                related_module='tours',
                related_object_id=instance.id,
                related_object_type='TourReservation'
            )
        except Exception as e:
            logger.error(f'Tur rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
            
            # Email bildirimi gönder
            try:
                from apps.tenant_apps.settings.email_utils import send_email_by_template
                email = instance.customer_email
                if email:
                    customer_name = f"{instance.customer_name} {instance.customer_surname}".strip() or 'Sayın Müşteri'
                    send_email_by_template(
                        template_code='reservation_confirmation',
                        to_email=email,
                        context={
                            'guest_name': customer_name,
                            'check_in_date': instance.tour_date.date.strftime('%d.%m.%Y') if instance.tour_date else '',
                            'reservation_number': instance.reservation_code,
                            'tour_name': instance.tour_date.tour.name if instance.tour_date and instance.tour_date.tour else '',
                        },
                        to_name=customer_name,
                        related_module='tours',
                        related_object_id=instance.id,
                        related_object_type='TourReservation'
                    )
            except Exception as e:
                logger.error(f'Tur rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)
    
    # Rezervasyon durumu confirmed'e değiştiğinde SMS gönder
    elif not created:
        old_status = _tour_reservation_status_cache.get(instance.pk)
        _tour_reservation_status_cache.pop(instance.pk, None)
        
        if old_status == instance.status:
            return
        
        if instance.status == 'confirmed' and old_status != 'confirmed':
            try:
                phone = instance.customer_phone
                if not phone:
                    return
                
                customer_name = f"{instance.customer_name} {instance.customer_surname}".strip() or 'Sayın Müşteri'
                
                send_sms_by_template(
                    template_code='reservation_confirmation',
                    phone=phone,
                    context={
                        'guest_name': customer_name,
                        'check_in_date': instance.tour_date.date.strftime('%d.%m.%Y') if instance.tour_date else '',
                        'reservation_number': instance.reservation_code,
                    },
                    related_module='tours',
                    related_object_id=instance.id,
                    related_object_type='TourReservation'
                )
            except Exception as e:
                logger.error(f'Tur rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
            
            # Email bildirimi gönder
            try:
                from apps.tenant_apps.settings.email_utils import send_email_by_template
                email = instance.customer_email
                if email:
                    customer_name = f"{instance.customer_name} {instance.customer_surname}".strip() or 'Sayın Müşteri'
                    send_email_by_template(
                        template_code='reservation_confirmation',
                        to_email=email,
                        context={
                            'guest_name': customer_name,
                            'check_in_date': instance.tour_date.date.strftime('%d.%m.%Y') if instance.tour_date else '',
                            'reservation_number': instance.reservation_code,
                            'tour_name': instance.tour_date.tour.name if instance.tour_date and instance.tour_date.tour else '',
                        },
                        to_name=customer_name,
                        related_module='tours',
                        related_object_id=instance.id,
                        related_object_type='TourReservation'
                    )
            except Exception as e:
                logger.error(f'Tur rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=TourPayment)
def send_tour_payment_confirmation_sms(sender, instance, created, **kwargs):
    """Tur ödemesi alındığında SMS gönder"""
    if not created or instance.status != 'completed':
        return
    
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        return
    
    try:
        reservation = instance.reservation
        phone = reservation.customer_phone
        if not phone:
            return
        
        customer_name = f"{reservation.customer_name} {reservation.customer_surname}".strip() or 'Sayın Müşteri'
        
        send_sms_by_template(
            template_code='payment_confirmation',
            phone=phone,
            context={
                'guest_name': customer_name,
                'amount': str(instance.amount),
                'currency': instance.currency or 'TL',
                'payment_number': instance.transaction_id or str(instance.id),
            },
                    related_module='tours',
                    related_object_id=reservation.id,
                    related_object_type='TourReservation'
                )
    except Exception as e:
        logger.error(f'Tur ödeme onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
    
    # Email bildirimi gönder
    try:
        from apps.tenant_apps.settings.email_utils import send_email_by_template
        email = reservation.customer_email
        if email:
            customer_name = f"{reservation.customer_name} {reservation.customer_surname}".strip() or 'Sayın Müşteri'
            send_email_by_template(
                template_code='payment_confirmation',
                to_email=email,
                context={
                    'guest_name': customer_name,
                    'amount': str(instance.amount),
                    'currency': instance.currency or 'TL',
                    'payment_number': instance.transaction_id or str(instance.id),
                    'reservation_number': reservation.reservation_code,
                },
                to_name=customer_name,
                related_module='tours',
                related_object_id=reservation.id,
                related_object_type='TourReservation'
            )
    except Exception as e:
        logger.error(f'Tur ödeme onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=TourPayment)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    Tur ödemesi yapıldığında Finance modülüne kasa işlemi oluştur
    """
    if created and instance.status == 'completed':
        try:
            from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
            
            # Varsayılan kasa hesabını bul
            account = get_default_cash_account(currency=instance.currency, hotel=None)
            if not account:
                # Varsayılan hesap yoksa ilk aktif hesabı kullan
                from apps.tenant_apps.finance.models import CashAccount
                account = CashAccount.objects.filter(
                    is_active=True,
                    is_deleted=False,
                    currency=instance.currency
                ).first()
            
            if account:
                # Ödeme yöntemini eşleştir
                payment_method_map = {
                    'cash': 'cash',
                    'bank_transfer': 'bank_transfer',
                    'credit_card': 'credit_card',
                    'iyzico': 'credit_card',
                    'paytr': 'credit_card',
                    'nestpay': 'credit_card',
                    'garanti': 'credit_card',
                    'akbank': 'credit_card',
                }
                finance_payment_method = payment_method_map.get(instance.payment_method, 'cash')
                
                # Kasa işlemi oluştur
                create_cash_transaction(
                    account_id=account.pk,
                    transaction_type='income',
                    amount=instance.amount,
                    source_module='tours',
                    source_id=instance.reservation.pk,
                    source_reference=f"Tur Rezervasyon: {instance.reservation.reservation_code}",
                    description=f"Tur rezervasyon ödemesi - {instance.reservation.tour.name}",
                    payment_method=finance_payment_method,
                    currency=instance.currency,
                    created_by=instance.reservation.sales_person.user if instance.reservation.sales_person else None,
                    status='completed',
                    hotel=None,  # Tours modülünde otel bilgisi yok
                )
        except Exception as e:
            # Finance modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=TourPayment)
def update_reservation_status_on_payment(sender, instance, created, **kwargs):
    """
    Tur ödemesi yapıldığında rezervasyonun total_paid değerini güncelle ve durumu kontrol et
    """
    if created and instance.status == 'completed':
        try:
            reservation = instance.reservation
            reservation.update_total_paid()
            
            # Tam ödendiyse durumu CONFIRMED yap
            if reservation.is_paid() and reservation.status != 'confirmed':
                reservation.status = 'confirmed'
                reservation.save(update_fields=['status'])
                logger.info(f'Rezervasyon tamamen ödendi, durum CONFIRMED olarak güncellendi - Rezervasyon: {reservation.reservation_code}')
        except Exception as e:
            logger.error(f'Rezervasyon durumu güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=TourPayment)
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """
    Tur ödemesi yapıldığında Accounting modülüne ödeme kaydı oluştur
    """
    if created and instance.status == 'completed':
        try:
            from apps.tenant_apps.accounting.utils import create_payment
            
            reservation = instance.reservation
            
            # İlgili faturayı bul (varsa)
            invoice_id = None
            try:
                from apps.tenant_apps.accounting.models import Invoice
                invoice = Invoice.objects.filter(
                    source_module='tours',
                    source_id=reservation.pk,
                    is_deleted=False
                ).first()
                if invoice:
                    invoice_id = invoice.pk
            except:
                pass
            
            # Accounting ödeme kaydı oluştur
            create_payment(
                amount=instance.amount,
                invoice_id=invoice_id,
                source_module='tours',
                source_id=reservation.pk,
                source_reference=f"Tur Rezervasyon: {reservation.reservation_code}",
                payment_date=instance.payment_date if hasattr(instance, 'payment_date') else timezone.now(),
                currency=instance.currency,
                payment_method=instance.payment_method,
                created_by=reservation.sales_person.user if reservation.sales_person else None,
                hotel=None,  # Tours modülünde otel bilgisi yok
                description=f"Tur rezervasyon ödemesi - {reservation.tour.name}",
                auto_complete=True,
            )
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(pre_save, sender=TourReservation)
def create_refund_request_on_cancellation(sender, instance, **kwargs):
    """
    Tur rezervasyonu iptal edildiğinde Refunds modülüne iade talebi oluştur
    """
    if instance.pk:
        try:
            old_instance = TourReservation.objects.get(pk=instance.pk)
            
            # Rezervasyon iptal edildiyse ve ödeme yapıldıysa
            if old_instance.status != 'cancelled' and instance.status == 'cancelled':
                if old_instance.payment_status in ['paid', 'partial']:
                    try:
                        from apps.tenant_apps.refunds.utils import create_refund_request
                        
                        # İade talebi oluştur
                        create_refund_request(
                            source_module='tours',
                            source_id=instance.pk,
                            source_reference=f"Tur Rezervasyon: {instance.reservation_code}",
                            customer_name=f"{instance.customer_name} {instance.customer_surname}",
                            customer_email=instance.customer_email,
                            customer_phone=instance.customer_phone,
                            original_amount=instance.total_amount,
                            original_payment_method='',  # TourPayment'lerden alınabilir
                            original_payment_date=instance.created_at.date() if instance.created_at else timezone.now().date(),
                            reason=f'Tur rezervasyon iptali - {instance.tour.name}',
                            hotel=getattr(instance, 'hotel', None),  # Otel bilgisi eklendi (eğer varsa)
                            created_by=instance.sales_person.user if instance.sales_person else None,
                        )
                    except Exception as e:
                        # Refunds modülü yoksa veya hata oluşursa sessizce geç
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.warning(f'Refunds modülü entegrasyonu başarısız: {str(e)}')
        except TourReservation.DoesNotExist:
            pass


@receiver(post_save, sender=TourReservation)
def create_accounting_invoice_on_payment(sender, instance, created, **kwargs):
    """
    Tur rezervasyon ödemesi tamamlandığında Accounting modülüne fatura oluştur
    """
    # Sadece ödeme tamamlandığında ve yeni fatura oluşturulmamışsa
    if instance.payment_status == 'paid' and not created:
        try:
            # Daha önce fatura oluşturulmuş mu kontrol et
            from apps.tenant_apps.accounting.models import Invoice
            existing_invoice = Invoice.objects.filter(
                source_module='tours',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if not existing_invoice:
                from apps.tenant_apps.accounting.utils import create_invoice
                
                # Fatura satırları
                lines_data = [
                    {
                        'item_name': f"Tur Rezervasyonu - {instance.tour.name}",
                        'item_code': instance.reservation_code,
                        'quantity': 1,
                        'unit_price': instance.total_amount,
                        'line_total': instance.total_amount,
                        'description': f"{instance.adult_count} yetişkin, {instance.child_count} çocuk",
                    }
                ]
                
                # Fatura oluştur
                create_invoice(
                    invoice_type='sales',
                    customer_name=f"{instance.customer_name} {instance.customer_surname}",
                    total_amount=instance.total_amount,
                    source_module='tours',
                    source_id=instance.pk,
                    source_reference=f"Tur Rezervasyon: {instance.reservation_code}",
                    invoice_date=timezone.now().date(),
                    currency=instance.currency,
                    created_by=instance.sales_person.user if instance.sales_person else None,
                    lines_data=lines_data,
                    hotel=None,  # Tours modülünde otel bilgisi yok
                    customer_email=instance.customer_email,
                    customer_phone=instance.customer_phone,
                    customer_address=instance.customer_address,
                    description=f"Tur rezervasyon faturası - {instance.tour.name}",
                    status='sent',
                )
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')

