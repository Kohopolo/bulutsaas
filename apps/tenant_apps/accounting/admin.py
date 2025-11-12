from django.contrib import admin
from .models import Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, Payment


@admin.register(Account)
class AccountAdmin(admin.ModelAdmin):
    list_display = ['code', 'name', 'account_type', 'currency', 'get_balance', 'is_active']
    list_filter = ['account_type', 'currency', 'is_active', 'is_system', 'is_deleted']
    search_fields = ['code', 'name', 'description']
    readonly_fields = ['created_at', 'updated_at', 'debit_balance', 'credit_balance', 'get_balance']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('code', 'name', 'account_type', 'currency', 'description', 'sort_order')
        }),
        ('Hiyerarşi', {
            'fields': ('parent', 'level')
        }),
        ('Bakiye Bilgileri', {
            'fields': ('opening_balance', 'debit_balance', 'credit_balance', 'get_balance')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_system', 'is_deleted')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    actions = ['calculate_balances']
    
    def calculate_balances(self, request, queryset):
        """Seçili hesapların bakiyelerini hesapla"""
        for account in queryset:
            account.calculate_balance()
        self.message_user(request, f"{queryset.count()} hesabın bakiyesi güncellendi.")
    calculate_balances.short_description = 'Seçili hesapların bakiyelerini hesapla'


class JournalEntryLineInline(admin.TabularInline):
    model = JournalEntryLine
    extra = 2
    fields = ['account', 'debit', 'credit', 'description']


@admin.register(JournalEntry)
class JournalEntryAdmin(admin.ModelAdmin):
    list_display = ['entry_number', 'entry_date', 'description', 'status', 'get_total_debit', 'get_total_credit', 'created_by']
    list_filter = ['status', 'entry_date', 'source_module', 'created_at']
    search_fields = ['entry_number', 'description', 'source_reference']
    readonly_fields = ['entry_number', 'created_at', 'updated_at', 'created_by', 'posted_by', 'posted_at', 
                      'get_total_debit', 'get_total_credit', 'is_balanced']
    date_hierarchy = 'entry_date'
    inlines = [JournalEntryLineInline]
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('entry_number', 'entry_date', 'description', 'status', 'is_balanced')
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference')
        }),
        ('Durum ve Onay', {
            'fields': ('posted_at', 'posted_by', 'created_by')
        }),
        ('Ek Bilgiler', {
            'fields': ('notes', 'attachments', 'metadata'),
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
    
    actions = ['post_entries', 'cancel_entries']
    
    def post_entries(self, request, queryset):
        """Seçili yevmiye kayıtlarını kaydet"""
        for entry in queryset:
            try:
                entry.post(user=request.user)
            except ValueError as e:
                self.message_user(request, f"Hata ({entry.entry_number}): {str(e)}", level='error')
        self.message_user(request, f"{queryset.count()} yevmiye kaydı kaydedildi.")
    post_entries.short_description = 'Seçili yevmiye kayıtlarını kaydet'
    
    def cancel_entries(self, request, queryset):
        """Seçili yevmiye kayıtlarını iptal et"""
        for entry in queryset:
            entry.cancel()
        self.message_user(request, f"{queryset.count()} yevmiye kaydı iptal edildi.")
    cancel_entries.short_description = 'Seçili yevmiye kayıtlarını iptal et'


class InvoiceLineInline(admin.TabularInline):
    model = InvoiceLine
    extra = 3
    fields = ['item_name', 'item_code', 'quantity', 'unit_price', 'discount_rate', 'line_total', 'description']


@admin.register(Invoice)
class InvoiceAdmin(admin.ModelAdmin):
    list_display = ['invoice_number', 'invoice_type', 'invoice_date', 'customer_name', 'total_amount', 
                   'currency', 'status', 'paid_amount', 'get_remaining_amount']
    list_filter = ['invoice_type', 'status', 'invoice_date', 'currency', 'created_at']
    search_fields = ['invoice_number', 'customer_name', 'customer_tax_id', 'source_reference']
    readonly_fields = ['invoice_number', 'created_at', 'updated_at', 'created_by', 'tax_amount', 
                      'total_amount', 'get_remaining_amount']
    date_hierarchy = 'invoice_date'
    inlines = [InvoiceLineInline]
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('invoice_number', 'invoice_type', 'invoice_date', 'due_date', 'status')
        }),
        ('Müşteri/Tedarikçi Bilgileri', {
            'fields': ('customer_name', 'customer_tax_id', 'customer_address', 'customer_email', 'customer_phone')
        }),
        ('Tutar Bilgileri', {
            'fields': ('subtotal', 'discount_amount', 'tax_rate', 'tax_amount', 'total_amount', 'currency', 
                      'paid_amount', 'get_remaining_amount')
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference')
        }),
        ('Açıklama', {
            'fields': ('description', 'notes')
        }),
        ('Oluşturan', {
            'fields': ('created_by',),
            'classes': ('collapse',)
        }),
        ('Ek Bilgiler', {
            'fields': ('attachments', 'metadata'),
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


@admin.register(Payment)
class PaymentAdmin(admin.ModelAdmin):
    list_display = ['payment_number', 'payment_date', 'amount', 'currency', 'payment_method', 
                   'invoice', 'status', 'created_by']
    list_filter = ['status', 'payment_method', 'currency', 'payment_date', 'created_at']
    search_fields = ['payment_number', 'description', 'source_reference']
    readonly_fields = ['payment_number', 'created_at', 'updated_at', 'created_by']
    date_hierarchy = 'payment_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('payment_number', 'payment_date', 'amount', 'currency', 'payment_method', 'status')
        }),
        ('İlişkiler', {
            'fields': ('invoice', 'cash_account_id')
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference')
        }),
        ('Açıklama', {
            'fields': ('description', 'notes')
        }),
        ('Oluşturan', {
            'fields': ('created_by',),
            'classes': ('collapse',)
        }),
        ('Ek Bilgiler', {
            'fields': ('attachments', 'metadata'),
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
    
    actions = ['mark_completed']
    
    def mark_completed(self, request, queryset):
        """Seçili ödemeleri tamamlandı olarak işaretle"""
        for payment in queryset:
            payment.complete()
        self.message_user(request, f"{queryset.count()} ödeme tamamlandı olarak işaretlendi.")
    mark_completed.short_description = 'Seçili ödemeleri tamamlandı olarak işaretle'

