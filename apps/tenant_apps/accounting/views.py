"""
Muhasebe Yönetim Views
Profesyonel muhasebe sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_POST
from django.db.models import Q, Count, Sum
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal
from datetime import datetime, timedelta
from .models import Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, Payment
from .forms import AccountForm, JournalEntryForm, JournalEntryLineForm, InvoiceForm, InvoiceLineForm, PaymentForm
from .decorators import require_accounting_module


# ==================== HESAP PLANI ====================

@login_required
@require_accounting_module
def account_list(request):
    """Hesap planı listesi"""
    accounts = Account.objects.filter(is_deleted=False)
    
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
            Q(code__icontains=search) |
            Q(name__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'code')
    accounts = accounts.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(accounts, 50)
    page = request.GET.get('page')
    accounts = paginator.get_page(page)
    
    context = {
        'accounts': accounts,
        'account_types': Account.ACCOUNT_TYPE_CHOICES,
        'currencies': Account.CURRENCY_CHOICES,
    }
    return render(request, 'tenant/accounting/accounts/list.html', context)


@login_required
@require_accounting_module
def account_detail(request, pk):
    """Hesap detayı"""
    account = get_object_or_404(Account, pk=pk, is_deleted=False)
    
    # Son yevmiye kayıtları
    recent_entries = JournalEntryLine.objects.filter(
        account=account,
        journal_entry__status='posted',
        journal_entry__is_deleted=False
    ).select_related('journal_entry').order_by('-journal_entry__entry_date')[:20]
    
    context = {
        'account': account,
        'recent_entries': recent_entries,
    }
    return render(request, 'tenant/accounting/accounts/detail.html', context)


@login_required
@require_accounting_module
def account_create(request):
    """Yeni hesap oluştur"""
    if request.method == 'POST':
        form = AccountForm(request.POST)
        if form.is_valid():
            account = form.save()
            messages.success(request, f'Hesap "{account.code} - {account.name}" başarıyla oluşturuldu.')
            return redirect('accounting:account_detail', pk=account.pk)
    else:
        form = AccountForm()
    
    context = {'form': form}
    return render(request, 'tenant/accounting/accounts/form.html', context)


@login_required
@require_accounting_module
def account_update(request, pk):
    """Hesap güncelle"""
    account = get_object_or_404(Account, pk=pk, is_deleted=False)
    
    if account.is_system:
        messages.error(request, 'Sistem hesapları düzenlenemez.')
        return redirect('accounting:account_detail', pk=account.pk)
    
    if request.method == 'POST':
        form = AccountForm(request.POST, instance=account)
        if form.is_valid():
            account = form.save()
            messages.success(request, f'Hesap "{account.code} - {account.name}" başarıyla güncellendi.')
            return redirect('accounting:account_detail', pk=account.pk)
    else:
        form = AccountForm(instance=account)
    
    context = {'form': form, 'account': account}
    return render(request, 'tenant/accounting/accounts/form.html', context)


@login_required
@require_accounting_module
@require_POST
def account_delete(request, pk):
    """Hesap sil"""
    account = get_object_or_404(Account, pk=pk, is_deleted=False)
    
    if account.is_system:
        messages.error(request, 'Sistem hesapları silinemez.')
        return redirect('accounting:account_detail', pk=account.pk)
    
    # Yevmiye kayıt kontrolü
    if JournalEntryLine.objects.filter(account=account).exists():
        messages.error(request, 'Bu hesapta yevmiye kaydı bulunduğu için silinemez.')
        return redirect('accounting:account_detail', pk=account.pk)
    
    account.is_deleted = True
    account.save()
    messages.success(request, f'Hesap "{account.code} - {account.name}" başarıyla silindi.')
    return redirect('accounting:account_list')


# ==================== YEVMİYE KAYITLARI ====================

@login_required
@require_accounting_module
def journal_entry_list(request):
    """Yevmiye kayıtları listesi"""
    entries = JournalEntry.objects.filter(is_deleted=False)
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        entries = entries.filter(status=status)
    
    source_module = request.GET.get('source_module')
    if source_module:
        entries = entries.filter(source_module=source_module)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        entries = entries.filter(entry_date__gte=date_from)
    if date_to:
        entries = entries.filter(entry_date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        entries = entries.filter(
            Q(entry_number__icontains=search) |
            Q(description__icontains=search) |
            Q(source_reference__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-entry_date')
    entries = entries.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(entries, 50)
    page = request.GET.get('page')
    entries = paginator.get_page(page)
    
    context = {
        'entries': entries,
        'statuses': JournalEntry.STATUS_CHOICES,
    }
    return render(request, 'tenant/accounting/journal_entries/list.html', context)


@login_required
@require_accounting_module
def journal_entry_detail(request, pk):
    """Yevmiye kaydı detayı"""
    entry = get_object_or_404(JournalEntry, pk=pk, is_deleted=False)
    lines = entry.lines.all()
    
    context = {
        'entry': entry,
        'lines': lines,
        'total_debit': entry.get_total_debit(),
        'total_credit': entry.get_total_credit(),
        'is_balanced': entry.is_balanced(),
    }
    return render(request, 'tenant/accounting/journal_entries/detail.html', context)


@login_required
@require_accounting_module
def journal_entry_create(request):
    """Yeni yevmiye kaydı oluştur"""
    if request.method == 'POST':
        form = JournalEntryForm(request.POST)
        if form.is_valid():
            entry = form.save(commit=False)
            entry.created_by = request.user
            entry.save()
            messages.success(request, f'Yevmiye kaydı "{entry.entry_number}" başarıyla oluşturuldu.')
            return redirect('accounting:journal_entry_detail', pk=entry.pk)
    else:
        form = JournalEntryForm()
    
    context = {'form': form}
    return render(request, 'tenant/accounting/journal_entries/form.html', context)


@login_required
@require_accounting_module
def journal_entry_update(request, pk):
    """Yevmiye kaydı güncelle"""
    entry = get_object_or_404(JournalEntry, pk=pk, is_deleted=False)
    
    if entry.status == 'posted':
        messages.error(request, 'Kaydedilmiş yevmiye kayıtları düzenlenemez.')
        return redirect('accounting:journal_entry_detail', pk=entry.pk)
    
    if request.method == 'POST':
        form = JournalEntryForm(request.POST, instance=entry)
        if form.is_valid():
            entry = form.save()
            messages.success(request, f'Yevmiye kaydı "{entry.entry_number}" başarıyla güncellendi.')
            return redirect('accounting:journal_entry_detail', pk=entry.pk)
    else:
        form = JournalEntryForm(instance=entry)
    
    context = {'form': form, 'entry': entry}
    return render(request, 'tenant/accounting/journal_entries/form.html', context)


@login_required
@require_accounting_module
@require_POST
def journal_entry_post(request, pk):
    """Yevmiye kaydını kaydet"""
    entry = get_object_or_404(JournalEntry, pk=pk, is_deleted=False)
    
    try:
        entry.post(user=request.user)
        messages.success(request, f'Yevmiye kaydı "{entry.entry_number}" başarıyla kaydedildi.')
    except ValueError as e:
        messages.error(request, f'Hata: {str(e)}')
    
    return redirect('accounting:journal_entry_detail', pk=entry.pk)


@login_required
@require_accounting_module
@require_POST
def journal_entry_cancel(request, pk):
    """Yevmiye kaydını iptal et"""
    entry = get_object_or_404(JournalEntry, pk=pk, is_deleted=False)
    reason = request.POST.get('reason', '')
    entry.cancel(reason=reason)
    messages.success(request, f'Yevmiye kaydı "{entry.entry_number}" iptal edildi.')
    return redirect('accounting:journal_entry_detail', pk=entry.pk)


# ==================== FATURALAR ====================

@login_required
@require_accounting_module
def invoice_list(request):
    """Fatura listesi"""
    invoices = Invoice.objects.filter(is_deleted=False)
    
    # Filtreleme
    invoice_type = request.GET.get('invoice_type')
    if invoice_type:
        invoices = invoices.filter(invoice_type=invoice_type)
    
    status = request.GET.get('status')
    if status:
        invoices = invoices.filter(status=status)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        invoices = invoices.filter(invoice_date__gte=date_from)
    if date_to:
        invoices = invoices.filter(invoice_date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        invoices = invoices.filter(
            Q(invoice_number__icontains=search) |
            Q(customer_name__icontains=search) |
            Q(customer_tax_id__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-invoice_date')
    invoices = invoices.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(invoices, 50)
    page = request.GET.get('page')
    invoices = paginator.get_page(page)
    
    context = {
        'invoices': invoices,
        'invoice_types': Invoice.INVOICE_TYPE_CHOICES,
        'statuses': Invoice.STATUS_CHOICES,
    }
    return render(request, 'tenant/accounting/invoices/list.html', context)


@login_required
@require_accounting_module
def invoice_detail(request, pk):
    """Fatura detayı"""
    invoice = get_object_or_404(Invoice, pk=pk, is_deleted=False)
    lines = invoice.lines.all()
    payments = invoice.payments.filter(is_deleted=False)
    
    context = {
        'invoice': invoice,
        'lines': lines,
        'payments': payments,
        'remaining_amount': invoice.get_remaining_amount(),
    }
    return render(request, 'tenant/accounting/invoices/detail.html', context)


@login_required
@require_accounting_module
def invoice_create(request):
    """Yeni fatura oluştur"""
    if request.method == 'POST':
        form = InvoiceForm(request.POST)
        if form.is_valid():
            invoice = form.save(commit=False)
            invoice.created_by = request.user
            invoice.save()
            messages.success(request, f'Fatura "{invoice.invoice_number}" başarıyla oluşturuldu.')
            return redirect('accounting:invoice_detail', pk=invoice.pk)
    else:
        form = InvoiceForm()
    
    context = {'form': form}
    return render(request, 'tenant/accounting/invoices/form.html', context)


@login_required
@require_accounting_module
def invoice_update(request, pk):
    """Fatura güncelle"""
    invoice = get_object_or_404(Invoice, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = InvoiceForm(request.POST, instance=invoice)
        if form.is_valid():
            invoice = form.save()
            messages.success(request, f'Fatura "{invoice.invoice_number}" başarıyla güncellendi.')
            return redirect('accounting:invoice_detail', pk=invoice.pk)
    else:
        form = InvoiceForm(instance=invoice)
    
    context = {'form': form, 'invoice': invoice}
    return render(request, 'tenant/accounting/invoices/form.html', context)


@login_required
@require_accounting_module
@require_POST
def invoice_delete(request, pk):
    """Fatura sil"""
    invoice = get_object_or_404(Invoice, pk=pk, is_deleted=False)
    
    if invoice.status == 'paid':
        messages.error(request, 'Ödenmiş faturalar silinemez.')
        return redirect('accounting:invoice_detail', pk=invoice.pk)
    
    invoice.is_deleted = True
    invoice.save()
    messages.success(request, f'Fatura "{invoice.invoice_number}" başarıyla silindi.')
    return redirect('accounting:invoice_list')


# ==================== ÖDEMELER ====================

@login_required
@require_accounting_module
def payment_list(request):
    """Ödeme listesi"""
    payments = Payment.objects.filter(is_deleted=False)
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        payments = payments.filter(status=status)
    
    payment_method = request.GET.get('payment_method')
    if payment_method:
        payments = payments.filter(payment_method=payment_method)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        payments = payments.filter(payment_date__date__gte=date_from)
    if date_to:
        payments = payments.filter(payment_date__date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        payments = payments.filter(
            Q(payment_number__icontains=search) |
            Q(description__icontains=search) |
            Q(source_reference__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-payment_date')
    payments = payments.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(payments, 50)
    page = request.GET.get('page')
    payments = paginator.get_page(page)
    
    context = {
        'payments': payments,
        'statuses': Payment.STATUS_CHOICES,
        'payment_methods': Payment.PAYMENT_METHOD_CHOICES,
    }
    return render(request, 'tenant/accounting/payments/list.html', context)


@login_required
@require_accounting_module
def payment_detail(request, pk):
    """Ödeme detayı"""
    payment = get_object_or_404(Payment, pk=pk, is_deleted=False)
    
    context = {
        'payment': payment,
    }
    return render(request, 'tenant/accounting/payments/detail.html', context)


@login_required
@require_accounting_module
def payment_create(request):
    """Yeni ödeme oluştur"""
    if request.method == 'POST':
        form = PaymentForm(request.POST)
        if form.is_valid():
            payment = form.save(commit=False)
            payment.created_by = request.user
            payment.save()
            messages.success(request, f'Ödeme "{payment.payment_number}" başarıyla oluşturuldu.')
            return redirect('accounting:payment_detail', pk=payment.pk)
    else:
        form = PaymentForm()
    
    context = {'form': form}
    return render(request, 'tenant/accounting/payments/form.html', context)


@login_required
@require_accounting_module
def payment_update(request, pk):
    """Ödeme güncelle"""
    payment = get_object_or_404(Payment, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = PaymentForm(request.POST, instance=payment)
        if form.is_valid():
            payment = form.save()
            messages.success(request, f'Ödeme "{payment.payment_number}" başarıyla güncellendi.')
            return redirect('accounting:payment_detail', pk=payment.pk)
    else:
        form = PaymentForm(instance=payment)
    
    context = {'form': form, 'payment': payment}
    return render(request, 'tenant/accounting/payments/form.html', context)


@login_required
@require_accounting_module
@require_POST
def payment_complete(request, pk):
    """Ödemeyi tamamla"""
    payment = get_object_or_404(Payment, pk=pk, is_deleted=False)
    payment.complete()
    messages.success(request, f'Ödeme "{payment.payment_number}" tamamlandı olarak işaretlendi.')
    return redirect('accounting:payment_detail', pk=payment.pk)


# ==================== RAPORLAR ====================

@login_required
@require_accounting_module
def report_trial_balance(request):
    """Mizan raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    accounts = Account.objects.filter(is_active=True, is_deleted=False)
    
    # Her hesap için borç/alacak toplamlarını hesapla
    trial_balance = []
    total_debit = Decimal('0')
    total_credit = Decimal('0')
    
    for account in accounts:
        lines = JournalEntryLine.objects.filter(
            account=account,
            journal_entry__entry_date__gte=date_from,
            journal_entry__entry_date__lte=date_to,
            journal_entry__status='posted',
            journal_entry__is_deleted=False
        )
        
        debit = lines.aggregate(total=Sum('debit'))['total'] or Decimal('0')
        credit = lines.aggregate(total=Sum('credit'))['total'] or Decimal('0')
        
        if debit > 0 or credit > 0:
            trial_balance.append({
                'account': account,
                'debit': account.opening_balance + debit if account.account_type in ['asset', 'expense'] else debit,
                'credit': credit if account.account_type in ['asset', 'expense'] else account.opening_balance + credit,
            })
            total_debit += debit
            total_credit += credit
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'trial_balance': trial_balance,
        'total_debit': total_debit,
        'total_credit': total_credit,
    }
    return render(request, 'tenant/accounting/reports/trial_balance.html', context)


