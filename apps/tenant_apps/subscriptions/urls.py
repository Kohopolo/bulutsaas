"""
Tenant Subscription URLs
"""
from django.urls import path
from . import views

app_name = 'tenant_subscriptions'

urlpatterns = [
    # Dashboard
    path('', views.package_dashboard, name='dashboard'),
    path('dashboard/', views.package_dashboard, name='dashboard'),
    
    # Paket Detayları
    path('details/', views.package_details, name='details'),
    
    # Paket Yükseltme
    path('upgrade/', views.package_upgrade, name='upgrade'),
    
    # Paket Yenileme
    path('renew/', views.package_renew, name='renew'),
    
    # Ödeme Geçmişi
    path('payments/', views.payment_history, name='payment_history'),
]
