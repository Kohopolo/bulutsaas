"""
Resepsiyon Modülü Views
Profesyonel otel resepsiyon yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.db.models import Q, Count, Sum, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal
from datetime import datetime, timedelta
import json

from .models import (
    Reservation, ReservationUpdate, RoomChange,
    CheckIn, CheckOut, KeyCard,
    ReceptionSession, ReceptionActivity, ReceptionSettings, QuickAction
)
from .forms import (
    ReservationForm, CheckInForm, CheckOutForm,
    KeyCardForm, ReceptionSettingsForm, QuickActionForm
)
from .decorators import require_reception_permission, check_reservation_limit
from .utils import (
    calculate_nights, is_early_checkout, is_late_checkout,
    calculate_early_checkout_fee, calculate_late_checkout_fee,
    get_room_availability, generate_reservation_code
)
from apps.tenant_apps.core.models import TenantUser, Customer
from apps.tenant_apps.hotels.models import Hotel, Room, RoomNumber, RoomNumberStatus
from apps.tenant_apps.reception.models import ReservationUpdate
from django.core.paginator import Paginator


# ==================== TAKVIM BAZLI ODA DURUMU ====================

@login_required
@require_reception_permission('view')
def room_calendar(request):
    """
    Takvim Bazlı Oda Durum Görünümü
    Gün gün oda durumlarını gösterir, her güne tıklandığında o günün oda durumlarını listeler
    """
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    # Tarih parametresi (varsayılan: bugün)
    selected_date_str = request.GET.get('date', None)
    if selected_date_str:
        try:
            selected_date = datetime.strptime(selected_date_str, '%Y-%m-%d').date()
        except ValueError:
            selected_date = timezone.now().date()
    else:
        selected_date = timezone.now().date()
    
    # Takvim için ay başlangıcı ve bitişi
    month_start = selected_date.replace(day=1)
    if month_start.month == 12:
        month_end = month_start.replace(year=month_start.year + 1, month=1) - timedelta(days=1)
    else:
        month_end = month_start.replace(month=month_start.month + 1) - timedelta(days=1)
    
    # Takvim haftasının başlangıcı (Pazartesi)
    calendar_start = month_start - timedelta(days=month_start.weekday())
    # Takvim haftasının bitişi (Pazar)
    calendar_end = month_end + timedelta(days=(6 - month_end.weekday()))
    
    # Tüm oda numaraları
    room_numbers = RoomNumber.objects.filter(
        hotel=hotel,
        is_active=True,
        is_deleted=False
    ).select_related('room', 'floor', 'block').order_by('floor__floor_number', 'number')
    
    # Seçili tarih için oda durumları
    selected_date_rooms = []
    for room_number in room_numbers:
        # Bu tarihte rezervasyon var mı?
        reservation = Reservation.objects.filter(
            hotel=hotel,
            room_number=room_number,
            check_in_date__lte=selected_date,
            check_out_date__gt=selected_date,
            status__in=['confirmed', 'checked_in'],
            is_deleted=False
        ).first()
        
        room_data = {
            'room_number': room_number,
            'is_available': not reservation and room_number.status == 'available',
            'reservation': reservation,
            'status': room_number.status,
        }
        selected_date_rooms.append(room_data)
    
    # Takvim günleri için oda durum özeti
    calendar_days = []
    current_date = calendar_start
    while current_date <= calendar_end:
        # Bu gün için rezervasyon sayısı
        reservations_count = Reservation.objects.filter(
            hotel=hotel,
            check_in_date__lte=current_date,
            check_out_date__gt=current_date,
            status__in=['confirmed', 'checked_in'],
            is_deleted=False
        ).exclude(room_number=None).count()
        
        # Müsait oda sayısı
        total_rooms = room_numbers.count()
        available_count = total_rooms - reservations_count
        
        calendar_days.append({
            'date': current_date,
            'is_current_month': month_start <= current_date <= month_end,
            'is_today': current_date == timezone.now().date(),
            'is_selected': current_date == selected_date,
            'reservations_count': reservations_count,
            'available_count': available_count,
            'total_rooms': total_rooms,
        })
        current_date += timedelta(days=1)
    
    # Müsait oda sayısı
    available_rooms_count = sum(1 for room_data in selected_date_rooms if room_data['is_available'])
    
    context = {
        'hotel': hotel,
        'selected_date': selected_date,
        'month_start': month_start,
        'month_end': month_end,
        'calendar_start': calendar_start,
        'calendar_end': calendar_end,
        'calendar_days': calendar_days,
        'selected_date_rooms': selected_date_rooms,
        'total_rooms': room_numbers.count(),
        'available_rooms_count': available_rooms_count,
    }
    
    return render(request, 'reception/rooms/calendar.html', context)


@login_required
@require_reception_permission('view')
def api_room_calendar(request):
    """
    API: Takvim için oda durumları (AJAX)
    Belirli bir tarih için oda durumlarını döndürür
    """
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        return JsonResponse({'error': 'Aktif otel seçilmedi.'}, status=400)
    
    hotel = request.active_hotel
    date_str = request.GET.get('date', None)
    
    if not date_str:
        return JsonResponse({'error': 'Tarih parametresi gerekli.'}, status=400)
    
    try:
        selected_date = datetime.strptime(date_str, '%Y-%m-%d').date()
    except ValueError:
        return JsonResponse({'error': 'Geçersiz tarih formatı.'}, status=400)
    
    room_numbers = RoomNumber.objects.filter(
        hotel=hotel,
        is_active=True,
        is_deleted=False
    ).select_related('room', 'floor', 'block')
    
    data = []
    for rn in room_numbers:
        reservation = Reservation.objects.filter(
            hotel=hotel,
            room_number=rn,
            check_in_date__lte=selected_date,
            check_out_date__gt=selected_date,
            status__in=['confirmed', 'checked_in'],
            is_deleted=False
        ).select_related('customer').first()
        
        room_data = {
            'id': rn.pk,
            'number': rn.number,
            'room_name': rn.room.name if rn.room else '',
            'room_type': rn.room.name if rn.room else '',
            'room_id': rn.room.pk if rn.room else None,
            'status': rn.status,
            'status_display': rn.get_status_display(),
            'is_available': not reservation and rn.status == 'available',
            'reservation_id': None,
            'reservation_status': None,
            'reservation_code': None,
            'customer_name': None,
        }
        
        if reservation:
            room_data['reservation_id'] = reservation.pk
            room_data['reservation_status'] = reservation.status
            room_data['reservation_code'] = reservation.reservation_code
            room_data['customer_name'] = f"{reservation.customer_first_name} {reservation.customer_last_name}".strip()
            if reservation.customer:
                room_data['customer_name'] = reservation.customer.full_name
        
        data.append(room_data)
    
    return JsonResponse({
        'success': True,
        'date': selected_date.isoformat(),
        'rooms': data
    })


# ==================== RESEPSİYON DASHBOARD ====================

@login_required
@require_reception_permission('view')
def dashboard(request):
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')

    hotel = request.active_hotel
    today = timezone.now().date()

    today_checkins = Reservation.objects.filter(
        hotel=hotel,
        check_in_date=today,
        is_deleted=False
    ).count()
    today_checkouts = Reservation.objects.filter(
        hotel=hotel,
        check_out_date=today,
        is_deleted=False
    ).count()
    active_reservations = Reservation.objects.filter(
        hotel=hotel,
        status__in=['pending', 'confirmed', 'checked_in'],
        is_deleted=False
    ).count()

    total_rooms = RoomNumber.objects.filter(hotel=hotel, is_active=True, is_deleted=False).count()
    occupied_rooms = Reservation.objects.filter(
        hotel=hotel,
        status__in=['confirmed', 'checked_in'],
        is_deleted=False,
        room__isnull=False,
        check_in_date__lte=today,
        check_out_date__gt=today,
    ).count()

    recent_reservations = Reservation.objects.filter(hotel=hotel, is_deleted=False).order_by('-created_at')[:10]

    context = {
        'hotel': hotel,
        'today_checkins': today_checkins,
        'today_checkouts': today_checkouts,
        'active_reservations': active_reservations,
        'total_rooms': total_rooms,
        'occupied_rooms': occupied_rooms,
        'recent_reservations': recent_reservations,
    }

    # İki farklı dashboard şablonu mevcut; apps içindeki daha zengin yapıyı tercih et
    return render(request, 'reception/dashboard.html', context)


# ==================== REZERVASYON LİSTE/DETAY ====================

@login_required
@require_reception_permission('view')
def reservation_list(request):
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')

    hotel = request.active_hotel
    status_filter = request.GET.get('status')
    qs = Reservation.objects.filter(hotel=hotel, is_deleted=False)
    if status_filter:
        qs = qs.filter(status=status_filter)

    paginator = Paginator(qs.order_by('-created_at'), 20)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)

    context = {
        'hotel': hotel,
        'status_filter': status_filter,
        'page_obj': page_obj,
        'reservations': page_obj.object_list,
    }
    return render(request, 'reception/reservations/list.html', context)


@login_required
@require_reception_permission('view')
def reservation_detail(request, pk):
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    hotel = request.active_hotel
    reservation = get_object_or_404(Reservation, pk=pk, hotel=hotel, is_deleted=False)
    return render(request, 'reception/reservations/detail.html', {'reservation': reservation, 'hotel': hotel})


# ==================== REZERVASYON OLUŞTUR ====================

@login_required
@require_reception_permission('manage')
@check_reservation_limit
def reservation_create(request):
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')

    hotel = request.active_hotel

    initial = {}
    # Oda veya müşteri parametreleri ile başlangıç değerleri
    room_id = request.GET.get('room')
    if room_id:
        try:
            initial['room'] = Room.objects.get(pk=room_id, hotel=hotel, is_deleted=False)
        except Room.DoesNotExist:
            pass

    # Varsayılan tarihleri bugün ve yarın olarak seç
    today = timezone.now().date()
    initial.setdefault('check_in_date', today)
    initial.setdefault('check_out_date', today + timedelta(days=1))
    # Fiyat alanlarını 0 başlat (formdaki readonly alanlar)
    initial.setdefault('base_price', Decimal('0'))
    initial.setdefault('adult_price', Decimal('0'))
    initial.setdefault('child_price', Decimal('0'))
    initial.setdefault('extra_services_total', Decimal('0'))
    initial.setdefault('discount_amount', Decimal('0'))
    initial.setdefault('reception_discount_rate', Decimal('0'))
    initial.setdefault('reception_discount_amount', Decimal('0'))
    initial.setdefault('total_amount', Decimal('0'))

    if request.method == 'POST':
        form = ReservationForm(request.POST, hotel=hotel)
        if form.is_valid():
            reservation = form.save(commit=False)
            reservation.hotel = hotel
            reservation.created_by = request.user

            # Toplam tutar eksikse hesapla
            base = reservation.base_price or Decimal('0')
            adult_p = reservation.adult_price or Decimal('0')
            child_p = reservation.child_price or Decimal('0')
            extras = reservation.extra_services_total or Decimal('0')
            discount = reservation.discount_amount or Decimal('0')
            reception_disc = reservation.reception_discount_amount or Decimal('0')
            if not reservation.total_amount or reservation.total_amount == Decimal('0'):
                reservation.total_amount = base + adult_p + child_p + extras - discount - reception_disc
                if reservation.total_amount < 0:
                    reservation.total_amount = Decimal('0')

            # Kod, gece ve toplam kişi hesaplamaları model save içinde otomatik
            reservation.save()

            # Audit log
            try:
                ReservationUpdate.objects.create(
                    reservation=reservation,
                    updated_by=request.user,
                    update_type='created',
                    notes='Resepsiyonda yeni rezervasyon oluşturuldu'
                )
            except Exception:
                pass

            messages.success(request, f'Rezervasyon oluşturuldu: {reservation.reservation_code}')
            return redirect('reception:reservation_detail', reservation.pk)
        else:
            messages.error(request, 'Form doğrulaması başarısız. Lütfen alanları kontrol edin.')
    else:
        form = ReservationForm(hotel=hotel, initial=initial)

    context = {
        'hotel': hotel,
        'form': form,
    }
    return render(request, 'reception/reservations/create.html', context)


# ==================== GEÇİCİ STUB FONKSİYONLAR ====================

# Aşağıdaki fonksiyonlar URL yapısının import sırasında hata vermemesi için geçici olarak eklenmiştir.
# İlerleyen geliştirmelerde gerçek işlevlerle güncellenecektir.

@login_required
def reservation_update(request, pk):
    return HttpResponse('Rezervasyon güncelleme henüz uygulanmadı.')


@login_required
def reservation_delete(request, pk):
    return HttpResponse('Rezervasyon silme henüz uygulanmadı.')


@login_required
def reservation_archive(request, pk):
    return HttpResponse('Rezervasyon arşivleme henüz uygulanmadı.')


@login_required
def reservation_restore(request, pk):
    return HttpResponse('Rezervasyon geri yükleme henüz uygulanmadı.')


@login_required
def reservation_checkin(request, pk):
    return HttpResponse('Rezervasyon check-in henüz uygulanmadı.')


@login_required
def reservation_checkout(request, pk):
    return HttpResponse('Rezervasyon check-out henüz uygulanmadı.')


@login_required
def reservation_noshow(request, pk):
    return HttpResponse('Rezervasyon no-show henüz uygulanmadı.')


@login_required
def reservation_room_change(request, pk):
    return HttpResponse('Oda değişimi henüz uygulanmadı.')


@login_required
def reservation_assign_room(request, pk):
    return HttpResponse('Oda atama henüz uygulanmadı.')


@login_required
def guest_list(request):
    return HttpResponse('Müşteri listesi henüz uygulanmadı.')


@login_required
def guest_search(request):
    return HttpResponse('Müşteri arama henüz uygulanmadı.')


@login_required
def guest_detail(request, pk):
    return HttpResponse('Müşteri detayı henüz uygulanmadı.')


@login_required
def guest_history(request, pk):
    return HttpResponse('Müşteri geçmişi henüz uygulanmadı.')


@login_required
def room_list(request):
    return HttpResponse('Oda listesi henüz uygulanmadı.')


@login_required
def room_rack(request):
    return HttpResponse('Oda durum panosu henüz uygulanmadı.')


@login_required
def room_detail(request, pk):
    return HttpResponse('Oda detayı henüz uygulanmadı.')


@login_required
def room_status_update(request, pk):
    return HttpResponse('Oda durum güncelleme henüz uygulanmadı.')


@login_required
def keycard_list(request):
    return HttpResponse('Anahtar kart listesi henüz uygulanmadı.')


@login_required
def keycard_detail(request, pk):
    return HttpResponse('Anahtar kart detayı henüz uygulanmadı.')


@login_required
def keycard_deactivate(request, pk):
    return HttpResponse('Anahtar kart iptali henüz uygulanmadı.')


@login_required
def keycard_print(request, pk):
    return HttpResponse('Anahtar kart yazdırma henüz uygulanmadı.')


@login_required
def reservation_invoice_print(request, pk):
    return HttpResponse('Rezervasyon fatura yazdırma henüz uygulanmadı.')


@login_required
def reservation_receipt_print(request, pk):
    return HttpResponse('Rezervasyon makbuz yazdırma henüz uygulanmadı.')


@login_required
def reservation_folio_print(request, pk):
    return HttpResponse('Rezervasyon folio yazdırma henüz uygulanmadı.')


@login_required
def session_list(request):
    return HttpResponse('Resepsiyon oturum listesi henüz uygulanmadı.')


@login_required
def session_start(request):
    return HttpResponse('Resepsiyon oturumu başlatma henüz uygulanmadı.')


@login_required
def session_end(request, pk):
    return HttpResponse('Resepsiyon oturumu bitirme henüz uygulanmadı.')


@login_required
def settings(request):
    return HttpResponse('Resepsiyon ayarları henüz uygulanmadı.')


@login_required
def report_daily(request):
    return HttpResponse('Günlük rapor henüz uygulanmadı.')


@login_required
def report_occupancy(request):
    return HttpResponse('Doluluk raporu henüz uygulanmadı.')


@login_required
def report_revenue(request):
    return HttpResponse('Gelir raporu henüz uygulanmadı.')


@login_required
def api_booking_list(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_booking_detail(request, pk):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_guest_search(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_customer_find(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_rooms_available(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_room_rack(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_pricing_calculate(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_pricing_activate(request, price_id):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})


@login_required
def api_keycard_create(request):
    return JsonResponse({'detail': 'Henüz uygulanmadı.'})

