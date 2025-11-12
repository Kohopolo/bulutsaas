"""
Kat Hizmetleri Modülü Oluşturma Command
SaaS panel'de modülü oluşturur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Kat Hizmetleri modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='housekeeping',
            defaults={
                'name': 'Kat Hizmetleri (Housekeeping)',
                'description': 'Profesyonel otel kat hizmetleri yönetim sistemi',
                'icon': 'fas fa-broom',
                'category': 'housekeeping',
                'url_prefix': 'housekeeping',
                'app_name': 'apps.tenant_apps.housekeeping',
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
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Kat Hizmetleri modülü oluşturuldu: {module.name}')
            )
        else:
            self.stdout.write(
                self.style.WARNING(f'[SKIP] Kat Hizmetleri modülü zaten mevcut: {module.name}')
            )

