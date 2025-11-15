"""
Celery tasks - Core app
"""
from celery import shared_task
from django.core.cache import cache


@shared_task
def cleanup_cache():
    """
    Cache temizliği
    """
    try:
        cache.clear()
        return 'Cache temizlendi'
    except Exception as e:
        return f'Cache temizlenirken hata: {str(e)}'



