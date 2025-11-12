from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin
from django.contrib.auth.models import User
from .models import TenantUser, UserType, Role, Permission, UserRole, RolePermission, UserPermission


class TenantUserInline(admin.StackedInline):
    """Django User admin'inde TenantUser bilgilerini göster"""
    model = TenantUser
    can_delete = False
    verbose_name_plural = 'Tenant Profil'
    fields = ('user_type', 'phone', 'department', 'position', 'is_active')


class UserAdmin(BaseUserAdmin):
    """Django User admin'ini extend et"""
    inlines = (TenantUserInline,)


# Django User admin'ini yeniden kaydet
admin.site.unregister(User)
admin.site.register(User, UserAdmin)


@admin.register(TenantUser)
class TenantUserAdmin(admin.ModelAdmin):
    list_display = ['user', 'user_type', 'department', 'position', 'is_active', 'last_login_at']
    list_filter = ['user_type', 'is_active', 'department', 'created_at']
    search_fields = ['user__username', 'user__first_name', 'user__last_name', 'user__email', 'phone']
    raw_id_fields = ['user', 'user_type']
    readonly_fields = ['created_at', 'updated_at', 'last_login_at']
    fieldsets = (
        ('Kullanıcı Bilgileri', {
            'fields': ('user', 'user_type', 'is_active')
        }),
        ('İletişim', {
            'fields': ('phone', 'department', 'position')
        }),
        ('Roller', {
            'fields': (),
            'description': 'Kullanıcının rollerini görmek için "Kullanıcı Rolleri" bölümüne bakın.'
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('last_login_at', 'created_at', 'updated_at')
        }),
    )


@admin.register(UserType)
class UserTypeAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'icon', 'default_role', 'is_active', 'sort_order']
    list_filter = ['is_active', 'created_at']
    search_fields = ['name', 'code', 'description']
    raw_id_fields = ['default_role']
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'icon', 'description')
        }),
        ('Panel Yönlendirme', {
            'fields': ('dashboard_url', 'panel_template', 'default_role')
        }),
        ('Durum', {
            'fields': ('is_active', 'sort_order')
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )


@admin.register(Role)
class RoleAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'icon', 'is_active', 'is_system', 'sort_order']
    list_filter = ['is_active', 'is_system', 'created_at']
    search_fields = ['name', 'code', 'description']
    readonly_fields = ['is_system'] if not admin.site.is_registered(Role) else []
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'icon', 'description')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_system', 'sort_order')
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )


@admin.register(Permission)
class PermissionAdmin(admin.ModelAdmin):
    list_display = ['name', 'module', 'code', 'permission_type', 'is_active', 'sort_order']
    list_filter = ['module', 'permission_type', 'is_active', 'is_system', 'created_at']
    search_fields = ['name', 'code', 'description', 'module__name']
    raw_id_fields = ['module']
    readonly_fields = ['is_system'] if not admin.site.is_registered(Permission) else []
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('module', 'name', 'code', 'description', 'permission_type')
        }),
        ('Durum', {
            'fields': ('is_active', 'is_system', 'sort_order')
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )


@admin.register(UserRole)
class UserRoleAdmin(admin.ModelAdmin):
    list_display = ['tenant_user', 'role', 'is_active', 'assigned_at', 'assigned_by']
    list_filter = ['role', 'is_active', 'assigned_at']
    search_fields = ['tenant_user__user__username', 'role__name']
    raw_id_fields = ['tenant_user', 'role', 'assigned_by']
    readonly_fields = ['assigned_at', 'created_at', 'updated_at']
    fieldsets = (
        ('İlişki', {
            'fields': ('tenant_user', 'role', 'is_active')
        }),
        ('Atama Bilgileri', {
            'fields': ('assigned_by', 'assigned_at')
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('created_at', 'updated_at')
        }),
    )


@admin.register(RolePermission)
class RolePermissionAdmin(admin.ModelAdmin):
    list_display = ['role', 'permission', 'is_active', 'assigned_at']
    list_filter = ['role', 'permission__module', 'is_active', 'assigned_at']
    search_fields = ['role__name', 'permission__name', 'permission__module__name']
    raw_id_fields = ['role', 'permission']
    readonly_fields = ['assigned_at', 'created_at', 'updated_at']
    fieldsets = (
        ('İlişki', {
            'fields': ('role', 'permission', 'is_active')
        }),
        ('Özel Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('assigned_at', 'created_at', 'updated_at')
        }),
    )


@admin.register(UserPermission)
class UserPermissionAdmin(admin.ModelAdmin):
    list_display = ['tenant_user', 'permission', 'is_active', 'assigned_at', 'assigned_by']
    list_filter = ['permission__module', 'is_active', 'assigned_at']
    search_fields = ['tenant_user__user__username', 'permission__name', 'permission__module__name']
    raw_id_fields = ['tenant_user', 'permission', 'assigned_by']
    readonly_fields = ['assigned_at', 'created_at', 'updated_at']
    fieldsets = (
        ('İlişki', {
            'fields': ('tenant_user', 'permission', 'is_active')
        }),
        ('Atama Bilgileri', {
            'fields': ('assigned_by',)
        }),
        ('Özel Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
        ('Tarihler', {
            'fields': ('assigned_at', 'created_at', 'updated_at')
        }),
    )
