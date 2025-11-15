"""
Kat Hizmetleri Admin
"""
from django.contrib import admin
from .models import (
    CleaningTask, CleaningChecklistItem, MissingItem,
    LaundryItem, MaintenanceRequest, HousekeepingSettings,
    HousekeepingDailyReport
)


@admin.register(CleaningTask)
class CleaningTaskAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'task_type', 'status', 'priority', 'assigned_to', 'scheduled_time']
    list_filter = ['status', 'priority', 'task_type', 'hotel']
    search_fields = ['room_number__number', 'notes']
    readonly_fields = ['created_at', 'updated_at']


@admin.register(CleaningChecklistItem)
class CleaningChecklistItemAdmin(admin.ModelAdmin):
    list_display = ['task', 'item_name', 'category', 'is_checked']
    list_filter = ['category', 'is_checked']


@admin.register(MissingItem)
class MissingItemAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'item_name', 'status', 'reported_by', 'reported_at']
    list_filter = ['status', 'item_category']


@admin.register(LaundryItem)
class LaundryItemAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'item_type', 'quantity', 'status', 'collected_at']
    list_filter = ['status', 'item_type']


@admin.register(MaintenanceRequest)
class MaintenanceRequestAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'request_type', 'priority', 'status', 'reported_by']
    list_filter = ['status', 'priority', 'request_type']


@admin.register(HousekeepingSettings)
class HousekeepingSettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'default_cleaning_duration', 'require_inspection']


@admin.register(HousekeepingDailyReport)
class HousekeepingDailyReportAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'report_date', 'total_tasks', 'completed_tasks']
    list_filter = ['report_date', 'hotel']

