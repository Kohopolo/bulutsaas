"""
Kanal Yönetimi Admin
"""
from django.contrib import admin
from .models import (
    ChannelConfiguration, ChannelSync,
    ChannelReservation, ChannelPricing, ChannelCommission
)
from apps.modules.models import ChannelTemplate  # Public schema'dan import


@admin.register(ChannelTemplate)
class ChannelTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'channel_type', 'is_active', 'is_popular', 'sort_order']
    list_filter = ['channel_type', 'is_active', 'is_popular', 'supports_pricing', 'supports_reservations']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['created_at', 'updated_at']
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'channel_type', 'description', 'icon', 'logo_url')
        }),
        ('API Bilgileri', {
            'fields': ('api_type', 'api_documentation_url', 'api_endpoint_template',
                      'required_fields', 'optional_fields')
        }),
        ('Özellikler', {
            'fields': ('supports_pricing', 'supports_availability', 'supports_reservations',
                      'supports_two_way', 'supports_commission', 'default_commission_rate')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_popular', 'sort_order')
        }),
        ('Zaman Damgaları', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(ChannelConfiguration)
class ChannelConfigurationAdmin(admin.ModelAdmin):
    list_display = ['name', 'tenant', 'template', 'hotel', 'is_active', 'sync_enabled', 'last_sync_at']
    list_filter = ['is_active', 'sync_enabled', 'is_test_mode', 'template']
    search_fields = ['name', 'tenant__name', 'hotel__name']
    readonly_fields = ['created_at', 'updated_at', 'last_sync_at', 'next_sync_at']
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('tenant', 'template', 'hotel', 'name')
        }),
        ('API Bilgileri', {
            'fields': ('api_credentials', 'api_endpoint', 'api_timeout', 'api_retry_count')
        }),
        ('Senkronizasyon Ayarları', {
            'fields': ('sync_enabled', 'sync_interval', 'last_sync_at', 'next_sync_at')
        }),
        ('Fiyat ve Müsaitlik', {
            'fields': ('auto_sync_pricing', 'auto_sync_availability',
                      'price_markup_percent', 'price_markup_amount')
        }),
        ('Komisyon Ayarları', {
            'fields': ('commission_rate', 'commission_calculation')
        }),
        ('Rezervasyon Ayarları', {
            'fields': ('auto_confirm_reservations', 'reservation_timeout',
                      'allow_modifications', 'allow_cancellations')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_test_mode', 'notes')
        }),
        ('Zaman Damgaları', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(ChannelSync)
class ChannelSyncAdmin(admin.ModelAdmin):
    list_display = ['configuration', 'sync_type', 'direction', 'status', 'started_at', 'duration_seconds']
    list_filter = ['sync_type', 'direction', 'status']
    search_fields = ['configuration__name', 'error_message']
    readonly_fields = ['created_at', 'started_at', 'completed_at', 'duration_seconds']
    date_hierarchy = 'created_at'


@admin.register(ChannelReservation)
class ChannelReservationAdmin(admin.ModelAdmin):
    list_display = ['channel_reservation_code', 'configuration', 'guest_name', 'check_in_date', 'status']
    list_filter = ['status', 'configuration', 'check_in_date']
    search_fields = ['channel_reservation_id', 'channel_reservation_code', 'guest_name', 'guest_email']
    readonly_fields = ['created_at', 'updated_at']
    date_hierarchy = 'check_in_date'


@admin.register(ChannelPricing)
class ChannelPricingAdmin(admin.ModelAdmin):
    list_display = ['configuration', 'room', 'start_date', 'end_date', 'channel_price', 'availability', 'is_active']
    list_filter = ['is_active', 'configuration', 'room']
    search_fields = ['room__name', 'configuration__name']
    date_hierarchy = 'start_date'


@admin.register(ChannelCommission)
class ChannelCommissionAdmin(admin.ModelAdmin):
    list_display = ['configuration', 'commission_date', 'commission_amount', 'currency', 'is_paid', 'payment_date']
    list_filter = ['is_paid', 'configuration', 'commission_date']
    search_fields = ['payment_reference', 'notes']
    date_hierarchy = 'commission_date'

