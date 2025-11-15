"""
Tur Modülü Limit Kontrol Decorator'ları
Paket limitlerini kontrol eder
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.http import JsonResponse
from apps.tenant_apps.core.models import TenantUser
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from .models import Tour, TourReservation


def get_tour_module_limits(request):
    """Tenant'ın tur modülü limitlerini al"""
    tenant = request.tenant
    
    # Aktif aboneliği al
    active_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not active_subscription:
        return None
    
    package = active_subscription.package
    
    # Tur modülünü al
    try:
        package_module = PackageModule.objects.get(
            package=package,
            module__code='tours',
            is_enabled=True
        )
    except PackageModule.DoesNotExist:
        return None
    
    # Limitler (PackageModule.limits JSON'dan)
    limits = package_module.limits or {}
    
    return {
        'max_tours': limits.get('max_tours', 0),
        'max_tour_users': limits.get('max_tour_users', 0),
        'max_tour_reservations': limits.get('max_tour_reservations', 0),
        'max_tour_reservations_per_month': limits.get('max_tour_reservations_per_month', 0),
    }


def check_tour_limit(view_func):
    """Tur sayısı limitini kontrol et"""
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        if request.method == 'POST' and 'create' in request.path:
            limits = get_tour_module_limits(request)
            
            if limits and limits['max_tours'] > 0:
                # Mevcut tur sayısı
                current_tours = Tour.objects.filter(is_active=True).count()
                
                if current_tours >= limits['max_tours']:
                    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                        return JsonResponse({
                            'success': False,
                            'message': f'Tur limitine ulaştınız. Maksimum {limits["max_tours"]} tur ekleyebilirsiniz.'
                        }, status=403)
                    
                    messages.error(
                        request,
                        f'Tur limitine ulaştınız. Maksimum {limits["max_tours"]} tur ekleyebilirsiniz. Paket yükseltmek için lütfen paket yönetimi sayfasını ziyaret edin.'
                    )
                    return redirect('tours:list')
        
        return view_func(request, *args, **kwargs)
    return _wrapped_view


def check_tour_user_limit(view_func):
    """Tur modülüne erişebilen kullanıcı sayısı limitini kontrol et"""
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        limits = get_tour_module_limits(request)
        
        if limits and limits['max_tour_users'] > 0:
            # Tur modülüne erişimi olan kullanıcı sayısı
            # (Tur modülünde en az bir yetkisi olan kullanıcılar)
            from apps.tenant_apps.core.models import Permission, RolePermission, UserRole
            
            try:
                tour_module = Module.objects.get(code='tours', is_active=True)
            except:
                tour_module = None
            
            if tour_module:
                # Tur modülünde yetkisi olan kullanıcılar
                tour_permissions = Permission.objects.filter(
                    module=tour_module,
                    is_active=True
                )
                
                user_ids_with_permission = UserRole.objects.filter(
                    role__role_permissions__permission__in=tour_permissions,
                    is_active=True
                ).values_list('user__user__id', flat=True).distinct()
                
                current_tour_users = len(set(user_ids_with_permission))
                
                if current_tour_users >= limits['max_tour_users']:
                    messages.warning(
                        request,
                        f'Tur modülü kullanıcı limitine ulaştınız. Maksimum {limits["max_tour_users"]} kullanıcı tur modülüne erişebilir.'
                    )
        
        return view_func(request, *args, **kwargs)
    return _wrapped_view


def check_tour_reservation_limit(view_func):
    """Tur rezervasyon sayısı limitini kontrol et"""
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        if request.method == 'POST' and 'create' in request.path:
            limits = get_tour_module_limits(request)
            
            if limits:
                # Toplam rezervasyon limiti
                if limits.get('max_tour_reservations', 0) > 0:
                    current_reservations = TourReservation.objects.filter(
                        is_deleted=False
                    ).count()
                    
                    if current_reservations >= limits['max_tour_reservations']:
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            return JsonResponse({
                                'success': False,
                                'message': f'Rezervasyon limitine ulaştınız. Maksimum {limits["max_tour_reservations"]} rezervasyon yapabilirsiniz.'
                            }, status=403)
                        
                        messages.error(
                            request,
                            f'Rezervasyon limitine ulaştınız. Maksimum {limits["max_tour_reservations"]} rezervasyon yapabilirsiniz.'
                        )
                        return redirect('tours:reservation_list')
                
                # Aylık rezervasyon limiti
                if limits.get('max_tour_reservations_per_month', 0) > 0:
                    from django.utils import timezone
                    from datetime import datetime
                    
                    current_month = timezone.now().month
                    current_year = timezone.now().year
                    
                    current_month_reservations = TourReservation.objects.filter(
                        created_at__month=current_month,
                        created_at__year=current_year,
                        is_deleted=False
                    ).count()
                    
                    if current_month_reservations >= limits['max_tour_reservations_per_month']:
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            return JsonResponse({
                                'success': False,
                                'message': f'Aylık rezervasyon limitine ulaştınız. Bu ay maksimum {limits["max_tour_reservations_per_month"]} rezervasyon yapabilirsiniz.'
                            }, status=403)
                        
                        messages.error(
                            request,
                            f'Aylık rezervasyon limitine ulaştınız. Bu ay maksimum {limits["max_tour_reservations_per_month"]} rezervasyon yapabilirsiniz.'
                        )
                        return redirect('tours:reservation_list')
        
        return view_func(request, *args, **kwargs)
    return _wrapped_view


def require_tour_module(view_func):
    """Tur modülünün pakette aktif olduğunu kontrol et"""
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        limits = get_tour_module_limits(request)
        
        if limits is None:
            messages.error(
                request,
                'Tur modülü paketinizde aktif değil. Lütfen paket yönetimi sayfasından paketinizi kontrol edin veya paket yükseltin.'
            )
            return redirect('tenant_subscriptions:dashboard')
        
        return view_func(request, *args, **kwargs)
    return _wrapped_view

