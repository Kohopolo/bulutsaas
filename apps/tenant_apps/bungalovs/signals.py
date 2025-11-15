"""
Bungalov Modülü Signals
Otomatik işlemler için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
from datetime import date
import logging

from .models import (
    BungalovReservation, BungalovReservationPayment,
    BungalovCleaning, BungalovMaintenance,
    ReservationStatus
)

logger = logging.getLogger(__name__)

# Rezervasyon durumu değişikliği için eski durumu saklamak için
_bungalov_reservation_status_cache = {}


@receiver(post_save, sender=BungalovReservationPayment)
def update_reservation_total_paid(sender, instance, created, **kwargs):
    """
    Ödeme kaydedildiğinde rezervasyonun total_paid değerini güncelle ve durumu kontrol et
    """
    try:
        if instance.reservation:
            reservation = instance.reservation
            reservation.update_total_paid()
            
            # Tam ödendiyse durumu CONFIRMED yap
            if reservation.is_paid() and reservation.status != ReservationStatus.CONFIRMED:
                reservation.status = ReservationStatus.CONFIRMED
                reservation.save(update_fields=['status'])
                logger.info(f'Rezervasyon tamamen ödendi, durum CONFIRMED olarak güncellendi - Rezervasyon: {reservation.reservation_code}')
    except Exception as e:
        logger.error(f'Rezervasyon total_paid güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovReservationPayment)
def send_bungalov_payment_confirmation_sms(sender, instance, created, **kwargs):
    """Bungalov ödemesi alındığında SMS gönder"""
    if not created:
        return
    
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        return
    
    try:
        reservation = instance.reservation
        phone = reservation.customer.phone
        if not phone:
            return
        
        send_sms_by_template(
            template_code='payment_confirmation',
            phone=phone,
            context={
                'guest_name': reservation.customer.get_full_name() or reservation.customer.first_name or 'Sayın Müşteri',
                'amount': str(instance.payment_amount),
                'currency': instance.currency or 'TL',
                'payment_number': instance.payment_reference or str(instance.id),
            },
            related_module='bungalovs',
            related_object_id=reservation.id,
            related_object_type='BungalovReservation',
            hotel=None,  # Bungalovs modülünde otel bilgisi yok
        )
    except Exception as e:
        logger.error(f'Bungalov ödeme onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
    
    # Email bildirimi gönder
    try:
        from apps.tenant_apps.settings.email_utils import send_email_by_template
        email = reservation.customer.email
        if email:
            send_email_by_template(
                template_code='payment_confirmation',
                to_email=email,
                context={
                    'guest_name': reservation.customer.get_full_name() or reservation.customer.first_name or 'Sayın Müşteri',
                    'amount': str(instance.payment_amount),
                    'currency': instance.currency or 'TL',
                    'payment_number': instance.payment_reference or str(instance.id),
                    'reservation_number': reservation.reservation_code,
                },
                to_name=reservation.customer.get_full_name() or reservation.customer.first_name,
                related_module='bungalovs',
                related_object_id=reservation.id,
                related_object_type='BungalovReservation',
                hotel=None,  # Bungalovs modülünde otel bilgisi yok
            )
    except Exception as e:
        logger.error(f'Bungalov ödeme onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovReservationPayment)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    Bungalov ödemesi yapıldığında Finance modülüne kasa işlemi oluştur
    """
    if created:
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
                    'credit_card': 'credit_card',
                    'debit_card': 'credit_card',
                    'bank_transfer': 'bank_transfer',
                    'check': 'check',
                    'iyzico': 'credit_card',
                    'paytr': 'credit_card',
                    'other': 'cash',
                }
                finance_payment_method = payment_method_map.get(instance.payment_method, 'cash')
                
                reservation = instance.reservation
                
                # Kasa işlemi oluştur
                create_cash_transaction(
                    account_id=account.pk,
                    transaction_type='income',
                    amount=instance.payment_amount,
                    source_module='bungalovs',
                    source_id=reservation.pk,
                    source_reference=f"Bungalov Rezervasyon: {reservation.reservation_code}",
                    description=f"Bungalov rezervasyon ödemesi - {reservation.bungalov.name if reservation.bungalov else ''}",
                    payment_method=finance_payment_method,
                    currency=instance.currency,
                    created_by=instance.created_by,
                    status='completed',
                    hotel=None,  # Bungalovs modülünde otel bilgisi yok
                )
        except Exception as e:
            # Finance modülü yoksa veya hata oluşursa sessizce geç
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=BungalovReservationPayment)
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """
    Bungalov ödemesi yapıldığında Accounting modülüne ödeme kaydı oluştur
    """
    if created:
        try:
            from apps.tenant_apps.accounting.utils import create_payment
            
            reservation = instance.reservation
            
            # İlgili faturayı bul (varsa)
            invoice_id = None
            try:
                from apps.tenant_apps.accounting.models import Invoice
                invoice = Invoice.objects.filter(
                    source_module='bungalovs',
                    source_id=reservation.pk,
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
                source_module='bungalovs',
                source_id=reservation.pk,
                source_reference=f"Bungalov Rezervasyon: {reservation.reservation_code}",
                payment_date=instance.payment_date if hasattr(instance, 'payment_date') else timezone.now(),
                currency=instance.currency,
                payment_method=instance.payment_method,
                created_by=instance.created_by,
                hotel=None,  # Bungalovs modülünde otel bilgisi yok
                description=f"Bungalov rezervasyon ödemesi - {reservation.bungalov.name if reservation.bungalov else ''}",
                auto_complete=True,
            )
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(pre_save, sender=BungalovReservation)
def cache_bungalov_reservation_status(sender, instance, **kwargs):
    """Rezervasyon kaydedilmeden önce eski durumu sakla"""
    if instance.pk:
        try:
            old_instance = BungalovReservation.objects.get(pk=instance.pk)
            _bungalov_reservation_status_cache[instance.pk] = old_instance.status
        except BungalovReservation.DoesNotExist:
            pass


