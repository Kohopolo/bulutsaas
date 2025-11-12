"""
Muhasebe Modülü - Detaylı Raporlama Sistemi
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
from .models import Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, Payment
from .decorators import require_accounting_module


# ==================== DETAYLI RAPORLAR ====================

@login_required
@require_accounting_module
def report_account_detail(request):
    """Hesap detay raporu"""
    account_id = request.GET.get('account')
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    if not account_id:
        messages.error(request, 'Lütfen bir hesap seçin.')
        return redirect('accounting:account_list')
    
    account = Account.objects.filter(pk=account_id, is_deleted=False).first()
    if not account:
        messages.error(request, 'Hesap bulunamadı.')
        return redirect('accounting:account_list')
    
    # Yevmiye kayıtları
    lines = JournalEntryLine.objects.filter(
        account=account,
        journal_entry__entry_date__gte=date_from,
        journal_entry__entry_date__lte=date_to,
        journal_entry__status='posted',
        journal_entry__is_deleted=False
    ).select_related('journal_entry').order_by('journal_entry__entry_date')
    
    # Toplamlar
    total_debit = lines.aggregate(total=Sum('debit'))['total'] or Decimal('0')
    total_credit = lines.aggregate(total=Sum('credit'))['total'] or Decimal('0')
    
    # Başlangıç bakiyesi
    opening_balance = account.opening_balance
    opening_lines = JournalEntryLine.objects.filter(
        account=account,
        journal_entry__entry_date__lt=date_from,
        journal_entry__status='posted',
        journal_entry__is_deleted=False
    )
    opening_debit = opening_lines.aggregate(total=Sum('debit'))['total'] or Decimal('0')
    opening_credit = opening_lines.aggregate(total=Sum('credit'))['total'] or Decimal('0')
    
    if account.account_type in ['asset', 'expense']:
        opening_balance = opening_balance + opening_debit - opening_credit
    else:
        opening_balance = opening_balance + opening_credit - opening_debit
    
    # Kapanış bakiyesi
    if account.account_type in ['asset', 'expense']:
        closing_balance = opening_balance + total_debit - total_credit
    else:
        closing_balance = opening_balance + total_credit - total_debit
    
    # Günlük trend
    daily_trend = lines.annotate(
        day=TruncDay('journal_entry__entry_date')
    ).values('day').annotate(
        debit=Sum('debit'),
        credit=Sum('credit'),
        count=Count('id')
    ).order_by('day')
    
    # Yevmiye kaydı bazında
    by_entry = lines.values('journal_entry__entry_number', 'journal_entry__entry_date', 'journal_entry__description').annotate(
        debit=Sum('debit'),
        credit=Sum('credit')
    ).order_by('journal_entry__entry_date')
    
    accounts = Account.objects.filter(is_active=True, is_deleted=False).order_by('code')
    
    context = {
        'account': account,
        'accounts': accounts,
        'date_from': date_from,
        'date_to': date_to,
        'lines': lines,
        'total_debit': total_debit,
        'total_credit': total_credit,
        'opening_balance': opening_balance,
        'closing_balance': closing_balance,
        'daily_trend': daily_trend,
        'by_entry': by_entry,
    }
    return render(request, 'tenant/accounting/reports/account_detail.html', context)


@login_required
@require_accounting_module
def report_period_comparison(request):
    """Dönemsel karşılaştırma raporu"""
    period1_from = request.GET.get('period1_from', (timezone.now().date() - timedelta(days=60)).isoformat())
    period1_to = request.GET.get('period1_to', (timezone.now().date() - timedelta(days=30)).isoformat())
    period2_from = request.GET.get('period2_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    period2_to = request.GET.get('period2_to', timezone.now().date().isoformat())
    
    # Dönem 1
    period1_lines = JournalEntryLine.objects.filter(
        journal_entry__entry_date__gte=period1_from,
        journal_entry__entry_date__lte=period1_to,
        journal_entry__status='posted',
        journal_entry__is_deleted=False
    )
    
    period1_by_account = period1_lines.values('account__code', 'account__name', 'account__account_type').annotate(
        debit=Sum('debit'),
        credit=Sum('credit')
    )
    
    # Dönem 2
    period2_lines = JournalEntryLine.objects.filter(
        journal_entry__entry_date__gte=period2_from,
        journal_entry__entry_date__lte=period2_to,
        journal_entry__status='posted',
        journal_entry__is_deleted=False
    )
    
    period2_by_account = period2_lines.values('account__code', 'account__name', 'account__account_type').annotate(
        debit=Sum('debit'),
        credit=Sum('credit')
    )
    
    # Karşılaştırma
    comparison = []
    all_accounts = set()
    for item in period1_by_account:
        all_accounts.add((item['account__code'], item['account__name'], item['account__account_type']))
    for item in period2_by_account:
        all_accounts.add((item['account__code'], item['account__name'], item['account__account_type']))
    
    for code, name, acc_type in all_accounts:
        p1 = next((x for x in period1_by_account if x['account__code'] == code), None)
        p2 = next((x for x in period2_by_account if x['account__code'] == code), None)
        
        p1_debit = p1['debit'] or Decimal('0') if p1 else Decimal('0')
        p1_credit = p1['credit'] or Decimal('0') if p1 else Decimal('0')
        p2_debit = p2['debit'] or Decimal('0') if p2 else Decimal('0')
        p2_credit = p2['credit'] or Decimal('0') if p2 else Decimal('0')
        
        if acc_type in ['asset', 'expense']:
            p1_balance = p1_debit - p1_credit
            p2_balance = p2_debit - p2_credit
        else:
            p1_balance = p1_credit - p1_debit
            p2_balance = p2_credit - p2_debit
        
        change = p2_balance - p1_balance
        change_percent = (change / p1_balance * 100) if p1_balance != 0 else Decimal('0')
        
        comparison.append({
            'code': code,
            'name': name,
            'account_type': acc_type,
            'period1_balance': p1_balance,
            'period2_balance': p2_balance,
            'change': change,
            'change_percent': change_percent,
        })
    
    comparison.sort(key=lambda x: abs(x['change']), reverse=True)
    
    context = {
        'period1_from': period1_from,
        'period1_to': period1_to,
        'period2_from': period2_from,
        'period2_to': period2_to,
        'comparison': comparison,
    }
    return render(request, 'tenant/accounting/reports/period_comparison.html', context)


@login_required
@require_accounting_module
def report_invoice_analysis(request):
    """Fatura analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    invoices = Invoice.objects.filter(
        invoice_date__gte=date_from,
        invoice_date__lte=date_to,
        is_deleted=False
    )
    
    # Toplamlar
    total_invoices = invoices.count()
    total_amount = invoices.aggregate(total=Sum('total_amount'))['total'] or Decimal('0')
    total_paid = invoices.filter(status='paid').aggregate(total=Sum('total_amount'))['total'] or Decimal('0')
    total_unpaid = invoices.filter(status='unpaid').aggregate(total=Sum('total_amount'))['total'] or Decimal('0')
    
    # Fatura tipi bazında
    by_type = invoices.values('invoice_type').annotate(
        count=Count('id'),
        total=Sum('total_amount'),
        paid=Sum('total_amount', filter=Q(status='paid')),
        unpaid=Sum('total_amount', filter=Q(status='unpaid'))
    )
    
    # Durum bazında
    by_status = invoices.values('status').annotate(
        count=Count('id'),
        total=Sum('total_amount')
    )
    
    # Günlük trend
    daily_trend = invoices.annotate(
        day=TruncDay('invoice_date')
    ).values('day').annotate(
        count=Count('id'),
        total=Sum('total_amount'),
        paid=Sum('total_amount', filter=Q(status='paid'))
    ).order_by('day')
    
    # Ortalama fatura tutarı
    avg_amount = total_amount / total_invoices if total_invoices > 0 else Decimal('0')
    
    # Ödeme oranı
    payment_rate = (total_paid / total_amount * 100) if total_amount > 0 else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'total_invoices': total_invoices,
        'total_amount': total_amount,
        'total_paid': total_paid,
        'total_unpaid': total_unpaid,
        'by_type': by_type,
        'by_status': by_status,
        'daily_trend': daily_trend,
        'avg_amount': avg_amount,
        'payment_rate': payment_rate,
    }
    return render(request, 'tenant/accounting/reports/invoice_analysis.html', context)


