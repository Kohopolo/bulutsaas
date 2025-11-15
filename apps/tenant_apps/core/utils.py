"""
Core Utility Functions
"""
from django.db import connection
from django_tenants.utils import get_tenant
from django.utils import timezone
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from decimal import Decimal


def is_hotels_module_enabled(tenant=None):
    """
    Tenant'ın paketinde 'hotels' modülünün aktif olup olmadığını kontrol eder
    
    Args:
        tenant: Tenant instance (None ise connection'dan alınır)
    
    Returns:
        bool: Hotels modülü aktifse True, değilse False
    """
    try:
        # Tenant yoksa connection'dan almaya çalış
        if tenant is None:
            try:
                tenant = get_tenant(connection)
            except Exception:
                # Django settings yüklenmemiş veya tenant bulunamadı
                return False
        
        # Tenant hala None ise False döndür
        if tenant is None:
            return False
        
        # Tenant'ın id'si yoksa False döndür
        if not hasattr(tenant, 'id') or tenant.id is None:
            return False
        
        try:
            # Aktif aboneliği al
            active_subscription = Subscription.objects.filter(
                tenant=tenant,
                status='active',
                end_date__gte=timezone.now().date()
            ).select_related('package').first()
            
            if not active_subscription:
                # Aktif abonelik yoksa, en son aboneliği al
                active_subscription = Subscription.objects.filter(
                    tenant=tenant
                ).order_by('-created_at').select_related('package').first()
            
            if active_subscription and active_subscription.package:
                # Hotels modülünü kontrol et
                hotels_module = Module.objects.filter(code='hotels').first()
                if hotels_module:
                    package_module = PackageModule.objects.filter(
                        package=active_subscription.package,
                        module=hotels_module,
                        is_enabled=True
                    ).first()
                    
                    return package_module is not None
            
            return False
        except Exception:
            # Database hatası veya başka bir sorun
            return False
    except Exception:
        # Herhangi bir hata durumunda False döndür
        return False


def can_delete_with_payment_check(obj, source_module):
    """
    Ödeme kontrolü ile silme yapılabilir mi kontrol et
    
    Args:
        obj: Reservation, TourReservation veya FerryTicket objesi
        source_module: 'reception', 'tours', 'ferry_tickets', 'bungalovs'
    
    Returns:
        dict: {
            'can_delete': bool,
            'has_payment': bool,
            'refund_status': str or None,
            'refund_request_id': int or None,
            'refund_request': RefundRequest or None,
            'message': str,
            'total_paid': Decimal,
        }
    """
    from apps.tenant_apps.refunds.models import RefundRequest
    
    # Ödeme kontrolü
    total_paid = Decimal('0')
    if hasattr(obj, 'total_paid'):
        total_paid = obj.total_paid or Decimal('0')
    elif hasattr(obj, 'payments'):
        # Payments üzerinden hesapla
        payments = obj.payments.filter(
            status__in=['completed', 'pending'],
            is_deleted=False
        ) if hasattr(obj.payments, 'filter') else []
        if hasattr(payments, 'aggregate'):
            from django.db.models import Sum
            total_paid = payments.aggregate(total=Sum('amount'))['total'] or Decimal('0')
        else:
            total_paid = sum(p.amount for p in payments if hasattr(p, 'amount'))
    
    has_payment = total_paid > 0
    
    if not has_payment:
        return {
            'can_delete': True,
            'has_payment': False,
            'refund_status': None,
            'refund_request_id': None,
            'refund_request': None,
            'message': 'Ödeme yok, silme yapılabilir.',
            'total_paid': total_paid,
        }
    
    # İade kontrolü
    refund_request = RefundRequest.objects.filter(
        source_module=source_module,
        source_id=obj.pk,
        is_deleted=False
    ).order_by('-created_at').first()
    
    if not refund_request:
        return {
            'can_delete': False,
            'has_payment': True,
            'refund_status': None,
            'refund_request_id': None,
            'refund_request': None,
            'message': 'Ödeme alınmış. Silme için önce iade yapılmalı.',
            'total_paid': total_paid,
        }
    
    # İade durumu kontrolü
    if refund_request.status == 'completed':
        return {
            'can_delete': True,
            'has_payment': True,
            'refund_status': 'completed',
            'refund_request_id': refund_request.pk,
            'refund_request': refund_request,
            'message': 'İade tamamlandı, silme yapılabilir.',
            'total_paid': total_paid,
        }
    else:
        return {
            'can_delete': False,
            'has_payment': True,
            'refund_status': refund_request.status,
            'refund_request_id': refund_request.pk,
            'refund_request': refund_request,
            'message': f'İade durumu: {refund_request.get_status_display()}. İade tamamlanana kadar silme yapılamaz.',
            'total_paid': total_paid,
        }


