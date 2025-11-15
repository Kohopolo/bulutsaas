from django.contrib import admin
from .models import RefundPolicy, RefundRequest, RefundTransaction


@admin.register(RefundPolicy)
class RefundPolicyAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'module', 'policy_type', 'refund_percentage', 'is_active', 'is_default', 'priority']
    list_filter = ['policy_type', 'module', 'is_active', 'is_default', 'is_deleted']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['created_at', 'updated_at']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'module', 'description', 'sort_order')
        }),
        ('İade Kuralları', {
            'fields': ('policy_type', 'refund_percentage', 'refund_amount')
        }),
        ('Zaman Kuralları', {
            'fields': ('days_before_start', 'days_after_booking', 'max_refund_days')
        }),
        ('İade Yöntemi', {
            'fields': ('refund_method',)
        }),
        ('İşlem Ücreti', {
            'fields': ('processing_fee_percentage', 'processing_fee_amount')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_default', 'priority')
        }),
        ('Özel Kurallar', {
            'fields': ('custom_rules', 'settings'),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(RefundRequest)
class RefundRequestAdmin(admin.ModelAdmin):
    list_display = ['request_number', 'request_date', 'customer_name', 'source_module', 
                   'original_amount', 'net_refund', 'currency', 'status', 'created_by']
    list_filter = ['status', 'source_module', 'refund_method', 'request_date', 'created_at']
    search_fields = ['request_number', 'customer_name', 'customer_email', 'source_reference']
    readonly_fields = ['request_number', 'created_at', 'updated_at', 'created_by', 
                      'approved_by', 'approved_at', 'processed_by', 'processed_at', 'status_changed_at']
    date_hierarchy = 'request_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('request_number', 'request_date', 'status', 'status_changed_at')
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference')
        }),
        ('Müşteri Bilgileri', {
            'fields': ('customer_name', 'customer_email', 'customer_phone')
        }),
        ('Orijinal Ödeme', {
            'fields': ('original_amount', 'original_payment_method', 'original_payment_date', 'currency')
        }),
        ('İade Politikası', {
            'fields': ('refund_policy',)
        }),
        ('İade Hesaplaması', {
            'fields': ('refund_amount', 'processing_fee', 'net_refund', 'refund_method')
        }),
        ('Talep Nedeni', {
            'fields': ('reason', 'customer_notes')
        }),
        ('Onay Bilgileri', {
            'fields': ('approved_by', 'approved_at', 'rejection_reason')
        }),
        ('İşlem Bilgileri', {
            'fields': ('processed_by', 'processed_at')
        }),
        ('Oluşturan', {
            'fields': ('created_by',),
            'classes': ('collapse',)
        }),
        ('Ek Bilgiler', {
            'fields': ('attachments', 'metadata', 'notes'),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def save_model(self, request, obj, form, change):
        if not change:
            obj.created_by = request.user
        super().save_model(request, obj, form, change)
    
    actions = ['approve_requests', 'reject_requests', 'process_requests']
    
    def approve_requests(self, request, queryset):
        """Seçili talepleri onayla"""
        for refund_request in queryset.filter(status='pending'):
            refund_request.approve(user=request.user)
        self.message_user(request, f"{queryset.count()} iade talebi onaylandı.")
    approve_requests.short_description = 'Seçili talepleri onayla'
    
    def reject_requests(self, request, queryset):
        """Seçili talepleri reddet"""
        for refund_request in queryset.filter(status='pending'):
            refund_request.reject(user=request.user, reason='Toplu red')
        self.message_user(request, f"{queryset.count()} iade talebi reddedildi.")
    reject_requests.short_description = 'Seçili talepleri reddet'
    
    def process_requests(self, request, queryset):
        """Seçili talepleri işleme al"""
        for refund_request in queryset.filter(status='approved'):
            refund_request.process(user=request.user)
        self.message_user(request, f"{queryset.count()} iade talebi işleme alındı.")
    process_requests.short_description = 'Seçili talepleri işleme al'


@admin.register(RefundTransaction)
class RefundTransactionAdmin(admin.ModelAdmin):
    list_display = ['transaction_number', 'refund_request', 'transaction_date', 'amount', 
                   'currency', 'refund_method', 'status', 'processed_by']
    list_filter = ['status', 'refund_method', 'currency', 'transaction_date', 'created_at']
    search_fields = ['transaction_number', 'payment_reference', 'refund_request__request_number']
    readonly_fields = ['transaction_number', 'created_at', 'updated_at', 'processed_by', 'status_changed_at']
    date_hierarchy = 'transaction_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('transaction_number', 'refund_request', 'transaction_date', 'amount', 'currency', 'status', 'status_changed_at')
        }),
        ('İade Yöntemi', {
            'fields': ('refund_method',)
        }),
        ('Ödeme Bilgileri', {
            'fields': ('payment_reference', 'payment_provider')
        }),
        ('Entegrasyon', {
            'fields': ('cash_transaction_id', 'accounting_entry_id')
        }),
        ('Hata Bilgisi', {
            'fields': ('error_message',)
        }),
        ('İşleyen', {
            'fields': ('processed_by',),
            'classes': ('collapse',)
        }),
        ('Ek Bilgiler', {
            'fields': ('metadata', 'notes'),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    actions = ['mark_completed', 'mark_failed']
    
    def mark_completed(self, request, queryset):
        """Seçili işlemleri tamamlandı olarak işaretle"""
        for transaction in queryset:
            transaction.complete(user=request.user)
        self.message_user(request, f"{queryset.count()} iade işlemi tamamlandı.")
    mark_completed.short_description = 'Seçili işlemleri tamamlandı olarak işaretle'
    
    def mark_failed(self, request, queryset):
        """Seçili işlemleri başarısız olarak işaretle"""
        for transaction in queryset:
            transaction.fail('Toplu işaretleme')
        self.message_user(request, f"{queryset.count()} iade işlemi başarısız olarak işaretlendi.")
    mark_failed.short_description = 'Seçili işlemleri başarısız olarak işaretle'

