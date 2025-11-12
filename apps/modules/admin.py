from django.contrib import admin
from .models import Module
from .forms import ModuleForm


@admin.register(Module)
class ModuleAdmin(admin.ModelAdmin):
    form = ModuleForm
    list_display = ['name', 'code', 'category', 'icon', 'get_packages_count', 'is_active', 'is_core']
    list_filter = ['is_active', 'is_core', 'category', 'created_at']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['created_at', 'updated_at', 'get_packages_count']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'description', 'icon', 'category', 'sort_order')
        }),
        ('Teknik Ayarlar', {
            'fields': ('app_name', 'url_prefix', 'available_permissions')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_core')
        }),
        ('İstatistikler', {
            'fields': ('get_packages_count',),
            'classes': ('collapse',)
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )



