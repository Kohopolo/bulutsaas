"""
İade Yönetimi Modülü Decorator'ları
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.db import connection
from django_tenants.utils import get_tenant
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from django.utils import timezone


def require_refunds_module(view_func):
    """
    İade yönetimi modülünün aktif olup olmadığını kontrol eder
    """
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        tenant = get_tenant(connection)
        
        # Aktif abonelik kontrolü
        active_subscription = Subscription.objects.filter(
            tenant=tenant,
            status='active',
            end_date__gte=timezone.now().date()
        ).select_related('package').first()
        
        if not active_subscription:
            messages.error(request, 'Aktif abonelik bulunamadı.')
            return redirect('tenant:dashboard')
        
        # İade yönetimi modülü kontrolü
        try:
            refunds_module = Module.objects.get(code='refunds')
            package_module = PackageModule.objects.filter(
                package=active_subscription.package,
                module=refunds_module,
                is_enabled=True
            ).first()
            
            if not package_module:
                messages.error(request, 'İade yönetimi modülü paketinizde aktif değil.')
                return redirect('tenant:dashboard')
        except Module.DoesNotExist:
            messages.error(request, 'İade yönetimi modülü bulunamadı.')
            return redirect('tenant:dashboard')
        
        return view_func(request, *args, **kwargs)
    
    return _wrapped_view

