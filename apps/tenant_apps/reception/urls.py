"""
Reception URL Configuration
"""
from django.urls import path
from . import views

app_name = 'reception'

urlpatterns = [
    # Dashboard
    path('', views.dashboard, name='dashboard'),
    
    # Rezervasyonlar
    path('reservations/', views.reservation_list, name='reservation_list'),
    path('reservations/create/', views.reservation_create, name='reservation_create'),
    path('reservations/<int:pk>/', views.reservation_detail, name='reservation_detail'),
    path('reservations/<int:pk>/edit/', views.reservation_update, name='reservation_update'),
    path('reservations/<int:pk>/delete/', views.reservation_delete, name='reservation_delete'),
    
    # Check-in/Check-out
    path('reservations/<int:pk>/checkin/', views.reservation_checkin, name='reservation_checkin'),
    path('reservations/<int:pk>/checkout/', views.reservation_checkout, name='reservation_checkout'),
    
    # Vouchers
    path('reservations/<int:pk>/voucher/create/', views.reservation_voucher_create, name='reservation_voucher_create'),
    path('vouchers/<int:pk>/', views.reservation_voucher_detail, name='reservation_voucher_detail'),
    path('vouchers/<int:pk>/pdf/', views.reservation_voucher_pdf, name='reservation_voucher_pdf'),
    
    # Voucher Templates
    path('voucher-templates/', views.voucher_template_list, name='voucher_template_list'),
    path('voucher-templates/create/', views.voucher_template_create, name='voucher_template_create'),
    path('voucher-templates/<int:pk>/', views.voucher_template_detail, name='voucher_template_detail'),
    path('voucher-templates/<int:pk>/edit/', views.voucher_template_update, name='voucher_template_update'),
    
           # Oda Planı ve Durumu
           path('room-plan/', views.room_plan, name='room_plan'),
           path('room-status/', views.room_status, name='room_status'),
           
           # API Endpoints
           path('api/search-customer/', views.api_search_customer, name='api_search_customer'),
           path('api/calculate-price/', views.api_calculate_price, name='api_calculate_price'),
           path('api/room-numbers/', views.api_room_numbers, name='api_room_numbers'),
           path('api/room-status-update/', views.api_room_status_update, name='api_room_status_update'),
       ]

