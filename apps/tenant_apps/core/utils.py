"""
Core Utility Functions
Tüm modüller tarafından kullanılabilecek global utility fonksiyonları

NOT: calculate_dynamic_price fonksiyonu sadece Otel modülünün rezervasyon hesaplamalarında kullanılacaktır.
Tur modülü kendi dinamik fiyatlama sistemini kullanmaktadır.
"""
from decimal import Decimal
from datetime import date, datetime
from typing import Optional, Dict, List, Tuple
from django.utils import timezone


def calculate_dynamic_price(
    base_price: Decimal,
    pricing_type: str,  # 'fixed' veya 'per_person'
    adults: int = 1,
    children: int = 0,
    child_ages: Optional[List[int]] = None,
    multipliers: Optional[Dict[int, Decimal]] = None,  # {1: 1.0, 2: 0.9, 3: 0.85}
    child_multiplier: Optional[Decimal] = None,
    free_children_rules: Optional[List[Dict]] = None,  # [{'age_range': (0, 6), 'count': 2, 'with_adults': 1}]
    seasonal_prices: Optional[List[Dict]] = None,  # [{'start_date': date, 'end_date': date, 'price': Decimal}]
    special_prices: Optional[List[Dict]] = None,  # [{'date': date, 'price': Decimal, 'day_of_week': int}]
    campaign_prices: Optional[List[Dict]] = None,  # [{'start_date': date, 'end_date': date, 'price': Decimal}]
    agency_prices: Optional[Dict[int, Decimal]] = None,  # {agency_id: price}
    channel_prices: Optional[Dict[str, Decimal]] = None,  # {'channel_name': price}
    check_date: Optional[date] = None,
    agency_id: Optional[int] = None,
    channel_name: Optional[str] = None,
    discount_rate: Optional[Decimal] = None,  # Toplam indirim oranı (0.10 = %10)
) -> Dict[str, any]:
    """
    Dinamik fiyat hesaplama fonksiyonu
    
    Öncelik sırası:
    1. Campaign Price (tarih aralığında)
    2. Seasonal Price (tarih aralığında)
    3. Special Price (tarih bazlı, gün bazlı)
    4. Agency Price (eğer agency_id verilmişse)
    5. Channel Price (eğer channel_name verilmişse)
    6. Base Price
    
    Args:
        base_price: Temel fiyat
        pricing_type: 'fixed' (sabit oda fiyatı) veya 'per_person' (kişi başı)
        adults: Yetişkin sayısı
        children: Çocuk sayısı
        child_ages: Çocuk yaşları listesi
        multipliers: Yetişkin çarpanları {1: 1.0, 2: 0.9, 3: 0.85}
        child_multiplier: Çocuk çarpanı
        free_children_rules: Ücretsiz çocuk kuralları
        seasonal_prices: Sezonluk fiyatlar
        special_prices: Özel fiyatlar
        campaign_prices: Kampanya fiyatları
        agency_prices: Acente fiyatları
        channel_prices: Kanal fiyatları
        check_date: Kontrol edilecek tarih (None ise bugün)
        agency_id: Acente ID (varsa)
        channel_name: Kanal adı (varsa)
        discount_rate: Toplam indirim oranı (0.10 = %10)
    
    Returns:
        Dict: {
            'total_price': Decimal,
            'adult_price': Decimal,
            'child_price': Decimal,
            'free_children_count': int,
            'paid_children_count': int,
            'applied_price_type': str,
            'breakdown': Dict
        }
    """
    if check_date is None:
        check_date = timezone.now().date()
    
    # Başlangıç değerleri
    applied_price = base_price
    applied_price_type = 'base'
    breakdown = {
        'base_price': base_price,
        'price_type': pricing_type,
    }
    
    # 1. Kampanya Fiyatı Kontrolü
    if campaign_prices:
        for campaign in campaign_prices:
            if (campaign.get('start_date') <= check_date <= campaign.get('end_date')):
                applied_price = campaign.get('price', base_price)
                applied_price_type = 'campaign'
                breakdown['campaign_price'] = applied_price
                break
    
    # 2. Sezonluk Fiyat Kontrolü (kampanya yoksa)
    if applied_price_type == 'base' and seasonal_prices:
        for seasonal in seasonal_prices:
            if (seasonal.get('start_date') <= check_date <= seasonal.get('end_date')):
                applied_price = seasonal.get('price', base_price)
                applied_price_type = 'seasonal'
                breakdown['seasonal_price'] = applied_price
                break
    
    # 3. Özel Fiyat Kontrolü (kampanya ve sezonluk yoksa)
    if applied_price_type == 'base' and special_prices:
        for special in special_prices:
            special_date = special.get('date')
            day_of_week = special.get('day_of_week')
            
            # Tarih bazlı kontrol
            if special_date and special_date == check_date:
                applied_price = special.get('price', base_price)
                applied_price_type = 'special_date'
                breakdown['special_date_price'] = applied_price
                break
            
            # Gün bazlı kontrol (pazartesi=0, pazar=6)
            if day_of_week is not None and check_date.weekday() == day_of_week:
                applied_price = special.get('price', base_price)
                applied_price_type = 'special_day'
                breakdown['special_day_price'] = applied_price
                break
    
    # 4. Acente Fiyatı Kontrolü
    if agency_id and agency_prices and agency_id in agency_prices:
        applied_price = agency_prices[agency_id]
        applied_price_type = 'agency'
        breakdown['agency_price'] = applied_price
    
    # 5. Kanal Fiyatı Kontrolü
    if channel_name and channel_prices and channel_name in channel_prices:
        applied_price = channel_prices[channel_name]
        applied_price_type = 'channel'
        breakdown['channel_price'] = applied_price
    
    # Fiyat tipine göre hesaplama
    if pricing_type == 'fixed':
        # Sabit oda fiyatı
        total_price = applied_price
        adult_price = applied_price
        child_price = Decimal('0')
        breakdown['calculation'] = 'fixed_room_price'
    else:
        # Kişi başı fiyat
        # Yetişkin fiyatı
        adult_multiplier = Decimal('1.0')
        if multipliers and adults in multipliers:
            adult_multiplier = multipliers[adults]
        elif multipliers and adults > max(multipliers.keys()):
            # En yüksek çarpanı kullan
            adult_multiplier = multipliers[max(multipliers.keys())]
        
        adult_price = applied_price * Decimal(str(adults)) * adult_multiplier
        breakdown['adult_multiplier'] = adult_multiplier
        breakdown['adult_count'] = adults
        
        # Ücretsiz çocuk hesaplama
        free_children_count = 0
        paid_children_count = children
        
        if free_children_rules and child_ages:
            for rule in free_children_rules:
                age_range = rule.get('age_range', (0, 18))
                max_free = rule.get('count', 0)
                with_adults = rule.get('with_adults', 1)
                
                # Yetişkin sayısı kontrolü
                if adults >= with_adults:
                    # Yaş aralığındaki çocukları say
                    free_in_range = sum(
                        1 for age in child_ages
                        if age_range[0] <= age <= age_range[1]
                    )
                    # Ücretsiz çocuk sayısını hesapla
                    free_count = min(free_in_range, max_free, paid_children_count)
                    free_children_count += free_count
                    paid_children_count -= free_count
        
        # Çocuk fiyatı
        child_price = Decimal('0')
        if paid_children_count > 0:
            child_mult = child_multiplier or Decimal('0.5')  # Varsayılan %50
            child_price = applied_price * Decimal(str(paid_children_count)) * child_mult
            breakdown['child_multiplier'] = child_mult
            breakdown['paid_children_count'] = paid_children_count
        
        breakdown['free_children_count'] = free_children_count
        breakdown['total_children'] = children
        
        total_price = adult_price + child_price
        breakdown['calculation'] = 'per_person'
    
    # Toplam indirim uygula
    if discount_rate and discount_rate > 0:
        discount_amount = total_price * discount_rate
        total_price = total_price - discount_amount
        breakdown['discount_rate'] = discount_rate
        breakdown['discount_amount'] = discount_amount
    
    breakdown['final_price'] = total_price
    breakdown['applied_price_type'] = applied_price_type
    
    return {
        'total_price': total_price,
        'adult_price': adult_price,
        'child_price': child_price,
        'free_children_count': free_children_count if pricing_type == 'per_person' else 0,
        'paid_children_count': paid_children_count if pricing_type == 'per_person' else children,
        'applied_price_type': applied_price_type,
        'breakdown': breakdown,
    }


def calculate_free_children(
    children: int,
    child_ages: List[int],
    free_children_rules: List[Dict],
    adults: int = 1,
) -> Tuple[int, int]:
    """
    Ücretsiz çocuk sayısını hesapla
    
    Args:
        children: Toplam çocuk sayısı
        child_ages: Çocuk yaşları listesi
        free_children_rules: Ücretsiz çocuk kuralları
        adults: Yetişkin sayısı
    
    Returns:
        Tuple: (free_children_count, paid_children_count)
    """
    free_count = 0
    paid_count = children
    
    if not free_children_rules or not child_ages:
        return (0, children)
    
    for rule in free_children_rules:
        age_range = rule.get('age_range', (0, 18))
        max_free = rule.get('count', 0)
        with_adults = rule.get('with_adults', 1)
        
        if adults >= with_adults:
            free_in_range = sum(
                1 for age in child_ages
                if age_range[0] <= age <= age_range[1]
            )
            free_in_rule = min(free_in_range, max_free, paid_count)
            free_count += free_in_rule
            paid_count -= free_in_rule
    
    return (free_count, paid_count)

