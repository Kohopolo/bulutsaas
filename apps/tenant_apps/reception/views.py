"""
Resepsiyon Modülü Views
Rezervasyon yönetimi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Sum
from django.core.paginator import Paginator
from django.utils import timezone
from django.db import models
from datetime import date, timedelta
from decimal import Decimal

from .models import Reservation, ReservationStatus
from .forms import ReservationForm, ReservationGuestFormSet
from .utils import save_guest_information
from apps.tenant_apps.hotels.decorators import require_hotel_permission


@login_required
@require_hotel_permission('view')
def dashboard(request):
    """Rezervasyon Dashboard - Gelişmiş"""
    hotel = request.active_hotel
    today = date.today()
    
    # Bugünkü rezervasyonlar (oda içinde)
    today_reservations = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__lte=today,
        check_out_date__gte=today,
        status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
        is_deleted=False
    ).select_related('customer', 'room', 'room_number').order_by('check_in_date')
    
    # Bekleyen check-in'ler (bugün)
    pending_checkins = Reservation.objects.filter(
        hotel=hotel,
        check_in_date=today,
        status=ReservationStatus.CONFIRMED,
        is_checked_in=False,
        is_deleted=False
    ).select_related('customer', 'room').order_by('check_in_time')[:10]
    
    # Bekleyen check-out'lar (bugün)
    pending_checkouts = Reservation.objects.filter(
        hotel=hotel,
        check_out_date=today,
        is_checked_in=True,
        is_checked_out=False,
        is_deleted=False
    ).select_related('customer', 'room').order_by('check_out_time')[:10]
    
    # Yaklaşan rezervasyonlar (7 gün içinde)
    upcoming_reservations = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__gt=today,
        check_in_date__lte=today + timedelta(days=7),
        status=ReservationStatus.CONFIRMED,
        is_deleted=False
    ).select_related('customer', 'room').order_by('check_in_date')[:10]
    
    # Bu ay istatistikleri
    from datetime import datetime
    month_start = today.replace(day=1)
    month_reservations = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__gte=month_start,
        check_in_date__lte=today,
        is_deleted=False
    )
    
    # Bu hafta istatistikleri
    week_start = today - timedelta(days=today.weekday())
    week_reservations = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__gte=week_start,
        check_in_date__lte=today,
        is_deleted=False
    )
    
    # Ödeme bekleyen rezervasyonlar
    pending_payments = Reservation.objects.filter(
        hotel=hotel,
        is_deleted=False
    ).exclude(total_paid__gte=F('total_amount')).select_related('customer', 'room').order_by('-check_in_date')[:10]
    
    # İptal edilen rezervasyonlar (bu ay)
    cancelled_this_month = Reservation.objects.filter(
        hotel=hotel,
        is_cancelled=True,
        cancelled_at__gte=month_start,
        is_deleted=False
    ).count()
    
    # No-show rezervasyonlar (bu ay)
    no_show_this_month = Reservation.objects.filter(
        hotel=hotel,
        is_no_show=True,
        check_in_date__gte=month_start,
        is_deleted=False
    ).count()
    
    # İstatistikler
    stats = {
        'total_reservations': Reservation.objects.filter(hotel=hotel, is_deleted=False).count(),
        'confirmed_reservations': Reservation.objects.filter(
            hotel=hotel, status=ReservationStatus.CONFIRMED, is_deleted=False
        ).count(),
        'checked_in': Reservation.objects.filter(
            hotel=hotel, is_checked_in=True, is_checked_out=False, is_deleted=False
        ).count(),
        'today_revenue': Reservation.objects.filter(
            hotel=hotel,
            check_in_date=today,
            is_deleted=False
        ).aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'month_revenue': month_reservations.aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'week_revenue': week_reservations.aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'month_reservations': month_reservations.count(),
        'week_reservations': week_reservations.count(),
        'pending_payments_count': Reservation.objects.filter(
            hotel=hotel,
            is_deleted=False
        ).exclude(total_paid__gte=F('total_amount')).count(),
        'cancelled_this_month': cancelled_this_month,
        'no_show_this_month': no_show_this_month,
        'today_checkins': pending_checkins.count(),
        'today_checkouts': pending_checkouts.count(),
        'today_guests': today_reservations.aggregate(
            total=Sum('adult_count') + Sum('child_count')
        )['total'] or 0,
    }
    
    # Oda durumu özeti
    room_status_summary = None
    try:
        from apps.tenant_apps.hotels.models import RoomNumber
        room_status_summary = RoomNumber.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).values('status').annotate(count=Count('id')).order_by('status')
    except:
        pass
    
    context = {
        'today_reservations': today_reservations,
        'pending_checkins': pending_checkins,
        'pending_checkouts': pending_checkouts,
        'upcoming_reservations': upcoming_reservations,
        'pending_payments': pending_payments,
        'stats': stats,
        'room_status_summary': room_status_summary,
        'today': today,
    }
    
    return render(request, 'reception/dashboard.html', context)


@login_required
@require_hotel_permission('view')
def reservation_list(request):
    """Rezervasyon Listesi"""
    hotel = request.active_hotel
    
    reservations = Reservation.objects.filter(
        hotel=hotel,
        is_deleted=False
    ).select_related('customer', 'room', 'room_number').order_by('-check_in_date')
    
    # Filtreleme
    status_filter = request.GET.get('status')
    if status_filter:
        reservations = reservations.filter(status=status_filter)
    
    search_query = request.GET.get('search')
    if search_query:
        reservations = reservations.filter(
            Q(reservation_code__icontains=search_query) |
            Q(customer__first_name__icontains=search_query) |
            Q(customer__last_name__icontains=search_query) |
            Q(customer__email__icontains=search_query)
        )
    
    # Sayfalama
    paginator = Paginator(reservations, 25)
    page = request.GET.get('page')
    reservations = paginator.get_page(page)
    
    # Form'u context'e ekle (modal için)
    form = ReservationForm(hotel=hotel)
    
    context = {
        'reservations': reservations,
        'status_choices': ReservationStatus.choices,
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'reception/reservations/list.html', context)


@login_required
@require_hotel_permission('add')
def reservation_create(request):
    """Yeni Rezervasyon Oluştur (Popup Modal)"""
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = ReservationForm(request.POST, hotel=hotel)
        if form.is_valid():
            reservation = form.save(commit=False)
            reservation.hotel = hotel
            reservation.created_by = request.user
            
            # Rezervasyon kodu oluştur
            if not reservation.reservation_code:
                from .utils import generate_reservation_code
                hotel_code = hotel.code if hasattr(hotel, 'code') else None
                reservation.reservation_code = generate_reservation_code(hotel_code=hotel_code)
            
            reservation.save()
            
            # Müşteri bilgilerini güncelle (formdan gelen bilgilerle)
            customer = reservation.customer
            if request.POST.get('customer_first_name'):
                customer.first_name = request.POST.get('customer_first_name')
            if request.POST.get('customer_last_name'):
                customer.last_name = request.POST.get('customer_last_name')
            if request.POST.get('customer_phone'):
                customer.phone = request.POST.get('customer_phone')
            if request.POST.get('customer_email'):
                customer.email = request.POST.get('customer_email')
            if request.POST.get('customer_address'):
                customer.address = request.POST.get('customer_address')
            if request.POST.get('customer_tc_no'):
                customer.tc_no = request.POST.get('customer_tc_no')
            customer.save()
            
            # Misafir bilgilerini kaydet (formset ile)
            try:
                guest_formset = ReservationGuestFormSet(request.POST, instance=reservation)
                if guest_formset.is_valid():
                    guest_formset.save()
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.info(f'Misafir bilgileri kaydedildi (formset) - Rezervasyon: {reservation.reservation_code}')
                else:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.warning(f'Misafir formset geçersiz: {guest_formset.errors}')
                    # Hata olsa bile devam et (rezervasyon kaydedildi)
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Misafir bilgileri kaydedilirken hata: {str(e)}', exc_info=True)
                # Hata olsa bile devam et (rezervasyon kaydedildi)
            
            # Ön ödeme varsa kaydet
            try:
                # Form'dan ön ödeme bilgisini al
                advance_payment = form.cleaned_data.get('advance_payment', 0)
                # Eğer form'da yoksa, POST'tan al
                if not advance_payment:
                    advance_payment_str = request.POST.get('advance_payment', '0')
                    try:
                        advance_payment = Decimal(advance_payment_str) if advance_payment_str else Decimal('0')
                    except (ValueError, TypeError):
                        advance_payment = Decimal('0')
                
                # Form'dan ödeme yöntemini al
                payment_method = form.cleaned_data.get('payment_method')
                # Eğer form'da yoksa, POST'tan al
                if not payment_method:
                    payment_method = request.POST.get('payment_method', '')
                
                # Ödeme kaydı oluştur (ön ödeme varsa ve ödeme yöntemi seçilmişse)
                if advance_payment and advance_payment > 0 and payment_method:
                    from .models import ReservationPayment
                    ReservationPayment.objects.create(
                        reservation=reservation,
                        payment_date=date.today(),
                        payment_amount=advance_payment,
                        payment_method=payment_method,
                        payment_type='advance',
                        currency=reservation.currency,
                        created_by=request.user
                    )
                    reservation.update_total_paid()
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.info(f'Ön ödeme kaydedildi: {advance_payment} {reservation.currency}, Yöntem: {payment_method}')
                else:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.warning(f'Ön ödeme kaydedilmedi - Advance: {advance_payment}, Method: {payment_method}')
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Ön ödeme kaydedilirken hata: {str(e)}', exc_info=True)
            
            # Timeline kaydı
            from .models import ReservationTimeline
            ReservationTimeline.objects.create(
                reservation=reservation,
                action_type='created',
                action_description=f'Rezervasyon oluşturuldu: {reservation.reservation_code}',
                user=request.user
            )
            
            # Bildirim gönder (signal'da otomatik gönderilecek, burada opsiyonel)
            if request.POST.get('send_notification') == 'on':
                from .utils_notifications import send_reservation_notification
                send_reservation_notification(
                    reservation=reservation,
                    notification_type='whatsapp',
                )
                send_reservation_notification(
                    reservation=reservation,
                    notification_type='sms',
                )
            
            messages.success(request, 'Rezervasyon başarıyla oluşturuldu.')
            
            # AJAX isteği ise JSON döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                return JsonResponse({
                    'success': True,
                    'message': 'Rezervasyon başarıyla oluşturuldu.',
                    'reservation_id': reservation.pk,
                    'reservation_code': reservation.reservation_code,
                    'redirect_url': f'/reception/reservations/{reservation.pk}/'
                })
            
            return redirect('reception:reservation_detail', pk=reservation.pk)
        else:
            # AJAX isteği ise hata mesajlarını döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                return JsonResponse({
                    'success': False,
                    'errors': form.errors,
                    'message': 'Form hataları var. Lütfen kontrol edin.'
                }, status=400)
    else:
        form = ReservationForm(hotel=hotel)
        # Yeni rezervasyon için boş formset
        guest_formset = ReservationGuestFormSet(instance=None)
    
    context = {
        'form': form,
        'guest_formset': guest_formset,
        'hotel': hotel,
    }
    
    # AJAX isteği ise sadece form HTML'ini döndür
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        from django.template.loader import render_to_string
        html = render_to_string('reception/reservations/form_modal.html', context, request=request)
        from django.http import JsonResponse
        return JsonResponse({'html': html})
    
    return render(request, 'reception/reservations/form.html', context)


@login_required
@require_hotel_permission('view')
def reservation_detail(request, pk):
    """Rezervasyon Detayı"""
    try:
        hotel = request.active_hotel
    except AttributeError:
        from django.shortcuts import redirect
        from django.contrib import messages
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    try:
        reservation = get_object_or_404(
            Reservation,
            pk=pk,
            hotel=hotel,
            is_deleted=False
        )
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Rezervasyon bulunamadı: {str(e)}', exc_info=True)
        from django.shortcuts import redirect
        from django.contrib import messages
        messages.error(request, 'Rezervasyon bulunamadı.')
        return redirect('reception:reservation_list')
    
    # Müşteri folyosu bilgileri
    customer = None
    if reservation and reservation.customer:
        customer = reservation.customer
    
    customer_folio_url = None
    if customer:
        from django.urls import reverse
        try:
            customer_folio_url = reverse('core:customer_detail', kwargs={'pk': customer.pk})
        except:
            pass
    
    # İlgili rezervasyonlar (aynı müşteri)
    related_reservations = None
    if customer:
        related_reservations = Reservation.objects.filter(
            customer=customer,
            is_deleted=False
        ).exclude(pk=reservation.pk).order_by('-check_in_date')[:10]
    
    # Ödemeler
    payments = reservation.payments.filter(is_deleted=False).order_by('-payment_date')
    
    # Ödeme özeti
    payment_summary = {
        'total_paid': reservation.total_paid,
        'total_amount': reservation.total_amount,
        'remaining_amount': reservation.get_remaining_amount(),
        'payment_count': payments.count(),
    }
    
    # Misafirler
    guests = reservation.guests.all().order_by('guest_type', 'guest_order')
    adult_guests = guests.filter(guest_type='adult')
    child_guests = guests.filter(guest_type='child')
    
    # Timeline
    timeline = reservation.timeline.all().order_by('-created_at')[:20]
    
    # Voucher'lar
    vouchers = reservation.vouchers.all().order_by('-created_at')
    
    # Form'u context'e ekle (modal için)
    form = ReservationForm(instance=reservation, hotel=hotel)
    
    context = {
        'reservation': reservation,
        'customer': customer,
        'customer_folio_url': customer_folio_url,
        'related_reservations': related_reservations,
        'payments': payments,
        'payment_summary': payment_summary,
        'guests': guests,
        'adult_guests': adult_guests,
        'child_guests': child_guests,
        'timeline': timeline,
        'vouchers': vouchers,
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'reception/reservations/detail.html', context)


@login_required
@require_hotel_permission('edit')
def reservation_update(request, pk):
    """Rezervasyon Güncelle (Modal)"""
    try:
        hotel = request.active_hotel
    except AttributeError:
        from django.http import JsonResponse
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'error': 'Aktif otel seçilmedi.'}, status=403)
        from django.shortcuts import redirect
        from django.contrib import messages
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    try:
        reservation = get_object_or_404(
            Reservation,
            pk=pk,
            hotel=hotel,
            is_deleted=False
        )
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Rezervasyon bulunamadı: {str(e)}', exc_info=True)
        from django.http import JsonResponse
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'error': 'Rezervasyon bulunamadı.'}, status=404)
        from django.shortcuts import redirect
        from django.contrib import messages
        messages.error(request, 'Rezervasyon bulunamadı.')
        return redirect('reception:reservation_list')
    
    if request.method == 'POST':
        form = ReservationForm(request.POST, instance=reservation, hotel=hotel)
        guest_formset = ReservationGuestFormSet(request.POST, instance=reservation)
        
        if form.is_valid() and guest_formset.is_valid():
            reservation = form.save()
            guest_formset.save()
            
            # Ödeme bilgilerini kaydet (form'dan gelen)
            try:
                advance_payment = form.cleaned_data.get('advance_payment', 0)
                payment_method = form.cleaned_data.get('payment_method')
                
                if not advance_payment:
                    advance_payment_str = request.POST.get('advance_payment', '0')
                    try:
                        advance_payment = Decimal(advance_payment_str) if advance_payment_str else Decimal('0')
                    except (ValueError, TypeError):
                        advance_payment = Decimal('0')
                
                if not payment_method:
                    payment_method = request.POST.get('payment_method', '')
                
                if advance_payment and advance_payment > 0 and payment_method:
                    from .models import ReservationPayment
                    ReservationPayment.objects.create(
                        reservation=reservation,
                        payment_date=date.today(),
                        payment_amount=advance_payment,
                        payment_method=payment_method,
                        payment_type='advance',
                        currency=reservation.currency,
                        created_by=request.user
                    )
                    reservation.update_total_paid()
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Ödeme kaydedilirken hata: {str(e)}', exc_info=True)
            
            messages.success(request, 'Rezervasyon başarıyla güncellendi.')
            # AJAX request ise JSON döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                return JsonResponse({'success': True, 'redirect_url': reverse('reception:reservation_detail', args=[reservation.pk])})
            return redirect('reception:reservation_detail', pk=reservation.pk)
        else:
            # AJAX request ise hata döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                errors = form.errors.copy()
                if guest_formset.errors:
                    errors['guests'] = guest_formset.errors
                return JsonResponse({'success': False, 'errors': errors})
    else:
        form = ReservationForm(instance=reservation, hotel=hotel)
        # Mevcut misafirleri formset'e yükle
        guest_formset = ReservationGuestFormSet(instance=reservation)
    
    # Misafir bilgileri (JavaScript için JSON)
    import json
    guests_data = []
    try:
        if reservation:
            for guest in reservation.guests.all().order_by('guest_type', 'guest_order'):
                guests_data.append({
                    'type': guest.guest_type,
                    'order': guest.guest_order,
                    'first_name': guest.first_name or '',
                    'last_name': guest.last_name or '',
                    'gender': guest.gender or '',
                    'age': guest.age if guest.age else None,
                    'tc_no': guest.tc_no or '',
                    'passport_no': guest.passport_no or '',
                    'passport_serial_no': guest.passport_serial_no or '',
                    'id_serial_no': guest.id_serial_no or '',
                })
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Misafir bilgileri yüklenirken hata: {str(e)}', exc_info=True)
    
    # Müşteri bilgileri (JavaScript için)
    customer_data = None
    try:
        if reservation and reservation.customer:
            customer = reservation.customer
            customer_data = {
                'id': customer.id,
                'first_name': customer.first_name or '',
                'last_name': customer.last_name or '',
                'phone': customer.phone or '',
                'email': customer.email or '',
                'address': customer.address or '',
                'tc_no': customer.tc_no or '',
                'passport_no': '',  # Customer modelinde passport_no yok, ReservationGuest'te var
                'nationality': customer.country or 'Türkiye',
            }
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Müşteri bilgileri yüklenirken hata: {str(e)}', exc_info=True)
    
    # Ödeme bilgileri (JavaScript için)
    payment_data = {
        'total_paid': 0,
        'total_amount': 0,
        'remaining_amount': 0,
        'payment_method': '',
    }
    try:
        if reservation:
            payment_data = {
                'total_paid': float(reservation.total_paid) if reservation.total_paid else 0,
                'total_amount': float(reservation.total_amount) if reservation.total_amount else 0,
                'remaining_amount': float(reservation.get_remaining_amount()) if reservation else 0,
            }
            
            # Son ödeme yöntemini al (varsa)
            last_payment = reservation.payments.filter(is_deleted=False).order_by('-payment_date').first()
            if last_payment:
                payment_data['payment_method'] = last_payment.payment_method or ''
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Ödeme bilgileri yüklenirken hata: {str(e)}', exc_info=True)
    
    context = {
        'form': form,
        'guest_formset': guest_formset,
        'reservation': reservation,
        'hotel': hotel,
        'guests_data_json': json.dumps(guests_data),
        'customer_data_json': json.dumps(customer_data) if customer_data else 'null',
        'payment_data_json': json.dumps(payment_data),
    }
    
    # AJAX request ise sadece form modal'ını döndür
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        return render(request, 'reception/reservations/form_modal.html', context)
    
    # Normal request ise detail sayfasına yönlendir (modal orada açılacak)
    return redirect('reception:reservation_detail', pk=reservation.pk)


@login_required
@require_hotel_permission('delete')
def reservation_delete(request, pk):
    """Rezervasyon Sil (Soft Delete)"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        reservation.is_deleted = True
        reservation.save()
        messages.success(request, 'Rezervasyon başarıyla silindi.')
        return redirect('reception:reservation_list')
    
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/delete.html', context)


