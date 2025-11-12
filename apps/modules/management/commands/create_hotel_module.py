"""
Hotels modülünü Module tablosuna ekle
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Hotels modülünü Module tablosuna ekler'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='hotels',
            defaults={
                'name': 'Otel Yönetimi',
                'description': 'Çoklu otel desteği ile otel, oda ve fiyatlama yönetimi',
                'icon': 'fas fa-hotel',
                'url_prefix': '/hotels/',
                'is_active': True,
                'sort_order': 3,
            }
        )
        
        if created:
            self.stdout.write(self.style.SUCCESS('[OK] Hotels modülü başarıyla oluşturuldu.'))
        else:
            self.stdout.write(self.style.WARNING('[INFO] Hotels modülü zaten mevcut.'))

