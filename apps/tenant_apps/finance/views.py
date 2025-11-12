"""
Kasa Yönetim Views
Profesyonel kasa yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods, require_GET, require_POST
from django.db.models import Q, Count, Sum, Avg
from django.db.models.functions import TruncDate, TruncMonth, TruncYear
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal
from datetime import datetime, timedelta
from .models import CashAccount, CashTransaction, CashFlow
from .forms import CashAccountForm, CashTransactionForm, CashFlowForm
from .decorators import require_finance_module
from . import views_reports


# ==================== KASA HESAPLARI ====================

@login_required
@require_finance_module
def account_list(request):
    """Kasa hesapları listesi"""
    accounts = CashAccount.objects.filter(is_deleted=False)
    
    # Filtreleme
    account_type = request.GET.get('account_type')
    if account_type:
        accounts = accounts.filter(account_type=account_type)
    
    currency = request.GET.get('currency')
    if currency:
        accounts = accounts.filter(currency=currency)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        accounts = accounts.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        accounts = accounts.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(bank_name__icontains=search) |
            Q(account_number__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'sort_order')
    accounts = accounts.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(accounts, 20)
    page = request.GET.get('page')
    accounts = paginator.get_page(page)
    
    context = {
        'accounts': accounts,
        'account_types': CashAccount.ACCOUNT_TYPE_CHOICES,
        'currencies': CashAccount.CURRENCY_CHOICES,
    }
    return render(request, 'tenant/finance/accounts/list.html', context)


@login_required
@require_finance_module
def account_detail(request, pk):
    """Kasa hesabı detayı"""
    account = get_object_or_404(CashAccount, pk=pk, is_deleted=False)
    
    # Son işlemler
    recent_transactions = account.transactions.filter(is_deleted=False).order_by('-payment_date')[:10]
    
    # İstatistikler
    today = timezone.now().date()
    this_month_start = today.replace(day=1)
    
    monthly_income = account.transactions.filter(
        transaction_type='income',
        status='completed',
        payment_date__date__gte=this_month_start,
        is_deleted=False
    ).aggregate(total=Sum('amount'))['total'] or Decimal('0')
    
    monthly_expense = account.transactions.filter(
        transaction_type='expense',
        status='completed',
        payment_date__date__gte=this_month_start,
        is_deleted=False
    ).aggregate(total=Sum('amount'))['total'] or Decimal('0')
    
    context = {
        'account': account,
        'recent_transactions': recent_transactions,
        'monthly_income': monthly_income,
        'monthly_expense': monthly_expense,
        'monthly_net': monthly_income - monthly_expense,
    }
    return render(request, 'tenant/finance/accounts/detail.html', context)


@login_required
@require_finance_module
def account_create(request):
    """Yeni kasa hesabı oluştur"""
    if request.method == 'POST':
        form = CashAccountForm(request.POST)
        if form.is_valid():
            account = form.save()
            messages.success(request, f'Kasa hesabı "{account.name}" başarıyla oluşturuldu.')
            return redirect('finance:account_detail', pk=account.pk)
    else:
        form = CashAccountForm()
    
    context = {'form': form}
    return render(request, 'tenant/finance/accounts/form.html', context)


@login_required
@require_finance_module
def account_update(request, pk):
    """Kasa hesabı güncelle"""
    account = get_object_or_404(CashAccount, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = CashAccountForm(request.POST, instance=account)
        if form.is_valid():
            account = form.save()
            messages.success(request, f'Kasa hesabı "{account.name}" başarıyla güncellendi.')
            return redirect('finance:account_detail', pk=account.pk)
    else:
        form = CashAccountForm(instance=account)
    
    context = {'form': form, 'account': account}
    return render(request, 'tenant/finance/accounts/form.html', context)


@login_required
@require_finance_module
@require_POST
def account_delete(request, pk):
    """Kasa hesabı sil"""
    account = get_object_or_404(CashAccount, pk=pk, is_deleted=False)
    
    # İşlem kontrolü
    if account.transactions.filter(is_deleted=False).exists():
        messages.error(request, 'Bu hesapta işlem bulunduğu için silinemez.')
        return redirect('finance:account_detail', pk=account.pk)
    
    account.is_deleted = True
    account.save()
    messages.success(request, f'Kasa hesabı "{account.name}" başarıyla silindi.')
    return redirect('finance:account_list')


@login_required
@require_finance_module
@require_POST
def account_calculate_balance(request, pk):
    """Kasa hesabı bakiyesini hesapla"""
    account = get_object_or_404(CashAccount, pk=pk, is_deleted=False)
    account.calculate_balance()
    messages.success(request, f'Hesap bakiyesi güncellendi: {account.current_balance} {account.currency}')
    return redirect('finance:account_detail', pk=account.pk)


# ==================== KASA İŞLEMLERİ ====================

@login_required
@require_finance_module
def transaction_list(request):
    """Kasa işlemleri listesi"""
    transactions = CashTransaction.objects.filter(is_deleted=False)
    
    # Filtreleme
    account_id = request.GET.get('account')
    if account_id:
        transactions = transactions.filter(account_id=account_id)
    
    transaction_type = request.GET.get('transaction_type')
    if transaction_type:
        transactions = transactions.filter(transaction_type=transaction_type)
    
    status = request.GET.get('status')
    if status:
        transactions = transactions.filter(status=status)
    
    payment_method = request.GET.get('payment_method')
    if payment_method:
        transactions = transactions.filter(payment_method=payment_method)
    
    source_module = request.GET.get('source_module')
    if source_module:
        transactions = transactions.filter(source_module=source_module)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        transactions = transactions.filter(payment_date__date__gte=date_from)
    if date_to:
        transactions = transactions.filter(payment_date__date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        transactions = transactions.filter(
            Q(transaction_number__icontains=search) |
            Q(description__icontains=search) |
            Q(source_reference__icontains=search) |
            Q(notes__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-payment_date')
    transactions = transactions.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(transactions, 50)
    page = request.GET.get('page')
    transactions = paginator.get_page(page)
    
    # Filtre seçenekleri
    accounts = CashAccount.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'transactions': transactions,
        'accounts': accounts,
        'transaction_types': CashTransaction.TRANSACTION_TYPE_CHOICES,
        'statuses': CashTransaction.STATUS_CHOICES,
        'payment_methods': CashTransaction.PAYMENT_METHOD_CHOICES,
    }
    return render(request, 'tenant/finance/transactions/list.html', context)


@login_required
@require_finance_module
def transaction_detail(request, pk):
    """Kasa işlemi detayı"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    
    context = {
        'transaction': transaction,
    }
    return render(request, 'tenant/finance/transactions/detail.html', context)