@login_required
@require_hotel_permission('checkin')
def reservation_checkin(request, pk):
    """Check-in Yap"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if not reservation.can_check_in():
        messages.error(request, 'Bu rezervasyon için check-in yapılamaz.')
        return redirect('reception:reservation_detail', pk=reservation.pk)
    
    if request.method == 'POST':
        reservation.is_checked_in = True
        reservation.status = ReservationStatus.CHECKED_IN
        reservation.checked_in_at = timezone.now()
        reservation.save()
        messages.success(request, 'Check-in başarıyla yapıldı.')
        return redirect('reception:reservation_detail', pk=reservation.pk)
    
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/checkin.html', context)


@login_required
@require_hotel_permission('checkout')
def reservation_checkout(request, pk):
    """Check-out Yap"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if not reservation.can_check_out():
        messages.error(request, 'Bu rezervasyon için check-out yapılamaz.')
        return redirect('reception:reservation_detail', pk=reservation.pk)
    
    if request.method == 'POST':
        reservation.is_checked_out = True
        reservation.status = ReservationStatus.CHECKED_OUT
        reservation.checked_out_at = timezone.now()
        reservation.save()
        messages.success(request, 'Check-out başarıyla yapıldı.')
        return redirect('reception:reservation_detail', pk=reservation.pk)
    
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/checkout.html', context)


