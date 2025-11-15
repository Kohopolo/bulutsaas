"""
Otel Modülü Signals
Finance, Accounting ve Refunds modülleriyle entegrasyon
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone


# NOT: Otel rezervasyon modelleri henüz oluşturulmadığı için
# Bu signals dosyası hazır olarak bırakılmıştır.
# Rezervasyon modelleri oluşturulduğunda aktif hale getirilecektir.

# Örnek yapı (Rezervasyon modelleri oluşturulduğunda kullanılacak):
"""
@receiver(post_save, sender=HotelReservationPayment)
def create_finance_transaction_on_hotel_payment(sender, instance, created, **kwargs):
    '''
    Otel rezervasyon ödemesi yapıldığında Finance modülüne kasa işlemi oluştur
    '''
    if created and instance.status == 'completed':
        try:
            from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
            
            account = get_default_cash_account(currency=instance.currency)
            if not account:
                from apps.tenant_apps.finance.models import CashAccount
                account = CashAccount.objects.filter(
                    is_active=True,
                    is_deleted=False,
                    currency=instance.currency
                ).first()
            
            if account:
                payment_method_map = {
                    'cash': 'cash',
                    'bank_transfer': 'bank_transfer',
                    'credit_card': 'credit_card',
                    'iyzico': 'credit_card',
                    'paytr': 'credit_card',
                    'nestpay': 'credit_card',
                }
                finance_payment_method = payment_method_map.get(instance.payment_method, 'cash')
                
                create_cash_transaction(
                    account_id=account.pk,
                    transaction_type='income',
                    amount=instance.amount,
                    source_module='hotels',
                    source_id=instance.reservation.pk,
                    source_reference=f"Otel Rezervasyon: {instance.reservation.reservation_code}",
                    description=f"Otel rezervasyon ödemesi - {instance.reservation.hotel.name}",
                    payment_method=finance_payment_method,
                    currency=instance.currency,
                    created_by=instance.reservation.created_by if hasattr(instance.reservation, 'created_by') else None,
                    status='completed',
                )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(pre_save, sender=HotelReservation)
def create_refund_request_on_hotel_cancellation(sender, instance, **kwargs):
    '''
    Otel rezervasyonu iptal edildiğinde Refunds modülüne iade talebi oluştur
    '''
    if instance.pk:
        try:
            old_instance = HotelReservation.objects.get(pk=instance.pk)
            
            if old_instance.status != 'cancelled' and instance.status == 'cancelled':
                if old_instance.payment_status in ['paid', 'partial']:
                    try:
                        from apps.tenant_apps.refunds.utils import create_refund_request
                        
                        create_refund_request(
                            source_module='hotels',
                            source_id=instance.pk,
                            source_reference=f"Otel Rezervasyon: {instance.reservation_code}",
                            customer_name=f"{instance.customer_name} {instance.customer_surname}",
                            customer_email=instance.customer_email,
                            customer_phone=instance.customer_phone,
                            original_amount=instance.total_amount,
                            original_payment_method='',
                            original_payment_date=instance.created_at.date() if instance.created_at else timezone.now().date(),
                            reason=f'Otel rezervasyon iptali - {instance.hotel.name}',
                            created_by=instance.created_by if hasattr(instance, 'created_by') else None,
                            hotel=instance.hotel,  # Otel bilgisi eklendi
                        )
                    except Exception as e:
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.warning(f'Refunds modülü entegrasyonu başarısız: {str(e)}')
        except HotelReservation.DoesNotExist:
            pass


@receiver(post_save, sender=HotelReservation)
def create_accounting_invoice_on_hotel_payment(sender, instance, created, **kwargs):
    '''
    Otel rezervasyon ödemesi tamamlandığında Accounting modülüne fatura oluştur
    '''
    if instance.payment_status == 'paid' and not created:
        try:
            from apps.tenant_apps.accounting.models import Invoice
            existing_invoice = Invoice.objects.filter(
                source_module='hotels',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if not existing_invoice:
                from apps.tenant_apps.accounting.utils import create_invoice
                
                lines_data = [
                    {
                        'item_name': f"Otel Rezervasyonu - {instance.hotel.name}",
                        'item_code': instance.reservation_code,
                        'quantity': 1,
                        'unit_price': instance.total_amount,
                        'line_total': instance.total_amount,
                        'description': f"{instance.check_in_date} - {instance.check_out_date}",
                    }
                ]
                
                create_invoice(
                    invoice_type='sales',
                    customer_name=f"{instance.customer_name} {instance.customer_surname}",
                    total_amount=instance.total_amount,
                    source_module='hotels',
                    source_id=instance.pk,
                    source_reference=f"Otel Rezervasyon: {instance.reservation_code}",
                    invoice_date=timezone.now().date(),
                    currency=instance.currency,
                    created_by=instance.created_by if hasattr(instance, 'created_by') else None,
                    lines_data=lines_data,
                    customer_email=instance.customer_email,
                    customer_phone=instance.customer_phone,
                    customer_address=instance.customer_address,
                    description=f"Otel rezervasyon faturası - {instance.hotel.name}",
                    status='sent',
                )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')
"""

