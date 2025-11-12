"""
Teknik Servis Admin
"""
from django.contrib import admin
from .models import MaintenanceRequest, MaintenanceRecord, Equipment, TechnicalServiceSettings


@admin.register(MaintenanceRequest)
class MaintenanceRequestAdmin(admin.ModelAdmin):
    list_display = ['room_number', 'request_type', 'priority', 'status', 'reported_by', 'reported_at']
    list_filter = ['status', 'priority', 'request_type', 'hotel']
    search_fields = ['description', 'room_number__number']


@admin.register(MaintenanceRecord)
class MaintenanceRecordAdmin(admin.ModelAdmin):
    list_display = ['equipment_name', 'maintenance_type', 'performed_by', 'performed_at', 'cost']
    list_filter = ['maintenance_type', 'equipment_type']


@admin.register(Equipment)
class EquipmentAdmin(admin.ModelAdmin):
    list_display = ['name', 'equipment_type', 'status', 'room_number', 'next_maintenance_date']
    list_filter = ['status', 'equipment_type']


@admin.register(TechnicalServiceSettings)
class TechnicalServiceSettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'auto_assign_requests', 'default_priority']

