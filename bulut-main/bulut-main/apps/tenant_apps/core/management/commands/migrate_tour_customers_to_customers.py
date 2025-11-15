"""
TourCustomer verilerini Customer modeline taşıma komutu
Tenant schema içinde çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from django.db.models import Q
from apps.tenant_apps.core.models import Customer, CustomerLoyaltyHistory, CustomerNote
from decimal import Decimal


class Command(BaseCommand):
    help = 'TourCustomer verilerini Customer modeline taşır (tenant schema içinde)'

    def add_arguments(self, parser):
        parser.add_argument(
            '--dry-run',
            action='store_true',
            help='Sadece simülasyon yap, veri taşıma',
        )
        parser.add_argument(
            '--skip-existing',
            action='store_true',
            help='Mevcut Customer kayıtlarını atla',
        )

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.WARNING('[WARN] Bu komut tenant schema icinde calistirilmalidir.'))
            return

        dry_run = options.get('dry_run', False)
        skip_existing = options.get('skip_existing', False)

        # TourCustomer modelini import et
        try:
            from apps.tenant_apps.tours.models import TourCustomer, TourLoyaltyHistory, TourCustomerNote
        except ImportError:
            self.stdout.write(self.style.ERROR('[ERROR] TourCustomer modeli bulunamadi. Tour modulu yuklu mu?'))
            return

        # TourCustomer kayıtlarını al
        tour_customers = TourCustomer.objects.filter(is_deleted=False)
        total_count = tour_customers.count()

        if total_count == 0:
            self.stdout.write(self.style.WARNING('[WARN] Tasinacak TourCustomer kaydi bulunamadi.'))
            return

        self.stdout.write(self.style.SUCCESS(f'[INFO] {total_count} TourCustomer kaydi bulundu.'))

        migrated_count = 0
        skipped_count = 0
        error_count = 0

        for tour_customer in tour_customers:
            try:
                # Mevcut Customer'ı kontrol et
                existing_customer = Customer.find_by_identifier(
                    email=tour_customer.email,
                    phone=tour_customer.phone,
                    tc_no=tour_customer.tc_no
                )

                if existing_customer:
                    if skip_existing:
                        skipped_count += 1
                        self.stdout.write(self.style.WARNING(f'[SKIP] {tour_customer.email} - Zaten mevcut'))
                        continue
                    else:
                        # Mevcut Customer'ı güncelle
                        customer = existing_customer
                        updated = True
                else:
                    # Yeni Customer oluştur
                    customer = Customer(
                        first_name=tour_customer.first_name,
                        last_name=tour_customer.last_name,
                        email=tour_customer.email,
                        phone=tour_customer.phone,
                        tc_no=tour_customer.tc_no,
                        address=tour_customer.address,
                        city=tour_customer.city,
                        country=tour_customer.country,
                        postal_code=tour_customer.postal_code,
                        birth_date=tour_customer.birth_date,
                        special_dates=tour_customer.special_dates,
                        loyalty_points=tour_customer.loyalty_points,
                        total_reservations=tour_customer.total_reservations,
                        total_spent=tour_customer.total_spent,
                        vip_level=tour_customer.vip_level,
                        is_vip=tour_customer.is_vip,
                        notes=tour_customer.notes,
                        special_requests=tour_customer.special_requests,
                        is_active=tour_customer.is_active,
                        last_reservation_date=tour_customer.last_reservation_date,
                    )
                    updated = False

                if not dry_run:
                    customer.save()
                    migrated_count += 1

                    # Sadakat puanı geçmişini taşı
                    loyalty_history = TourLoyaltyHistory.objects.filter(customer=tour_customer)
                    for history in loyalty_history:
                        CustomerLoyaltyHistory.objects.get_or_create(
                            customer=customer,
                            points=history.points,
                            reason=history.reason,
                            module='tours',
                            reference_id=history.reservation_id if history.reservation else None,
                            reference_type='reservation' if history.reservation else '',
                            defaults={
                                'created_at': history.created_at,
                            }
                        )

                    # Müşteri notlarını taşı
                    customer_notes = TourCustomerNote.objects.filter(customer=tour_customer)
                    for note in customer_notes:
                        CustomerNote.objects.get_or_create(
                            customer=customer,
                            note=note.note,
                            note_type=note.note_type,
                            created_by=note.created_by,
                            is_important=note.is_important,
                            defaults={
                                'created_at': note.created_at,
                            }
                        )

                    # TourReservation'lardaki customer referanslarını güncelle
                    from apps.tenant_apps.tours.models import TourReservation
                    TourReservation.objects.filter(
                        customer_email=tour_customer.email
                    ).update(customer=customer)

                    if updated:
                        self.stdout.write(self.style.SUCCESS(f'[UPDATE] {customer.email} - Guncellendi'))
                    else:
                        self.stdout.write(self.style.SUCCESS(f'[CREATE] {customer.email} - Olusturuldu'))
                else:
                    # Dry run - sadece bilgi ver
                    if existing_customer:
                        self.stdout.write(self.style.WARNING(f'[DRY-RUN] {tour_customer.email} - Guncellenecek'))
                    else:
                        self.stdout.write(self.style.SUCCESS(f'[DRY-RUN] {tour_customer.email} - Olusturulacak'))
                    migrated_count += 1

            except Exception as e:
                error_count += 1
                self.stdout.write(self.style.ERROR(f'[ERROR] {tour_customer.email} - Hata: {str(e)}'))

        # Özet
        self.stdout.write(self.style.SUCCESS(f'\n[OK] Toplam: {total_count} kayit'))
        if dry_run:
            self.stdout.write(self.style.WARNING(f'[DRY-RUN] {migrated_count} kayit olusturulacak/guncellenecek'))
        else:
            self.stdout.write(self.style.SUCCESS(f'[OK] {migrated_count} kayit tasindi'))
            if skipped_count > 0:
                self.stdout.write(self.style.WARNING(f'[SKIP] {skipped_count} kayit atlandi'))
            if error_count > 0:
                self.stdout.write(self.style.ERROR(f'[ERROR] {error_count} kayit hata verdi'))

