"""
Tüm aktif paketlere Tur modülünü ekle
Tur modülünün tüm yetkileri ve limitleri ile birlikte
"""
from django.core.management.base import BaseCommand
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Tüm aktif paketlere Tur modülünü ekle'

    def add_arguments(self, parser):
        parser.add_argument(
            '--package-code',
            type=str,
            help='Belirli bir pakete ekle (kod ile)',
        )

    def handle(self, *args, **options):
        # Tur modülünü al
        try:
            tour_module = Module.objects.get(code='tours', is_active=True)
            self.stdout.write(self.style.SUCCESS(f'[OK] Tur modülü bulundu: {tour_module.name}'))
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Tur modülü bulunamadı. Önce "python manage.py create_tour_module" komutunu çalıştırın.')
            )
            return
        
        # Paketleri al
        if options['package_code']:
            packages = Package.objects.filter(code=options['package_code'], is_active=True)
        else:
            packages = Package.objects.filter(is_active=True)
        
        if not packages.exists():
            self.stdout.write(self.style.WARNING('[UYARI] Aktif paket bulunamadı.'))
            return
        
        # Tur modülü için detaylı yetkiler (tüm alt modüller dahil)
        tour_permissions = {
            # Temel Tur Yetkileri
            'view': True,
            'add': True,
            'edit': True,
            'delete': False,
            'report': True,
            'export': True,
            # Rezervasyon Yetkileri
            'reservation_view': True,
            'reservation_add': True,
            'reservation_edit': True,
            'reservation_delete': False,
            'reservation_cancel': True,
            'reservation_refund': False,
            'reservation_payment': True,
            'reservation_voucher': True,
            # Dinamik Yönetim Yetkileri
            'dynamic_manage': True,
            # CRM ve Müşteri Yönetimi
            'customer_manage': True,
            'customer_view': True,
            'customer_add': True,
            'customer_edit': True,
            'customer_delete': False,
            # Acente Yönetimi
            'agency_manage': True,
            'agency_view': True,
            'agency_add': True,
            'agency_edit': True,
            'agency_delete': False,
            # Kampanya Yönetimi
            'campaign_manage': True,
            'campaign_view': True,
            'campaign_add': True,
            'campaign_edit': True,
            'campaign_delete': False,
            # Operasyonel Yönetim
            'operation_manage': True,
            'guide_manage': True,
            'vehicle_manage': True,
            'hotel_manage': True,
            'transfer_manage': True,
            # Bildirim Yönetimi
            'notification_manage': True,
            'notification_template_view': True,
            'notification_template_add': True,
            'notification_template_edit': True,
            'notification_template_delete': False,
            # Bekleme Listesi
            'waiting_list_view': True,
            'waiting_list_manage': True,
        }
        
        # Her pakete ekle
        added_count = 0
        skipped_count = 0
        
        for package in packages:
            # Paket tipine göre limitleri ayarla
            package_code_lower = package.code.lower()
            if 'starter' in package_code_lower or 'baslangic' in package_code_lower or 'başlangıç' in package_code_lower:
                current_limits = {
                    'max_tours': 20,
                    'max_tour_users': 3,
                    'max_tour_reservations': 500,
                    'max_tour_reservations_per_month': 50,
                }
            elif 'professional' in package_code_lower or 'profesyonel' in package_code_lower:
                current_limits = {
                    'max_tours': 100,
                    'max_tour_users': 10,
                    'max_tour_reservations': 5000,
                    'max_tour_reservations_per_month': 500,
                }
            elif 'enterprise' in package_code_lower or 'kurumsal' in package_code_lower:
                current_limits = {
                    'max_tours': -1,  # Sınırsız
                    'max_tour_users': -1,  # Sınırsız
                    'max_tour_reservations': -1,  # Sınırsız
                    'max_tour_reservations_per_month': -1,  # Sınırsız
                }
            else:
                # Varsayılan limitler
                current_limits = {
                    'max_tours': 50,
                    'max_tour_users': 5,
                    'max_tour_reservations': 1000,
                    'max_tour_reservations_per_month': 100,
                }
            
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=tour_module,
                defaults={
                    'permissions': tour_permissions,
                    'limits': current_limits,
                    'is_enabled': True,
                    'is_required': False,
                }
            )
            
            if created:
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Tur modülü "{package.name}" paketine eklendi')
                )
                added_count += 1
            else:
                # Mevcut modülü güncelle
                package_module.permissions = tour_permissions
                package_module.limits = current_limits
                package_module.is_enabled = True
                package_module.save()
                self.stdout.write(
                    self.style.WARNING(f'[GÜNCELLENDİ] Tur modülü "{package.name}" paketinde güncellendi')
                )
                skipped_count += 1
        
        # Özet
        self.stdout.write('')
        self.stdout.write(self.style.SUCCESS('=' * 60))
        self.stdout.write(self.style.SUCCESS(f'Toplam {packages.count()} paket işlendi:'))
        self.stdout.write(f'  - Yeni eklenen: {added_count}')
        self.stdout.write(f'  - Güncellenen: {skipped_count}')
        self.stdout.write(self.style.SUCCESS('=' * 60))

