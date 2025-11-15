"""
Ayarlar Modülü URL Yapılandırması
"""
from django.urls import path
from . import views

app_name = 'settings'

urlpatterns = [
    # SMS Gateway Yönetimi
    path('sms-gateways/', views.sms_gateway_list, name='sms_gateway_list'),
    path('sms-gateways/create/', views.sms_gateway_create, name='sms_gateway_create'),
    path('sms-gateways/<int:pk>/', views.sms_gateway_detail, name='sms_gateway_detail'),
    path('sms-gateways/<int:pk>/edit/', views.sms_gateway_edit, name='sms_gateway_edit'),
    path('sms-gateways/<int:pk>/delete/', views.sms_gateway_delete, name='sms_gateway_delete'),
    path('sms-gateways/<int:pk>/test/', views.sms_gateway_test, name='sms_gateway_test'),
    path('sms-gateways/<int:pk>/balance/', views.sms_gateway_balance, name='sms_gateway_balance'),
    
    # SMS Şablon Yönetimi
    path('sms-templates/', views.sms_template_list, name='sms_template_list'),
    path('sms-templates/create/', views.sms_template_create, name='sms_template_create'),
    path('sms-templates/<int:pk>/', views.sms_template_detail, name='sms_template_detail'),
    path('sms-templates/<int:pk>/edit/', views.sms_template_edit, name='sms_template_edit'),
    path('sms-templates/<int:pk>/delete/', views.sms_template_delete, name='sms_template_delete'),
    path('sms-templates/<int:pk>/preview/', views.sms_template_preview, name='sms_template_preview'),
    
    # SMS Gönderim Logları
    path('sms-logs/', views.sms_log_list, name='sms_log_list'),
    path('sms-logs/<int:pk>/', views.sms_log_detail, name='sms_log_detail'),
    
    # Email Gateway Yönetimi
    path('email-gateways/', views.email_gateway_list, name='email_gateway_list'),
    path('email-gateways/create/', views.email_gateway_create, name='email_gateway_create'),
    path('email-gateways/<int:pk>/', views.email_gateway_detail, name='email_gateway_detail'),
    path('email-gateways/<int:pk>/edit/', views.email_gateway_edit, name='email_gateway_edit'),
    path('email-gateways/<int:pk>/delete/', views.email_gateway_delete, name='email_gateway_delete'),
    path('email-gateways/<int:pk>/test/', views.email_gateway_test, name='email_gateway_test'),
    
    # Email Şablon Yönetimi
    path('email-templates/', views.email_template_list, name='email_template_list'),
    path('email-templates/create/', views.email_template_create, name='email_template_create'),
    path('email-templates/<int:pk>/', views.email_template_detail, name='email_template_detail'),
    path('email-templates/<int:pk>/edit/', views.email_template_edit, name='email_template_edit'),
    path('email-templates/<int:pk>/delete/', views.email_template_delete, name='email_template_delete'),
    
    # Email Gönderim Logları
    path('email-logs/', views.email_log_list, name='email_log_list'),
    path('email-logs/<int:pk>/', views.email_log_detail, name='email_log_detail'),
]

