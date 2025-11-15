"""
Satış Yönetimi Admin
"""
from django.contrib import admin
from .models import Agency, SalesRecord, SalesTarget, SalesReport, SalesSettings


@admin.register(Agency)
class AgencyAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'commission_rate', 'is_active', 'contract_start_date']
    list_filter = ['is_active', 'hotel']


@admin.register(SalesRecord)
class SalesRecordAdmin(admin.ModelAdmin):
    list_display = ['sales_type', 'sales_date', 'sales_amount', 'agency', 'sales_person']
    list_filter = ['sales_type', 'sales_date']


@admin.register(SalesTarget)
class SalesTargetAdmin(admin.ModelAdmin):
    list_display = ['target_name', 'target_type', 'target_amount', 'period_type', 'is_active']
    list_filter = ['target_type', 'period_type', 'is_active']


@admin.register(SalesReport)
class SalesReportAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'report_date', 'report_type', 'total_sales']
    list_filter = ['report_type', 'report_date']


@admin.register(SalesSettings)
class SalesSettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'default_commission_rate', 'auto_calculate_commission']