@login_required
@require_finance_module
def transaction_create(request):
    """Yeni kasa işlemi oluştur"""
    if request.method == 'POST':
        form = CashTransactionForm(request.POST)
        if form.is_valid():
            transaction = form.save(commit=False)
            transaction.created_by = request.user
            transaction.save()
            
            # Tamamlandı olarak işaretlenmişse
            if transaction.status == 'completed':
                transaction.complete(user=request.user)
            
            messages.success(request, f'Kasa işlemi "{transaction.transaction_number}" başarıyla oluşturuldu.')
            return redirect('finance:transaction_detail', pk=transaction.pk)
    else:
        form = CashTransactionForm()
        # Varsayılan hesap
        default_account = CashAccount.objects.filter(is_default=True, is_active=True, is_deleted=False).first()
        if default_account:
            form.fields['account'].initial = default_account
    
    context = {'form': form}
    return render(request, 'tenant/finance/transactions/form.html', context)


@login_required
@require_finance_module
def transaction_update(request, pk):
    """Kasa işlemi güncelle"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = CashTransactionForm(request.POST, instance=transaction)
        if form.is_valid():
            transaction = form.save()
            messages.success(request, f'Kasa işlemi "{transaction.transaction_number}" başarıyla güncellendi.')
            return redirect('finance:transaction_detail', pk=transaction.pk)
    else:
        form = CashTransactionForm(instance=transaction)
    
    context = {'form': form, 'transaction': transaction}
    return render(request, 'tenant/finance/transactions/form.html', context)


@login_required
@require_finance_module
@require_POST
def transaction_delete(request, pk):
    """Kasa işlemi sil"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    
    if transaction.status == 'completed':
        messages.error(request, 'Tamamlanmış işlemler silinemez. Önce iptal edin veya geri alın.')
        return redirect('finance:transaction_detail', pk=transaction.pk)
    
    transaction.is_deleted = True
    transaction.save()
    messages.success(request, f'Kasa işlemi "{transaction.transaction_number}" başarıyla silindi.')
    return redirect('finance:transaction_list')


@login_required
@require_finance_module
@require_POST
def transaction_complete(request, pk):
    """Kasa işlemini tamamla"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    transaction.complete(user=request.user)
    messages.success(request, f'İşlem "{transaction.transaction_number}" tamamlandı olarak işaretlendi.')
    return redirect('finance:transaction_detail', pk=transaction.pk)


@login_required
@require_finance_module
@require_POST
def transaction_cancel(request, pk):
    """Kasa işlemini iptal et"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    reason = request.POST.get('reason', '')
    transaction.cancel(reason=reason)
    messages.success(request, f'İşlem "{transaction.transaction_number}" iptal edildi.')
    return redirect('finance:transaction_detail', pk=transaction.pk)


@login_required
@require_finance_module
@require_POST
def transaction_reverse(request, pk):
    """Kasa işlemini geri al"""
    transaction = get_object_or_404(CashTransaction, pk=pk, is_deleted=False)
    reason = request.POST.get('reason', '')
    transaction.reverse(reason=reason)
    messages.success(request, f'İşlem "{transaction.transaction_number}" geri alındı.')
    return redirect('finance:transaction_detail', pk=transaction.pk)


# ==================== NAKİT AKIŞI ====================

