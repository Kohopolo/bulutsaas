#!/usr/bin/env python
"""
Resepsiyon Modülü Veritabanı Temizleme Scripti
Module kaydını ve veritabanı tablolarını siler
"""
import os
import sys
import django

# Django setup
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from django.db import connection
from django_tenants.utils import schema_context
from apps.modules.models import Module
from apps.packages.models import PackageModule


def cleanup_reception_database():
    """Resepsiyon modülünü veritabanından tamamen temizle"""
    
    print("=" * 60)
    print("RESEPSIYON MODULU VERITABANI TEMIZLEME")
    print("=" * 60)
    
    # 1. PackageModule kayıtlarını sil (public schema)
    print("\n[1] PackageModule kayitlarini temizleniyor...")
    try:
        with schema_context('public'):
            reception_module = Module.objects.filter(code='reception').first()
            if reception_module:
                package_modules = PackageModule.objects.filter(module=reception_module)
                count = package_modules.count()
                package_modules.delete()
                print(f"   [OK] {count} PackageModule kaydi silindi")
            else:
                print("   [WARN] Reception modulu bulunamadi")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 2. Module kaydını sil (public schema)
    print("\n[2] Module kaydini siliniyor...")
    try:
        with schema_context('public'):
            reception_module = Module.objects.filter(code='reception').first()
            if reception_module:
                reception_module.delete()
                print("   [OK] Reception modulu silindi")
            else:
                print("   [WARN] Reception modulu zaten silinmis")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 3. Veritabanı tablolarını sil (public schema)
    print("\n[3] Veritabani tablolarini siliniyor...")
    try:
        with schema_context('public'):
            with connection.cursor() as cursor:
                # Reception modülü tabloları
                tables = [
                    'reception_reservation',
                    'reception_reservationupdate',
                    'reception_roomchange',
                    'reception_checkin',
                    'reception_checkout',
                    'reception_keycard',
                    'reception_receptionsession',
                    'reception_receptionactivity',
                    'reception_receptionsettings',
                    'reception_quickaction',
                ]
                
                deleted_count = 0
                for table in tables:
                    try:
                        cursor.execute(f"DROP TABLE IF EXISTS {table} CASCADE;")
                        deleted_count += 1
                        print(f"   [OK] {table} tablosu silindi")
                    except Exception as e:
                        print(f"   [WARN] {table} tablosu silinemedi: {e}")
                
                print(f"\n   [OK] {deleted_count} tablo silindi")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 4. Tenant schema'lardaki tabloları sil
    print("\n[4] Tenant schema'lardaki tablolari siliniyor...")
    try:
        from apps.tenants.models import Tenant
        
        tenants = Tenant.objects.all()
        total_deleted = 0
        
        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    with connection.cursor() as cursor:
                        tables = [
                            'reception_reservation',
                            'reception_reservationupdate',
                            'reception_roomchange',
                            'reception_checkin',
                            'reception_checkout',
                            'reception_keycard',
                            'reception_receptionsession',
                            'reception_receptionactivity',
                            'reception_receptionsettings',
                            'reception_quickaction',
                        ]
                        
                        for table in tables:
                            try:
                                cursor.execute(f"DROP TABLE IF EXISTS {table} CASCADE;")
                                total_deleted += 1
                            except Exception as e:
                                pass  # Tablo yoksa hata verme
                
                print(f"   [OK] {tenant.schema_name} schema'sindaki tablolar temizlendi")
            except Exception as e:
                print(f"   [WARN] {tenant.schema_name} schema'sinda hata: {e}")
        
        print(f"\n   [OK] Toplam {total_deleted} tablo silindi")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    print("\n" + "=" * 60)
    print("[OK] VERITABANI TEMIZLEME ISLEMI TAMAMLANDI")
    print("=" * 60)


if __name__ == '__main__':
    cleanup_reception_database()

