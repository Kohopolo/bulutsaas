"""
Tenant AI Views
AI kredi yönetimi ve kullanım logları
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse
from django.views.decorators.http import require_http_methods
from django.core.paginator import Paginator
from django.db.models import Q
from django.utils import timezone
from django_tenants.utils import get_tenant
from django.db import connection
from .models import TenantAICredit, TenantAIUsage
from apps.ai.services import get_tenant_ai_credit
from apps.subscriptions.models import Subscription
from apps.ai.models import PackageAI


@login_required
def credit_dashboard(request):
    """AI Kredi Dashboard"""
    tenant = get_tenant(connection)
    
    # Kredi bilgisi
    credit = get_tenant_ai_credit(tenant)
    
    # Paket AI bilgileri
    active_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active',
        end_date__gte=timezone.now().date()
    ).select_related('package').first()
    
    package_ais = []
    if active_subscription:
        package_ais = PackageAI.objects.filter(
            package=active_subscription.package,
            is_enabled=True
        ).select_related('ai_provider', 'ai_model')
    
    # Son kullanımlar
    recent_usage = TenantAIUsage.objects.filter(
        tenant_id=tenant.id if hasattr(tenant, 'id') else None
    ).order_by('-created_at')[:10]
    
    # İstatistikler
    total_usage = TenantAIUsage.objects.filter(
        tenant_id=tenant.id if hasattr(tenant, 'id') else None,
        status='success'
    ).count()
    
    this_month_usage = TenantAIUsage.objects.filter(
        tenant_id=tenant.id if hasattr(tenant, 'id') else None,
        status='success',
        created_at__month=timezone.now().month,
        created_at__year=timezone.now().year
    ).count()
    
    context = {
        'credit': credit,
        'package_ais': package_ais,
        'recent_usage': recent_usage,
        'total_usage': total_usage,
        'this_month_usage': this_month_usage,
        'active_subscription': active_subscription,
    }
    return render(request, 'tenant/ai/credit_dashboard.html', context)


@login_required
@require_http_methods(["GET", "POST"])
def credit_add(request):
    """Manuel Kredi Ekleme (Şimdilik sadece görüntüleme - ödeme entegrasyonu sonra eklenecek)"""
    tenant = get_tenant(connection)
    credit = get_tenant_ai_credit(tenant)
    
    if request.method == 'POST':
        # Manuel kredi ekleme işlemi (şimdilik placeholder)
        messages.info(request, 'Manuel kredi ekleme özelliği yakında eklenecek. Paket yenileme ile kredileriniz otomatik yenilenecektir.')
        return redirect('ai:credit_dashboard')
    
    context = {
        'credit': credit,
    }
    return render(request, 'tenant/ai/credit_add.html', context)


@login_required
def credit_history(request):
    """Kredi Kullanım Geçmişi"""
    tenant = get_tenant(connection)
    
    usage_logs = TenantAIUsage.objects.filter(
        tenant_id=tenant.id if hasattr(tenant, 'id') else None
    ).order_by('-created_at')
    
    # Filtreleme
    usage_type = request.GET.get('usage_type', '')
    status = request.GET.get('status', '')
    search = request.GET.get('search', '')
    
    if usage_type:
        usage_logs = usage_logs.filter(usage_type=usage_type)
    if status:
        usage_logs = usage_logs.filter(status=status)
    if search:
        usage_logs = usage_logs.filter(
            Q(ai_provider_name__icontains=search) |
            Q(ai_model_name__icontains=search) |
            Q(prompt__icontains=search)
        )
    
    paginator = Paginator(usage_logs, 20)
    page = request.GET.get('page', 1)
    usage_logs = paginator.get_page(page)
    
    context = {
        'usage_logs': usage_logs,
        'usage_type_choices': TenantAIUsage._meta.get_field('usage_type').choices,
        'status_choices': TenantAIUsage.STATUS_CHOICES,
    }
    return render(request, 'tenant/ai/credit_history.html', context)


@login_required
def usage_list(request):
    """AI Kullanım Logları Listesi"""
    return credit_history(request)  # Aynı sayfa


@login_required
def usage_detail(request, pk):
    """AI Kullanım Logu Detay"""
    tenant = get_tenant(connection)
    
    usage = get_object_or_404(
        TenantAIUsage,
        pk=pk,
        tenant_id=tenant.id if hasattr(tenant, 'id') else None
    )
    
    context = {
        'usage': usage,
    }
    return render(request, 'tenant/ai/usage_detail.html', context)

