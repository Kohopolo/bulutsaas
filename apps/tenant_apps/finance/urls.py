"""
Kasa Modülü URL Yapılandırması
"""
from django.urls import path
from . import views, views_reports

app_name = 'finance'

urlpatterns = [
    # Kasa Hesapları
    path('accounts/', views.account_list, name='account_list'),
    path('accounts/create/', views.account_create, name='account_create'),
    path('accounts/<int:pk>/', views.account_detail, name='account_detail'),
    path('accounts/<int:pk>/edit/', views.account_update, name='account_update'),
    path('accounts/<int:pk>/delete/', views.account_delete, name='account_delete'),
    path('accounts/<int:pk>/calculate-balance/', views.account_calculate_balance, name='account_calculate_balance'),
    
    # Kasa İşlemleri
    path('transactions/', views.transaction_list, name='transaction_list'),
    path('transactions/create/', views.transaction_create, name='transaction_create'),
    path('transactions/<int:pk>/', views.transaction_detail, name='transaction_detail'),
    path('transactions/<int:pk>/edit/', views.transaction_update, name='transaction_update'),
    path('transactions/<int:pk>/delete/', views.transaction_delete, name='transaction_delete'),
    path('transactions/<int:pk>/complete/', views.transaction_complete, name='transaction_complete'),
    path('transactions/<int:pk>/cancel/', views.transaction_cancel, name='transaction_cancel'),
    path('transactions/<int:pk>/reverse/', views.transaction_reverse, name='transaction_reverse'),
    
    # Nakit Akışı
    path('cash-flow/', views.cash_flow_list, name='cash_flow_list'),
    path('cash-flow/create/', views.cash_flow_create, name='cash_flow_create'),
    path('cash-flow/<int:pk>/', views.cash_flow_detail, name='cash_flow_detail'),
    path('cash-flow/<int:pk>/recalculate/', views.cash_flow_recalculate, name='cash_flow_recalculate'),
    
    # Raporlar
    path('reports/balance-sheet/', views.report_balance_sheet, name='report_balance_sheet'),
    path('reports/income-expense/', views.report_income_expense, name='report_income_expense'),
    path('reports/account-statement/', views.report_account_statement, name='report_account_statement'),
    
    # Detaylı Raporlar
    path('reports/daily-summary/', views_reports.report_daily_summary, name='report_daily_summary'),
    path('reports/monthly-summary/', views_reports.report_monthly_summary, name='report_monthly_summary'),
    path('reports/yearly-summary/', views_reports.report_yearly_summary, name='report_yearly_summary'),
    path('reports/payment-method-analysis/', views_reports.report_payment_method_analysis, name='report_payment_method_analysis'),
    path('reports/module-analysis/', views_reports.report_module_analysis, name='report_module_analysis'),
    path('reports/trend-analysis/', views_reports.report_trend_analysis, name='report_trend_analysis'),
    path('reports/export-csv/', views_reports.report_export_csv, name='report_export_csv'),
]

