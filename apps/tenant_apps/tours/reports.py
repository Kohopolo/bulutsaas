"""
Tur Raporlama Sistemi
Sektör standartlarında detaylı raporlar
"""
from django.db.models import Q, Count, Sum, Avg, Max, Min
from django.db.models.functions import TruncDate, TruncMonth, TruncYear
from django.utils import timezone
from datetime import datetime, timedelta
from decimal import Decimal
from .models import (
    Tour, TourReservation, TourPayment, TourDate,
    TourGuest, TourReservationExtraService
)


def get_date_range(request):
    """Tarih aralığı al"""
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    
    if date_from:
        try:
            date_from = datetime.strptime(date_from, '%Y-%m-%d').date()
        except:
            date_from = timezone.now().date() - timedelta(days=30)
    else:
        date_from = timezone.now().date() - timedelta(days=30)
    
    if date_to:
        try:
            date_to = datetime.strptime(date_to, '%Y-%m-%d').date()
        except:
            date_to = timezone.now().date()
    else:
        date_to = timezone.now().date()
    
    return date_from, date_to


def get_period_stats(reservations, payments):
    """Dönem istatistikleri"""
    stats = {
        'total_reservations': reservations.count(),
        'confirmed_reservations': reservations.filter(status='confirmed').count(),
        'pending_reservations': reservations.filter(status='pending').count(),
        'cancelled_reservations': reservations.filter(status='cancelled').count(),
        'total_revenue': payments.filter(status='completed').aggregate(Sum('amount'))['amount__sum'] or Decimal('0'),
        'total_reservation_amount': reservations.aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'total_paid': payments.filter(status='completed').aggregate(Sum('amount'))['amount__sum'] or Decimal('0'),
        'total_pending': reservations.filter(payment_status='pending').aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'total_partial': reservations.filter(payment_status='partial').aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'total_people': reservations.aggregate(Sum('total_people'))['total_people__sum'] or 0,
        'avg_reservation_amount': reservations.aggregate(Avg('total_amount'))['total_amount__avg'] or Decimal('0'),
        'avg_people_per_reservation': reservations.aggregate(Avg('total_people'))['total_people__avg'] or 0,
    }
    
    # Ödeme yöntemleri dağılımı
    payment_methods = payments.filter(status='completed').values('payment_method').annotate(
        total=Sum('amount'),
        count=Count('id')
    ).order_by('-total')
    stats['payment_methods'] = payment_methods
    
    # Durum dağılımı
    status_distribution = reservations.values('status').annotate(
        count=Count('id'),
        total=Sum('total_amount')
    ).order_by('-count')
    stats['status_distribution'] = status_distribution
    
    return stats


def get_daily_stats(date_from, date_to):
    """Günlük istatistikler"""
    daily_stats = []
    current_date = date_from
    
    while current_date <= date_to:
        day_reservations = TourReservation.objects.filter(
            created_at__date=current_date
        )
        day_payments = TourPayment.objects.filter(
            payment_date=current_date,
            status='completed'
        )
        
        daily_stats.append({
            'date': current_date,
            'reservations': day_reservations.count(),
            'revenue': day_payments.aggregate(Sum('amount'))['amount__sum'] or Decimal('0'),
            'people': day_reservations.aggregate(Sum('total_people'))['total_people__sum'] or 0,
        })
        
        current_date += timedelta(days=1)
    
    return daily_stats


def get_top_tours(date_from, date_to, limit=10):
    """En çok satan turlar"""
    top_tours = Tour.objects.filter(
        reservations__created_at__date__gte=date_from,
        reservations__created_at__date__lte=date_to,
        reservations__status__in=['confirmed', 'completed']
    ).annotate(
        total_reservations=Count('reservations'),
        total_revenue=Sum('reservations__total_amount'),
        total_people=Sum('reservations__total_people')
    ).order_by('-total_reservations')[:limit]
    
    return top_tours


