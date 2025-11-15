"""
Bungalov Yönetimi Views
Bungalov rezervasyon ve yönetim sistemi
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

from .models import (
    Bungalov, BungalovType, BungalovFeature,
    BungalovReservation, ReservationStatus, ReservationSource,
    BungalovReservationGuest, BungalovReservationPayment,
    BungalovCleaning, BungalovMaintenance, BungalovEquipment,
    BungalovPrice, BungalovVoucherTemplate, BungalovVoucher,
    CleaningType, CleaningStatus, MaintenanceType, MaintenanceStatus
)
from .forms import (
    BungalovForm, BungalovTypeForm,
    BungalovReservationForm, BungalovReservationGuestFormSet,
    BungalovCleaningForm, BungalovMaintenanceForm,
    BungalovPriceForm, BungalovVoucherTemplateForm,
    BungalovFeatureForm, BungalovEquipmentForm
)
from .utils import (
    generate_reservation_code, save_guest_information,
    generate_reservation_voucher, create_reservation_voucher,
    check_bungalov_availability, get_available_bungalovs
)
from .decorators import require_bungalov_permission
from apps.tenant_apps.core.utils import can_delete_with_payment_check, start_refund_process_for_deletion


@login_required
@require_bungalov_permission('view')
def dashboard(request):
    """Bungalov Dashboard"""
    today = date.today()
    
    # Bugünkü rezervasyonlar
    today_reservations = BungalovReservation.objects.filter(
        check_in_date__lte=today,
        check_out_date__gte=today,
        status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
        is_deleted=False
    ).select_related('customer', 'bungalov', 'bungalov__bungalov_type').order_by('check_in_date')
    
    # Bekleyen check-in'ler
    pending_checkins = BungalovReservation.objects.filter(
        check_in_date=today,
        status=ReservationStatus.CONFIRMED,
        is_checked_in=False,
        is_deleted=False
    ).select_related('customer', 'bungalov').order_by('check_in_time')[:10]
    
    # Bekleyen check-out'lar
    pending_checkouts = BungalovReservation.objects.filter(
        check_out_date=today,
        is_checked_in=True,
        is_checked_out=False,
        is_deleted=False
    ).select_related('customer', 'bungalov').order_by('check_out_time')[:10]
    
    # Bu ay istatistikleri
    month_start = today.replace(day=1)
    month_reservations = BungalovReservation.objects.filter(
        check_in_date__gte=month_start,
        check_in_date__lte=today,
        is_deleted=False
    )
    
    total_reservations = month_reservations.count()
    total_revenue = month_reservations.aggregate(total=Sum('total_amount'))['total'] or Decimal('0')
    total_paid = month_reservations.aggregate(total=Sum('total_paid'))['total'] or Decimal('0')
    
    # Bungalov durumları
    bungalov_statuses = Bungalov.objects.filter(is_deleted=False).values('status').annotate(
        count=Count('id')
    )
    
    context = {
        'today': today,
        'today_reservations': today_reservations,
        'pending_checkins': pending_checkins,
        'pending_checkouts': pending_checkouts,
        'total_reservations': total_reservations,
        'total_revenue': total_revenue,
        'total_paid': total_paid,
        'bungalov_statuses': bungalov_statuses,
    }
    
    return render(request, 'bungalovs/dashboard.html', context)


@login_required
@require_bungalov_permission('view')
def bungalov_list(request):
    """Bungalov Listesi"""
    bungalovs = Bungalov.objects.filter(is_deleted=False).select_related('bungalov_type')
    
    # Filtreler
    status = request.GET.get('status')
    if status:
        bungalovs = bungalovs.filter(status=status)
    
    bungalov_type_id = request.GET.get('bungalov_type')
    if bungalov_type_id:
        bungalovs = bungalovs.filter(bungalov_type_id=bungalov_type_id)
    
    search = request.GET.get('search')
    if search:
        bungalovs = bungalovs.filter(
            Q(code__icontains=search) |
            Q(name__icontains=search) |
            Q(location__icontains=search)
        )
    
    paginator = Paginator(bungalovs, 25)
    page = request.GET.get('page')
    bungalovs = paginator.get_page(page)
    
    bungalov_types = BungalovType.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'bungalovs': bungalovs,
        'bungalov_types': bungalov_types,
    }
    
    return render(request, 'bungalovs/bungalovs/list.html', context)


@login_required
@require_bungalov_permission('add')
def bungalov_create(request):
    """Yeni Bungalov Oluştur"""
    if request.method == 'POST':
        form = BungalovForm(request.POST)
        if form.is_valid():
            bungalov = form.save(commit=False)
            bungalov.save()
            form.save_m2m()  # Many-to-Many için
            messages.success(request, 'Bungalov başarıyla oluşturuldu.')
            return redirect('bungalovs:bungalov_detail', pk=bungalov.pk)
    else:
        form = BungalovForm()
    
    context = {'form': form}
    return render(request, 'bungalovs/bungalovs/form.html', context)


@login_required
@require_bungalov_permission('view')
def bungalov_detail(request, pk):
    """Bungalov Detay"""
    bungalov = get_object_or_404(Bungalov, pk=pk, is_deleted=False)
    
    # Aktif rezervasyon
    current_reservation = bungalov.get_current_reservation()
    
    # Geçmiş rezervasyonlar
    past_reservations = bungalov.reservations.filter(
        check_out_date__lt=date.today(),
        is_deleted=False
    ).order_by('-check_out_date')[:10]
    
    # Yaklaşan rezervasyonlar
    upcoming_reservations = bungalov.reservations.filter(
        check_in_date__gt=date.today(),
        is_deleted=False
    ).order_by('check_in_date')[:10]
    
    # Temizlik kayıtları
    cleanings = bungalov.cleanings.filter(is_deleted=False).order_by('-cleaning_date')[:10]
    
    # Bakım kayıtları
    maintenances = bungalov.maintenances.filter(is_deleted=False).order_by('-planned_date')[:10]
    
    # Ekipmanlar
    equipments = bungalov.equipments.filter(is_deleted=False)
    
    context = {
        'bungalov': bungalov,
        'current_reservation': current_reservation,
        'past_reservations': past_reservations,
        'upcoming_reservations': upcoming_reservations,
        'cleanings': cleanings,
        'maintenances': maintenances,
        'equipments': equipments,
    }
    
    return render(request, 'bungalovs/bungalovs/detail.html', context)


@login_required
@require_bungalov_permission('edit')
def bungalov_update(request, pk):
    """Bungalov Güncelle"""
    bungalov = get_object_or_404(Bungalov, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = BungalovForm(request.POST, instance=bungalov)
        if form.is_valid():
            bungalov = form.save(commit=False)
            bungalov.save()
            form.save_m2m()
            messages.success(request, 'Bungalov başarıyla güncellendi.')
            return redirect('bungalovs:bungalov_detail', pk=bungalov.pk)
    else:
        form = BungalovForm(instance=bungalov)
    
    context = {'form': form, 'bungalov': bungalov}
    return render(request, 'bungalovs/bungalovs/form.html', context)


@login_required
@require_bungalov_permission('delete')
def bungalov_delete(request, pk):
    """Bungalov Sil"""
    bungalov = get_object_or_404(Bungalov, pk=pk, is_deleted=False)
    
    # Aktif rezervasyon kontrolü
    current_reservation = bungalov.get_current_reservation()
    
    if request.method == 'POST':
        if current_reservation:
            messages.error(request, 'Bu bungalovda aktif rezervasyon var. Silme işlemi yapılamaz.')
            return redirect('bungalovs:bungalov_detail', pk=bungalov.pk)
        
        bungalov.is_deleted = True
        bungalov.deleted_by = request.user
        bungalov.deleted_at = timezone.now()
        bungalov.save()
        messages.success(request, 'Bungalov başarıyla silindi.')
        return redirect('bungalovs:bungalov_list')
    
    context = {
        'bungalov': bungalov,
        'current_reservation': current_reservation,
    }
    return render(request, 'bungalovs/bungalovs/delete_confirm.html', context)


# ==================== REZERVASYON İŞLEMLERİ ====================

@login_required
@require_bungalov_permission('view')
def reservation_list(request):
    """Rezervasyon Listesi"""
    reservations = BungalovReservation.objects.filter(is_deleted=False).select_related(
        'customer', 'bungalov', 'bungalov__bungalov_type'
    )
    
    # Filtreler
    status = request.GET.get('status')
    if status:
        reservations = reservations.filter(status=status)
    
    bungalov_id = request.GET.get('bungalov')
    if bungalov_id:
        reservations = reservations.filter(bungalov_id=bungalov_id)
    
    check_in_date = request.GET.get('check_in_date')
    if check_in_date:
        reservations = reservations.filter(check_in_date=check_in_date)
    
    search = request.GET.get('search')
    if search:
        reservations = reservations.filter(
            Q(reservation_code__icontains=search) |
            Q(customer__first_name__icontains=search) |
            Q(customer__last_name__icontains=search) |
            Q(customer__email__icontains=search)
        )
    
    paginator = Paginator(reservations, 25)
    page = request.GET.get('page')
    reservations = paginator.get_page(page)
    
    bungalovs = Bungalov.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'reservations': reservations,
        'status_choices': ReservationStatus.choices,
        'bungalovs': bungalovs,
    }
    
    return render(request, 'bungalovs/reservations/list.html', context)


@login_required
@require_bungalov_permission('add')
def reservation_create(request):
    """Yeni Rezervasyon Oluştur"""
    if request.method == 'POST':
        form = BungalovReservationForm(request.POST)
        if form.is_valid():
            reservation = form.save(commit=False)
            
            # Rezervasyon kodu oluştur
            if not reservation.reservation_code:
                reservation.reservation_code = generate_reservation_code()
            
            reservation.created_by = request.user
            reservation.save()
            
            # Misafir bilgilerini kaydet
            save_guest_information(reservation, request.POST)
            
            messages.success(request, 'Rezervasyon başarıyla oluşturuldu.')
            return redirect('bungalovs:reservation_detail', pk=reservation.pk)
    else:
        form = BungalovReservationForm()
    
    context = {'form': form}
    return render(request, 'bungalovs/reservations/form.html', context)


@login_required
@require_bungalov_permission('view')
def reservation_detail(request, pk):
    """Rezervasyon Detay"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    # Ödeme ve iade kontrolü (silme için)
    delete_check = can_delete_with_payment_check(reservation, 'bungalovs')
    
    # Ödeme toplamı
    total_paid = reservation.payments.filter(is_deleted=False).aggregate(
        total=Sum('payment_amount')
    )['total'] or Decimal('0')
    remaining_amount = reservation.total_amount - total_paid
    
    # Misafirler
    guests = reservation.guests.all().order_by('guest_type', 'guest_order')
    
    # Ödemeler
    payments = reservation.payments.filter(is_deleted=False).order_by('-payment_date')
    
    context = {
        'reservation': reservation,
        'total_paid': total_paid,
        'remaining_amount': remaining_amount,
        'guests': guests,
        'payments': payments,
        'delete_check': delete_check,
    }
    
    return render(request, 'bungalovs/reservations/detail.html', context)


