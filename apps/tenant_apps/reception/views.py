"""
Resepsiyon Modülü Views
Rezervasyon yönetimi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Sum, F
from django.core.paginator import Paginator
from django.utils import timezone
from django.db import models
from datetime import date, timedelta
from decimal import Decimal
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_http_methods
from django.http import JsonResponse
from django.urls import reverse

from .models import Reservation, ReservationStatus, ReservationSource
from .forms import ReservationForm, ReservationGuestFormSet
from .utils import save_guest_information
from apps.tenant_apps.hotels.decorators import require_hotel_permission


@login_required
@require_hotel_permission('view')
def dashboard(request):
    """Rezervasyon Dashboard - Gelişmiş"""
    try:
        hotel = request.active_hotel
        if not hotel:
            messages.error(request, 'Aktif otel seçilmedi.')
            return redirect('hotels:select_hotel')
    except AttributeError:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
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
        'today_guests': sum(r.adult_count + r.child_count for r in today_reservations),
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
    
    # Pending payments için remaining_amount hesapla
    pending_payments_with_remaining = []
    for payment in pending_payments:
        payment.remaining_amount = payment.get_remaining_amount()
        pending_payments_with_remaining.append(payment)
    
    context = {
        'today_reservations': today_reservations,
        'pending_checkins': pending_checkins,
        'pending_checkouts': pending_checkouts,
        'upcoming_reservations': upcoming_reservations,
        'pending_payments': pending_payments_with_remaining,
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
    
    # Gelişmiş Filtreleme
    # 1. Durum filtresi
    status_filter = request.GET.get('status')
    if status_filter:
        reservations = reservations.filter(status=status_filter)
    
    # 2. Arama filtresi
    search_query = request.GET.get('search')
    if search_query:
        reservations = reservations.filter(
            Q(reservation_code__icontains=search_query) |
            Q(customer__first_name__icontains=search_query) |
            Q(customer__last_name__icontains=search_query) |
            Q(customer__email__icontains=search_query) |
            Q(customer__phone__icontains=search_query) |
            Q(customer__tc_no__icontains=search_query)
        )
    
    # 3. Tarih aralığı filtreleri
    check_in_from = request.GET.get('check_in_from')
    if check_in_from:
        try:
            from datetime import datetime
            check_in_from_date = datetime.strptime(check_in_from, '%Y-%m-%d').date()
            reservations = reservations.filter(check_in_date__gte=check_in_from_date)
        except (ValueError, TypeError):
            pass
    
    check_in_to = request.GET.get('check_in_to')
    if check_in_to:
        try:
            from datetime import datetime
            check_in_to_date = datetime.strptime(check_in_to, '%Y-%m-%d').date()
            reservations = reservations.filter(check_in_date__lte=check_in_to_date)
        except (ValueError, TypeError):
            pass
    
    check_out_from = request.GET.get('check_out_from')
    if check_out_from:
        try:
            from datetime import datetime
            check_out_from_date = datetime.strptime(check_out_from, '%Y-%m-%d').date()
            reservations = reservations.filter(check_out_date__gte=check_out_from_date)
        except (ValueError, TypeError):
            pass
    
    check_out_to = request.GET.get('check_out_to')
    if check_out_to:
        try:
            from datetime import datetime
            check_out_to_date = datetime.strptime(check_out_to, '%Y-%m-%d').date()
            reservations = reservations.filter(check_out_date__lte=check_out_to_date)
        except (ValueError, TypeError):
            pass
    
    # 4. Oda tipi filtresi
    room_filter = request.GET.get('room')
    if room_filter:
        reservations = reservations.filter(room_id=room_filter)
    
    # 5. Rezervasyon kaynağı filtresi
    source_filter = request.GET.get('source')
    if source_filter:
        reservations = reservations.filter(source=source_filter)
    
    # 6. Check-in durumu filtresi
    check_in_status = request.GET.get('check_in_status')
    if check_in_status == 'yes':
        reservations = reservations.filter(is_checked_in=True)
    elif check_in_status == 'no':
        reservations = reservations.filter(is_checked_in=False)
    
    # 7. Check-out durumu filtresi
    check_out_status = request.GET.get('check_out_status')
    if check_out_status == 'yes':
        reservations = reservations.filter(is_checked_out=True)
    elif check_out_status == 'no':
        reservations = reservations.filter(is_checked_out=False)
    
    # 8. Ödeme durumu filtresi
    payment_status = request.GET.get('payment_status')
    if payment_status == 'paid':
        from django.db.models import F
        reservations = reservations.filter(total_paid__gte=F('total_amount'))
    elif payment_status == 'partial':
        from django.db.models import F
        reservations = reservations.filter(
            total_paid__gt=0,
            total_paid__lt=F('total_amount')
        )
    elif payment_status == 'unpaid':
        reservations = reservations.filter(total_paid=0)
    
    # Sayfalama
    paginator = Paginator(reservations, 25)
    page = request.GET.get('page')
    reservations = paginator.get_page(page)
    
    # Form'u context'e ekle (modal için)
    form = ReservationForm(hotel=hotel)
    
    # Oda tipleri (filtre için)
    from apps.tenant_apps.hotels.models import Room
    rooms = Room.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('name')
    
    context = {
        'reservations': reservations,
        'status_choices': ReservationStatus.choices,
        'source_choices': ReservationSource.choices,
        'rooms': rooms,
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
    """Rezervasyon Detayı (Aktif ve Arşivlenmiş)"""
    try:
        hotel = request.active_hotel
    except AttributeError:
        from django.shortcuts import redirect
        from django.contrib import messages
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    try:
        # Arşivlenmiş rezervasyonları da göster
        reservation = get_object_or_404(
            Reservation,
            pk=pk,
            hotel=hotel
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
            customer_folio_url = reverse('tenant:customer_detail', kwargs={'pk': customer.pk})
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
            # Değişiklikleri kaydetmeden önce eski değerleri al (timeline için)
            old_values = {
                'status': reservation.status,
                'check_in_date': str(reservation.check_in_date) if reservation.check_in_date else None,
                'check_out_date': str(reservation.check_out_date) if reservation.check_out_date else None,
                'adult_count': reservation.adult_count,
                'child_count': reservation.child_count,
                'room_rate': float(reservation.room_rate) if reservation.room_rate else 0,
                'total_amount': float(reservation.total_amount) if reservation.total_amount else 0,
            }
            
            reservation = form.save()
            
            # Formset'i kaydet (Django inline formset otomatik olarak reservation'ı atar)
            # Formset save() metodu:
            # - Yeni eklenen misafirleri ekler
            # - Mevcut misafirleri günceller
            # - DELETE işaretlenen misafirleri siler
            guest_formset.save()
            
            # Rezervasyon kaydedildikten sonra total_amount'u yeniden hesapla
            # save() metodu zaten hesaplıyor ama emin olmak için tekrar kaydet
            reservation.save()
            
            # Yeni değerleri al (timeline için)
            new_values = {
                'status': reservation.status,
                'check_in_date': str(reservation.check_in_date) if reservation.check_in_date else None,
                'check_out_date': str(reservation.check_out_date) if reservation.check_out_date else None,
                'adult_count': reservation.adult_count,
                'child_count': reservation.child_count,
                'room_rate': float(reservation.room_rate) if reservation.room_rate else 0,
                'total_amount': float(reservation.total_amount) if reservation.total_amount else 0,
            }
            
            # Değişiklikleri kontrol et ve timeline'a kaydet
            changes = {}
            for key, old_val in old_values.items():
                if old_val != new_values.get(key):
                    changes[key] = {'old': old_val, 'new': new_values.get(key)}
            
            if changes:
                from .models import ReservationTimeline
                ReservationTimeline.objects.create(
                    reservation=reservation,
                    action_type='updated',
                    action_description=f'Rezervasyon güncellendi: {", ".join(changes.keys())} değiştirildi',
                    old_value=old_values,
                    new_value=new_values,
                    user=request.user
                )
            
            # Ödeme bilgilerini kaydet (SADECE yeni ödeme eklendiğinde)
            # Düzenlemede mevcut ödemeleri tekrar kaydetme!
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
                
                # Mevcut toplam ödemeyi kontrol et (güncellenmiş rezervasyondan)
                from .models import ReservationPayment
                reservation.refresh_from_db()  # Rezervasyonu yeniden yükle
                existing_total_paid = reservation.total_paid or Decimal('0')
                
                # SADECE form'daki ön ödeme mevcut toplam ödemeden BÜYÜKSE yeni ödeme ekle
                # Eşitse veya küçükse hiçbir şey yapma (mevcut ödemeleri koru)
                if advance_payment and advance_payment > existing_total_paid and payment_method:
                    # Yeni ödeme tutarı = form'daki ön ödeme - mevcut toplam ödeme
                    new_payment_amount = advance_payment - existing_total_paid
                    
                    if new_payment_amount > 0:
                        ReservationPayment.objects.create(
                            reservation=reservation,
                            payment_date=date.today(),
                            payment_amount=new_payment_amount,
                            payment_method=payment_method,
                            payment_type='advance',
                            currency=reservation.currency,
                            created_by=request.user
                        )
                        reservation.update_total_paid()
                        import logging
                        logger = logging.getLogger(__name__)
                        logger.info(f'Yeni ödeme eklendi: {new_payment_amount} {reservation.currency}, Yöntem: {payment_method}')
                elif advance_payment == existing_total_paid:
                    # Ödeme değişmemiş, hiçbir şey yapma
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.info(f'Ödeme değişmedi, yeni ödeme eklenmedi. Mevcut: {existing_total_paid}, Form: {advance_payment}')
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Ödeme kaydedilirken hata: {str(e)}', exc_info=True)
            
            # messages modülü dosyanın başında import edilmiş, local scope'ta shadow etmemek için
            from django.contrib import messages as django_messages
            django_messages.success(request, 'Rezervasyon başarıyla güncellendi.')
            # AJAX request ise JSON döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                from django.urls import reverse
                return JsonResponse({'success': True, 'redirect_url': reverse('reception:reservation_detail', args=[reservation.pk])})
            return redirect('reception:reservation_detail', pk=reservation.pk)
        else:
            # AJAX request ise hata döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                from django.http import JsonResponse
                import logging
                logger = logging.getLogger(__name__)
                
                errors = {}
                
                # Form hatalarını topla ve logla
                if form.errors:
                    logger.error(f'Form hataları: {form.errors}')
                    errors.update(form.errors)
                
                # Formset hatalarını topla ve logla
                if guest_formset.errors:
                    logger.error(f'Formset hataları: {guest_formset.errors}')
                    errors['guests'] = guest_formset.errors
                
                # Non-field hatalarını topla ve logla
                non_field_errors = []
                if form.non_field_errors():
                    form_nfe = list(form.non_field_errors())
                    logger.error(f'Form non-field hataları: {form_nfe}')
                    non_field_errors.extend(form_nfe)
                if guest_formset.non_form_errors():
                    formset_nfe = list(guest_formset.non_form_errors())
                    logger.error(f'Formset non-form hataları: {formset_nfe}')
                    non_field_errors.extend(formset_nfe)
                
                if non_field_errors:
                    errors['__all__'] = non_field_errors
                
                # Hata mesajı oluştur
                error_messages = []
                for field, field_errors in errors.items():
                    if isinstance(field_errors, list):
                        error_messages.append(f"{field}: {', '.join(str(e) for e in field_errors)}")
                    else:
                        error_messages.append(f"{field}: {str(field_errors)}")
                
                # Tüm hata mesajlarını logla
                logger.error(f'Tüm hata mesajları: {error_messages}')
                
                return JsonResponse({
                    'success': False, 
                    'errors': errors,
                    'message': 'Form gönderilirken hatalar oluştu: ' + '; '.join(error_messages[:10])  # İlk 10 hatayı göster
                })
    else:
        form = ReservationForm(instance=reservation, hotel=hotel)
        # Mevcut misafirleri formset'e yükle
        # Rezervasyonu yeniden yükle (misafirlerin güncel olduğundan emin ol)
        reservation.refresh_from_db()
        
        # Formset'i queryset ile açıkça oluştur (instance yeterli değil)
        from .models import ReservationGuest
        guest_formset = ReservationGuestFormSet(
            instance=reservation,
            queryset=ReservationGuest.objects.filter(reservation=reservation).order_by('guest_type', 'guest_order')
        )
        
        # Debug: Formset'teki misafir sayısını kontrol et
        import logging
        logger = logging.getLogger(__name__)
        logger.info(f'Rezervasyon {reservation.pk} için formset oluşturuldu.')
        logger.info(f'Rezervasyon misafir sayısı: {reservation.guests.count()}')
        logger.info(f'Formset queryset sayısı: {guest_formset.queryset.count()}')
        logger.info(f'Formset form sayısı: {len(guest_formset)}')
        
        # Eğer formset hala boşsa, misafirleri logla
        if len(guest_formset) == 0 and reservation.guests.count() > 0:
            logger.warning(f'Formset boş ama {reservation.guests.count()} misafir var!')
            for guest in reservation.guests.all()[:5]:
                logger.info(f'Misafir: {guest.id} - {guest.first_name} {guest.last_name} (type: {guest.guest_type}, order: {guest.guest_order}, reservation_id: {guest.reservation_id})')
    
    # Misafir bilgileri (JavaScript için JSON)
    import json
    guests_data = []
    try:
        if reservation:
            # Distinct ekle ve sadece aktif misafirleri al (is_deleted yok, tümünü al)
            seen_guest_ids = set()  # Duplicate kontrolü için
            for guest in reservation.guests.all().order_by('guest_type', 'guest_order'):
                # Duplicate kontrolü (aynı ID'ye sahip misafirleri atla)
                if guest.id in seen_guest_ids:
                    continue
                seen_guest_ids.add(guest.id)
                
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
    
    # Ödeme ve Fiyat bilgileri (JavaScript için)
    payment_data = {
        'total_paid': 0,
        'total_amount': 0,
        'remaining_amount': 0,
        'payment_method': '',
        'room_rate': 0,
        'total_nights': 0,
        'discount_amount': 0,
        'discount_percentage': 0,
        'discount_type': '',
        'tax_amount': 0,
        'currency': 'TRY',
    }
    try:
        if reservation:
            payment_data = {
                'total_paid': float(reservation.total_paid) if reservation.total_paid else 0,
                'total_amount': float(reservation.total_amount) if reservation.total_amount else 0,
                'remaining_amount': float(reservation.get_remaining_amount()) if reservation else 0,
                'room_rate': float(reservation.room_rate) if reservation.room_rate else 0,
                'total_nights': int(reservation.total_nights) if reservation.total_nights else 0,
                'discount_amount': float(reservation.discount_amount) if reservation.discount_amount else 0,
                'discount_percentage': float(reservation.discount_percentage) if reservation.discount_percentage else 0,
                'discount_type': reservation.discount_type or '',
                'tax_amount': float(reservation.tax_amount) if reservation.tax_amount else 0,
                'currency': reservation.currency or 'TRY',
            }
            
            # Son ödeme yöntemini al (varsa)
            last_payment = reservation.payments.filter(is_deleted=False).order_by('-payment_date').first()
            if last_payment:
                payment_data['payment_method'] = last_payment.payment_method or ''
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Ödeme ve fiyat bilgileri yüklenirken hata: {str(e)}', exc_info=True)
    
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
    """Rezervasyon Sil (Soft Delete) - İki Aşamalı Onay"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        # İki aşamalı onay kontrolü
        confirm_step = request.POST.get('confirm_step', '1')
        final_confirm = request.POST.get('final_confirm', '')
        
        if confirm_step == '1':
            # İlk onay - sadece onay mesajı döndür
            return JsonResponse({
                'success': True,
                'step': 1,
                'message': 'İlk onay alındı. Son onay için rezervasyon kodunu girin.'
            })
        
        elif confirm_step == '2':
            # İkinci onay - rezervasyon kodu kontrolü
            entered_code = request.POST.get('reservation_code', '').strip()
            
            if entered_code != reservation.reservation_code:
                return JsonResponse({
                    'success': False,
                    'error': 'Rezervasyon kodu eşleşmiyor. Lütfen doğru kodu girin.'
                }, status=400)
            
            if final_confirm != 'DELETE':
                return JsonResponse({
                    'success': False,
                    'error': 'Son onay metni hatalı. Lütfen "DELETE" yazın.'
                }, status=400)
            
            # Silme işlemini gerçekleştir
            reservation.is_deleted = True
            reservation.deleted_by = request.user
            reservation.deleted_at = timezone.now()
            reservation.save()
            
            # Timeline'a kaydet
            from .models import ReservationTimeline
            ReservationTimeline.objects.create(
                reservation=reservation,
                action_type='cancelled',
                action_description=f'Rezervasyon silindi (arşivlendi) - {request.user.get_full_name()} tarafından',
                user=request.user
            )
            
            return JsonResponse({
                'success': True,
                'step': 2,
                'message': 'Rezervasyon başarıyla silindi ve arşivlendi.',
                'redirect_url': reverse('reception:reservation_list')
            })
        
        else:
            return JsonResponse({
                'success': False,
                'error': 'Geçersiz onay adımı.'
            }, status=400)
    
    # GET request - silme modalı için bilgileri döndür
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/delete.html', context)


