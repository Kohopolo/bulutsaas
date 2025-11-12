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
]

