"""
Resepsiyon modülündeki tüm kayıtlarda hotel değerlerini kontrol et
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.reception.models import Reservation, ReservationPayment, ReservationGuest, ReservationTimeline, ReservationVoucher
from apps.tenant_apps.hotels.models import Hotel


class Command(BaseCommand):
    help = 'Resepsiyon modülündeki kayıtlarda hotel değerlerini kontrol et'

    def handle(self, *args, **options):
        self.stdout.write("=" * 60)
        self.stdout.write(self.style.SUCCESS("RESEPSIYON MODULU HOTEL DEGER KONTROLU"))
        self.stdout.write("=" * 60)

        # REZERVASYONLAR
        self.stdout.write("\n=== REZERVASYONLAR ===")
        reservations = Reservation.objects.filter(is_deleted=False)
        total_reservations = reservations.count()
        reservations_with_hotel = reservations.exclude(hotel__isnull=True).count()
        reservations_null = reservations.filter(hotel__isnull=True).count()

        self.stdout.write(f"Toplam rezervasyon: {total_reservations}")
        self.stdout.write(f"Hotel atanmis: {reservations_with_hotel}")
        self.stdout.write(f"Hotel NULL: {reservations_null}")

        if reservations_null > 0:
            self.stdout.write(self.style.WARNING("\nHotel NULL olan rezervasyonlar:"))
            for res in reservations.filter(hotel__isnull=True)[:10]:
                self.stdout.write(f"  - {res.reservation_code}: hotel=NULL")

        # REZERVASYON ODEMELERI
        self.stdout.write("\n=== REZERVASYON ODEMELERI ===")
        payments = ReservationPayment.objects.filter(is_deleted=False)
        total_payments = payments.count()
        
        # Rezervasyon üzerinden hotel kontrolü
        payments_with_hotel = 0
        payments_null_hotel = 0
        for payment in payments:
            if payment.reservation and payment.reservation.hotel:
                payments_with_hotel += 1
            else:
                payments_null_hotel += 1

        self.stdout.write(f"Toplam odeme: {total_payments}")
        self.stdout.write(f"Rezervasyon hotel atanmis: {payments_with_hotel}")
        self.stdout.write(f"Rezervasyon hotel NULL: {payments_null_hotel}")

        if payments_null_hotel > 0:
            self.stdout.write(self.style.WARNING("\nRezervasyon hotel NULL olan odemeler:"))
            for pay in payments.filter(reservation__hotel__isnull=True)[:10]:
                self.stdout.write(f"  - {pay.reservation.reservation_code if pay.reservation else 'NO RESERVATION'}: hotel=NULL")

        # REZERVASYON MISAFIRLERI
        self.stdout.write("\n=== REZERVASYON MISAFIRLERI ===")
        guests = ReservationGuest.objects.all()
        total_guests = guests.count()
        
        guests_with_hotel = 0
        guests_null_hotel = 0
        for guest in guests:
            if guest.reservation and guest.reservation.hotel:
                guests_with_hotel += 1
            else:
                guests_null_hotel += 1

        self.stdout.write(f"Toplam misafir: {total_guests}")
        self.stdout.write(f"Rezervasyon hotel atanmis: {guests_with_hotel}")
        self.stdout.write(f"Rezervasyon hotel NULL: {guests_null_hotel}")

        # REZERVASYON TIMELINE
        self.stdout.write("\n=== REZERVASYON TIMELINE ===")
        timelines = ReservationTimeline.objects.all()
        total_timelines = timelines.count()
        
        timelines_with_hotel = 0
        timelines_null_hotel = 0
        for timeline in timelines:
            if timeline.reservation and timeline.reservation.hotel:
                timelines_with_hotel += 1
            else:
                timelines_null_hotel += 1

        self.stdout.write(f"Toplam timeline: {total_timelines}")
        self.stdout.write(f"Rezervasyon hotel atanmis: {timelines_with_hotel}")
        self.stdout.write(f"Rezervasyon hotel NULL: {timelines_null_hotel}")

        # REZERVASYON VOUCHERLARI
        self.stdout.write("\n=== REZERVASYON VOUCHERLARI ===")
        vouchers = ReservationVoucher.objects.all()
        total_vouchers = vouchers.count()
        
        vouchers_with_hotel = 0
        vouchers_null_hotel = 0
        for voucher in vouchers:
            if voucher.reservation and voucher.reservation.hotel:
                vouchers_with_hotel += 1
            else:
                vouchers_null_hotel += 1

        self.stdout.write(f"Toplam voucher: {total_vouchers}")
        self.stdout.write(f"Rezervasyon hotel atanmis: {vouchers_with_hotel}")
        self.stdout.write(f"Rezervasyon hotel NULL: {vouchers_null_hotel}")

        # OZET
        self.stdout.write("\n" + "=" * 60)
        self.stdout.write("OZET")
        self.stdout.write("=" * 60)
        
        total_records = total_reservations + total_payments + total_guests + total_timelines + total_vouchers
        total_with_hotel = reservations_with_hotel + payments_with_hotel + guests_with_hotel + timelines_with_hotel + vouchers_with_hotel
        total_null = reservations_null + payments_null_hotel + guests_null_hotel + timelines_null_hotel + vouchers_null_hotel
        
        self.stdout.write(f"Toplam kayit: {total_records}")
        self.stdout.write(f"Hotel atanmis: {total_with_hotel}")
        self.stdout.write(f"Hotel NULL: {total_null}")
        
        if total_null > 0:
            self.stdout.write(self.style.WARNING(f"\nUYARI: {total_null} kayitta hotel degeri NULL!"))
            self.stdout.write(self.style.WARNING("Bu kayitlar icin hotel degeri atanmali."))
        else:
            self.stdout.write(self.style.SUCCESS("\nBASARILI: Tum kayitlarda hotel degeri atanmis!"))

        self.stdout.write("\n" + "=" * 60)

