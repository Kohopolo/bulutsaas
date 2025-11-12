"""
Resepsiyon Permission Decorators
Otel bazlı yetki kontrolü ve rezervasyon limit kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.db import connection
from django_tenants.utils import get_tenant
from django.utils import timezone
from apps.tenant_apps.core.models import TenantUser
from apps.tenant_apps.hotels.models import HotelUserPermission
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from apps.tenant_apps.core.decorators import require_module_permission


def require_reception_permission(permission_level='view'):
    """
    Resepsiyon modülü yetki kontrolü decorator'ı
    Hem modül yetkisi hem de otel bazlı yetki kontrolü yapar
    
    Kullanım:
    @require_reception_permission('manage')
    def reception_dashboard(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Modül yetkisi kontrolü
            permission_map = {
                'view': 'view',
                'manage': 'add',
                'admin': 'admin'
            }
            permission_code = permission_map.get(permission_level, 'view')
            
            # require_module_permission decorator'ını kullan
            from apps.tenant_apps.core.decorators import require_module_permission
            module_decorator = require_module_permission('reception', permission_code)
            wrapped_func = module_decorator(view_func)
            
            # Otel bazlı yetki kontrolü
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Superuser veya staff kullanıcılar tüm yetkilere sahip
                if request.user.is_superuser or request.user.is_staff:
                    return wrapped_func(request, *args, **kwargs)
                
                # Admin kullanıcılar tüm yetkilere sahip
                is_admin = tenant_user.has_module_permission('reception', 'admin')
                if is_admin:
                    return wrapped_func(request, *args, **kwargs)
                
                # Otel bazlı resepsiyon yetkisini kontrol et
                hotel_permission = HotelUserPermission.objects.filter(
                    tenant_user=tenant_user,
                    hotel=hotel,
                    is_active=True
                ).first()
                
                # Eğer otel yetkisi yoksa, modül yetkisi varsa view seviyesinde izin ver
                if not hotel_permission:
                    # Modül yetkisi kontrolü
                    if tenant_user.has_module_permission('reception', 'view'):
                        # View seviyesinde izin ver
                        if permission_level == 'view':
                            return wrapped_func(request, *args, **kwargs)
                        else:
                            messages.error(request, f'{hotel.name} oteli için resepsiyon yetkiniz bulunmamaktadır.')
                            return redirect('hotels:select_hotel')
                    else:
                        messages.error(request, f'{hotel.name} oteli için resepsiyon yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                user_level = permission_levels.index(hotel_permission.permission_level)
                required_level = permission_levels.index(permission_level)
                
                if user_level >= required_level:
                    return wrapped_func(request, *args, **kwargs)
                else:
                    messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                    return redirect('reception:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
            except Exception as e:
                messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator


def check_reservation_limit(view_func):
    """
    Rezervasyon limit kontrolü decorator'ı
    Paket limitlerini kontrol eder
    
    Kullanım:
    @check_reservation_limit
    def reservation_create(request):
        ...
    """
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        if not request.user.is_authenticated:
            return redirect('tenant:login')
        
        try:
            tenant = get_tenant(connection)
            active_subscription = Subscription.objects.filter(
                tenant=tenant,
                status='active',
                end_date__gte=timezone.now().date()
            ).select_related('package').first()
            
            if active_subscription:
                try:
                    module = Module.objects.get(code='reception')
                    package_module = PackageModule.objects.filter(
                        package=active_subscription.package,
                        module=module,
                        is_enabled=True
                    ).first()
                    
                    if package_module:
                        limits = package_module.limits or {}
                        max_reservations = limits.get('max_reservations')
                        max_reservations_per_month = limits.get('max_reservations_per_month')
                        
                        if max_reservations:
                            # Toplam rezervasyon sayısını kontrol et
                            from .models import Reservation
                            current_reservations = Reservation.objects.filter(
                                hotel=request.active_hotel,
                                is_deleted=False
                            ).count()
                            
                            if current_reservations >= max_reservations:
                                messages.error(
                                    request,
                                    f'Rezervasyon limitine ulaşıldı. Maksimum {max_reservations} rezervasyon yapabilirsiniz.'
                                )
                                return redirect('reception:reservation_list')
                        
                        if max_reservations_per_month:
                            # Aylık rezervasyon sayısını kontrol et
                            from .models import Reservation
                            from datetime import datetime
                            current_month = timezone.now().month
                            current_year = timezone.now().year
                            
                            monthly_reservations = Reservation.objects.filter(
                                hotel=request.active_hotel,
                                is_deleted=False,
                                created_at__month=current_month,
                                created_at__year=current_year
                            ).count()
                            
                            if monthly_reservations >= max_reservations_per_month:
                                messages.error(
                                    request,
                                    f'Aylık rezervasyon limitine ulaşıldı. Bu ay maksimum {max_reservations_per_month} rezervasyon yapabilirsiniz.'
                                )
                                return redirect('reception:reservation_list')
                
                except Module.DoesNotExist:
                    pass
            
            return view_func(request, *args, **kwargs)
            
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Rezervasyon limit kontrolü başarısız: {str(e)}')
            return view_func(request, *args, **kwargs)
    
    return _wrapped_view