# ==================== API VIEWS ====================

@login_required
@require_hotel_permission('view')
def api_search_customer(request):
    """Müşteri arama API"""
    from django.http import JsonResponse
    from apps.tenant_apps.core.models import Customer
    
    search_term = request.GET.get('q', '').strip()
    if not search_term:
        return JsonResponse({'customer': None})
    
    # TC No, Email veya Telefon ile ara (Customer tenant bazlı, hotel field'ı yok)
    customer = Customer.objects.filter(
        is_active=True,
        is_deleted=False
    ).filter(
        Q(tc_no=search_term) | Q(email=search_term) | Q(phone=search_term)
    ).first()
    
    if customer:
        return JsonResponse({
            'customer': {
                'id': customer.id,
                'first_name': customer.first_name,
                'last_name': customer.last_name,
                'phone': customer.phone,
                'email': customer.email,
                'address': customer.address,
                'tc_no': customer.tc_no,
                'nationality': customer.country,
            }
        })
    else:
        return JsonResponse({'customer': None})


@login_required
@require_hotel_permission('view')
def api_room_numbers(request):
    """Oda numaralarını getir API"""
    from django.http import JsonResponse
    from apps.tenant_apps.hotels.models import RoomNumber
    
    room_id = request.GET.get('room_id')
    if not room_id:
        return JsonResponse({'room_numbers': []})
    
    hotel = request.active_hotel
    
    room_numbers = RoomNumber.objects.filter(
        hotel=hotel,
        room_id=room_id,
        is_active=True,
        is_deleted=False
    ).order_by('number')
    
    return JsonResponse({
        'room_numbers': [
            {
                'id': rn.id,
                'number': rn.number,
                'status': rn.status,
                'status_display': rn.get_status_display(),
            }
            for rn in room_numbers
        ]
    })