def start_refund_process_for_deletion(obj, source_module, user, reason='Silme işlemi için iade'):
    """
    Silme için iade sürecini başlat
    
    Args:
        obj: Reservation, TourReservation veya FerryTicket objesi
        source_module: 'reception', 'tours', 'ferry_tickets', 'bungalovs'
        user: User instance
        reason: İade nedeni
    
    Returns:
        RefundRequest objesi veya None
    """
    from apps.tenant_apps.refunds.utils import create_refund_request
    
    # Müşteri bilgilerini al
    customer_name = ''
    customer_email = ''
    if hasattr(obj, 'customer'):
        customer = obj.customer
        if customer:
            customer_name = f"{getattr(customer, 'first_name', '')} {getattr(customer, 'last_name', '')}".strip()
            customer_email = getattr(customer, 'email', '')
    elif hasattr(obj, 'customer_name'):
        customer_name = obj.customer_name
        customer_email = getattr(obj, 'customer_email', '')
    
    # Ödeme bilgilerini al
    total_paid = Decimal('0')
    if hasattr(obj, 'total_paid'):
        total_paid = obj.total_paid or Decimal('0')
    elif hasattr(obj, 'payments'):
        payments = obj.payments.filter(
            status__in=['completed', 'pending'],
            is_deleted=False
        ) if hasattr(obj.payments, 'filter') else []
        if hasattr(payments, 'aggregate'):
            from django.db.models import Sum
            total_paid = payments.aggregate(total=Sum('amount'))['total'] or Decimal('0')
    
    # Hotel bilgisini al
    hotel = None
    if hasattr(obj, 'hotel'):
        hotel = obj.hotel
    
    # Referans bilgisi
    source_reference = getattr(obj, 'reservation_code', None) or getattr(obj, 'ticket_code', None) or str(obj.pk)
    
    try:
        refund_request = create_refund_request(
            source_module=source_module,
            source_id=obj.pk,
            source_reference=source_reference,
            customer_name=customer_name,
            customer_email=customer_email,
            original_amount=total_paid,
            original_payment_method='',
            reason=reason,
            created_by=user,
            hotel=hotel,  # Otel bilgisi eklendi
        )
        return refund_request
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'İade süreci başlatılamadı: {e}')
        return None


def calculate_dynamic_price(base_price, check_in_date, check_out_date, **kwargs):
    """
    Dinamik fiyat hesaplama (hotels modülü için)
    
    Args:
        base_price: Temel fiyat
        check_in_date: Check-in tarihi
        check_out_date: Check-out tarihi
        **kwargs: Diğer parametreler (pricing_type, adults, children, vb.)
    
    Returns:
        dict: Fiyat hesaplama sonucu
    """
    from decimal import Decimal
    
    # Basit implementasyon - hotels modülünde detaylandırılabilir
    # Şimdilik temel fiyatı döndür
    pricing_type = kwargs.get('pricing_type', 'fixed')
    adults = kwargs.get('adults', 1)
    children = kwargs.get('children', 0)
    
    # Eğer per_person ise yetişkin sayısı ile çarp
    if pricing_type == 'per_person':
        total_price = base_price * Decimal(str(adults))
    else:
        total_price = base_price
    
    return {
        'total_price': total_price,
        'adult_price': base_price if pricing_type == 'per_person' else base_price,
        'child_price': Decimal('0'),
        'breakdown': {
            'base_price': base_price,
            'pricing_type': pricing_type,
            'adults': adults,
            'children': children,
        }
    }
