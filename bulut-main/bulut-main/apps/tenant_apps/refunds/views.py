"""
İade Yönetimi Views
Profesyonel iade yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse
from django.views.decorators.http import require_POST
from django.db.models import Q, Count, Sum
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal
from datetime import datetime, timedelta
from .models import RefundPolicy, RefundRequest, RefundTransaction
from .forms import RefundPolicyForm, RefundRequestForm
from .decorators import require_refunds_module


# ==================== İADE POLİTİKALARI ====================

@login_required
@require_refunds_module
def policy_list(request):
    """İade politikaları listesi"""
    policies = RefundPolicy.objects.filter(is_deleted=False).select_related('hotel')
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                policies = policies.filter(hotel_id=hotel_id)
            elif hotel_id == 0:
                # Genel politikalar (otel yok)
                policies = policies.filter(hotel__isnull=True)
                hotel_id = 0  # Genel politikalar için 0 olarak işaretle
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin politikalarını göster
            # Sadece aktif otelin politikalarını göster (genel politikaları gösterme)
            policies = policies.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id  # Context için hotel_id'yi set et
    
    # Filtreleme
    module = request.GET.get('module')
    if module:
        policies = policies.filter(module=module)
    
    policy_type = request.GET.get('policy_type')
    if policy_type:
        policies = policies.filter(policy_type=policy_type)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        policies = policies.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        policies = policies.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'priority')
    policies = policies.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(policies, 20)
    page = request.GET.get('page')
    policies = paginator.get_page(page)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = list(request.accessible_hotels) if request.accessible_hotels else []
    # Eğer accessible_hotels boşsa ama active_hotel varsa, onu ekle
    if not accessible_hotels and hasattr(request, 'active_hotel') and request.active_hotel:
        accessible_hotels = [request.active_hotel]
    
    context = {
        'policies': policies,
        'policy_types': RefundPolicy.POLICY_TYPE_CHOICES,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
        'hotels_module_enabled': hotels_module_enabled,
    }
    return render(request, 'tenant/refunds/policies/list.html', context)


@login_required
@require_refunds_module
def policy_detail(request, pk):
    """İade politikası detayı"""
    policy = get_object_or_404(RefundPolicy, pk=pk, is_deleted=False)
    
    # Bu politikayı kullanan talepler
    requests = policy.refund_requests.all()[:10]
    
    context = {
        'policy': policy,
        'requests': requests,
    }
    return render(request, 'tenant/refunds/policies/detail.html', context)


@login_required
@require_refunds_module
def policy_create(request):
    """Yeni iade politikası oluştur"""
    if request.method == 'POST':
        form = RefundPolicyForm(request.POST)
        if form.is_valid():
            policy = form.save(commit=False)
            # Eğer hotel seçilmemişse ve aktif otel varsa, aktif oteli ata
            if not policy.hotel and hasattr(request, 'active_hotel') and request.active_hotel:
                policy.hotel = request.active_hotel
            policy.save()
            messages.success(request, f'İade politikası "{policy.name}" başarıyla oluşturuldu.')
            return redirect('refunds:policy_detail', pk=policy.pk)
    else:
        form = RefundPolicyForm()
        # Varsayılan olarak aktif oteli seç
        if hasattr(request, 'active_hotel') and request.active_hotel:
            form.fields['hotel'].initial = request.active_hotel
    
    context = {'form': form}
    return render(request, 'tenant/refunds/policies/form.html', context)


@login_required
@require_refunds_module
def policy_update(request, pk):
    """İade politikası güncelle"""
    policy = get_object_or_404(RefundPolicy, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = RefundPolicyForm(request.POST, instance=policy)
        if form.is_valid():
            policy = form.save()
            messages.success(request, f'İade politikası "{policy.name}" başarıyla güncellendi.')
            return redirect('refunds:policy_detail', pk=policy.pk)
    else:
        form = RefundPolicyForm(instance=policy)
    
    context = {'form': form, 'policy': policy}
    return render(request, 'tenant/refunds/policies/form.html', context)


@login_required
@require_refunds_module
@require_POST
def policy_delete(request, pk):
    """İade politikası sil"""
    policy = get_object_or_404(RefundPolicy, pk=pk, is_deleted=False)
    
    # Talep kontrolü
    if policy.refund_requests.exists():
        messages.error(request, 'Bu politikayı kullanan iade talepleri bulunduğu için silinemez.')
        return redirect('refunds:policy_detail', pk=policy.pk)
    
    policy.is_deleted = True
    policy.save()
    messages.success(request, f'İade politikası "{policy.name}" başarıyla silindi.')
    return redirect('refunds:policy_list')


# ==================== İADE TALEPLERİ ====================

@login_required
@require_refunds_module
def request_list(request):
    """İade talepleri listesi"""
    requests = RefundRequest.objects.filter(is_deleted=False).select_related('hotel')
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                requests = requests.filter(hotel_id=hotel_id)
            elif hotel_id == 0:
                # Genel talepler (otel yok)
                requests = requests.filter(hotel__isnull=True)
                hotel_id = 0  # Genel talepler için 0 olarak işaretle
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin taleplerini göster
            # Sadece aktif otelin taleplerini göster (genel talepleri gösterme)
            requests = requests.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id  # Context için hotel_id'yi set et
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        requests = requests.filter(status=status)
    
    source_module = request.GET.get('source_module')
    if source_module:
        requests = requests.filter(source_module=source_module)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        requests = requests.filter(request_date__date__gte=date_from)
    if date_to:
        requests = requests.filter(request_date__date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        requests = requests.filter(
            Q(request_number__icontains=search) |
            Q(customer_name__icontains=search) |
            Q(customer_email__icontains=search) |
            Q(source_reference__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-request_date')
    requests = requests.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(requests, 50)
    page = request.GET.get('page')
    requests = paginator.get_page(page)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = list(request.accessible_hotels) if request.accessible_hotels else []
    # Eğer accessible_hotels boşsa ama active_hotel varsa, onu ekle
    if not accessible_hotels and hasattr(request, 'active_hotel') and request.active_hotel:
        accessible_hotels = [request.active_hotel]
    
    context = {
        'requests': requests,
        'statuses': RefundRequest.STATUS_CHOICES,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
        'hotels_module_enabled': hotels_module_enabled,
    }
    return render(request, 'tenant/refunds/requests/list.html', context)


@login_required
@require_refunds_module
def request_detail(request, pk):
    """İade talebi detayı"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    transactions = refund_request.transactions.filter(is_deleted=False)
    
    context = {
        'refund_request': refund_request,
        'transactions': transactions,
    }
    return render(request, 'tenant/refunds/requests/detail.html', context)


