"""
Ödeme Yönetimi Modülünü Public Schema'da Oluşturma Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Ödeme Yönetimi modülünü public schema\'da oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='payment_management',
            defaults={
                'name': 'Ödeme Yönetimi',
                'description': 'Ödeme gateway yönetimi ve yapılandırması - İyzico, PayTR, NestPay ve tüm Türk bankaları',
                'icon': 'fas fa-credit-card',
                'category': 'payment',
                'app_name': 'apps.tenant_apps.payment_management',
                'url_prefix': 'payment-management',
                'is_active': True,
                'is_core': False,
                'sort_order': 20,
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'transaction_view': 'İşlem görüntüleme',
                }
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Ödeme Yönetimi modülü oluşturuldu: {module.name}')
            )
        else:
            self.stdout.write(
                self.style.WARNING(f'[SKIP] Ödeme Yönetimi modülü zaten mevcut: {module.name}')
            )

