"""
Tenant AI URLs
"""
from django.urls import path
from . import views

app_name = 'ai'

urlpatterns = [
    # AI Kredi Yönetimi
    path('credits/', views.credit_dashboard, name='credit_dashboard'),
    path('credits/add/', views.credit_add, name='credit_add'),
    path('credits/history/', views.credit_history, name='credit_history'),
    
    # AI Kullanım Logları
    path('usage/', views.usage_list, name='usage_list'),
    path('usage/<int:pk>/', views.usage_detail, name='usage_detail'),
]

