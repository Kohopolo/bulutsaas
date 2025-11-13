"""
Reception Signals
Rezervasyon işlemlerinde otomatik entegrasyonlar
Finance ve Accounting modülleriyle entegrasyon
WhatsApp ve SMS bildirimleri
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.db import transaction
from django.utils import timezone
from decimal import Decimal
from .models import Reservation, ReservationPayment, ReservationVoucher


@receiver(post_save, sender=ReservationPayment)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    Rezervasyon ödemesi yapıldığında Finance modülüne kasa işlemi oluştur
    """
    if created:  # ReservationPayment'te status field'ı yok, tüm ödemeler için oluştur
        try:
            from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
            
            # Varsayılan kasa hesabını bul
            account = get_default_cash_account(currency=instance.currency)
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
                )
                
                # ReservationPayment'e bağla
                instance.cash_transaction = cash_transaction
                instance.save(update_fields=['cash_transaction'])
        except Exception as e:
            # Finance modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=Reservation)
def create_accounting_invoice_on_reservation(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda Accounting modülüne fatura oluştur
    Sadece comp olmayan rezervasyonlar için
    """
    if created and not instance.is_comp and instance.total_amount > 0:
        try:
            # Daha önce fatura oluşturulmuş mu kontrol et
            from apps.tenant_apps.accounting.models import Invoice
            existing_invoice = Invoice.objects.filter(
                source_module='reception',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if not existing_invoice:
                from apps.tenant_apps.accounting.utils import create_invoice
                
                # Müşteri bilgileri
                customer = instance.customer
                customer_name = f"{customer.first_name} {customer.last_name}" if customer else "Misafir"
                
                # Fatura satırları
                lines_data = [
                    {
                        'item_name': f"Oda Rezervasyonu - {instance.room.name if instance.room else 'Oda'}" if instance.room else "Oda Rezervasyonu",
                        'item_code': instance.reservation_code,
                        'quantity': instance.total_nights,
                        'unit_price': instance.room_rate,
                        'line_total': instance.total_amount,
                        'description': f"{instance.check_in_date} - {instance.check_out_date} ({instance.total_nights} gece)",
                    }
                ]
                
                # Fatura oluştur
                create_invoice(
                    invoice_type='sales',
                    customer_name=customer_name,
                    total_amount=instance.total_amount,
                    source_module='reception',
                    source_id=instance.pk,
                    source_reference=f"Rezervasyon: {instance.reservation_code}",
                    invoice_date=instance.check_in_date or timezone.now().date(),
                    currency=instance.currency,
                    created_by=instance.created_by,
                    lines_data=lines_data,
                    customer_email=customer.email if customer else '',
                    customer_phone=customer.phone if customer else '',
                    customer_address=customer.address if customer else '',
                    description=f"Oda rezervasyon faturası - {instance.reservation_code}",
                    status='draft',  # Ödeme yapıldığında 'sent' olacak
                    tax_rate=instance.tax_amount / instance.total_amount * 100 if instance.total_amount > 0 else 0,
                    discount_amount=instance.discount_amount,
                )
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=ReservationPayment)
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """
    Rezervasyon ödemesi yapıldığında Accounting modülüne ödeme kaydı oluştur
    """
    if created:  # ReservationPayment'te status field'ı yok, tüm ödemeler için oluştur
        try:
            # İlgili faturayı bul
            from apps.tenant_apps.accounting.models import Invoice, Payment
            invoice = Invoice.objects.filter(
                source_module='reception',
                source_id=instance.reservation.pk,
                is_deleted=False
            ).first()
            
            # Kasa hesabını bul (Finance modülünden)
            cash_account_id = None
            try:
                from apps.tenant_apps.finance.utils import get_default_cash_account
                account = get_default_cash_account(currency=instance.currency)
                if account:
                    cash_account_id = account.pk
            except:
                pass
            
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
            from apps.tenant_apps.accounting.utils import create_payment
            accounting_payment = create_payment(
                amount=instance.payment_amount,
                invoice_id=invoice.pk if invoice else None,
                source_module='reception',
                source_id=instance.reservation.pk,
                source_reference=f"Rezervasyon Ödemesi: {instance.reservation.reservation_code}",
                payment_date=instance.payment_date,
                currency=instance.currency,
                payment_method=accounting_payment_method,
                cash_account_id=cash_account_id,
                created_by=instance.created_by,
                auto_complete=True,  # Otomatik tamamla
                description=f"Rezervasyon ödemesi - {instance.reservation.reservation_code}",
            )
            
            # ReservationPayment'e bağla
            instance.accounting_payment = accounting_payment
            instance.save(update_fields=['accounting_payment'])
            
            # Fatura durumunu güncelle
            if invoice:
                # Toplam ödenen tutarı kontrol et
                total_paid = instance.reservation.total_paid
                if total_paid >= invoice.total_amount:
                    invoice.status = 'paid'
                    invoice.save()
                elif total_paid > 0:
                    invoice.status = 'partial'
                    invoice.save()
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü ödeme entegrasyonu başarısız: {str(e)}')


@receiver(pre_save, sender=Reservation)
def create_refund_request_on_cancellation(sender, instance, **kwargs):
    """
    Rezervasyon iptal edildiğinde Refunds modülüne iade talebi oluştur
    """
    if instance.pk:
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            
            # Rezervasyon iptal edildiyse ve ödeme yapıldıysa
            if not old_instance.is_cancelled and instance.is_cancelled:
                if instance.total_paid > 0:
                    try:
                        from apps.tenant_apps.refunds.utils import create_refund_request
                        
                        customer = instance.customer
                        customer_name = f"{customer.first_name} {customer.last_name}" if customer else "Misafir"
                        
                        # İade talebi oluştur
                        create_refund_request(
                            source_module='reception',
                            source_id=instance.pk,
                            source_reference=f"Rezervasyon: {instance.reservation_code}",
                            customer_name=customer_name,
                            customer_email=customer.email if customer else '',
                            customer_phone=customer.phone if customer else '',
                            original_amount=instance.total_paid,
                            original_payment_method='',  # ReservationPayment'lerden alınabilir
                            original_payment_date=instance.created_at.date() if instance.created_at else timezone.now().date(),
                            reason=f'Rezervasyon iptali - {instance.reservation_code}',
                            created_by=instance.updated_by,
                        )
                    except Exception as e:
                        # Refunds modülü yoksa veya hata oluşursa sessizce geç
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.warning(f'Refunds modülü entegrasyonu başarısız: {str(e)}')
        except Reservation.DoesNotExist:
            pass


@receiver(post_save, sender=Reservation)
def update_accounting_invoice_on_reservation_update(sender, instance, created, **kwargs):
    """
    Rezervasyon güncellendiğinde Accounting modülündeki faturayı güncelle
    """
    if not created:
        try:
            from apps.tenant_apps.accounting.models import Invoice
            invoice = Invoice.objects.filter(
                source_module='reception',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if invoice:
                # Fatura tutarını güncelle
                invoice.total_amount = instance.total_amount
                invoice.subtotal = instance.total_amount - instance.tax_amount
                invoice.tax_amount = instance.tax_amount
                invoice.discount_amount = instance.discount_amount
                invoice.save()
                
                # Fatura satırlarını güncelle
                if invoice.lines.exists():
                    line = invoice.lines.first()
                    line.quantity = instance.total_nights
                    line.unit_price = instance.room_rate
                    line.line_total = instance.total_amount
                    line.description = f"{instance.check_in_date} - {instance.check_out_date} ({instance.total_nights} gece)"
                    line.save()
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü fatura güncelleme entegrasyonu başarısız: {str(e)}')


# ==================== BİLDİRİM SİNYALLERİ ====================

@receiver(post_save, sender=Reservation)
def send_reservation_created_notification(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda bildirim gönder
    """
    if created:
        try:
            from .utils_notifications import send_reservation_notification
            
            # WhatsApp bildirimi gönder
            send_reservation_notification(
                reservation=instance,
                notification_type='whatsapp',
                template_code='reservation_created_whatsapp',
            )
            
            # SMS bildirimi gönder
            send_reservation_notification(
                reservation=instance,
                notification_type='sms',
                template_code='reservation_created_sms',
            )
            
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Rezervasyon bildirimi gönderilemedi: {str(e)}')


@receiver(post_save, sender=Reservation)
def send_reservation_confirmed_notification(sender, instance, created, **kwargs):
    """
    Rezervasyon onaylandığında bildirim gönder
    """
    if not created and instance.pk:
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            
            # Rezervasyon onaylandıysa
            if old_instance.status != 'confirmed' and instance.status == 'confirmed':
                from .utils_notifications import send_reservation_notification
                
                # WhatsApp bildirimi gönder
                send_reservation_notification(
                    reservation=instance,
                    notification_type='whatsapp',
                    template_code='reservation_confirmed_whatsapp',
                )
                
                # SMS bildirimi gönder
                send_reservation_notification(
                    reservation=instance,
                    notification_type='sms',
                    template_code='reservation_confirmed_sms',
                )
        except Reservation.DoesNotExist:
            pass
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Rezervasyon onay bildirimi gönderilemedi: {str(e)}')


@receiver(post_save, sender=Reservation)
def send_checkin_notification(sender, instance, created, **kwargs):
    """
    Check-in yapıldığında bildirim gönder
    """
    if not created and instance.pk:
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            
            # Check-in yapıldıysa
            if not old_instance.is_checked_in and instance.is_checked_in:
                from .utils_notifications import send_reservation_notification
                
                # WhatsApp bildirimi gönder
                send_reservation_notification(
                    reservation=instance,
                    notification_type='whatsapp',
                    template_code='checkin_whatsapp',
                )
        except Reservation.DoesNotExist:
            pass
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Check-in bildirimi gönderilemedi: {str(e)}')


@receiver(post_save, sender=ReservationPayment)
def send_payment_notification(sender, instance, created, **kwargs):
    """
    Ödeme yapıldığında bildirim gönder
    """
    if created:
        try:
            from .utils_notifications import send_reservation_notification
            
            # WhatsApp bildirimi gönder
            send_reservation_notification(
                reservation=instance.reservation,
                notification_type='whatsapp',
                template_code='payment_received_whatsapp',
            )
            
            # SMS bildirimi gönder
            send_reservation_notification(
                reservation=instance.reservation,
                notification_type='sms',
                template_code='payment_received_sms',
            )
            
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Ödeme bildirimi gönderilemedi: {str(e)}')


@receiver(post_save, sender=ReservationVoucher)
def send_voucher_notification(sender, instance, created, **kwargs):
    """
    Voucher oluşturulduğunda bildirim gönder
    """
    if created:
        try:
            from .utils_notifications import send_voucher_notification
            
            # WhatsApp bildirimi gönder
            send_voucher_notification(
                voucher=instance,
                notification_type='whatsapp',
            )
            
            # SMS bildirimi gönder
            send_voucher_notification(
                voucher=instance,
                notification_type='sms',
            )
            
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Voucher bildirimi gönderilemedi: {str(e)}')