# NOT: api_calculate_price fonksiyonu aşağıda tekrar tanımlanmış (1048. satır)
# Bu fonksiyon kaldırıldı, çünkü GET request bekleyen versiyon kullanılıyor


# ==================== ODA PLANI VE DURUMU ====================

@login_required
@require_hotel_permission('view')
def room_plan(request):
    """Oda Planı Görünümü"""
    hotel = request.active_hotel
    
    # Katlar
    try:
        from apps.tenant_apps.hotels.models import Floor, RoomNumber, RoomNumberStatus
        floors = Floor.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).order_by('floor_number')
        
        # Bugünkü rezervasyonlar (tüm katlar için ortak)
        today = date.today()
        reservations = Reservation.objects.filter(
            hotel=hotel,
            check_in_date__lte=today,
            check_out_date__gte=today,
            is_deleted=False
        ).select_related('customer', 'room')
        
        # Oda numarasına göre rezervasyon eşleştirme
        room_reservations = {r.room_number_id: r for r in reservations if r.room_number_id}
        
        # Her kat için oda numaraları
        floor_rooms = {}
        for floor in floors:
            rooms = RoomNumber.objects.filter(
                hotel=hotel,
                floor=floor,
                is_active=True,
                is_deleted=False
            ).select_related('room', 'block').order_by('number')
            
            # Her oda için rezervasyon bilgisini ekle
            rooms_with_reservations = []
            for room in rooms:
                reservation = room_reservations.get(room.id)
                rooms_with_reservations.append({
                    'room': room,
                    'reservation': reservation,
                })
            
            floor_rooms[floor] = rooms_with_reservations
        
        # Bloklar
        from apps.tenant_apps.hotels.models import Block
        blocks = Block.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).order_by('name')
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Oda planı görüntüleme hatası: {str(e)}', exc_info=True)
        floors = []
        floor_rooms = {}
        room_reservations = {}
        blocks = []
    
    context = {
        'hotel': hotel,
        'floors': floors,
        'floor_rooms': floor_rooms,
        'room_reservations': room_reservations,
        'blocks': blocks,
    }
    
    return render(request, 'reception/room_plan.html', context)


