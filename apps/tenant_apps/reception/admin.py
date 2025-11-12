"""
Reception Admin Configuration
"""
from django.contrib import admin
from .models import Reservation


@admin.register(Reservation)
class ReservationAdmin(admin.ModelAdmin):
    list_display = ['reservation_code', 'customer', 'hotel', 'room', 'check_in_date', 'check_out_date', 'status', 'total_amount']
    list_filter = ['status', 'source', 'hotel', 'check_in_date']
    search_fields = ['reservation_code', 'customer__first_name', 'customer__last_name', 'customer__email']
    readonly_fields = ['reservation_code', 'total_nights', 'total_amount', 'created_at', 'updated_at']
    date_hierarchy = 'check_in_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('reservation_code', 'hotel', 'room', 'room_number', 'customer')
        }),
        ('Tarih Bilgileri', {
            'fields': ('check_in_date', 'check_out_date', 'check_in_time', 'check_out_time')
        }),
        ('Misafir Bilgileri', {
            'fields': ('adult_count', 'child_count', 'child_ages')
        }),
        ('Rezervasyon Bilgileri', {
            'fields': ('status', 'source')
        }),
        ('Fiyatlandırma', {
            'fields': ('room_rate', 'total_nights', 'discount_amount', 'tax_amount', 'total_amount', 'total_paid', 'currency')
        }),
        ('Notlar', {
            'fields': ('special_requests', 'internal_notes')
        }),
        ('Durum Bilgileri', {
            'fields': ('is_checked_in', 'is_checked_out', 'checked_in_at', 'checked_out_at')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )

