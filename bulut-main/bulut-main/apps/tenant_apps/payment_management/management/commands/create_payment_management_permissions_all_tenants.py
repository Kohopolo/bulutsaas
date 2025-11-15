"""
Tüm Tenant'larda Ödeme Yönetimi Modülü İzinlerini Oluşturma Komutu
Public schema'da çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Tüm tenant\'larda Ödeme Yönetimi modülü için izinleri oluşturur'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmalı
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(self.style.WARNING('[WARN] Bu komut public schema\'da calistirilmalidir.'))
            return

        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        total_tenants = tenants.count()
        success_count = 0
        error_count = 0

        self.stdout.write(f'[INFO] {total_tenants} tenant bulundu. İzinler oluşturuluyor...\n')

        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    from django.core.management import call_command
                    call_command('create_payment_management_permissions', verbosity=0)
                    success_count += 1
                    self.stdout.write(f'  [OK] {tenant.schema_name}: İzinler oluşturuldu')
            except Exception as e:
                error_count += 1
                self.stdout.write(
                    self.style.ERROR(f'  [ERROR] {tenant.schema_name}: {str(e)}')
                )

        self.stdout.write(
            self.style.SUCCESS(
                f'\n[OK] Tamamlandı: {success_count} başarılı, {error_count} hata'
            )
        )

