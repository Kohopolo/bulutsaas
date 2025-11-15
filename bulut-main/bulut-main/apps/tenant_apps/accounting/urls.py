"""
Muhasebe Modülü URL Yapılandırması
"""
from django.urls import path
from . import views, views_reports

app_name = 'accounting'

urlpatterns = [
    # Hesap Planı
    path('accounts/', views.account_list, name='account_list'),
    path('accounts/create/', views.account_create, name='account_create'),
    path('accounts/<int:pk>/', views.account_detail, name='account_detail'),
    path('accounts/<int:pk>/edit/', views.account_update, name='account_update'),
    path('accounts/<int:pk>/delete/', views.account_delete, name='account_delete'),
    
    # Yevmiye Kayıtları
    path('journal-entries/', views.journal_entry_list, name='journal_entry_list'),
    path('journal-entries/create/', views.journal_entry_create, name='journal_entry_create'),
    path('journal-entries/<int:pk>/', views.journal_entry_detail, name='journal_entry_detail'),
    path('journal-entries/<int:pk>/edit/', views.journal_entry_update, name='journal_entry_update'),
    path('journal-entries/<int:pk>/post/', views.journal_entry_post, name='journal_entry_post'),
    path('journal-entries/<int:pk>/cancel/', views.journal_entry_cancel, name='journal_entry_cancel'),
    
    # Faturalar
    path('invoices/', views.invoice_list, name='invoice_list'),
    path('invoices/create/', views.invoice_create, name='invoice_create'),
    path('invoices/<int:pk>/', views.invoice_detail, name='invoice_detail'),
    path('invoices/<int:pk>/edit/', views.invoice_update, name='invoice_update'),
    path('invoices/<int:pk>/delete/', views.invoice_delete, name='invoice_delete'),
    
    # Ödemeler
    path('payments/', views.payment_list, name='payment_list'),
    path('payments/create/', views.payment_create, name='payment_create'),
    path('payments/<int:pk>/', views.payment_detail, name='payment_detail'),
    path('payments/<int:pk>/edit/', views.payment_update, name='payment_update'),
    path('payments/<int:pk>/complete/', views.payment_complete, name='payment_complete'),
    
    # Raporlar
    path('reports/trial-balance/', views.report_trial_balance, name='report_trial_balance'),
    path('reports/profit-loss/', views.report_profit_loss, name='report_profit_loss'),
    path('reports/balance-sheet/', views.report_balance_sheet, name='report_balance_sheet'),
    
    # Detaylı Raporlar
    path('reports/account-detail/', views_reports.report_account_detail, name='report_account_detail'),
    path('reports/period-comparison/', views_reports.report_period_comparison, name='report_period_comparison'),
    path('reports/invoice-analysis/', views_reports.report_invoice_analysis, name='report_invoice_analysis'),
    path('reports/payment-analysis/', views_reports.report_payment_analysis, name='report_payment_analysis'),
    path('reports/journal-entry-analysis/', views_reports.report_journal_entry_analysis, name='report_journal_entry_analysis'),
    path('reports/export-csv/', views_reports.report_export_csv, name='report_export_csv'),
]

