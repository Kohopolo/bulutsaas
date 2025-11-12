"""
İade Yönetimi - Detaylı Raporlama Sistemi
Sektör standartlarının üzerinde profesyonel raporlar
"""
from django.shortcuts import render, redirect
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.db.models import Q, Count, Sum, Avg, Min, Max
from django.db.models.functions import TruncDate, TruncMonth, TruncYear, TruncDay
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal
from datetime import datetime, timedelta
from .models import RefundPolicy, RefundRequest, RefundTransaction
from .decorators import require_refunds_module


# ==================== DETAYLI RAPORLAR ====================

@login_required
@require_refunds_module
def report_trend_analysis(request):
    """İade trend analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=90)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    period = request.GET.get('period', 'daily')  # daily, weekly, monthly
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    )
    
    if period == 'daily':
        trend = requests.annotate(
            period=TruncDay('request_date')
        ).values('period').annotate(
            count=Count('id'),
            total_original=Sum('original_amount'),
            total_refund=Sum('net_refund'),
            total_fee=Sum('processing_fee')
        ).order_by('period')
    elif period == 'weekly':
        trend = requests.annotate(
            period=TruncDay('request_date')
        ).values('period').annotate(
            count=Count('id'),
            total_original=Sum('original_amount'),
            total_refund=Sum('net_refund'),
            total_fee=Sum('processing_fee')
        ).order_by('period')
    else:  # monthly
        trend = requests.annotate(
            period=TruncMonth('request_date')
        ).values('period').annotate(
            count=Count('id'),
            total_original=Sum('original_amount'),
            total_refund=Sum('net_refund'),
            total_fee=Sum('processing_fee')
        ).order_by('period')
    
    # İstatistikler
    total_requests = sum(item['count'] for item in trend)
    total_original = sum(item['total_original'] or Decimal('0') for item in trend)
    total_refund = sum(item['total_refund'] or Decimal('0') for item in trend)
    total_fee = sum(item['total_fee'] or Decimal('0') for item in trend)
    
    avg_request_amount = total_original / total_requests if total_requests > 0 else Decimal('0')
    avg_refund_amount = total_refund / total_requests if total_requests > 0 else Decimal('0')
    refund_rate = (total_refund / total_original * 100) if total_original > 0 else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'period': period,
        'trend': trend,
        'total_requests': total_requests,
        'total_original': total_original,
        'total_refund': total_refund,
        'total_fee': total_fee,
        'avg_request_amount': avg_request_amount,
        'avg_refund_amount': avg_refund_amount,
        'refund_rate': refund_rate,
    }
    return render(request, 'tenant/refunds/reports/trend_analysis.html', context)


@login_required
@require_refunds_module
def report_customer_analysis(request):
    """Müşteri bazında iade analizi"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    )
    
    # Müşteri bazında analiz
    by_customer = requests.values('customer_name', 'customer_email').annotate(
        count=Count('id'),
        total_original=Sum('original_amount'),
        total_refund=Sum('net_refund'),
        total_fee=Sum('processing_fee'),
        approved=Count('id', filter=Q(status='approved')),
        rejected=Count('id', filter=Q(status='rejected')),
        completed=Count('id', filter=Q(status='completed'))
    ).order_by('-count')
    
    # En çok iade talep eden müşteriler
    top_customers = by_customer[:10]
    
    # Ortalama iade tutarı (müşteri bazında)
    for customer in by_customer:
        customer['avg_refund'] = customer['total_refund'] / customer['count'] if customer['count'] > 0 else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'by_customer': by_customer,
        'top_customers': top_customers,
    }
    return render(request, 'tenant/refunds/reports/customer_analysis.html', context)


