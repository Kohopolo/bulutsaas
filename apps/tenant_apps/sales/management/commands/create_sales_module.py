"""
Satış Yönetimi Modülü Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Satış Yönetimi modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='sales',
            defaults={
                'name': 'Satış Yönetimi',
                'description': 'Profesyonel otel satış yönetim sistemi',
                'icon': 'fas fa-chart-line',
                'category': 'other',
                'url_prefix': 'sales',
                'app_name': 'apps.tenant_apps.sales',
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
            self.stdout.write(self.style.SUCCESS(f'[OK] Satış Yönetimi modülü oluşturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Satış Yönetimi modülü zaten mevcut: {module.name}'))

