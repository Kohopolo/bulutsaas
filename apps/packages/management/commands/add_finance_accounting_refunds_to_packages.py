"""
Finance, Accounting ve Refunds modüllerini tüm aktif paketlere ekler
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Finance, Accounting ve Refunds modüllerini tüm aktif paketlere ekler'
    
    def handle(self, *args, **kwargs):
        self.stdout.write('Modüller paketlere ekleniyor...')
        
        # Modülleri al
        try:
            finance_module = Module.objects.get(code='finance')
            accounting_module = Module.objects.get(code='accounting')
            refunds_module = Module.objects.get(code='refunds')
        except Module.DoesNotExist as e:
            self.stdout.write(self.style.ERROR(f'Hata: {e}'))
            self.stdout.write('Önce modülleri oluşturun: python manage.py create_finance_accounting_refunds_modules')
            return
        
        # Tüm aktif paketleri al
        packages = Package.objects.filter(is_active=True, is_deleted=False)
        
        for package in packages:
            self.stdout.write(f'\nPaket: {package.name}')
            
            # Finance modülü
            finance_permissions = {
                'view': True,
                'add': True,
                'edit': True,
                'delete': True,
                'transaction_view': True,
                'transaction_add': True,
                'transaction_edit': True,
                'transaction_delete': True,
                'report_view': True,
            }
            finance_limits = {}
            
            PackageModule.objects.get_or_create(
                package=package,
                module=finance_module,
                defaults={
                    'permissions': finance_permissions,
                    'limits': finance_limits,
                    'is_enabled': True,
                    'is_required': False,
                }
            )
            self.stdout.write(f'  [OK] Finance modülü eklendi')
            
            # Accounting modülü
            accounting_permissions = {
                'view': True,
                'add': True,
                'edit': True,
                'delete': True,
                'account_view': True,
                'account_add': True,
                'journal_view': True,
                'journal_add': True,
                'journal_post': True,
                'invoice_view': True,
                'invoice_add': True,
                'payment_view': True,
                'payment_add': True,
                'report_view': True,
            }
            accounting_limits = {}
            
            PackageModule.objects.get_or_create(
                package=package,
                module=accounting_module,
                defaults={
                    'permissions': accounting_permissions,
                    'limits': accounting_limits,
                    'is_enabled': True,
                    'is_required': False,
                }
            )
            self.stdout.write(f'  [OK] Accounting modülü eklendi')
            
            # Refunds modülü
            refunds_permissions = {
                'view': True,
                'add': True,
                'edit': True,
                'delete': True,
                'policy_view': True,
                'policy_add': True,
                'request_view': True,
                'request_add': True,
                'request_approve': True,
                'request_reject': True,
                'transaction_view': True,
                'report_view': True,
            }
            refunds_limits = {}
            
            PackageModule.objects.get_or_create(
                package=package,
                module=refunds_module,
                defaults={
                    'permissions': refunds_permissions,
                    'limits': refunds_limits,
                    'is_enabled': True,
                    'is_required': False,
                }
            )
            self.stdout.write(f'  [OK] Refunds modülü eklendi')
        
        self.stdout.write(self.style.SUCCESS(f'\n[OK] Tüm paketlere modüller eklendi!'))

