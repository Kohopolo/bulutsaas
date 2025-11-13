"""
Ödeme Yönetimi Modülü Signal'ları
Finance, Accounting, Sales ve Refunds modülleriyle entegrasyon
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
from .models import PaymentTransaction
import logging

logger = logging.getLogger(__name__)


@receiver(post_save, sender=PaymentTransaction)
def create_finance_transaction_on_payment(sender, instance, created, **kwargs):
    """
    PaymentTransaction tamamlandığında Finance modülüne CashTransaction oluştur
    """
    # Sadece completed durumunda ve daha önce oluşturulmamışsa
    if instance.status != 'completed' or instance.cash_transaction_id:
        return
    
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
                'bank_transfer': 'bank_transfer',
                'digital_wallet': 'digital_wallet',
                'check': 'check',
                'other': 'other',
            }
            # Gateway kodundan ödeme yöntemini belirle
            gateway_code = instance.gateway.code if instance.gateway else ''
            if gateway_code in ['iyzico', 'paytr', 'nestpay', 'payu', 'garanti', 'isbank', 
                               'akbank', 'ziraat', 'yapikredi', 'denizbank', 'halkbank',
                               'qnbfinansbank', 'teb', 'sekerbank', 'ingbank', 'vakifbank',
                               'fibabanka', 'albaraka', 'kuveytturk', 'ziraatkatilim', 'vakifkatilim']:
                finance_payment_method = 'credit_card'
            else:
                finance_payment_method = payment_method_map.get(instance.payment_method, 'credit_card')
            
            # Kasa işlemi oluştur
            cash_transaction = create_cash_transaction(
                account_id=account.pk,
                transaction_type='income',
                amount=instance.amount,
                source_module=instance.source_module or 'payments',
                source_id=instance.pk,
                source_reference=instance.source_reference or f"Ödeme İşlemi: {instance.transaction_id}",
                description=f"Ödeme işlemi - {instance.transaction_id}",
                payment_method=finance_payment_method,
                currency=instance.currency,
                created_by=None,  # PaymentTransaction'da created_by yok
                status='completed',
            )
            
            # PaymentTransaction'a bağla
            instance.cash_transaction_id = cash_transaction.pk
            instance.save(update_fields=['cash_transaction_id'])
            
    except Exception as e:
        # Finance modülü yoksa veya hata oluşursa sessizce geç
        logger.warning(f'Finance modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=PaymentTransaction)
def create_accounting_payment_on_payment(sender, instance, created, **kwargs):
    """
    PaymentTransaction tamamlandığında Accounting modülüne Payment kaydı oluştur
    """
    # Sadece completed durumunda ve daha önce oluşturulmamışsa
    if instance.status != 'completed' or instance.accounting_payment_id:
        return
    
    try:
        from apps.tenant_apps.accounting.utils import create_payment
        from apps.tenant_apps.accounting.models import Invoice
        
        # İlgili faturayı bul (varsa)
        invoice = None
        if instance.source_module and instance.source_id:
            invoice = Invoice.objects.filter(
                source_module=instance.source_module,
                source_id=instance.source_id,
                is_deleted=False
            ).first()
        
        # Kasa hesabını bul (Finance modülünden)
        cash_account_id = instance.cash_transaction_id
        
        # Ödeme yöntemini eşleştir
        payment_method_map = {
            'cash': 'cash',
            'credit_card': 'credit_card',
            'bank_transfer': 'bank_transfer',
            'digital_wallet': 'digital_wallet',
            'check': 'check',
            'other': 'other',
        }
        accounting_payment_method = payment_method_map.get(instance.payment_method, 'credit_card')
        
        # Ödeme kaydı oluştur
        accounting_payment = create_payment(
            amount=instance.amount,
            invoice_id=invoice.pk if invoice else None,
            source_module=instance.source_module or 'payments',
            source_id=instance.pk,
            source_reference=instance.source_reference or f"Ödeme İşlemi: {instance.transaction_id}",
            payment_date=instance.payment_date or timezone.now(),
            currency=instance.currency,
            payment_method=accounting_payment_method,
            cash_account_id=cash_account_id,
            created_by=None,
            auto_complete=True,  # Otomatik tamamla
            description=f"Ödeme işlemi - {instance.transaction_id}",
        )
        
        # PaymentTransaction'a bağla
        instance.accounting_payment_id = accounting_payment.pk
        instance.save(update_fields=['accounting_payment_id'])
        
        # Fatura durumunu güncelle
        if invoice:
            # Toplam ödenen tutarı kontrol et
            from django.db.models import Sum
            total_paid = invoice.payments.filter(status='completed').aggregate(
                total=Sum('amount')
            )['total'] or 0
            if total_paid >= invoice.total_amount:
                invoice.status = 'paid'
                invoice.save()
            elif total_paid > 0:
                invoice.status = 'partial'
                invoice.save()
                
    except Exception as e:
        # Accounting modülü yoksa veya hata oluşursa sessizce geç
        logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=PaymentTransaction)
def create_sales_record_on_payment(sender, instance, created, **kwargs):
    """
    PaymentTransaction tamamlandığında Sales modülüne SalesRecord oluştur (eğer source_module='sales' ise)
    """
    # Sadece completed durumunda ve source_module='sales' ise
    if instance.status != 'completed' or instance.source_module != 'sales' or instance.sales_record_id:
        return
    
    try:
        from apps.tenant_apps.sales.models import SalesRecord
        
        # SalesRecord'u bul ve güncelle
        if instance.source_id:
            sales_record = SalesRecord.objects.filter(
                pk=instance.source_id,
                is_deleted=False
            ).first()
            
            if sales_record:
                # SalesRecord'u PaymentTransaction ile ilişkilendir
                # Not: SalesRecord modelinde payment_transaction_id field'ı yok, 
                # bu yüzden metadata veya notes'a ekleyebiliriz
                if not hasattr(sales_record, 'metadata'):
                    # Eğer metadata field'ı yoksa notes'a ekle
                    sales_record.notes = f"{sales_record.notes}\nÖdeme İşlemi: {instance.transaction_id}".strip()
                else:
                    # Metadata varsa oraya ekle
                    if not sales_record.metadata:
                        sales_record.metadata = {}
                    sales_record.metadata['payment_transaction_id'] = instance.pk
                    sales_record.metadata['payment_transaction_ref'] = instance.transaction_id
                
                sales_record.save()
                
                # PaymentTransaction'a bağla
                instance.sales_record_id = sales_record.pk
                instance.save(update_fields=['sales_record_id'])
                
    except Exception as e:
        # Sales modülü yoksa veya hata oluşursa sessizce geç
        logger.warning(f'Sales modülü entegrasyonu başarısız: {str(e)}')


@receiver(post_save, sender=PaymentTransaction)
def update_refund_transaction_on_payment(sender, instance, created, **kwargs):
    """
    PaymentTransaction iade edildiğinde Refunds modülündeki RefundTransaction'ı güncelle
    """
    # Sadece refunded veya partially_refunded durumunda
    if instance.status not in ['refunded', 'partially_refunded']:
        return
    
    try:
        from apps.tenant_apps.refunds.models import RefundTransaction
        
        # RefundTransaction'ı bul ve güncelle
        if instance.refund_transaction_id:
            refund_transaction = RefundTransaction.objects.filter(
                pk=instance.refund_transaction_id,
                is_deleted=False
            ).first()
            
            if refund_transaction:
                # PaymentTransaction bilgilerini RefundTransaction'a ekle
                refund_transaction.payment_reference = instance.transaction_id
                refund_transaction.payment_provider = instance.gateway.name if instance.gateway else ''
                refund_transaction.save()
                
    except Exception as e:
        # Refunds modülü yoksa veya hata oluşursa sessizce geç
        logger.warning(f'Refunds modülü entegrasyonu başarısız: {str(e)}')

