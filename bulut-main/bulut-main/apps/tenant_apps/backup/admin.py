from django.contrib import admin
from .models import DatabaseBackup


@admin.register(DatabaseBackup)
class DatabaseBackupAdmin(admin.ModelAdmin):
    list_display = [
        'file_name',
        'backup_type',
        'status',
        'database_name',
        'schema_name',
        'file_size',
        'started_by',
        'started_at',
        'completed_at',
    ]
    list_filter = [
        'backup_type',
        'status',
        'created_at',
    ]
    search_fields = [
        'file_name',
        'database_name',
        'schema_name',
    ]
    readonly_fields = [
        'file_name',
        'file_path',
        'file_size',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ]