@login_required
@require_accounting_module
def report_payment_analysis(request):
    """Ödeme analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    payments = Payment.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        is_deleted=False
    )
    
    # Toplamlar
    total_payments = payments.count()
    total_amount = payments.aggregate(total=Sum('amount'))['total'] or Decimal('0')
    total_completed = payments.filter(status='completed').aggregate(total=Sum('amount'))['total'] or Decimal('0')
    total_pending = payments.filter(status='pending').aggregate(total=Sum('amount'))['total'] or Decimal('0')
    
    # Ödeme yöntemi bazında
    by_method = payments.values('payment_method').annotate(
        count=Count('id'),
        total=Sum('amount'),
        completed=Sum('amount', filter=Q(status='completed')),
        pending=Sum('amount', filter=Q(status='pending'))
    ).order_by('-total')
    
    # Durum bazında
    by_status = payments.values('status').annotate(
        count=Count('id'),
        total=Sum('amount')
    )
    
    # Günlük trend
    daily_trend = payments.annotate(
        day=TruncDay('payment_date')
    ).values('day').annotate(
        count=Count('id'),
        total=Sum('amount'),
        completed=Sum('amount', filter=Q(status='completed'))
    ).order_by('day')
    
    # Ortalama ödeme tutarı
    avg_amount = total_amount / total_payments if total_payments > 0 else Decimal('0')
    
    # Tamamlanma oranı
    completion_rate = (total_completed / total_amount * 100) if total_amount > 0 else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'total_payments': total_payments,
        'total_amount': total_amount,
        'total_completed': total_completed,
        'total_pending': total_pending,
        'by_method': by_method,
        'by_status': by_status,
        'daily_trend': daily_trend,
        'avg_amount': avg_amount,
        'completion_rate': completion_rate,
    }
    return render(request, 'tenant/accounting/reports/payment_analysis.html', context)


@login_required
@require_accounting_module
def report_journal_entry_analysis(request):
    """Yevmiye kaydı analiz raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    entries = JournalEntry.objects.filter(
        entry_date__gte=date_from,
        entry_date__lte=date_to,
        is_deleted=False
    )
    
    # Toplamlar
    total_entries = entries.count()
    posted_entries = entries.filter(status='posted').count()
    draft_entries = entries.filter(status='draft').count()
    
    # Toplam borç/alacak
    total_debit = Decimal('0')
    total_credit = Decimal('0')
    for entry in entries.filter(status='posted'):
        total_debit += entry.get_total_debit()
        total_credit += entry.get_total_credit()
    
    # Modül bazında
    by_module = entries.values('source_module').annotate(
        count=Count('id'),
        posted=Count('id', filter=Q(status='posted')),
        draft=Count('id', filter=Q(status='draft'))
    ).order_by('-count')
    
    # Günlük trend
    daily_trend = entries.annotate(
        day=TruncDay('entry_date')
    ).values('day').annotate(
        count=Count('id'),
        posted=Count('id', filter=Q(status='posted')),
        draft=Count('id', filter=Q(status='draft'))
    ).order_by('day')
    
    # Ortalama kayıt sayısı
    avg_daily_entries = total_entries / ((datetime.strptime(date_to, '%Y-%m-%d').date() - datetime.strptime(date_from, '%Y-%m-%d').date()).days + 1) if date_from and date_to else Decimal('0')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'total_entries': total_entries,
        'posted_entries': posted_entries,
        'draft_entries': draft_entries,
        'total_debit': total_debit,
        'total_credit': total_credit,
        'by_module': by_module,
        'daily_trend': daily_trend,
        'avg_daily_entries': avg_daily_entries,
    }
    return render(request, 'tenant/accounting/reports/journal_entry_analysis.html', context)


