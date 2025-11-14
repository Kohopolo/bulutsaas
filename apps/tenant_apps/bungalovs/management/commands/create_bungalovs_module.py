"""
Management Command: Bungalovs Modülü Oluştur
Public schema'da bungalovs modülünü oluşturur
"""
from django.core.management.base import BaseCommand
from django.db import connection
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Bungalovs modülünü public schema\'da oluşturur'

    def handle(self, *args, **options):
        # Public schema'da çalıştığımızdan emin ol
        if connection.schema_name != 'public':
            self.stdout.write(self.style.WARNING('Bu komut sadece public schema\'da çalıştırılmalıdır.'))
            return

        # Modül bilgileri
        module_code = 'bungalovs'
        module_name = 'Bungalov Yönetimi'
        module_description = 'Profesyonel bungalov rezervasyon ve yönetim sistemi'
        module_icon = 'fas fa-home'
        module_category = 'reservation'
        module_app_name = 'apps.tenant_apps.bungalovs'
        module_url_prefix = 'bungalovs'

        # Mevcut modülü kontrol et
        module, created = Module.objects.get_or_create(
            code=module_code,
            defaults={
                'name': module_name,
                'description': module_description,
                'icon': module_icon,
                'category': module_category,
                'app_name': module_app_name,
                'url_prefix': module_url_prefix,
                'is_active': True,
                'is_core': False,
                'sort_order': 5,
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
            self.stdout.write(self.style.SUCCESS(f'[OK] {module_name} modulu olusturuldu: {module_code}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] {module_name} modulu zaten mevcut: {module_code}'))

        self.stdout.write(self.style.SUCCESS(f'\n[OK] {module_name} modulu hazir!'))

