"""
Yedekleme Modülü Utility Fonksiyonları
"""
import os
from pathlib import Path
from django.conf import settings
from django.core.files.storage import default_storage


def get_backup_directory():
    """Backup klasörünü döndür"""
    base_dir = Path(settings.BASE_DIR)
    backup_dir = base_dir / 'backupdatabase'
    return backup_dir


def ensure_backup_directory():
    """Backup klasörünün var olduğundan emin ol"""
    backup_dir = get_backup_directory()
    backup_dir.mkdir(parents=True, exist_ok=True)
    return backup_dir


def get_backup_file_path(file_name):
    """Backup dosyasının tam yolunu döndür"""
    backup_dir = ensure_backup_directory()
    return backup_dir / file_name


def is_backup_file_exists(file_name):
    """Backup dosyasının var olup olmadığını kontrol et"""
    file_path = get_backup_file_path(file_name)
    return file_path.exists()


def get_backup_file_size(file_name):
    """Backup dosyasının boyutunu döndür"""
    file_path = get_backup_file_path(file_name)
    if file_path.exists():
        return file_path.stat().st_size
    return 0


def format_file_size(size_bytes):
    """Dosya boyutunu okunabilir formatta döndür"""
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if size_bytes < 1024.0:
            return f"{size_bytes:.2f} {unit}"
        size_bytes /= 1024.0
    return f"{size_bytes:.2f} PB"


def delete_old_backups(days=30):
    """Belirtilen günden eski yedekleri sil"""
    from datetime import datetime, timedelta
    from .models import DatabaseBackup
    
    cutoff_date = datetime.now() - timedelta(days=days)
    
    old_backups = DatabaseBackup.objects.filter(
        created_at__lt=cutoff_date,
        is_deleted=False
    )
    
    deleted_count = 0
    for backup in old_backups:
        try:
            file_path = Path(backup.file_path)
            if file_path.exists():
                file_path.unlink()
            backup.is_deleted = True
            backup.save()
            deleted_count += 1
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Yedek silinirken hata: {str(e)}", exc_info=True)
    
    return deleted_count

