"""
Ödeme Yönetimi Modülü Views
Tenant bazlı ödeme gateway yönetimi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse
from django.db import transaction
from django.utils import timezone
from django_tenants.utils import get_tenant_model

from .decorators import require_payment_management_permission
from .forms import TenantPaymentGatewayForm
from apps.payments.models import PaymentGateway, TenantPaymentGateway
from apps.tenants.models import Tenant


@login_required
@require_payment_management_permission('view')
def gateway_list(request):
    """Ödeme Gateway'leri Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    # Tüm aktif gateway'ler
    all_gateways = PaymentGateway.objects.filter(
        is_active=True,
        is_deleted=False
    ).order_by('sort_order', 'name')
    
    # Tenant'ın yapılandırdığı gateway'ler
    tenant_gateways = TenantPaymentGateway.objects.filter(
        tenant=tenant
    ).select_related('gateway').order_by('gateway__sort_order', 'gateway__name')
    
    # Gateway ID'lerine göre mapping
    configured_gateway_ids = {tg.gateway_id for tg in tenant_gateways}
    
    # Yapılandırılmamış gateway'ler
    unconfigured_gateways = [gw for gw in all_gateways if gw.id not in configured_gateway_ids]
    
    context = {
        'tenant': tenant,
        'all_gateways': all_gateways,
        'tenant_gateways': tenant_gateways,
        'unconfigured_gateways': unconfigured_gateways,
    }
    
    return render(request, 'payment_management/gateway_list.html', context)


@login_required
@require_payment_management_permission('add')
def gateway_create(request):
    """Yeni Gateway Yapılandırması Oluştur"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    gateway_id = request.GET.get('gateway_id')
    
    if gateway_id:
        gateway = get_object_or_404(PaymentGateway, pk=gateway_id, is_active=True, is_deleted=False)
    else:
        gateway = None
    
    # Bu gateway zaten yapılandırılmış mı?
    if gateway:
        existing = TenantPaymentGateway.objects.filter(tenant=tenant, gateway=gateway).first()
        if existing:
            messages.warning(request, f'{gateway.name} zaten yapılandırılmış. Düzenlemek için lütfen mevcut yapılandırmayı düzenleyin.')
            return redirect('payment_management:gateway_update', pk=existing.pk)
    
    if request.method == 'POST':
        form = TenantPaymentGatewayForm(request.POST, tenant=tenant)
        if form.is_valid():
            tenant_gateway = form.save(commit=False)
            tenant_gateway.tenant = tenant
            if gateway:
                tenant_gateway.gateway = gateway
            tenant_gateway.save()
            messages.success(request, f'{tenant_gateway.gateway.name} yapılandırması başarıyla oluşturuldu.')
            return redirect('payment_management:gateway_list')
    else:
        form = TenantPaymentGatewayForm(tenant=tenant)
        if gateway:
            form.initial['gateway'] = gateway.pk
            form.fields['gateway'].initial = gateway.pk
    
    context = {
        'tenant': tenant,
        'form': form,
        'gateway': gateway,
    }
    
    return render(request, 'payment_management/gateway_form.html', context)


@login_required
@require_payment_management_permission('edit')
def gateway_update(request, pk):
    """Gateway Yapılandırması Düzenle"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    tenant_gateway = get_object_or_404(
        TenantPaymentGateway,
        pk=pk,
        tenant=tenant
    )
    
    if request.method == 'POST':
        form = TenantPaymentGatewayForm(request.POST, instance=tenant_gateway, tenant=tenant)
        if form.is_valid():
            form.save()
            messages.success(request, f'{tenant_gateway.gateway.name} yapılandırması başarıyla güncellendi.')
            return redirect('payment_management:gateway_list')
    else:
        form = TenantPaymentGatewayForm(instance=tenant_gateway, tenant=tenant)
    
    context = {
        'tenant': tenant,
        'form': form,
        'tenant_gateway': tenant_gateway,
        'gateway': tenant_gateway.gateway,
    }
    
    return render(request, 'payment_management/gateway_form.html', context)


@login_required
@require_payment_management_permission('delete')
def gateway_delete(request, pk):
    """Gateway Yapılandırması Sil"""
    if not hasattr(request, 'tenant') or not request.tenant:
        return JsonResponse({'success': False, 'error': 'Tenant bulunamadı.'})
    
    tenant = request.tenant
    tenant_gateway = get_object_or_404(
        TenantPaymentGateway,
        pk=pk,
        tenant=tenant
    )
    
    gateway_name = tenant_gateway.gateway.name
    tenant_gateway.delete()
    
    messages.success(request, f'{gateway_name} yapılandırması silindi.')
    return redirect('payment_management:gateway_list')


@login_required
@require_payment_management_permission('view')
def gateway_toggle_active(request, pk):
    """Gateway Aktif/Pasif Toggle"""
    if not hasattr(request, 'tenant') or not request.tenant:
        return JsonResponse({'success': False, 'error': 'Tenant bulunamadı.'})
    
    tenant = request.tenant
    tenant_gateway = get_object_or_404(
        TenantPaymentGateway,
        pk=pk,
        tenant=tenant
    )
    
    tenant_gateway.is_active = not tenant_gateway.is_active
    tenant_gateway.save()
    
    status = 'aktif' if tenant_gateway.is_active else 'pasif'
    messages.success(request, f'{tenant_gateway.gateway.name} {status} hale getirildi.')
    
    return redirect('payment_management:gateway_list')


@login_required
@require_payment_management_permission('view')
def transaction_list(request):
    """Ödeme İşlemleri Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    from apps.payments.models import PaymentTransaction
    from django.core.paginator import Paginator
    
    transactions = PaymentTransaction.objects.filter(
        tenant=tenant
    ).select_related('gateway').order_by('-created_at')
    
    # Filtreleme
    status_filter = request.GET.get('status')
    gateway_filter = request.GET.get('gateway')
    
    if status_filter:
        transactions = transactions.filter(status=status_filter)
    if gateway_filter:
        transactions = transactions.filter(gateway_id=gateway_filter)
    
    paginator = Paginator(transactions, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Gateway listesi (filtre için)
    gateways = PaymentGateway.objects.filter(
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'tenant': tenant,
        'transactions': page_obj,
        'gateways': gateways,
        'status_filter': status_filter,
        'gateway_filter': gateway_filter,
    }
    
    return render(request, 'payment_management/transaction_list.html', context)

