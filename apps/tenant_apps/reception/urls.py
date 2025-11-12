"""
Resepsiyon Modülü URL Yönlendirmeleri
"""
from django.urls import path
from django.views.generic import TemplateView
from . import views

app_name = 'reception'

urlpatterns = [
    # Ana Ekran
    path('', views.dashboard, name='dashboard'),
    # Mock Önizleme (Bulut Acente Front Office)
    path('mock/front-office/', TemplateView.as_view(template_name='reception/bulut_acente_front_office.html'), name='bulut_acente_front_office'),
    
    # Rezervasyon Yönetimi
    path('reservations/', views.reservation_list, name='reservation_list'),
    path('reservations/create/', views.reservation_create, name='reservation_create'),
    path('reservations/<int:pk>/', views.reservation_detail, name='reservation_detail'),
    path('reservations/<int:pk>/update/', views.reservation_update, name='reservation_update'),
    path('reservations/<int:pk>/delete/', views.reservation_delete, name='reservation_delete'),
    path('reservations/<int:pk>/archive/', views.reservation_archive, name='reservation_archive'),
    path('reservations/<int:pk>/restore/', views.reservation_restore, name='reservation_restore'),
    
    # Check-in/out
    path('reservations/<int:pk>/checkin/', views.reservation_checkin, name='reservation_checkin'),
    path('reservations/<int:pk>/checkout/', views.reservation_checkout, name='reservation_checkout'),
    path('reservations/<int:pk>/noshow/', views.reservation_noshow, name='reservation_noshow'),
    
    # Oda Değişimi
    path('reservations/<int:pk>/room-change/', views.reservation_room_change, name='reservation_room_change'),
    path('reservations/<int:pk>/assign-room/', views.reservation_assign_room, name='reservation_assign_room'),
    
    # Müşteri Yönetimi
    path('guests/', views.guest_list, name='guest_list'),
    path('guests/search/', views.guest_search, name='guest_search'),
    path('guests/<int:pk>/', views.guest_detail, name='guest_detail'),
    path('guests/<int:pk>/history/', views.guest_history, name='guest_history'),
    
    # Oda Durumu
    path('rooms/', views.room_list, name='room_list'),
    path('rooms/rack/', views.room_rack, name='room_rack'),
    path('rooms/calendar/', views.room_calendar, name='room_calendar'),
    path('rooms/<int:pk>/', views.room_detail, name='room_detail'),
    path('rooms/<int:pk>/status/', views.room_status_update, name='room_status_update'),
    
    # Dijital Anahtar
    path('keycards/', views.keycard_list, name='keycard_list'),
    path('keycards/<int:pk>/', views.keycard_detail, name='keycard_detail'),
    path('keycards/<int:pk>/deactivate/', views.keycard_deactivate, name='keycard_deactivate'),
    path('keycards/<int:pk>/print/', views.keycard_print, name='keycard_print'),
    
    # Yazdırma
    path('reservations/<int:pk>/invoice/', views.reservation_invoice_print, name='reservation_invoice_print'),
    path('reservations/<int:pk>/receipt/', views.reservation_receipt_print, name='reservation_receipt_print'),
    path('reservations/<int:pk>/folio/', views.reservation_folio_print, name='reservation_folio_print'),
    
    # Resepsiyon Oturumu
    path('sessions/', views.session_list, name='session_list'),
    path('sessions/start/', views.session_start, name='session_start'),
    path('sessions/<int:pk>/end/', views.session_end, name='session_end'),
    
    # Ayarlar
    path('settings/', views.settings, name='settings'),
    
    # Raporlar
    path('reports/daily/', views.report_daily, name='report_daily'),
    path('reports/occupancy/', views.report_occupancy, name='report_occupancy'),
    path('reports/revenue/', views.report_revenue, name='report_revenue'),
    
    # API Endpoints
    path('api/bookings/', views.api_booking_list, name='api_booking_list'),
    path('api/bookings/<int:pk>/', views.api_booking_detail, name='api_booking_detail'),
    path('api/guests/search/', views.api_guest_search, name='api_guest_search'),
    path('api/customer/find/', views.api_customer_find, name='api_customer_find'),
    path('api/rooms/available/', views.api_rooms_available, name='api_rooms_available'),
    path('api/rooms/rack/', views.api_room_rack, name='api_room_rack'),
    path('api/rooms/calendar/', views.api_room_calendar, name='api_room_calendar'),
    path('api/pricing/calculate/', views.api_pricing_calculate, name='api_pricing_calculate'),
    path('api/pricing/activate/<int:price_id>/', views.api_pricing_activate, name='api_pricing_activate'),
    path('api/keycards/', views.api_keycard_create, name='api_keycard_create'),
]

