"""
Feribot Bileti Admin
"""
from django.contrib import admin
from .models import (
    Ferry, FerryRoute, FerrySchedule, FerryTicket, FerryTicketGuest,
    FerryTicketPayment, FerryTicketVoucher, FerryTicketVoucherTemplate,
    FerryAPIConfiguration, FerryAPISync
)


@admin.register(Ferry)
class FerryAdmin(admin.ModelAdmin):
    list_display = ['name', 'company_name', 'capacity_passengers', 'capacity_vehicles', 'is_active']
    list_filter = ['is_active', 'company_name']
    search_fields = ['name', 'company_name', 'code']
    ordering = ['name']


@admin.register(FerryRoute)
class FerryRouteAdmin(admin.ModelAdmin):
    list_display = ['name', 'departure_port', 'arrival_port', 'is_active', 'is_international']
    list_filter = ['is_active', 'is_international', 'departure_country', 'arrival_country']
    search_fields = ['name', 'departure_port', 'arrival_port', 'code']
    ordering = ['name']


@admin.register(FerrySchedule)
class FerryScheduleAdmin(admin.ModelAdmin):
    list_display = ['route', 'ferry', 'departure_date', 'departure_time', 'is_active', 'is_cancelled']
    list_filter = ['is_active', 'is_cancelled', 'departure_date', 'route']
    search_fields = ['route__name', 'ferry__name', 'external_id']
    date_hierarchy = 'departure_date'
    ordering = ['-departure_date', 'departure_time']


@admin.register(FerryTicket)
class FerryTicketAdmin(admin.ModelAdmin):
    list_display = ['ticket_code', 'schedule', 'customer', 'status', 'total_amount', 'total_paid', 'created_at']
    list_filter = ['status', 'source', 'is_cancelled', 'is_used', 'created_at']
    search_fields = ['ticket_code', 'customer__first_name', 'customer__last_name', 'customer__phone']
    date_hierarchy = 'created_at'
    ordering = ['-created_at']


@admin.register(FerryTicketGuest)
class FerryTicketGuestAdmin(admin.ModelAdmin):
    list_display = ['ticket', 'first_name', 'last_name', 'ticket_type', 'tc_no', 'passport_no']
    list_filter = ['ticket_type', 'gender', 'nationality']
    search_fields = ['first_name', 'last_name', 'tc_no', 'passport_no']
    ordering = ['ticket', 'guest_order']


@admin.register(FerryTicketPayment)
class FerryTicketPaymentAdmin(admin.ModelAdmin):
    list_display = ['ticket', 'payment_date', 'payment_amount', 'payment_method', 'payment_type']
    list_filter = ['payment_method', 'payment_type', 'payment_date']
    search_fields = ['ticket__ticket_code', 'payment_reference']
    date_hierarchy = 'payment_date'
    ordering = ['-payment_date']


@admin.register(FerryTicketVoucher)
class FerryTicketVoucherAdmin(admin.ModelAdmin):
    list_display = ['voucher_code', 'ticket', 'payment_status', 'is_sent', 'sent_via', 'created_at']
    list_filter = ['payment_status', 'is_sent', 'sent_via', 'created_at']
    search_fields = ['voucher_code', 'ticket__ticket_code', 'access_token']
    ordering = ['-created_at']


@admin.register(FerryTicketVoucherTemplate)
class FerryTicketVoucherTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_active', 'is_default', 'created_at']
    list_filter = ['is_active', 'is_default']
    search_fields = ['name', 'code']
    ordering = ['-is_default', 'name']


@admin.register(FerryAPIConfiguration)
class FerryAPIConfigurationAdmin(admin.ModelAdmin):
    list_display = ['name', 'provider', 'is_active', 'is_test_mode', 'auto_sync_schedules', 'last_sync_at']
    list_filter = ['provider', 'is_active', 'is_test_mode', 'auto_sync_schedules']
    search_fields = ['name', 'code', 'api_url']
    ordering = ['name']


@admin.register(FerryAPISync)
class FerryAPISyncAdmin(admin.ModelAdmin):
    list_display = ['api_config', 'sync_type', 'status', 'schedules_fetched', 'schedules_created', 'started_at']
    list_filter = ['status', 'sync_type', 'started_at']
    search_fields = ['api_config__name', 'error_message']
    date_hierarchy = 'started_at'
    ordering = ['-started_at']