@login_required
@require_bungalov_permission('edit')
def reservation_update(request, pk):
    """Rezervasyon Güncelle"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = BungalovReservationForm(request.POST, instance=reservation)
        if form.is_valid():
            reservation = form.save(commit=False)
            reservation.updated_by = request.user
            reservation.save()
            
            # Misafir bilgilerini güncelle
            reservation.guests.all().delete()  # Eski misafirleri sil
            save_guest_information(reservation, request.POST)
            
            # Ön ödeme varsa kaydet
            advance_payment = form.cleaned_data.get('advance_payment', Decimal('0'))
            payment_method = form.cleaned_data.get('payment_method', '')
            
            if advance_payment and advance_payment > 0 and payment_method:
                # Yeni ödeme ekle (sadece fark varsa)
                current_total_paid = reservation.total_paid or Decimal('0')
                if advance_payment > current_total_paid:
                    payment_amount = advance_payment - current_total_paid
                    BungalovReservationPayment.objects.create(
                        reservation=reservation,
                        payment_type='payment',
                        payment_date=date.today(),
                        payment_amount=payment_amount,
                        payment_method=payment_method,
                        currency=reservation.currency,
                        created_by=request.user
                    )
                    reservation.update_total_paid()
            
            messages.success(request, 'Rezervasyon başarıyla güncellendi.')
            return redirect('bungalovs:reservation_detail', pk=reservation.pk)
    else:
        form = BungalovReservationForm(instance=reservation)
    
    context = {'form': form, 'reservation': reservation}
    return render(request, 'bungalovs/reservations/form.html', context)


@login_required
@require_bungalov_permission('delete')
def reservation_delete(request, pk):
    """Rezervasyon Sil - Ödeme ve İade Kontrolü ile"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    # Ödeme ve iade kontrolü
    delete_check = can_delete_with_payment_check(reservation, 'bungalovs')
    
    if request.method == 'POST':
        start_refund = request.POST.get('start_refund', '0') == '1'
        
        # İade başlatma
        if start_refund and delete_check['has_payment'] and not delete_check['refund_request']:
            refund_request = start_refund_process_for_deletion(
                reservation, 'bungalovs', request.user,
                reason='Bungalov rezervasyon silme işlemi için iade'
            )
            
            if refund_request:
                messages.success(request, f'İade süreci başlatıldı. İade Talebi No: {refund_request.request_number}.')
                return redirect('refunds:refund_request_detail', pk=refund_request.pk)
        
        # Silme kontrolü
        delete_check = can_delete_with_payment_check(reservation, 'bungalovs')
        
        if not delete_check['can_delete']:
            messages.error(request, delete_check['message'])
            return redirect('bungalovs:reservation_detail', pk=reservation.pk)
        
        reservation.is_deleted = True
        reservation.deleted_by = request.user
        reservation.deleted_at = timezone.now()
        reservation.save()
        
        messages.success(request, 'Rezervasyon başarıyla silindi.')
        return redirect('bungalovs:reservation_list')
    
    context = {
        'reservation': reservation,
        'delete_check': delete_check,
    }
    
    return render(request, 'bungalovs/reservations/delete_confirm.html', context)