def get_top_customers(date_from, date_to, limit=10):
    """En çok rezervasyon yapan müşteriler"""
    from django.db.models import F
    
    top_customers = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to,
        status__in=['confirmed', 'completed']
    ).values(
        'customer_name', 'customer_surname', 'customer_email', 'customer_phone'
    ).annotate(
        reservation_count=Count('id'),
        total_amount=Sum('total_amount'),
        total_people=Sum('total_people')
    ).order_by('-reservation_count')[:limit]
    
    return top_customers


def get_salesperson_performance(date_from, date_to):
    """Satış elemanı performansı"""
    from apps.tenant_apps.core.models import TenantUser
    
    salesperson_stats = TenantUser.objects.filter(
        tour_reservations__created_at__date__gte=date_from,
        tour_reservations__created_at__date__lte=date_to,
        tour_reservations__status__in=['confirmed', 'completed']
    ).annotate(
        reservation_count=Count('tour_reservations'),
        total_revenue=Sum('tour_reservations__total_amount'),
        total_people=Sum('tour_reservations__total_people'),
        avg_reservation_amount=Avg('tour_reservations__total_amount')
    ).order_by('-total_revenue')
    
    return salesperson_stats


def get_capacity_utilization(date_from, date_to):
    """Kontenjan doluluk oranları"""
    tour_dates = TourDate.objects.filter(
        date__gte=date_from,
        date__lte=date_to,
        is_active=True
    ).select_related('tour')
    
    capacity_stats = []
    for tour_date in tour_dates:
        reservations = tour_date.reservations.filter(
            status__in=['confirmed', 'pending']
        )
        
        reserved_adults = reservations.aggregate(Sum('adult_count'))['adult_count__sum'] or 0
        reserved_children = reservations.aggregate(Sum('child_count'))['child_count__sum'] or 0
        
        max_adults = tour_date.max_adults if tour_date.max_adults is not None else tour_date.tour.max_adults
        max_children = tour_date.max_children if tour_date.max_children is not None else tour_date.tour.max_children
        
        adult_utilization = (reserved_adults / max_adults * 100) if max_adults > 0 else 0
        child_utilization = (reserved_children / max_children * 100) if max_children > 0 else 0
        total_utilization = ((reserved_adults + reserved_children) / (max_adults + max_children) * 100) if (max_adults + max_children) > 0 else 0
        
        capacity_stats.append({
            'tour': tour_date.tour,
            'tour_date': tour_date,
            'date': tour_date.date,
            'max_adults': max_adults,
            'max_children': max_children,
            'reserved_adults': reserved_adults,
            'reserved_children': reserved_children,
            'adult_utilization': round(adult_utilization, 2),
            'child_utilization': round(child_utilization, 2),
            'total_utilization': round(total_utilization, 2),
        })
    
    return sorted(capacity_stats, key=lambda x: x['total_utilization'], reverse=True)


def get_cancellation_stats(date_from, date_to):
    """İptal/İade istatistikleri"""
    cancelled_reservations = TourReservation.objects.filter(
        status__in=['cancelled', 'refunded'],
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    )
    
    stats = {
        'total_cancelled': cancelled_reservations.filter(status='cancelled').count(),
        'total_refunded': cancelled_reservations.filter(status='refunded').count(),
        'cancelled_amount': cancelled_reservations.filter(status='cancelled').aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'refunded_amount': cancelled_reservations.filter(status='refunded').aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'cancellation_rate': 0,
    }
    
    total_reservations = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    ).count()
    
    if total_reservations > 0:
        stats['cancellation_rate'] = round((stats['total_cancelled'] / total_reservations) * 100, 2)
    
    # Günlük iptal trendi
    daily_cancellations = cancelled_reservations.values('created_at__date').annotate(
        count=Count('id'),
        amount=Sum('total_amount')
    ).order_by('created_at__date')
    
    stats['daily_cancellations'] = daily_cancellations
    
    return stats

