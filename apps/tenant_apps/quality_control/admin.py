"""
Kalite Kontrol Admin
"""
from django.contrib import admin
from .models import RoomQualityInspection, QualityChecklistItem, CustomerComplaint, QualityStandard, QualityAuditReport, QualityControlSettings


@admin.register(RoomQualityInspection)
class RoomQualityInspectionAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'inspection_type', 'overall_score', 'status', 'inspected_by', 'inspected_at']
    list_filter = ['status', 'inspection_type', 'hotel']


@admin.register(QualityChecklistItem)
class QualityChecklistItemAdmin(admin.ModelAdmin):
    list_display = ['inspection', 'item_name', 'category', 'is_checked', 'score']
    list_filter = ['category', 'is_checked']


@admin.register(CustomerComplaint)
class CustomerComplaintAdmin(admin.ModelAdmin):
    list_display = ['complaint_type', 'priority', 'status', 'reported_by', 'reported_at']
    list_filter = ['status', 'priority', 'complaint_type']


@admin.register(QualityStandard)
class QualityStandardAdmin(admin.ModelAdmin):
    list_display = ['name', 'category', 'minimum_score', 'is_active']
    list_filter = ['category', 'is_active']


@admin.register(QualityAuditReport)
class QualityAuditReportAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'report_date', 'total_inspections', 'average_score']
    list_filter = ['report_date']


@admin.register(QualityControlSettings)
class QualityControlSettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'require_pre_checkin_inspection', 'minimum_overall_score']

