"""
Teknik Servis Modülü Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Teknik Servis modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='technical_service',
            defaults={
                'name': 'Teknik Servis',
                'description': 'Profesyonel otel teknik servis yönetim sistemi',
                'icon': 'fas fa-tools',
                'category': 'other',
                'url_prefix': 'technical_service',
                'app_name': 'apps.tenant_apps.technical_service',
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
            self.stdout.write(self.style.SUCCESS(f'[OK] Teknik Servis modülü oluşturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Teknik Servis modülü zaten mevcut: {module.name}'))

