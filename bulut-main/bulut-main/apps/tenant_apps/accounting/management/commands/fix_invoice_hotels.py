"""
Mevcut faturaların ve ödemelerin hotel field'ını düzelt
Eğer hotel null ise, source_module ve source_id'den otel bilgisini al
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.accounting.models import Invoice, Payment
from apps.tenant_apps.hotels.models import Hotel


class Command(BaseCommand):
    help = 'Mevcut faturaların ve ödemelerin hotel field\'ını düzelt'

    def handle(self, *args, **options):
        # FATURALAR
        self.stdout.write('=' * 60)
        self.stdout.write('FATURALAR')
        self.stdout.write('=' * 60)
        
        invoices_without_hotel = Invoice.objects.filter(
            is_deleted=False,
            hotel__isnull=True
        )
        
        self.stdout.write(f'Hotel field\'ı null olan fatura sayısı: {invoices_without_hotel.count()}')
        
        fixed_invoices = 0
        for invoice in invoices_without_hotel:
            hotel = None
            
            # Source module'dan otel bilgisini al
            if invoice.source_module == 'reception' and invoice.source_id:
                try:
                    from apps.tenant_apps.reception.models import Reservation
                    reservation = Reservation.objects.filter(pk=invoice.source_id).first()
                    if reservation and reservation.hotel:
                        hotel = reservation.hotel
                except Exception as e:
                    self.stdout.write(self.style.WARNING(f'Rezervasyon bulunamadı: {e}'))
            
            # Eğer hotel bulunduysa güncelle
            if hotel:
                invoice.hotel = hotel
                invoice.save(update_fields=['hotel'])
                fixed_invoices += 1
                self.stdout.write(self.style.SUCCESS(
                    f'Fatura {invoice.invoice_number} -> {hotel.name} oteline atandı'
                ))
            else:
                self.stdout.write(self.style.WARNING(
                    f'Fatura {invoice.invoice_number} için otel bulunamadı (source_module: {invoice.source_module}, source_id: {invoice.source_id})'
                ))
        
        self.stdout.write(self.style.SUCCESS(f'\nToplam {fixed_invoices} fatura güncellendi.'))
        
        # ÖDEMELER
        self.stdout.write('\n' + '=' * 60)
        self.stdout.write('ODEMELER')
        self.stdout.write('=' * 60)
        
        payments_without_hotel = Payment.objects.filter(
            is_deleted=False,
            hotel__isnull=True
        )
        
        self.stdout.write(f'Hotel field\'ı null olan ödeme sayısı: {payments_without_hotel.count()}')
        
        fixed_payments = 0
        for payment in payments_without_hotel:
            hotel = None
            
            # Invoice varsa invoice'dan otel bilgisini al
            if payment.invoice and payment.invoice.hotel:
                hotel = payment.invoice.hotel
            # Source module'dan otel bilgisini al
            elif payment.source_module == 'reception' and payment.source_id:
                try:
                    from apps.tenant_apps.reception.models import Reservation
                    reservation = Reservation.objects.filter(pk=payment.source_id).first()
                    if reservation and reservation.hotel:
                        hotel = reservation.hotel
                except Exception as e:
                    self.stdout.write(self.style.WARNING(f'Rezervasyon bulunamadı: {e}'))
            
            # Eğer hotel bulunduysa güncelle
            if hotel:
                payment.hotel = hotel
                payment.save(update_fields=['hotel'])
                fixed_payments += 1
                self.stdout.write(self.style.SUCCESS(
                    f'Ödeme {payment.payment_number} -> {hotel.name} oteline atandı'
                ))
            else:
                self.stdout.write(self.style.WARNING(
                    f'Ödeme {payment.payment_number} için otel bulunamadı (source_module: {payment.source_module}, source_id: {payment.source_id})'
                ))
        
        self.stdout.write(self.style.SUCCESS(f'\nToplam {fixed_payments} ödeme güncellendi.'))
        
        # ÖZET
        self.stdout.write('\n' + '=' * 60)
        self.stdout.write('OZET')
        self.stdout.write('=' * 60)
        self.stdout.write(self.style.SUCCESS(f'Toplam {fixed_invoices + fixed_payments} kayıt güncellendi.'))