@login_required
@require_refunds_module
def report_refund_method_analysis(request):
    """İade yöntemi bazında analiz"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    )
    
    # İade yöntemi bazında
    by_method = requests.values('refund_method').annotate(
        count=Count('id'),
        total_original=Sum('original_amount'),
        total_refund=Sum('net_refund'),
        total_fee=Sum('processing_fee'),
        avg_refund=Avg('net_refund'),
        min_refund=Min('net_refund'),
        max_refund=Max('net_refund')
    ).order_by('-count')
    
    # Günlük trend (yöntem bazında)
    daily_trend = requests.annotate(
        day=TruncDay('request_date')
    ).values('day', 'refund_method').annotate(
        count=Count('id'),
        total_refund=Sum('net_refund')
    ).order_by('day', 'refund_method')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'by_method': by_method,
        'daily_trend': daily_trend,
    }
    return render(request, 'tenant/refunds/reports/refund_method_analysis.html', context)


@login_required
@require_refunds_module
def report_processing_time_analysis(request):
    """İşlem süresi analizi"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    ).exclude(processed_at__isnull=True)
    
    # İşlem süresi hesapla (gün cinsinden)
    processing_times = []
    for req in requests:
        if req.processed_at and req.request_date:
            delta = req.processed_at.date() - req.request_date
            processing_times.append({
                'request': req,
                'days': delta.days,
                'status': req.status,
            })
    
    # İstatistikler
    if processing_times:
        avg_days = sum(item['days'] for item in processing_times) / len(processing_times)
        min_days = min(item['days'] for item in processing_times)
        max_days = max(item['days'] for item in processing_times)
    else:
        avg_days = Decimal('0')
        min_days = 0
        max_days = 0
    
    # Durum bazında ortalama süre
    by_status = {}
    for item in processing_times:
        status = item['status']
        if status not in by_status:
            by_status[status] = []
        by_status[status].append(item['days'])
    
    status_avg = {}
    for status, days_list in by_status.items():
        status_avg[status] = sum(days_list) / len(days_list) if days_list else Decimal('0')
    
    # Süre aralıkları
    time_ranges = {
        '0-1': len([x for x in processing_times if x['days'] <= 1]),
        '2-3': len([x for x in processing_times if 2 <= x['days'] <= 3]),
        '4-7': len([x for x in processing_times if 4 <= x['days'] <= 7]),
        '8-14': len([x for x in processing_times if 8 <= x['days'] <= 14]),
        '15+': len([x for x in processing_times if x['days'] >= 15]),
    }
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'processing_times': processing_times[:50],  # Son 50 kayıt
        'avg_days': avg_days,
        'min_days': min_days,
        'max_days': max_days,
        'status_avg': status_avg,
        'time_ranges': time_ranges,
    }
    return render(request, 'tenant/refunds/reports/processing_time_analysis.html', context)


@login_required
@require_refunds_module
def report_policy_performance(request):
    """Politika performans raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    ).exclude(refund_policy__isnull=True)
    
    # Politika bazında analiz
    by_policy = requests.values('refund_policy__name', 'refund_policy__code', 'refund_policy__policy_type').annotate(
        count=Count('id'),
        total_original=Sum('original_amount'),
        total_refund=Sum('net_refund'),
        total_fee=Sum('processing_fee'),
        approved=Count('id', filter=Q(status='approved')),
        rejected=Count('id', filter=Q(status='rejected')),
        completed=Count('id', filter=Q(status='completed')),
        avg_refund=Avg('net_refund')
    ).order_by('-count')
    
    # Onay oranı
    for policy in by_policy:
        policy['approval_rate'] = (policy['approved'] / policy['count'] * 100) if policy['count'] > 0 else Decimal('0')
        policy['refund_rate'] = (policy['total_refund'] / policy['total_original'] * 100) if policy['total_original'] > 0 else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'by_policy': by_policy,
    }
    return render(request, 'tenant/refunds/reports/policy_performance.html', context)


@login_required
@require_refunds_module
def report_export_csv(request):
    """Rapor CSV export"""
    report_type = request.GET.get('type', 'requests')
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    import csv
    
    response = HttpResponse(content_type='text/csv; charset=utf-8')
    response['Content-Disposition'] = f'attachment; filename="iade_raporu_{date_from}_{date_to}.csv"'
    
    writer = csv.writer(response)
    
    if report_type == 'requests':
        writer.writerow(['Tarih', 'Talep No', 'Müşteri', 'Modül', 'Orijinal Tutar', 'İade Tutarı', 'İşlem Ücreti', 'Net İade', 'Durum', 'Yöntem'])
        
        requests = RefundRequest.objects.filter(
            request_date__date__gte=date_from,
            request_date__date__lte=date_to,
            is_deleted=False
        ).order_by('request_date')
        
        for req in requests:
            writer.writerow([
                req.request_date.strftime('%d.%m.%Y'),
                req.request_number,
                req.customer_name,
                req.source_module or '-',
                str(req.original_amount),
                str(req.refund_amount),
                str(req.processing_fee),
                str(req.net_refund),
                req.get_status_display(),
                req.get_refund_method_display(),
            ])
    
    elif report_type == 'transactions':
        writer.writerow(['Tarih', 'İşlem No', 'Talep No', 'Tutar', 'Yöntem', 'Durum', 'Referans'])
        
        transactions = RefundTransaction.objects.filter(
            transaction_date__date__gte=date_from,
            transaction_date__date__lte=date_to,
            is_deleted=False
        ).select_related('refund_request').order_by('transaction_date')
        
        for trans in transactions:
            writer.writerow([
                trans.transaction_date.strftime('%d.%m.%Y'),
                trans.transaction_number,
                trans.refund_request.request_number if trans.refund_request else '-',
                str(trans.amount),
                trans.get_refund_method_display(),
                trans.get_status_display(),
                trans.payment_reference or '-',
            ])
    
    return response

