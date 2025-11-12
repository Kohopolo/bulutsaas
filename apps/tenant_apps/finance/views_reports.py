"""
Kasa Yönetimi - Detaylı Raporlama Sistemi
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
from .models import CashAccount, CashTransaction, CashFlow
from .decorators import require_finance_module


# ==================== DETAYLI RAPORLAR ====================

@login_required
@require_finance_module
def report_daily_summary(request):
    """Günlük özet raporu"""
    date = request.GET.get('date', timezone.now().date().isoformat())
    
    transactions = CashTransaction.objects.filter(
        payment_date__date=date,
        status='completed',
        is_deleted=False
    )
    
    # Gelir-Gider özeti
    total_income = transactions.filter(transaction_type='income').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    total_expense = transactions.filter(transaction_type='expense').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    net = total_income - total_expense
    
    # Ödeme yöntemi bazında
    by_payment_method = transactions.values('payment_method').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    )
    
    # Hesap bazında
    by_account = transactions.values('account__name', 'account__code').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    )
    
    # Modül bazında
    by_module = transactions.values('source_module').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    )
    
    context = {
        'date': date,
        'total_income': total_income,
        'total_expense': total_expense,
        'net': net,
        'transaction_count': transactions.count(),
        'by_payment_method': by_payment_method,
        'by_account': by_account,
        'by_module': by_module,
        'transactions': transactions[:50],  # Son 50 işlem
    }
    return render(request, 'tenant/finance/reports/daily_summary.html', context)


@login_required
@require_finance_module
def report_monthly_summary(request):
    """Aylık özet raporu"""
    year = request.GET.get('year', timezone.now().year)
    month = request.GET.get('month', timezone.now().month)
    
    transactions = CashTransaction.objects.filter(
        payment_date__year=year,
        payment_date__month=month,
        status='completed',
        is_deleted=False
    )
    
    # Toplamlar
    total_income = transactions.filter(transaction_type='income').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    total_expense = transactions.filter(transaction_type='expense').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    net = total_income - total_expense
    
    # Günlük trend
    daily_trend = transactions.annotate(
        day=TruncDay('payment_date')
    ).values('day').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('day')
    
    # Ödeme yöntemi bazında
    by_payment_method = transactions.values('payment_method').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Hesap bazında
    by_account = transactions.values('account__name', 'account__code').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Modül bazında
    by_module = transactions.values('source_module').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Ortalama günlük gelir/gider
    days_in_month = (timezone.now().replace(year=int(year), month=int(month), day=1) + timedelta(days=32)).replace(day=1) - timedelta(days=1)
    avg_daily_income = total_income / days_in_month.day if days_in_month.day > 0 else Decimal('0')
    avg_daily_expense = total_expense / days_in_month.day if days_in_month.day > 0 else Decimal('0')
    
    context = {
        'year': year,
        'month': month,
        'total_income': total_income,
        'total_expense': total_expense,
        'net': net,
        'transaction_count': transactions.count(),
        'daily_trend': daily_trend,
        'by_payment_method': by_payment_method,
        'by_account': by_account,
        'by_module': by_module,
        'avg_daily_income': avg_daily_income,
        'avg_daily_expense': avg_daily_expense,
    }
    return render(request, 'tenant/finance/reports/monthly_summary.html', context)


@login_required
@require_finance_module
def report_yearly_summary(request):
    """Yıllık özet raporu"""
    year = request.GET.get('year', timezone.now().year)
    
    transactions = CashTransaction.objects.filter(
        payment_date__year=year,
        status='completed',
        is_deleted=False
    )
    
    # Toplamlar
    total_income = transactions.filter(transaction_type='income').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    total_expense = transactions.filter(transaction_type='expense').aggregate(
        total=Sum('amount')
    )['total'] or Decimal('0')
    
    net = total_income - total_expense
    
    # Aylık trend
    monthly_trend = transactions.annotate(
        month=TruncMonth('payment_date')
    ).values('month').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('month')
    
    # Ödeme yöntemi bazında
    by_payment_method = transactions.values('payment_method').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Hesap bazında
    by_account = transactions.values('account__name', 'account__code').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Modül bazında
    by_module = transactions.values('source_module').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('-income')
    
    # Ortalama aylık gelir/gider
    avg_monthly_income = total_income / 12
    avg_monthly_expense = total_expense / 12
    
    context = {
        'year': year,
        'total_income': total_income,
        'total_expense': total_expense,
        'net': net,
        'transaction_count': transactions.count(),
        'monthly_trend': monthly_trend,
        'by_payment_method': by_payment_method,
        'by_account': by_account,
        'by_module': by_module,
        'avg_monthly_income': avg_monthly_income,
        'avg_monthly_expense': avg_monthly_expense,
    }
    return render(request, 'tenant/finance/reports/yearly_summary.html', context)


@login_required
@require_finance_module
def report_payment_method_analysis(request):
    """Ödeme yöntemi analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    transactions = CashTransaction.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        status='completed',
        is_deleted=False
    )
    
    # Ödeme yöntemi bazında detaylı analiz
    analysis = transactions.values('payment_method').annotate(
        total_count=Count('id'),
        income_count=Count('id', filter=Q(transaction_type='income')),
        expense_count=Count('id', filter=Q(transaction_type='expense')),
        total_income=Sum('amount', filter=Q(transaction_type='income')),
        total_expense=Sum('amount', filter=Q(transaction_type='expense')),
        avg_amount=Avg('amount'),
        min_amount=Min('amount'),
        max_amount=Max('amount'),
    ).order_by('-total_income')
    
    # Günlük trend (ödeme yöntemi bazında)
    daily_trend = transactions.annotate(
        day=TruncDay('payment_date')
    ).values('day', 'payment_method').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('day', 'payment_method')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'analysis': analysis,
        'daily_trend': daily_trend,
    }
    return render(request, 'tenant/finance/reports/payment_method_analysis.html', context)


