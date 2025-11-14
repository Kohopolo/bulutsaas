"""
Bungalov Admin Configuration
"""
from django.contrib import admin
from .models import (
    BungalovType, BungalovFeature, Bungalov,
    BungalovReservation, BungalovReservationGuest, BungalovReservationPayment,
    BungalovCleaning, BungalovMaintenance, BungalovEquipment,
    BungalovPrice, BungalovVoucherTemplate, BungalovVoucher
)


@admin.register(BungalovType)
class BungalovTypeAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'max_adults', 'max_children', 'bedroom_count', 'is_active']
    list_filter = ['is_active', 'has_kitchen', 'has_living_room']
    search_fields = ['name', 'code']
    ordering = ['sort_order', 'name']


@admin.register(BungalovFeature)
class BungalovFeatureAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'category', 'is_active', 'sort_order']
    list_filter = ['category', 'is_active']
    search_fields = ['name', 'code']
    ordering = ['category', 'sort_order', 'name']


@admin.register(Bungalov)
class BungalovAdmin(admin.ModelAdmin):
    list_display = ['code', 'name', 'bungalov_type', 'location', 'status', 'is_active']
    list_filter = ['status', 'bungalov_type', 'is_active', 'location']
    search_fields = ['code', 'name', 'location']
    filter_horizontal = ['features']
    readonly_fields = ['created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('code', 'name', 'bungalov_type', 'location', 'floor_number')
        }),
        ('Pozisyon', {
            'fields': ('position_x', 'position_y')
        }),
        ('Özellikler', {
            'fields': ('features',)
        }),
        ('Durum', {
            'fields': ('status', 'is_active')
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(BungalovReservation)
class BungalovReservationAdmin(admin.ModelAdmin):
    list_display = ['reservation_code', 'customer', 'bungalov', 'check_in_date', 'check_out_date', 'status', 'total_amount']
    list_filter = ['status', 'source', 'check_in_date', 'is_comp', 'is_no_show']
    search_fields = ['reservation_code', 'customer__first_name', 'customer__last_name', 'customer__email']
    readonly_fields = ['reservation_code', 'total_nights', 'total_amount', 'created_at', 'updated_at']
    date_hierarchy = 'check_in_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('reservation_code', 'bungalov', 'customer')
        }),
        ('Tarih Bilgileri', {
            'fields': ('check_in_date', 'check_out_date', 'check_in_time', 'check_out_time', 'total_nights')
        }),
        ('Misafir Bilgileri', {
            'fields': ('adult_count', 'child_count', 'infant_count', 'child_ages')
        }),
        ('Rezervasyon Bilgileri', {
            'fields': ('status', 'source', 'reservation_agent', 'reservation_channel')
        }),
        ('Fiyatlandırma', {
            'fields': ('nightly_rate', 'weekly_rate', 'monthly_rate', 'is_manual_price',
                     'discount_type', 'discount_percentage', 'discount_amount',
                     'cleaning_fee', 'extra_person_fee', 'pet_fee',
                     'early_check_in_fee', 'late_check_out_fee',
                     'tax_amount', 'total_amount', 'total_paid', 'currency')
        }),
        ('Depozito', {
            'fields': ('deposit_amount', 'deposit_paid', 'deposit_returned')
        }),
        ('Özel Durumlar', {
            'fields': ('is_comp', 'is_no_show', 'no_show_reason')
        }),
        ('Notlar', {
            'fields': ('special_requests', 'internal_notes')
        }),
        ('Durum Bilgileri', {
            'fields': ('is_checked_in', 'is_checked_out', 'checked_in_at', 'checked_out_at',
                      'early_check_in', 'late_check_out')
        }),
        ('İptal Bilgileri', {
            'fields': ('is_cancelled', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'cancellation_refund_amount')
        }),
        ('Kullanıcı Takibi', {
            'fields': ('created_by', 'updated_by', 'deleted_by')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(BungalovReservationGuest)
class BungalovReservationGuestAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'first_name', 'last_name', 'guest_type', 'age', 'tc_no', 'passport_no']
    list_filter = ['guest_type', 'gender']
    search_fields = ['first_name', 'last_name', 'tc_no', 'passport_no', 'reservation__reservation_code']


@admin.register(BungalovReservationPayment)
class BungalovReservationPaymentAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'payment_date', 'payment_amount', 'payment_method', 'payment_type']
    list_filter = ['payment_method', 'payment_type', 'payment_date']
    search_fields = ['reservation__reservation_code', 'payment_reference', 'transaction_id']


@admin.register(BungalovCleaning)
class BungalovCleaningAdmin(admin.ModelAdmin):
    list_display = ['bungalov', 'cleaning_type', 'cleaning_date', 'status', 'assigned_to', 'completed_by']
    list_filter = ['cleaning_type', 'status', 'cleaning_date']
    search_fields = ['bungalov__code', 'bungalov__name']
    date_hierarchy = 'cleaning_date'


@admin.register(BungalovMaintenance)
class BungalovMaintenanceAdmin(admin.ModelAdmin):
    list_display = ['bungalov', 'title', 'maintenance_type', 'priority', 'status', 'planned_date', 'assigned_to']
    list_filter = ['maintenance_type', 'priority', 'status', 'planned_date']
    search_fields = ['bungalov__code', 'title', 'description']
    date_hierarchy = 'planned_date'


@admin.register(BungalovEquipment)
class BungalovEquipmentAdmin(admin.ModelAdmin):
    list_display = ['bungalov', 'name', 'category', 'brand', 'model', 'status']
    list_filter = ['category', 'status']
    search_fields = ['bungalov__code', 'name', 'brand', 'model', 'serial_number']


@admin.register(BungalovPrice)
class BungalovPriceAdmin(admin.ModelAdmin):
    list_display = ['bungalov_type', 'price_type', 'season', 'start_date', 'end_date', 'base_price', 'is_active']
    list_filter = ['price_type', 'season', 'is_active']
    search_fields = ['bungalov_type__name']
    date_hierarchy = 'start_date'


@admin.register(BungalovVoucherTemplate)
class BungalovVoucherTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_active', 'is_default']
    list_filter = ['is_active', 'is_default']
    search_fields = ['name', 'code']


@admin.register(BungalovVoucher)
class BungalovVoucherAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'voucher_code', 'template', 'is_sent', 'sent_method', 'sent_at']
    list_filter = ['is_sent', 'sent_method', 'sent_at']
    search_fields = ['reservation__reservation_code', 'voucher_code', 'voucher_token']
    readonly_fields = ['voucher_code', 'voucher_token', 'created_at', 'updated_at']

