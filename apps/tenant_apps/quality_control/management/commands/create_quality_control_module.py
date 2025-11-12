"""
Kalite Kontrol Modülü Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Kalite Kontrol modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='quality_control',
            defaults={
                'name': 'Kalite Kontrol',
                'description': 'Profesyonel otel kalite kontrol yönetim sistemi',
                'icon': 'fas fa-clipboard-check',
                'category': 'other',
                'url_prefix': 'quality_control',
                'app_name': 'apps.tenant_apps.quality_control',
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'manage': 'Yönetim',
                    'admin': 'Yönetici'
                },
                'is_active': True,
            }
        )
        
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Kalite Kontrol modülü oluşturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Kalite Kontrol modülü zaten mevcut: {module.name}'))

