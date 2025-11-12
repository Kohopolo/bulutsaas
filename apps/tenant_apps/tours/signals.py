"""
Tur Modülü Signals
Finance, Accounting ve Refunds modülleriyle entegrasyon
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.db import transaction
from django.utils import timezone
from .models import TourPayment, TourReservation


@receiver(post_save, sender=TourPayment)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    Tur ödemesi yapıldığında Finance modülüne kasa işlemi oluştur
    """
    if created and instance.status == 'completed':
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
                )
        except Exception as e:
            # Finance modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


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

