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
                # AJAX isteği ise JSON döndür
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    from django.http import JsonResponse
                    return JsonResponse({'error': 'Aktif otel seçilmedi.'}, status=403)
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Superuser veya staff kullanıcılar tüm yetkilere sahip
                if request.user.is_superuser or request.user.is_staff:
                    return view_func(request, *args, **kwargs)
                
                # Admin kullanıcılar tüm yetkilere sahip
                try:
                    is_admin = tenant_user.has_module_permission('hotels', 'admin')
                    if is_admin:
                        return view_func(request, *args, **kwargs)
                except Exception as e:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.error(f'Admin permission kontrolü hatası: {str(e)}', exc_info=True)
                    # Hata durumunda devam et (normal yetki kontrolüne geç)
                
                # Otel yetkisini kontrol et
                try:
                    hotel_permission = HotelUserPermission.objects.filter(
                        tenant_user=tenant_user,
                        hotel=hotel,
                        is_active=True
                    ).first()
                except Exception as e:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.error(f'Hotel permission sorgulama hatası: {str(e)}', exc_info=True)
                    # AJAX isteği ise JSON döndür
                    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                        from django.http import JsonResponse
                        return JsonResponse({'error': 'Yetki kontrolü sırasında hata oluştu.'}, status=500)
                    messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                    return redirect('tenant:dashboard')
                
                # Hotel name güvenli erişim
                hotel_name = getattr(hotel, 'name', 'Otel')
                
                # Eğer otel yetkisi yoksa, modül yetkisi varsa view seviyesinde izin ver
                if not hotel_permission:
                    # Modül yetkisi kontrolü
                    try:
                        has_view_permission = tenant_user.has_module_permission('hotels', 'view')
                    except Exception as e:
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.error(f'Module permission kontrolü hatası: {str(e)}', exc_info=True)
                        has_view_permission = False
                    
                    if has_view_permission:
                        # View seviyesinde izin ver
                        if permission_level == 'view':
                            return view_func(request, *args, **kwargs)
                        else:
                            # AJAX isteği ise JSON döndür
                            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                                from django.http import JsonResponse
                                return JsonResponse({'error': f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.'}, status=403)
                            messages.error(request, f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.')
                            return redirect('hotels:select_hotel')
                    else:
                        # AJAX isteği ise JSON döndür
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            from django.http import JsonResponse
                            return JsonResponse({'error': f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.'}, status=403)
                        messages.error(request, f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                
                # Permission level mapping (reception modülü için)
                permission_mapping = {
                    'view': 'view',
                    'add': 'manage',
                    'edit': 'manage',
                    'delete': 'admin',
                    'checkin': 'manage',
                    'checkout': 'manage',
                    'change': 'manage',
                }
                
                # Permission level'ı map et
                mapped_permission = permission_mapping.get(permission_level, permission_level)
                
                # Eğer mapped permission permission_levels'da yoksa, view olarak kabul et
                if mapped_permission not in permission_levels:
                    mapped_permission = 'view'
                
                # hotel_permission burada None olamaz (yukarıda kontrol edildi)
                # Ama permission_level None olabilir, kontrol edelim
                try:
                    permission_level_value = getattr(hotel_permission, 'permission_level', None)
                except AttributeError:
                    permission_level_value = None
                
                if not permission_level_value:
                    # Permission level yoksa, view seviyesinde izin ver
                    if permission_level == 'view':
                        return view_func(request, *args, **kwargs)
                    else:
                        # AJAX isteği ise JSON döndür
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            from django.http import JsonResponse
                            return JsonResponse({'error': f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.'}, status=403)
                        messages.error(request, f'{hotel_name} oteline erişim yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                
                try:
                    user_level = permission_levels.index(permission_level_value)
                    required_level = permission_levels.index(mapped_permission)
                    
                    if user_level >= required_level:
                        return view_func(request, *args, **kwargs)
                    else:
                        # AJAX isteği ise JSON döndür
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            from django.http import JsonResponse
                            return JsonResponse({'error': 'Bu işlem için yeterli yetkiniz bulunmamaktadır.'}, status=403)
                        messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                except (ValueError, AttributeError) as e:
                    # Permission level geçersizse, view seviyesinde izin ver
                    import logging
                    logger = logging.getLogger(__name__)
                    try:
                        perm_level = getattr(hotel_permission, 'permission_level', None) if hotel_permission else None
                    except:
                        perm_level = None
                    logger.warning(f'Geçersiz permission level: {perm_level}, Hata: {str(e)}')
                    if permission_level == 'view':
                        return view_func(request, *args, **kwargs)
                    else:
                        # AJAX isteği ise JSON döndür
                        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                            from django.http import JsonResponse
                            return JsonResponse({'error': 'Bu işlem için yeterli yetkiniz bulunmamaktadır.'}, status=403)
                        messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                        return redirect('hotels:select_hotel')
                    
            except TenantUser.DoesNotExist:
                # AJAX isteği ise JSON döndür
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    from django.http import JsonResponse
                    return JsonResponse({'error': 'Tenant kullanıcı profili bulunamadı.'}, status=403)
                messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
                return redirect('tenant:login')
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Hotel yetki kontrolü hatası: {str(e)}', exc_info=True)
                # AJAX isteği ise JSON döndür
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    from django.http import JsonResponse
                    return JsonResponse({'error': 'Yetki kontrolü sırasında hata oluştu.'}, status=500)
                messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator

