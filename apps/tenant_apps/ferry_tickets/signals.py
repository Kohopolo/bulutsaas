"""
Feribot Bileti Signals
Otomatik işlemler için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
import logging

logger = logging.getLogger(__name__)


@receiver(post_save, sender='ferry_tickets.FerryTicketPayment')
def update_ticket_total_paid(sender, instance, created, **kwargs):
    """Ödeme kaydedildiğinde bilet toplam ödenen tutarını güncelle"""
    if created and not instance.is_deleted:
        try:
            ticket = instance.ticket
            ticket.update_total_paid()
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
            from apps.tenant_apps.accounting.models import Payment
            
            ticket = instance.ticket
            
            Payment.objects.create(
                customer=ticket.customer,
                amount=instance.payment_amount,
                currency=instance.currency,
                payment_method=instance.payment_method,
                payment_date=instance.payment_date,
                description=f'Feribot Bileti Ödemesi - {ticket.ticket_code}',
                reference_code=ticket.ticket_code,
                created_by=instance.created_by,
            )
            logger.info(f'Accounting ödeme kaydı oluşturuldu - Bilet: {ticket.ticket_code}')
        except Exception as e:
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


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

