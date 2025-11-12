"""
Tur Modülü için detaylı yetkileri oluştur
Tenant schema'da çalışır
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.core.models import Permission
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Tur modülü için detaylı yetkileri oluştur'

    def handle(self, *args, **options):
        # Tur modülünü al (shared app'ten)
        try:
            tour_module = Module.objects.get(code='tours', is_active=True)
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Tur modülü bulunamadı. Önce "python manage.py create_tour_module" komutunu çalıştırın.')
            )
            return
        
        # Tur modülü yetkileri
        permissions = [
            # Temel Tur Yetkileri
            {
                'name': 'Tur Görüntüleme',
                'code': 'view',
                'description': 'Turları görüntüleme yetkisi',
                'permission_type': 'view',
                'is_system': True,
            },
            {
                'name': 'Tur Ekleme',
                'code': 'add',
                'description': 'Yeni tur ekleme yetkisi',
                'permission_type': 'add',
                'is_system': True,
            },
            {
                'name': 'Tur Düzenleme',
                'code': 'edit',
                'description': 'Mevcut turları düzenleme yetkisi',
                'permission_type': 'edit',
                'is_system': True,
            },
            {
                'name': 'Tur Silme',
                'code': 'delete',
                'description': 'Turları silme yetkisi',
                'permission_type': 'delete',
                'is_system': True,
            },
            {
                'name': 'Tur Raporlama',
                'code': 'report',
                'description': 'Tur raporlarını görüntüleme yetkisi',
                'permission_type': 'other',
                'is_system': True,
            },
            {
                'name': 'Tur Dışa Aktarma',
                'code': 'export',
                'description': 'Tur verilerini dışa aktarma yetkisi',
                'permission_type': 'export',
                'is_system': True,
            },
            # Rezervasyon Yetkileri
            {
                'name': 'Rezervasyon Görüntüleme',
                'code': 'reservation_view',
                'description': 'Tur rezervasyonlarını görüntüleme yetkisi',
                'permission_type': 'view',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon Ekleme',
                'code': 'reservation_add',
                'description': 'Yeni tur rezervasyonu ekleme yetkisi',
                'permission_type': 'add',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon Düzenleme',
                'code': 'reservation_edit',
                'description': 'Tur rezervasyonlarını düzenleme yetkisi',
                'permission_type': 'edit',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon Silme',
                'code': 'reservation_delete',
                'description': 'Tur rezervasyonlarını silme yetkisi',
                'permission_type': 'delete',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon İptal',
                'code': 'reservation_cancel',
                'description': 'Tur rezervasyonlarını iptal etme yetkisi',
                'permission_type': 'cancel',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon İade',
                'code': 'reservation_refund',
                'description': 'Tur rezervasyonlarında iade yapma yetkisi',
                'permission_type': 'other',
                'is_system': True,
            },
            {
                'name': 'Rezervasyon Ödeme',
                'code': 'reservation_payment',
                'description': 'Tur rezervasyonları için ödeme ekleme/düzenleme yetkisi',
                'permission_type': 'other',
                'is_system': True,
            },
            {
                'name': 'Voucher Oluşturma',
                'code': 'reservation_voucher',
                'description': 'Tur rezervasyonları için voucher oluşturma yetkisi',
                'permission_type': 'other',
                'is_system': True,
            },
            # Dinamik Yönetim Yetkileri
            {
                'name': 'Dinamik Yönetim',
                'code': 'dynamic_manage',
                'description': 'Bölge, Şehir, Tür, Lokasyon gibi dinamik verileri yönetme yetkisi',
                'permission_type': 'other',
                'is_system': True,
            },
        ]
        
        created_count = 0
        updated_count = 0
        
        for perm_data in permissions:
            permission, created = Permission.objects.get_or_create(
                module=tour_module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'description': perm_data['description'],
                    'permission_type': perm_data['permission_type'],
                    'is_active': True,
                    'is_system': perm_data['is_system'],
                    'sort_order': created_count + 1,
                }
            )
            
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {permission.name} oluşturuldu')
                )
            else:
                # Mevcut yetkiyi güncelle
                permission.name = perm_data['name']
                permission.description = perm_data['description']
                permission.permission_type = perm_data['permission_type']
                permission.is_active = True
                permission.is_system = perm_data['is_system']
                permission.save()
                updated_count += 1
                self.stdout.write(
                    self.style.WARNING(f'[GUNCELLENDI] {permission.name} güncellendi')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} yetki oluşturuldu, {updated_count} yetki güncellendi')
        )

