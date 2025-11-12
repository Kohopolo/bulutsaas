"""
Celery configuration for SaaS 2026
Arka plan işleri ve zamanlanmış görevler için
"""
import os
from celery import Celery
from celery.schedules import crontab

# Django settings modülünü belirt
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')

# Celery app oluştur
app = Celery('saas2026')

# Django settings'den config oku (CELERY_ ile başlayan)
app.config_from_object('django.conf:settings', namespace='CELERY')

# Django app'lerdeki tasks.py dosyalarını otomatik bul
app.autodiscover_tasks()

# Periyodik görevler (Celery Beat)
app.conf.beat_schedule = {
    # Her gece saat 02:00'de abonelik kontrolü
    'check-expired-subscriptions': {
        'task': 'apps.subscriptions.tasks.check_expired_subscriptions',
        'schedule': crontab(hour=2, minute=0),
    },
    # Her gün saat 09:00'da hatırlatma e-postaları
    'send-subscription-reminders': {
        'task': 'apps.subscriptions.tasks.send_subscription_reminders',
        'schedule': crontab(hour=9, minute=0),
    },
    # Her 6 saatte bir cache temizliği
    'cleanup-cache': {
        'task': 'apps.core.tasks.cleanup_cache',
        'schedule': crontab(minute=0, hour='*/6'),
    },
}

@app.task(bind=True, ignore_result=True)
def debug_task(self):
    """Debug görevi"""
    print(f'Request: {self.request!r}')



