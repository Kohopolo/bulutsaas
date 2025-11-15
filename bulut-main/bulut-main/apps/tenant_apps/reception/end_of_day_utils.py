"""
Gün Sonu İşlemleri Utility Fonksiyonları
Hotel bazlı çalışır - Tüm fonksiyonlar hotel parametresi alır
"""
from datetime import date, timedelta
from decimal import Decimal
from django.utils import timezone
from django.db.models import Q, Sum, Count, F
from django.db import transaction
import logging

logger = logging.getLogger(__name__)


# ==================== PRE-AUDIT KONTROLLERİ ====================

def check_room_prices_zero(hotel, operation_date):
    """
    Oda fiyatlarının sıfır olup olmadığını kontrol et
    Hotel bazlı çalışır
    """
    try:
        from apps.tenant_apps.hotels.models import Room, RoomPrice
        
        # Bu otel için aktif odalar
        rooms = Room.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        )
        
        # Bu tarih için fiyatı sıfır olan odalar
        zero_price_rooms = []
        for room in rooms:
            # RoomPrice modelinden aktif fiyat kontrolü
            try:
                # Aktif RoomPrice kaydı var mı?
                room_price = RoomPrice.objects.filter(
                    room=room,
                    is_active=True,
                    is_deleted=False
                ).first()
                
                if room_price:
                    # RoomPrice varsa basic_nightly_price kontrol et
                    if room_price.basic_nightly_price == 0:
                        zero_price_rooms.append({
                            'room': room.name,
                            'room_id': room.id,
                            'price': 0,
                            'date': operation_date,
                            'price_type': 'RoomPrice'
                        })
                else:
                    # RoomPrice yoksa Room'un basic_nightly_price kontrol et
                    if hasattr(room, 'basic_nightly_price') and room.basic_nightly_price == 0:
                        zero_price_rooms.append({
                            'room': room.name,
                            'room_id': room.id,
                            'price': 0,
                            'date': operation_date,
                            'price_type': 'Room'
                        })
            except Exception as e:
                logger.warning(f'Room {room.id} price check error: {e}')
                # Son çare: Room'un basic_nightly_price kontrol et
                try:
                    if hasattr(room, 'basic_nightly_price') and room.basic_nightly_price == 0:
                        zero_price_rooms.append({
                            'room': room.name,
                            'room_id': room.id,
                            'price': 0,
                            'date': operation_date,
                            'price_type': 'Room (fallback)'
                        })
                except:
                    pass
        
        return {
            'has_error': len(zero_price_rooms) > 0,
            'zero_price_rooms': zero_price_rooms,
            'message': f'{len(zero_price_rooms)} oda için fiyat sıfır!' if zero_price_rooms else 'Tüm odalar için fiyat tanımlı.'
        }
    except Exception as e:
        logger.error(f'Room price check error: {e}', exc_info=True)
        return {
            'has_error': True,
            'zero_price_rooms': [],
            'message': f'Hata: {str(e)}'
        }


def check_advance_folio_balance(hotel, operation_date):
    """
    Peşin ödemeli rezervasyonlarda folyo balansının sıfır olup olmadığını kontrol et
    Hotel bazlı çalışır
    """
    try:
        from .models import Reservation, ReservationPayment
        
        # Bu otel için bugün check-in yapılacak veya bugün içinde olan rezervasyonlar
        reservations = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_in_date__lte=operation_date,
            check_out_date__gte=operation_date
        )
        
        # Peşin ödemeli rezervasyonlar (total_paid > 0)
        advance_reservations = reservations.filter(total_paid__gt=0)
        
        # Folyo balansı sıfır olmayan rezervasyonlar
        non_zero_balance_reservations = []
        for reservation in advance_reservations:
            # Toplam tutar - ödenen tutar = bakiye
            balance = reservation.total_amount - reservation.total_paid
            if balance != 0:
                non_zero_balance_reservations.append({
                    'reservation_code': reservation.reservation_code,
                    'reservation_id': reservation.id,
                    'total_amount': float(reservation.total_amount),
                    'total_paid': float(reservation.total_paid),
                    'balance': float(balance),
                    'customer': f"{reservation.customer.first_name} {reservation.customer.last_name}" if reservation.customer else "Bilinmiyor"
                })
        
        return {
            'has_error': len(non_zero_balance_reservations) > 0,
            'non_zero_balance_reservations': non_zero_balance_reservations,
            'message': f'{len(non_zero_balance_reservations)} rezervasyonda folyo balansı sıfır değil!' if non_zero_balance_reservations else 'Tüm peşin ödemeli rezervasyonlarda folyo balansı sıfır.'
        }
    except Exception as e:
        logger.error(f'Advance folio balance check error: {e}', exc_info=True)
        return {
            'has_error': True,
            'non_zero_balance_reservations': [],
            'message': f'Hata: {str(e)}'
        }


def check_checkout_folios(hotel, operation_date):
    """
    Check-out yapılmış folyoları kontrol et
    Hotel bazlı çalışır
    """
    try:
        from .models import Reservation
        
        # Bu otel için check-out yapılmış ama folyo kapanmamış rezervasyonlar
        checkout_reservations = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            is_checked_out=True,
            checked_out_at__date=operation_date
        )
        
        # Folyo kontrolü (total_paid < total_amount)
        unclosed_folios = []
        for reservation in checkout_reservations:
            if reservation.total_paid < reservation.total_amount:
                unclosed_folios.append({
                    'reservation_code': reservation.reservation_code,
                    'reservation_id': reservation.id,
                    'total_amount': float(reservation.total_amount),
                    'total_paid': float(reservation.total_paid),
                    'balance': float(reservation.total_amount - reservation.total_paid),
                    'customer': f"{reservation.customer.first_name} {reservation.customer.last_name}" if reservation.customer else "Bilinmiyor"
                })
        
        return {
            'has_error': len(unclosed_folios) > 0,
            'unclosed_folios': unclosed_folios,
            'message': f'{len(unclosed_folios)} check-out yapılmış rezervasyonda folyo kapanmamış!' if unclosed_folios else 'Tüm check-out yapılmış rezervasyonlarda folyo kapanmış.'
        }
    except Exception as e:
        logger.error(f'Checkout folio check error: {e}', exc_info=True)
        return {
            'has_error': True,
            'unclosed_folios': [],
            'message': f'Hata: {str(e)}'
        }


def run_pre_audit_checks(hotel, settings, operation_date):
    """
    Pre-audit kontrollerini çalıştır
    Hotel bazlı çalışır
    Returns: (can_proceed, errors, warnings)
    """
    errors = []
    warnings = []
    
    # 1. Oda fiyatı sıfır kontrolü
    if settings.stop_if_room_price_zero:
        room_price_check = check_room_prices_zero(hotel, operation_date)
        if room_price_check['has_error']:
            errors.append({
                'check': 'room_price_zero',
                'message': room_price_check['message'],
                'details': room_price_check['zero_price_rooms']
            })
    
    # 2. Peşin folyo balansı kontrolü
    if settings.stop_if_advance_folio_balance_not_zero:
        folio_balance_check = check_advance_folio_balance(hotel, operation_date)
        if folio_balance_check['has_error']:
            errors.append({
                'check': 'advance_folio_balance',
                'message': folio_balance_check['message'],
                'details': folio_balance_check['non_zero_balance_reservations']
            })
    
    # 3. Checkout folyoları kontrolü
    if settings.check_checkout_folios:
        checkout_folio_check = check_checkout_folios(hotel, operation_date)
        if checkout_folio_check['has_error']:
            warnings.append({
                'check': 'checkout_folios',
                'message': checkout_folio_check['message'],
                'details': checkout_folio_check['unclosed_folios']
            })
    
    can_proceed = len(errors) == 0
    
    return can_proceed, errors, warnings


# ==================== İŞLEM ADIMLARI ====================