@login_required
@require_finance_module
def cash_flow_list(request):
    """Nakit akışı listesi"""
    flows = CashFlow.objects.all()
    
    # Filtreleme
    account_id = request.GET.get('account')
    if account_id:
        flows = flows.filter(account_id=account_id)
    
    period_type = request.GET.get('period_type')
    if period_type:
        flows = flows.filter(period_type=period_type)
    
    # Sıralama
    sort_by = request.GET.get('sort', '-period_start')
    flows = flows.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(flows, 20)
    page = request.GET.get('page')
    flows = paginator.get_page(page)
    
    # Filtre seçenekleri
    accounts = CashAccount.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'flows': flows,
        'accounts': accounts,
        'period_types': CashFlow.PERIOD_TYPE_CHOICES,
    }
    return render(request, 'tenant/finance/cash_flow/list.html', context)


@login_required
@require_finance_module
def cash_flow_detail(request, pk):
    """Nakit akışı detayı"""
    flow = get_object_or_404(CashFlow, pk=pk)
    
    # İşlemler
    transactions = CashTransaction.objects.filter(
        account=flow.account,
        payment_date__date__gte=flow.period_start,
        payment_date__date__lte=flow.period_end,
        status='completed',
        is_deleted=False
    ).order_by('payment_date')
    
    context = {
        'flow': flow,
        'transactions': transactions,
    }
    return render(request, 'tenant/finance/cash_flow/detail.html', context)


@login_required
@require_finance_module
def cash_flow_create(request):
    """Yeni nakit akışı oluştur"""
    if request.method == 'POST':
        form = CashFlowForm(request.POST)
        if form.is_valid():
            flow = form.save()
            flow.calculate()
            messages.success(request, f'Nakit akışı başarıyla oluşturuldu ve hesaplandı.')
            return redirect('finance:cash_flow_detail', pk=flow.pk)
    else:
        form = CashFlowForm()
    
    context = {'form': form}
    return render(request, 'tenant/finance/cash_flow/form.html', context)


@login_required
@require_finance_module
@require_POST
def cash_flow_recalculate(request, pk):
    """Nakit akışını yeniden hesapla"""
    flow = get_object_or_404(CashFlow, pk=pk)
    flow.calculate()
    messages.success(request, 'Nakit akışı yeniden hesaplandı.')
    return redirect('finance:cash_flow_detail', pk=flow.pk)


# ==================== RAPORLAR ====================

@login_required
@require_finance_module
def report_balance_sheet(request):
    """Bilanço raporu"""
    # Tüm hesapların bakiyeleri
    accounts = CashAccount.objects.filter(is_active=True, is_deleted=False)
    
    total_balance = sum(acc.current_balance for acc in accounts)
    
    context = {
        'accounts': accounts,
        'total_balance': total_balance,
    }
    return render(request, 'tenant/finance/reports/balance_sheet.html', context)


@login_required
@require_finance_module
def report_income_expense(request):
    """Gelir-Gider raporu"""
    # Tarih aralığı
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    transactions = CashTransaction.objects.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        status='completed',
        is_deleted=False
    )
    
    total_income = transactions.filter(transaction_type='income').aggregate(total=Sum('amount'))['total'] or Decimal('0')
    total_expense = transactions.filter(transaction_type='expense').aggregate(total=Sum('amount'))['total'] or Decimal('0')
    net = total_income - total_expense
    
    # Günlük özet
    daily_summary = transactions.values('payment_date__date').annotate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense'))
    ).order_by('payment_date__date')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'total_income': total_income,
        'total_expense': total_expense,
        'net': net,
        'daily_summary': daily_summary,
    }
    return render(request, 'tenant/finance/reports/income_expense.html', context)


@login_required
@require_finance_module
def report_account_statement(request):
    """Hesap ekstresi raporu"""
    account_id = request.GET.get('account')
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    if not account_id:
        messages.error(request, 'Lütfen bir hesap seçin.')
        return redirect('finance:account_list')
    
    account = get_object_or_404(CashAccount, pk=account_id, is_deleted=False)
    
    transactions = account.transactions.filter(
        payment_date__date__gte=date_from,
        payment_date__date__lte=date_to,
        is_deleted=False
    ).order_by('payment_date')
    
    # Başlangıç bakiyesi (dönem öncesi)
    opening_balance = account.transactions.filter(
        payment_date__date__lt=date_from,
        status='completed',
        is_deleted=False
    ).aggregate(
        income=Sum('amount', filter=Q(transaction_type='income')),
        expense=Sum('amount', filter=Q(transaction_type='expense'))
    )
    
    opening = account.initial_balance
    if opening_balance['income']:
        opening += opening_balance['income']
    if opening_balance['expense']:
        opening -= opening_balance['expense']
    
    # Kapanış bakiyesi
    closing = opening
    for transaction in transactions.filter(status='completed'):
        if transaction.transaction_type == 'income':
            closing += transaction.amount
        elif transaction.transaction_type == 'expense':
            closing -= transaction.amount
    
    accounts = CashAccount.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'account': account,
        'accounts': accounts,
        'date_from': date_from,
        'date_to': date_to,
        'transactions': transactions,
        'opening_balance': opening,
        'closing_balance': closing,
    }
    return render(request, 'tenant/finance/reports/account_statement.html', context)

