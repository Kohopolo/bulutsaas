"""
Ödeme Yönetimi Modülü URL'leri
"""
from django.urls import path
from . import views

app_name = 'payment_management'

urlpatterns = [
    # Gateway Yönetimi
    path('gateways/', views.gateway_list, name='gateway_list'),
    path('gateways/create/', views.gateway_create, name='gateway_create'),
    path('gateways/<int:pk>/update/', views.gateway_update, name='gateway_update'),
    path('gateways/<int:pk>/delete/', views.gateway_delete, name='gateway_delete'),
    path('gateways/<int:pk>/toggle-active/', views.gateway_toggle_active, name='gateway_toggle_active'),
    
    # İşlemler
    path('transactions/', views.transaction_list, name='transaction_list'),
]





