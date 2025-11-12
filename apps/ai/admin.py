"""
AI Yönetim Admin Panel
Super Admin tarafından AI sağlayıcıları ve modelleri yönetilir
"""
from django.contrib import admin
from django import forms
from .models import AIProvider, AIModel, PackageAI
from .forms import AIProviderForm, AIModelForm, PackageAIForm


@admin.register(AIProvider)
class AIProviderAdmin(admin.ModelAdmin):
    form = AIProviderForm
    list_display = ['name', 'code', 'provider_type', 'icon', 'is_active', 'has_api_key', 'sort_order']
    list_filter = ['provider_type', 'is_active', 'is_deleted', 'created_at']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['created_at', 'updated_at', 'has_api_key']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'provider_type', 'description', 'icon', 'sort_order'),
            'description': 'AI sağlayıcısının temel bilgilerini girin.'
        }),
        ('API Ayarları', {
            'fields': ('api_base_url', 'api_key_encrypted', 'api_key_label'),
            'description': 'API key şifreli olarak saklanır. Yeni key eklemek için API Key güncelle butonunu kullanın.'
        }),
        ('Durum', {
            'fields': ('is_active', 'is_deleted')
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',),
            'description': 'Ek ayarlar (timeout, retry vb.) JSON formatında.'
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def has_api_key(self, obj):
        """API key var mı?"""
        return obj.has_api_key
    has_api_key.boolean = True
    has_api_key.short_description = 'API Key Var'


@admin.register(AIModel)
class AIModelAdmin(admin.ModelAdmin):
    form = AIModelForm
    list_display = ['name', 'provider', 'model_id', 'credit_cost', 'is_active', 'is_default', 'sort_order']
    list_filter = ['provider', 'is_active', 'is_default', 'supports_streaming', 'supports_function_calling']
    search_fields = ['name', 'code', 'model_id', 'description']
    readonly_fields = ['created_at', 'updated_at']
    prepopulated_fields = {'code': ('name',)}
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('provider', 'name', 'code', 'model_id', 'description', 'sort_order'),
            'description': 'AI modelinin temel bilgilerini girin. Model ID, API\'de kullanılan gerçek model adıdır (örn: gpt-4, claude-3-opus).'
        }),
        ('Kredi Ayarları', {
            'fields': ('credit_cost',),
            'description': 'Bu modeli kullanmak için düşülecek kredi miktarı. Her AI işlemi için bu kadar kredi düşer.'
        }),
        ('Model Özellikleri', {
            'fields': ('max_tokens', 'supports_streaming', 'supports_function_calling'),
            'description': 'Modelin teknik özellikleri ve yetenekleri.'
        }),
        ('Durum', {
            'fields': ('is_active', 'is_default'),
            'description': 'Aktif: Model kullanılabilir. Varsayılan: Bu sağlayıcı için varsayılan model (sadece bir tane olabilir).'
        }),
        ('Ek Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',),
            'description': 'Model özel ayarları JSON formatında.'
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )


@admin.register(PackageAI)
class PackageAIAdmin(admin.ModelAdmin):
    form = PackageAIForm
    list_display = ['package', 'ai_provider', 'ai_model', 'monthly_credit_limit', 'credit_renewal_type', 'is_enabled']
    list_filter = ['package', 'ai_provider', 'is_enabled', 'credit_renewal_type']
    search_fields = ['package__name', 'ai_provider__name', 'ai_model__name']
    readonly_fields = ['created_at', 'updated_at']
    
    fieldsets = (
        ('Paket ve AI Seçimi', {
            'fields': ('package', 'ai_provider', 'ai_model'),
            'description': 'Hangi pakete hangi AI sağlayıcı ve modeli tanımlanacağını seçin.'
        }),
        ('Kredi Ayarları', {
            'fields': ('monthly_credit_limit', 'credit_renewal_type'),
            'description': 'Aylık kredi limiti: Paket ile birlikte verilen aylık kredi miktarı. -1 = sınırsız. Yenileme tipi: Kredilerin ne zaman yenileneceği.'
        }),
        ('Durum', {
            'fields': ('is_enabled',),
            'description': 'Bu AI yapılandırması aktif mi?'
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )

