"""
Kanal Yönetimi URL Configuration
"""
from django.urls import path
from . import views

app_name = 'channel_management'

urlpatterns = [
    # Dashboard / Ana Sayfa
    path('', views.configuration_list, name='dashboard'),
    
    # Kanal Şablonları
    path('templates/', views.template_list, name='template_list'),
    path('templates/<int:pk>/', views.template_detail, name='template_detail'),
    path('templates/<int:pk>/test-connection/', views.test_template_connection, name='test_template_connection'),
    
    # Kanal Konfigürasyonları
    path('configurations/', views.configuration_list, name='configuration_list'),
    path('configurations/create/', views.configuration_create, name='configuration_create'),
    path('configurations/<int:pk>/', views.configuration_detail, name='configuration_detail'),
    path('configurations/<int:pk>/edit/', views.configuration_update, name='configuration_update'),
    path('configurations/<int:pk>/delete/', views.configuration_delete, name='configuration_delete'),
    
    # Senkronizasyon
    path('configurations/<int:pk>/sync/', views.sync_trigger, name='sync_trigger'),
    path('configurations/<int:pk>/syncs/', views.sync_list, name='sync_list'),
    
    # Rezervasyonlar
    path('reservations/', views.reservation_list, name='reservation_list'),
    path('reservations/<int:pk>/', views.reservation_detail, name='reservation_detail'),
    
    # Fiyatlar
    path('pricing/', views.pricing_list, name='pricing_list'),
    path('pricing/create/', views.pricing_create, name='pricing_create'),
    path('pricing/<int:pk>/update/', views.pricing_update, name='pricing_update'),
    path('pricing/<int:pk>/delete/', views.pricing_delete, name='pricing_delete'),
    
    # Komisyonlar
    path('commissions/', views.commission_list, name='commission_list'),
    path('commissions/<int:pk>/', views.commission_detail, name='commission_detail'),
]

