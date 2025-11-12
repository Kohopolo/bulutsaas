"""
Resepsiyon Modülü Signals
Finance, Accounting ve Refunds modülleriyle entegrasyon
"""
from django.db.models.signals import post_save, pre_save, post_delete
from django.dispatch import receiver
from django.utils import timezone
from decimal import Decimal
from .models import Reservation, ReservationUpdate, CheckIn, CheckOut, KeyCard


@receiver(post_save, sender=Reservation)
def create_reservation_update_log(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda veya güncellendiğinde audit log oluştur
    """
    if created:
        # Yeni rezervasyon oluşturuldu
        ReservationUpdate.objects.create(
            reservation=instance,
            updated_by=instance.created_by,
            update_type='created',
            notes='Rezervasyon oluşturuldu'
        )
    else:
        # Rezervasyon güncellendi - değişiklikleri kontrol et
        try:
            old_instance = Reservation.objects.get(pk=instance.pk)
            
            # Durum değişikliği
            if old_instance.status != instance.status:
                ReservationUpdate.objects.create(
                    reservation=instance,
                    updated_by=instance.created_by,
                    update_type='updated',
                    field_name='status',
                    old_value=old_instance.get_status_display(),
                    new_value=instance.get_status_display(),
                    notes=f'Durum değiştirildi: {old_instance.get_status_display()} → {instance.get_status_display()}'
                )
            
            # İptal edildi
            if old_instance.status != 'cancelled' and instance.status == 'cancelled':
                ReservationUpdate.objects.create(
                    reservation=instance,
                    updated_by=instance.created_by,
                    update_type='cancelled',
                    notes='Rezervasyon iptal edildi'
                )
                
                # Refunds modülüne iade talebi oluştur (eğer ödeme yapıldıysa)
                if instance.get_total_paid() > 0:
                    try:
                        from apps.tenant_apps.refunds.utils import create_refund_request
                        
                        create_refund_request(
                            source_module='reception',
                            source_id=instance.pk,
                            source_reference=f"Rezervasyon: {instance.reservation_code}",
                            customer_name=f"{instance.customer_first_name} {instance.customer_last_name}",
                            customer_email=instance.customer_email,
                            customer_phone=instance.customer_phone,
                            original_amount=instance.total_amount,
                            original_payment_method='',
                            original_payment_date=instance.created_at.date() if instance.created_at else timezone.now().date(),
                            reason=f'Rezervasyon iptali - {instance.hotel.name}',
                            created_by=instance.created_by,
                        )
                    except Exception as e:
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.warning(f'Refunds modülü entegrasyonu başarısız: {str(e)}')
            
            # Arşivlendi
            if not old_instance.is_deleted and instance.is_deleted:
                ReservationUpdate.objects.create(
                    reservation=instance,
                    updated_by=instance.archived_by,
                    update_type='archived',
                    notes=f'Arşivlendi: {instance.archive_reason}'
                )
        
        except Reservation.DoesNotExist:
            pass


@receiver(post_save, sender=CheckIn)
def create_checkin_update_log(sender, instance, created, **kwargs):
    """
    Check-in yapıldığında rezervasyon güncelleme logu oluştur
    """
    if created:
        ReservationUpdate.objects.create(
            reservation=instance.reservation,
            updated_by=instance.checked_in_by,
            update_type='checked_in',
            notes=f'Check-in yapıldı - {instance.check_in_datetime}'
        )
        
        # Rezervasyon durumunu güncelle
        instance.reservation.status = 'checked_in'
        instance.reservation.checked_in_at = instance.check_in_datetime
        instance.reservation.checked_in_by = instance.checked_in_by
        instance.reservation.save(update_fields=['status', 'checked_in_at', 'checked_in_by'])


@receiver(post_save, sender=CheckOut)
def create_checkout_update_log(sender, instance, created, **kwargs):
    """
    Check-out yapıldığında rezervasyon güncelleme logu oluştur
    """
    if created:
        ReservationUpdate.objects.create(
            reservation=instance.reservation,
            updated_by=instance.checked_out_by,
            update_type='checked_out',
            notes=f'Check-out yapıldı - {instance.check_out_datetime}'
        )
        
        # Rezervasyon durumunu güncelle
        instance.reservation.status = 'checked_out'
        instance.reservation.checked_out_at = instance.check_out_datetime
        instance.reservation.checked_out_by = instance.checked_out_by
        instance.reservation.is_early_checkout = instance.is_early_checkout
        instance.reservation.is_late_checkout = instance.is_late_checkout
        instance.reservation.save(update_fields=['status', 'checked_out_at', 'checked_out_by', 'is_early_checkout', 'is_late_checkout'])


@receiver(post_save, sender=KeyCard)
def deactivate_keycard_on_checkout(sender, instance, created, **kwargs):
    """
    Check-out yapıldığında anahtar kartını iptal et
    """
    if not created and not instance.is_active:
        # Anahtar kartı iptal edildi
        if instance.reservation:
            ReservationUpdate.objects.create(
                reservation=instance.reservation,
                updated_by=None,  # Sistem tarafından
                update_type='keycard_deactivated',
                notes=f'Anahtar kartı iptal edildi: {instance.card_number}'
            )


@receiver(pre_save, sender=Reservation)
def sync_payment_to_finance_and_accounting(sender, instance, **kwargs):
    """
    Rezervasyon ödemesi yapıldığında Finance ve Accounting modüllerine kayıt oluştur
    total_paid değiştiğinde tetiklenir
    """
    if not instance.pk:
        # Yeni rezervasyon, henüz kaydedilmemiş
        return
    
    try:
        old_instance = Reservation.objects.get(pk=instance.pk)
        old_total_paid = old_instance.total_paid or Decimal('0')
        new_total_paid = instance.total_paid or Decimal('0')
        
        # Ödeme tutarı değişti mi?
        payment_difference = new_total_paid - old_total_paid
        
        if payment_difference > 0:
            # Yeni ödeme yapıldı
            try:
                from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
                from apps.tenant_apps.accounting.utils import create_payment
                
                # Varsayılan kasa hesabını bul
                account = get_default_cash_account(currency=instance.currency)
                if not account:
                    # Varsayılan hesap yoksa ilk aktif hesabı kullan
                    from apps.tenant_apps.finance.models import CashAccount
                    account = CashAccount.objects.filter(
                        is_active=True,
                        is_deleted=False,
                        currency=instance.currency
                    ).first()
                
                if account:
                    # Finance: Kasa işlemi oluştur
                    create_cash_transaction(
                        account_id=account.pk,
                        transaction_type='income',
                        amount=payment_difference,
                        source_module='reception',
                        source_id=instance.pk,
                        source_reference=f"Rezervasyon: {instance.reservation_code}",
                        description=f"Otel rezervasyon ödemesi - {instance.hotel.name}",
                        payment_method='cash',  # Varsayılan, formdan alınabilir
                        currency=instance.currency,
                        created_by=instance.created_by,
                        status='completed',
                    )
                
                # Accounting: Ödeme kaydı oluştur
                # Önce fatura var mı kontrol et
                from apps.tenant_apps.accounting.models import Invoice
                invoice = Invoice.objects.filter(
                    source_module='reception',
                    source_id=instance.pk,
                    is_deleted=False
                ).first()
                
                if invoice:
                    # Mevcut faturaya ödeme ekle
                    create_payment(
                        amount=payment_difference,
                        invoice_id=invoice.pk,
                        source_module='reception',
                        source_id=instance.pk,
                        source_reference=f"Rezervasyon: {instance.reservation_code}",
                        currency=instance.currency,
                        payment_method='cash',
                        cash_account_id=account.pk if account else None,
                        created_by=instance.created_by,
                        auto_complete=True,
                        description=f"Rezervasyon ödemesi - {instance.reservation_code}",
                    )
                else:
                    # Fatura yoksa sadece ödeme kaydı oluştur (fatura sonra oluşturulabilir)
                    create_payment(
                        amount=payment_difference,
                        source_module='reception',
                        source_id=instance.pk,
                        source_reference=f"Rezervasyon: {instance.reservation_code}",
                        currency=instance.currency,
                        payment_method='cash',
                        cash_account_id=account.pk if account else None,
                        created_by=instance.created_by,
                        auto_complete=True,
                        description=f"Rezervasyon ödemesi - {instance.reservation_code}",
                    )
                    
            except Exception as e:
                # Finance/Accounting modülü yoksa veya hata oluşursa sessizce geç
                import logging
                logger = logging.getLogger(__name__)
                logger.warning(f'Finance/Accounting modülü entegrasyonu başarısız: {str(e)}')
        
        elif payment_difference < 0:
            # Ödeme iptal edildi veya geri alındı (iade)
            # Bu durumda Refunds modülüne yönlendirilebilir
            pass
            
    except Reservation.DoesNotExist:
        pass


@receiver(post_save, sender=Reservation)
def create_accounting_invoice_on_reservation(sender, instance, created, **kwargs):
    """
    Rezervasyon oluşturulduğunda Accounting modülüne fatura oluştur
    """
    if created and instance.total_amount > 0:
        try:
            # Daha önce fatura oluşturulmuş mu kontrol et
            from apps.tenant_apps.accounting.models import Invoice
            existing_invoice = Invoice.objects.filter(
                source_module='reception',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if not existing_invoice:
                from apps.tenant_apps.accounting.utils import create_invoice
                
                # Fatura satırları
                lines_data = [
                    {
                        'item_name': f"Otel Rezervasyonu - {instance.room.name}",
                        'item_code': instance.reservation_code,
                        'quantity': instance.nights or 1,
                        'unit_price': instance.base_price or instance.total_amount,
                        'line_total': instance.total_amount,
                        'description': f"{instance.adult_count} yetişkin, {instance.child_count} çocuk - {instance.nights} gece",
                    }
                ]
                
                # Ekstra hizmetler varsa ekle
                if instance.extra_services_total and instance.extra_services_total > 0:
                    lines_data.append({
                        'item_name': 'Ekstra Hizmetler',
                        'item_code': 'EXTRA',
                        'quantity': 1,
                        'unit_price': instance.extra_services_total,
                        'line_total': instance.extra_services_total,
                        'description': 'Ekstra hizmetler',
                    })
                
                # Fatura oluştur
                create_invoice(
                    invoice_type='sales',
                    customer_name=f"{instance.customer_first_name} {instance.customer_last_name}",
                    total_amount=instance.total_amount,
                    source_module='reception',
                    source_id=instance.pk,
                    source_reference=f"Rezervasyon: {instance.reservation_code}",
                    invoice_date=timezone.now().date(),
                    currency=instance.currency,
                    created_by=instance.created_by,
                    lines_data=lines_data,
                    customer_email=instance.customer_email,
                    customer_phone=instance.customer_phone,
                    customer_address=instance.customer_address,
                    description=f"Otel rezervasyon faturası - {instance.hotel.name}",
                    status='draft',  # Taslak olarak oluştur, ödeme yapıldığında gönderilebilir
                )
        except Exception as e:
            # Accounting modülü yoksa veya hata oluşursa sessizce geç
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')

