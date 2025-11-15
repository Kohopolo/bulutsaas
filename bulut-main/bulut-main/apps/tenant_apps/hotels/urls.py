"""
Otel Yönetimi URLs
"""
from django.urls import path
from . import views

app_name = 'hotels'

urlpatterns = [
    # Otel Seçimi
    path('select/', views.select_hotel, name='select_hotel'),
    path('switch/<int:hotel_id>/', views.switch_hotel, name='switch_hotel'),
    
    # Otel Ayarları
    path('settings/', views.settings_list, name='settings_list'),
    
    # Bölge Yönetimi
    path('settings/regions/', views.region_list, name='region_list'),
    path('settings/regions/create/', views.region_create, name='region_create'),
    path('settings/regions/<int:pk>/edit/', views.region_update, name='region_update'),
    path('settings/regions/<int:pk>/delete/', views.region_delete, name='region_delete'),
    
    # Şehir Yönetimi
    path('settings/cities/', views.city_list, name='city_list'),
    path('settings/cities/create/', views.city_create, name='city_create'),
    path('settings/cities/<int:pk>/edit/', views.city_update, name='city_update'),
    path('settings/cities/<int:pk>/delete/', views.city_delete, name='city_delete'),
    
    # Otel Türü Yönetimi
    path('settings/hotel-types/', views.hotel_type_list, name='hotel_type_list'),
    path('settings/hotel-types/create/', views.hotel_type_create, name='hotel_type_create'),
    path('settings/hotel-types/<int:pk>/edit/', views.hotel_type_update, name='hotel_type_update'),
    path('settings/hotel-types/<int:pk>/delete/', views.hotel_type_delete, name='hotel_type_delete'),
    
    # Oda Tipi Yönetimi
    path('settings/room-types/', views.room_type_list, name='room_type_list'),
    path('settings/room-types/create/', views.room_type_create, name='room_type_create'),
    path('settings/room-types/<int:pk>/edit/', views.room_type_update, name='room_type_update'),
    path('settings/room-types/<int:pk>/delete/', views.room_type_delete, name='room_type_delete'),
    
    # Pansiyon Tipi Yönetimi
    path('settings/board-types/', views.board_type_list, name='board_type_list'),
    path('settings/board-types/create/', views.board_type_create, name='board_type_create'),
    path('settings/board-types/<int:pk>/edit/', views.board_type_update, name='board_type_update'),
    path('settings/board-types/<int:pk>/delete/', views.board_type_delete, name='board_type_delete'),
    
    # Yatak Tipi Yönetimi
    path('settings/bed-types/', views.bed_type_list, name='bed_type_list'),
    path('settings/bed-types/create/', views.bed_type_create, name='bed_type_create'),
    path('settings/bed-types/<int:pk>/edit/', views.bed_type_update, name='bed_type_update'),
    path('settings/bed-types/<int:pk>/delete/', views.bed_type_delete, name='bed_type_delete'),
    
    # Oda Özellikleri Yönetimi
    path('settings/room-features/', views.room_feature_list, name='room_feature_list'),
    path('settings/room-features/create/', views.room_feature_create, name='room_feature_create'),
    path('settings/room-features/<int:pk>/edit/', views.room_feature_update, name='room_feature_update'),
    path('settings/room-features/<int:pk>/delete/', views.room_feature_delete, name='room_feature_delete'),
    
    # Otel Özellikleri Yönetimi
    path('settings/hotel-features/', views.hotel_feature_list, name='hotel_feature_list'),
    path('settings/hotel-features/create/', views.hotel_feature_create, name='hotel_feature_create'),
    path('settings/hotel-features/<int:pk>/edit/', views.hotel_feature_update, name='hotel_feature_update'),
    path('settings/hotel-features/<int:pk>/delete/', views.hotel_feature_delete, name='hotel_feature_delete'),
    
    # Otel Yönetimi
    path('hotels/', views.hotel_list, name='hotel_list'),
    path('hotels/<int:pk>/', views.hotel_detail, name='hotel_detail'),
    path('hotels/create/', views.hotel_create, name='hotel_create'),
    path('hotels/<int:pk>/edit/', views.hotel_update, name='hotel_update'),
    path('hotels/<int:pk>/delete/', views.hotel_delete, name='hotel_delete'),
    
    # Ekstra Hizmetler
    path('extra-services/', views.extra_service_list, name='extra_service_list'),
    path('extra-services/create/', views.extra_service_create, name='extra_service_create'),
    path('extra-services/<int:pk>/edit/', views.extra_service_update, name='extra_service_update'),
    path('extra-services/<int:pk>/delete/', views.extra_service_delete, name='extra_service_delete'),
    
    # Galeri Yönetimi (AJAX)
    path('api/hotel/<int:hotel_id>/images/upload/', views.api_hotel_image_upload, name='api_hotel_image_upload'),
    path('api/hotel/image/<int:pk>/delete/', views.api_hotel_image_delete, name='api_hotel_image_delete'),
    path('api/hotel/image/<int:pk>/update/', views.api_hotel_image_update, name='api_hotel_image_update'),
    path('api/hotel/images/reorder/', views.api_hotel_images_reorder, name='api_hotel_images_reorder'),
    path('api/room/<int:room_id>/images/upload/', views.api_room_image_upload, name='api_room_image_upload'),
    path('api/room/image/<int:pk>/delete/', views.api_room_image_delete, name='api_room_image_delete'),
    path('api/room/image/<int:pk>/update/', views.api_room_image_update, name='api_room_image_update'),
    path('api/room/images/reorder/', views.api_room_images_reorder, name='api_room_images_reorder'),
    
    # Oda Yönetimi
    path('rooms/', views.room_list, name='room_list'),
    path('rooms/<int:pk>/', views.room_detail, name='room_detail'),
    path('rooms/create/', views.room_create, name='room_create'),
    path('rooms/<int:pk>/edit/', views.room_update, name='room_update'),
    path('rooms/<int:pk>/delete/', views.room_delete, name='room_delete'),
    
    # Oda Fiyatlama
    path('rooms/<int:room_id>/pricing/', views.room_price_detail, name='room_price_detail'),
    path('rooms/<int:room_id>/pricing/create/', views.room_price_create, name='room_price_create'),
    path('rooms/<int:room_id>/pricing/edit/', views.room_price_update, name='room_price_update'),
    
    # Sezonluk Fiyat
    path('pricing/seasonal/<int:room_price_id>/create/', views.room_seasonal_price_create, name='room_seasonal_price_create'),
    path('pricing/seasonal/<int:pk>/edit/', views.room_seasonal_price_update, name='room_seasonal_price_update'),
    path('pricing/seasonal/<int:pk>/delete/', views.room_seasonal_price_delete, name='room_seasonal_price_delete'),
    
    # Özel Fiyat
    path('pricing/special/<int:room_price_id>/create/', views.room_special_price_create, name='room_special_price_create'),
    path('pricing/special/<int:pk>/edit/', views.room_special_price_update, name='room_special_price_update'),
    path('pricing/special/<int:pk>/delete/', views.room_special_price_delete, name='room_special_price_delete'),
    
    # Kampanya Fiyat
    path('pricing/campaign/<int:room_price_id>/create/', views.room_campaign_price_create, name='room_campaign_price_create'),
    path('pricing/campaign/<int:pk>/edit/', views.room_campaign_price_update, name='room_campaign_price_update'),
    path('pricing/campaign/<int:pk>/delete/', views.room_campaign_price_delete, name='room_campaign_price_delete'),
    
    # Acente Fiyat
    path('pricing/agency/<int:room_price_id>/create/', views.room_agency_price_create, name='room_agency_price_create'),
    path('pricing/agency/<int:pk>/edit/', views.room_agency_price_update, name='room_agency_price_update'),
    path('pricing/agency/<int:pk>/delete/', views.room_agency_price_delete, name='room_agency_price_delete'),
    
    # Kanal Fiyat
    path('pricing/channel/<int:room_price_id>/create/', views.room_channel_price_create, name='room_channel_price_create'),
    path('pricing/channel/<int:pk>/edit/', views.room_channel_price_update, name='room_channel_price_update'),
    path('pricing/channel/<int:pk>/delete/', views.room_channel_price_delete, name='room_channel_price_delete'),
    
    # Oda Numaraları
    path('room-numbers/', views.room_number_list, name='room_number_list'),
    path('room-numbers/create/', views.room_number_create, name='room_number_create'),
    path('room-numbers/bulk-create/', views.room_number_bulk_create, name='room_number_bulk_create'),
    path('room-numbers/<int:pk>/edit/', views.room_number_update, name='room_number_update'),
    path('room-numbers/<int:pk>/delete/', views.room_number_delete, name='room_number_delete'),
    
    # Kat Yönetimi
    path('settings/floors/', views.floor_list, name='floor_list'),
    path('settings/floors/create/', views.floor_create, name='floor_create'),
    path('settings/floors/<int:pk>/edit/', views.floor_update, name='floor_update'),
    path('settings/floors/<int:pk>/delete/', views.floor_delete, name='floor_delete'),
    
    # Blok Yönetimi
    path('settings/blocks/', views.block_list, name='block_list'),
    path('settings/blocks/create/', views.block_create, name='block_create'),
    path('settings/blocks/<int:pk>/edit/', views.block_update, name='block_update'),
    path('settings/blocks/<int:pk>/delete/', views.block_delete, name='block_delete'),
    
    # Kullanıcı Otel Yetki Yönetimi
    path('users/<int:user_id>/hotel-permission/', views.user_hotel_permission_assign, name='user_hotel_permission_assign'),
    path('users/<int:user_id>/hotel-permission/<int:hotel_id>/remove/', views.user_hotel_permission_remove, name='user_hotel_permission_remove'),
    path('users/bulk-hotel-permission/', views.bulk_hotel_permission_assign, name='bulk_hotel_permission_assign'),
    
    # Bulk İşlemler
    path('settings/room-types/<int:room_type_id>/copy/', views.room_type_copy, name='room_type_copy'),
    
    # Raporlama
    path('reports/usage/', views.hotel_usage_report, name='hotel_usage_report'),
    path('reports/usage/<int:hotel_id>/', views.hotel_usage_report, name='hotel_usage_report_detail'),
    
    # API Endpoints
    path('api/accessible-hotels/', views.api_accessible_hotels, name='api_accessible_hotels'),
    path('api/module-limits/', views.api_module_limits, name='api_module_limits'),
]