@login_required
@require_hotel_permission('view')
def room_status(request):
    """Oda Durumu Listesi"""
    hotel = request.active_hotel
    
    try:
        from apps.tenant_apps.hotels.models import RoomNumber, RoomNumberStatus
        from django.db.models import Count, Q
        
        # Filtreleme
        status_filter = request.GET.get('status')
        floor_filter = request.GET.get('floor')
        room_type_filter = request.GET.get('room_type')
        
        room_numbers = RoomNumber.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).select_related('room', 'floor', 'block').order_by('floor__floor_number', 'number')
        
        if status_filter:
            room_numbers = room_numbers.filter(status=status_filter)
        
        if floor_filter:
            room_numbers = room_numbers.filter(floor_id=floor_filter)
        
        if room_type_filter:
            room_numbers = room_numbers.filter(room_id=room_type_filter)
        
        # Bugünkü rezervasyonlar
        today = date.today()
        reservations = Reservation.objects.filter(
            hotel=hotel,
            check_in_date__lte=today,
            check_out_date__gte=today,
            is_deleted=False
        ).select_related('customer', 'room')
        
        room_reservations = {r.room_number_id: r for r in reservations if r.room_number_id}
        
        # İstatistikler
        status_counts = RoomNumber.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).values('status').annotate(count=Count('id'))
        
        # Katlar ve oda tipleri (filtre için)
        from apps.tenant_apps.hotels.models import Floor, Room
        floors = Floor.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('floor_number')
        room_types = Room.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('name')
        
        # Template için room_reservations'ı list olarak hazırla
        room_reservations_list = list(room_reservations.keys())
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Oda durumu görüntüleme hatası: {str(e)}')
        room_numbers = []
        room_reservations = {}
        room_reservations_list = []
        status_counts = []
        floors = []
        room_types = []
        try:
            from apps.tenant_apps.hotels.models import RoomNumberStatus
        except:
            RoomNumberStatus = type('RoomNumberStatus', (), {'choices': []})
    
    context = {
        'hotel': hotel,
        'room_numbers': room_numbers,
        'room_reservations': room_reservations,
        'room_reservations_list': room_reservations_list,
        'status_counts': status_counts,
        'status_choices': RoomNumberStatus.choices if RoomNumberStatus else [],
        'floors': floors,
        'room_types': room_types,
        'current_status': status_filter,
        'current_floor': floor_filter,
        'current_room_type': room_type_filter,
    }
    
    return render(request, 'reception/room_status.html', context)


