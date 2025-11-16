"""
URL configuration for SaaS 2026 project.
Tenant URL'leri için - Admin paneli yok (sadece public schema'da)
"""
# from django.contrib import admin  # KALDIRILDI - Tenant domain'lerinde admin yok
from django.urls import path, include
from django.conf import settings
from django.conf.urls.static import static
from django.http import HttpResponse
from drf_spectacular.views import SpectacularAPIView, SpectacularSwaggerView

urlpatterns = [
    # Admin Panel KALDIRILDI - Tenant domain'lerinde admin paneli olmamalı
    # Admin paneli sadece public schema'da (config.urls_public) kullanılmalı
    # path('admin/', admin.site.urls),  # KALDIRILDI
    
    # VB Theme Frontend (ElektraWeb Desktop Style) - Tenant URL'leri için
    # Public schema için config.urls_public kullanılır
    # path('', include('apps.core.vb_urls')),
    
    # API Documentation
    path('api/schema/', SpectacularAPIView.as_view(), name='schema'),
    path('api/docs/', SpectacularSwaggerView.as_view(url_name='schema'), name='swagger-ui'),
    
    # API Endpoints - Şimdilik kapalı (urls dosyaları oluşturulacak)
    # path('api/', include('apps.core.urls')),
    # path('api/packages/', include('apps.packages.urls')),
    # path('api/modules/', include('apps.modules.urls')),
    # path('api/subscriptions/', include('apps.subscriptions.urls')),
    
    # Tenant Core (Kiracı Üye Paneli)
    path('', include('apps.tenant_apps.core.urls')),
    
    # Tenant Subscription Management (Paket Yönetimi)
    path('subscriptions/', include('apps.tenant_apps.subscriptions.urls')),
    
    # Tur Yönetimi
    path('tours/', include('apps.tenant_apps.tours.urls')),
    
    # Otel Yönetimi
    path('hotels/', include('apps.tenant_apps.hotels.urls')),
    
    # Resepsiyon (Ön Büro) - Rezervasyon odaklı
    path('reception/', include('apps.tenant_apps.reception.urls')),
    
    # Kat Hizmetleri (Housekeeping)
    path('housekeeping/', include('apps.tenant_apps.housekeeping.urls')),
    
    # Teknik Servis
    path('technical-service/', include('apps.tenant_apps.technical_service.urls')),
    
    # Kalite Kontrol
    path('quality-control/', include('apps.tenant_apps.quality_control.urls')),
    
    # Satış Yönetimi
    path('sales/', include('apps.tenant_apps.sales.urls')),
    
    # Personel Yönetimi
    path('staff/', include('apps.tenant_apps.staff.urls')),
    
    # AI Yönetimi (Tenant)
    path('ai/', include('apps.tenant_apps.ai.urls')),
    
    # Kasa Yönetimi (Finance)
    path('finance/', include('apps.tenant_apps.finance.urls')),
    
    # Muhasebe Yönetimi (Accounting)
    path('accounting/', include('apps.tenant_apps.accounting.urls')),
    
    # İade Yönetimi (Refunds)
    path('refunds/', include('apps.tenant_apps.refunds.urls')),
    
    # Ödeme Yönetimi (Payment Management)
    path('payment-management/', include('apps.tenant_apps.payment_management.urls')),
    
    # Kanal Yönetimi (Channel Management)
    path('channel-management/', include('apps.tenant_apps.channel_management.urls')),
    
    # Feribot Bileti (Ferry Tickets)
    path('ferry-tickets/', include('apps.tenant_apps.ferry_tickets.urls')),
    
    # Bungalov Yönetimi (Bungalovs)
    path('bungalovs/', include('apps.tenant_apps.bungalovs.urls')),
    
    # Yedekleme Yönetimi (Backup)
    path('backup/', include('apps.tenant_apps.backup.urls')),
    
    # Raporlar Modülü (Reports)
    path('reports/', include('apps.tenant_apps.reports.urls')),
    
    # Ayarlar Modülü (Settings - SMS entegrasyonları)
    path('settings/', include('apps.tenant_apps.settings.urls')),
    
    # Tenant Apps (tenant üyelerine özel) - Şimdilik kapalı
    # path('reservations/', include('apps.tenant_apps.reservations.urls')),
    # path('housekeeping/', include('apps.tenant_apps.housekeeping.urls')),
    # path('channels/', include('apps.tenant_apps.channels.urls')),
    
    # Health Check (trailing slash olmadan da çalışsın)
    path('health/', lambda request: HttpResponse('OK')),
    path('health', lambda request: HttpResponse('OK')),
]

# Static ve Media dosyalar (development)
if settings.DEBUG:
    urlpatterns += static(settings.STATIC_URL, document_root=settings.STATIC_ROOT)
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
    
    # Django Debug Toolbar
    try:
        import debug_toolbar
        urlpatterns = [
            path('__debug__/', include(debug_toolbar.urls)),
        ] + urlpatterns
    except ImportError:
        pass

# Admin site customization - Sadece public schema için (urls_public.py'de kullanılır)
# Tenant domain'lerinde admin paneli yok
# admin.site.site_header = "SaaS 2026 Super Admin"
# admin.site.site_title = "SaaS 2026"
# admin.site.index_title = "Hoş Geldiniz"


