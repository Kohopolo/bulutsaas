from django.contrib import admin
from .models import CashAccount, CashTransaction, CashFlow


@admin.register(CashAccount)
class CashAccountAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'account_type', 'currency', 'current_balance', 'is_active', 'is_default']
    list_filter = ['account_type', 'currency', 'is_active', 'is_default', 'is_deleted']
    search_fields = ['name', 'code', 'bank_name', 'account_number', 'iban']
    readonly_fields = ['created_at', 'updated_at', 'current_balance']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'account_type', 'currency', 'description', 'sort_order')
        }),
        ('Banka Bilgileri', {
            'fields': ('bank_name', 'branch_name', 'account_number', 'iban'),
            'classes': ('collapse',)
        }),
        ('Bakiye Bilgileri', {
            'fields': ('initial_balance', 'current_balance')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_default', 'is_deleted')
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


@admin.register(CashTransaction)
class CashTransactionAdmin(admin.ModelAdmin):
    list_display = ['transaction_number', 'account', 'transaction_type', 'amount', 'currency', 
                   'source_module', 'source_reference', 'status', 'payment_date', 'created_by']
    list_filter = ['transaction_type', 'status', 'payment_method', 'currency', 'source_module', 
                  'is_reconciled', 'payment_date', 'created_at']
    search_fields = ['transaction_number', 'description', 'source_reference', 'account__name']
    readonly_fields = ['transaction_number', 'created_at', 'updated_at', 'created_by', 'approved_by', 
                       'approved_at', 'reconciled_at', 'reconciled_by']
    date_hierarchy = 'payment_date'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('transaction_number', 'account', 'transaction_type', 'amount', 'currency', 'payment_method')
        }),
        ('Transfer Bilgileri', {
            'fields': ('to_account',),
            'classes': ('collapse',)
        }),
        ('Kaynak Bilgileri', {
            'fields': ('source_module', 'source_id', 'source_reference')
        }),
        ('Tarih Bilgileri', {
            'fields': ('payment_date', 'due_date')
        }),
        ('Açıklama', {
            'fields': ('description', 'notes')
        }),
        ('Durum', {
            'fields': ('status', 'is_reconciled', 'reconciled_at', 'reconciled_by')
        }),
        ('Onay Bilgileri', {
            'fields': ('created_by', 'approved_by', 'approved_at'),
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
        if not change:  # Yeni kayıt
            obj.created_by = request.user
        super().save_model(request, obj, form, change)
    
    actions = ['mark_completed', 'mark_cancelled', 'mark_reconciled']
    
    def mark_completed(self, request, queryset):
        """Seçili işlemleri tamamlandı olarak işaretle"""
        for transaction in queryset:
            transaction.complete(user=request.user)
        self.message_user(request, f"{queryset.count()} işlem tamamlandı olarak işaretlendi.")
    mark_completed.short_description = 'Seçili işlemleri tamamlandı olarak işaretle'
    
    def mark_cancelled(self, request, queryset):
        """Seçili işlemleri iptal et"""
        for transaction in queryset:
            transaction.cancel()
        self.message_user(request, f"{queryset.count()} işlem iptal edildi.")
    mark_cancelled.short_description = 'Seçili işlemleri iptal et'
    
    def mark_reconciled(self, request, queryset):
        """Seçili işlemleri mutabakat yapıldı olarak işaretle"""
        from django.utils import timezone
        for transaction in queryset:
            transaction.is_reconciled = True
            transaction.reconciled_at = timezone.now()
            transaction.reconciled_by = request.user
            transaction.save()
        self.message_user(request, f"{queryset.count()} işlem mutabakat yapıldı olarak işaretlendi.")
    mark_reconciled.short_description = 'Seçili işlemleri mutabakat yapıldı olarak işaretle'


@admin.register(CashFlow)
class CashFlowAdmin(admin.ModelAdmin):
    list_display = ['account', 'period_type', 'period_start', 'period_end', 
                   'opening_balance', 'total_income', 'total_expense', 'net_flow', 'closing_balance']
    list_filter = ['period_type', 'account', 'period_start', 'period_end']
    search_fields = ['account__name']
    readonly_fields = ['opening_balance', 'closing_balance', 'total_income', 'total_expense', 
                       'net_flow', 'income_count', 'expense_count', 'calculated_at']
    date_hierarchy = 'period_start'
    
    fieldsets = (
        ('Dönem Bilgileri', {
            'fields': ('account', 'period_type', 'period_start', 'period_end')
        }),
        ('Bakiye Bilgileri', {
            'fields': ('opening_balance', 'closing_balance')
        }),
        ('Gelir-Gider Özeti', {
            'fields': ('total_income', 'income_count', 'total_expense', 'expense_count', 'net_flow')
        }),
        ('Hesaplanma', {
            'fields': ('calculated_at',),
            'classes': ('collapse',)
        }),
    )
    
    actions = ['recalculate']
    
    def recalculate(self, request, queryset):
        """Seçili nakit akışlarını yeniden hesapla"""
        for flow in queryset:
            flow.calculate()
        self.message_user(request, f"{queryset.count()} nakit akışı yeniden hesaplandı.")
    recalculate.short_description = 'Seçili nakit akışlarını yeniden hesapla'