@login_required
@require_hotel_permission('change')
def api_room_status_update(request):
    """Oda durumu güncelleme API"""
    from django.http import JsonResponse
    
    if request.method != 'POST':
        return JsonResponse({'success': False, 'error': 'POST method required'})
    
    try:
        import json
        data = json.loads(request.body)
        room_number_id = data.get('room_number_id')
        new_status = data.get('status')
        
        if not room_number_id or not new_status:
            return JsonResponse({'success': False, 'error': 'Eksik parametreler'})
        
        from apps.tenant_apps.hotels.models import RoomNumber, RoomNumberStatus
        
        hotel = request.active_hotel
        room_number = RoomNumber.objects.get(
            pk=room_number_id,
            hotel=hotel,
            is_active=True,
            is_deleted=False
        )
        
        # Status doğrulama
        valid_statuses = [choice[0] for choice in RoomNumberStatus.choices]
        if new_status not in valid_statuses:
            return JsonResponse({'success': False, 'error': 'Geçersiz durum'})
        
        room_number.status = new_status
        room_number.save()
        
        return JsonResponse({
            'success': True,
            'message': 'Oda durumu güncellendi',
            'room_number_id': room_number_id,
            'new_status': new_status,
            'status_display': room_number.get_status_display(),
        })
        
    except RoomNumber.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'Oda bulunamadı'})
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Oda durumu güncelleme hatası: {str(e)}')
        return JsonResponse({'success': False, 'error': str(e)})


