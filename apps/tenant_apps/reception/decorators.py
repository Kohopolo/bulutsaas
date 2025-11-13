"""
Reception Decorators
Otel bazlı önbüro yetki kontrolü
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.hotels.decorators import require_hotel_permission
from apps.tenant_apps.core.models import TenantUser
from apps.tenant_apps.core.decorators import require_module_permission


def require_reception_permission(permission_level='view'):
    """
    Resepsiyon modülü yetki kontrolü decorator'ı
    Otel bazlı yetki kontrolü + Modül yetkisi kontrolü
    
    Kullanım:
    @require_reception_permission('add')
    def my_view(request):
        ...
    
    Yetki Kontrolü:
    1. Kullanıcı giriş yapmış mı?
    2. Aktif otel seçilmiş mi?
    3. Otel bazlı yetki var mı? (HotelUserPermission)
    4. Reception modülü yetkisi var mı? (Module Permission)
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Aktif otel kontrolü
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Superuser veya staff kullanıcılar tüm yetkilere sahip
                if request.user.is_superuser or request.user.is_staff:
                    return view_func(request, *args, **kwargs)
                
                # Reception modülü yetkisi kontrolü
                reception_permission_map = {
                    'view': 'view',
                    'add': 'add',
                    'edit': 'edit',
                    'delete': 'delete',
                    'checkin': 'checkin',
                    'checkout': 'checkout',
                }
                
                module_permission_code = reception_permission_map.get(permission_level, 'view')
                
                # Modül yetkisi var mı?
                has_module_permission = tenant_user.has_module_permission('reception', module_permission_code)
                
                if not has_module_permission:
                    messages.error(request, 'Reception modülüne erişim yetkiniz bulunmamaktadır.')
                    return redirect('tenant:dashboard')
                
                # Otel bazlı yetki kontrolü (HotelUserPermission)
                from apps.tenant_apps.hotels.models import HotelUserPermission
                hotel_permission = HotelUserPermission.objects.filter(
                    tenant_user=tenant_user,
                    hotel=hotel,
                    is_active=True
                ).first()
                
                # Otel yetkisi yoksa, modül yetkisi varsa view seviyesinde izin ver
                if not hotel_permission:
                    # View seviyesinde izin ver (sadece görüntüleme)
                    if permission_level == 'view':
                        return view_func(request, *args, **kwargs)
                    else:
                        messages.error(request, f'{hotel.name} oteline erişim yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                user_level = permission_levels.index(hotel_permission.permission_level)
                
                # Reception işlemleri için gereken seviye
                required_level_map = {
                    'view': 0,  # view seviyesi yeterli
                    'add': 1,   # manage seviyesi gerekli
                    'edit': 1,  # manage seviyesi gerekli
                    'delete': 2,  # admin seviyesi gerekli
                    'checkin': 1,  # manage seviyesi gerekli
                    'checkout': 1,  # manage seviyesi gerekli
                }
                
                required_level = required_level_map.get(permission_level, 0)
                
                if user_level >= required_level:
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır. (Gerekli: {permission_levels[required_level]}, Mevcut: {hotel_permission.get_permission_level_display()})')
                    return redirect('reception:dashboard')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Reception yetki kontrolü hatası: {str(e)}')
                messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator

