from django.contrib import admin
from django.utils.html import format_html
from .models import Announcement, Advertisement


@admin.register(Announcement)
class AnnouncementAdmin(admin.ModelAdmin):
    list_display = ['title', 'is_active', 'priority', 'start_date', 'end_date', 'created_at', 'is_visible_display']
    list_filter = ['is_active', 'is_deleted', 'created_at', 'start_date', 'end_date']
    search_fields = ['title', 'content']
    readonly_fields = ['created_at', 'updated_at', 'created_by']
    date_hierarchy = 'created_at'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('title', 'content', 'is_active', 'priority')
        }),
        ('Tarih Aralığı', {
            'fields': ('start_date', 'end_date'),
            'description': 'Boş bırakılırsa süresiz olarak gösterilir.'
        }),
        ('Ek Bilgiler', {
            'fields': ('created_by', 'created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def save_model(self, request, obj, form, change):
        """Kayıt sırasında created_by'yi otomatik ayarla"""
        if not change:  # Yeni kayıt
            obj.created_by = request.user
        super().save_model(request, obj, form, change)
    
    def is_visible_display(self, obj):
        """Görünürlük durumunu göster"""
        if obj.is_visible():
            return format_html('<span style="color: green;">✓ Görünür</span>')
        return format_html('<span style="color: red;">✗ Görünmez</span>')
    is_visible_display.short_description = 'Durum'


@admin.register(Advertisement)
class AdvertisementAdmin(admin.ModelAdmin):
    list_display = ['title', 'image_preview', 'is_active', 'priority', 'start_date', 'end_date', 'created_at', 'is_visible_display']
    list_filter = ['is_active', 'is_deleted', 'created_at', 'start_date', 'end_date']
    search_fields = ['title', 'content']
    readonly_fields = ['created_at', 'updated_at', 'created_by', 'image_preview_large']
    date_hierarchy = 'created_at'
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('title', 'content', 'is_active', 'priority')
        }),
        ('Görsel ve Link', {
            'fields': ('image', 'image_preview_large', 'link_url', 'link_text')
        }),
        ('Tarih Aralığı', {
            'fields': ('start_date', 'end_date'),
            'description': 'Boş bırakılırsa süresiz olarak gösterilir.'
        }),
        ('Ek Bilgiler', {
            'fields': ('created_by', 'created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def save_model(self, request, obj, form, change):
        """Kayıt sırasında created_by'yi otomatik ayarla"""
        if not change:  # Yeni kayıt
            obj.created_by = request.user
        super().save_model(request, obj, form, change)
    
    def image_preview(self, obj):
        """Liste görünümünde küçük resim önizleme"""
        if obj.image:
            return format_html('<img src="{}" style="max-width: 50px; max-height: 50px;" />', obj.image.url)
        return '-'
    image_preview.short_description = 'Resim'
    
    def image_preview_large(self, obj):
        """Detay görünümünde büyük resim önizleme"""
        if obj.image:
            return format_html('<img src="{}" style="max-width: 300px; max-height: 300px;" />', obj.image.url)
        return 'Resim yok'
    image_preview_large.short_description = 'Resim Önizleme'
    
    def is_visible_display(self, obj):
        """Görünürlük durumunu göster"""
        if obj.is_visible():
            return format_html('<span style="color: green;">✓ Görünür</span>')
        return format_html('<span style="color: red;">✗ Görünmez</span>')
    is_visible_display.short_description = 'Durum'
