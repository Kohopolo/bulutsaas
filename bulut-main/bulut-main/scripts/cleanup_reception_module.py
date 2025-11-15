#!/usr/bin/env python
"""
Resepsiyon Modülü Temizleme Scripti
Veritabanından reception modülünü tamamen kaldırır
"""
import os
import sys
import django

# Django setup
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from django.core.management import call_command
from django.db import connection
from apps.modules.models import Module
from apps.packages.models import PackageModule


def cleanup_reception_module():
    """Resepsiyon modülünü veritabanından temizle"""
    
    print("=" * 60)
    print("RESEPSIYON MODULU TEMIZLEME ISLEMI")
    print("=" * 60)
    
    # 1. PackageModule kayıtlarını sil
    print("\n[1] PackageModule kayitlarini temizleniyor...")
    try:
        reception_module = Module.objects.filter(code='reception').first()
        if reception_module:
            package_modules = PackageModule.objects.filter(module=reception_module)
            count = package_modules.count()
            package_modules.delete()
            print(f"   [OK] {count} PackageModule kaydi silindi")
        else:
            print("   [WARN] Reception modulu bulunamadi (zaten silinmis olabilir)")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 2. Module kaydını sil
    print("\n[2] Module kaydini temizleniyor...")
    try:
        reception_module = Module.objects.filter(code='reception').first()
        if reception_module:
            reception_module.delete()
            print("   [OK] Reception modulu silindi")
        else:
            print("   [WARN] Reception modulu zaten silinmis")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 3. Migration'ları geri al (opsiyonel - dikkatli olun!)
    print("\n[3] Migration'lari geri alma islemi atlandi.")
    print("   Migration'ları manuel olarak geri almak için:")
    print("   python manage.py migrate_schemas reception zero --schema public")
    print("   python manage.py migrate_schemas reception zero")
    
    print("\n" + "=" * 60)
    print("[OK] TEMIZLEME ISLEMI TAMAMLANDI")
    print("=" * 60)


if __name__ == '__main__':
    cleanup_reception_module()