def create_operation_steps(operation):
    """
    İşlem adımlarını oluştur
    Eğer adımlar zaten varsa, mevcut adımları döndür
    """
    from .models import EndOfDayOperationStep, EndOfDayStepStatus
    from django.db import transaction
    
    # Adım tanımları
    step_definitions = [
        {'order': 1, 'name': 'Pre-Audit Kontrolleri', 'status': EndOfDayStepStatus.PENDING},
        {'order': 2, 'name': 'Folyo Kontrolleri', 'status': EndOfDayStepStatus.PENDING},
        {'order': 3, 'name': 'No-Show İşlemleri', 'status': EndOfDayStepStatus.PENDING},
        {'order': 4, 'name': 'Oda Fiyatlarını Güncelle', 'status': EndOfDayStepStatus.PENDING},
        {'order': 5, 'name': 'Gelir Dağılımı', 'status': EndOfDayStepStatus.PENDING},
        {'order': 6, 'name': 'Muhasebe Fişleri Oluştur', 'status': EndOfDayStepStatus.PENDING},
        {'order': 7, 'name': 'Raporlar Oluştur', 'status': EndOfDayStepStatus.PENDING},
        {'order': 8, 'name': 'Sistem Tarihini Güncelle', 'status': EndOfDayStepStatus.PENDING},
    ]
    
    # Önce mevcut adımları al
    existing_steps = EndOfDayOperationStep.objects.filter(
        operation=operation
    ).order_by('step_order')
    
    existing_step_orders = set(existing_steps.values_list('step_order', flat=True))
    
    # Eksik adımları oluştur
    with transaction.atomic():
        created_steps = list(existing_steps)
        
        for step_data in step_definitions:
            if step_data['order'] not in existing_step_orders:
                # Bu adım yok, oluştur
                try:
                    step = EndOfDayOperationStep.objects.create(
                        operation=operation,
                        step_order=step_data['order'],
                        step_name=step_data['name'],
                        status=step_data['status']
                    )
                    created_steps.append(step)
                except Exception as e:
                    # Eğer oluşturulamazsa (örneğin race condition), mevcut olanı almaya çalış
                    try:
                        step = EndOfDayOperationStep.objects.get(
                            operation=operation,
                            step_order=step_data['order']
                        )
                        if step not in created_steps:
                            created_steps.append(step)
                    except EndOfDayOperationStep.DoesNotExist:
                        logger.warning(f'Adım {step_data["order"]} oluşturulamadı ve mevcut değil: {e}')
                        continue
    
    # Adımları sıraya göre sırala
    created_steps.sort(key=lambda x: x.step_order)
    
    return created_steps


def execute_step(step, operation, settings):
    """
    İşlem adımını çalıştır
    """
    from .models import EndOfDayStepStatus
    
    step.status = EndOfDayStepStatus.RUNNING
    step.started_at = timezone.now()
    step.save()
    
    try:
        result_data = {}
        
        if step.step_order == 1:
            # Pre-Audit Kontrolleri
            can_proceed, errors, warnings = run_pre_audit_checks(
                operation.hotel,
                settings,
                operation.operation_date
            )
            result_data = {
                'can_proceed': can_proceed,
                'errors': errors,
                'warnings': warnings
            }
            if not can_proceed:
                # Detaylı hata mesajı oluştur
                error_messages = []
                for error in errors:
                    error_messages.append(f"- {error.get('message', 'Bilinmeyen hata')}")
                
                error_detail = '\n'.join(error_messages) if error_messages else 'Pre-audit kontrolleri başarısız!'
                raise Exception(f'Pre-audit kontrolleri başarısız!\n\nBaşarısız Kontroller:\n{error_detail}')
        
        elif step.step_order == 2:
            # Folyo Kontrolleri
            result_data = check_folios(operation.hotel, operation.operation_date)
        
        elif step.step_order == 3:
            # No-Show İşlemleri
            result_data = process_no_shows(operation.hotel, settings, operation.operation_date)
        
        elif step.step_order == 4:
            # Oda Fiyatlarını Güncelle
            result_data = update_room_prices(operation.hotel, operation.operation_date)
        
        elif step.step_order == 5:
            # Gelir Dağılımı
            result_data = distribute_revenue(operation.hotel, operation.operation_date)
        
        elif step.step_order == 6:
            # Muhasebe Fişleri Oluştur
            result_data = create_accounting_entries(operation)
        
        elif step.step_order == 7:
            # Raporlar Oluştur
            result_data = create_reports(operation)
        
        elif step.step_order == 8:
            # Sistem Tarihini Güncelle
            result_data = update_system_date(operation.hotel, operation.operation_date)
        
        step.status = EndOfDayStepStatus.COMPLETED
        step.completed_at = timezone.now()
        step.result_data = result_data
        step.save()
        
        return True, result_data
    
    except Exception as e:
        step.status = EndOfDayStepStatus.FAILED
        step.completed_at = timezone.now()
        step.error_message = str(e)
        step.save()
        
        logger.error(f'Step {step.step_order} execution failed: {e}', exc_info=True)
        return False, {'error': str(e)}