@login_required
@require_accounting_module
def report_profit_loss(request):
    """Gelir-Gider raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    # Gelir hesapları
    revenue_accounts = Account.objects.filter(account_type='revenue', is_active=True, is_deleted=False)
    revenue_total = Decimal('0')
    revenue_details = []
    
    for account in revenue_accounts:
        lines = JournalEntryLine.objects.filter(
            account=account,
            journal_entry__entry_date__gte=date_from,
            journal_entry__entry_date__lte=date_to,
            journal_entry__status='posted',
            journal_entry__is_deleted=False
        )
        credit = lines.aggregate(total=Sum('credit'))['total'] or Decimal('0')
        if credit > 0:
            revenue_details.append({'account': account, 'amount': credit})
            revenue_total += credit
    
    # Gider hesapları
    expense_accounts = Account.objects.filter(account_type='expense', is_active=True, is_deleted=False)
    expense_total = Decimal('0')
    expense_details = []
    
    for account in expense_accounts:
        lines = JournalEntryLine.objects.filter(
            account=account,
            journal_entry__entry_date__gte=date_from,
            journal_entry__entry_date__lte=date_to,
            journal_entry__status='posted',
            journal_entry__is_deleted=False
        )
        debit = lines.aggregate(total=Sum('debit'))['total'] or Decimal('0')
        if debit > 0:
            expense_details.append({'account': account, 'amount': debit})
            expense_total += debit
    
    net_profit = revenue_total - expense_total
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'revenue_details': revenue_details,
        'revenue_total': revenue_total,
        'expense_details': expense_details,
        'expense_total': expense_total,
        'net_profit': net_profit,
    }
    return render(request, 'tenant/accounting/reports/profit_loss.html', context)


@login_required
@require_accounting_module
def report_balance_sheet(request):
    """Bilanço raporu"""
    date = request.GET.get('date', timezone.now().date().isoformat())
    
    # Aktif hesaplar
    asset_accounts = Account.objects.filter(account_type='asset', is_active=True, is_deleted=False)
    asset_total = Decimal('0')
    asset_details = []
    
    for account in asset_accounts:
        balance = account.get_balance()
        if balance != 0:
            asset_details.append({'account': account, 'balance': balance})
            asset_total += balance
    
    # Pasif hesaplar
    liability_accounts = Account.objects.filter(account_type='liability', is_active=True, is_deleted=False)
    liability_total = Decimal('0')
    liability_details = []
    
    for account in liability_accounts:
        balance = account.get_balance()
        if balance != 0:
            liability_details.append({'account': account, 'balance': balance})
            liability_total += balance
    
    # Özsermaye
    equity_accounts = Account.objects.filter(account_type='equity', is_active=True, is_deleted=False)
    equity_total = Decimal('0')
    equity_details = []
    
    for account in equity_accounts:
        balance = account.get_balance()
        if balance != 0:
            equity_details.append({'account': account, 'balance': balance})
            equity_total += balance
    
    context = {
        'date': date,
        'asset_details': asset_details,
        'asset_total': asset_total,
        'liability_details': liability_details,
        'liability_total': liability_total,
        'equity_details': equity_details,
        'equity_total': equity_total,
    }
    return render(request, 'tenant/accounting/reports/balance_sheet.html', context)

