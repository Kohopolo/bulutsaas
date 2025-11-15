"""
İade Yönetimi Modülü Utility Fonksiyonları
Diğer modüllerden kullanılabilir fonksiyonlar
"""
from django.db import transaction
from django.utils import timezone
from decimal import Decimal
from .models import RefundPolicy, RefundRequest, RefundTransaction


def get_refund_policy(module, booking_date=None, start_date=None):
    """
    Modül için uygun iade politikasını döndür
    
    Args:
        module: Modül kodu (tours, reservations vb.)
        booking_date: Rezervasyon tarihi (opsiyonel)
        start_date: Etkinlik/tur başlangıç tarihi (opsiyonel)
    
    Returns:
        RefundPolicy objesi veya None
    """
    # Modül bazlı politika
    policy = RefundPolicy.objects.filter(
        module=module,
        is_active=True,
        is_deleted=False
    ).order_by('-priority', '-is_default').first()
    
    # Modül bazlı bulunamazsa genel politika
    if not policy:
        policy = RefundPolicy.objects.filter(
            module='',
            is_active=True,
            is_deleted=False,
            is_default=True
        ).first()
    
    return policy


def create_refund_request(
    source_module,
    source_id,
    source_reference,
    customer_name,
    customer_email,
    original_amount,
    original_payment_method='',
    original_payment_date=None,
    reason='',
    customer_phone='',
    created_by=None,
    refund_policy_id=None,
    hotel=None,
    **kwargs
):
    """
    İade talebi oluştur (Diğer modüllerden kullanılabilir)
    
    Args:
        source_module: Kaynak modül kodu
        source_id: Kaynak modülün kayıt ID'si
        source_reference: Kaynak referans
        customer_name: Müşteri adı
        customer_email: Müşteri e-posta
        original_amount: Orijinal ödeme tutarı
        original_payment_method: Orijinal ödeme yöntemi
        original_payment_date: Orijinal ödeme tarihi
        reason: İade nedeni
        customer_phone: Müşteri telefon
        created_by: Oluşturan kullanıcı
        refund_policy_id: İade politikası ID (otomatik bulunur)
        hotel: Otel bilgisi (otomatik bulunur source_module'den)
        **kwargs: Ek parametreler
    
    Returns:
        RefundRequest objesi
    """
    if original_payment_date is None:
        original_payment_date = timezone.now().date()
    
    # Hotel bilgisini source_module'den çıkar (eğer verilmemişse)
    if not hotel and source_module and source_id:
        try:
            if source_module == 'reception':
                from apps.tenant_apps.reception.models import Reservation
                source_obj = Reservation.objects.filter(pk=source_id).first()
                if source_obj and hasattr(source_obj, 'hotel'):
                    hotel = source_obj.hotel
            elif source_module == 'tours':
                from apps.tenant_apps.tours.models import TourReservation
                source_obj = TourReservation.objects.filter(pk=source_id).first()
                if source_obj and hasattr(source_obj, 'hotel'):
                    hotel = source_obj.hotel
            elif source_module == 'ferry_tickets':
                from apps.tenant_apps.ferry_tickets.models import FerryTicket
                source_obj = FerryTicket.objects.filter(pk=source_id).first()
                if source_obj and hasattr(source_obj, 'hotel'):
                    hotel = source_obj.hotel
            elif source_module == 'bungalovs':
                from apps.tenant_apps.bungalovs.models import BungalovReservation
                source_obj = BungalovReservation.objects.filter(pk=source_id).first()
                if source_obj and hasattr(source_obj, 'hotel'):
                    hotel = source_obj.hotel
        except Exception:
            # Hata durumunda hotel None kalır
            pass
    
    # İade politikasını bul
    if refund_policy_id:
        try:
            refund_policy = RefundPolicy.objects.get(pk=refund_policy_id, is_active=True, is_deleted=False)
        except RefundPolicy.DoesNotExist:
            refund_policy = None
    else:
        refund_policy = get_refund_policy(source_module, original_payment_date)
    
    # İade tutarını hesapla
    refund_amount = Decimal('0')
    processing_fee = Decimal('0')
    net_refund = Decimal('0')
    refund_method = 'original'
    
    if refund_policy:
        refund_amount, processing_fee, net_refund = refund_policy.calculate_refund_amount(
            original_amount=original_amount,
            booking_date=original_payment_date,
            current_date=timezone.now().date()
        )
        refund_method = refund_policy.refund_method
    
    refund_request = RefundRequest.objects.create(
        hotel=hotel,  # Otel bilgisi eklendi
        source_module=source_module,
        source_id=source_id,
        source_reference=source_reference,
        customer_name=customer_name,
        customer_email=customer_email,
        customer_phone=customer_phone,
        original_amount=original_amount,
        original_payment_method=original_payment_method,
        original_payment_date=original_payment_date,
        refund_policy=refund_policy,
        refund_amount=refund_amount,
        processing_fee=processing_fee,
        net_refund=net_refund,
        refund_method=refund_method,
        reason=reason,
        customer_notes=kwargs.get('customer_notes', ''),
        created_by=created_by,
        status='pending',
    )
    
    return refund_request


def process_refund(
    refund_request_id,
    processed_by,
    cash_transaction_id=None,
    accounting_entry_id=None,
    payment_reference='',
    payment_provider='',
    **kwargs
):
    """
    İade işlemini gerçekleştir
    
    Args:
        refund_request_id: İade talebi ID
        processed_by: İşlemi yapan kullanıcı
        cash_transaction_id: Kasa işlemi ID (Finance modülünden)
        accounting_entry_id: Muhasebe kaydı ID (Accounting modülünden)
        payment_reference: Ödeme referansı
        payment_provider: Ödeme sağlayıcı
        **kwargs: Ek parametreler
    
    Returns:
        RefundTransaction objesi
    """
    try:
        refund_request = RefundRequest.objects.get(pk=refund_request_id, is_deleted=False)
    except RefundRequest.DoesNotExist:
        raise ValueError(f'İade talebi bulunamadı: {refund_request_id}')
    
    if refund_request.status != 'approved':
        raise ValueError(f'İade talebi onaylanmamış. Mevcut durum: {refund_request.status}')
    
    # İade işlemini oluştur
    refund_transaction = RefundTransaction.objects.create(
        refund_request=refund_request,
        transaction_date=timezone.now(),
        amount=refund_request.net_refund,
        currency=refund_request.currency,
        refund_method=refund_request.refund_method,
        payment_reference=payment_reference,
        payment_provider=payment_provider,
        cash_transaction_id=cash_transaction_id,
        accounting_entry_id=accounting_entry_id,
        processed_by=processed_by,
        status='processing',
    )
    
    # İade talebini işleme al
    refund_request.process(user=processed_by)
    
    return refund_transaction