@login_required
@require_bungalov_permission('edit')
def reservation_checkin(request, pk):
    """Check-In"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    if reservation.can_check_in():
        reservation.is_checked_in = True
        reservation.checked_in_at = timezone.now()
        reservation.status = ReservationStatus.CHECKED_IN
        reservation.save()
        
        # Bungalov durumunu güncelle
        reservation.bungalov.status = 'occupied'
        reservation.bungalov.save()
        
        messages.success(request, 'Check-in başarıyla yapıldı.')
    else:
        messages.error(request, 'Check-in yapılamaz.')
    
    return redirect('bungalovs:reservation_detail', pk=reservation.pk)


@login_required
@require_bungalov_permission('edit')
def reservation_checkout(request, pk):
    """Check-Out"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    if not reservation.can_check_out():
        messages.error(request, 'Check-out yapılamaz.')
        return redirect('bungalovs:reservation_detail', pk=reservation.pk)
    
    reservation.is_checked_out = True
    reservation.checked_out_at = timezone.now()
    reservation.status = ReservationStatus.CHECKED_OUT
    reservation.save()
    
    # Bungalov durumunu güncelle
    reservation.bungalov.status = 'cleaning'
    reservation.bungalov.save()
    
    # Temizlik kaydı oluştur
    from .models import CleaningType, CleaningStatus
    BungalovCleaning.objects.create(
        bungalov=reservation.bungalov,
        reservation=reservation,
        cleaning_type=CleaningType.CHECKOUT,
        cleaning_date=date.today(),
        status=CleaningStatus.DIRTY
    )
    
    messages.success(request, 'Check-out başarıyla yapıldı.')
    return redirect('bungalovs:reservation_detail', pk=reservation.pk)


