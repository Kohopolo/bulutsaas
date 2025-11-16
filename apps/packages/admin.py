from django.contrib import admin
from .models import Package, PackageModule
from .forms import PackageForm, PackageModuleForm, PackageAIInlineForm, PackageModuleInlineForm
from apps.ai.models import PackageAI


class PackageModuleInline(admin.TabularInline):
    form = PackageModuleInlineForm
    model = PackageModule
    extra = 1
    fields = ['module', 'permissions', 'limits', 'is_enabled', 'is_required']
    verbose_name = 'Paket Modülü'
    verbose_name_plural = 'Paket Modülleri'
    
    def get_formset(self, request, obj=None, **kwargs):
        formset = super().get_formset(request, obj, **kwargs)
        # Tüm aktif modülleri göster
        from apps.modules.models import Module
        formset.form.base_fields['module'].queryset = Module.objects.filter(is_active=True).order_by('sort_order', 'name')
        return formset


class PackageAIInline(admin.TabularInline):
    """Paket AI yapılandırması inline"""
    form = PackageAIInlineForm
    model = PackageAI
    extra = 1
    fields = ['ai_provider', 'ai_model', 'monthly_credit_limit', 'credit_renewal_type', 'is_enabled']
    verbose_name = 'AI Yapılandırması'
    verbose_name_plural = 'AI Yapılandırmaları'


@admin.register(Package)
class PackageAdmin(admin.ModelAdmin):
    form = PackageForm
    list_display = ['name', 'code', 'price_monthly', 'currency', 'get_modules_count', 'get_tenants_count', 'is_active', 'is_featured']
    list_filter = ['is_active', 'is_featured', 'currency', 'created_at']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['created_at', 'updated_at', 'get_modules_count', 'get_tenants_count']
    prepopulated_fields = {'code': ('name',)}
    inlines = [PackageModuleInline, PackageAIInline]
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'description', 'sort_order')
        }),
        ('Fiyatlandırma', {
            'fields': ('price_monthly', 'price_yearly', 'currency', 'trial_days'),
            'description': 'NOT: Limitler artık "Paket Modülleri" bölümünde modül bazlı olarak tanımlanmaktadır.'
        }),
        ('Durum', {
            'fields': ('is_active', 'is_featured', 'is_deleted')
        }),
        ('İstatistikler', {
            'fields': ('get_modules_count', 'get_tenants_count'),
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
    
    def get_queryset(self, request):
        """Silinmemiş paketleri göster"""
        qs = super().get_queryset(request)
        return qs.filter(is_deleted=False)
    
    def save_formset(self, request, form, formset, change):
        """Inline formset'i kaydet"""
        instances = formset.save(commit=False)
        for instance in instances:
            # Module seçilmemişse atla
            if not instance.module_id:
                continue
            # Package otomatik olarak atanır (inline formset)
            if not instance.package_id:
                instance.package = form.instance
            instance.save()
        
        # Silinen kayıtları işle
        for obj in formset.deleted_objects:
            obj.delete()
        
        # Yeni kayıtları kaydet
        for instance in formset.new_objects:
            # Module seçilmemişse atla
            if not instance.module_id:
                continue
            # Package otomatik olarak atanır (inline formset)
            if not instance.package_id:
                instance.package = form.instance
            instance.save()


@admin.register(PackageModule)
class PackageModuleAdmin(admin.ModelAdmin):
    form = PackageModuleForm
    list_display = ['package', 'module', 'is_enabled', 'is_required', 'created_at']
    list_filter = ['is_enabled', 'is_required', 'package', 'created_at']
    search_fields = ['package__name', 'module__name']
    
    fieldsets = (
        ('İlişki', {
            'fields': ('package', 'module'),
            'description': 'Hangi pakete hangi modül eklenecek?'
        }),
        ('Yetkiler & Limitler', {
            'fields': ('permissions', 'limits'),
            'description': 'Modül yetkileri ve limitleri JSON formatında tanımlanır.'
        }),
        ('Durum', {
            'fields': ('is_enabled', 'is_required'),
            'description': 'Aktif: Modül bu pakette kullanılabilir. Zorunlu: Modül bu pakette zorunludur.'
        }),
    )



