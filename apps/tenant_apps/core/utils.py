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


def get_filter_hotels(request):
    """
    Filtreleme için otel listesini döndürür
    Eğer active_hotel varsa sadece onu döndürür, yoksa accessible_hotels'i döndürür
    
    Args:
        request: Django request objesi
    
    Returns:
        list: Otel listesi (Hotel instance'ları)
    """
    # Aktif otel varsa sadece onu göster
    if hasattr(request, 'active_hotel') and request.active_hotel:
        return [request.active_hotel]
    
    # Aktif otel yoksa accessible_hotels'i kullan
    if hasattr(request, 'accessible_hotels'):
        return list(request.accessible_hotels) if request.accessible_hotels else []
    
    return []


def calculate_dynamic_price(base_price, check_in_date, check_out_date, **kwargs):
    """
    Dinamik fiyat hesaplama (hotels modülü için)
    
    FORMÜL:
    1. Yetişkin Fiyatı:
       - fixed: base_price (oda fiyatı, kişi sayısından bağımsız)
       - per_person: base_price × adult_multiplier[adult_count] veya base_price × adults
    
    2. Çocuk Fiyatı:
       - Her çocuk için: base_price × child_multiplier
       - Ücretsiz çocuk kurallarına göre bazı çocuklar ücretsiz olabilir
    
    3. Ücretsiz Çocuk Hesaplama:
       - free_children_rules listesindeki her kural için:
         * Çocuk yaşı age_range içinde mi?
         * Yetişkin sayısı >= adult_required mi?
         * Ücretsiz çocuk sayısı limiti aşılmadı mı?
    
    4. Toplam Fiyat:
       - Yetişkin fiyatı + (Ücretli çocuk sayısı × base_price × child_multiplier)
    
    Args:
        base_price: Temel fiyat
        check_in_date: Check-in tarihi
        check_out_date: Check-out tarihi
        **kwargs: Diğer parametreler:
            - pricing_type: 'fixed' veya 'per_person'
            - adults: Yetişkin sayısı
            - children: Çocuk sayısı
            - child_ages: Çocuk yaşları listesi [5, 8, 12]
            - multipliers: Yetişkin çarpanları dict {1: 1.0, 2: 1.8, 3: 2.5}
            - child_multiplier: Çocuk sabit çarpanı (örn: 0.5)
            - free_children_rules: Ücretsiz çocuk kuralları listesi
                [{'age_range': (0, 6), 'count': 2, 'with_adults': 2}]
    
    Returns:
        dict: Fiyat hesaplama sonucu
    """
    from decimal import Decimal
    
    # base_price'ı Decimal'e çevir
    try:
        # Eğer base_price bir string ve 'per_person' gibi bir değer ise hata ver
        if isinstance(base_price, str) and base_price.lower().strip() == 'per_person':
            raise ValueError(f"base_price geçersiz değer: '{base_price}'. Fiyat değeri bekleniyor.")
        base_price = Decimal(str(base_price))
    except (ValueError, TypeError) as e:
        raise ValueError(f"base_price geçersiz: {base_price}. Hata: {str(e)}")
    
    # Parametreleri al
    pricing_type = kwargs.get('pricing_type', 'fixed')
    adults = int(kwargs.get('adults', 1))
    children = int(kwargs.get('children', 0))
    child_ages = kwargs.get('child_ages', [])
    multipliers = kwargs.get('multipliers', {})  # Yetişkin çarpanları
    child_multiplier = kwargs.get('child_multiplier', Decimal('0.5'))  # Çocuk çarpanı (varsayılan 0.5)
    free_children_rules = kwargs.get('free_children_rules', [])  # Ücretsiz çocuk kuralları
    
    # child_multiplier'ı Decimal'e çevir
    if not isinstance(child_multiplier, Decimal):
        child_multiplier = Decimal(str(child_multiplier))
    
    # ========== 1. YETİŞKİN FİYATI HESAPLAMA ==========
    if pricing_type == 'per_person':
        # Kişi başı fiyatlandırma
        # Yetişkin çarpanı varsa kullan, yoksa yetişkin sayısı ile çarp
        if multipliers and adults in multipliers:
            adult_multiplier = Decimal(str(multipliers[adults]))
            adult_price = base_price * adult_multiplier
        else:
            # Çarpan yoksa direkt yetişkin sayısı ile çarp
            adult_price = base_price * Decimal(str(adults))
    else:
        # Sabit oda fiyatı (kişi sayısından bağımsız)
        adult_price = base_price
    
    # ========== 2. ÜCRETSİZ ÇOCUK HESAPLAMA ==========
    free_children_count = 0
    
    # Debug: Ücretsiz çocuk hesaplama parametrelerini kontrol et
    import logging
    logger = logging.getLogger(__name__)
    
    # child_ages None ise boş liste yap
    if child_ages is None:
        child_ages = []
    
    logger.debug(f"Ücretsiz çocuk hesaplama - children: {children}, free_children_rules: {free_children_rules}, child_ages: {child_ages}")
    
    # Eğer child_ages boşsa, ücretsiz çocuk hesaplaması yapılamaz (yaş bilgisi yok)
    # Ama eğer child_ages varsa ve en az bir yaş bilgisi varsa, hesaplama yapılabilir
    if children > 0 and free_children_rules and child_ages and len(child_ages) > 0:
        # Ücretsiz olarak işaretlenecek çocukları takip et (indeks bazlı)
        free_children_indices = set()
        
        logger.debug(f"Ücretsiz çocuk kuralları sayısı: {len(free_children_rules)}")
        
        # Her ücretsiz çocuk kuralı için kontrol et
        for rule_idx, rule in enumerate(free_children_rules):
            age_range = rule.get('age_range', (0, 12))
            max_free_count = rule.get('count', 0)
            adult_required = rule.get('with_adults', 1)  # 'adult_required' veya 'with_adults' olabilir
            
            logger.debug(f"Kural {rule_idx}: age_range={age_range}, max_free_count={max_free_count}, adult_required={adult_required}, adults={adults}")
            
            # Yetişkin sayısı yeterli mi?
            if adults < adult_required:
                logger.debug(f"Kural {rule_idx} atlandı - yeterli yetişkin yok ({adults} < {adult_required})")
                continue  # Bu kural için yeterli yetişkin yok
            
            # Yaş aralığına uyan çocukları bul (indeks ile birlikte)
            # Sadece mevcut yaş bilgisi olan çocukları kontrol et
            age_start, age_end = age_range
            eligible_children_indices = [
                idx for idx, age in enumerate(child_ages)
                if age_start <= age <= age_end and idx not in free_children_indices
            ]
            
            logger.debug(f"Kural {rule_idx} - Uygun çocuklar (indeks): {eligible_children_indices}, Yaşlar: {[child_ages[idx] for idx in eligible_children_indices]}")
            
            # Ücretsiz çocuk sayısını hesapla (limit dahilinde ve henüz ücretsiz olmayan çocuklar)
            eligible_count = len(eligible_children_indices)
            free_count_for_rule = min(eligible_count, max_free_count)
            
            logger.debug(f"Kural {rule_idx} - Ücretsiz çocuk sayısı: {free_count_for_rule} (eligible: {eligible_count}, max: {max_free_count})")
            
            # İlk N çocuğu ücretsiz olarak işaretle
            for i in range(free_count_for_rule):
                if i < len(eligible_children_indices):
                    free_children_indices.add(eligible_children_indices[i])
                    logger.debug(f"Çocuk {eligible_children_indices[i]} (yaş: {child_ages[eligible_children_indices[i]]}) ücretsiz olarak işaretlendi")
        
        # Toplam ücretsiz çocuk sayısı
        free_children_count = len(free_children_indices)
        logger.debug(f"Toplam ücretsiz çocuk sayısı: {free_children_count}")
    else:
        logger.debug(f"Ücretsiz çocuk hesaplama atlandı - children: {children}, free_children_rules: {bool(free_children_rules)}, child_ages uzunluğu: {len(child_ages) if child_ages else 0}")
    
    # Ücretsiz çocuk sayısı toplam çocuk sayısını aşamaz
    free_children_count = min(free_children_count, children)
    logger.debug(f"Final ücretsiz çocuk sayısı: {free_children_count}")
    
    # ========== 3. ÜCRETLİ ÇOCUK SAYISI ==========
    paid_children_count = children - free_children_count
    
    # ========== 4. ÇOCUK FİYATI HESAPLAMA ==========
    if pricing_type == 'per_person':
        # Kişi başı fiyatlandırmada çocuklar da base_price × child_multiplier
        child_price = base_price * child_multiplier * Decimal(str(paid_children_count))
    else:
        # Sabit oda fiyatında çocuklar ek ücret olarak eklenir
        # Çocuk fiyatı = base_price × child_multiplier × ücretli çocuk sayısı
        child_price = base_price * child_multiplier * Decimal(str(paid_children_count))
    
    # ========== 5. TOPLAM FİYAT ==========
    total_price = adult_price + child_price
    
    # ========== 6. BREAKDOWN (DETAYLI BİLGİ) ==========
    breakdown = {
        'base_price': float(base_price),
        'pricing_type': pricing_type,
        'adults': adults,
        'children': children,
        'child_ages': child_ages,
        'adult_price': float(adult_price),
        'child_price': float(child_price),
        'free_children_count': free_children_count,
        'paid_children_count': paid_children_count,
        'child_multiplier': float(child_multiplier),
    }
    
    # Yetişkin çarpanı varsa breakdown'a ekle
    if multipliers and adults in multipliers:
        breakdown['adult_multiplier'] = float(multipliers[adults])
    
    return {
        'total_price': total_price,
        'adult_price': adult_price,
        'child_price': child_price,
        'breakdown': breakdown,
    }
