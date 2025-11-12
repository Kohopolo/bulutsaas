"""
Bildirim Sistemi Admin
"""
from django.contrib import admin
from django.utils.html import format_html
from .models import NotificationProvider, NotificationProviderConfig, NotificationTemplate, NotificationLog


@admin.register(NotificationProvider)
class NotificationProviderAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'provider_type', 'is_active', 'is_test_mode', 'sort_order']
    list_filter = ['provider_type', 'is_active', 'is_test_mode']
    search_fields = ['name', 'code', 'description']
    ordering = ['sort_order', 'name']
    
    fieldsets = (
        ('Genel Bilgiler', {
            'fields': ('name', 'code', 'provider_type', 'description')
        }),
        ('API Ayarları', {
            'fields': ('api_url', 'test_api_url')
        }),
        ('Özellikler', {
            'fields': ('supports_bulk', 'supports_template', 'supports_unicode')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_test_mode', 'sort_order')
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )


@admin.register(NotificationProviderConfig)
class NotificationProviderConfigAdmin(admin.ModelAdmin):
    list_display = ['provider', 'sender_id', 'is_active', 'is_test_mode', 'total_sent', 'total_failed', 'last_used_at']
    list_filter = ['provider', 'is_active', 'is_test_mode', 'provider__provider_type']
    search_fields = ['provider__name', 'sender_id', 'username']
    readonly_fields = ['total_sent', 'total_failed', 'last_used_at']
    
    fieldsets = (
        ('Sağlayıcı', {
            'fields': ('provider',)
        }),
        ('Genel API Bilgileri', {
            'fields': ('api_key', 'api_secret', 'username', 'password', 'sender_id')
        }),
        ('WhatsApp Ayarları', {
            'fields': ('whatsapp_business_id', 'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_verify_token'),
            'classes': ('collapse',)
        }),
        ('SMS Ayarları', {
            'fields': ('sms_username', 'sms_password', 'sms_header'),
            'classes': ('collapse',)
        }),
        ('Email Ayarları (SMTP)', {
            'fields': ('email_host', 'email_port', 'email_use_tls', 'email_use_ssl', 'email_from', 'email_from_name'),
            'classes': ('collapse',)
        }),
        ('Durum', {
            'fields': ('is_active', 'is_test_mode')
        }),
        ('İstatistikler', {
            'fields': ('total_sent', 'total_failed', 'last_used_at'),
            'classes': ('collapse',)
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )
    
    def get_fieldsets(self, request, obj=None):
        fieldsets = super().get_fieldsets(request, obj)
        if obj and obj.provider:
            # Sağlayıcı tipine göre ilgili alanları göster
            if obj.provider.provider_type == 'email':
                # Email alanlarını göster
                pass
            elif obj.provider.provider_type == 'sms':
                # SMS alanlarını göster
                pass
            elif obj.provider.provider_type == 'whatsapp':
                # WhatsApp alanlarını göster
                pass
        return fieldsets


@admin.register(NotificationTemplate)
class NotificationTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'template_type', 'trigger_event', 'is_active', 'priority']
    list_filter = ['template_type', 'trigger_event', 'is_active', 'is_system']
    search_fields = ['name', 'code', 'subject', 'content']
    ordering = ['-priority', 'name']
    readonly_fields = ['is_system'] if not admin.site.is_registered(NotificationTemplate) else []
    
    fieldsets = (
        ('Genel Bilgiler', {
            'fields': ('name', 'code', 'template_type', 'trigger_event')
        }),
        ('İçerik', {
            'fields': ('subject', 'content', 'content_html')
        }),
        ('Ayarlar', {
            'fields': ('is_active', 'is_system', 'priority')
        }),
        ('Değişkenler', {
            'fields': ('variables',),
            'classes': ('collapse',)
        }),
    )


@admin.register(NotificationLog)
class NotificationLogAdmin(admin.ModelAdmin):
    list_display = ['id', 'provider', 'template', 'recipient_display', 'status', 'sent_at', 'created_at']
    list_filter = ['status', 'provider', 'template', 'recipient_type', 'created_at']
    search_fields = ['recipient_email', 'recipient_phone', 'recipient_name', 'subject', 'content']
    readonly_fields = ['created_at', 'sent_at', 'delivered_at', 'read_at', 'provider_response']
    ordering = ['-created_at']
    date_hierarchy = 'created_at'
    
    fieldsets = (
        ('Genel Bilgiler', {
            'fields': ('provider', 'template', 'status')
        }),
        ('Alıcı Bilgileri', {
            'fields': ('recipient_type', 'recipient_email', 'recipient_phone', 'recipient_name')
        }),
        ('İçerik', {
            'fields': ('subject', 'content', 'content_html')
        }),
        ('Durum ve Tarihler', {
            'fields': ('sent_at', 'delivered_at', 'read_at', 'retry_count')
        }),
        ('Sağlayıcı Yanıtı', {
            'fields': ('provider_response', 'provider_message_id', 'error_message'),
            'classes': ('collapse',)
        }),
        ('İlişkili Kayıtlar', {
            'fields': ('related_model', 'related_id'),
            'classes': ('collapse',)
        }),
    )
    
    def recipient_display(self, obj):
        """Alıcı bilgisini göster"""
        if obj.recipient_email:
            return obj.recipient_email
        elif obj.recipient_phone:
            return obj.recipient_phone
        elif obj.recipient_name:
            return obj.recipient_name
        return '-'
    recipient_display.short_description = 'Alıcı'

