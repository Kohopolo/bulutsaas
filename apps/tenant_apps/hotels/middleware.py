"""
Otel Middleware
Aktif otel bilgisini request'e ekler
"""
from django.utils.deprecation import MiddlewareMixin
from django.shortcuts import redirect
from django.urls import resolve
from .models import Hotel, HotelUserPermission
from apps.tenant_apps.core.models import TenantUser
from django_tenants.utils import get_public_schema_name
from django.db import connection


class HotelMiddleware(MiddlewareMixin):
    """
    Aktif otel bilgisini request'e ekler
    Session'dan aktif otel ID'sini alır veya varsayılan oteli kullanır
    """
    
    def process_request(self, request):
        # Public schema'da çalışmaz
        if connection.schema_name == get_public_schema_name():
            request.active_hotel = None
            request.accessible_hotels = []
            return None
        
        # Tenant yoksa
        if not hasattr(request, 'tenant') or not request.tenant:
            request.active_hotel = None
            request.accessible_hotels = []
            return None
        
        # Kullanıcı giriş yapmamışsa
        if not request.user.is_authenticated:
            request.active_hotel = None
            request.accessible_hotels = []
            return None
        
        try:
            tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
            
            # Kullanıcının erişebileceği otelleri al
            request.accessible_hotels = self._get_accessible_hotels(tenant_user)
            
            # Hotels modülü pakette varsa ve kullanıcının birden fazla otel yetkisi varsa otel seçimi kontrolü yap
            from apps.tenant_apps.core.utils import is_hotels_module_enabled
            hotels_module_enabled = is_hotels_module_enabled(request.tenant)
            
            if hotels_module_enabled:
                accessible_hotels_count = len(request.accessible_hotels) if request.accessible_hotels else 0
                
                # Birden fazla otel yetkisi varsa ve session'da aktif otel yoksa otel seçimi yapılmalı
                # Ancak otel seçim sayfasına zaten gidiyorsa veya login sayfasındaysa yönlendirme yapma
                if accessible_hotels_count > 1 and not request.session.get('active_hotel_id'):
                    # Otel seçim sayfasına veya login sayfasına gitmiyorsa yönlendir
                    try:
                        resolver_match = resolve(request.path)
                        url_name = resolver_match.url_name if resolver_match else None
                        
                        # Bu sayfalara gidiyorsa yönlendirme yapma
                        excluded_urls = ['select_hotel', 'tenant_login', 'tenant_logout', 'login', 'logout']
                        if url_name not in excluded_urls:
                            return redirect('hotels:select_hotel')
                    except Exception:
                        # URL resolve edilemezse yine de yönlendir (güvenli tarafta ol)
                        if '/hotels/select/' not in request.path and '/login' not in request.path:
                            return redirect('hotels:select_hotel')
            
            # Session'dan aktif otel ID'sini al
            active_hotel_id = request.session.get('active_hotel_id')
            
            if active_hotel_id:
                try:
                    # Kullanıcının bu otelde yetkisi var mı kontrol et
                    hotel = Hotel.objects.get(id=active_hotel_id, is_active=True)
                    has_permission = HotelUserPermission.objects.filter(
                        tenant_user=tenant_user,
                        hotel=hotel,
                        is_active=True
                    ).exists()
                    
                    # Admin kullanıcılar tüm otellere erişebilir
                    is_admin = tenant_user.has_module_permission('hotels', 'admin')
                    
                    if has_permission or is_admin:
                        request.active_hotel = hotel
                    else:
                        # Yetki yoksa varsayılan oteli kullan
                        request.active_hotel = self._get_default_hotel(tenant_user)
                except Hotel.DoesNotExist:
                    request.active_hotel = self._get_default_hotel(tenant_user)
            else:
                # Session'da yoksa varsayılan oteli kullan
                request.active_hotel = self._get_default_hotel(tenant_user)
            
        except TenantUser.DoesNotExist:
            request.active_hotel = None
            request.accessible_hotels = []
        except Exception as e:
            # Hata durumunda varsayılan değerler
            request.active_hotel = None
            request.accessible_hotels = []
        
        return None
    
    def _get_default_hotel(self, tenant_user):
        """Kullanıcının varsayılan otelini getir"""
        # Önce kullanıcının yetkili olduğu varsayılan oteli bul
        hotel_permission = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            hotel__is_default=True,
            hotel__is_active=True,
            is_active=True
        ).select_related('hotel').first()
        
        if hotel_permission:
            return hotel_permission.hotel
        
        # Varsayılan otel yoksa, kullanıcının yetkili olduğu ilk oteli al
        hotel_permission = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            hotel__is_active=True,
            is_active=True
        ).select_related('hotel').first()
        
        if hotel_permission:
            return hotel_permission.hotel
        
        # Admin kullanıcılar için varsayılan oteli döndür
        is_admin = tenant_user.has_module_permission('hotels', 'admin')
        if is_admin:
            return Hotel.objects.filter(is_default=True, is_active=True).first()
        
        # Hiç otel yoksa None döndür
        return None
    
    def _get_accessible_hotels(self, tenant_user):
        """Kullanıcının erişebileceği otelleri getir"""
        # Admin kullanıcılar tüm otellere erişebilir
        is_admin = tenant_user.has_module_permission('hotels', 'admin')
        
        if is_admin:
            return Hotel.objects.filter(is_active=True).order_by('sort_order', 'name')
        
        # Normal kullanıcılar sadece yetkili oldukları otellere erişebilir
        hotel_ids = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).values_list('hotel_id', flat=True)
        
        return Hotel.objects.filter(id__in=hotel_ids, is_active=True).order_by('sort_order', 'name')

