"""
Permission Decorators
Modül bazında yetki kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.db import connection
from django_tenants.utils import get_tenant
from django.utils import timezone
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from .models import TenantUser


def require_module_permission(module_code, permission_code):
    """
    Modül bazında yetki kontrolü decorator'ı
    Hem paket kontrolü hem de kullanıcı yetkisi kontrolü yapar
    
    Kullanım:
    @require_module_permission('reservations', 'view')
    def my_view(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Paket kontrolü - modül pakette aktif mi?
                tenant = get_tenant(connection)
                active_subscription = Subscription.objects.filter(
                    tenant=tenant,
                    status='active',
                    end_date__gte=timezone.now().date()
                ).select_related('package').first()
                
                if active_subscription:
                    try:
                        module = Module.objects.get(code=module_code)
                        package_module = PackageModule.objects.filter(
                            package=active_subscription.package,
                            module=module,
                            is_enabled=True
                        ).first()
                        
                        if not package_module:
                            messages.error(request, f'{module.name} modülü paketinizde aktif değil.')
                            # AJAX request ise JSON response döndür
                            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                                from django.http import JsonResponse
                                return JsonResponse({'error': f'{module.name} modülü paketinizde aktif değil.'}, status=403)
                            return redirect('tenant:dashboard')
                    except Module.DoesNotExist:
                        # Modül bulunamazsa devam et (eski modüller için uyumluluk)
                        pass
                
                # Kullanıcı yetkisi kontrolü
                # Eğer kullanıcıya hiç yetki atanmamışsa, superuser veya staff ise izin ver
                if request.user.is_superuser or request.user.is_staff:
                    return view_func(request, *args, **kwargs)
                
                # Modül yetkisi kontrolü
                if tenant_user.has_module_permission(module_code, permission_code):
                    return view_func(request, *args, **kwargs)
                else:
                    # Eğer modül pakette aktifse ve kullanıcıya yetki atanmamışsa, view yetkisi ver
                    if active_subscription:
                        try:
                            module = Module.objects.get(code=module_code)
                            package_module = PackageModule.objects.filter(
                                package=active_subscription.package,
                                module=module,
                                is_enabled=True
                            ).first()
                            
                            if package_module and permission_code == 'view':
                                # View yetkisi için modül aktifse izin ver
                                return view_func(request, *args, **kwargs)
                        except Module.DoesNotExist:
                            pass
                    
                    messages.error(request, f'Bu işlem için yetkiniz bulunmamaktadır.')
                    # AJAX request ise JSON response döndür
                    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                        from django.http import JsonResponse
                        return JsonResponse({'error': 'Bu işlem için yetkiniz bulunmamaktadır.'}, status=403)
                    return redirect('tenant:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
        
        return _wrapped_view
    return decorator


def require_user_type(*user_type_codes):
    """
    Kullanıcı tipi kontrolü decorator'ı
    
    Kullanım:
    @require_user_type('reception', 'manager')
    def my_view(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                if tenant_user.user_type and tenant_user.user_type.code in user_type_codes:
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.')
                    return redirect('tenant:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
        
        return _wrapped_view
    return decorator


def require_role(*role_codes):
    """
    Rol kontrolü decorator'ı
    
    Kullanım:
    @require_role('admin', 'manager')
    def my_view(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                user_roles = tenant_user.get_roles()
                
                role_codes_list = [ur.role.code for ur in user_roles]
                
                if any(role_code in role_codes_list for role_code in role_codes):
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, 'Bu işlem için gerekli role sahip değilsiniz.')
                    return redirect('tenant:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
        
        return _wrapped_view
    return decorator
