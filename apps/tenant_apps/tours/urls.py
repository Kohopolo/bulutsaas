"""
Tur Yönetim URLs
"""
from django.urls import path
from . import views

app_name = 'tours'

urlpatterns = [
    # Tur Listeleme ve Yönetim
    path('', views.tour_list, name='list'),
    path('create/', views.tour_create, name='create'),
    path('<int:pk>/', views.tour_detail, name='detail'),
    path('<int:pk>/edit/', views.tour_update, name='update'),
    path('<int:pk>/delete/', views.tour_delete, name='delete'),
    path('<int:pk>/toggle-status/', views.tour_toggle_status, name='toggle_status'),
    path('<int:pk>/duplicate/', views.tour_duplicate, name='duplicate'),
    
    # Dinamik Yönetim
    path('regions/', views.tour_region_list, name='region_list'),
    path('regions/create/', views.tour_region_create, name='region_create'),
    path('regions/<int:pk>/edit/', views.tour_region_update, name='region_update'),
    path('regions/<int:pk>/delete/', views.tour_region_delete, name='region_delete'),
    
    path('locations/', views.tour_location_list, name='location_list'),
    path('locations/create/', views.tour_location_create, name='location_create'),
    path('locations/<int:pk>/edit/', views.tour_location_update, name='location_update'),
    path('locations/<int:pk>/delete/', views.tour_location_delete, name='location_delete'),
    
    path('cities/', views.tour_city_list, name='city_list'),
    path('cities/create/', views.tour_city_create, name='city_create'),
    path('cities/<int:pk>/edit/', views.tour_city_update, name='city_update'),
    path('cities/<int:pk>/delete/', views.tour_city_delete, name='city_delete'),
    
    path('types/', views.tour_type_list, name='type_list'),
    path('types/create/', views.tour_type_create, name='type_create'),
    path('types/<int:pk>/edit/', views.tour_type_update, name='type_update'),
    path('types/<int:pk>/delete/', views.tour_type_delete, name='type_delete'),
    
    path('voucher-templates/', views.tour_voucher_template_list, name='voucher_template_list'),
    path('voucher-templates/create/', views.tour_voucher_template_create, name='voucher_template_create'),
    path('voucher-templates/<int:pk>/edit/', views.tour_voucher_template_update, name='voucher_template_update'),
    path('voucher-templates/<int:pk>/delete/', views.tour_voucher_template_delete, name='voucher_template_delete'),
    
    # Tur Detay İşlemleri
    path('<int:tour_pk>/dates/add/', views.tour_date_add, name='date_add'),
    path('<int:tour_pk>/dates/<int:pk>/edit/', views.tour_date_update, name='date_update'),
    path('<int:tour_pk>/dates/<int:pk>/delete/', views.tour_date_delete, name='date_delete'),
    
    path('<int:tour_pk>/programs/add/', views.tour_program_add, name='program_add'),
    path('<int:tour_pk>/programs/<int:pk>/edit/', views.tour_program_update, name='program_update'),
    path('<int:tour_pk>/programs/<int:pk>/delete/', views.tour_program_delete, name='program_delete'),
    
    path('<int:tour_pk>/images/upload/', views.tour_image_upload, name='image_upload'),
    path('<int:tour_pk>/images/<int:pk>/delete/', views.tour_image_delete, name='image_delete'),
    
    path('<int:tour_pk>/videos/add/', views.tour_video_add, name='video_add'),
    path('<int:tour_pk>/videos/<int:pk>/delete/', views.tour_video_delete, name='video_delete'),
    
    path('<int:tour_pk>/extra-services/add/', views.tour_extra_service_add, name='extra_service_add'),
    path('<int:tour_pk>/extra-services/<int:pk>/edit/', views.tour_extra_service_update, name='extra_service_update'),
    path('<int:tour_pk>/extra-services/<int:pk>/delete/', views.tour_extra_service_delete, name='extra_service_delete'),
    
    path('<int:tour_pk>/routes/add/', views.tour_route_add, name='route_add'),
    path('<int:tour_pk>/routes/<int:pk>/edit/', views.tour_route_update, name='route_update'),
    path('<int:tour_pk>/routes/<int:pk>/delete/', views.tour_route_delete, name='route_delete'),
    
    # PDF ve Harita
    path('<int:pk>/pdf/', views.tour_pdf_program, name='pdf_program'),
    path('<int:pk>/map/', views.tour_map, name='map'),
    
    # Rezervasyonlar
    path('reservations/', views.tour_reservation_list, name='reservation_list'),
    path('reservations/create/', views.tour_reservation_create, name='reservation_create'),
    path('reservations/<int:pk>/', views.tour_reservation_detail, name='reservation_detail'),
    path('reservations/<int:pk>/edit/', views.tour_reservation_update, name='reservation_update'),
    path('reservations/<int:pk>/cancel/', views.tour_reservation_cancel, name='reservation_cancel'),
    path('reservations/<int:pk>/refund/', views.tour_reservation_refund, name='reservation_refund'),
    path('reservations/<int:pk>/voucher/', views.tour_reservation_voucher, name='reservation_voucher'),
    path('reservations/<int:pk>/voucher/send-whatsapp/', views.tour_reservation_voucher_send_whatsapp, name='reservation_voucher_send_whatsapp'),
    path('reservations/<int:pk>/payment/', views.tour_reservation_payment, name='reservation_payment'),

    # Bekleme Listesi
    path('waiting-list/', views.tour_waiting_list, name='waiting_list'),
    path('waiting-list/<int:pk>/', views.tour_waiting_list_detail, name='waiting_list_detail'),
    path('waiting-list/<int:pk>/notify/', views.tour_waiting_list_notify, name='waiting_list_notify'),
    path('waiting-list/<int:pk>/convert/', views.tour_waiting_list_convert, name='waiting_list_convert'),
    path('waiting-list/<int:pk>/cancel/', views.tour_waiting_list_cancel, name='waiting_list_cancel'),

    # AJAX Endpoints
    path('ajax/get-tour-price/', views.ajax_get_tour_price, name='ajax_get_tour_price'),
    path('ajax/get-available-capacity/', views.ajax_get_available_capacity, name='ajax_get_available_capacity'),
    path('ajax/get-tour-dates/', views.ajax_get_tour_dates, name='ajax_get_tour_dates'),
    path('ajax/calculate-reservation-total/', views.ajax_calculate_reservation_total, name='ajax_calculate_reservation_total'),
    
    # Raporlama
    path('reports/', views.reports_dashboard, name='reports_dashboard'),
    path('reports/sales/', views.report_sales, name='report_sales'),
    path('reports/reservations/', views.report_reservations, name='report_reservations'),
    path('reports/revenue/', views.report_revenue, name='report_revenue'),
    path('reports/customers/', views.report_customers, name='report_customers'),
    path('reports/tour-performance/', views.report_tour_performance, name='report_tour_performance'),
    path('reports/salesperson/', views.report_salesperson, name='report_salesperson'),
    path('reports/cancellations/', views.report_cancellations, name='report_cancellations'),
    path('reports/payments/', views.report_payments, name='report_payments'),
    path('reports/capacity/', views.report_capacity, name='report_capacity'),
    path('reports/customer-analysis/', views.report_customer_analysis, name='report_customer_analysis'),
    path('reports/agency-performance/', views.report_agency_performance, name='report_agency_performance'),
    path('reports/campaign-performance/', views.report_campaign_performance, name='report_campaign_performance'),
    path('reports/export/', views.report_export, name='report_export'),
    
    # CRM - Müşteri Yönetimi
    path('customers/', views.customer_list, name='customer_list'),
    path('customers/create/', views.customer_create, name='customer_create'),
    path('customers/<int:pk>/', views.customer_detail, name='customer_detail'),
    path('customers/<int:pk>/edit/', views.customer_update, name='customer_update'),
    path('customers/<int:pk>/delete/', views.customer_delete, name='customer_delete'),
    
    # Acente Yönetimi
    path('agencies/', views.agency_list, name='agency_list'),
    path('agencies/create/', views.agency_create, name='agency_create'),
    path('agencies/<int:pk>/', views.agency_detail, name='agency_detail'),
    path('agencies/<int:pk>/edit/', views.agency_update, name='agency_update'),
    path('agencies/<int:pk>/delete/', views.agency_delete, name='agency_delete'),
    
    # Kampanya Yönetimi
    path('campaigns/', views.campaign_list, name='campaign_list'),
    path('campaigns/create/', views.campaign_create, name='campaign_create'),
    path('campaigns/<int:pk>/', views.campaign_detail, name='campaign_detail'),
    path('campaigns/<int:pk>/edit/', views.campaign_update, name='campaign_update'),
    path('campaigns/<int:pk>/delete/', views.campaign_delete, name='campaign_delete'),
    path('campaigns/<int:campaign_pk>/promo-codes/create/', views.promo_code_create, name='promo_code_create'),
    path('promo-codes/create/', views.promo_code_create, name='promo_code_create_standalone'),
    path('promo-codes/<int:pk>/edit/', views.promo_code_update, name='promo_code_update'),
    path('promo-codes/<int:pk>/delete/', views.promo_code_delete, name='promo_code_delete'),
    
    # Operasyonel Yönetim
    path('operations/', views.operation_list, name='operation_list'),
    path('operations/guides/', views.guide_list, name='guide_list'),
    path('operations/guides/create/', views.guide_create, name='guide_create'),
    path('operations/guides/<int:pk>/', views.guide_detail, name='guide_detail'),
    path('operations/guides/<int:pk>/edit/', views.guide_update, name='guide_update'),
    path('operations/guides/<int:pk>/delete/', views.guide_delete, name='guide_delete'),
    path('operations/vehicles/', views.vehicle_list, name='vehicle_list'),
    path('operations/vehicles/create/', views.vehicle_create, name='vehicle_create'),
    path('operations/vehicles/<int:pk>/', views.vehicle_detail, name='vehicle_detail'),
    path('operations/vehicles/<int:pk>/edit/', views.vehicle_update, name='vehicle_update'),
    path('operations/vehicles/<int:pk>/delete/', views.vehicle_delete, name='vehicle_delete'),
    path('operations/hotels/', views.hotel_list, name='hotel_list'),
    path('operations/hotels/create/', views.hotel_create, name='hotel_create'),
    path('operations/hotels/<int:pk>/', views.hotel_detail, name='hotel_detail'),
    path('operations/hotels/<int:pk>/edit/', views.hotel_update, name='hotel_update'),
    path('operations/hotels/<int:pk>/delete/', views.hotel_delete, name='hotel_delete'),
    path('operations/transfers/', views.transfer_list, name='transfer_list'),
    path('operations/transfers/create/', views.transfer_create, name='transfer_create'),
    path('operations/transfers/<int:pk>/', views.transfer_detail, name='transfer_detail'),
    path('operations/transfers/<int:pk>/edit/', views.transfer_update, name='transfer_update'),
    path('operations/transfers/<int:pk>/delete/', views.transfer_delete, name='transfer_delete'),
    
    # Bildirim Şablonları
    path('notification-templates/', views.notification_template_list, name='notification_template_list'),
    path('notification-templates/create/', views.notification_template_create, name='notification_template_create'),
    path('notification-templates/<int:pk>/', views.notification_template_detail, name='notification_template_detail'),
    path('notification-templates/<int:pk>/edit/', views.notification_template_update, name='notification_template_update'),
    path('notification-templates/<int:pk>/delete/', views.notification_template_delete, name='notification_template_delete'),
    
    # AI Entegrasyonu
    path('ai/models/', views.get_available_ai_models, name='ai_models'),
    path('ai/generate-description/', views.generate_tour_description, name='ai_generate_description'),
    path('ai/generate-program/', views.generate_tour_program, name='ai_generate_program'),
]

