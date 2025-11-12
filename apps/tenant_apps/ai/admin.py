"""
Tenant AI Admin Panel
"""
from django.contrib import admin
from .models import TenantAICredit, TenantAIUsage


@admin.register(TenantAICredit)
class TenantAICreditAdmin(admin.ModelAdmin):
    list_display = ['tenant_name', 'total_credits', 'used_credits', 'remaining_credits', 'renewal_type', 'next_renewal_date']
    list_filter = ['renewal_type', 'last_renewal_date']
    search_fields = ['tenant_name', 'tenant_id']
    readonly_fields = ['created_at', 'updated_at', 'remaining_credits', 'is_credit_available']
    
    fieldsets = (
        ('Tenant Bilgileri', {
            'fields': ('tenant_id', 'tenant_name')
        }),
        ('Kredi Bilgileri', {
            'fields': ('total_credits', 'used_credits', 'remaining_credits', 'is_credit_available', 'manual_credits')
        }),
        ('Yenileme Bilgileri', {
            'fields': ('renewal_type', 'last_renewal_date', 'next_renewal_date')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(TenantAIUsage)
class TenantAIUsageAdmin(admin.ModelAdmin):
    list_display = ['tenant_name', 'ai_provider_name', 'ai_model_name', 'usage_type', 'credit_used', 'status', 'created_at']
    list_filter = ['usage_type', 'status', 'ai_provider_code', 'created_at']
    search_fields = ['tenant_name', 'ai_provider_name', 'ai_model_name', 'prompt']
    readonly_fields = ['created_at', 'updated_at']
    
    fieldsets = (
        ('Tenant ve AI Bilgileri', {
            'fields': ('tenant_id', 'tenant_name', 'ai_provider_name', 'ai_model_name', 'ai_provider_code', 'ai_model_code')
        }),
        ('Kullanım Bilgileri', {
            'fields': ('usage_type', 'prompt', 'response', 'user')
        }),
        ('Kredi ve Durum', {
            'fields': ('credit_used', 'status', 'error_message')
        }),
        ('Ek Bilgiler', {
            'fields': ('metadata',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def has_add_permission(self, request):
        """Manuel ekleme yapılamaz, sadece sistem tarafından oluşturulur"""
        return False