# Placeholder views - Detaylandırılacak
@login_required
@require_bungalov_permission('view')
def bungalov_type_list(request):
    """Bungalov Tipi Listesi"""
    types = BungalovType.objects.filter(is_deleted=False)
    return render(request, 'bungalovs/types/list.html', {'types': types})


@login_required
@require_bungalov_permission('add')
def bungalov_type_create(request):
    """Yeni Bungalov Tipi Oluştur"""
    if request.method == 'POST':
        form = BungalovTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bungalov tipi başarıyla oluşturuldu.')
            return redirect('bungalovs:bungalov_type_list')
    else:
        form = BungalovTypeForm()
    return render(request, 'bungalovs/types/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def bungalov_type_update(request, pk):
    """Bungalov Tipi Güncelle"""
    bungalov_type = get_object_or_404(BungalovType, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovTypeForm(request.POST, instance=bungalov_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bungalov tipi başarıyla güncellendi.')
            return redirect('bungalovs:bungalov_type_list')
    else:
        form = BungalovTypeForm(instance=bungalov_type)
    return render(request, 'bungalovs/types/form.html', {'form': form, 'bungalov_type': bungalov_type})


@login_required
@require_bungalov_permission('delete')
def bungalov_type_delete(request, pk):
    """Bungalov Tipi Sil"""
    bungalov_type = get_object_or_404(BungalovType, pk=pk, is_deleted=False)
    if request.method == 'POST':
        bungalov_type.is_deleted = True
        bungalov_type.save()
        messages.success(request, 'Bungalov tipi başarıyla silindi.')
        return redirect('bungalovs:bungalov_type_list')
    return render(request, 'bungalovs/types/delete_confirm.html', {'bungalov_type': bungalov_type})


# Diğer placeholder view'lar
@login_required
@require_bungalov_permission('view')
def bungalov_feature_list(request):
    return render(request, 'bungalovs/features/list.html', {'features': BungalovFeature.objects.filter(is_active=True)})


@login_required
@require_bungalov_permission('add')
def bungalov_feature_create(request):
    if request.method == 'POST':
        form = BungalovFeatureForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bungalov özelliği oluşturuldu.')
            return redirect('bungalovs:bungalov_feature_list')
    else:
        form = BungalovFeatureForm()
    return render(request, 'bungalovs/features/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def bungalov_feature_update(request, pk):
    feature = get_object_or_404(BungalovFeature, pk=pk)
    if request.method == 'POST':
        form = BungalovFeatureForm(request.POST, instance=feature)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bungalov özelliği güncellendi.')
            return redirect('bungalovs:bungalov_feature_list')
    else:
        form = BungalovFeatureForm(instance=feature)
    return render(request, 'bungalovs/features/form.html', {'form': form, 'feature': feature})


@login_required
@require_bungalov_permission('delete')
def bungalov_feature_delete(request, pk):
    return redirect('bungalovs:bungalov_feature_list')


@login_required
@require_bungalov_permission('edit')
def reservation_cancel(request, pk):
    """Rezervasyon İptal"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    delete_check = can_delete_with_payment_check(reservation, 'bungalovs')
    
    if delete_check['has_payment'] and not delete_check['can_delete']:
        messages.error(request, delete_check['message'])
        return redirect('bungalovs:reservation_detail', pk=reservation.pk)
    
    reservation.status = ReservationStatus.CANCELLED
    reservation.is_cancelled = True
    reservation.cancelled_at = timezone.now()
    reservation.cancelled_by = request.user
    reservation.save()
    
    messages.success(request, 'Rezervasyon iptal edildi.')
    return redirect('bungalovs:reservation_detail', pk=reservation.pk)


@login_required
@require_bungalov_permission('edit')
def reservation_refund(request, pk):
    """Rezervasyon İade"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    # İade işlemi - Refunds modülüne yönlendir
    messages.info(request, 'İade işlemi için Refunds modülünü kullanın.')
    return redirect('bungalovs:reservation_detail', pk=reservation.pk)


@login_required
@require_bungalov_permission('edit')
def reservation_status_change(request, pk):
    """Rezervasyon Durum Değiştir"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        new_status = request.POST.get('status')
        if new_status in [s[0] for s in ReservationStatus.choices]:
            reservation.status = new_status
            reservation.save()
            messages.success(request, 'Rezervasyon durumu güncellendi.')
    
    return redirect('bungalovs:reservation_detail', pk=reservation.pk)


@login_required
@require_bungalov_permission('edit')
@require_http_methods(["GET", "POST"])
def reservation_payment_add(request, pk):
    """Rezervasyon Ödeme Ekle"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    
    # Ödeme toplamı
    total_paid = reservation.payments.filter(is_deleted=False).aggregate(
        total=Sum('payment_amount')
    )['total'] or Decimal('0')
    remaining_amount = reservation.total_amount - total_paid
    
    if request.method == 'POST':
        payment_amount = Decimal(request.POST.get('payment_amount', 0))
        payment_method = request.POST.get('payment_method')
        payment_date = request.POST.get('payment_date') or timezone.now().date()
        payment_reference = request.POST.get('payment_reference', '')
        transaction_id = request.POST.get('transaction_id', '')
        notes = request.POST.get('notes', '')
        
        if payment_amount <= 0:
            messages.error(request, 'Ödeme tutarı 0\'dan büyük olmalıdır.')
        elif payment_amount > remaining_amount:
            messages.error(request, f'Ödeme tutarı kalan tutardan ({remaining_amount} {reservation.currency}) fazla olamaz.')
        else:
            # Ödeme kaydı oluştur
            payment_date_obj = None
            if payment_date:
                try:
                    if isinstance(payment_date, str):
                        from datetime import datetime
                        payment_date_obj = datetime.strptime(payment_date, '%Y-%m-%d').date()
                    else:
                        payment_date_obj = payment_date
                except:
                    payment_date_obj = timezone.now().date()
            else:
                payment_date_obj = timezone.now().date()
            
            payment = BungalovReservationPayment.objects.create(
                reservation=reservation,
                payment_type='payment',
                payment_date=payment_date_obj,
                payment_amount=payment_amount,
                payment_method=payment_method,
                currency=reservation.currency,
                payment_reference=payment_reference,
                transaction_id=transaction_id,
                notes=notes,
                created_by=request.user,
            )
            
            # Rezervasyon ödeme durumunu güncelle
            reservation.update_total_paid()
            
            messages.success(request, f'{payment_amount} {reservation.currency} ödeme başarıyla eklendi.')
            return redirect('bungalovs:reservation_detail', pk=reservation.pk)
    
    context = {
        'reservation': reservation,
        'total_paid': total_paid,
        'remaining_amount': remaining_amount,
    }
    
    return render(request, 'bungalovs/reservations/payment_add.html', context)


@login_required
@require_bungalov_permission('view')
def reservation_payment_link(request, pk):
    """Rezervasyon Ödeme Linki"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    # Ödeme linki oluşturma - Payments modülüne entegre edilecek
    return render(request, 'bungalovs/reservations/payment_link.html', {'reservation': reservation})


@login_required
@require_bungalov_permission('view')
def reservation_voucher_create(request, pk):
    """Voucher Oluştur"""
    reservation = get_object_or_404(BungalovReservation, pk=pk, is_deleted=False)
    voucher = create_reservation_voucher(reservation)
    messages.success(request, 'Voucher başarıyla oluşturuldu.')
    return redirect('bungalovs:reservation_voucher_detail', pk=voucher.pk)


@login_required
@require_bungalov_permission('view')
def reservation_voucher_detail(request, pk):
    """Voucher Detay"""
    voucher = get_object_or_404(BungalovVoucher, pk=pk, is_deleted=False)
    return render(request, 'bungalovs/vouchers/detail.html', {'voucher': voucher})


@login_required
@require_bungalov_permission('view')
def reservation_voucher_pdf(request, pk):
    """Voucher PDF İndir - Direkt PDF formatında"""
    voucher = get_object_or_404(BungalovVoucher, pk=pk, is_deleted=False)
    from django.http import HttpResponse
    from django.contrib import messages
    from django.shortcuts import redirect
    import logging
    logger = logging.getLogger(__name__)
    
    # Voucher HTML'ini oluştur
    try:
        from .utils import generate_reservation_voucher
        voucher_html, _ = generate_reservation_voucher(voucher.reservation, voucher.template)
    except Exception as e:
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        messages.error(request, f'Voucher PDF oluşturulurken hata: {str(e)}')
        return redirect('bungalovs:reservation_voucher_detail', pk=voucher.pk)
    
    # PDF oluştur - Güvenli utility fonksiyonu kullan (ReportLab öncelikli)
    from apps.tenant_apps.core.pdf_utils import generate_pdf_response
    
    pdf_response = generate_pdf_response(
        voucher_html,
        filename=f'voucher_{voucher.voucher_code}.pdf'
    )
    
    if pdf_response:
        return pdf_response
    
    # PDF oluşturulamadıysa hata mesajı göster
    logger.error('PDF oluşturma için gerekli kütüphaneler bulunamadı (ReportLab veya WeasyPrint)')
    messages.error(
        request,
        'PDF oluşturulamadı. Lütfen sistem yöneticisine başvurun. '
        'Gerekli kütüphaneler: reportlab veya weasyprint'
    )
    return redirect('bungalovs:reservation_voucher_detail', pk=voucher.pk)


@login_required
@require_bungalov_permission('view')
def voucher_send(request, pk):
    """Voucher Gönder"""
    voucher = get_object_or_404(BungalovVoucher, pk=pk, is_deleted=False)
    # E-posta/SMS gönderme - Notifications modülüne entegre edilecek
    return JsonResponse({'message': 'Voucher gönderme yakında eklenecek'})


@login_required
@require_bungalov_permission('view')
def voucher_view(request, token):
    """Public Voucher Görüntüleme"""
    voucher = get_object_or_404(BungalovVoucher, voucher_token=token, is_deleted=False)
    return render(request, 'bungalovs/vouchers/public_view.html', {'voucher': voucher})


@login_required
@require_bungalov_permission('view')
def voucher_payment(request, token):
    """Voucher Ödeme"""
    voucher = get_object_or_404(BungalovVoucher, voucher_token=token, is_deleted=False)
    return render(request, 'bungalovs/vouchers/payment.html', {'voucher': voucher})


@login_required
@require_bungalov_permission('view')
def voucher_template_list(request):
    """Voucher Şablon Listesi"""
    templates = BungalovVoucherTemplate.objects.filter(is_deleted=False)
    return render(request, 'bungalovs/voucher_templates/list.html', {'templates': templates})


@login_required
@require_bungalov_permission('add')
def voucher_template_create(request):
    """Voucher Şablon Oluştur"""
    if request.method == 'POST':
        form = BungalovVoucherTemplateForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Voucher şablonu başarıyla oluşturuldu.')
            return redirect('bungalovs:voucher_template_list')
    else:
        form = BungalovVoucherTemplateForm()
    return render(request, 'bungalovs/voucher_templates/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def voucher_template_update(request, pk):
    """Voucher Şablon Güncelle"""
    template = get_object_or_404(BungalovVoucherTemplate, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovVoucherTemplateForm(request.POST, instance=template)
        if form.is_valid():
            form.save()
            messages.success(request, 'Voucher şablonu başarıyla güncellendi.')
            return redirect('bungalovs:voucher_template_list')
    else:
        form = BungalovVoucherTemplateForm(instance=template)
    return render(request, 'bungalovs/voucher_templates/form.html', {'form': form, 'template': template})


# Temizlik, Bakım, Ekipman, Fiyatlandırma placeholder view'ları
@login_required
@require_bungalov_permission('view')
def cleaning_list(request):
    cleanings = BungalovCleaning.objects.filter(is_deleted=False).order_by('-cleaning_date')
    return render(request, 'bungalovs/cleanings/list.html', {'cleanings': cleanings})


@login_required
@require_bungalov_permission('add')
def cleaning_create(request):
    if request.method == 'POST':
        form = BungalovCleaningForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Temizlik kaydı oluşturuldu.')
            return redirect('bungalovs:cleaning_list')
    else:
        form = BungalovCleaningForm()
    return render(request, 'bungalovs/cleanings/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def cleaning_update(request, pk):
    cleaning = get_object_or_404(BungalovCleaning, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovCleaningForm(request.POST, instance=cleaning)
        if form.is_valid():
            form.save()
            messages.success(request, 'Temizlik kaydı güncellendi.')
            return redirect('bungalovs:cleaning_list')
    else:
        form = BungalovCleaningForm(instance=cleaning)
    return render(request, 'bungalovs/cleanings/form.html', {'form': form, 'cleaning': cleaning})


@login_required
@require_bungalov_permission('edit')
def cleaning_complete(request, pk):
    cleaning = get_object_or_404(BungalovCleaning, pk=pk, is_deleted=False)
    cleaning.status = CleaningStatus.CLEAN
    cleaning.completed_by = request.user
    cleaning.completed_at = timezone.now()
    cleaning.save()
    
    # Bungalov durumunu güncelle
    cleaning.bungalov.status = 'available'
    cleaning.bungalov.save()
    
    messages.success(request, 'Temizlik tamamlandı.')
    return redirect('bungalovs:cleaning_list')


@login_required
@require_bungalov_permission('view')
def maintenance_list(request):
    maintenances = BungalovMaintenance.objects.filter(is_deleted=False).order_by('-planned_date')
    return render(request, 'bungalovs/maintenances/list.html', {'maintenances': maintenances})


@login_required
@require_bungalov_permission('add')
def maintenance_create(request):
    if request.method == 'POST':
        form = BungalovMaintenanceForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bakım kaydı oluşturuldu.')
            return redirect('bungalovs:maintenance_list')
    else:
        form = BungalovMaintenanceForm()
    return render(request, 'bungalovs/maintenances/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def maintenance_update(request, pk):
    maintenance = get_object_or_404(BungalovMaintenance, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovMaintenanceForm(request.POST, instance=maintenance)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bakım kaydı güncellendi.')
            return redirect('bungalovs:maintenance_list')
    else:
        form = BungalovMaintenanceForm(instance=maintenance)
    return render(request, 'bungalovs/maintenances/form.html', {'form': form, 'maintenance': maintenance})


@login_required
@require_bungalov_permission('edit')
def maintenance_complete(request, pk):
    maintenance = get_object_or_404(BungalovMaintenance, pk=pk, is_deleted=False)
    maintenance.status = MaintenanceStatus.COMPLETED
    maintenance.completed_date = date.today()
    maintenance.save()
    
    # Bungalov durumunu güncelle
    maintenance.bungalov.status = 'available'
    maintenance.bungalov.save()
    
    messages.success(request, 'Bakım tamamlandı.')
    return redirect('bungalovs:maintenance_list')


@login_required
@require_bungalov_permission('view')
def equipment_list(request):
    equipments = BungalovEquipment.objects.filter(is_deleted=False)
    return render(request, 'bungalovs/equipments/list.html', {'equipments': equipments})


@login_required
@require_bungalov_permission('add')
def equipment_create(request):
    if request.method == 'POST':
        form = BungalovEquipmentForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ekipman kaydı oluşturuldu.')
            return redirect('bungalovs:equipment_list')
    else:
        form = BungalovEquipmentForm()
    return render(request, 'bungalovs/equipments/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def equipment_update(request, pk):
    equipment = get_object_or_404(BungalovEquipment, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovEquipmentForm(request.POST, instance=equipment)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ekipman kaydı güncellendi.')
            return redirect('bungalovs:equipment_list')
    else:
        form = BungalovEquipmentForm(instance=equipment)
    return render(request, 'bungalovs/equipments/form.html', {'form': form, 'equipment': equipment})


@login_required
@require_bungalov_permission('view')
def price_list(request):
    prices = BungalovPrice.objects.filter(is_deleted=False).order_by('-start_date')
    return render(request, 'bungalovs/prices/list.html', {'prices': prices})


@login_required
@require_bungalov_permission('add')
def price_create(request):
    if request.method == 'POST':
        form = BungalovPriceForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Fiyatlandırma kaydı oluşturuldu.')
            return redirect('bungalovs:price_list')
    else:
        form = BungalovPriceForm()
    return render(request, 'bungalovs/prices/form.html', {'form': form})


@login_required
@require_bungalov_permission('edit')
def price_update(request, pk):
    price = get_object_or_404(BungalovPrice, pk=pk, is_deleted=False)
    if request.method == 'POST':
        form = BungalovPriceForm(request.POST, instance=price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Fiyatlandırma kaydı güncellendi.')
            return redirect('bungalovs:price_list')
    else:
        form = BungalovPriceForm(instance=price)
    return render(request, 'bungalovs/prices/form.html', {'form': form, 'price': price})


# ==================== API ENDPOINTS ====================

@login_required
@require_bungalov_permission('view')
def api_search_customer(request):
    """API - Müşteri Ara"""
    query = request.GET.get('q', '')
    
    if not query:
        return JsonResponse({'results': []})
    
    from apps.tenant_apps.core.models import Customer
    
    customers = Customer.objects.filter(
        Q(first_name__icontains=query) |
        Q(last_name__icontains=query) |
        Q(email__icontains=query) |
        Q(phone__icontains=query) |
        Q(tc_no__icontains=query)
    )[:10]
    
    results = [
        {
            'id': c.pk,
            'text': f"{c.first_name} {c.last_name} ({c.email or c.phone})",
            'first_name': c.first_name,
            'last_name': c.last_name,
            'email': c.email or '',
            'phone': c.phone or '',
            'tc_no': c.tc_no or '',
        }
        for c in customers
    ]
    
    return JsonResponse({'results': results})


@login_required
@require_bungalov_permission('view')
def api_calculate_price(request):
    """API - Fiyat Hesapla"""
    bungalov_id = request.GET.get('bungalov_id')
    check_in_date = request.GET.get('check_in_date')
    check_out_date = request.GET.get('check_out_date')
    
    if not all([bungalov_id, check_in_date, check_out_date]):
        return JsonResponse({'error': 'Eksik parametreler'}, status=400)
    
    try:
        bungalov = Bungalov.objects.get(pk=bungalov_id, is_deleted=False)
        check_in = date.fromisoformat(check_in_date)
        check_out = date.fromisoformat(check_out_date)
        
        nights = (check_out - check_in).days
        if nights < 1:
            nights = 1
        
        # Fiyatlandırma hesaplama - BungalovPrice modelinden
        # Şimdilik basit hesaplama
        price = BungalovPrice.objects.filter(
            bungalov_type=bungalov.bungalov_type,
            start_date__lte=check_in,
            end_date__gte=check_out,
            is_active=True,
            is_deleted=False
        ).first()
        
        if price:
            base_price = price.base_price
        else:
            base_price = Decimal('0')
        
        total = base_price * nights
        
        return JsonResponse({
            'success': True,
            'nights': nights,
            'base_price': float(base_price),
            'total': float(total),
        })
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=400)


@login_required
@require_bungalov_permission('view')
def api_check_availability(request):
    """API - Müsaitlik Kontrolü"""
    bungalov_id = request.GET.get('bungalov_id')
    check_in_date = request.GET.get('check_in_date')
    check_out_date = request.GET.get('check_out_date')
    
    if not all([bungalov_id, check_in_date, check_out_date]):
        return JsonResponse({'error': 'Eksik parametreler'}, status=400)
    
    try:
        bungalov = Bungalov.objects.get(pk=bungalov_id, is_deleted=False)
        check_in = date.fromisoformat(check_in_date)
        check_out = date.fromisoformat(check_out_date)
        
        is_available = check_bungalov_availability(bungalov, check_in, check_out)
        
        return JsonResponse({
            'success': True,
            'available': is_available,
        })
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=400)

