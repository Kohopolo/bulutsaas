"""
Feribot Bileti Modülünü Public Schema'da Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Feribot Bileti modülünü public schema\'da oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='ferry_tickets',
            defaults={
                'name': 'Feribot Bileti',
                'description': 'Profesyonel feribot bilet satış ve yönetim sistemi',
                'icon': 'fas fa-ship',
                'category': 'reservation',
                'app_name': 'apps.tenant_apps.ferry_tickets',
                'url_prefix': 'ferry-tickets',
                'is_active': True,
                'is_core': False,
                'sort_order': 4,
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'voucher': 'Voucher Oluşturma',
                    'payment': 'Ödeme İşlemleri',
                }
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Feribot Bileti modülü oluşturuldu: {module.name}')
            )
        else:
            self.stdout.write(
                self.style.WARNING(f'[SKIP] Feribot Bileti modülü zaten mevcut: {module.name}')
            )