@receiver(post_save, sender=BungalovReservation)
def send_bungalov_reservation_sms_notification(sender, instance, created, **kwargs):
    """Bungalov rezervasyonu onaylandığında SMS gönder"""
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        return
    
    # Yeni rezervasyon oluşturulduğunda ve onaylandığında SMS gönder
    if created and instance.status == ReservationStatus.CONFIRMED:
        try:
            phone = instance.customer.phone
            if not phone:
                return
            
            send_sms_by_template(
                template_code='reservation_confirmation',
                phone=phone,
                context={
                    'guest_name': instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Müşteri',
                    'check_in_date': instance.check_in_date.strftime('%d.%m.%Y'),
                    'reservation_number': instance.reservation_code,
                },
                related_module='bungalovs',
                related_object_id=instance.id,
                related_object_type='BungalovReservation'
            )
        except Exception as e:
            logger.error(f'Bungalov rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
        
        # Email bildirimi gönder
        try:
            from apps.tenant_apps.settings.email_utils import send_email_by_template
            email = instance.customer.email
            if email:
                send_email_by_template(
                    template_code='reservation_confirmation',
                    to_email=email,
                    context={
                        'guest_name': instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Müşteri',
                        'check_in_date': instance.check_in_date.strftime('%d.%m.%Y'),
                        'check_out_date': instance.check_out_date.strftime('%d.%m.%Y') if instance.check_out_date else '',
                        'reservation_number': instance.reservation_code,
                        'bungalov_name': instance.bungalov.name if instance.bungalov else '',
                    },
                    to_name=instance.customer.get_full_name() or instance.customer.first_name,
                    related_module='bungalovs',
                    related_object_id=instance.id,
                    related_object_type='BungalovReservation'
                )
        except Exception as e:
            logger.error(f'Bungalov rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)
    
    # Rezervasyon durumu CONFIRMED'e değiştiğinde SMS gönder
    elif not created:
        old_status = _bungalov_reservation_status_cache.get(instance.pk)
        _bungalov_reservation_status_cache.pop(instance.pk, None)
        
        if old_status == instance.status:
            return
        
        if instance.status == ReservationStatus.CONFIRMED and old_status != ReservationStatus.CONFIRMED:
            try:
                phone = instance.customer.phone
                if not phone:
                    return
                
                send_sms_by_template(
                    template_code='reservation_confirmation',
                    phone=phone,
                    context={
                        'guest_name': instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Müşteri',
                        'check_in_date': instance.check_in_date.strftime('%d.%m.%Y'),
                        'reservation_number': instance.reservation_code,
                    },
                    related_module='bungalovs',
                    related_object_id=instance.id,
                    related_object_type='BungalovReservation'
                )
            except Exception as e:
                logger.error(f'Bungalov rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
            
            # Email bildirimi gönder
            try:
                from apps.tenant_apps.settings.email_utils import send_email_by_template
                email = instance.customer.email
                if email:
                    send_email_by_template(
                        template_code='reservation_confirmation',
                        to_email=email,
                        context={
                            'guest_name': instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Müşteri',
                            'check_in_date': instance.check_in_date.strftime('%d.%m.%Y'),
                            'check_out_date': instance.check_out_date.strftime('%d.%m.%Y') if instance.check_out_date else '',
                            'reservation_number': instance.reservation_code,
                            'bungalov_name': instance.bungalov.name if instance.bungalov else '',
                        },
                        to_name=instance.customer.get_full_name() or instance.customer.first_name,
                        related_module='bungalovs',
                        related_object_id=instance.id,
                        related_object_type='BungalovReservation'
                    )
            except Exception as e:
                logger.error(f'Bungalov rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovReservation)
def update_bungalov_status_on_reservation(sender, instance, created, **kwargs):
    """
    Rezervasyon durumu değiştiğinde bungalov durumunu güncelle
    """
    try:
        if instance.bungalov:
            today = timezone.now().date()
            
            # Check-in yapıldıysa bungalov'u dolu yap
            if instance.is_checked_in and instance.status == ReservationStatus.CHECKED_IN:
                if instance.check_in_date <= today <= instance.check_out_date:
                    instance.bungalov.status = 'occupied'
                    instance.bungalov.save()
            
            # Check-out yapıldıysa bungalov'u temizlikte yap
            elif instance.is_checked_out and instance.status == ReservationStatus.CHECKED_OUT:
                instance.bungalov.status = 'cleaning'
                instance.bungalov.save()
            
            # Rezervasyon iptal edildiyse bungalov'u müsait yap
            elif instance.is_cancelled:
                if instance.bungalov.status == 'occupied':
                    instance.bungalov.status = 'available'
                    instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovCleaning)
def update_bungalov_status_on_cleaning(sender, instance, created, **kwargs):
    """
    Temizlik tamamlandığında bungalov durumunu güncelle
    """
    try:
        if instance.status == 'clean' and instance.bungalov:
            instance.bungalov.status = 'available'
            instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovMaintenance)
def update_bungalov_status_on_maintenance(sender, instance, created, **kwargs):
    """
    Bakım durumu değiştiğinde bungalov durumunu güncelle
    """
    try:
        if instance.bungalov:
            if instance.status == 'in_progress':
                instance.bungalov.status = 'maintenance'
                instance.bungalov.save()
            elif instance.status == 'completed':
                # Bakım tamamlandıysa ve başka sorun yoksa müsait yap
                if instance.bungalov.status == 'maintenance':
                    instance.bungalov.status = 'available'
                    instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)

