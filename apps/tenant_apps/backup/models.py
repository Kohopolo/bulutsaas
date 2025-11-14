"""
Yedekleme Modülü Models
Veritabanı yedekleme kayıtları
"""
from django.db import models
from django.utils import timezone
from apps.tenant_apps.core.models import TimeStampedModel, SoftDeleteModel


class DatabaseBackup(TimeStampedModel, SoftDeleteModel):
    """
    Veritabanı Yedekleme Kayıtları
    """
    BACKUP_TYPES = [
        ('manual', 'Manuel'),
        ('automatic', 'Otomatik'),
    ]
    
    BACKUP_STATUS = [
        ('pending', 'Bekliyor'),
        ('in_progress', 'Devam Ediyor'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
    ]
    
    backup_type = models.CharField(
        'Yedekleme Tipi',
        max_length=20,
        choices=BACKUP_TYPES,
        default='manual'
    )
    
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=BACKUP_STATUS,
        default='pending'
    )
    
    file_name = models.CharField(
        'Dosya Adı',
        max_length=255,
        unique=True,
        db_index=True
    )
    
    file_path = models.CharField(
        'Dosya Yolu',
        max_length=500
    )
    
    file_size = models.BigIntegerField(
        'Dosya Boyutu (bytes)',
        default=0
    )
    
    schema_name = models.CharField(
        'Schema Adı',
        max_length=100,
        blank=True,
        null=True
    )
    
    database_name = models.CharField(
        'Veritabanı Adı',
        max_length=100
    )
    
    started_by = models.ForeignKey(
        'tenant_core.TenantUser',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='backups_started',
        verbose_name='Başlatan Kullanıcı'
    )
    
    started_at = models.DateTimeField(
        'Başlangıç Zamanı',
        default=timezone.now
    )
    
    completed_at = models.DateTimeField(
        'Tamamlanma Zamanı',
        null=True,
        blank=True
    )
    
    error_message = models.TextField(
        'Hata Mesajı',
        blank=True
    )
    
    notes = models.TextField(
        'Notlar',
        blank=True
    )
    
    class Meta:
        verbose_name = 'Veritabanı Yedeği'
        verbose_name_plural = 'Veritabanı Yedekleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['file_name']),
            models.Index(fields=['status']),
            models.Index(fields=['backup_type']),
            models.Index(fields=['created_at']),
        ]
    
    def __str__(self):
        return f"{self.file_name} - {self.get_status_display()}"
    
    def get_file_size_display(self):
        """Dosya boyutunu okunabilir formatta döndür"""
        size = self.file_size
        for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
            if size < 1024.0:
                return f"{size:.2f} {unit}"
            size /= 1024.0
        return f"{size:.2f} PB"
    
    def get_duration(self):
        """Yedekleme süresini döndür"""
        if self.completed_at and self.started_at:
            duration = self.completed_at - self.started_at
            return duration.total_seconds()
        return None
    
    def get_duration_display(self):
        """Yedekleme süresini okunabilir formatta döndür"""
        duration = self.get_duration()
        if duration is None:
            return '-'
        
        if duration < 60:
            return f"{duration:.1f} saniye"
        elif duration < 3600:
            return f"{duration / 60:.1f} dakika"
        else:
            return f"{duration / 3600:.1f} saat"