@login_required
@require_finance_module
def report_module_analysis(request):
    """Modül bazında analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    transactions = CashTransaction.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        status='completed',
        is_deleted=False
    ).exclude(source_module='')
    
    # Modül bazında detaylı analiz
    analysis = transactions.values('source_module').annotate(
        total_count=Count('id'),
        income_count=Count('id', filter=Q(transaction_type='income')),
        expense_count=Count('id', filter=Q(transaction_type='expense')),
        total_income=Sum('amount', filter=Q(transaction_type='income')),
        total_expense=Sum('amount', filter=Q(transaction_type='expense')),
        avg_amount=Avg('amount'),
        min_amount=Min('amount'),
        max_amount=Max('amount'),
    ).order_by('-total_income')
    
    # Günlük trend (modül bazında)
    daily_trend = transactions.annotate(
        day=TruncDay('payment_date')
    ).values('day', 'source_module').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense')),
        count=Count('id')
    ).order_by('day', 'source_module')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'analysis': analysis,
        'daily_trend': daily_trend,
    }
    return render(request, 'tenant/finance/reports/module_analysis.html', context)


@login_required
@require_finance_module
def report_trend_analysis(request):
    """Trend analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=90)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    period = request.GET.get('period', 'daily')  # daily, weekly, monthly
    
    transactions = CashTransaction.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        status='completed',
        is_deleted=False
    )
    
    if period == 'daily':
        trend = transactions.annotate(
            period=TruncDay('payment_date')
        ).values('period').annotate(
            income=Sum('amount', filter=Q(transaction_type='income')),
            expense=Sum('amount', filter=Q(transaction_type='expense')),
            count=Count('id')
        ).order_by('period')
    elif period == 'weekly':
        # Haftalık trend (basitleştirilmiş - gün bazında grupla)
        trend = transactions.annotate(
            period=TruncDay('payment_date')
        ).values('period').annotate(
            income=Sum('amount', filter=Q(transaction_type='income')),
            expense=Sum('amount', filter=Q(transaction_type='expense')),
            count=Count('id')
        ).order_by('period')
    else:  # monthly
        trend = transactions.annotate(
            period=TruncMonth('payment_date')
        ).values('period').annotate(
            income=Sum('amount', filter=Q(transaction_type='income')),
            expense=Sum('amount', filter=Q(transaction_type='expense')),
            count=Count('id')
        ).order_by('period')
    
    # İstatistikler
    total_income = sum(item['income'] or Decimal('0') for item in trend)
    total_expense = sum(item['expense'] or Decimal('0') for item in trend)
    avg_income = total_income / len(trend) if trend else Decimal('0')
    avg_expense = total_expense / len(trend) if trend else Decimal('0')
    
    # En yüksek/düşük günler
    if trend:
        max_income_day = max(trend, key=lambda x: x['income'] or Decimal('0'))
        max_expense_day = max(trend, key=lambda x: x['expense'] or Decimal('0'))
    else:
        max_income_day = None
        max_expense_day = None
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'period': period,
        'trend': trend,
        'total_income': total_income,
        'total_expense': total_expense,
        'avg_income': avg_income,
        'avg_expense': avg_expense,
        'max_income_day': max_income_day,
        'max_expense_day': max_expense_day,
    }
    return render(request, 'tenant/finance/reports/trend_analysis.html', context)


@login_required
@require_finance_module
def report_export_csv(request):
    """Rapor CSV export"""
    report_type = request.GET.get('type', 'transactions')
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    import csv
    
    response = HttpResponse(content_type='text/csv; charset=utf-8')
    response['Content-Disposition'] = f'attachment; filename="kasa_raporu_{date_from}_{date_to}.csv"'
    
    writer = csv.writer(response)
    writer.writerow(['Tarih', 'İşlem No', 'Hesap', 'Tip', 'Ödeme Yöntemi', 'Tutar', 'Para Birimi', 'Durum', 'Açıklama'])
    
    transactions = CashTransaction.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        is_deleted=False
    ).select_related('account').order_by('payment_date')
    
    for t in transactions:
        writer.writerow([
            t.payment_date.strftime('%d.%m.%Y'),
            t.transaction_number,
            t.account.name if t.account else '-',
            t.get_transaction_type_display(),
            t.get_payment_method_display(),
            str(t.amount),
            t.currency,
            t.get_status_display(),
            t.description[:50] if t.description else '-',
        ])
    
    return response

