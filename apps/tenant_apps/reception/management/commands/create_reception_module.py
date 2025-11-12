"""
Resepsiyon Modülü Oluşturma Command
SaaS panel'de modülü oluşturur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Resepsiyon modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='reception',
            defaults={
                'name': 'Resepsiyon (Ön Büro)',
                'description': 'Profesyonel otel resepsiyon yönetim sistemi',
                'icon': 'fas fa-concierge-bell',
                'url_prefix': 'reception',
                'is_active': True,
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Resepsiyon modülü oluşturuldu: {module.name}')
            )
        else:
            self.stdout.write(
                self.style.WARNING(f'[SKIP] Resepsiyon modülü zaten mevcut: {module.name}')
            )

