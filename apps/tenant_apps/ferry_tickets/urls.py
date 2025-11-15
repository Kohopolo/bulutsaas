"""
Feribot Bileti URL Configuration
"""
from django.urls import path
from . import views

app_name = 'ferry_tickets'

urlpatterns = [
    # Dashboard
    path('', views.dashboard, name='dashboard'),
    
    # Biletler
    path('tickets/', views.ticket_list, name='ticket_list'),
    path('tickets/archived/', views.ticket_archived_list, name='ticket_archived_list'),
    path('tickets/create/', views.ticket_create, name='ticket_create'),
    path('tickets/<int:pk>/', views.ticket_detail, name='ticket_detail'),
    path('tickets/<int:pk>/edit/', views.ticket_update, name='ticket_update'),
    path('tickets/<int:pk>/delete/', views.ticket_delete, name='ticket_delete'),
    path('tickets/<int:pk>/restore/', views.ticket_restore, name='ticket_restore'),
    path('tickets/<int:pk>/cancel/', views.ticket_cancel, name='ticket_cancel'),
    path('tickets/<int:pk>/refund/', views.ticket_refund, name='ticket_refund'),
    path('tickets/<int:pk>/status-change/', views.ticket_status_change, name='ticket_status_change'),
    
    # Ödemeler
    path('tickets/<int:pk>/payment/add/', views.ticket_payment_add, name='ticket_payment_add'),
    path('payments/<int:pk>/delete/', views.payment_delete, name='payment_delete'),
    
    # Bilet Ödeme Linki
    path('tickets/<int:pk>/payment-link/', views.ticket_payment_link, name='ticket_payment_link'),
    
    # Vouchers
    path('tickets/<int:pk>/voucher/create/', views.ticket_voucher_create, name='ticket_voucher_create'),
    path('vouchers/<int:pk>/', views.ticket_voucher_detail, name='ticket_voucher_detail'),
    path('vouchers/<int:pk>/pdf/', views.ticket_voucher_pdf, name='ticket_voucher_pdf'),
    path('vouchers/<int:pk>/send/', views.voucher_send, name='voucher_send'),
    
    # Public Voucher (Token ile)
    path('voucher/<str:token>/', views.voucher_view, name='voucher_view'),
    path('voucher/<str:token>/payment/', views.voucher_payment, name='voucher_payment'),
    path('voucher/<str:token>/payment/callback/', views.voucher_payment_callback, name='voucher_payment_callback'),
    path('voucher/<str:token>/payment/success/', views.voucher_payment_success, name='voucher_payment_success'),
    path('voucher/<str:token>/payment/fail/', views.voucher_payment_fail, name='voucher_payment_fail'),
    
    # Voucher Templates
    path('voucher-templates/', views.voucher_template_list, name='voucher_template_list'),
    path('voucher-templates/create/', views.voucher_template_create, name='voucher_template_create'),
    path('voucher-templates/<int:pk>/', views.voucher_template_detail, name='voucher_template_detail'),
    path('voucher-templates/<int:pk>/edit/', views.voucher_template_update, name='voucher_template_update'),
    
    # Feribotlar
    path('ferries/', views.ferry_list, name='ferry_list'),
    path('ferries/create/', views.ferry_create, name='ferry_create'),
    path('ferries/<int:pk>/edit/', views.ferry_update, name='ferry_update'),
    path('ferries/<int:pk>/delete/', views.ferry_delete, name='ferry_delete'),
    
    # Rotalar
    path('routes/', views.route_list, name='route_list'),
    path('routes/create/', views.route_create, name='route_create'),
    path('routes/<int:pk>/edit/', views.route_update, name='route_update'),
    path('routes/<int:pk>/delete/', views.route_delete, name='route_delete'),
    
    # Seferler
    path('schedules/', views.schedule_list, name='schedule_list'),
    path('schedules/create/', views.schedule_create, name='schedule_create'),
    path('schedules/<int:pk>/edit/', views.schedule_update, name='schedule_update'),
    path('schedules/<int:pk>/delete/', views.schedule_delete, name='schedule_delete'),
    
    # API Konfigürasyonları
    path('api-configurations/', views.api_configuration_list, name='api_configuration_list'),
    path('api-configurations/create/', views.api_configuration_create, name='api_configuration_create'),
    path('api-configurations/<int:pk>/edit/', views.api_configuration_update, name='api_configuration_update'),
    path('api-configurations/<int:pk>/delete/', views.api_configuration_delete, name='api_configuration_delete'),
    path('api-configurations/<int:pk>/sync/', views.api_configuration_sync, name='api_configuration_sync'),
    
    # API Senkronizasyon Kayıtları
    path('api-syncs/', views.api_sync_list, name='api_sync_list'),
    path('api-syncs/<int:pk>/', views.api_sync_detail, name='api_sync_detail'),
    
    # API Endpoints
    path('api/search-customer/', views.api_search_customer, name='api_search_customer'),
    path('api/calculate-price/', views.api_calculate_price, name='api_calculate_price'),
    path('api/schedules/', views.api_schedules, name='api_schedules'),
]