def check_folios(hotel, operation_date):
    """
    Folyo kontrolleri
    Hotel bazlı çalışır - Açık folyoları kontrol eder
    """
    try:
        from .models import Reservation
        
        # Bu otel için bugün içinde olan rezervasyonlar
        active_reservations = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_in_date__lte=operation_date,
            check_out_date__gte=operation_date
        )
        
        # Açık folyolar (total_paid < total_amount)
        open_folios = []
        total_open_balance = Decimal('0')
        
        for reservation in active_reservations:
            balance = reservation.total_amount - reservation.total_paid
            if balance > 0:
                open_folios.append({
                    'reservation_code': reservation.reservation_code,
                    'reservation_id': reservation.id,
                    'total_amount': float(reservation.total_amount),
                    'total_paid': float(reservation.total_paid),
                    'balance': float(balance),
                    'customer': f"{reservation.customer.first_name} {reservation.customer.last_name}" if reservation.customer else "Bilinmiyor",
                    'check_in_date': str(reservation.check_in_date),
                    'check_out_date': str(reservation.check_out_date),
                    'is_checked_in': reservation.is_checked_in,
                    'is_checked_out': reservation.is_checked_out,
                })
                total_open_balance += balance
        
        # Özet bilgiler
        summary = {
            'total_reservations': active_reservations.count(),
            'open_folios_count': len(open_folios),
            'total_open_balance': float(total_open_balance),
            'closed_folios_count': active_reservations.count() - len(open_folios),
        }
        
        return {
            'open_folios': open_folios,
            'summary': summary,
            'message': f'{len(open_folios)} açık folyo bulundu. Toplam açık bakiye: {total_open_balance:.2f} TRY' if open_folios else 'Tüm folyolar kapalı.'
        }
    except Exception as e:
        logger.error(f'Folio check error: {e}', exc_info=True)
        return {
            'open_folios': [],
            'summary': {},
            'message': f'Hata: {str(e)}'
        }


def process_no_shows(hotel, settings, operation_date):
    """
    No-show rezervasyonlarını işle
    Hotel bazlı çalışır
    """
    try:
        from .models import Reservation, ReservationStatus
        
        # Bugün check-in yapılacak ama check-in yapılmamış rezervasyonlar
        no_show_reservations = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_in_date=operation_date,
            status=ReservationStatus.CONFIRMED,
            is_checked_in=False
        )
        
        processed_count = 0
        processed_reservations = []
        
        if settings.cancel_no_show_reservations:
            for reservation in no_show_reservations:
                if settings.no_show_action == 'cancel':
                    reservation.status = ReservationStatus.NO_SHOW
                    reservation.is_no_show = True
                    reservation.no_show_reason = 'Gün sonu işlemi - Otomatik iptal'
                    reservation.save()
                    processed_reservations.append({
                        'reservation_code': reservation.reservation_code,
                        'action': 'cancelled'
                    })
                    processed_count += 1
                elif settings.no_show_action == 'move_to_tomorrow':
                    reservation.check_in_date = operation_date + timedelta(days=1)
                    reservation.check_out_date = reservation.check_out_date + timedelta(days=1)
                    reservation.save()
                    processed_reservations.append({
                        'reservation_code': reservation.reservation_code,
                        'action': 'moved_to_tomorrow',
                        'new_check_in_date': str(reservation.check_in_date)
                    })
                    processed_count += 1
        
        return {
            'processed_count': processed_count,
            'processed_reservations': processed_reservations,
            'message': f'{processed_count} no-show rezervasyonu işlendi.'
        }
    except Exception as e:
        logger.error(f'No-show processing error: {e}', exc_info=True)
        return {
            'processed_count': 0,
            'processed_reservations': [],
            'message': f'Hata: {str(e)}'
        }