@login_required
@require_hotel_permission('view')
def reservation_archived_list(request):
    """Silinen Rezervasyonlar (Arşiv)"""
    hotel = request.active_hotel
    
    reservations = Reservation.objects.filter(
        hotel=hotel,
        is_deleted=True
    ).select_related('customer', 'room', 'room_number', 'deleted_by').order_by('-deleted_at', '-check_in_date')
    
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
    
    context = {
        'reservations': reservations,
        'status_choices': ReservationStatus.choices,
        'hotel': hotel,
    }
    
    return render(request, 'reception/reservations/archived_list.html', context)


@login_required
@require_hotel_permission('delete')
def reservation_restore(request, pk):
    """Rezervasyonu Geri Al / Aktifleştir"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=True  # Sadece silinen rezervasyonlar geri alınabilir
    )
    
    if request.method == 'POST':
        # İki aşamalı onay kontrolü
        confirm_step = request.POST.get('confirm_step', '1')
        final_confirm = request.POST.get('final_confirm', '')
        
        if confirm_step == '1':
            # İlk onay - sadece onay mesajı döndür
            return JsonResponse({
                'success': True,
                'step': 1,
                'message': 'İlk onay alındı. Son onay için rezervasyon kodunu girin.'
            })
        
        elif confirm_step == '2':
            # İkinci onay - rezervasyon kodu kontrolü
            entered_code = request.POST.get('reservation_code', '').strip()
            
            if entered_code != reservation.reservation_code:
                return JsonResponse({
                    'success': False,
                    'error': 'Rezervasyon kodu eşleşmiyor. Lütfen doğru kodu girin.'
                }, status=400)
            
            if final_confirm != 'RESTORE':
                return JsonResponse({
                    'success': False,
                    'error': 'Son onay metni hatalı. Lütfen "RESTORE" yazın.'
                }, status=400)
            
            # Geri alma işlemini gerçekleştir
            reservation.is_deleted = False
            reservation.deleted_by = None
            reservation.deleted_at = None
            reservation.save()
            
            # Timeline'a kaydet
            from .models import ReservationTimeline
            ReservationTimeline.objects.create(
                reservation=reservation,
                action_type='status_changed',
                action_description=f'Rezervasyon geri alındı (aktifleştirildi) - {request.user.get_full_name()} tarafından',
                user=request.user
            )
            
            return JsonResponse({
                'success': True,
                'step': 2,
                'message': 'Rezervasyon başarıyla geri alındı ve aktifleştirildi.',
                'redirect_url': reverse('reception:reservation_detail', kwargs={'pk': reservation.pk})
            })
        
        else:
            return JsonResponse({
                'success': False,
                'error': 'Geçersiz onay adımı.'
            }, status=400)
    
    # GET request - geri alma modalı için bilgileri döndür
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/restore.html', context)


@login_required
@require_hotel_permission('manage')
def reservation_refund(request, pk):
    """Rezervasyon İadesi - İade Yönetimi Modülü ile Entegre"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        refund_amount = Decimal(request.POST.get('refund_amount', '0'))
        refund_method = request.POST.get('refund_method', '')
        refund_reason = request.POST.get('refund_reason', '')
        
        if refund_amount > 0 and refund_method:
            try:
                from django.db import transaction as db_transaction
                from apps.tenant_apps.refunds.utils import create_refund_request, process_refund
                from apps.tenant_apps.refunds.models import RefundRequest
                from .models import ReservationPayment
                
                # İade tutarını kontrol et (toplam ödenen tutardan fazla olamaz)
                if refund_amount > reservation.total_paid:
                    messages.error(request, f'İade tutarı toplam ödenen tutardan ({reservation.total_paid} {reservation.currency}) fazla olamaz.')
                    context = {
                        'reservation': reservation,
                    }
                    return render(request, 'reception/reservations/refund.html', context)
                
                # İade politikasını bul (reception modülü için)
                from apps.tenant_apps.refunds.utils import get_refund_policy
                refund_policy = get_refund_policy('reception', booking_date=reservation.created_at.date() if reservation.created_at else date.today())
                
                # Müşteri bilgilerini hazırla
                customer_name = f"{reservation.customer.first_name} {reservation.customer.last_name}" if reservation.customer else "Bilinmeyen Müşteri"
                customer_email = reservation.customer.email if reservation.customer and reservation.customer.email else ""
                customer_phone = reservation.customer.phone if reservation.customer and reservation.customer.phone else ""
                
                # Orijinal ödeme bilgilerini bul (en son ödeme)
                last_payment = reservation.payments.filter(
                    payment_type__in=['advance', 'full', 'partial'],
                    is_deleted=False
                ).order_by('-payment_date', '-id').first()
                
                original_payment_method = last_payment.payment_method if last_payment else ''
                original_payment_date = last_payment.payment_date if last_payment else reservation.created_at.date() if reservation.created_at else date.today()
                
                with db_transaction.atomic():
                    # 1. İade talebi oluştur (otomatik onaylanmış olarak)
                    refund_request = create_refund_request(
                        source_module='reception',
                        source_id=reservation.pk,
                        source_reference=reservation.reservation_code,
                        customer_name=customer_name,
                        customer_email=customer_email,
                        original_amount=reservation.total_paid,
                        original_payment_method=original_payment_method,
                        original_payment_date=original_payment_date,
                        reason=refund_reason or 'Rezervasyon iadesi',
                        customer_phone=customer_phone,
                        created_by=request.user,
                        refund_policy_id=refund_policy.pk if refund_policy else None,
                        customer=reservation.customer if reservation.customer else None,
                    )
                    
                    # Manuel iade tutarını ayarla (politika hesaplamasını override et)
                    refund_request.refund_amount = refund_amount
                    refund_request.net_refund = refund_amount  # İşlem ücreti yoksa net = brut
                    refund_request.refund_method = refund_method
                    refund_request.status = 'approved'  # Otomatik onayla
                    refund_request.approved_by = request.user
                    refund_request.approved_at = timezone.now()
                    refund_request.save()
                    
                    # 2. İade işlemini gerçekleştir
                    refund_transaction = process_refund(
                        refund_request_id=refund_request.pk,
                        processed_by=request.user,
                        payment_reference=f'REF-{reservation.reservation_code}',
                    )
                    
                    # 3. ReservationPayment olarak kaydet (geriye dönük uyumluluk için)
                    reservation_payment = ReservationPayment.objects.create(
                        reservation=reservation,
                        payment_date=date.today(),
                        payment_amount=-refund_amount,  # Negatif tutar (iade)
                        payment_method=refund_method,
                        payment_type='refund',
                        currency=reservation.currency,
                        notes=f'İade: {refund_reason} | İade Talebi: {refund_request.request_number}',
                        created_by=request.user
                    )
                    
                    # 4. RefundTransaction ile ReservationPayment'i ilişkilendir
                    refund_transaction.notes = f"Rezervasyon Ödeme ID: {reservation_payment.pk}"
                    refund_transaction.save()
                    
                    # 5. Rezervasyon toplam ödemesini güncelle
                    reservation.update_total_paid()
                    
                    # 6. Timeline'a kaydet
                    from .models import ReservationTimeline
                    ReservationTimeline.objects.create(
                        reservation=reservation,
                        action_type='payment',
                        action_description=f'İade yapıldı: {refund_amount} {reservation.currency} - {refund_reason} | İade Talebi: {refund_request.request_number}',
                        user=request.user
                    )
                    
                    # 7. İade işlemini tamamla
                    refund_transaction.status = 'completed'
                    refund_transaction.status_changed_at = timezone.now()
                    refund_transaction.save()
                    
                    refund_request.complete(user=request.user, notes=f'Rezervasyon iadesi tamamlandı: {reservation.reservation_code}')
                
                messages.success(request, f'İade başarıyla yapıldı: {refund_amount} {reservation.currency} | İade Talebi: {refund_request.request_number}')
                return redirect('reception:reservation_detail', pk=reservation.pk)
                
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'İade işlemi sırasında hata: {str(e)}', exc_info=True)
                messages.error(request, f'İade işlemi sırasında bir hata oluştu: {str(e)}')
        else:
            messages.error(request, 'Lütfen geçerli bir iade tutarı ve yöntemi girin.')
    
    # İade politikasını context'e ekle
    from apps.tenant_apps.refunds.utils import get_refund_policy
    refund_policy = get_refund_policy('reception', booking_date=reservation.created_at.date() if reservation.created_at else date.today())
    
    context = {
        'reservation': reservation,
        'refund_policy': refund_policy,
    }
    return render(request, 'reception/reservations/refund.html', context)


