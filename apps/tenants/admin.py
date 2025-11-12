from django.contrib import admin
from django_tenants.admin import TenantAdminMixin
from .models import Tenant, Domain
from .forms import TenantForm, DomainForm


@admin.register(Tenant)
class TenantAdmin(TenantAdminMixin, admin.ModelAdmin):
    form = TenantForm
    list_display = ['name', 'slug', 'owner_name', 'owner_email', 'package', 'is_active', 'is_trial', 'created_at']
    list_filter = ['is_active', 'is_trial', 'package', 'created_at']
    search_fields = ['name', 'slug', 'owner_name', 'owner_email']
    readonly_fields = ['schema_name', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'slug', 'schema_name')
        }),
        ('Sahip Bilgileri', {
            'fields': ('owner_name', 'owner_email', 'phone')
        }),
        ('Adres Bilgileri', {
            'fields': ('address', 'city', 'country')
        }),
        ('Paket & Abonelik', {
            'fields': ('package', 'is_trial', 'trial_end_date')
        }),
        ('Durum', {
            'fields': ('is_active', 'settings')
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(Domain)
class DomainAdmin(admin.ModelAdmin):
    form = DomainForm
    list_display = ['domain', 'tenant', 'domain_type', 'is_primary', 'ssl_enabled']
    list_filter = ['domain_type', 'is_primary', 'ssl_enabled']
    search_fields = ['domain', 'tenant__name']
    
    fieldsets = (
        ('Domain Bilgileri', {
            'fields': ('domain', 'tenant', 'domain_type', 'is_primary')
        }),
        ('SSL Ayarları', {
            'fields': ('ssl_enabled', 'ssl_certificate'),
            'classes': ('collapse',)
        }),
    )



