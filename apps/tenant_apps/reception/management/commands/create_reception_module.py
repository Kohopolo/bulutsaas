"""
Reception Modülünü Public Schema'da Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Reception modülünü public schema\'da oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='reception',
            defaults={
                'name': 'Resepsiyon (Ön Büro)',
                'description': 'Profesyonel otel resepsiyon yönetim sistemi - Rezervasyon odaklı',
                'icon': 'fas fa-concierge-bell',
                'category': 'reservation',
                'app_name': 'apps.tenant_apps.reception',
                'url_prefix': 'reception',
                'is_active': True,
                'is_core': False,
                'sort_order': 3,
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'checkin': 'Check-in',
                    'checkout': 'Check-out',
                }
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Reception modülü oluşturuldu: {module.name}')
            )
        else:
            self.stdout.write(
                self.style.WARNING(f'[SKIP] Reception modülü zaten mevcut: {module.name}')
            )





