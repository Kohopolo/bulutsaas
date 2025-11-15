"""
Ayarlar Modülü Admin
"""
from django.contrib import admin
from .models import SMSGateway, SMSTemplate, SMSSentLog, EmailGateway, EmailTemplate, EmailSentLog


@admin.register(SMSGateway)
class SMSGatewayAdmin(admin.ModelAdmin):
    list_display = ['name', 'gateway_type', 'is_active', 'is_default', 'total_sent', 'total_failed', 'last_sent_at']
    list_filter = ['gateway_type', 'is_active', 'is_default', 'is_test_mode']
    search_fields = ['name', 'sender_id']
    readonly_fields = ['total_sent', 'total_failed', 'last_sent_at', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'gateway_type', 'is_active', 'is_default', 'is_test_mode')
        }),
        ('API Ayarları', {
            'fields': ('api_credentials', 'api_endpoint', 'api_timeout', 'api_retry_count')
        }),
        ('Gönderim Ayarları', {
            'fields': ('sender_id', 'default_country_code')
        }),
        ('İstatistikler', {
            'fields': ('total_sent', 'total_failed', 'last_sent_at'),
            'classes': ('collapse',)
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
    )


@admin.register(SMSTemplate)
class SMSTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'category', 'module_usage', 'is_active', 'usage_count', 'last_used_at']
    list_filter = ['category', 'is_active', 'is_system_template', 'module_usage']
    search_fields = ['name', 'code', 'template_text', 'description']
    readonly_fields = ['usage_count', 'last_used_at', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'category', 'is_active', 'is_system_template')
        }),
        ('Şablon İçeriği', {
            'fields': ('template_text', 'available_variables', 'max_length')
        }),
        ('Kullanım', {
            'fields': ('module_usage', 'description')
        }),
        ('İstatistikler', {
            'fields': ('usage_count', 'last_used_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(SMSSentLog)
class SMSSentLogAdmin(admin.ModelAdmin):
    list_display = ['recipient_phone', 'gateway', 'template', 'status', 'message_length', 'created_at']
    list_filter = ['status', 'gateway', 'created_at']
    search_fields = ['recipient_phone', 'recipient_name', 'message_text']
    readonly_fields = ['created_at', 'updated_at', 'sent_at', 'delivered_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('gateway', 'template', 'status')
        }),
        ('Alıcı Bilgileri', {
            'fields': ('recipient_phone', 'recipient_name')
        }),
        ('Mesaj Bilgileri', {
            'fields': ('message_text', 'message_length')
        }),
        ('Durum Bilgileri', {
            'fields': ('error_message', 'gateway_response', 'gateway_message_id')
        }),
        ('Zaman Bilgileri', {
            'fields': ('created_at', 'sent_at', 'delivered_at')
        }),
        ('İlişkili Kayıt', {
            'fields': ('related_module', 'related_object_type', 'related_object_id', 'context_data'),
            'classes': ('collapse',)
        }),
    )


@admin.register(EmailGateway)
class EmailGatewayAdmin(admin.ModelAdmin):
    list_display = ['name', 'gateway_type', 'is_active', 'is_default', 'total_sent', 'total_failed', 'last_sent_at']
    list_filter = ['gateway_type', 'is_active', 'is_default', 'is_test_mode']
    search_fields = ['name', 'from_email', 'from_name']
    readonly_fields = ['total_sent', 'total_failed', 'last_sent_at', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'gateway_type', 'is_active', 'is_default', 'is_test_mode')
        }),
        ('SMTP Ayarları', {
            'fields': ('smtp_credentials', 'smtp_host', 'smtp_port', 'use_tls', 'use_ssl', 'smtp_timeout')
        }),
        ('Gönderim Ayarları', {
            'fields': ('from_email', 'from_name', 'reply_to')
        }),
        ('İstatistikler', {
            'fields': ('total_sent', 'total_failed', 'last_sent_at'),
            'classes': ('collapse',)
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
    )


@admin.register(EmailTemplate)
class EmailTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'category', 'module_usage', 'is_active', 'usage_count', 'last_used_at']
    list_filter = ['category', 'is_active', 'is_system_template', 'module_usage']
    search_fields = ['name', 'code', 'subject', 'template_html', 'template_text', 'description']
    readonly_fields = ['usage_count', 'last_used_at', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'category', 'is_active', 'is_system_template')
        }),
        ('Şablon İçeriği', {
            'fields': ('subject', 'template_html', 'template_text', 'available_variables')
        }),
        ('Kullanım', {
            'fields': ('module_usage', 'description')
        }),
        ('İstatistikler', {
            'fields': ('usage_count', 'last_used_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(EmailSentLog)
class EmailSentLogAdmin(admin.ModelAdmin):
    list_display = ['recipient_email', 'gateway', 'template', 'status', 'subject', 'created_at']
    list_filter = ['status', 'gateway', 'created_at']
    search_fields = ['recipient_email', 'recipient_name', 'subject', 'message_html', 'message_text']
    readonly_fields = ['created_at', 'updated_at', 'sent_at', 'delivered_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('gateway', 'template', 'status')
        }),
        ('Alıcı Bilgileri', {
            'fields': ('recipient_email', 'recipient_name')
        }),
        ('Email Bilgileri', {
            'fields': ('subject', 'message_html', 'message_text')
        }),
        ('Durum Bilgileri', {
            'fields': ('error_message', 'smtp_response', 'message_id')
        }),
        ('Zaman Bilgileri', {
            'fields': ('created_at', 'sent_at', 'delivered_at')
        }),
        ('İlişkili Kayıt', {
            'fields': ('related_module', 'related_object_type', 'related_object_id', 'context_data'),
            'classes': ('collapse',)
        }),
    )