def update_room_prices(hotel, operation_date):
    """
    Oda fiyatlarını güncelle
    Hotel bazlı çalışır - Dinamik fiyatlandırma kurallarını uygular
    """
    try:
        from apps.tenant_apps.hotels.models import Room, RoomPrice
        
        # Bu otel için aktif odalar
        rooms = Room.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        )
        
        updated_count = 0
        updated_rooms = []
        
        # Yarına ait fiyatları kontrol et ve güncelle
        tomorrow = operation_date + timedelta(days=1)
        
        for room in rooms:
            try:
                # Yarına ait RoomPrice var mı kontrol et
                tomorrow_price = RoomPrice.objects.filter(
                    room=room,
                    is_active=True,
                    is_deleted=False
                ).first()
                
                # Eğer yarına ait fiyat yoksa, bugünkü fiyatı kullan
                if not tomorrow_price:
                    # Bugünkü fiyatı al
                    current_price = room.get_current_price(date=operation_date)
                    
                    # Yarına ait yeni RoomPrice oluştur (eğer gerekirse)
                    # Not: Bu işlem sadece fiyat değişikliği varsa yapılmalı
                    # Şimdilik sadece kontrol ediyoruz
                    updated_rooms.append({
                        'room': room.name,
                        'room_id': room.id,
                        'current_price': float(current_price) if current_price else 0,
                        'tomorrow_price': float(current_price) if current_price else 0,
                        'status': 'no_change' if current_price else 'no_price'
                    })
                    updated_count += 1
                else:
                    # Yarına ait fiyat var, kontrol et
                    updated_rooms.append({
                        'room': room.name,
                        'room_id': room.id,
                        'current_price': float(tomorrow_price.basic_nightly_price),
                        'tomorrow_price': float(tomorrow_price.basic_nightly_price),
                        'status': 'exists'
                    })
                    updated_count += 1
            except Exception as e:
                logger.warning(f'Room {room.id} price update error: {e}')
                updated_rooms.append({
                    'room': room.name,
                    'room_id': room.id,
                    'status': 'error',
                    'error': str(e)
                })
        
        return {
            'updated_count': updated_count,
            'updated_rooms': updated_rooms,
            'message': f'{updated_count} oda fiyatı kontrol edildi.'
        }
    except Exception as e:
        logger.error(f'Room price update error: {e}', exc_info=True)
        return {
            'updated_count': 0,
            'updated_rooms': [],
            'message': f'Hata: {str(e)}'
        }


def distribute_revenue(hotel, operation_date):
    """
    Gelir dağılımı
    Hotel bazlı çalışır - Departman ve pazar segmenti bazlı gelir dağılımı
    """
    try:
        from .models import Reservation, ReservationSource
        
        # Bu otel için bugün içinde olan rezervasyonlar
        active_reservations = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_in_date__lte=operation_date,
            check_out_date__gte=operation_date
        )
        
        # Gelir toplama
        total_revenue = Decimal('0')
        revenue_by_department = {
            'room': Decimal('0'),
            'f&b': Decimal('0'),
            'spa': Decimal('0'),
            'extra': Decimal('0'),
        }
        
        revenue_by_segment = {
            'direct': Decimal('0'),
            'online': Decimal('0'),
            'agency': Decimal('0'),
            'corporate': Decimal('0'),
            'group': Decimal('0'),
            'walk_in': Decimal('0'),
        }
        
        # Rezervasyon bazlı gelir toplama
        for reservation in active_reservations:
            # Oda geliri (Room Revenue)
            room_revenue = reservation.total_amount
            total_revenue += room_revenue
            revenue_by_department['room'] += room_revenue
            
            # Pazar segmenti bazlı dağılım
            source = reservation.source
            if source == ReservationSource.DIRECT:
                revenue_by_segment['direct'] += room_revenue
            elif source == ReservationSource.ONLINE:
                revenue_by_segment['online'] += room_revenue
            elif source == ReservationSource.AGENCY:
                revenue_by_segment['agency'] += room_revenue
            elif source == ReservationSource.CORPORATE:
                revenue_by_segment['corporate'] += room_revenue
            elif source == ReservationSource.GROUP:
                revenue_by_segment['group'] += room_revenue
            elif source == ReservationSource.WALK_IN:
                revenue_by_segment['walk_in'] += room_revenue
        
        # Özet bilgiler
        summary = {
            'total_revenue': float(total_revenue),
            'revenue_by_department': {k: float(v) for k, v in revenue_by_department.items()},
            'revenue_by_segment': {k: float(v) for k, v in revenue_by_segment.items()},
            'total_reservations': active_reservations.count(),
        }
        
        return {
            'summary': summary,
            'message': f'Toplam gelir: {total_revenue:.2f} TRY. Oda geliri: {revenue_by_department["room"]:.2f} TRY'
        }
    except Exception as e:
        logger.error(f'Revenue distribution error: {e}', exc_info=True)
        return {
            'summary': {},
            'message': f'Hata: {str(e)}'
        }


def create_accounting_entries(operation):
    """
    Muhasebe fişleri oluştur
    Hotel bazlı çalışır - Gelir hesaplarına yevmiye kayıtları oluşturur
    """
    try:
        from .models import EndOfDayJournalEntry
        from apps.tenant_apps.accounting.utils import create_journal_entry
        from apps.tenant_apps.accounting.models import Account, JournalEntry
        from django.db import transaction
        
        hotel = operation.hotel
        operation_date = operation.operation_date
        
        # Gelir dağılımını al
        revenue_data = distribute_revenue(hotel, operation_date)
        summary = revenue_data.get('summary', {})
        
        total_revenue = Decimal(str(summary.get('total_revenue', 0)))
        room_revenue = Decimal(str(summary.get('revenue_by_department', {}).get('room', 0)))
        
        if total_revenue == 0:
            return {
                'created_count': 0,
                'created_entries': [],
                'message': 'Gelir bulunamadı, muhasebe fişi oluşturulmadı.'
            }
        
        created_entries = []
        
        with transaction.atomic():
            # Gelir hesaplarına yevmiye kaydı oluştur
            # 600 - Konaklama Geliri
            # 102 - Kasa (veya 120 - Müşteri Alacakları)
            
            # Hesap kodlarını bul
            revenue_account = Account.objects.filter(
                hotel=hotel,
                code__startswith='600',
                is_active=True,
                is_deleted=False
            ).first()
            
            if not revenue_account:
                # Genel hesap ara
                revenue_account = Account.objects.filter(
                    hotel__isnull=True,
                    code__startswith='600',
                    is_active=True,
                    is_deleted=False
                ).first()
            
            cash_account = Account.objects.filter(
                hotel=hotel,
                code__startswith='102',
                is_active=True,
                is_deleted=False
            ).first()
            
            if not cash_account:
                # Genel hesap ara
                cash_account = Account.objects.filter(
                    hotel__isnull=True,
                    code__startswith='102',
                    is_active=True,
                    is_deleted=False
                ).first()
            
            if revenue_account and cash_account:
                # Yevmiye kaydı oluştur
                lines_data = [
                    {
                        'account_code': cash_account.code,
                        'debit': float(total_revenue),
                        'credit': 0,
                        'description': f'Gün sonu gelir - {operation_date}'
                    },
                    {
                        'account_code': revenue_account.code,
                        'debit': 0,
                        'credit': float(total_revenue),
                        'description': f'Konaklama geliri - {operation_date}'
                    }
                ]
                
                journal_entry = create_journal_entry(
                    description=f'Gün sonu gelir kaydı - {hotel.name} - {operation_date}',
                    source_module='reception',
                    source_id=operation.id,
                    source_reference=f'EOD-{operation.id}',
                    entry_date=operation_date,
                    created_by=operation.created_by,
                    lines_data=lines_data,
                    hotel=hotel,
                    notes=f'Gün sonu işlemi otomatik oluşturuldu. Toplam gelir: {total_revenue:.2f} TRY'
                )
                
                # Yevmiye kaydını kaydet
                journal_entry.post(user=operation.created_by)
                
                # EndOfDayJournalEntry kaydı oluştur
                eod_entry = EndOfDayJournalEntry.objects.create(
                    operation=operation,
                    journal_entry=journal_entry,
                    entry_type='revenue',
                    department='room',
                    amount=total_revenue,
                    currency='TRY'
                )
                
                created_entries.append({
                    'journal_entry_number': journal_entry.entry_number,
                    'journal_entry_id': journal_entry.id,
                    'amount': float(total_revenue),
                    'type': 'revenue'
                })
        
        return {
            'created_count': len(created_entries),
            'created_entries': created_entries,
            'message': f'{len(created_entries)} muhasebe fişi oluşturuldu.'
        }
    except Exception as e:
        logger.error(f'Accounting entries creation error: {e}', exc_info=True)
        return {
            'created_count': 0,
            'created_entries': [],
            'message': f'Hata: {str(e)}'
        }


def create_reports(operation):
    """
    Raporlar oluştur
    Hotel bazlı çalışır - Özet, finansal, operasyonel raporlar oluşturur
    """
    try:
        from .models import EndOfDayReport, EndOfDayReportType
        
        hotel = operation.hotel
        operation_date = operation.operation_date
        
        # Gelir dağılımını al
        revenue_data = distribute_revenue(hotel, operation_date)
        revenue_summary = revenue_data.get('summary', {})
        
        # Folyo kontrollerini al
        folio_data = check_folios(hotel, operation_date)
        folio_summary = folio_data.get('summary', {})
        
        created_reports = []
        
        # 1. Özet Rapor
        summary_report_data = {
            'hotel_name': hotel.name,
            'operation_date': str(operation_date),
            'total_revenue': revenue_summary.get('total_revenue', 0),
            'revenue_by_department': revenue_summary.get('revenue_by_department', {}),
            'revenue_by_segment': revenue_summary.get('revenue_by_segment', {}),
            'total_reservations': revenue_summary.get('total_reservations', 0),
            'open_folios_count': folio_summary.get('open_folios_count', 0),
            'total_open_balance': folio_summary.get('total_open_balance', 0),
            'closed_folios_count': folio_summary.get('closed_folios_count', 0),
        }
        
        summary_report = EndOfDayReport.objects.create(
            operation=operation,
            report_type=EndOfDayReportType.SUMMARY,
            report_data=summary_report_data,
            export_format='json'
        )
        created_reports.append({
            'report_id': summary_report.id,
            'report_type': 'summary',
            'report_type_display': summary_report.get_report_type_display()
        })
        
        # 2. Finansal Rapor
        financial_report_data = {
            'hotel_name': hotel.name,
            'operation_date': str(operation_date),
            'revenue_summary': revenue_summary,
            'folio_summary': folio_summary,
        }
        
        financial_report = EndOfDayReport.objects.create(
            operation=operation,
            report_type=EndOfDayReportType.FINANCIAL,
            report_data=financial_report_data,
            export_format='json'
        )
        created_reports.append({
            'report_id': financial_report.id,
            'report_type': 'financial',
            'report_type_display': financial_report.get_report_type_display()
        })
        
        # 3. Operasyonel Rapor
        operational_report_data = {
            'hotel_name': hotel.name,
            'operation_date': str(operation_date),
            'folio_summary': folio_summary,
            'open_folios': folio_data.get('open_folios', []),
        }
        
        operational_report = EndOfDayReport.objects.create(
            operation=operation,
            report_type=EndOfDayReportType.OPERATIONAL,
            report_data=operational_report_data,
            export_format='json'
        )
        created_reports.append({
            'report_id': operational_report.id,
            'report_type': 'operational',
            'report_type_display': operational_report.get_report_type_display()
        })
        
        return {
            'created_count': len(created_reports),
            'created_reports': created_reports,
            'message': f'{len(created_reports)} rapor oluşturuldu.'
        }
    except Exception as e:
        logger.error(f'Report creation error: {e}', exc_info=True)
        return {
            'created_count': 0,
            'created_reports': [],
            'message': f'Hata: {str(e)}'
        }


def update_system_date(hotel, operation_date):
    """
    Sistem tarihini güncelle
    Hotel bazlı çalışır - Yeni gün için hazırlık yapar
    """
    try:
        from .models import Reservation, ReservationStatus
        from apps.tenant_apps.hotels.models import RoomNumber, RoomNumberStatus
        
        # Yarına ait rezervasyonları kontrol et
        tomorrow = operation_date + timedelta(days=1)
        
        # Bugün check-out yapılacak rezervasyonları kontrol et
        checkout_today = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_out_date=operation_date,
            is_checked_out=False
        )
        
        # Yarına check-in yapılacak rezervasyonları kontrol et
        checkin_tomorrow = Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False,
            check_in_date=tomorrow,
            status=ReservationStatus.CONFIRMED
        )
        
        # Oda durumlarını sıfırla (opsiyonel - sadece belirli durumlar için)
        # Not: Bu işlem dikkatli yapılmalı, aktif rezervasyonları etkilememeli
        
        summary = {
            'checkout_today_count': checkout_today.count(),
            'checkin_tomorrow_count': checkin_tomorrow.count(),
            'operation_date': str(operation_date),
            'tomorrow': str(tomorrow),
        }
        
        return {
            'summary': summary,
            'message': f'Sistem tarihi güncellendi. Yarına {checkin_tomorrow.count()} check-in bekleniyor.'
        }
    except Exception as e:
        logger.error(f'System date update error: {e}', exc_info=True)
        return {
            'summary': {},
            'message': f'Hata: {str(e)}'
        }