@login_required
@require_refunds_module
def request_create(request):
    """Yeni iade talebi oluştur"""
    if request.method == 'POST':
        form = RefundRequestForm(request.POST)
        if form.is_valid():
            refund_request = form.save(commit=False)
            refund_request.created_by = request.user
            # Eğer hotel seçilmemişse ve aktif otel varsa, aktif oteli ata
            if not refund_request.hotel and hasattr(request, 'active_hotel') and request.active_hotel:
                refund_request.hotel = request.active_hotel
            
            # İade tutarını hesapla (politika varsa)
            if refund_request.refund_policy:
                refund_amount, processing_fee, net_refund = refund_request.refund_policy.calculate_refund_amount(
                    original_amount=refund_request.original_amount,
                    booking_date=refund_request.original_payment_date,
                    current_date=timezone.now().date()
                )
                refund_request.refund_amount = refund_amount
                refund_request.processing_fee = processing_fee
                refund_request.net_refund = net_refund
                refund_request.refund_method = refund_request.refund_policy.refund_method
            
            refund_request.save()
            messages.success(request, f'İade talebi "{refund_request.request_number}" başarıyla oluşturuldu.')
            return redirect('refunds:request_detail', pk=refund_request.pk)
    else:
        form = RefundRequestForm()
        # Varsayılan olarak aktif oteli seç
        if hasattr(request, 'active_hotel') and request.active_hotel:
            form.fields['hotel'].initial = request.active_hotel
    
    context = {'form': form}
    return render(request, 'tenant/refunds/requests/form.html', context)


@login_required
@require_refunds_module
def request_update(request, pk):
    """İade talebi güncelle"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = RefundRequestForm(request.POST, instance=refund_request)
        if form.is_valid():
            refund_request = form.save()
            messages.success(request, f'İade talebi "{refund_request.request_number}" başarıyla güncellendi.')
            return redirect('refunds:request_detail', pk=refund_request.pk)
    else:
        form = RefundRequestForm(instance=refund_request)
    
    context = {'form': form, 'refund_request': refund_request}
    return render(request, 'tenant/refunds/requests/form.html', context)


@login_required
@require_refunds_module
@require_POST
def request_approve(request, pk):
    """İade talebini onayla"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    notes = request.POST.get('notes', '')
    refund_request.approve(user=request.user, notes=notes)
    messages.success(request, f'İade talebi "{refund_request.request_number}" onaylandı.')
    return redirect('refunds:request_detail', pk=refund_request.pk)


