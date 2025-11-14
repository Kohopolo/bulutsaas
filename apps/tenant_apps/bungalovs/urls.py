"""
Bungalov URL Configuration
"""
from django.urls import path
from . import views

app_name = 'bungalovs'

urlpatterns = [
    # Dashboard
    path('', views.dashboard, name='dashboard'),
    
    # Bungalovlar
    path('bungalovs/', views.bungalov_list, name='bungalov_list'),
    path('bungalovs/create/', views.bungalov_create, name='bungalov_create'),
    path('bungalovs/<int:pk>/', views.bungalov_detail, name='bungalov_detail'),
    path('bungalovs/<int:pk>/edit/', views.bungalov_update, name='bungalov_update'),
    path('bungalovs/<int:pk>/delete/', views.bungalov_delete, name='bungalov_delete'),
    
    # Bungalov Tipleri
    path('types/', views.bungalov_type_list, name='bungalov_type_list'),
    path('types/create/', views.bungalov_type_create, name='bungalov_type_create'),
    path('types/<int:pk>/edit/', views.bungalov_type_update, name='bungalov_type_update'),
    path('types/<int:pk>/delete/', views.bungalov_type_delete, name='bungalov_type_delete'),
    
    # Bungalov Özellikleri
    path('features/', views.bungalov_feature_list, name='bungalov_feature_list'),
    path('features/create/', views.bungalov_feature_create, name='bungalov_feature_create'),
    path('features/<int:pk>/edit/', views.bungalov_feature_update, name='bungalov_feature_update'),
    path('features/<int:pk>/delete/', views.bungalov_feature_delete, name='bungalov_feature_delete'),
    
    # Rezervasyonlar
    path('reservations/', views.reservation_list, name='reservation_list'),
    path('reservations/create/', views.reservation_create, name='reservation_create'),
    path('reservations/<int:pk>/', views.reservation_detail, name='reservation_detail'),
    path('reservations/<int:pk>/edit/', views.reservation_update, name='reservation_update'),
    path('reservations/<int:pk>/delete/', views.reservation_delete, name='reservation_delete'),
    path('reservations/<int:pk>/cancel/', views.reservation_cancel, name='reservation_cancel'),
    path('reservations/<int:pk>/refund/', views.reservation_refund, name='reservation_refund'),
    path('reservations/<int:pk>/status-change/', views.reservation_status_change, name='reservation_status_change'),
    
    # Check-in/Check-out
    path('reservations/<int:pk>/checkin/', views.reservation_checkin, name='reservation_checkin'),
    path('reservations/<int:pk>/checkout/', views.reservation_checkout, name='reservation_checkout'),
    
    # Rezervasyon Ödeme Linki
    path('reservations/<int:pk>/payment-link/', views.reservation_payment_link, name='reservation_payment_link'),
    
    # Vouchers
    path('reservations/<int:pk>/voucher/create/', views.reservation_voucher_create, name='reservation_voucher_create'),
    path('vouchers/<int:pk>/', views.reservation_voucher_detail, name='reservation_voucher_detail'),
    path('vouchers/<int:pk>/pdf/', views.reservation_voucher_pdf, name='reservation_voucher_pdf'),
    path('vouchers/<int:pk>/send/', views.voucher_send, name='voucher_send'),
    
    # Public Voucher (Token ile)
    path('voucher/<str:token>/', views.voucher_view, name='voucher_view'),
    path('voucher/<str:token>/payment/', views.voucher_payment, name='voucher_payment'),
    
    # Voucher Templates
    path('voucher-templates/', views.voucher_template_list, name='voucher_template_list'),
    path('voucher-templates/create/', views.voucher_template_create, name='voucher_template_create'),
    path('voucher-templates/<int:pk>/edit/', views.voucher_template_update, name='voucher_template_update'),
    
    # Temizlik Yönetimi
    path('cleanings/', views.cleaning_list, name='cleaning_list'),
    path('cleanings/create/', views.cleaning_create, name='cleaning_create'),
    path('cleanings/<int:pk>/edit/', views.cleaning_update, name='cleaning_update'),
    path('cleanings/<int:pk>/complete/', views.cleaning_complete, name='cleaning_complete'),
    
    # Bakım Yönetimi
    path('maintenances/', views.maintenance_list, name='maintenance_list'),
    path('maintenances/create/', views.maintenance_create, name='maintenance_create'),
    path('maintenances/<int:pk>/edit/', views.maintenance_update, name='maintenance_update'),
    path('maintenances/<int:pk>/complete/', views.maintenance_complete, name='maintenance_complete'),
    
    # Ekipman Yönetimi
    path('equipments/', views.equipment_list, name='equipment_list'),
    path('equipments/create/', views.equipment_create, name='equipment_create'),
    path('equipments/<int:pk>/edit/', views.equipment_update, name='equipment_update'),
    
    # Fiyatlandırma
    path('prices/', views.price_list, name='price_list'),
    path('prices/create/', views.price_create, name='price_create'),
    path('prices/<int:pk>/edit/', views.price_update, name='price_update'),
    
    # API Endpoints
    path('api/search-customer/', views.api_search_customer, name='api_search_customer'),
    path('api/calculate-price/', views.api_calculate_price, name='api_calculate_price'),
    path('api/check-availability/', views.api_check_availability, name='api_check_availability'),
]

