"""
Reception Admin Configuration
"""
from django.contrib import admin
from .models import (
    Reservation, ReservationGuest, ReservationPayment,
    ReservationTimeline, ReservationVoucher, VoucherTemplate,
    EndOfDayOperation, EndOfDaySettings, EndOfDayOperationStep,
    EndOfDayReport, EndOfDayJournalEntry
)


@admin.register(Reservation)
class ReservationAdmin(admin.ModelAdmin):
    list_display = ['reservation_code', 'customer', 'hotel', 'room', 'check_in_date', 'check_out_date', 'status', 'total_amount']
    list_filter = ['status', 'source', 'hotel', 'check_in_date', 'is_comp', 'is_no_show']
    search_fields = ['reservation_code', 'customer__first_name', 'customer__last_name', 'customer__email']
    readonly_fields = ['reservation_code', 'total_nights', 'total_amount', 'created_at', 'updated_at']
    date_hierarchy = 'check_in_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('reservation_code', 'hotel', 'room', 'room_number', 'customer')
        }),
        ('Tarih Bilgileri', {
            'fields': ('check_in_date', 'check_out_date', 'check_in_time', 'check_out_time', 'total_nights')
        }),
        ('Misafir Bilgileri', {
            'fields': ('adult_count', 'child_count', 'child_ages')
        }),
        ('Rezervasyon Bilgileri', {
            'fields': ('status', 'source', 'reservation_agent', 'reservation_channel')
        }),
        ('Fiyatlandırma', {
            'fields': ('room_rate', 'is_manual_price', 'discount_type', 'discount_percentage', 
                     'discount_amount', 'tax_amount', 'total_amount', 'total_paid', 'currency')
        }),
        ('Özel Durumlar', {
            'fields': ('is_comp', 'is_no_show', 'no_show_reason')
        }),
        ('Notlar', {
            'fields': ('special_requests', 'internal_notes')
        }),
        ('Durum Bilgileri', {
            'fields': ('is_checked_in', 'is_checked_out', 'checked_in_at', 'checked_out_at',
                      'early_check_in', 'early_check_in_fee', 'late_check_out', 'late_check_out_fee')
        }),
        ('İptal Bilgileri', {
            'fields': ('is_cancelled', 'cancelled_at', 'cancellation_reason', 'cancellation_refund_amount')
        }),
        ('Kullanıcı Takibi', {
            'fields': ('created_by', 'updated_by')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(ReservationGuest)
class ReservationGuestAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'first_name', 'last_name', 'guest_type', 'tc_no', 'passport_no']
    list_filter = ['guest_type', 'gender']
    search_fields = ['first_name', 'last_name', 'tc_no', 'passport_no', 'reservation__reservation_code']


@admin.register(ReservationPayment)
class ReservationPaymentAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'payment_date', 'payment_amount', 'payment_method', 'payment_type']
    list_filter = ['payment_method', 'payment_type', 'payment_date']
    search_fields = ['reservation__reservation_code', 'receipt_no']


@admin.register(ReservationTimeline)
class ReservationTimelineAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'action_type', 'user', 'created_at']
    list_filter = ['action_type', 'created_at']
    search_fields = ['reservation__reservation_code']
    readonly_fields = ['created_at']


@admin.register(ReservationVoucher)
class ReservationVoucherAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'voucher_code', 'voucher_template', 'is_sent', 'sent_via']
    list_filter = ['is_sent', 'sent_via']
    search_fields = ['reservation__reservation_code', 'voucher_code']


@admin.register(VoucherTemplate)
class VoucherTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_active', 'is_default']
    list_filter = ['is_active', 'is_default']
    search_fields = ['name', 'code']


# ==================== GÜN SONU İŞLEMLERİ ADMIN ====================

@admin.register(EndOfDayOperation)
class EndOfDayOperationAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'operation_date', 'program_date', 'status', 'automation_type', 'started_at', 'completed_at', 'created_by']
    list_filter = ['status', 'automation_type', 'operation_date', 'hotel', 'is_async']
    search_fields = ['hotel__name', 'operation_date', 'program_date']
    readonly_fields = ['started_at', 'completed_at', 'created_at', 'updated_at']
    date_hierarchy = 'operation_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('hotel', 'operation_date', 'program_date', 'status')
        }),
        ('İşlem Türü', {
            'fields': ('automation_type', 'is_async')
        }),
        ('Ayarlar ve Sonuçlar', {
            'fields': ('settings', 'results', 'metadata')
        }),
        ('Zaman Bilgileri', {
            'fields': ('started_at', 'completed_at')
        }),
        ('Hata ve Rollback', {
            'fields': ('error_message', 'rollback_data')
        }),
        ('Kullanıcı Takibi', {
            'fields': ('created_by',)
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def enable_rollback(self, obj):
        """Rollback yapılabilir mi?"""
        return obj.can_rollback()
    enable_rollback.boolean = True
    enable_rollback.short_description = 'Rollback Yapılabilir mi?'


@admin.register(EndOfDaySettings)
class EndOfDaySettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'is_active', 'automation_type', 'auto_run_time', 'enable_rollback']
    list_filter = ['is_active', 'automation_type', 'stop_if_room_price_zero', 'cancel_no_show_reservations']
    search_fields = ['hotel__name']
    
    fieldsets = (
        ('Otel', {
            'fields': ('hotel',)
        }),
        ('Pre-Audit Kontrol Ayarları', {
            'fields': (
                'stop_if_room_price_zero',
                'stop_if_advance_folio_balance_not_zero',
                'check_checkout_folios'
            )
        }),
        ('Otomatik İşlem Ayarları', {
            'fields': (
                'cancel_no_show_reservations',
                'no_show_action',
                'extend_non_checkout_reservations',
                'extend_days',
                'cancel_room_change_plans'
            )
        }),
        ('Otomasyon Ayarları', {
            'fields': ('automation_type', 'auto_run_time', 'is_active')
        }),
        ('Genel Ayarlar', {
            'fields': ('enable_rollback',)
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
    )


@admin.register(EndOfDayOperationStep)
class EndOfDayOperationStepAdmin(admin.ModelAdmin):
    list_display = ['operation', 'step_order', 'step_name', 'status', 'started_at', 'completed_at']
    list_filter = ['status', 'operation__hotel', 'operation__operation_date']
    search_fields = ['step_name', 'operation__hotel__name']
    readonly_fields = ['started_at', 'completed_at', 'created_at', 'updated_at']
    ordering = ['operation', 'step_order']


@admin.register(EndOfDayReport)
class EndOfDayReportAdmin(admin.ModelAdmin):
    list_display = ['operation', 'report_type', 'export_format', 'generated_at', 'report_file']
    list_filter = ['report_type', 'export_format', 'generated_at', 'operation__hotel']
    search_fields = ['operation__hotel__name', 'report_type']
    readonly_fields = ['generated_at', 'created_at', 'updated_at']
    date_hierarchy = 'generated_at'


@admin.register(EndOfDayJournalEntry)
class EndOfDayJournalEntryAdmin(admin.ModelAdmin):
    list_display = ['operation', 'journal_entry', 'entry_type', 'department', 'market_segment', 'amount', 'currency']
    list_filter = ['entry_type', 'department', 'market_segment', 'operation__hotel']
    search_fields = ['operation__hotel__name', 'journal_entry__entry_number']
    readonly_fields = ['created_at', 'updated_at']

