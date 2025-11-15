"""
Reception Modülü Signals
SMS bildirimleri için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from .models import Reservation, ReservationStatus, ReservationPayment


# Rezervasyon durumu değişikliği için eski durumu saklamak için
_reservation_status_cache = {}


@receiver(pre_save, sender=Reservation)
def cache_reservation_status(sender, instance, **kwargs):
    """Rezervasyon kaydedilmeden önce eski durumu sakla"""
    if instance.pk:
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            _reservation_status_cache[instance.pk] = old_instance.status
        except Reservation.DoesNotExist:
            pass


@receiver(post_save, sender=Reservation)
def create_accounting_invoice_on_reservation(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda Accounting modülüne Invoice oluştur
    """
    if not created or instance.total_amount <= 0:
        return
    
    try:
        from apps.tenant_apps.accounting.utils import create_invoice
        
        hotel = instance.hotel
        customer = instance.customer
        
        # Daha önce fatura oluşturulmuş mu kontrol et
        from apps.tenant_apps.accounting.models import Invoice
        existing_invoice = Invoice.objects.filter(
            source_module='reception',
            source_id=instance.pk,
            is_deleted=False
        ).first()
        
        if not existing_invoice:
            # Fatura satırları
            lines_data = [
                {
                    'item_name': f"Otel Rezervasyonu - {instance.room.name if instance.room else 'Oda'}",
                    'item_code': instance.reservation_code,
                    'quantity': instance.total_nights,
                    'unit_price': instance.room_rate if instance.room_rate else instance.total_amount / instance.total_nights if instance.total_nights > 0 else instance.total_amount,
                    'line_total': instance.total_amount,
                    'description': f"{instance.total_nights} gece konaklama - {instance.adult_count} yetişkin, {instance.child_count} çocuk",
                }
            ]
            
            # Fatura oluştur
            create_invoice(
                invoice_type='sales',
                customer_name=customer.get_full_name() if customer else 'Misafir',
                total_amount=instance.total_amount,
                source_module='reception',
                source_id=instance.pk,
                source_reference=f"Rezervasyon: {instance.reservation_code}",
                invoice_date=instance.created_at.date() if instance.created_at else None,
                currency=instance.currency,
                created_by=instance.created_by,
                lines_data=lines_data,
                hotel=hotel,
                customer_email=customer.email if customer else '',
                customer_phone=customer.phone if customer else '',
                customer_address=customer.address if customer and hasattr(customer, 'address') else '',
                description=f"Otel rezervasyon faturası - {instance.reservation_code}",
            )
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=Reservation)
def send_reservation_sms_notification(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda veya durumu değiştiğinde SMS gönder
    """
    # SMS göndermek için settings modülünü kontrol et
    try:
        from apps.tenant_apps.settings.utils import send_sms_by_template
    except ImportError:
        # Settings modülü yoksa veya import edilemiyorsa sessizce geç
        return
    
    # Yeni rezervasyon oluşturulduğunda ve onaylandığında SMS gönder
    if created and instance.status == ReservationStatus.CONFIRMED:
        try:
            # Müşteri telefon numarasını al
            phone = instance.customer.phone
            if not phone:
                return
            
            # Rezervasyon onayı SMS'i gönder
            send_sms_by_template(
                template_code='reservation_confirmation',
                phone=phone,
                context={
                    'guest_name': instance.customer.get_full_name() or instance.customer.first_name or 'Sayın Müşteri',
                    'check_in_date': instance.check_in_date.strftime('%d.%m.%Y'),
                    'reservation_number': instance.reservation_code,
                },
                hotel=instance.hotel,
                related_module='reception',
                related_object_id=instance.id,
                related_object_type='Reservation'
            )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f'Rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
        
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
                        'hotel_name': instance.hotel.name if instance.hotel else '',
                    },
                    hotel=instance.hotel,
                    to_name=instance.customer.get_full_name() or instance.customer.first_name,
                    related_module='reception',
                    related_object_id=instance.id,
                    related_object_type='Reservation'
                )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f'Rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)
    
    # Rezervasyon durumu CONFIRMED'e değiştiğinde SMS gönder
    elif not created:
        old_status = _reservation_status_cache.get(instance.pk)
        _reservation_status_cache.pop(instance.pk, None)  # Cache'i temizle
        
        # Durum değişmemişse çık
        if old_status == instance.status:
            return
        
        # Durum CONFIRMED'e değiştiyse SMS gönder
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
                    hotel=instance.hotel,
                    related_module='reception',
                    related_object_id=instance.id,
                    related_object_type='Reservation'
                )
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Rezervasyon onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
            
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
                            'hotel_name': instance.hotel.name if instance.hotel else '',
                        },
                        to_name=instance.customer.get_full_name() or instance.customer.first_name,
                        related_module='reception',
                        related_object_id=instance.id,
                        related_object_type='Reservation'
                    )
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Rezervasyon onayı email gönderilirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=ReservationPayment)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    Rezervasyon ödemesi oluşturulduğunda Finance modülüne CashTransaction oluştur
    """
    if not created or instance.cash_transaction:
        return
    
    try:
        from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
        
        reservation = instance.reservation
        hotel = reservation.hotel
        
        # Varsayılan kasa hesabını bul (otel bazlı)
        account = get_default_cash_account(currency=instance.currency, hotel=hotel)
        if not account:
            # Varsayılan hesap yoksa ilk aktif hesabı kullan
            from apps.tenant_apps.finance.models import CashAccount
            if hotel:
                account = CashAccount.objects.filter(
                    hotel=hotel,
                    is_active=True,
                    is_deleted=False,
                    currency=instance.currency
                ).first()
            if not account:
                account = CashAccount.objects.filter(
                    hotel__isnull=True,
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
                'transfer': 'bank_transfer',
                'check': 'check',
                'other': 'other',
            }
            finance_payment_method = payment_method_map.get(instance.payment_method, 'cash')
            
            # Kasa işlemi oluştur
            cash_transaction = create_cash_transaction(
                account_id=account.pk,
                transaction_type='income',
                amount=instance.payment_amount,
                source_module='reception',
                source_id=instance.reservation.pk,
                source_reference=f"Rezervasyon: {instance.reservation.reservation_code}",
                description=f"Rezervasyon ödemesi - {instance.reservation.reservation_code}",
                payment_method=finance_payment_method,
                currency=instance.currency,
                created_by=instance.created_by,
                status='completed',
                hotel=hotel,
            )
            
            # ReservationPayment'a bağla
            instance.cash_transaction = cash_transaction
            instance.save(update_fields=['cash_transaction'])
            
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=ReservationPayment)
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """
    Rezervasyon ödemesi oluşturulduğunda Accounting modülüne Payment kaydı oluştur
    """
    if not created or instance.accounting_payment:
        return
    
    try:
        from apps.tenant_apps.accounting.utils import create_payment
        from apps.tenant_apps.accounting.models import Invoice
        
        reservation = instance.reservation
        hotel = reservation.hotel
        
        # İlgili faturayı bul (varsa)
        invoice = Invoice.objects.filter(
            source_module='reception',
            source_id=reservation.pk,
            is_deleted=False
        ).first()
        
        # Kasa hesabını bul (Finance modülünden)
        cash_account_id = instance.cash_transaction.pk if instance.cash_transaction else None
        
        # Ödeme yöntemini eşleştir
        payment_method_map = {
            'cash': 'cash',
            'credit_card': 'credit_card',
            'debit_card': 'credit_card',
            'transfer': 'bank_transfer',
            'check': 'check',
            'other': 'other',
        }
        accounting_payment_method = payment_method_map.get(instance.payment_method, 'cash')
        
        # Ödeme kaydı oluştur
        accounting_payment = create_payment(
            amount=instance.payment_amount,
            invoice_id=invoice.pk if invoice else None,
            source_module='reception',
            source_id=reservation.pk,
            source_reference=f"Rezervasyon: {instance.reservation.reservation_code}",
            payment_date=instance.payment_date,
            currency=instance.currency,
            payment_method=accounting_payment_method,
            cash_account_id=cash_account_id,
            created_by=instance.created_by,
            hotel=hotel,
            auto_complete=True,  # Otomatik tamamla
            description=f"Rezervasyon ödemesi - {instance.reservation.reservation_code}",
        )
        
        # ReservationPayment'a bağla
        instance.accounting_payment = accounting_payment
        instance.save(update_fields=['accounting_payment'])
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=ReservationPayment)
def send_payment_confirmation_sms(sender, instance, created, **kwargs):
    """
    Ödeme alındığında SMS gönder
    """
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
        
        # Ödeme onayı SMS'i gönder
        send_sms_by_template(
            template_code='payment_confirmation',
            phone=phone,
            context={
                'guest_name': reservation.customer.get_full_name() or reservation.customer.first_name or 'Sayın Müşteri',
                'amount': str(instance.payment_amount),
                'currency': instance.currency or 'TL',
                'payment_number': instance.payment_reference or str(instance.id),
            },
            hotel=reservation.hotel,
            related_module='reception',
            related_object_id=reservation.id,
            related_object_type='Reservation'
        )
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Ödeme onayı SMS gönderilirken hata: {str(e)}', exc_info=True)
    
    # Email bildirimi gönder
    try:
        from apps.tenant_apps.settings.email_utils import send_email_by_template
        reservation = instance.reservation
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
                hotel=reservation.hotel,
                to_name=reservation.customer.get_full_name() or reservation.customer.first_name,
                related_module='reception',
                related_object_id=reservation.id,
                related_object_type='Reservation'
            )
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Ödeme onayı email gönderilirken hata: {str(e)}', exc_info=True)
