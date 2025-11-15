"""
Finance, Accounting ve Refunds modüllerini oluşturur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Finance, Accounting ve Refunds modüllerini oluşturur'
    
    def handle(self, *args, **kwargs):
        self.stdout.write('Modüller oluşturuluyor...')
        
        # Finance (Kasa) Modülü
        finance_module, created = Module.objects.get_or_create(
            code='finance',
            defaults={
                'name': 'Kasa Yönetimi',
                'description': 'Kasa hesapları, işlemler ve nakit akışı yönetimi',
                'icon': 'fas fa-cash-register',
                'category': 'payment',
                'app_name': 'apps.tenant_apps.finance',
                'url_prefix': 'finance',
                'available_permissions': {
                    'view': 'Kasa görüntüleme',
                    'add': 'Kasa ekleme',
                    'edit': 'Kasa düzenleme',
                    'delete': 'Kasa silme',
                    'transaction_view': 'İşlem görüntüleme',
                    'transaction_add': 'İşlem ekleme',
                    'transaction_edit': 'İşlem düzenleme',
                    'transaction_delete': 'İşlem silme',
                    'report_view': 'Rapor görüntüleme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 10,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {finance_module.name} modülü oluşturuldu'))
        else:
            self.stdout.write(f'  {finance_module.name} modülü zaten mevcut')
        
        # Accounting (Muhasebe) Modülü
        accounting_module, created = Module.objects.get_or_create(
            code='accounting',
            defaults={
                'name': 'Muhasebe Yönetimi',
                'description': 'Hesap planı, yevmiye kayıtları, fatura ve ödeme yönetimi',
                'icon': 'fas fa-book',
                'category': 'other',
                'app_name': 'apps.tenant_apps.accounting',
                'url_prefix': 'accounting',
                'available_permissions': {
                    'view': 'Muhasebe görüntüleme',
                    'add': 'Muhasebe ekleme',
                    'edit': 'Muhasebe düzenleme',
                    'delete': 'Muhasebe silme',
                    'account_view': 'Hesap görüntüleme',
                    'account_add': 'Hesap ekleme',
                    'journal_view': 'Yevmiye görüntüleme',
                    'journal_add': 'Yevmiye ekleme',
                    'journal_post': 'Yevmiye kaydetme',
                    'invoice_view': 'Fatura görüntüleme',
                    'invoice_add': 'Fatura ekleme',
                    'payment_view': 'Ödeme görüntüleme',
                    'payment_add': 'Ödeme ekleme',
                    'report_view': 'Rapor görüntüleme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 11,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {accounting_module.name} modülü oluşturuldu'))
        else:
            self.stdout.write(f'  {accounting_module.name} modülü zaten mevcut')
        
        # Refunds (İade Yönetimi) Modülü
        refunds_module, created = Module.objects.get_or_create(
            code='refunds',
            defaults={
                'name': 'İade Yönetimi',
                'description': 'İade politikaları, talepler ve işlemler yönetimi',
                'icon': 'fas fa-undo',
                'category': 'other',
                'app_name': 'apps.tenant_apps.refunds',
                'url_prefix': 'refunds',
                'available_permissions': {
                    'view': 'İade görüntüleme',
                    'add': 'İade ekleme',
                    'edit': 'İade düzenleme',
                    'delete': 'İade silme',
                    'policy_view': 'Politika görüntüleme',
                    'policy_add': 'Politika ekleme',
                    'request_view': 'Talep görüntüleme',
                    'request_add': 'Talep ekleme',
                    'request_approve': 'Talep onaylama',
                    'request_reject': 'Talep reddetme',
                    'transaction_view': 'İşlem görüntüleme',
                    'report_view': 'Rapor görüntüleme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 12,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {refunds_module.name} modülü oluşturuldu'))
        else:
            self.stdout.write(f'  {refunds_module.name} modülü zaten mevcut')
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Tüm modüller oluşturuldu!'))