@login_required
@require_refunds_module
@require_POST
def request_reject(request, pk):
    """İade talebini reddet"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    reason = request.POST.get('reason', '')
    refund_request.reject(user=request.user, reason=reason)
    messages.success(request, f'İade talebi "{refund_request.request_number}" reddedildi.')
    return redirect('refunds:request_detail', pk=refund_request.pk)


@login_required
@require_refunds_module
@require_POST
def request_process(request, pk):
    """İade talebini işleme al"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    notes = request.POST.get('notes', '')
    refund_request.process(user=request.user, notes=notes)
    messages.success(request, f'İade talebi "{refund_request.request_number}" işleme alındı.')
    return redirect('refunds:request_detail', pk=refund_request.pk)


@login_required
@require_refunds_module
@require_POST
def request_complete(request, pk):
    """İade talebini tamamla"""
    refund_request = get_object_or_404(RefundRequest, pk=pk, is_deleted=False)
    notes = request.POST.get('notes', '')
    refund_request.complete(user=request.user, notes=notes)
    messages.success(request, f'İade talebi "{refund_request.request_number}" tamamlandı.')
    return redirect('refunds:request_detail', pk=refund_request.pk)


# ==================== İADE İŞLEMLERİ ====================

@login_required
@require_refunds_module
def transaction_list(request):
    """İade işlemleri listesi"""
    transactions = RefundTransaction.objects.filter(is_deleted=False)
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        transactions = transactions.filter(status=status)
    
    refund_method = request.GET.get('refund_method')
    if refund_method:
        transactions = transactions.filter(refund_method=refund_method)
    
    # Tarih aralığı
    date_from = request.GET.get('date_from')
    date_to = request.GET.get('date_to')
    if date_from:
        transactions = transactions.filter(transaction_date__date__gte=date_from)
    if date_to:
        transactions = transactions.filter(transaction_date__date__lte=date_to)
    
    # Arama
    search = request.GET.get('search')
    if search:
        transactions = transactions.filter(
            Q(transaction_number__icontains=search) |
            Q(payment_reference__icontains=search) |
            Q(refund_request__request_number__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-transaction_date')
    transactions = transactions.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(transactions, 50)
    page = request.GET.get('page')
    transactions = paginator.get_page(page)
    
    context = {
        'transactions': transactions,
        'statuses': RefundTransaction.STATUS_CHOICES,
        'refund_methods': RefundPolicy.REFUND_METHOD_CHOICES,
    }
    return render(request, 'tenant/refunds/transactions/list.html', context)


@login_required
@require_refunds_module
def transaction_detail(request, pk):
    """İade işlemi detayı"""
    transaction = get_object_or_404(RefundTransaction, pk=pk, is_deleted=False)
    
    context = {
        'transaction': transaction,
    }
    return render(request, 'tenant/refunds/transactions/detail.html', context)


@login_required
@require_refunds_module
@require_POST
def transaction_complete(request, pk):
    """İade işlemini tamamla"""
    transaction = get_object_or_404(RefundTransaction, pk=pk, is_deleted=False)
    transaction.complete(user=request.user)
    messages.success(request, f'İade işlemi "{transaction.transaction_number}" tamamlandı.')
    return redirect('refunds:transaction_detail', pk=transaction.pk)


# ==================== RAPORLAR ====================

@login_required
@require_refunds_module
def report_summary(request):
    """İade özet raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    )
    
    total_requests = requests.count()
    total_original_amount = requests.aggregate(total=Sum('original_amount'))['total'] or Decimal('0')
    total_refund_amount = requests.aggregate(total=Sum('refund_amount'))['total'] or Decimal('0')
    total_processing_fee = requests.aggregate(total=Sum('processing_fee'))['total'] or Decimal('0')
    total_net_refund = requests.aggregate(total=Sum('net_refund'))['total'] or Decimal('0')
    
    # Durum bazında
    by_status = requests.values('status').annotate(
        count=Count('id'),
        total_refund=Sum('net_refund')
    )
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'total_requests': total_requests,
        'total_original_amount': total_original_amount,
        'total_refund_amount': total_refund_amount,
        'total_processing_fee': total_processing_fee,
        'total_net_refund': total_net_refund,
        'by_status': by_status,
    }
    return render(request, 'tenant/refunds/reports/summary.html', context)


@login_required
@require_refunds_module
def report_by_module(request):
    """Modül bazında iade raporu"""
    date_from = request.GET.get('date_from', (timezone.now().date() - timedelta(days=30)).isoformat())
    date_to = request.GET.get('date_to', timezone.now().date().isoformat())
    
    requests = RefundRequest.objects.filter(
        request_date__date__gte=date_from,
        request_date__date__lte=date_to,
        is_deleted=False
    )
    
    by_module = requests.values('source_module').annotate(
        count=Count('id'),
        total_original=Sum('original_amount'),
        total_refund=Sum('net_refund')
    ).order_by('-total_refund')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'by_module': by_module,
    }
    return render(request, 'tenant/refunds/reports/by_module.html', context)