# ==================== VOUCHER VIEWS ====================

@login_required
@require_hotel_permission('view')
def reservation_voucher_create(request, pk):
    """Rezervasyon voucher'ı oluştur"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    template_id = request.GET.get('template_id')
    template = None
    if template_id:
        from .models import VoucherTemplate
        template = VoucherTemplate.objects.filter(pk=template_id, is_active=True).first()
    
    from .utils import create_reservation_voucher
    voucher = create_reservation_voucher(reservation, template=template)
    
    # Voucher bildirimi gönder (signal'da otomatik gönderilecek)
    if request.GET.get('send_notification') == '1':
        from .utils_notifications import send_voucher_notification
        send_voucher_notification(voucher, notification_type='whatsapp')
        send_voucher_notification(voucher, notification_type='sms')
    
    messages.success(request, 'Voucher başarıyla oluşturuldu.')
    return redirect('reception:reservation_voucher_detail', pk=voucher.pk)


@login_required
@require_hotel_permission('view')
def reservation_voucher_detail(request, pk):
    """Voucher detay ve görüntüleme"""
    from .models import ReservationVoucher
    voucher = get_object_or_404(ReservationVoucher, pk=pk)
    
    # Voucher HTML'ini oluştur
    from .utils import generate_reservation_voucher
    voucher_html, _ = generate_reservation_voucher(voucher.reservation, voucher.voucher_template)
    
    context = {
        'voucher': voucher,
        'voucher_html': voucher_html,
    }
    
    return render(request, 'reception/vouchers/detail.html', context)


@login_required
@require_hotel_permission('view')
def reservation_voucher_pdf(request, pk):
    """Voucher PDF olarak indir"""
    from .models import ReservationVoucher
    from django.http import HttpResponse
    voucher = get_object_or_404(ReservationVoucher, pk=pk)
    
    # Voucher HTML'ini oluştur
    from .utils import generate_reservation_voucher
    voucher_html, _ = generate_reservation_voucher(voucher.reservation, voucher.voucher_template)
    
    # PDF oluştur (weasyprint veya xhtml2pdf kullanılabilir)
    try:
        from weasyprint import HTML
        pdf = HTML(string=voucher_html).write_pdf()
        response = HttpResponse(pdf, content_type='application/pdf')
        response['Content-Disposition'] = f'attachment; filename="voucher_{voucher.voucher_code}.pdf"'
        return response
    except ImportError:
        # weasyprint yoksa HTML döndür
        return HttpResponse(voucher_html, content_type='text/html')


@login_required
@require_hotel_permission('view')
def voucher_template_list(request):
    """Voucher şablonları listesi"""
    from .models import VoucherTemplate
    templates = VoucherTemplate.objects.filter(is_deleted=False).order_by('-is_default', 'name')
    
    context = {
        'templates': templates,
    }
    
    return render(request, 'reception/vouchers/templates/list.html', context)


@login_required
@require_hotel_permission('add')
def voucher_template_create(request):
    """Voucher şablonu oluştur"""
    from .models import VoucherTemplate
    from .forms import VoucherTemplateForm
    
    if request.method == 'POST':
        form = VoucherTemplateForm(request.POST)
        if form.is_valid():
            template = form.save()
            messages.success(request, 'Voucher şablonu başarıyla oluşturuldu.')
            return redirect('reception:voucher_template_detail', pk=template.pk)
    else:
        form = VoucherTemplateForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'reception/vouchers/templates/form.html', context)


@login_required
@require_hotel_permission('change')
def voucher_template_update(request, pk):
    """Voucher şablonu güncelle"""
    from .models import VoucherTemplate
    from .forms import VoucherTemplateForm
    
    template = get_object_or_404(VoucherTemplate, pk=pk)
    
    if request.method == 'POST':
        form = VoucherTemplateForm(request.POST, instance=template)
        if form.is_valid():
            template = form.save()
            messages.success(request, 'Voucher şablonu başarıyla güncellendi.')
            return redirect('reception:voucher_template_detail', pk=template.pk)
    else:
        form = VoucherTemplateForm(instance=template)
    
    context = {
        'form': form,
        'template': template,
    }
    
    return render(request, 'reception/vouchers/templates/form.html', context)


@login_required
@require_hotel_permission('view')
def voucher_template_detail(request, pk):
    """Voucher şablonu detay"""
    from .models import VoucherTemplate
    template = get_object_or_404(VoucherTemplate, pk=pk)
    
    context = {
        'template': template,
    }
    
    return render(request, 'reception/vouchers/templates/detail.html', context)


# ==================== API ENDPOINTS ====================

@login_required
@require_hotel_permission('view')
def api_customer_search(request):
    """Müşteri Arama API"""
    from django.http import JsonResponse
    from apps.tenant_apps.core.models import Customer
    
    search_query = request.GET.get('q', '').strip()
    hotel = request.active_hotel
    
    if not search_query:
        return JsonResponse({'customers': []})
    
    # TC No, Email veya Telefon ile ara
    customers = Customer.objects.filter(
        hotel=hotel,
        is_active=True,
        is_deleted=False
    ).filter(
        Q(tc_no__icontains=search_query) |
        Q(email__icontains=search_query) |
        Q(phone__icontains=search_query) |
        Q(first_name__icontains=search_query) |
        Q(last_name__icontains=search_query)
    )[:10]
    
    results = []
    for customer in customers:
        results.append({
            'id': customer.id,
            'first_name': customer.first_name,
            'last_name': customer.last_name,
            'email': customer.email,
            'phone': customer.phone,
            'tc_no': customer.tc_no,
            'address': customer.address,
            'city': customer.city,
            'country': customer.country,
            'full_name': customer.get_full_name(),
        })
    
    return JsonResponse({'customers': results})


@login_required
@require_hotel_permission('view')
def api_calculate_price(request):
    """Fiyat Hesaplama API"""
    from django.http import JsonResponse
    from .utils import calculate_room_price_with_utility
    
    hotel = request.active_hotel
    
    try:
        room_id = int(request.GET.get('room_id'))
        check_in_date = request.GET.get('check_in_date')
        check_out_date = request.GET.get('check_out_date')
        adult_count = int(request.GET.get('adult_count', 1))
        child_count = int(request.GET.get('child_count', 0))
        agency_id = request.GET.get('agency_id')
        channel_id = request.GET.get('channel_id')
        
        # Tarih parse
        from datetime import datetime
        check_in = datetime.strptime(check_in_date, '%Y-%m-%d').date()
        check_out = datetime.strptime(check_out_date, '%Y-%m-%d').date()
        
        # Oda bul
        from apps.tenant_apps.hotels.models import Room
        room = Room.objects.get(id=room_id, hotel=hotel)
        
        # Çocuk yaşları
        child_ages = []
        if child_count > 0:
            child_ages_str = request.GET.get('child_ages', '')
            if child_ages_str:
                child_ages = [int(age) for age in child_ages_str.split(',') if age.strip()]
        
        # Fiyat hesapla
        result = calculate_room_price_with_utility(
            room=room,
            check_in_date=check_in,
            check_out_date=check_out,
            adult_count=adult_count,
            child_count=child_count,
            child_ages=child_ages,
            agency_id=int(agency_id) if agency_id else None,
            channel_id=int(channel_id) if channel_id else None
        )
        
        if result['success']:
            return JsonResponse({
                'success': True,
                'avg_nightly_price': float(result['avg_nightly_price']),
                'total_price': float(result['total_price']),
                'nights': result['nights'],
            })
        else:
            return JsonResponse({
                'success': False,
                'error': result.get('error', 'Fiyat hesaplanamadı')
            }, status=400)
            
    except Exception as e:
        return JsonResponse({
            'success': False,
            'error': str(e)
        }, status=400)


@login_required
@require_hotel_permission('view')
def api_room_numbers(request):
    """Oda Numaraları API"""
    from django.http import JsonResponse
    from apps.tenant_apps.hotels.models import RoomNumber
    
    hotel = request.active_hotel
    room_id = request.GET.get('room_id')
    
    if not room_id:
        return JsonResponse({'room_numbers': []})
    
    room_numbers = RoomNumber.objects.filter(
        hotel=hotel,
        room_id=room_id,
        is_active=True,
        is_deleted=False
    ).order_by('number')
    
    results = []
    for room_number in room_numbers:
        results.append({
            'id': room_number.id,
            'number': room_number.number,
            'status': room_number.status,
            'status_display': room_number.get_status_display(),
        })
    
    return JsonResponse({'room_numbers': results})

