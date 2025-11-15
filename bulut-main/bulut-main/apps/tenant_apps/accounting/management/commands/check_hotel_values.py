"""
Muhasebe modülündeki kayıtlarda hotel değerlerini kontrol et
Tenant schema'sında çalışır
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.accounting.models import Invoice, Account, JournalEntry, Payment
from apps.tenant_apps.hotels.models import Hotel


class Command(BaseCommand):
    help = 'Muhasebe modülündeki kayıtlarda hotel değerlerini kontrol et'

    def handle(self, *args, **options):
        self.stdout.write("=" * 60)
        self.stdout.write(self.style.SUCCESS("MUHASEBE MODÜLÜ HOTEL DEĞER KONTROLÜ"))
        self.stdout.write("=" * 60)

        # FATURALAR
        self.stdout.write("\n=== FATURALAR ===")
        invoices = Invoice.objects.filter(is_deleted=False)
        total_invoices = invoices.count()
        invoices_with_hotel = invoices.exclude(hotel__isnull=True).count()
        invoices_null = invoices.filter(hotel__isnull=True).count()

        self.stdout.write(f"Toplam fatura: {total_invoices}")
        self.stdout.write(f"Hotel atanmış: {invoices_with_hotel}")
        self.stdout.write(f"Hotel NULL: {invoices_null}")

        if invoices_null > 0:
            self.stdout.write(self.style.WARNING("\nHotel NULL olan faturalar:"))
            for inv in invoices.filter(hotel__isnull=True)[:10]:
                hotel_info = f"hotel={inv.hotel.id} ({inv.hotel.name})" if inv.hotel else "hotel=NULL"
                self.stdout.write(f"  - {inv.invoice_number}: {hotel_info} (source_module: {inv.source_module}, source_id: {inv.source_id})")

        # HESAPLAR
        self.stdout.write("\n=== HESAPLAR ===")
        accounts = Account.objects.filter(is_deleted=False)
        total_accounts = accounts.count()
        accounts_with_hotel = accounts.exclude(hotel__isnull=True).count()
        accounts_null = accounts.filter(hotel__isnull=True).count()

        self.stdout.write(f"Toplam hesap: {total_accounts}")
        self.stdout.write(f"Hotel atanmış: {accounts_with_hotel}")
        self.stdout.write(f"Hotel NULL: {accounts_null}")

        if accounts_null > 0:
            self.stdout.write(self.style.WARNING("\nHotel NULL olan hesaplar:"))
            for acc in accounts.filter(hotel__isnull=True)[:10]:
                hotel_info = f"hotel={acc.hotel.id} ({acc.hotel.name})" if acc.hotel else "hotel=NULL"
                self.stdout.write(f"  - {acc.code} - {acc.name}: {hotel_info}")

        # YEVMİYE KAYITLARI
        self.stdout.write("\n=== YEVMİYE KAYITLARI ===")
        entries = JournalEntry.objects.filter(is_deleted=False)
        total_entries = entries.count()
        entries_with_hotel = entries.exclude(hotel__isnull=True).count()
        entries_null = entries.filter(hotel__isnull=True).count()

        self.stdout.write(f"Toplam kayıt: {total_entries}")
        self.stdout.write(f"Hotel atanmış: {entries_with_hotel}")
        self.stdout.write(f"Hotel NULL: {entries_null}")

        if entries_null > 0:
            self.stdout.write(self.style.WARNING("\nHotel NULL olan kayıtlar:"))
            for entry in entries.filter(hotel__isnull=True)[:10]:
                hotel_info = f"hotel={entry.hotel.id} ({entry.hotel.name})" if entry.hotel else "hotel=NULL"
                self.stdout.write(f"  - {entry.entry_number}: {hotel_info} (source_module: {entry.source_module}, source_id: {entry.source_id})")

        # ÖDEMELER
        self.stdout.write("\n=== ÖDEMELER ===")
        payments = Payment.objects.filter(is_deleted=False)
        total_payments = payments.count()
        payments_with_hotel = payments.exclude(hotel__isnull=True).count()
        payments_null = payments.filter(hotel__isnull=True).count()

        self.stdout.write(f"Toplam ödeme: {total_payments}")
        self.stdout.write(f"Hotel atanmış: {payments_with_hotel}")
        self.stdout.write(f"Hotel NULL: {payments_null}")

        if payments_null > 0:
            self.stdout.write(self.style.WARNING("\nHotel NULL olan ödemeler:"))
            for pay in payments.filter(hotel__isnull=True)[:10]:
                hotel_info = f"hotel={pay.hotel.id} ({pay.hotel.name})" if pay.hotel else "hotel=NULL"
                self.stdout.write(f"  - {pay.payment_number}: {hotel_info} (source_module: {pay.source_module}, source_id: {pay.source_id})")

        # ÖZET
        self.stdout.write("\n" + "=" * 60)
        self.stdout.write(self.style.SUCCESS("ÖZET"))
        self.stdout.write("=" * 60)
        total_records = total_invoices + total_accounts + total_entries + total_payments
        total_with_hotel = invoices_with_hotel + accounts_with_hotel + entries_with_hotel + payments_with_hotel
        total_null = invoices_null + accounts_null + entries_null + payments_null
        
        self.stdout.write(f"Toplam kayıt: {total_records}")
        self.stdout.write(f"Hotel atanmış: {total_with_hotel}")
        self.stdout.write(f"Hotel NULL: {total_null}")
        
        if total_null > 0:
            self.stdout.write(self.style.WARNING(f"\nUYARI: {total_null} kayitta hotel degeri NULL!"))
            self.stdout.write(self.style.WARNING("Bu kayitlar icin hotel degeri atanmali."))
        else:
            self.stdout.write(self.style.SUCCESS("\nBASARILI: Tum kayitlarda hotel degeri atanmis!"))

        self.stdout.write("\n" + "=" * 60)

