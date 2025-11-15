"""
Muhasebe Modülü Utility Fonksiyonları
Diğer modüllerden kullanılabilir fonksiyonlar
"""
from django.db import transaction
from django.utils import timezone
from decimal import Decimal
from .models import Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, Payment


def create_journal_entry(
    description,
    source_module,
    source_id,
    source_reference='',
    entry_date=None,
    created_by=None,
    lines_data=None,
    hotel=None,
    **kwargs
):
    """
    Yevmiye kaydı oluştur (Diğer modüllerden kullanılabilir)
    
    Args:
        description: Yevmiye açıklaması
        source_module: Kaynak modül kodu
        source_id: Kaynak modülün kayıt ID'si
        source_reference: Kaynak referans
        entry_date: Kayıt tarihi (varsayılan: bugün)
        created_by: Oluşturan kullanıcı
        lines_data: Yevmiye satırları listesi
            Örnek: [
                {'account_code': '100', 'debit': 1000, 'credit': 0, 'description': 'Nakit'},
                {'account_code': '600', 'debit': 0, 'credit': 1000, 'description': 'Gelir'},
            ]
        **kwargs: Ek parametreler
    
    Returns:
        JournalEntry objesi
    """
    if entry_date is None:
        entry_date = timezone.now().date()
    
    if lines_data is None:
        lines_data = []
    
    with transaction.atomic():
        journal_entry = JournalEntry.objects.create(
            hotel=hotel,  # Otel bilgisi eklendi
            entry_date=entry_date,
            description=description,
            source_module=source_module,
            source_id=source_id,
            source_reference=source_reference,
            created_by=created_by,
            notes=kwargs.get('notes', ''),
            status='draft',
        )
        
        # Yevmiye satırlarını oluştur
        for line_data in lines_data:
            account_code = line_data.get('account_code')
            if not account_code:
                continue
            
            try:
                # Önce otel bazlı hesabı ara, yoksa genel hesabı ara
                if hotel:
                    account = Account.objects.filter(
                        hotel=hotel,
                        code=account_code,
                        is_active=True,
                        is_deleted=False
                    ).first()
                    if not account:
                        account = Account.objects.filter(
                            hotel__isnull=True,
                            code=account_code,
                            is_active=True,
                            is_deleted=False
                        ).first()
                else:
                    account = Account.objects.filter(
                        hotel__isnull=True,
                        code=account_code,
                        is_active=True,
                        is_deleted=False
                    ).first()
                
                if not account:
                    continue
            except Account.DoesNotExist:
                continue
            
            JournalEntryLine.objects.create(
                journal_entry=journal_entry,
                account=account,
                debit=Decimal(str(line_data.get('debit', 0))),
                credit=Decimal(str(line_data.get('credit', 0))),
                description=line_data.get('description', ''),
            )
        
        # Borç ve alacak eşit mi kontrol et
        if journal_entry.is_balanced():
            # Otomatik kaydet
            journal_entry.post(user=created_by)
        else:
            # Eşit değilse taslak olarak bırak
            pass
    
    return journal_entry


def create_invoice(
    invoice_type,
    customer_name,
    total_amount,
    source_module,
    source_id,
    source_reference='',
    invoice_date=None,
    currency='TRY',
    created_by=None,
    lines_data=None,
    hotel=None,
    **kwargs
):
    """
    Fatura oluştur (Diğer modüllerden kullanılabilir)
    
    Args:
        invoice_type: 'sales', 'purchase', 'expense'
        customer_name: Müşteri/Tedarikçi adı
        total_amount: Toplam tutar
        source_module: Kaynak modül kodu
        source_id: Kaynak modülün kayıt ID'si
        source_reference: Kaynak referans
        invoice_date: Fatura tarihi (varsayılan: bugün)
        currency: Para birimi
        created_by: Oluşturan kullanıcı
        lines_data: Fatura satırları listesi
            Örnek: [
                {'item_name': 'Tur Rezervasyonu', 'quantity': 1, 'unit_price': 1000, 'line_total': 1000},
            ]
        **kwargs: Ek parametreler
    
    Returns:
        Invoice objesi
    """
    if invoice_date is None:
        invoice_date = timezone.now().date()
    
    if lines_data is None:
        lines_data = []
    
    # Subtotal hesapla
    subtotal = sum(Decimal(str(line.get('line_total', 0))) for line in lines_data)
    
    # KDV ve toplam hesapla
    tax_rate = Decimal(str(kwargs.get('tax_rate', 20)))
    discount_amount = Decimal(str(kwargs.get('discount_amount', 0)))
    tax_amount = (subtotal - discount_amount) * (tax_rate / 100)
    total = subtotal - discount_amount + tax_amount
    
    invoice = Invoice.objects.create(
        hotel=hotel,  # Otel bilgisi eklendi
        invoice_type=invoice_type,
        invoice_date=invoice_date,
        due_date=kwargs.get('due_date'),
        customer_name=customer_name,
        customer_tax_id=kwargs.get('customer_tax_id', ''),
        customer_address=kwargs.get('customer_address', ''),
        customer_email=kwargs.get('customer_email', ''),
        customer_phone=kwargs.get('customer_phone', ''),
        subtotal=subtotal,
        discount_amount=discount_amount,
        tax_rate=tax_rate,
        tax_amount=tax_amount,
        total_amount=total,
        currency=currency,
        source_module=source_module,
        source_id=source_id,
        source_reference=source_reference,
        description=kwargs.get('description', ''),
        created_by=created_by,
        status='draft',
    )
    
    # Fatura satırlarını oluştur
    for line_data in lines_data:
        InvoiceLine.objects.create(
            invoice=invoice,
            item_name=line_data.get('item_name', ''),
            item_code=line_data.get('item_code', ''),
            quantity=Decimal(str(line_data.get('quantity', 1))),
            unit_price=Decimal(str(line_data.get('unit_price', 0))),
            discount_rate=Decimal(str(line_data.get('discount_rate', 0))),
            description=line_data.get('description', ''),
        )
    
    return invoice


def create_payment(
    amount,
    invoice_id=None,
    source_module='',
    source_id=None,
    source_reference='',
    payment_date=None,
    currency='TRY',
    payment_method='cash',
    cash_account_id=None,
    created_by=None,
    hotel=None,
    **kwargs
):
    """
    Ödeme kaydı oluştur (Diğer modüllerden kullanılabilir)
    
    Args:
        amount: Ödeme tutarı
        invoice_id: Fatura ID (varsa)
        source_module: Kaynak modül kodu
        source_id: Kaynak modülün kayıt ID'si
        source_reference: Kaynak referans
        payment_date: Ödeme tarihi (varsayılan: şimdi)
        currency: Para birimi
        payment_method: Ödeme yöntemi
        cash_account_id: Kasa hesabı ID (Finance modülünden)
        created_by: Oluşturan kullanıcı
        **kwargs: Ek parametreler
    
    Returns:
        Payment objesi
    """
    if payment_date is None:
        payment_date = timezone.now()
    
    payment = Payment.objects.create(
        hotel=hotel,  # Otel bilgisi eklendi
        payment_date=payment_date,
        amount=amount,
        currency=currency,
        payment_method=payment_method,
        invoice_id=invoice_id,
        cash_account_id=cash_account_id,
        source_module=source_module,
        source_id=source_id,
        source_reference=source_reference,
        description=kwargs.get('description', ''),
        created_by=created_by,
        status='pending',
    )
    
    # Tamamlandı olarak işaretlenmişse
    if kwargs.get('auto_complete', False):
        payment.complete()
    
    return payment

