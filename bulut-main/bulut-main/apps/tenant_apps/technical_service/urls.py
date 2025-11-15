"""
Teknik Servis Modülü URLs
"""
from django.urls import path
from . import views

app_name = 'technical_service'

urlpatterns = [
    path('', views.dashboard, name='dashboard'),
    path('requests/', views.request_list, name='request_list'),
    path('requests/create/', views.request_create, name='request_create'),
    path('requests/<int:pk>/', views.request_detail, name='request_detail'),
    path('requests/<int:pk>/assign/', views.request_assign, name='request_assign'),
    path('requests/<int:pk>/start/', views.request_start, name='request_start'),
    path('requests/<int:pk>/complete/', views.request_complete, name='request_complete'),
    path('equipment/', views.equipment_list, name='equipment_list'),
    path('equipment/create/', views.equipment_create, name='equipment_create'),
    path('settings/', views.settings, name='settings'),
]

