"""
Reception Signals
Rezervasyon işlemlerinde otomatik entegrasyonlar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from .models import Reservation


@receiver(post_save, sender=Reservation)
def create_reservation_update_log(sender, instance, created, **kwargs):
    """Rezervasyon oluşturulduğunda veya güncellendiğinde log oluştur"""
    # Audit log için kullanılabilir
    pass


@receiver(pre_save, sender=Reservation)
def sync_payment_to_finance_and_accounting(sender, instance, **kwargs):
    """Rezervasyon ödemesi değiştiğinde Finance ve Accounting'e senkronize et"""
    if instance.pk:
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            if old_instance.total_paid != instance.total_paid:
                # Finance ve Accounting entegrasyonu burada yapılabilir
                pass
        except Reservation.DoesNotExist:
            pass