@login_required
@require_hotel_permission('manage')
def reservation_status_change(request, pk):
    """Rezervasyon Durum Değiştirme"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        new_status = request.POST.get('new_status')
        status_reason = request.POST.get('status_reason', '')
        
        if new_status and new_status in dict(ReservationStatus.choices):
            old_status = reservation.status
            reservation.status = new_status
            reservation.save()
            
            # Timeline'a kaydet
            from .models import ReservationTimeline
            ReservationTimeline.objects.create(
                reservation=reservation,
                action_type='status_changed',
                action_description=f'Durum değiştirildi: {reservation.get_status_display()} - {status_reason}',
                old_value={'status': old_status},
                new_value={'status': new_status},
                user=request.user
            )
            
            messages.success(request, f'Rezervasyon durumu {reservation.get_status_display()} olarak güncellendi.')
            return redirect('reception:reservation_detail', pk=reservation.pk)
        else:
            messages.error(request, 'Geçersiz durum seçildi.')
    
    context = {
        'reservation': reservation,
        'status_choices': ReservationStatus.choices,
    }
    return render(request, 'reception/reservations/status_change.html', context)


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
    
    import logging
    logger = logging.getLogger(__name__)
    
    # Varsayılan değerler
    floors = []
    floor_rooms = {}
    room_reservations = {}
    blocks = []
    
    # Katlar
    try:
        from apps.tenant_apps.hotels.models import Floor, RoomNumber, RoomNumberStatus, Block
        
        # Önce tüm katları kontrol et (aktif/pasif fark etmeksizin debug için)
        all_floors = Floor.objects.filter(hotel=hotel, is_deleted=False).order_by('floor_number')
        logger.info(f'Oda planı - Tüm kat sayısı (silinmemiş): {all_floors.count()}, Otel: {hotel.name}')
        
        # Aktif katları getir
        floors = Floor.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).order_by('floor_number')
        
        logger.info(f'Oda planı - Aktif kat sayısı: {floors.count()}, Otel: {hotel.name}')
        
        if floors.count() == 0:
            logger.warning(f'Oda planı - Aktif kat bulunamadı! Otel: {hotel.name}, Tüm katlar: {all_floors.count()}')
            # Aktif kat yoksa, pasif katları da göster (debug için)
            floors = all_floors
            logger.info(f'Oda planı - Pasif katlar dahil edildi: {floors.count()}')
        
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
        logger.info(f'Oda planı - Aktif rezervasyon sayısı: {len(room_reservations)}')
        
        # Her kat için oda numaraları
        floor_rooms = {}
        for floor in floors:
            # Önce tüm odaları kontrol et (aktif/pasif fark etmeksizin)
            all_rooms = RoomNumber.objects.filter(hotel=hotel, floor=floor, is_deleted=False)
            logger.info(f'Kat {floor.name} (ID: {floor.id}) - Tüm oda sayısı (silinmemiş): {all_rooms.count()}')
            
            # Aktif odaları getir
            rooms = RoomNumber.objects.filter(
                hotel=hotel,
                floor=floor,
                is_active=True,
                is_deleted=False
            ).select_related('room', 'block').order_by('number')
            
            logger.info(f'Kat {floor.name} (ID: {floor.id}) - Aktif oda sayısı: {rooms.count()}')
            
            if rooms.count() == 0:
                logger.warning(f'Kat {floor.name} (ID: {floor.id}) - Aktif oda bulunamadı! Tüm odalar: {all_rooms.count()}')
                # Aktif oda yoksa, pasif odaları da göster (debug için)
                rooms = all_rooms.filter(is_deleted=False).select_related('room', 'block').order_by('number')
                logger.info(f'Kat {floor.name} - Pasif odalar dahil edildi: {rooms.count()}')
            
            # Her oda için rezervasyon bilgisini ekle
            rooms_with_reservations = []
            for room in rooms:
                reservation = room_reservations.get(room.id)
                rooms_with_reservations.append({
                    'room': room,
                    'reservation': reservation,
                })
            
            # floor.id ile key kullan (template'te floor.id ile erişiliyor)
            floor_rooms[floor.id] = rooms_with_reservations
        
        # Bloklar
        blocks = Block.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).order_by('name')
        
    except Exception as e:
        logger.error(f'Oda planı görüntüleme hatası: {str(e)}', exc_info=True)
    
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
@require_hotel_permission('view')
def room_status_dashboard(request):
    """Oda Durum Panosu - Doluluk oranı, temizlik durumları, forecast"""
    hotel = request.active_hotel
    today = date.today()
    
    try:
        from apps.tenant_apps.hotels.models import RoomNumber, RoomNumberStatus, Floor, Room
        from django.db.models import Count, Q, Avg, Sum
        from datetime import timedelta
        
        # Tarih filtreleri
        forecast_days = int(request.GET.get('forecast_days', 30))
        start_date = request.GET.get('start_date')
        end_date = request.GET.get('end_date')
        
        if start_date:
            try:
                start_date = date.fromisoformat(start_date)
            except:
                start_date = today
        else:
            start_date = today
            
        if end_date:
            try:
                end_date = date.fromisoformat(end_date)
            except:
                end_date = today + timedelta(days=forecast_days)
        else:
            end_date = today + timedelta(days=forecast_days)
        
        # Tüm odalar
        all_rooms = RoomNumber.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).select_related('room', 'floor', 'block').order_by('floor__floor_number', 'number')
        
        # Oda durum istatistikleri
        status_stats = all_rooms.values('status').annotate(count=Count('id'))
        
        # Her oda için detaylı bilgiler
        rooms_data = []
        for room in all_rooms:
            # Mevcut rezervasyon
            current_reservation = Reservation.objects.filter(
                hotel=hotel,
                room_number=room,
                check_in_date__lte=today,
                check_out_date__gte=today,
                is_deleted=False
            ).select_related('customer').first()
            
            # Yaklaşan rezervasyonlar (forecast dönemi içinde)
            upcoming_reservations = Reservation.objects.filter(
                hotel=hotel,
                room_number=room,
                check_in_date__gte=today,
                check_in_date__lte=end_date,
                is_deleted=False
            ).select_related('customer').order_by('check_in_date')[:5]
            
            # Geçmiş rezervasyonlar (doluluk hesaplama için)
            past_reservations = Reservation.objects.filter(
                hotel=hotel,
                room_number=room,
                check_in_date__gte=start_date - timedelta(days=30),
                check_out_date__lte=today,
                is_deleted=False
            )
            
            # Doluluk oranı hesaplama (son 30 gün)
            occupancy_days = 0
            for res in past_reservations:
                occupancy_days += (res.check_out_date - res.check_in_date).days
            
            total_days = 30
            occupancy_rate = (occupancy_days / total_days * 100) if total_days > 0 else 0
            
            rooms_data.append({
                'room': room,
                'current_reservation': current_reservation,
                'upcoming_reservations': upcoming_reservations,
                'occupancy_rate': round(occupancy_rate, 1),
                'occupancy_days': occupancy_days,
            })
        
        # Genel istatistikler
        total_rooms = all_rooms.count()
        occupied_rooms = all_rooms.filter(status=RoomNumberStatus.OCCUPIED).count()
        available_rooms = all_rooms.filter(status=RoomNumberStatus.AVAILABLE).count()
        cleaning_rooms = all_rooms.filter(status__in=['cleaning', 'cleaning_pending']).count()
        maintenance_rooms = all_rooms.filter(status=RoomNumberStatus.MAINTENANCE).count()
        
        # Forecast: Gelecek dönem için tahmini doluluk
        forecast_reservations = Reservation.objects.filter(
            hotel=hotel,
            check_in_date__gte=today,
            check_in_date__lte=end_date,
            is_deleted=False
        ).count()
        
        # Katlar ve oda tipleri (filtre için)
        floors = Floor.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('floor_number')
        room_types = Room.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('name')
        
        # Filtreleme
        floor_filter = request.GET.get('floor')
        room_type_filter = request.GET.get('room_type')
        status_filter = request.GET.get('status')
        
        if floor_filter:
            rooms_data = [r for r in rooms_data if r['room'].floor_id == int(floor_filter)]
        if room_type_filter:
            rooms_data = [r for r in rooms_data if r['room'].room_id == int(room_type_filter)]
        if status_filter:
            rooms_data = [r for r in rooms_data if r['room'].status == status_filter]
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Oda durum panosu hatası: {str(e)}', exc_info=True)
        rooms_data = []
        status_stats = []
        floors = []
        room_types = []
        total_rooms = 0
        occupied_rooms = 0
        available_rooms = 0
        cleaning_rooms = 0
        maintenance_rooms = 0
        forecast_reservations = 0
        start_date = today
        end_date = today + timedelta(days=30)
    
    context = {
        'hotel': hotel,
        'rooms_data': rooms_data,
        'status_stats': status_stats,
        'floors': floors,
        'room_types': room_types,
        'total_rooms': total_rooms,
        'occupied_rooms': occupied_rooms,
        'available_rooms': available_rooms,
        'cleaning_rooms': cleaning_rooms,
        'maintenance_rooms': maintenance_rooms,
        'forecast_reservations': forecast_reservations,
        'start_date': start_date,
        'end_date': end_date,
        'today': today,
        'current_floor': request.GET.get('floor'),
        'current_room_type': request.GET.get('room_type'),
        'current_status': request.GET.get('status'),
        'forecast_days': forecast_days,
    }
    
    return render(request, 'reception/room_status_dashboard.html', context)


@login_required
@require_hotel_permission('view')
def room_calendar_view(request):
    """Takvim Tabanlı Oda Planı Görünümü"""
    hotel = request.active_hotel
    today = date.today()
    
    try:
        from apps.tenant_apps.hotels.models import RoomNumber, Floor
        from calendar import monthrange
        from datetime import datetime
        
        # Ay navigasyonu
        year = int(request.GET.get('year', today.year))
        month = int(request.GET.get('month', today.month))
        
        # Ay sınırları
        first_day = date(year, month, 1)
        last_day = date(year, month, monthrange(year, month)[1])
        
        # Ayın tüm günlerini oluştur
        days_of_month = []
        current_date = first_day
        while current_date <= last_day:
            days_of_month.append(current_date)
            current_date += timedelta(days=1)
        
        # Önceki/Sonraki ay
        if month == 1:
            prev_month = 12
            prev_year = year - 1
        else:
            prev_month = month - 1
            prev_year = year
            
        if month == 12:
            next_month = 1
            next_year = year + 1
        else:
            next_month = month + 1
            next_year = year
        
        # Tüm odalar
        all_rooms = RoomNumber.objects.filter(
            hotel=hotel,
            is_active=True,
            is_deleted=False
        ).select_related('room', 'floor', 'block').order_by('floor__floor_number', 'number')
        
        # Ay içindeki tüm rezervasyonlar
        month_reservations = Reservation.objects.filter(
            hotel=hotel,
            check_in_date__lte=last_day,
            check_out_date__gte=first_day,
            is_deleted=False
        ).select_related('customer', 'room_number', 'room')
        
        # Her oda için günlük durum
        rooms_calendar = []
        for room in all_rooms:
            daily_status = {}
            
            # Ayın her günü için durum belirle
            current_date = first_day
            while current_date <= last_day:
                # Dictionary key olarak string kullan (template'te erişim için)
                date_key = current_date.strftime('%Y-%m-%d')
                
                # Bu gün için rezervasyon var mı?
                reservation = month_reservations.filter(
                    room_number=room,
                    check_in_date__lte=current_date,
                    check_out_date__gte=current_date
                ).first()
                
                if reservation:
                    daily_status[date_key] = {
                        'reservation': reservation,
                        'status': 'occupied',
                        'is_checkin': reservation.check_in_date == current_date,
                        'is_checkout': reservation.check_out_date == current_date,
                    }
                else:
                    # Oda durumuna göre
                    daily_status[date_key] = {
                        'reservation': None,
                        'status': room.status,
                    }
                
                current_date += timedelta(days=1)
            
            rooms_calendar.append({
                'room': room,
                'daily_status': daily_status,
            })
        
        # Katlar (filtre için)
        floors = Floor.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('floor_number')
        
        # Filtreleme
        floor_filter = request.GET.get('floor')
        if floor_filter:
            rooms_calendar = [r for r in rooms_calendar if r['room'].floor_id == int(floor_filter)]
        
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Takvim görünümü hatası: {str(e)}', exc_info=True)
        rooms_calendar = []
        floors = []
        days_of_month = []
        year = today.year
        month = today.month
        first_day = today
        last_day = today
        prev_year = year
        prev_month = month
        next_year = year
        next_month = month
    
    context = {
        'hotel': hotel,
        'rooms_calendar': rooms_calendar,
        'floors': floors,
        'year': year,
        'month': month,
        'first_day': first_day,
        'last_day': last_day,
        'days_of_month': days_of_month,
        'prev_year': prev_year,
        'prev_month': prev_month,
        'next_year': next_year,
        'next_month': next_month,
        'today': today,
        'current_floor': request.GET.get('floor'),
    }
    
    return render(request, 'reception/room_calendar.html', context)


@login_required
@require_hotel_permission('view')
def api_room_detail(request, room_number_id):
    """Oda detay bilgileri API"""
    from django.http import JsonResponse
    from datetime import date
    
    try:
        from apps.tenant_apps.hotels.models import RoomNumber
        hotel = request.active_hotel
        
        room_number = RoomNumber.objects.select_related(
            'room', 'floor', 'block', 'hotel'
        ).get(
            pk=room_number_id,
            hotel=hotel,
            is_deleted=False
        )
        
        # Oda bilgileri
        room_data = {
            'id': room_number.id,
            'number': room_number.number,
            'status': room_number.status,
            'status_display': room_number.get_status_display(),
            'notes': room_number.notes or '',
            'is_active': room_number.is_active,
        }
        
        # Oda tipi bilgileri
        if room_number.room:
            bed_type = getattr(room_number.room, 'bed_type', None)
            bed_type_name = bed_type.name if bed_type else None
            room_data['room_type'] = {
                'id': room_number.room.id,
                'name': room_number.room.name,
                'capacity': getattr(room_number.room, 'capacity', None),
                'bed_type': bed_type_name,
            }
        else:
            room_data['room_type'] = None
        
        # Kat bilgileri
        if room_number.floor:
            room_data['floor'] = {
                'id': room_number.floor.id,
                'name': room_number.floor.name,
                'floor_number': room_number.floor.floor_number,
            }
        else:
            room_data['floor'] = None
        
        # Blok bilgileri
        if room_number.block:
            room_data['block'] = {
                'id': room_number.block.id,
                'name': room_number.block.name,
                'code': getattr(room_number.block, 'code', ''),
            }
        else:
            room_data['block'] = None
        
        # Bugünkü rezervasyon
        today = date.today()
        current_reservation = Reservation.objects.filter(
            hotel=hotel,
            room_number=room_number,
            check_in_date__lte=today,
            check_out_date__gte=today,
            is_deleted=False
        ).select_related('customer', 'room').first()
        
        reservation_data = None
        if current_reservation:
            try:
                # Müşteri bilgileri
                customer = current_reservation.customer
                
                # Kalan tutarı hesapla
                try:
                    remaining_amount = float(current_reservation.get_remaining_amount())
                except:
                    total_paid = float(current_reservation.total_paid) if current_reservation.total_paid else 0
                    total_amount = float(current_reservation.total_amount) if current_reservation.total_amount else 0
                    remaining_amount = total_amount - total_paid
                
                reservation_data = {
                    'id': current_reservation.id,
                    'reservation_code': current_reservation.reservation_code,
                    'check_in_date': current_reservation.check_in_date.strftime('%d.%m.%Y') if current_reservation.check_in_date else None,
                    'check_out_date': current_reservation.check_out_date.strftime('%d.%m.%Y') if current_reservation.check_out_date else None,
                    'check_in_time': str(current_reservation.check_in_time) if current_reservation.check_in_time else None,
                    'check_out_time': str(current_reservation.check_out_time) if current_reservation.check_out_time else None,
                    'total_nights': current_reservation.total_nights or 0,
                    'adult_count': current_reservation.adult_count or 0,
                    'child_count': current_reservation.child_count or 0,
                    'total_amount': float(current_reservation.total_amount) if current_reservation.total_amount else 0,
                    'currency': current_reservation.currency or 'TRY',
                    'status': current_reservation.status or 'pending',
                    'status_display': current_reservation.get_status_display() if hasattr(current_reservation, 'get_status_display') else 'Beklemede',
                    'remaining_amount': remaining_amount,
                    'total_paid': float(current_reservation.total_paid) if current_reservation.total_paid else 0,
                    'notes': getattr(current_reservation, 'internal_notes', '') or getattr(current_reservation, 'notes', '') or '',
                    'customer': {
                        'id': customer.id if customer else None,
                        'first_name': customer.first_name if customer else '',
                        'last_name': customer.last_name if customer else '',
                        'full_name': customer.get_full_name() if customer and hasattr(customer, 'get_full_name') else (f"{customer.first_name} {customer.last_name}" if customer else ''),
                        'email': customer.email or '' if customer else '',
                        'phone': customer.phone or '' if customer else '',
                        'tc_no': customer.tc_no or '' if customer else '',
                    } if customer else None,
                }
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Rezervasyon verisi hazırlanırken hata: {str(e)}', exc_info=True)
                reservation_data = {
                    'id': current_reservation.id,
                    'reservation_code': current_reservation.reservation_code,
                    'error': f'Rezervasyon verisi yüklenirken hata: {str(e)}'
                }
            
            # Ödemeler (reservation_data varsa)
            if reservation_data:
                try:
                    payments = current_reservation.payments.filter(is_deleted=False).order_by('-payment_date') if hasattr(current_reservation, 'payments') else []
                    reservation_data['payments'] = [
                        {
                            'id': p.id,
                            'amount': float(p.payment_amount) if hasattr(p, 'payment_amount') else 0,
                            'currency': p.currency or 'TRY',
                            'payment_method': p.payment_method or '',
                            'payment_method_display': p.get_payment_method_display() if hasattr(p, 'get_payment_method_display') else (p.payment_method or ''),
                            'payment_date': p.payment_date.strftime('%d.%m.%Y') if p.payment_date else None,
                            'payment_type': p.payment_type or '',
                            'payment_type_display': p.get_payment_type_display() if hasattr(p, 'get_payment_type_display') else (p.payment_type or ''),
                        }
                        for p in payments
                    ]
                    
                    # Toplam ödenen (tüm ödemelerin toplamı)
                    total_paid = sum(float(p.payment_amount) for p in payments if hasattr(p, 'payment_amount'))
                    reservation_data['total_paid'] = total_paid
                except Exception as e:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.error(f'Ödeme bilgileri yüklenirken hata: {str(e)}', exc_info=True)
                    if reservation_data:
                        reservation_data['payments'] = []
                        reservation_data['total_paid'] = float(current_reservation.total_paid) if current_reservation.total_paid else 0
            
            # Misafirler (reservation_data varsa)
            if reservation_data:
                try:
                    # ReservationGuest modelinde is_deleted yok, direkt filter yap
                    guests = current_reservation.guests.all() if hasattr(current_reservation, 'guests') else []
                    reservation_data['guests'] = [
                        {
                            'id': g.id,
                            'first_name': g.first_name or '',
                            'last_name': g.last_name or '',
                            'is_adult': g.guest_type == 'adult' if hasattr(g, 'guest_type') else True,
                            'age': g.age if g.age else None,
                            'tc_no': g.tc_no or '',
                            'passport_no': g.passport_no or '',
                        }
                        for g in guests
                    ]
                except Exception as e:
                    import logging
                    logger = logging.getLogger(__name__)
                    logger.error(f'Misafir bilgileri yüklenirken hata: {str(e)}', exc_info=True)
                    if reservation_data:
                        reservation_data['guests'] = []
        
        # Geçmiş rezervasyonlar (son 10)
        past_reservations = Reservation.objects.filter(
            hotel=hotel,
            room_number=room_number,
            is_deleted=False
        ).exclude(
            check_out_date__gte=today
        ).select_related('customer').order_by('-check_out_date')[:10]
        
        past_reservations_data = [
            {
                'id': r.id,
                'reservation_code': r.reservation_code,
                'check_in_date': r.check_in_date.strftime('%d.%m.%Y'),
                'check_out_date': r.check_out_date.strftime('%d.%m.%Y'),
                'customer_name': r.customer.get_full_name() if r.customer else 'Müşteri Yok',
                'status': r.status,
                'status_display': r.get_status_display(),
            }
            for r in past_reservations
        ]
        
        return JsonResponse({
            'success': True,
            'room': room_data,
            'current_reservation': reservation_data,
            'past_reservations': past_reservations_data,
        })
        
    except RoomNumber.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'Oda bulunamadı.'}, status=404)
    except Exception as e:
        import logging
        import traceback
        logger = logging.getLogger(__name__)
        error_message = str(e)
        error_traceback = traceback.format_exc()
        logger.error(f'Oda detay API hatası: {error_message}\n{error_traceback}')
        # Güvenli hata mesajı (detaylı bilgi sadece log'da)
        return JsonResponse({
            'success': False, 
            'error': f'Oda bilgileri yüklenirken bir hata oluştu: {error_message}'
        }, status=500)


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
    try:
        hotel = request.active_hotel
        if not hotel:
            messages.error(request, 'Aktif otel seçilmedi.')
            return redirect('hotels:select_hotel')
    except AttributeError:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
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
        template = VoucherTemplate.objects.filter(pk=template_id, is_active=True, is_deleted=False).first()
    
    try:
        from .utils import create_reservation_voucher
        import logging
        logger = logging.getLogger(__name__)
        
        logger.info(f'Voucher oluşturuluyor - Rezervasyon: {reservation.reservation_code}, Template: {template.pk if template else "Varsayılan"}')
        
        # Voucher oluştur
        voucher = create_reservation_voucher(reservation, template=template)
        
        if not voucher:
            raise ValueError('Voucher oluşturulamadı. create_reservation_voucher None döndü.')
        
        logger.info(f'Voucher başarıyla oluşturuldu - ID: {voucher.pk}, Kod: {voucher.voucher_code}')
        
        # Voucher bildirimi gönder (signal'da otomatik gönderilecek)
        if request.GET.get('send_notification') == '1':
            try:
                from .utils_notifications import send_voucher_notification
                send_voucher_notification(voucher, notification_type='whatsapp')
                send_voucher_notification(voucher, notification_type='sms')
            except ImportError:
                logger.warning('utils_notifications modülü bulunamadı, bildirim gönderilmedi.')
            except Exception as e:
                logger.error(f'Voucher bildirimi gönderilirken hata: {str(e)}', exc_info=True)
        
        messages.success(request, f'Voucher başarıyla oluşturuldu. (Kod: {voucher.voucher_code})')
        return redirect('reception:reservation_voucher_detail', pk=voucher.pk)
    except Exception as e:
        import logging
        import traceback
        logger = logging.getLogger(__name__)
        error_trace = traceback.format_exc()
        logger.error(f'Voucher oluşturulurken hata: {str(e)}\n{error_trace}')
        messages.error(request, f'Voucher oluşturulurken hata oluştu: {str(e)}')
        return redirect('reception:reservation_detail', pk=reservation.pk)


@login_required
@require_hotel_permission('view')
def reservation_voucher_detail(request, pk):
    """Voucher detay ve görüntüleme"""
    try:
        hotel = request.active_hotel
        if not hotel:
            messages.error(request, 'Aktif otel seçilmedi.')
            return redirect('hotels:select_hotel')
    except AttributeError:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    from .models import ReservationVoucher
    voucher = get_object_or_404(ReservationVoucher, pk=pk)
    
    # Voucher'ın rezervasyonunun otel kontrolü
    if voucher.reservation.hotel != hotel:
        messages.error(request, 'Bu voucher\'a erişim yetkiniz yok.')
        return redirect('reception:reservation_list')
    
    # Voucher HTML'ini oluştur
    try:
        from .utils import generate_reservation_voucher
        voucher_html, _ = generate_reservation_voucher(voucher.reservation, voucher.voucher_template)
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        voucher_html = f'<p>Voucher HTML oluşturulurken hata oluştu: {str(e)}</p>'
    
    context = {
        'voucher': voucher,
        'voucher_html': voucher_html,
        'hotel': hotel,
    }
    
    return render(request, 'reception/vouchers/detail.html', context)


@login_required
@require_hotel_permission('view')
def reservation_voucher_pdf(request, pk):
    """Voucher PDF olarak indir"""
    from .models import ReservationVoucher
    from django.http import HttpResponse
    import logging
    logger = logging.getLogger(__name__)
    
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
        # weasyprint yoksa HTML döndür (indir butonu ile)
        logger.warning('weasyprint bulunamadı, HTML olarak döndürülüyor')
        return render(request, 'reception/vouchers/pdf_view.html', {
            'voucher': voucher,
            'voucher_html': voucher_html,
        })


@login_required
@require_hotel_permission('view')
def voucher_send(request, pk):
    """Voucher gönderme (WhatsApp/Email)"""
    from .models import ReservationVoucher
    from django.http import JsonResponse
    import logging
    logger = logging.getLogger(__name__)
    
    voucher = get_object_or_404(ReservationVoucher, pk=pk)
    method = request.GET.get('method', 'email')  # whatsapp, email, sms
    
    try:
        if method == 'whatsapp':
            # WhatsApp için sadece durumu güncelle (wa.me linki zaten açıldı)
            voucher.is_sent = True
            voucher.sent_at = timezone.now()
            voucher.sent_via = 'whatsapp'
            voucher.save()
            logger.info(f'Voucher WhatsApp ile gönderildi: {voucher.voucher_code}')
            return JsonResponse({'success': True, 'message': 'WhatsApp linki açıldı'})
        
        elif method == 'email':
            # Email gönder
            from django.core.mail import send_mail
            from django.conf import settings
            
            customer = voucher.reservation.customer
            if not customer or not customer.email:
                return JsonResponse({'success': False, 'error': 'Müşteri email adresi bulunamadı'})
            
            subject = voucher.get_email_subject()
            body = voucher.get_email_body()
            
            # Email gönder
            send_mail(
                subject=subject,
                message=body,
                from_email=settings.DEFAULT_FROM_EMAIL,
                recipient_list=[customer.email],
                fail_silently=False,
            )
            
            # Durumu güncelle
            voucher.is_sent = True
            voucher.sent_at = timezone.now()
            voucher.sent_via = 'email'
            voucher.save()
            
            logger.info(f'Voucher email ile gönderildi: {voucher.voucher_code} -> {customer.email}')
            return JsonResponse({'success': True, 'message': 'Email başarıyla gönderildi'})
        
        elif method == 'link':
            # Link gönderimi (zaten kopyalandı)
            voucher.is_sent = True
            voucher.sent_at = timezone.now()
            voucher.sent_via = 'link'
            voucher.save()
            return JsonResponse({'success': True, 'message': 'Link kopyalandı'})
        
        else:
            return JsonResponse({'success': False, 'error': 'Geçersiz gönderim yöntemi'})
    
    except Exception as e:
        logger.error(f'Voucher gönderilirken hata: {str(e)}', exc_info=True)
        return JsonResponse({'success': False, 'error': str(e)})


def voucher_view(request, token):
    """Voucher görüntüleme (Token ile - Public)"""
    from .models import ReservationVoucher
    from django.utils import timezone
    
    voucher = get_object_or_404(ReservationVoucher, access_token=token)
    
    # Token geçerlilik kontrolü
    if voucher.token_expires_at and voucher.token_expires_at < timezone.now():
        return render(request, 'reception/vouchers/expired.html', {
            'voucher': voucher
        })
    
    # Voucher HTML'ini oluştur
    from .utils import generate_reservation_voucher
    try:
        voucher_html, _ = generate_reservation_voucher(voucher.reservation, voucher.voucher_template)
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        voucher_html = f'<p>Voucher yüklenirken bir hata oluştu.</p>'
    
    context = {
        'voucher': voucher,
        'voucher_html': voucher_html,
        'reservation': voucher.reservation,
        'token': token,  # Token'ı context'e ekle
    }
    
    return render(request, 'reception/vouchers/public_view.html', context)


@require_http_methods(["GET", "POST"])
def voucher_payment(request, token):
    """Voucher ödeme sayfası (Token ile - Public)"""
    from .models import ReservationVoucher
    from django.utils import timezone
    from decimal import Decimal
    
    voucher = get_object_or_404(ReservationVoucher, access_token=token)
    
    # Token geçerlilik kontrolü
    if voucher.token_expires_at and voucher.token_expires_at < timezone.now():
        return render(request, 'reception/vouchers/expired.html', {
            'voucher': voucher
        })
    
    # Ödeme durumu kontrolü
    if voucher.payment_status == 'paid':
        messages.info(request, 'Bu voucher için ödeme zaten yapılmış.')
        return redirect('reception:voucher_view', token=token)
    
    reservation = voucher.reservation
    payment_amount = voucher.calculate_payment_amount()
    
    if request.method == 'POST':
        # Ödeme başlat
        gateway_code = request.POST.get('gateway', 'iyzico')
        payment_method = request.POST.get('payment_method', 'credit_card')
        
        try:
            # Payment gateway'i bul
            from apps.payments.models import PaymentGateway, TenantPaymentGateway
            from apps.tenants.models import Tenant
            from django_tenants.utils import get_tenant_model
            
            # Tenant'ı bul (voucher'ın rezervasyonundan)
            tenant = reservation.hotel.tenant if hasattr(reservation.hotel, 'tenant') else None
            if not tenant:
                # Public schema'dan tenant bul
                TenantModel = get_tenant_model()
                tenant = TenantModel.objects.filter(schema_name=request.tenant.schema_name).first()
            
            if not tenant:
                messages.error(request, 'Ödeme gateway ayarları bulunamadı.')
                return redirect('reception:voucher_payment', token=token)
            
            # Gateway config'i bul
            gateway_obj = PaymentGateway.objects.filter(code=gateway_code, is_active=True).first()
            if not gateway_obj:
                messages.error(request, 'Ödeme gateway bulunamadı.')
                return redirect('reception:voucher_payment', token=token)
            
            tenant_gateway = TenantPaymentGateway.objects.filter(
                tenant=tenant,
                gateway=gateway_obj,
                is_active=True
            ).first()
            
            if not tenant_gateway:
                messages.error(request, 'Ödeme gateway ayarları yapılandırılmamış.')
                return redirect('reception:voucher_payment', token=token)
            
            # Ödeme işlemini başlat
            from apps.payments.views import get_gateway_instance
            from apps.payments.models import PaymentTransaction
            import uuid
            
            gateway = get_gateway_instance(gateway_code, tenant_gateway)
            
            # Müşteri bilgileri
            customer = reservation.customer
            customer_info = {
                'name': customer.first_name if customer else '',
                'surname': customer.last_name if customer else '',
                'email': customer.email if customer else '',
                'phone': customer.phone if customer else '',
                'address': customer.address if customer and hasattr(customer, 'address') else '',
                'city': customer.city if customer and hasattr(customer, 'city') else '',
                'country': 'Türkiye',
            }
            
            # Transaction oluştur
            transaction_id = str(uuid.uuid4())
            payment_transaction = PaymentTransaction.objects.create(
                tenant=tenant,
                gateway=gateway_obj,
                transaction_id=transaction_id,
                order_id=f'VCH-{voucher.voucher_code}',
                amount=payment_amount,
                currency=voucher.payment_currency,
                payment_method=payment_method,
                customer_name=customer_info['name'],
                customer_surname=customer_info['surname'],
                customer_email=customer_info['email'],
                customer_phone=customer_info['phone'],
                customer_address=customer_info['address'],
                customer_city=customer_info['city'],
                customer_country=customer_info['country'],
                status='pending',
                notes=f'Voucher ödemesi: {voucher.voucher_code}',
            )
            
            # Voucher'ı transaction ile ilişkilendir
            voucher.payment_transaction = payment_transaction
            voucher.save()
            
            # Ödeme sayfası oluştur
            callback_url = request.build_absolute_uri(
                reverse('reception:voucher_payment_callback', kwargs={'token': token})
            )
            
            result = gateway.create_payment(
                amount=payment_amount,
                currency=voucher.payment_currency,
                order_id=transaction_id,
                customer_info=customer_info,
                callback_url=callback_url,
            )
            
            if result.get('success'):
                # Gateway transaction ID'yi kaydet
                payment_transaction.gateway_transaction_id = result.get('transaction_id', '')
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                # 3D Secure için redirect
                if result.get('payment_url'):
                    return redirect(result['payment_url'])
                else:
                    messages.error(request, 'Ödeme sayfası oluşturulamadı.')
                    return redirect('reception:voucher_payment', token=token)
            else:
                messages.error(request, f'Ödeme başlatılamadı: {result.get("error", "Bilinmeyen hata")}')
                return redirect('reception:voucher_payment', token=token)
        
        except Exception as e:
            import logging
            import traceback
            logger = logging.getLogger(__name__)
            logger.error(f'Voucher ödeme başlatılırken hata: {str(e)}\n{traceback.format_exc()}')
            messages.error(request, f'Ödeme başlatılırken hata oluştu: {str(e)}')
            return redirect('reception:voucher_payment', token=token)
    
    # GET request - Ödeme formu göster
    # Aktif gateway'leri bul
    from apps.payments.models import PaymentGateway, TenantPaymentGateway
    from django_tenants.utils import get_tenant_model
    
    tenant = None
    if hasattr(reservation.hotel, 'tenant'):
        tenant = reservation.hotel.tenant
    else:
        TenantModel = get_tenant_model()
        tenant = TenantModel.objects.filter(schema_name=request.tenant.schema_name).first()
    
    active_gateways = []
    if tenant:
        tenant_gateways = TenantPaymentGateway.objects.filter(
            tenant=tenant,
            is_active=True
        ).select_related('gateway')
        
        for tg in tenant_gateways:
            if tg.gateway.is_active:
                active_gateways.append({
                    'code': tg.gateway.code,
                    'name': tg.gateway.name,
                    'gateway_type': tg.gateway.gateway_type,
                })
    
    context = {
        'voucher': voucher,
        'reservation': reservation,
        'payment_amount': payment_amount,
        'active_gateways': active_gateways,
        'token': token,  # Token'ı context'e ekle
    }
    
    return render(request, 'reception/vouchers/payment.html', context)


@csrf_exempt
@require_http_methods(["POST", "GET"])
def voucher_payment_callback(request, token):
    """Voucher ödeme callback (API)"""
    from .models import ReservationVoucher
    from django.http import JsonResponse
    from django.db import transaction as db_transaction
    import logging
    logger = logging.getLogger(__name__)
    
    voucher = get_object_or_404(ReservationVoucher, access_token=token)
    payment_transaction = voucher.payment_transaction
    
    if not payment_transaction:
        logger.error(f'Voucher ödeme callback: Payment transaction bulunamadı - Token: {token}')
        return JsonResponse({'success': False, 'error': 'Payment transaction not found'}, status=400)
    
    try:
        # Gateway'i bul ve ödeme durumunu kontrol et
        from apps.payments.views import get_gateway_instance
        from apps.payments.models import TenantPaymentGateway
        
        tenant_gateway = TenantPaymentGateway.objects.filter(
            tenant=payment_transaction.tenant,
            gateway=payment_transaction.gateway,
            is_active=True
        ).first()
        
        if not tenant_gateway:
            logger.error(f'Voucher ödeme callback: Gateway config bulunamadı')
            return JsonResponse({'success': False, 'error': 'Gateway config not found'}, status=400)
        
        gateway = get_gateway_instance(payment_transaction.gateway.code, tenant_gateway)
        
        # Ödeme durumunu kontrol et
        result = gateway.verify_payment(
            payment_transaction.gateway_transaction_id or payment_transaction.transaction_id,
            **request.POST.dict() if request.method == 'POST' else request.GET.dict()
        )
        
        with db_transaction.atomic():
            # Transaction güncelle
            if result.get('success') and result.get('status') == 'completed':
                payment_transaction.status = 'completed'
                payment_transaction.payment_date = timezone.now()
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                # Voucher'ı güncelle
                voucher.payment_status = 'paid'
                voucher.payment_date = timezone.now()
                voucher.payment_completed_at = timezone.now()
                voucher.payment_method = payment_transaction.payment_method
                voucher.payment_info = {
                    'transaction_id': payment_transaction.transaction_id,
                    'gateway_transaction_id': payment_transaction.gateway_transaction_id,
                    'amount': str(payment_transaction.amount),
                    'currency': payment_transaction.currency,
                    'payment_method': payment_transaction.payment_method,
                    'card_last_four': payment_transaction.card_last_four,
                    'card_type': payment_transaction.card_type,
                }
                voucher.save()
                
                # Rezervasyon ödemesini güncelle
                from .models import ReservationPayment
                ReservationPayment.objects.create(
                    reservation=voucher.reservation,
                    payment_date=timezone.now().date(),
                    payment_amount=payment_transaction.amount,
                    payment_method=payment_transaction.payment_method,
                    payment_type='full' if payment_transaction.amount >= voucher.reservation.get_remaining_amount() else 'partial',
                    currency=payment_transaction.currency,
                    notes=f'Voucher ödemesi: {voucher.voucher_code} | Transaction: {payment_transaction.transaction_id}',
                    created_by=None,  # Müşteri ödemesi
                )
                
                # Rezervasyon toplam ödemesini güncelle
                voucher.reservation.update_total_paid()
                
                # Timeline'a kaydet
                from .models import ReservationTimeline
                ReservationTimeline.objects.create(
                    reservation=voucher.reservation,
                    action_type='payment',
                    action_description=f'Voucher ödemesi yapıldı: {payment_transaction.amount} {payment_transaction.currency} | Voucher: {voucher.voucher_code}',
                    user=None,  # Müşteri ödemesi
                )
                
                logger.info(f'Voucher ödeme başarılı: {voucher.voucher_code} - {payment_transaction.amount} {payment_transaction.currency}')
                
                # Başarı sayfasına yönlendir
                return redirect('reception:voucher_payment_success', token=token)
            else:
                # Ödeme başarısız
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', 'Ödeme başarısız')
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                voucher.payment_status = 'failed'
                voucher.save()
                
                logger.warning(f'Voucher ödeme başarısız: {voucher.voucher_code} - {result.get("error", "Bilinmeyen hata")}')
                
                # Hata sayfasına yönlendir
                return redirect('reception:voucher_payment_fail', token=token)
    
    except Exception as e:
        import traceback
        logger.error(f'Voucher ödeme callback hatası: {str(e)}\n{traceback.format_exc()}')
        return JsonResponse({'success': False, 'error': str(e)}, status=500)


def voucher_payment_success(request, token):
    """Voucher ödeme başarılı sayfası"""
    from .models import ReservationVoucher
    voucher = get_object_or_404(ReservationVoucher, access_token=token)
    
    context = {
        'voucher': voucher,
        'reservation': voucher.reservation,
        'token': token,  # Token'ı context'e ekle
    }
    
    return render(request, 'reception/vouchers/payment_success.html', context)


def voucher_payment_fail(request, token):
    """Voucher ödeme başarısız sayfası"""
    from .models import ReservationVoucher
    voucher = get_object_or_404(ReservationVoucher, access_token=token)
    
    context = {
        'voucher': voucher,
        'reservation': voucher.reservation,
        'token': token,  # Token'ı context'e ekle
    }
    
    return render(request, 'reception/vouchers/payment_fail.html', context)


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

