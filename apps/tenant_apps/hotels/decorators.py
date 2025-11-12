"""
Otel Permission Decorators
Otel bazlı yetki kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from .models import HotelUserPermission
from apps.tenant_apps.core.models import TenantUser


def require_hotel_permission(permission_level='view'):
    """
    Otel bazlı yetki kontrolü decorator'ı
    
    Kullanım:
    @require_hotel_permission('manage')
    def hotel_edit(request, hotel_id):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Aktif otel yoksa
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Superuser veya staff kullanıcılar tüm yetkilere sahip
                if request.user.is_superuser or request.user.is_staff:
                    return view_func(request, *args, **kwargs)
                
                # Admin kullanıcılar tüm yetkilere sahip
                is_admin = tenant_user.has_module_permission('hotels', 'admin')
                if is_admin:
                    return view_func(request, *args, **kwargs)
                
                # Otel yetkisini kontrol et
                hotel_permission = HotelUserPermission.objects.filter(
                    tenant_user=tenant_user,
                    hotel=hotel,
                    is_active=True
                ).first()
                
                # Eğer otel yetkisi yoksa, modül yetkisi varsa view seviyesinde izin ver
                if not hotel_permission:
                    # Modül yetkisi kontrolü
                    if tenant_user.has_module_permission('hotels', 'view'):
                        # View seviyesinde izin ver
                        if permission_level == 'view':
                            return view_func(request, *args, **kwargs)
                        else:
                            messages.error(request, f'{hotel.name} oteline erişim yetkiniz bulunmamaktadır.')
                            return redirect('hotels:select_hotel')
                    else:
                        messages.error(request, f'{hotel.name} oteline erişim yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                user_level = permission_levels.index(hotel_permission.permission_level)
                required_level = permission_levels.index(permission_level)
                
                if user_level >= required_level:
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                    return redirect('hotels:select_hotel')
                    
            except TenantUser.DoesNotExist:
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
            except Exception as e:
                messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator

