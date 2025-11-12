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
from datetime import date, timedelta
from decimal import Decimal

from .models import Reservation, ReservationStatus
from .forms import ReservationForm
from apps.tenant_apps.hotels.decorators import require_hotel_permission


@login_required
@require_hotel_permission('view')
def dashboard(request):
    """Rezervasyon Dashboard"""
    hotel = request.active_hotel
    
    # Bugünkü rezervasyonlar
    today = date.today()
    today_reservations = Reservation.objects.filter(
        hotel=hotel,
        check_in_date__lte=today,
        check_out_date__gte=today,
        status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
        is_deleted=False
    ).order_by('check_in_date')
    
    # Bekleyen check-in'ler
    pending_checkins = Reservation.objects.filter(
        hotel=hotel,
        check_in_date=today,
        status=ReservationStatus.CONFIRMED,
        is_checked_in=False,
        is_deleted=False
    ).order_by('check_in_time')
    
    # Bekleyen check-out'lar
    pending_checkouts = Reservation.objects.filter(
        hotel=hotel,
        check_out_date=today,
        is_checked_in=True,
        is_checked_out=False,
        is_deleted=False
    ).order_by('check_out_time')
    
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
    }
    
    context = {
        'today_reservations': today_reservations,
        'pending_checkins': pending_checkins,
        'pending_checkouts': pending_checkouts,
        'stats': stats,
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
    
    context = {
        'reservations': reservations,
        'status_choices': ReservationStatus.choices,
    }
    
    return render(request, 'reception/reservations/list.html', context)


@login_required
@require_hotel_permission('add')
def reservation_create(request):
    """Yeni Rezervasyon Oluştur"""
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = ReservationForm(request.POST, hotel=hotel)
        if form.is_valid():
            reservation = form.save(commit=False)
            reservation.hotel = hotel
            
            # Rezervasyon kodu oluştur
            if not reservation.reservation_code:
                year = timezone.now().year
                last_reservation = Reservation.objects.filter(
                    reservation_code__startswith=f'RES-{year}-'
                ).order_by('-reservation_code').first()
                
                if last_reservation:
                    last_number = int(last_reservation.reservation_code.split('-')[-1])
                    new_number = last_number + 1
                else:
                    new_number = 1
                
                reservation.reservation_code = f'RES-{year}-{new_number:04d}'
            
            reservation.save()
            messages.success(request, 'Rezervasyon başarıyla oluşturuldu.')
            return redirect('reception:reservation_detail', pk=reservation.pk)
    else:
        form = ReservationForm(hotel=hotel)
    
    context = {
        'form': form,
    }
    
    return render(request, 'reception/reservations/form.html', context)


@login_required
@require_hotel_permission('view')
def reservation_detail(request, pk):
    """Rezervasyon Detayı"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    context = {
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/detail.html', context)


@login_required
@require_hotel_permission('edit')
def reservation_update(request, pk):
    """Rezervasyon Güncelle"""
    hotel = request.active_hotel
    reservation = get_object_or_404(
        Reservation,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = ReservationForm(request.POST, instance=reservation, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Rezervasyon başarıyla güncellendi.')
            return redirect('reception:reservation_detail', pk=reservation.pk)
    else:
        form = ReservationForm(instance=reservation, hotel=hotel)
    
    context = {
        'form': form,
        'reservation': reservation,
    }
    
    return render(request, 'reception/reservations/form.html', context)


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

