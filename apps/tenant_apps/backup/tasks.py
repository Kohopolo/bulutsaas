"""
Yedekleme Modülü Celery Tasks
Günlük otomatik yedekleme için
"""
from celery import shared_task
from django.core.management import call_command
from django_tenants.utils import get_tenant_model, get_public_schema_name
import logging

logger = logging.getLogger(__name__)


@shared_task(name='backup.daily_backup')
def daily_backup():
    """
    Günlük otomatik veritabanı yedekleme
    Celery Beat ile günlük çalıştırılır
    """
    try:
        logger.info('Günlük otomatik yedekleme başlatılıyor...')
        
        # Public schema'yı yedekle
        try:
            logger.info('Public schema yedekleniyor...')
            call_command(
                'backup_database',
                schema=get_public_schema_name(),
                type='automatic'
            )
            logger.info('Public schema yedekleme tamamlandı.')
        except Exception as e:
            logger.error(f'Public schema yedeklenirken hata: {str(e)}', exc_info=True)
        
        # Tüm tenant schema'larını yedekle
        Tenant = get_tenant_model()
        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        
        logger.info(f'{tenants.count()} tenant schema yedekleniyor...')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                logger.info(f'Tenant schema yedekleniyor: {tenant.schema_name}')
                call_command(
                    'backup_database',
                    schema=tenant.schema_name,
                    type='automatic'
                )
                success_count += 1
                logger.info(f'Tenant {tenant.schema_name} yedekleme tamamlandı.')
            except Exception as e:
                error_count += 1
                logger.error(
                    f'Tenant {tenant.schema_name} yedeklenirken hata: {str(e)}',
                    exc_info=True
                )
        
        result_message = (
            f'Günlük otomatik yedekleme tamamlandı. '
            f'Başarılı: {success_count}, Hatalı: {error_count}'
        )
        logger.info(result_message)
        
        return result_message
        
    except Exception as e:
        error_message = f'Günlük otomatik yedekleme hatası: {str(e)}'
        logger.error(error_message, exc_info=True)
        raise


@shared_task(name='backup.cleanup_old_backups')
def cleanup_old_backups(days=30):
    """
    Eski yedekleri temizle
    Varsayılan olarak 30 günden eski yedekleri siler
    
    Args:
        days: Kaç günden eski yedekler silinecek (varsayılan: 30)
    """
    try:
        from .utils import delete_old_backups
        
        logger.info(f'{days} günden eski yedekler temizleniyor...')
        deleted_count = delete_old_backups(days=days)
        logger.info(f'{deleted_count} adet eski yedek silindi.')
        
        return f'{deleted_count} adet eski yedek silindi.'
        
    except Exception as e:
        error_message = f'Eski yedekler temizlenirken hata: {str(e)}'
        logger.error(error_message, exc_info=True)
        raise

