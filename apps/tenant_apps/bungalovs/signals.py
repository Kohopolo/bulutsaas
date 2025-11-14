"""
Bungalov Modülü Signals
Otomatik işlemler için signal'lar
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
from datetime import date
import logging

from .models import (
    BungalovReservation, BungalovReservationPayment,
    BungalovCleaning, BungalovMaintenance,
    ReservationStatus
)

logger = logging.getLogger(__name__)


@receiver(post_save, sender=BungalovReservationPayment)
def update_reservation_total_paid(sender, instance, created, **kwargs):
    """
    Ödeme kaydedildiğinde rezervasyonun total_paid değerini güncelle
    """
    try:
        if instance.reservation:
            instance.reservation.update_total_paid()
    except Exception as e:
        logger.error(f'Rezervasyon total_paid güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovReservation)
def update_bungalov_status_on_reservation(sender, instance, created, **kwargs):
    """
    Rezervasyon durumu değiştiğinde bungalov durumunu güncelle
    """
    try:
        if instance.bungalov:
            today = timezone.now().date()
            
            # Check-in yapıldıysa bungalov'u dolu yap
            if instance.is_checked_in and instance.status == ReservationStatus.CHECKED_IN:
                if instance.check_in_date <= today <= instance.check_out_date:
                    instance.bungalov.status = 'occupied'
                    instance.bungalov.save()
            
            # Check-out yapıldıysa bungalov'u temizlikte yap
            elif instance.is_checked_out and instance.status == ReservationStatus.CHECKED_OUT:
                instance.bungalov.status = 'cleaning'
                instance.bungalov.save()
            
            # Rezervasyon iptal edildiyse bungalov'u müsait yap
            elif instance.is_cancelled:
                if instance.bungalov.status == 'occupied':
                    instance.bungalov.status = 'available'
                    instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovCleaning)
def update_bungalov_status_on_cleaning(sender, instance, created, **kwargs):
    """
    Temizlik tamamlandığında bungalov durumunu güncelle
    """
    try:
        if instance.status == 'clean' and instance.bungalov:
            instance.bungalov.status = 'available'
            instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)


@receiver(post_save, sender=BungalovMaintenance)
def update_bungalov_status_on_maintenance(sender, instance, created, **kwargs):
    """
    Bakım durumu değiştiğinde bungalov durumunu güncelle
    """
    try:
        if instance.bungalov:
            if instance.status == 'in_progress':
                instance.bungalov.status = 'maintenance'
                instance.bungalov.save()
            elif instance.status == 'completed':
                # Bakım tamamlandıysa ve başka sorun yoksa müsait yap
                if instance.bungalov.status == 'maintenance':
                    instance.bungalov.status = 'available'
                    instance.bungalov.save()
    except Exception as e:
        logger.error(f'Bungalov durumu güncellenirken hata: {str(e)}', exc_info=True)

