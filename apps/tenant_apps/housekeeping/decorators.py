"""
Kat Hizmetleri Permission Decorators
Otel bazlı yetki kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_housekeeping_permission(permission_level='view'):
    """
    Kat Hizmetleri modülü yetki kontrolü decorator'ı
    Hem modül yetkisi hem de otel bazlı yetki kontrolü yapar
    
    Kullanım:
    @require_housekeeping_permission('manage')
    def housekeeping_dashboard(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Otel bazlı yetki kontrolü
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                from apps.tenant_apps.core.models import TenantUser
                from apps.tenant_apps.hotels.models import HotelUserPermission
                
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Superuser veya staff kullanıcılar tüm yetkilere sahip
                if request.user.is_superuser or request.user.is_staff:
                    return view_func(request, *args, **kwargs)
                
                # Modül yetkisi kontrolü
                permission_map = {
                    'view': 'view',
                    'manage': 'add',
                    'admin': 'admin'
                }
                permission_code = permission_map.get(permission_level, 'view')
                
                # Admin kullanıcılar tüm yetkilere sahip
                is_admin = tenant_user.has_module_permission('housekeeping', 'admin')
                if is_admin:
                    return view_func(request, *args, **kwargs)
                
                # Modül yetkisi kontrolü
                if not tenant_user.has_module_permission('housekeeping', permission_code):
                    # View yetkisi için modül aktifse izin ver
                    if permission_code == 'view':
                        # Paket kontrolü yap
                        from django.db import connection
                        from django_tenants.utils import get_tenant
                        from django.utils import timezone
                        from apps.subscriptions.models import Subscription
                        from apps.packages.models import PackageModule
                        from apps.modules.models import Module
                        
                        tenant = get_tenant(connection)
                        active_subscription = Subscription.objects.filter(
                            tenant=tenant,
                            status='active',
                            end_date__gte=timezone.now().date()
                        ).select_related('package').first()
                        
                        if active_subscription:
                            try:
                                module = Module.objects.get(code='housekeeping')
                                package_module = PackageModule.objects.filter(
                                    package=active_subscription.package,
                                    module=module,
                                    is_enabled=True
                                ).first()
                                
                                if package_module:
                                    # View yetkisi için modül aktifse izin ver
                                    pass  # Devam et
                                else:
                                    messages.error(request, 'Kat Hizmetleri modülü paketinizde aktif değil.')
                                    return redirect('tenant:dashboard')
                            except Module.DoesNotExist:
                                pass
                        else:
                            messages.error(request, 'Aktif aboneliğiniz bulunmamaktadır.')
                            return redirect('tenant:dashboard')
                    else:
                        messages.error(request, 'Bu işlem için yetkiniz bulunmamaktadır.')
                        return redirect('tenant:dashboard')
                
                # Otel bazlı kat hizmetleri yetkisini kontrol et
                hotel_permission = HotelUserPermission.objects.filter(
                    tenant_user=tenant_user,
                    hotel=hotel,
                    is_active=True
                ).first()
                
                # Eğer otel yetkisi yoksa, modül yetkisi varsa view seviyesinde izin ver
                if not hotel_permission:
                    # Modül yetkisi kontrolü
                    if tenant_user.has_module_permission('housekeeping', 'view'):
                        # View seviyesinde izin ver
                        if permission_level == 'view':
                            return view_func(request, *args, **kwargs)
                        else:
                            messages.error(request, f'{hotel.name} oteli için kat hizmetleri yetkiniz bulunmamaktadır.')
                            return redirect('hotels:select_hotel')
                    else:
                        messages.error(request, f'{hotel.name} oteli için kat hizmetleri yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                user_level = permission_levels.index(hotel_permission.permission_level)
                required_level = permission_levels.index(permission_level)
                
                if user_level >= required_level:
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                    return redirect('housekeeping:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Housekeeping yetki kontrolü hatası: {str(e)}', exc_info=True)
                messages.error(request, f'Yetki kontrolü sırasında hata oluştu: {str(e)}')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator

