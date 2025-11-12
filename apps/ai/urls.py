"""
AI Yönetim URLs
Super Admin paneli için
"""
from django.urls import path
from . import views

app_name = 'ai'

urlpatterns = [
    # AI Provider Yönetimi
    path('providers/', views.provider_list, name='provider_list'),
    path('providers/create/', views.provider_create, name='provider_create'),
    path('providers/<int:pk>/', views.provider_detail, name='provider_detail'),
    path('providers/<int:pk>/edit/', views.provider_update, name='provider_update'),
    path('providers/<int:pk>/delete/', views.provider_delete, name='provider_delete'),
    path('providers/<int:pk>/update-api-key/', views.provider_update_api_key, name='provider_update_api_key'),
    
    # AI Model Yönetimi
    path('models/', views.model_list, name='model_list'),
    path('models/create/', views.model_create, name='model_create'),
    path('models/<int:pk>/', views.model_detail, name='model_detail'),
    path('models/<int:pk>/edit/', views.model_update, name='model_update'),
    path('models/<int:pk>/delete/', views.model_delete, name='model_delete'),
    
    # Paket AI Yönetimi
    path('package-ai/', views.package_ai_list, name='package_ai_list'),
    path('package-ai/create/', views.package_ai_create, name='package_ai_create'),
    path('package-ai/<int:pk>/edit/', views.package_ai_update, name='package_ai_update'),
    path('package-ai/<int:pk>/delete/', views.package_ai_delete, name='package_ai_delete'),
]

