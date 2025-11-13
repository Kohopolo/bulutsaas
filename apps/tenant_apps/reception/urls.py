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
    path('reservations/archived/', views.reservation_archived_list, name='reservation_archived_list'),
    path('reservations/create/', views.reservation_create, name='reservation_create'),
    path('reservations/<int:pk>/', views.reservation_detail, name='reservation_detail'),
    path('reservations/<int:pk>/edit/', views.reservation_update, name='reservation_update'),
    path('reservations/<int:pk>/delete/', views.reservation_delete, name='reservation_delete'),
    path('reservations/<int:pk>/restore/', views.reservation_restore, name='reservation_restore'),
    path('reservations/<int:pk>/refund/', views.reservation_refund, name='reservation_refund'),
    path('reservations/<int:pk>/status-change/', views.reservation_status_change, name='reservation_status_change'),
    
    # Check-in/Check-out
    path('reservations/<int:pk>/checkin/', views.reservation_checkin, name='reservation_checkin'),
    path('reservations/<int:pk>/checkout/', views.reservation_checkout, name='reservation_checkout'),
    
    # Vouchers
    path('reservations/<int:pk>/voucher/create/', views.reservation_voucher_create, name='reservation_voucher_create'),
    path('vouchers/<int:pk>/', views.reservation_voucher_detail, name='reservation_voucher_detail'),
    path('vouchers/<int:pk>/pdf/', views.reservation_voucher_pdf, name='reservation_voucher_pdf'),
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
    
           # Oda Planı ve Durumu
           path('room-plan/', views.room_plan, name='room_plan'),
           path('room-status/', views.room_status, name='room_status'),
           path('room-status-dashboard/', views.room_status_dashboard, name='room_status_dashboard'),
           path('room-calendar/', views.room_calendar_view, name='room_calendar'),
           
           # API Endpoints
           path('api/search-customer/', views.api_search_customer, name='api_search_customer'),
           path('api/calculate-price/', views.api_calculate_price, name='api_calculate_price'),
           path('api/room-numbers/', views.api_room_numbers, name='api_room_numbers'),
           path('api/room-status-update/', views.api_room_status_update, name='api_room_status_update'),
           path('api/room-detail/<int:room_number_id>/', views.api_room_detail, name='api_room_detail'),
       ]

