"""
Personel Yönetimi Modülü Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Personel Yönetimi modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='staff',
            defaults={
                'name': 'Personel Yönetimi',
                'description': 'Profesyonel otel personel yönetim sistemi',
                'icon': 'fas fa-users',
                'category': 'other',
                'url_prefix': 'staff',
                'app_name': 'apps.tenant_apps.staff',
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
            self.stdout.write(self.style.SUCCESS(f'[OK] Personel Yönetimi modülü oluşturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Personel Yönetimi modülü zaten mevcut: {module.name}'))

