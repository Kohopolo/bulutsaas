"""
Ödeme Sistemi URL'leri
"""
from django.urls import path
from . import views

app_name = 'payments'

urlpatterns = [
    # Ödeme İşlemleri
    path('initiate/<int:package_id>/', views.initiate_payment, name='initiate'),
    path('callback/<int:transaction_id>/', views.payment_callback, name='callback'),
    path('success/', views.payment_success, name='success'),
    path('fail/', views.payment_fail, name='fail'),
    
    # Webhooks
    path('webhook/<str:gateway_code>/', views.payment_webhook, name='webhook'),
]