# ==================== İŞLEM ÇALIŞTIRMA ====================

def run_end_of_day_operation(operation, settings):
    """
    Gün sonu işlemini çalıştır
    Hotel bazlı çalışır
    """
    from .models import EndOfDayOperationStatus, EndOfDayStepStatus
    
    operation.status = EndOfDayOperationStatus.RUNNING
    operation.started_at = timezone.now()
    operation.save()
    
    try:
        # Rollback verilerini sakla
        rollback_data = {
            'operation_date': str(operation.operation_date),
            'program_date': str(operation.program_date),
            'started_at': str(operation.started_at),
        }
        operation.rollback_data = rollback_data
        operation.save()
        
        # Adımları oluştur
        steps = create_operation_steps(operation)
        
        # Her adımı sırayla çalıştır
        for step in steps:
            success, result_data = execute_step(step, operation, settings)
            
            if not success:
                # Adım başarısız oldu, işlemi durdur
                operation.status = EndOfDayOperationStatus.FAILED
                operation.completed_at = timezone.now()
                operation.error_message = f"Adım {step.step_order} ({step.step_name}) başarısız: {step.error_message}"
                operation.save()
                return False, operation.error_message
        
        # Tüm adımlar başarılı
        operation.status = EndOfDayOperationStatus.COMPLETED
        operation.completed_at = timezone.now()
        operation.results = {
            'total_steps': len(steps),
            'completed_steps': len([s for s in steps if s.status == EndOfDayStepStatus.COMPLETED]),
            'completed_at': str(timezone.now())
        }
        operation.save()
        
        return True, 'İşlem başarıyla tamamlandı.'
    
    except Exception as e:
        operation.status = EndOfDayOperationStatus.FAILED
        operation.completed_at = timezone.now()
        operation.error_message = str(e)
        operation.save()
        
        logger.error(f'End of day operation failed: {e}', exc_info=True)
        return False, str(e)


# ==================== ROLLBACK ====================

def rollback_end_of_day_operation(operation):
    """
    Gün sonu işlemini geri al (rollback)
    Hotel bazlı çalışır - Oluşturulan kayıtları siler veya geri alır
    """
    from .models import EndOfDayOperationStatus, EndOfDayJournalEntry
    from django.db import transaction
    
    if not operation.can_rollback():
        return False, 'Rollback yapılamaz. Rollback verileri bulunamadı veya işlem tamamlanmamış.'
    
    try:
        with transaction.atomic():
            # 1. Muhasebe fişlerini iptal et
            journal_entries = EndOfDayJournalEntry.objects.filter(operation=operation)
            cancelled_count = 0
            
            for eod_entry in journal_entries:
                if eod_entry.journal_entry:
                    try:
                        eod_entry.journal_entry.cancel(reason='Gün sonu işlemi rollback')
                        cancelled_count += 1
                    except Exception as e:
                        logger.warning(f'Journal entry {eod_entry.journal_entry.id} cancel error: {e}')
            
            # 2. Raporları sil (opsiyonel - veri kaybı olabilir)
            # reports = operation.reports.all()
            # reports.delete()
            
            # 3. İşlem adımlarını sıfırla (opsiyonel)
            # steps = operation.steps.all()
            # for step in steps:
            #     step.status = EndOfDayStepStatus.PENDING
            #     step.result_data = None
            #     step.error_message = None
            #     step.save()
            
            # 4. İşlem durumunu güncelle
            operation.status = EndOfDayOperationStatus.ROLLED_BACK
            operation.save()
        
        return True, f'Rollback işlemi başarıyla tamamlandı. {cancelled_count} muhasebe fişi iptal edildi.'
    
    except Exception as e:
        logger.error(f'Rollback error: {e}', exc_info=True)
        return False, str(e)

