"""
Public schema URL configuration
Bu URL'ler sadece public schema için (tenant olmayan istekler)
"""
from django.contrib import admin
from django.urls import path, include
from django.conf import settings
from django.conf.urls.static import static
from django.http import HttpResponse, HttpResponseRedirect
from drf_spectacular.views import SpectacularAPIView, SpectacularSwaggerView

def landing_page(request):
    """Bulut Acente - Landing Page veya Admin'e yönlendirme"""
    # Eğer landing page template'i yoksa admin'e yönlendir
    try:
        from apps.core.views import landing_page as core_landing_page
        return core_landing_page(request)
    except:
        # Template yoksa admin paneline yönlendir
        return HttpResponseRedirect('/admin/')

urlpatterns = [
    # Ana sayfa - Landing Page veya Admin'e yönlendirme
    path('', landing_page, name='landing'),
    
    # Ödeme Sistemi
    path('payments/', include('apps.payments.urls')),
    
    # Admin Panel (Super Admin için)
    path('admin/', admin.site.urls),
    
    # AI Yönetimi (Super Admin)
    path('admin/ai/', include('apps.ai.urls')),
    
    # API Documentation
    path('api/schema/', SpectacularAPIView.as_view(), name='schema'),
    path('api/docs/', SpectacularSwaggerView.as_view(url_name='schema'), name='swagger-ui'),
    
    # API Endpoints (Public) - Şimdilik kapalı
    # path('api/', include('apps.core.urls')),
    # path('api/packages/', include('apps.packages.urls')),
    
    # Health Check (trailing slash olmadan da çalışsın)
    path('health/', lambda request: HttpResponse('OK')),
    path('health', lambda request: HttpResponse('OK')),
]

if settings.DEBUG:
    urlpatterns += static(settings.STATIC_URL, document_root=settings.STATIC_ROOT)
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)



