"""
Yedekleme Modülünü Public Schema'da Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Yedekleme modülünü public schema\'da oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='backup',
            defaults={
                'name': 'Yedekleme Yönetimi',
                'description': 'Veritabanı yedekleme ve geri yükleme yönetim sistemi',
                'icon': 'fas fa-database',
                'category': 'system',
                'app_name': 'apps.tenant_apps.backup',
                'url_prefix': 'backup',
                'is_active': True,
                'is_core': False,
                'sort_order': 99,
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Yedekleme Oluşturma',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'download': 'İndirme',
                }
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Yedekleme modülü oluşturuldu: {module.name}')
            )
        else:
            # Mevcut modülü güncelle
            module.name = 'Yedekleme Yönetimi'
            module.description = 'Veritabanı yedekleme ve geri yükleme yönetim sistemi'
            module.icon = 'fas fa-database'
            module.category = 'system'
            module.app_name = 'apps.tenant_apps.backup'
            module.url_prefix = 'backup'
            module.is_active = True
            module.sort_order = 99
            module.available_permissions = {
                'view': 'Görüntüleme',
                'add': 'Yedekleme Oluşturma',
                'edit': 'Düzenleme',
                'delete': 'Silme',
                'download': 'İndirme',
            }
            module.save()
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Yedekleme modülü güncellendi: {module.name}')
            )

