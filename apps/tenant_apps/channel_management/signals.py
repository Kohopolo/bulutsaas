"""
Kanal Yönetimi Signal'ları
"""
from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone
from datetime import timedelta, date
from decimal import Decimal
from .models import ChannelConfiguration, ChannelReservation, ChannelSync, ChannelPricing


@receiver(post_save, sender=ChannelConfiguration)
def update_next_sync_time(sender, instance, created, **kwargs):
    """Kanal konfigürasyonu kaydedildiğinde sonraki senkronizasyon zamanını güncelle"""
    if instance.sync_enabled and instance.sync_interval > 0:
        if not instance.next_sync_at or instance.next_sync_at < timezone.now():
            instance.next_sync_at = timezone.now() + timedelta(minutes=instance.sync_interval)
            # post_save'den kaçınmak için update kullan
            ChannelConfiguration.objects.filter(pk=instance.pk).update(
                next_sync_at=instance.next_sync_at
            )


@receiver(post_save, sender=ChannelReservation)
def create_system_reservation(sender, instance, created, **kwargs):
    """Kanal rezervasyonu oluşturulduğunda sistem rezervasyonu oluştur (opsiyonel)"""
    if created and instance.status == 'confirmed' and not instance.system_reservation:
        # Sistem rezervasyonu oluşturma işlemi burada yapılabilir
        # Şimdilik boş bırakıyoruz, ileride entegre edilebilir
        pass


# ==================== HOTELS MODÜLÜ ENTEGRASYONU ====================

@receiver(post_save, sender='hotels.RoomPrice')
def sync_room_price_to_channels(sender, instance, created, **kwargs):
    """Oda fiyatı değiştiğinde kanallara senkronize et"""
    try:
        from .models import ChannelConfiguration
        
        # Bu oda için aktif kanal konfigürasyonlarını bul
        configurations = ChannelConfiguration.objects.filter(
            hotel=instance.room.hotel,
            is_active=True,
            sync_enabled=True,
            auto_sync_pricing=True,
            is_deleted=False
        )
        
        for config in configurations:
            # Otomatik senkronizasyon için işaretle
            # Gerçek senkronizasyon async task olarak çalıştırılabilir
            pass
            
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f"Oda fiyatı kanal senkronizasyonu hatası: {str(e)}", exc_info=True)


@receiver(post_save, sender='hotels.RoomNumber')
def sync_room_availability_to_channels(sender, instance, created, **kwargs):
    """Oda durumu değiştiğinde müsaitlik bilgisini kanallara senkronize et"""
    try:
        from .models import ChannelConfiguration
        
        # Bu oda için aktif kanal konfigürasyonlarını bul
        configurations = ChannelConfiguration.objects.filter(
            hotel=instance.room.hotel,
            is_active=True,
            sync_enabled=True,
            auto_sync_availability=True,
            is_deleted=False
        )
        
        for config in configurations:
            # Otomatik senkronizasyon için işaretle
            # Gerçek senkronizasyon async task olarak çalıştırılabilir
            pass
            
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f"Oda müsaitlik kanal senkronizasyonu hatası: {str(e)}", exc_info=True)


# ==================== RECEPTION MODÜLÜ ENTEGRASYONU ====================

@receiver(post_save, sender='reception.Reservation')
def sync_reservation_to_channels(sender, instance, created, **kwargs):
    """Sistem rezervasyonu oluşturulduğunda kanallara bildir (eğer kanal rezervasyonu ise)"""
    try:
        from .models import ChannelReservation
        
        # Eğer bu rezervasyon bir kanal rezervasyonundan oluşturulduysa
        # ChannelReservation'ı güncelle
        channel_reservations = ChannelReservation.objects.filter(
            system_reservation=instance
        )
        
        for channel_res in channel_reservations:
            # Rezervasyon durumunu güncelle
            if instance.status == 'checked_in':
                channel_res.status = 'checked_in'
            elif instance.status == 'checked_out':
                channel_res.status = 'checked_out'
            elif instance.is_cancelled:
                channel_res.status = 'cancelled'
                channel_res.cancellation_date = timezone.now()
            
            channel_res.save()
            
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f"Rezervasyon kanal senkronizasyonu hatası: {str(e)}", exc_info=True)


# ==================== FINANCE/ACCOUNTING MODÜLÜ ENTEGRASYONU ====================

@receiver(post_save, sender='channel_management.ChannelCommission')
def create_finance_transaction(sender, instance, created, **kwargs):
    """Komisyon ödendiğinde finance modülüne işlem oluştur"""
    if created and instance.is_paid:
        try:
            # Finance modülü entegrasyonu
            # from apps.tenant_apps.finance.models import CashTransaction
            # CashTransaction.objects.create(...)
            pass
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Komisyon finance entegrasyonu hatası: {str(e)}", exc_info=True)

