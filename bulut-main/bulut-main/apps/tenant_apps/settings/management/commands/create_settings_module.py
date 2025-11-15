"""
Settings Modülü Oluşturma Komutu
SaaS superadmin panelinde modülü tanımlar
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Settings (Ayarlar) modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='settings',
            defaults={
                'name': 'Ayarlar',
                'description': 'Sistem ayarları ve SMS entegrasyonları yönetimi. SMS Gateway konfigürasyonları, SMS şablonları ve gönderim logları.',
                'icon': 'fas fa-cog',
                'category': 'settings',
                'app_name': 'apps.tenant_apps.settings',
                'url_prefix': 'settings',
                'available_permissions': {
                    'view': 'Ayarlar Görüntüleme',
                    'add': 'Ayarlar Ekleme',
                    'change': 'Ayarlar Düzenleme',
                    'delete': 'Ayarlar Silme',
                    'sms_gateway_view': 'SMS Gateway Görüntüleme',
                    'sms_gateway_add': 'SMS Gateway Ekleme',
                    'sms_gateway_change': 'SMS Gateway Düzenleme',
                    'sms_gateway_delete': 'SMS Gateway Silme',
                    'sms_template_view': 'SMS Şablon Görüntüleme',
                    'sms_template_add': 'SMS Şablon Ekleme',
                    'sms_template_change': 'SMS Şablon Düzenleme',
                    'sms_template_delete': 'SMS Şablon Silme',
                    'sms_send': 'SMS Gönderme',
                    'sms_log_view': 'SMS Log Görüntüleme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 100,
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Settings modülü oluşturuldu: {module.name}')
            )
        else:
            # Mevcut modülü güncelle
            module.name = 'Ayarlar'
            module.description = 'Sistem ayarları ve SMS entegrasyonları yönetimi. SMS Gateway konfigürasyonları, SMS şablonları ve gönderim logları.'
            module.icon = 'fas fa-cog'
            module.category = 'settings'
            module.app_name = 'apps.tenant_apps.settings'
            module.url_prefix = 'settings'
            module.is_active = True
            module.is_core = False
            module.sort_order = 100
            module.save()
            self.stdout.write(
                self.style.WARNING(f'[GUNCELLENDI] Settings modülü güncellendi: {module.name}')
            )

