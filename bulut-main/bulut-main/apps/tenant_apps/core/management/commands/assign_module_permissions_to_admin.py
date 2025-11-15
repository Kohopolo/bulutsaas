"""
Yeni modül eklendiğinde admin rolüne otomatik yetki atama komutu
Tenant schema içinde çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.tenant_apps.core.models import Role, RolePermission, Permission


class Command(BaseCommand):
    help = 'Yeni eklenen modül yetkilerini admin rolüne otomatik atar (tenant schema içinde)'

    def add_arguments(self, parser):
        parser.add_argument(
            '--module-code',
            type=str,
            help='Belirli bir modül için yetki atama (opsiyonel)',
        )

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.WARNING('[WARN] Bu komut tenant schema icinde calistirilmalidir.'))
            return

        module_code = options.get('module_code')

        # Admin rolünü bul
        admin_role = Role.objects.filter(code='admin', is_active=True).first()
        if not admin_role:
            self.stdout.write(self.style.ERROR('[ERROR] Admin rolu bulunamadi. Once create_default_roles komutunu calistirin.'))
            return

        # Yetkileri filtrele
        if module_code:
            permissions = Permission.objects.filter(
                module__code=module_code,
                is_active=True
            )
        else:
            # Tüm aktif yetkileri al
            permissions = Permission.objects.filter(is_active=True)

        assigned_count = 0
        skipped_count = 0

        for permission in permissions:
            role_permission, created = RolePermission.objects.get_or_create(
                role=admin_role,
                permission=permission,
                defaults={
                    'is_active': True,
                }
            )
            if created:
                assigned_count += 1
            else:
                skipped_count += 1

        if module_code:
            self.stdout.write(self.style.SUCCESS(
                f'[OK] {module_code} modulu icin {assigned_count} yetki admin rolune atandi, {skipped_count} zaten mevcut'
            ))
        else:
            self.stdout.write(self.style.SUCCESS(
                f'[OK] Toplam {assigned_count} yetki admin rolune atandi, {skipped_count} zaten mevcut'
            ))


