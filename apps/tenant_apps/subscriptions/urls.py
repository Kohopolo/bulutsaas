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
    path('upgrade-payment/<int:package_id>/', views.package_upgrade_payment, name='upgrade_payment'),
    path('upgrade-payment-callback/<int:transaction_id>/', views.upgrade_payment_callback, name='upgrade_payment_callback'),
    path('upgrade-success/', views.upgrade_success, name='upgrade_success'),
    path('upgrade-fail/', views.upgrade_fail, name='upgrade_fail'),
    
    # Paket Yenileme
    path('renew/', views.package_renew, name='renew'),
    path('payment-callback/<int:transaction_id>/', views.renew_payment_callback, name='renew_payment_callback'),
    path('renew-success/', views.renew_success, name='renew_success'),
    path('renew-fail/', views.renew_fail, name='renew_fail'),
    
    # Ödeme Geçmişi
    path('payments/', views.payment_history, name='payment_history'),
]
