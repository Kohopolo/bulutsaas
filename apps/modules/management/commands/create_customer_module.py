"""
Customer Modülü Oluşturma Komutu
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Customer (Müşteri Yönetimi) modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='customers',
            defaults={
                'name': 'Müşteri Yönetimi (CRM)',
                'description': 'Merkezi müşteri yönetimi ve CRM sistemi. Tüm modüllerde kullanılabilir.',
                'icon': 'fas fa-users',
                'category': 'other',
                'app_name': 'apps.tenant_apps.core',
                'url_prefix': '/customers/',
                'available_permissions': {
                    'view': 'Müşteri Görüntüleme',
                    'add': 'Müşteri Ekleme',
                    'edit': 'Müşteri Düzenleme',
                    'delete': 'Müşteri Silme',
                    'export': 'Müşteri Dışa Aktarma',
                    'view_notes': 'Müşteri Notlarını Görüntüleme',
                    'add_notes': 'Müşteri Notu Ekleme',
                    'view_loyalty': 'Sadakat Puanı Görüntüleme',
                    'manage_loyalty': 'Sadakat Puanı Yönetimi',
                },
                'is_active': True,
                'is_core': True,  # Temel modül - tüm paketlerde zorunlu
                'sort_order': 5,
            }
        )

        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Customer modulu olusturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[WARN] Customer modulu zaten mevcut: {module.name}'))
            # Mevcut modülü güncelle
            module.name = 'Müşteri Yönetimi (CRM)'
            module.description = 'Merkezi müşteri yönetimi ve CRM sistemi. Tüm modüllerde kullanılabilir.'
            module.icon = 'fas fa-users'
            module.app_name = 'apps.tenant_apps.core'
            module.url_prefix = '/customers/'
            module.is_core = True
            module.save()
            self.stdout.write(self.style.SUCCESS(f'[OK] Customer modulu guncellendi'))

