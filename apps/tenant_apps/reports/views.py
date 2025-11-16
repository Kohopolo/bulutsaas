"""
Raporlar Modülü Views
Tüm modüllerin raporlarına erişim sağlayan merkezi dashboard
"""
from django.shortcuts import render
from django.contrib.auth.decorators import login_required
from .decorators import require_reports_permission
from apps.tenant_apps.core.context_processors import tenant_modules
from apps.tenant_apps.core.utils import is_hotels_module_enabled


@login_required
@require_reports_permission('view')
def dashboard(request):
    """
    Raporlar Dashboard - Tüm modüllerin raporlarına linkler
    """
    # Context processor'dan modül bilgilerini al
    context_data = tenant_modules(request)
    enabled_module_codes = context_data.get('enabled_module_codes', [])
    user_accessible_modules = context_data.get('user_accessible_modules', [])
    
    # Otel filtresi için parametre hazırla
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    hotel_param = ''
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        # URL'de zaten query parametresi varsa &, yoksa ? kullan
        hotel_param = f'?hotel={request.active_hotel.id}'
    
    # Kullanıcının erişebileceği modüllerin raporlarını listele
    reports_categories = []
    
    # Modül kontrolü - hem pakette aktif hem de kullanıcının yetkisi olmalı
    has_finance_module = 'finance' in enabled_module_codes and 'finance' in user_accessible_modules
    has_accounting_module = 'accounting' in enabled_module_codes and 'accounting' in user_accessible_modules
    has_tour_module = 'tours' in enabled_module_codes and 'tours' in user_accessible_modules
    has_hotel_module = 'hotels' in enabled_module_codes and 'hotels' in user_accessible_modules
    has_refunds_module = 'refunds' in enabled_module_codes and 'refunds' in user_accessible_modules
    
    # Finance Raporları
    if has_finance_module:
        reports_categories.append({
            'name': 'Kasa Yönetimi Raporları',
            'icon': 'fas fa-wallet',
            'color': 'blue',
            'reports': [
                {'name': 'Bilanço Raporu', 'url': f'/finance/reports/balance-sheet/{hotel_param}', 'icon': 'fas fa-file-invoice-dollar'},
                {'name': 'Gelir-Gider Raporu', 'url': f'/finance/reports/income-expense/{hotel_param}', 'icon': 'fas fa-chart-line'},
                {'name': 'Hesap Ekstresi', 'url': f'/finance/reports/account-statement/{hotel_param}', 'icon': 'fas fa-file-alt'},
                {'name': 'Günlük Özet', 'url': f'/finance/reports/daily-summary/{hotel_param}', 'icon': 'fas fa-calendar-day'},
                {'name': 'Aylık Özet', 'url': f'/finance/reports/monthly-summary/{hotel_param}', 'icon': 'fas fa-calendar-alt'},
                {'name': 'Yıllık Özet', 'url': f'/finance/reports/yearly-summary/{hotel_param}', 'icon': 'fas fa-calendar'},
                {'name': 'Ödeme Yöntemi Analizi', 'url': f'/finance/reports/payment-method-analysis/{hotel_param}', 'icon': 'fas fa-credit-card'},
                {'name': 'Modül Analizi', 'url': f'/finance/reports/module-analysis/{hotel_param}', 'icon': 'fas fa-chart-pie'},
                {'name': 'Trend Analizi', 'url': f'/finance/reports/trend-analysis/{hotel_param}', 'icon': 'fas fa-chart-area'},
                {'name': 'CSV Export', 'url': f'/finance/reports/export-csv/{hotel_param}', 'icon': 'fas fa-file-csv'},
            ]
        })
    
    # Accounting Raporları
    if has_accounting_module:
        reports_categories.append({
            'name': 'Muhasebe Raporları',
            'icon': 'fas fa-book',
            'color': 'green',
            'reports': [
                {'name': 'Mizan Raporu', 'url': f'/accounting/reports/trial-balance/{hotel_param}', 'icon': 'fas fa-balance-scale'},
                {'name': 'Kar-Zarar Raporu', 'url': f'/accounting/reports/profit-loss/{hotel_param}', 'icon': 'fas fa-chart-bar'},
                {'name': 'Bilanço Raporu', 'url': f'/accounting/reports/balance-sheet/{hotel_param}', 'icon': 'fas fa-file-invoice'},
                {'name': 'Hesap Detay Raporu', 'url': f'/accounting/reports/account-detail/{hotel_param}', 'icon': 'fas fa-file-alt'},
                {'name': 'Dönemsel Karşılaştırma', 'url': f'/accounting/reports/period-comparison/{hotel_param}', 'icon': 'fas fa-exchange-alt'},
                {'name': 'Fatura Analiz Raporu', 'url': f'/accounting/reports/invoice-analysis/{hotel_param}', 'icon': 'fas fa-file-invoice-dollar'},
                {'name': 'Ödeme Analiz Raporu', 'url': f'/accounting/reports/payment-analysis/{hotel_param}', 'icon': 'fas fa-money-check-alt'},
                {'name': 'Yevmiye Kaydı Analizi', 'url': f'/accounting/reports/journal-entry-analysis/{hotel_param}', 'icon': 'fas fa-book-open'},
                {'name': 'CSV Export', 'url': f'/accounting/reports/export-csv/{hotel_param}', 'icon': 'fas fa-file-csv'},
            ]
        })
    
    # Tours Raporları
    if has_tour_module:
        reports_categories.append({
            'name': 'Tur Yönetimi Raporları',
            'icon': 'fas fa-route',
            'color': 'purple',
            'reports': [
                {'name': 'Raporlar Dashboard', 'url': f'/tours/reports/{hotel_param}', 'icon': 'fas fa-tachometer-alt'},
                {'name': 'Satış Raporları', 'url': f'/tours/reports/sales/{hotel_param}', 'icon': 'fas fa-shopping-cart'},
                {'name': 'Rezervasyon Raporları', 'url': f'/tours/reports/reservations/{hotel_param}', 'icon': 'fas fa-calendar-check'},
                {'name': 'Gelir Raporları', 'url': f'/tours/reports/revenue/{hotel_param}', 'icon': 'fas fa-lira-sign'},
                {'name': 'Müşteri Raporları', 'url': f'/tours/reports/customers/{hotel_param}', 'icon': 'fas fa-users'},
                {'name': 'Tur Performans Raporları', 'url': f'/tours/reports/tour-performance/{hotel_param}', 'icon': 'fas fa-chart-line'},
                {'name': 'Satış Elemanı Raporları', 'url': f'/tours/reports/salesperson/{hotel_param}', 'icon': 'fas fa-user-tie'},
                {'name': 'İptal Raporları', 'url': f'/tours/reports/cancellations/{hotel_param}', 'icon': 'fas fa-times-circle'},
                {'name': 'Ödeme Raporları', 'url': f'/tours/reports/payments/{hotel_param}', 'icon': 'fas fa-credit-card'},
                {'name': 'Kapasite Raporları', 'url': f'/tours/reports/capacity/{hotel_param}', 'icon': 'fas fa-users-cog'},
                {'name': 'Müşteri Analizi', 'url': f'/tours/reports/customer-analysis/{hotel_param}', 'icon': 'fas fa-user-chart'},
                {'name': 'Acente Performansı', 'url': f'/tours/reports/agency-performance/{hotel_param}', 'icon': 'fas fa-building'},
                {'name': 'Kampanya Performansı', 'url': f'/tours/reports/campaign-performance/{hotel_param}', 'icon': 'fas fa-bullhorn'},
                {'name': 'Export', 'url': f'/tours/reports/export/{hotel_param}', 'icon': 'fas fa-file-export'},
            ]
        })
    
    # Hotels Raporları
    if has_hotel_module:
        reports_categories.append({
            'name': 'Otel Yönetimi Raporları',
            'icon': 'fas fa-hotel',
            'color': 'orange',
            'reports': [
                {'name': 'Kullanım Raporu', 'url': f'/hotels/reports/usage/{hotel_param}', 'icon': 'fas fa-chart-bar'},
            ]
        })
    
    # Refunds Raporları
    if has_refunds_module:
        reports_categories.append({
            'name': 'İade Yönetimi Raporları',
            'icon': 'fas fa-undo',
            'color': 'red',
            'reports': [
                {'name': 'Özet Rapor', 'url': f'/refunds/reports/summary/{hotel_param}', 'icon': 'fas fa-file-alt'},
                {'name': 'Modül Bazında Rapor', 'url': f'/refunds/reports/by-module/{hotel_param}', 'icon': 'fas fa-chart-pie'},
            ]
        })
    
    # Reception Raporları
    has_reception_module = 'reception' in enabled_module_codes and 'reception' in user_accessible_modules
    if has_reception_module:
        reports_categories.append({
            'name': 'Resepsiyon Raporları',
            'icon': 'fas fa-concierge-bell',
            'color': 'teal',
            'reports': [
                {'name': 'Gün Sonu Raporu', 'url': f'/reception/end-of-day/{hotel_param}', 'icon': 'fas fa-calendar-day'},
                {'name': 'Rezervasyon Raporları', 'url': f'/reception/reservations/{hotel_param}', 'icon': 'fas fa-calendar-check'},
                {'name': 'Oda Durumu Raporu', 'url': f'/reception/room-calendar/{hotel_param}', 'icon': 'fas fa-bed'},
            ]
        })
    
    # Housekeeping Raporları
    has_housekeeping_module = 'housekeeping' in enabled_module_codes and 'housekeeping' in user_accessible_modules
    if has_housekeeping_module:
        reports_categories.append({
            'name': 'Housekeeping Raporları',
            'icon': 'fas fa-broom',
            'color': 'cyan',
            'reports': [
                {'name': 'Görev Raporları', 'url': f'/housekeeping/tasks/{hotel_param}', 'icon': 'fas fa-tasks'},
            ]
        })
    
    # Sales Raporları
    has_sales_module = 'sales' in enabled_module_codes and 'sales' in user_accessible_modules
    if has_sales_module:
        reports_categories.append({
            'name': 'Satış Raporları',
            'icon': 'fas fa-handshake',
            'color': 'indigo',
            'reports': [
                {'name': 'Acente Raporları', 'url': f'/sales/agencies/{hotel_param}', 'icon': 'fas fa-building'},
                {'name': 'Satış Kayıtları', 'url': f'/sales/records/{hotel_param}', 'icon': 'fas fa-file-invoice'},
            ]
        })
    
    # Staff Raporları
    has_staff_module = 'staff' in enabled_module_codes and 'staff' in user_accessible_modules
    if has_staff_module:
        reports_categories.append({
            'name': 'Personel Raporları',
            'icon': 'fas fa-users-cog',
            'color': 'pink',
            'reports': [
                {'name': 'Personel Listesi', 'url': f'/staff/staff/{hotel_param}', 'icon': 'fas fa-users'},
                {'name': 'Vardiya Raporları', 'url': f'/staff/shifts/{hotel_param}', 'icon': 'fas fa-calendar-alt'},
                {'name': 'İzin Raporları', 'url': f'/staff/leaves/{hotel_param}', 'icon': 'fas fa-calendar-times'},
            ]
        })
    
    # Debug bilgisi (geliştirme için)
    import logging
    logger = logging.getLogger(__name__)
    logger.debug(f"Reports dashboard - Enabled modules: {enabled_module_codes}")
    logger.debug(f"Reports dashboard - User accessible: {user_accessible_modules}")
    logger.debug(f"Reports dashboard - Categories count: {len(reports_categories)}")
    
    context = {
        'reports_categories': reports_categories,
        'debug_enabled_modules': enabled_module_codes,  # Debug için
        'debug_user_modules': user_accessible_modules,  # Debug için
    }
    
    return render(request, 'tenant/reports/dashboard.html', context)

