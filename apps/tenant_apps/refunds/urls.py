"""
İade Yönetimi Modülü URL Yapılandırması
"""
from django.urls import path
from . import views, views_reports

app_name = 'refunds'

urlpatterns = [
    # İade Politikaları
    path('policies/', views.policy_list, name='policy_list'),
    path('policies/create/', views.policy_create, name='policy_create'),
    path('policies/<int:pk>/', views.policy_detail, name='policy_detail'),
    path('policies/<int:pk>/edit/', views.policy_update, name='policy_update'),
    path('policies/<int:pk>/delete/', views.policy_delete, name='policy_delete'),
    
    # İade Talepleri
    path('requests/', views.request_list, name='request_list'),
    path('requests/create/', views.request_create, name='request_create'),
    path('requests/<int:pk>/', views.request_detail, name='request_detail'),
    path('requests/<int:pk>/edit/', views.request_update, name='request_update'),
    path('requests/<int:pk>/approve/', views.request_approve, name='request_approve'),
    path('requests/<int:pk>/reject/', views.request_reject, name='request_reject'),
    path('requests/<int:pk>/process/', views.request_process, name='request_process'),
    path('requests/<int:pk>/complete/', views.request_complete, name='request_complete'),
    
    # İade İşlemleri
    path('transactions/', views.transaction_list, name='transaction_list'),
    path('transactions/<int:pk>/', views.transaction_detail, name='transaction_detail'),
    path('transactions/<int:pk>/complete/', views.transaction_complete, name='transaction_complete'),
    
    # Raporlar
    path('reports/summary/', views.report_summary, name='report_summary'),
    path('reports/by-module/', views.report_by_module, name='report_by_module'),
    
    # Detaylı Raporlar
    path('reports/trend-analysis/', views_reports.report_trend_analysis, name='report_trend_analysis'),
    path('reports/customer-analysis/', views_reports.report_customer_analysis, name='report_customer_analysis'),
    path('reports/refund-method-analysis/', views_reports.report_refund_method_analysis, name='report_refund_method_analysis'),
    path('reports/processing-time-analysis/', views_reports.report_processing_time_analysis, name='report_processing_time_analysis'),
    path('reports/policy-performance/', views_reports.report_policy_performance, name='report_policy_performance'),
    path('reports/export-csv/', views_reports.report_export_csv, name='report_export_csv'),
]

