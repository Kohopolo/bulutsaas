"""
Kasa Modülü Utility Fonksiyonları
Diğer modüllerden kullanılabilir fonksiyonlar
"""
from django.db import transaction
from django.utils import timezone
from decimal import Decimal
from .models import CashAccount, CashTransaction


def create_cash_transaction(
    account_id,
    transaction_type,
    amount,
    source_module,
    source_id,
    source_reference='',
    description='',
    payment_method='cash',
    currency='TRY',
    to_account_id=None,
    created_by=None,
    status='completed',
    **kwargs
):
    """
    Kasa işlemi oluştur (Diğer modüllerden kullanılabilir)
    
    Args:
        account_id: Kasa hesabı ID
        transaction_type: 'income', 'expense', 'transfer', 'adjustment'
        amount: İşlem tutarı
        source_module: Kaynak modül kodu (tours, reservations vb.)
        source_id: Kaynak modülün kayıt ID'si
        source_reference: Kaynak referans (Rezervasyon no, Tur adı vb.)
        description: İşlem açıklaması
        payment_method: Ödeme yöntemi
        currency: Para birimi
        to_account_id: Transfer için hedef hesap ID
        created_by: İşlemi yapan kullanıcı
        status: İşlem durumu ('pending', 'completed')
        **kwargs: Ek parametreler (notes, due_date, vb.)
    
    Returns:
        CashTransaction objesi
    """
    try:
        account = CashAccount.objects.get(pk=account_id, is_active=True, is_deleted=False)
    except CashAccount.DoesNotExist:
        raise ValueError(f'Kasa hesabı bulunamadı: {account_id}')
    
    if to_account_id:
        try:
            to_account = CashAccount.objects.get(pk=to_account_id, is_active=True, is_deleted=False)
        except CashAccount.DoesNotExist:
            raise ValueError(f'Hedef kasa hesabı bulunamadı: {to_account_id}')
    else:
        to_account = None
    
    cash_transaction = CashTransaction.objects.create(
        account=account,
        transaction_type=transaction_type,
        amount=amount,
        currency=currency,
        to_account=to_account,
        source_module=source_module,
        source_id=source_id,
        source_reference=source_reference,
        description=description,
        payment_method=payment_method,
        payment_date=timezone.now(),
        status=status,
        created_by=created_by,
        due_date=kwargs.get('due_date'),
        notes=kwargs.get('notes', ''),
    )
    
    # Tamamlandı olarak işaretlenmişse bakiyeyi güncelle
    if status == 'completed':
        cash_transaction.complete(user=created_by)
    
    return cash_transaction


def get_default_cash_account(currency='TRY'):
    """
    Varsayılan kasa hesabını döndür
    
    Args:
        currency: Para birimi
    
    Returns:
        CashAccount objesi veya None
    """
    return CashAccount.objects.filter(
        is_default=True,
        is_active=True,
        is_deleted=False,
        currency=currency
    ).first()


def get_account_balance(account_id):
    """
    Kasa hesabı bakiyesini döndür
    
    Args:
        account_id: Kasa hesabı ID
    
    Returns:
        Decimal: Güncel bakiye
    """
    try:
        account = CashAccount.objects.get(pk=account_id, is_deleted=False)
        account.calculate_balance()
        return account.current_balance
    except CashAccount.DoesNotExist:
        return Decimal('0')

