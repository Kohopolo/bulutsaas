"""
Database bağlantısı hazır olana kadar bekleyen management command
Docker Compose deployment için kullanılır
"""
import time
from django.core.management.base import BaseCommand
from django.db import connection
from django.db.utils import OperationalError


class Command(BaseCommand):
    """Database hazır olana kadar bekle"""
    help = 'Database bağlantısı hazır olana kadar bekler'
    
    def add_arguments(self, parser):
        parser.add_argument(
            '--max-retries',
            type=int,
            default=30,
            help='Maksimum deneme sayısı (default: 30)',
        )
        parser.add_argument(
            '--retry-delay',
            type=int,
            default=2,
            help='Her deneme arası bekleme süresi (saniye, default: 2)',
        )
    
    def handle(self, *args, **options):
        max_retries = options['max_retries']
        retry_delay = options['retry_delay']
        
        self.stdout.write('Database bağlantısı bekleniyor...')
        db_conn = None
        retries = 0
        
        while not db_conn and retries < max_retries:
            try:
                connection.ensure_connection()
                db_conn = True
                self.stdout.write(self.style.SUCCESS('✓ Database bağlantısı başarılı!'))
            except OperationalError:
                retries += 1
                self.stdout.write(
                    self.style.WARNING(
                        f'Database henüz hazır değil ({retries}/{max_retries}), '
                        f'{retry_delay} saniye bekleniyor...'
                    )
                )
                time.sleep(retry_delay)
        
        if not db_conn:
            self.stdout.write(
                self.style.ERROR(
                    f'Database bağlantısı {max_retries} denemede başarısız oldu!'
                )
            )
            raise OperationalError('Database bağlantısı kurulamadı')