@login_required
@require_accounting_module
def report_export_csv(request):
    """Rapor CSV export"""
    report_type = request.GET.get('type', 'journal_entries')
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    import csv
    
    response = HttpResponse(content_type='text/csv; charset=utf-8')
    response['Content-Disposition'] = f'attachment; filename="muhasebe_raporu_{date_from}_{date_to}.csv"'
    
    writer = csv.writer(response)
    
    if report_type == 'journal_entries':
        writer.writerow(['Tarih', 'Yevmiye No', 'Açıklama', 'Hesap', 'Borç', 'Alacak', 'Durum'])
        
        entries = JournalEntry.objects.filter(
            entry_date__gte=date_from,
            entry_date__lte=date_to,
            is_deleted=False
        ).select_related().prefetch_related('lines__account').order_by('entry_date')
        
        for entry in entries:
            for line in entry.lines.all():
                writer.writerow([
                    entry.entry_date.strftime('%d.%m.%Y'),
                    entry.entry_number,
                    entry.description[:50] if entry.description else '-',
                    f"{line.account.code} - {line.account.name}",
                    str(line.debit),
                    str(line.credit),
                    entry.get_status_display(),
                ])
    
    elif report_type == 'invoices':
        writer.writerow(['Tarih', 'Fatura No', 'Müşteri', 'Tip', 'Tutar', 'Durum', 'Para Birimi'])
        
        invoices = Invoice.objects.filter(
            invoice_date__gte=date_from,
            invoice_date__lte=date_to,
            is_deleted=False
        ).order_by('invoice_date')
        
        for invoice in invoices:
            writer.writerow([
                invoice.invoice_date.strftime('%d.%m.%Y'),
                invoice.invoice_number,
                invoice.customer_name,
                invoice.get_invoice_type_display(),
                str(invoice.total_amount),
                invoice.get_status_display(),
                invoice.currency,
            ])
    
    return response

