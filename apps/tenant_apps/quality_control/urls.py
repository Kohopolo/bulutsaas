"""
Kalite Kontrol Modülü URLs
"""
from django.urls import path
from . import views

app_name = 'quality_control'

urlpatterns = [
    path('', views.dashboard, name='dashboard'),
    path('inspections/', views.inspection_list, name='inspection_list'),
    path('inspections/create/', views.inspection_create, name='inspection_create'),
    path('inspections/<int:pk>/', views.inspection_detail, name='inspection_detail'),
    path('complaints/', views.complaint_list, name='complaint_list'),
    path('complaints/create/', views.complaint_create, name='complaint_create'),
    path('settings/', views.settings, name='settings'),
]

