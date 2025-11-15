"""
Tur Yönetim Views
Profesyonel tur operatörü yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.db.models import Q, Count, Sum, Avg, Min
from django.db.models.functions import TruncDate, TruncMonth, TruncYear
from django.core.paginator import Paginator
from django.utils import timezone
from django.forms import inlineformset_factory
from decimal import Decimal
from datetime import timedelta
from .models import (
    Tour, TourRegion, TourLocation, TourCity, TourType, TourVoucherTemplate,
    TourDate, TourProgram, TourImage, TourVideo, TourExtraService, TourRoute,
    TourReservation, TourGuest, TourReservationExtraService, TourPayment, TourReview,
    TourWaitingList, TourCustomer, TourLoyaltyHistory, TourCustomerNote,  # DEPRECATED: TourCustomer
    TourAgency, TourReservationCommission,
    TourGuide, TourVehicle, TourHotel, TourTransfer, TourReservationOperation,
    TourNotificationTemplate, TourNotification,
    TourCampaign, TourPromoCode
)
from apps.tenant_apps.core.models import Customer, CustomerLoyaltyHistory, CustomerNote
from .forms import (
    TourForm, TourRegionForm, TourLocationForm, TourCityForm, TourTypeForm,
    TourVoucherTemplateForm, TourDateForm, TourProgramForm, TourImageForm,
    TourVideoForm, TourExtraServiceForm, TourRouteForm, TourReservationForm, TourGuestForm,
    TourGuideForm, TourVehicleForm, TourHotelForm, TourTransferForm,
    TourCustomerForm, TourAgencyForm, TourCampaignForm, TourPromoCodeForm, TourNotificationTemplateForm
)
from .decorators import (
    require_tour_module, check_tour_limit, check_tour_reservation_limit
)
from apps.ai.services import generate_ai_content, get_tenant_ai_credit, check_credit_available
from apps.ai.models import AIProvider, AIModel, PackageAI
from apps.subscriptions.models import Subscription
from django.utils import timezone


# ==================== TUR CRUD ====================

@login_required
@require_tour_module
def tour_list(request):
    """Tur listeleme"""
    tours = Tour.objects.all()
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        tours = tours.filter(status=status)
    
    region_id = request.GET.get('region')
    if region_id:
        tours = tours.filter(region_id=region_id)
    
    location_id = request.GET.get('location')
    if location_id:
        tours = tours.filter(location_id=location_id)
    
    tour_type_id = request.GET.get('tour_type')
    if tour_type_id:
        tours = tours.filter(tour_type_id=tour_type_id)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        tours = tours.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        tours = tours.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-created_at')
    tours = tours.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(tours, 20)
    page = request.GET.get('page')
    tours = paginator.get_page(page)
    
    # Filtre seçenekleri
    regions = TourRegion.objects.filter(is_active=True)
    locations = TourLocation.objects.filter(is_active=True)
    tour_types = TourType.objects.filter(is_active=True)
    
    # Paket limiti bilgisi
    from .decorators import get_tour_module_limits
    limits = get_tour_module_limits(request)
    max_tours = limits.get('max_tours', 0) if limits else 0
    current_tours = Tour.objects.filter(is_active=True, is_deleted=False).count()
    can_add_tour = current_tours < max_tours if max_tours > 0 else True
    
    context = {
        'tours': tours,
        'regions': regions,
        'locations': locations,
        'tour_types': tour_types,
        'status_choices': Tour.STATUS_CHOICES,
        'max_tours': max_tours,
        'current_tours': current_tours,
        'can_add_tour': can_add_tour,
    }
    
    return render(request, 'tenant/tours/list.html', context)


@login_required
def tour_detail(request, pk):
    """Tur detay sayfası"""
    tour = get_object_or_404(Tour, pk=pk)
    
    # İstatistikler
    reservations = TourReservation.objects.filter(tour=tour)
    total_reservations = reservations.count()
    total_revenue = reservations.aggregate(total=Sum('total_amount'))['total'] or 0
    
    context = {
        'tour': tour,
        'total_reservations': total_reservations,
        'total_revenue': total_revenue,
    }
    
    return render(request, 'tenant/tours/detail.html', context)


@login_required
@require_tour_module
@check_tour_limit
@require_http_methods(["GET", "POST"])
def tour_create(request):
    """Tur ekleme"""
    if request.method == 'POST':
        form = TourForm(request.POST, request.FILES)
        if form.is_valid():
            tour = form.save()
            messages.success(request, 'Tur başarıyla oluşturuldu.')
            return redirect('tours:detail', pk=tour.pk)
        else:
            messages.error(request, 'Lütfen form hatalarını düzeltin.')
    else:
        form = TourForm()
    
    context = {
        'form': form,
        'regions': TourRegion.objects.filter(is_active=True),
        'locations': TourLocation.objects.filter(is_active=True),
        'cities': TourCity.objects.filter(is_active=True),
        'tour_types': TourType.objects.filter(is_active=True),
        'transport_choices': Tour.TRANSPORT_CHOICES,
    }
    
    return render(request, 'tenant/tours/form.html', context)


@login_required
@require_http_methods(["GET", "POST"])
def tour_update(request, pk):
    """Tur düzenleme"""
    tour = get_object_or_404(Tour, pk=pk)
    
    if request.method == 'POST':
        form = TourForm(request.POST, request.FILES, instance=tour)
        if form.is_valid():
            tour = form.save()
            messages.success(request, 'Tur başarıyla güncellendi.')
            return redirect('tours:detail', pk=tour.pk)
        else:
            messages.error(request, 'Lütfen form hatalarını düzeltin.')
    else:
        form = TourForm(instance=tour)
    
    context = {
        'tour': tour,
        'form': form,
        'regions': TourRegion.objects.filter(is_active=True),
        'locations': TourLocation.objects.filter(is_active=True),
        'cities': TourCity.objects.filter(is_active=True),
        'tour_types': TourType.objects.filter(is_active=True),
        'transport_choices': Tour.TRANSPORT_CHOICES,
    }
    
    return render(request, 'tenant/tours/form.html', context)


@login_required
@require_http_methods(["POST"])
def tour_delete(request, pk):
    """Tur silme (soft delete)"""
    tour = get_object_or_404(Tour, pk=pk)
    tour.soft_delete()
    messages.success(request, 'Tur başarıyla silindi.')
    return redirect('tours:list')


@login_required
@require_http_methods(["POST"])
def tour_toggle_status(request, pk):
    """Tur durumunu değiştir (aktif/pasif)"""
    tour = get_object_or_404(Tour, pk=pk)
    
    if request.method == 'POST':
        tour.is_active = not tour.is_active
        tour.save()
        
        status_text = 'aktif' if tour.is_active else 'pasif'
        messages.success(request, f'{tour.name} turu {status_text} olarak işaretlendi.')
        return redirect('tours:detail', pk=tour.pk)
    
    return redirect('tours:list')


@login_required
def tour_duplicate(request, pk):
    """Tur kopyalama"""
    tour = get_object_or_404(Tour, pk=pk)
    
    # Tur kopyalama işlemi
    new_tour = Tour.objects.get(pk=tour.pk)
    new_tour.pk = None
    new_tour.code = f"{tour.code}_copy"
    new_tour.name = f"{tour.name} (Kopya)"
    new_tour.status = 'draft'
    new_tour.save()
    
    # İlişkili verileri kopyala
    # TourDate, TourProgram, vb.
    
    messages.success(request, 'Tur başarıyla kopyalandı.')
    return redirect('tours:detail', pk=new_tour.pk)


# ==================== DİNAMİK YÖNETİM ====================

@login_required
def tour_region_list(request):
    """Bölge listesi"""
    regions = TourRegion.objects.all().order_by('sort_order', 'name')
    return render(request, 'tenant/tours/regions/list.html', {'regions': regions})


@login_required
@require_http_methods(["GET", "POST"])
def tour_region_create(request):
    """Bölge ekleme"""
    if request.method == 'POST':
        form = TourRegionForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bölge başarıyla oluşturuldu.')
            return redirect('tours:region_list')
    else:
        form = TourRegionForm()
    return render(request, 'tenant/tours/regions/form.html', {'form': form})


@login_required
@require_http_methods(["GET", "POST"])
def tour_region_update(request, pk):
    """Bölge güncelleme"""
    region = get_object_or_404(TourRegion, pk=pk)
    if request.method == 'POST':
        form = TourRegionForm(request.POST, instance=region)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bölge başarıyla güncellendi.')
            return redirect('tours:region_list')
    else:
        form = TourRegionForm(instance=region)
    return render(request, 'tenant/tours/regions/form.html', {'form': form, 'region': region})


@login_required
@require_http_methods(["POST"])
def tour_region_delete(request, pk):
    """Bölge silme"""
    region = get_object_or_404(TourRegion, pk=pk)
    region.soft_delete()
    messages.success(request, 'Bölge başarıyla silindi.')
    return redirect('tours:region_list')


# Benzer şekilde TourLocation, TourCity, TourType için de views oluşturulacak
# Şimdilik placeholder olarak bırakıyorum, sonra detaylandırılacak

@login_required
def tour_location_list(request):
    """Lokasyon listesi"""
    locations = TourLocation.objects.all().order_by('sort_order', 'name')
    return render(request, 'tenant/tours/locations/list.html', {'locations': locations})


@login_required
@require_http_methods(["GET", "POST"])
def tour_location_create(request):
    """Lokasyon ekleme"""
    if request.method == 'POST':
        form = TourLocationForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Lokasyon başarıyla oluşturuldu.')
            return redirect('tours:location_list')
    else:
        form = TourLocationForm()
    return render(request, 'tenant/tours/locations/form.html', {'form': form})


@login_required
@require_http_methods(["GET", "POST"])
def tour_location_update(request, pk):
    """Lokasyon güncelleme"""
    location = get_object_or_404(TourLocation, pk=pk)
    if request.method == 'POST':
        form = TourLocationForm(request.POST, instance=location)
        if form.is_valid():
            form.save()
            messages.success(request, 'Lokasyon başarıyla güncellendi.')
            return redirect('tours:location_list')
    else:
        form = TourLocationForm(instance=location)
    return render(request, 'tenant/tours/locations/form.html', {'form': form, 'location': location})


@login_required
@require_http_methods(["POST"])
def tour_location_delete(request, pk):
    """Lokasyon silme"""
    location = get_object_or_404(TourLocation, pk=pk)
    location.soft_delete()
    messages.success(request, 'Lokasyon başarıyla silindi.')
    return redirect('tours:location_list')


# ==================== REZERVASYON ====================

@login_required
def tour_reservation_list(request):
    """Rezervasyon listesi"""
    reservations = TourReservation.objects.all().select_related('tour', 'tour_date', 'sales_person').prefetch_related('payments').order_by('-created_at')
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        reservations = reservations.filter(status=status)
    
    tour_id = request.GET.get('tour')
    if tour_id:
        reservations = reservations.filter(tour_id=tour_id)
    
    # Sayfalama
    paginator = Paginator(reservations, 20)
    page = request.GET.get('page', 1)
    try:
        reservations = paginator.page(page)
    except:
        reservations = paginator.page(1)
    
    # Her rezervasyon için ödeme bilgilerini hesapla
    for reservation in reservations:
        reservation.total_paid = reservation.payments.filter(status='completed').aggregate(total=Sum('amount'))['total'] or Decimal('0')
        reservation.remaining_amount = reservation.total_amount - reservation.total_paid
    
    # Context için gerekli veriler
    tours = Tour.objects.filter(is_active=True).order_by('name')
    status_choices = TourReservation.STATUS_CHOICES
    
    context = {
        'reservations': reservations,
        'tours': tours,
        'status_choices': status_choices,
    }
    
    return render(request, 'tenant/tours/reservations/list.html', context)


@login_required
def tour_reservation_detail(request, pk):
    """Rezervasyon detayı"""
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    # Ödeme toplamı
    total_paid = reservation.payments.filter(status='completed').aggregate(total=Sum('amount'))['total'] or 0
    remaining_amount = reservation.total_amount - Decimal(str(total_paid))
    
    # Ödeme ve iade kontrolü (iptal/silme için)
    from apps.tenant_apps.core.utils import can_delete_with_payment_check
    delete_check = can_delete_with_payment_check(reservation, 'tours')
    
    context = {
        'reservation': reservation,
        'total_paid': total_paid,
        'remaining_amount': remaining_amount,
        'delete_check': delete_check,
        'can_delete': delete_check['can_delete'],
        'has_payment': delete_check['has_payment'],
        'refund_status': delete_check['refund_status'],
        'refund_request': delete_check['refund_request'],
        'refund_message': delete_check['message'],
    }
    
    return render(request, 'tenant/tours/reservations/detail.html', context)


# ==================== AJAX ENDPOINTS ====================

@login_required
def ajax_get_tour_price(request):
    """AJAX - Tur fiyatı getir (tarih bazlı)"""
    tour_id = request.GET.get('tour_id')
    date_id = request.GET.get('date_id')
    is_adult = request.GET.get('is_adult', 'true') == 'true'
    
    try:
        tour = Tour.objects.get(pk=tour_id)
        price = tour.adult_price if is_adult else tour.child_price
        
        # Tarih bazlı fiyat varsa onu kullan
        if date_id:
            try:
                tour_date = TourDate.objects.get(pk=date_id, tour=tour)
                price = tour_date.get_adult_price() if is_adult else tour_date.get_child_price()
            except TourDate.DoesNotExist:
                pass
        
        return JsonResponse({
            'success': True,
            'price': float(price),
            'currency': tour.currency,
        })
    except Tour.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'Tur bulunamadı'})


@login_required
def ajax_get_available_capacity(request):
    """AJAX - Müsait kontenjan getir"""
    date_id = request.GET.get('date_id')
    
    try:
        tour_date = TourDate.objects.get(pk=date_id)
        capacity = tour_date.tour.get_available_capacity(tour_date.date)
        
        return JsonResponse({
            'success': True,
            'capacity': capacity,
        })
    except TourDate.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'Tur tarihi bulunamadı'})
    
# Yeni endpoint: Tur tarihlerini getir
@login_required
def ajax_get_tour_dates(request):
    """AJAX - Tur tarihlerini getir (Geçmiş tarihler hariç)"""
    tour_id = request.GET.get('tour_id')
    
    try:
        tour = Tour.objects.get(pk=tour_id)
        dates = tour.tour_dates.filter(is_active=True).order_by('date')
        
        dates_list = []
        for date_obj in dates:
            # Geçmiş tarihleri filtrele
            if date_obj.is_expired():
                continue
            
            dates_list.append({
                'id': date_obj.pk,
                'date': date_obj.date.strftime('%d.%m.%Y'),
                'adult_price': float(date_obj.get_adult_price()),
                'child_price': float(date_obj.get_child_price()),
            })
        
        return JsonResponse({
            'success': True,
            'dates': dates_list,
        })
    except Tour.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'Tur bulunamadı'})


# Placeholder views - TODO: Detaylandırılacak
@login_required
def tour_city_list(request):
    """Şehir listesi"""
    cities = TourCity.objects.all().order_by('sort_order', 'name')
    return render(request, 'tenant/tours/cities/list.html', {'cities': cities})

@login_required
@require_http_methods(["GET", "POST"])
def tour_city_create(request):
    """Şehir ekleme"""
    if request.method == 'POST':
        form = TourCityForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Şehir başarıyla oluşturuldu.')
            return redirect('tours:city_list')
    else:
        form = TourCityForm()
    return render(request, 'tenant/tours/cities/form.html', {'form': form})

@login_required
@require_http_methods(["GET", "POST"])
def tour_city_update(request, pk):
    """Şehir güncelleme"""
    city = get_object_or_404(TourCity, pk=pk)
    if request.method == 'POST':
        form = TourCityForm(request.POST, instance=city)
        if form.is_valid():
            form.save()
            messages.success(request, 'Şehir başarıyla güncellendi.')
            return redirect('tours:city_list')
    else:
        form = TourCityForm(instance=city)
    return render(request, 'tenant/tours/cities/form.html', {'form': form, 'city': city})

@login_required
@require_http_methods(["POST"])
def tour_city_delete(request, pk):
    """Şehir silme"""
    city = get_object_or_404(TourCity, pk=pk)
    city.soft_delete()
    messages.success(request, 'Şehir başarıyla silindi.')
    return redirect('tours:city_list')

@login_required
def tour_type_list(request):
    """Tür listesi"""
    types = TourType.objects.all().order_by('sort_order', 'name')
    return render(request, 'tenant/tours/types/list.html', {'types': types})

@login_required
@require_http_methods(["GET", "POST"])
def tour_type_create(request):
    """Tür ekleme"""
    if request.method == 'POST':
        form = TourTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Tür başarıyla oluşturuldu.')
            return redirect('tours:type_list')
    else:
        form = TourTypeForm()
    return render(request, 'tenant/tours/types/form.html', {'form': form})

@login_required
@require_http_methods(["GET", "POST"])
def tour_type_update(request, pk):
    """Tür güncelleme"""
    tour_type = get_object_or_404(TourType, pk=pk)
    if request.method == 'POST':
        form = TourTypeForm(request.POST, instance=tour_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Tür başarıyla güncellendi.')
            return redirect('tours:type_list')
    else:
        form = TourTypeForm(instance=tour_type)
    return render(request, 'tenant/tours/types/form.html', {'form': form, 'tour_type': tour_type})

@login_required
@require_http_methods(["POST"])
def tour_type_delete(request, pk):
    """Tür silme"""
    tour_type = get_object_or_404(TourType, pk=pk)
    tour_type.soft_delete()
    messages.success(request, 'Tür başarıyla silindi.')
    return redirect('tours:type_list')

@login_required
def tour_voucher_template_list(request):
    """Voucher şablon listesi"""
    templates = TourVoucherTemplate.objects.all()
    return render(request, 'tenant/tours/voucher_templates/list.html', {'templates': templates})

@login_required
@require_http_methods(["GET", "POST"])
def tour_voucher_template_create(request):
    """Voucher şablon ekleme"""
    if request.method == 'POST':
        form = TourVoucherTemplateForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Voucher şablonu başarıyla oluşturuldu.')
            return redirect('tours:voucher_template_list')
    else:
        form = TourVoucherTemplateForm()
    return render(request, 'tenant/tours/voucher_templates/form.html', {'form': form})

@login_required
@require_http_methods(["GET", "POST"])
def tour_voucher_template_update(request, pk):
    """Voucher şablon güncelleme"""
    template = get_object_or_404(TourVoucherTemplate, pk=pk)
    if request.method == 'POST':
        form = TourVoucherTemplateForm(request.POST, instance=template)
        if form.is_valid():
            form.save()
            messages.success(request, 'Voucher şablonu başarıyla güncellendi.')
            return redirect('tours:voucher_template_list')
    else:
        form = TourVoucherTemplateForm(instance=template)
    return render(request, 'tenant/tours/voucher_templates/form.html', {'form': form, 'template': template})

@login_required
@require_http_methods(["POST"])
def tour_voucher_template_delete(request, pk):
    """Voucher şablon silme"""
    template = get_object_or_404(TourVoucherTemplate, pk=pk)
    template.soft_delete()
    messages.success(request, 'Voucher şablonu başarıyla silindi.')
    return redirect('tours:voucher_template_list')

# Diğer placeholder views - TODO: Detaylandırılacak
@login_required
@require_http_methods(["GET", "POST"])
def tour_date_add(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourDateForm(request.POST)
        if form.is_valid():
            tour_date = form.save(commit=False)
            tour_date.tour = tour
            tour_date.save()
            messages.success(request, 'Tur tarihi başarıyla eklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourDateForm()
    return render(request, 'tenant/tours/dates/form.html', {'form': form, 'tour': tour})

@login_required
@require_http_methods(["GET", "POST"])
def tour_date_update(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    tour_date = get_object_or_404(TourDate, pk=pk, tour=tour)
    if request.method == 'POST':
        form = TourDateForm(request.POST, instance=tour_date)
        if form.is_valid():
            form.save()
            messages.success(request, 'Tur tarihi başarıyla güncellendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourDateForm(instance=tour_date)
    return render(request, 'tenant/tours/dates/form.html', {'form': form, 'tour': tour, 'tour_date': tour_date})

@login_required
@require_http_methods(["POST"])
def tour_date_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    tour_date = get_object_or_404(TourDate, pk=pk, tour=tour)
    tour_date.delete()
    messages.success(request, 'Tur tarihi başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

# Diğer placeholder views - TODO: Detaylandırılacak
@login_required
@require_http_methods(["GET", "POST"])
def tour_program_add(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourProgramForm(request.POST)
        if form.is_valid():
            program = form.save(commit=False)
            program.tour = tour
            program.save()
            messages.success(request, 'Program günü başarıyla eklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourProgramForm()
    return render(request, 'tenant/tours/programs/form.html', {'form': form, 'tour': tour})

@login_required
@require_http_methods(["GET", "POST"])
def tour_program_update(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    program = get_object_or_404(TourProgram, pk=pk, tour=tour)
    if request.method == 'POST':
        form = TourProgramForm(request.POST, instance=program)
        if form.is_valid():
            form.save()
            messages.success(request, 'Program günü başarıyla güncellendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourProgramForm(instance=program)
    return render(request, 'tenant/tours/programs/form.html', {'form': form, 'tour': tour, 'program': program})

@login_required
@require_http_methods(["POST"])
def tour_program_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    program = get_object_or_404(TourProgram, pk=pk, tour=tour)
    program.delete()
    messages.success(request, 'Program günü başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

@login_required
@require_http_methods(["GET", "POST"])
def tour_image_upload(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourImageForm(request.POST, request.FILES)
        if form.is_valid():
            image = form.save(commit=False)
            image.tour = tour
            image.save()
            messages.success(request, 'Resim başarıyla yüklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourImageForm()
    return render(request, 'tenant/tours/images/upload.html', {'form': form, 'tour': tour})

@login_required
@require_http_methods(["POST"])
def tour_image_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    image = get_object_or_404(TourImage, pk=pk, tour=tour)
    image.delete()
    messages.success(request, 'Resim başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

@login_required
@require_http_methods(["GET", "POST"])
def tour_video_add(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourVideoForm(request.POST)
        if form.is_valid():
            video = form.save(commit=False)
            video.tour = tour
            video.save()
            messages.success(request, 'Video başarıyla eklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourVideoForm()
    return render(request, 'tenant/tours/videos/form.html', {'form': form, 'tour': tour})

@login_required
@require_http_methods(["POST"])
def tour_video_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    video = get_object_or_404(TourVideo, pk=pk, tour=tour)
    video.delete()
    messages.success(request, 'Video başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

@login_required
@require_http_methods(["GET", "POST"])
def tour_extra_service_add(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourExtraServiceForm(request.POST)
        if form.is_valid():
            service = form.save(commit=False)
            service.tour = tour
            service.save()
            messages.success(request, 'Ekstra hizmet başarıyla eklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourExtraServiceForm()
    return render(request, 'tenant/tours/extra_services/form.html', {'form': form, 'tour': tour})

@login_required
@require_http_methods(["GET", "POST"])
def tour_extra_service_update(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    service = get_object_or_404(TourExtraService, pk=pk, tour=tour)
    if request.method == 'POST':
        form = TourExtraServiceForm(request.POST, instance=service)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ekstra hizmet başarıyla güncellendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourExtraServiceForm(instance=service)
    return render(request, 'tenant/tours/extra_services/form.html', {'form': form, 'tour': tour, 'service': service})

@login_required
@require_http_methods(["POST"])
def tour_extra_service_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    service = get_object_or_404(TourExtraService, pk=pk, tour=tour)
    service.delete()
    messages.success(request, 'Ekstra hizmet başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

@login_required
@require_http_methods(["GET", "POST"])
def tour_route_add(request, tour_pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    if request.method == 'POST':
        form = TourRouteForm(request.POST)
        if form.is_valid():
            route = form.save(commit=False)
            route.tour = tour
            route.save()
            messages.success(request, 'Rota başarıyla eklendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourRouteForm()
    cities = TourCity.objects.filter(is_active=True)
    return render(request, 'tenant/tours/routes/form.html', {'form': form, 'tour': tour, 'cities': cities})

@login_required
@require_http_methods(["GET", "POST"])
def tour_route_update(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    route = get_object_or_404(TourRoute, pk=pk, tour=tour)
    if request.method == 'POST':
        form = TourRouteForm(request.POST, instance=route)
        if form.is_valid():
            form.save()
            messages.success(request, 'Rota başarıyla güncellendi.')
            return redirect('tours:detail', pk=tour.pk)
    else:
        form = TourRouteForm(instance=route)
    cities = TourCity.objects.filter(is_active=True)
    return render(request, 'tenant/tours/routes/form.html', {'form': form, 'tour': tour, 'route': route, 'cities': cities})

@login_required
@require_http_methods(["POST"])
def tour_route_delete(request, tour_pk, pk):
    tour = get_object_or_404(Tour, pk=tour_pk)
    route = get_object_or_404(TourRoute, pk=pk, tour=tour)
    route.delete()
    messages.success(request, 'Rota başarıyla silindi.')
    return redirect('tours:detail', pk=tour.pk)

@login_required
def tour_pdf_program(request, pk):
    """PDF program oluştur ve göster"""
    from .utils import generate_tour_pdf_program
    from django.http import HttpResponse
    
    tour = get_object_or_404(Tour, pk=pk)
    
    try:
        buffer, filename = generate_tour_pdf_program(tour)
        
        if buffer:
            response = HttpResponse(buffer.getvalue(), content_type='application/pdf')
            response['Content-Disposition'] = f'attachment; filename="tur_program_{tour.code}.pdf"'
            return response
        else:
            # reportlab yoksa HTML olarak göster
            return render(request, 'tenant/tours/pdf_program.html', {'tour': tour})
    except Exception as e:
        messages.error(request, f'PDF oluşturulurken hata oluştu: {str(e)}')
        return redirect('tours:detail', pk=tour.pk)

@login_required
def tour_map(request, pk):
    """Tur haritası"""
    tour = get_object_or_404(Tour, pk=pk)
    routes = tour.routes.all().order_by('order')
    return render(request, 'tenant/tours/map.html', {'tour': tour, 'routes': routes})

@login_required
@require_tour_module
@check_tour_reservation_limit
@require_http_methods(["GET", "POST"])
def tour_reservation_create(request):
    """Rezervasyon oluşturma"""
    tour_id = request.GET.get('tour')
    initial_tour = None
    if tour_id:
        try:
            initial_tour = Tour.objects.get(pk=tour_id, is_active=True)
        except Tour.DoesNotExist:
            pass
    
    if request.method == 'POST':
        form = TourReservationForm(request.POST)
        if form.is_valid():
            reservation = form.save(commit=False)
            
            # Satış elemanı atama
            try:
                from apps.tenant_apps.core.models import TenantUser
                tenant_user = TenantUser.objects.get(user=request.user)
                reservation.sales_person = tenant_user
            except:
                pass
            
            # Fiyat hesaplama (Dinamik fiyatlandırma ile)
            tour_date = reservation.tour_date
            reservation_date = timezone.now().date()
            
            # Dinamik fiyatlandırma aktifse kullan
            if reservation.tour.enable_dynamic_pricing:
                reservation.adult_price = reservation.tour.get_current_price(
                    date=tour_date.date,
                    is_adult=True,
                    reservation_date=reservation_date
                )
                reservation.child_price = reservation.tour.get_current_price(
                    date=tour_date.date,
                    is_adult=False,
                    reservation_date=reservation_date
                )
            else:
                # Eski yöntem
                reservation.adult_price = tour_date.get_adult_price()
                reservation.child_price = tour_date.get_child_price()
            
            # Toplam kişi
            reservation.total_people = reservation.adult_count + reservation.child_count
            
            # Kontenjan kontrolü
            capacity_check = reservation.check_capacity()
            if not capacity_check['available']:
                # Bekleme listesine eklenebilir mi kontrol et
                if capacity_check.get('add_to_waiting_list', False):
                    # Kullanıcıya bekleme listesine ekleme seçeneği sun
                    add_to_waiting = request.POST.get('add_to_waiting_list', 'no')
                    if add_to_waiting == 'yes':
                        # Bekleme listesine ekle
                        waiting_list = reservation.add_to_waiting_list(priority=0)
                        messages.info(request, f'Kontenjan dolu. Rezervasyonunuz bekleme listesine eklendi. Müsaitlik durumunda size bildirim gönderilecektir.')
                        return redirect('tours:waiting_list')
                    else:
                        # Kullanıcıya bekleme listesine ekleme seçeneği göster
                        context['capacity_check'] = capacity_check
                        context['show_waiting_list_option'] = True
                        messages.warning(request, capacity_check['message'] + ' Bekleme listesine eklenmek ister misiniz?')
                        return render(request, 'tenant/tours/reservations/create.html', context)
                else:
                    messages.error(request, capacity_check['message'])
                    return render(request, 'tenant/tours/reservations/create.html', context)
            
            # Toplam tutarı hesapla (dinamik fiyatlandırma dahil)
            reservation.calculate_total()
            reservation.status = 'pending'
            reservation.save()
            
            # Rezervasyon oluşturuldu bildirimi gönder
            from .utils_notifications import send_reservation_notifications
            send_reservation_notifications(reservation, 'reservation_created')
            
            # Misafirleri ekle (formset ile)
            # TODO: Guest formset işleme - şimdilik sadece rezervasyon kaydediliyor
            
            messages.success(request, 'Rezervasyon başarıyla oluşturuldu.')
            return redirect('tours:reservation_detail', pk=reservation.pk)
        else:
            messages.error(request, 'Lütfen form hatalarını düzeltin.')
    else:
        form = TourReservationForm(initial={'tour': initial_tour} if initial_tour else {})
    
    tours = Tour.objects.filter(is_active=True, status='published')
    
    # Seçili tur için tarihleri getir (Geçmiş tarihler hariç)
    tour_dates = []
    if initial_tour:
        all_dates = initial_tour.tour_dates.filter(is_active=True).order_by('date')
        # Geçmiş tarihleri filtrele
        tour_dates = [date_obj for date_obj in all_dates if not date_obj.is_expired()]
    
    context = {
        'form': form,
        'tours': tours,
        'tour_dates': tour_dates,
        'initial_tour': initial_tour,
    }
    
    return render(request, 'tenant/tours/reservations/create.html', context)

@login_required
@require_http_methods(["GET", "POST"])
def tour_reservation_update(request, pk):
    """Rezervasyon güncelleme"""
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    if request.method == 'POST':
        form = TourReservationForm(request.POST, instance=reservation)
        if form.is_valid():
            reservation = form.save()
            # Fiyatları yeniden hesapla
            reservation.calculate_total()
            reservation.save()
            messages.success(request, 'Rezervasyon başarıyla güncellendi.')
            return redirect('tours:reservation_detail', pk=reservation.pk)
    else:
        form = TourReservationForm(instance=reservation)
    
    tours = Tour.objects.filter(is_active=True, status='published')
    tour_dates = reservation.tour.tour_dates.filter(is_active=True).order_by('date') if reservation.tour else []
    
    context = {
        'reservation': reservation,
        'form': form,
        'tours': tours,
        'tour_dates': tour_dates,
    }
    
    return render(request, 'tenant/tours/reservations/form.html', context)

@login_required
@require_http_methods(["POST"])
def tour_reservation_cancel(request, pk):
    """Rezervasyon iptal - Ödeme ve İade Kontrolü ile"""
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    if reservation.status == 'cancelled':
        messages.warning(request, 'Bu rezervasyon zaten iptal edilmiş.')
        return redirect('tours:reservation_detail', pk=reservation.pk)
    
    # Ödeme ve iade kontrolü
    from apps.tenant_apps.core.utils import can_delete_with_payment_check, start_refund_process_for_deletion
    
    delete_check = can_delete_with_payment_check(reservation, 'tours')
    start_refund = request.POST.get('start_refund', '0') == '1' if request.method == 'POST' else False
    
    # İade başlatma isteği varsa
    if start_refund and delete_check['has_payment'] and not delete_check['refund_request']:
        refund_request = start_refund_process_for_deletion(
            reservation,
            'tours',
            request.user,
            reason='Rezervasyon iptal işlemi için iade'
        )
        
        if refund_request:
            messages.success(
                request,
                f'İade süreci başlatıldı. İade Talebi No: {refund_request.request_number}. '
                f'İade tamamlandıktan sonra iptal işlemini yapabilirsiniz.'
            )
            return redirect('refunds:refund_request_detail', pk=refund_request.pk)
        else:
            messages.error(request, 'İade süreci başlatılamadı. Lütfen tekrar deneyin.')
            return redirect('tours:reservation_detail', pk=reservation.pk)
    
    # Ödeme kontrolü - İptal işlemi için
    if delete_check['has_payment'] and not delete_check['can_delete']:
        messages.error(request, delete_check['message'])
        if request.method == 'POST':
            return redirect('tours:reservation_detail', pk=reservation.pk)
        # GET request ise detail sayfasına yönlendir (modal orada açılacak)
        return redirect('tours:reservation_detail', pk=reservation.pk)
    
    # İptal işlemi - kontenjan geri verilir (otomatik olarak get_available_capacity'de hesaplanır)
    reservation.status = 'cancelled'
    reservation.payment_status = 'refunded' if reservation.payment_status == 'paid' else 'cancelled'
    reservation.save()
    
    # Bekleme listesindeki ilk müşteriye bildirim gönder
    from .models import TourWaitingList
    waiting_list = TourWaitingList.objects.filter(
        tour=reservation.tour,
        tour_date=reservation.tour_date,
        status='waiting'
    ).order_by('priority', 'created_at').first()
    
    if waiting_list:
        # Müsaitlik bildirimi gönder
        waiting_list.notify_availability(method='email')
        messages.info(request, f'Bekleme listesindeki müşteriye ({waiting_list.customer_email}) müsaitlik bildirimi gönderildi.')
    
    messages.success(request, 'Rezervasyon iptal edildi. Kontenjan geri verildi.')
    return redirect('tours:reservation_detail', pk=reservation.pk)

@login_required
@require_http_methods(["POST"])
def tour_reservation_refund(request, pk):
    """Rezervasyon iade"""
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    if reservation.status == 'refunded':
        messages.warning(request, 'Bu rezervasyon zaten iade edilmiş.')
        return redirect('tours:reservation_detail', pk=reservation.pk)
    
    reservation.status = 'refunded'
    reservation.payment_status = 'refunded'
    reservation.save()
    
    messages.success(request, 'Rezervasyon iade edildi.')
    return redirect('tours:reservation_detail', pk=reservation.pk)

@login_required
def tour_reservation_voucher(request, pk):
    """Voucher görüntüleme"""
    from .utils import generate_reservation_voucher
    
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    voucher_html = generate_reservation_voucher(reservation)
    
    context = {
        'reservation': reservation,
        'voucher_html': voucher_html,
    }
    
    return render(request, 'tenant/tours/reservations/voucher.html', context)

@login_required
def tour_reservation_voucher_send_whatsapp(request, pk):
    """Voucher WhatsApp ile gönderme"""
    from .utils import create_whatsapp_link, generate_reservation_voucher
    
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    # Voucher mesajı oluştur
    message = f"""Tur Rezervasyon Voucher'ınız

Rezervasyon Kodu: {reservation.reservation_code}
Tur: {reservation.tour.name}
Tarih: {reservation.tour_date.date.strftime('%d.%m.%Y')}
Müşteri: {reservation.customer_name} {reservation.customer_surname}
Toplam Tutar: {reservation.total_amount} {reservation.currency}

Detaylar için: {request.build_absolute_uri(reservation.get_absolute_url())}
"""
    
    # WhatsApp link oluştur
    whatsapp_link = create_whatsapp_link(reservation.customer_phone, message)
    
    # Kullanıcıya link göster veya direkt yönlendir
    if request.GET.get('redirect') == 'true':
        return redirect(whatsapp_link)
    else:
        messages.success(request, f'WhatsApp linki oluşturuldu. <a href="{whatsapp_link}" target="_blank">Göndermek için tıklayın</a>', extra_tags='safe')
        return redirect('tours:reservation_detail', pk=reservation.pk)

@login_required
@require_http_methods(["GET", "POST"])
def tour_reservation_payment(request, pk):
    """Rezervasyon ödeme"""
    reservation = get_object_or_404(TourReservation, pk=pk)
    
    # Ödeme toplamı
    total_paid = reservation.payments.filter(status='completed').aggregate(total=Sum('amount'))['total'] or 0
    remaining_amount = reservation.total_amount - Decimal(str(total_paid))
    
    if request.method == 'POST':
        amount = Decimal(request.POST.get('amount', 0))
        payment_method = request.POST.get('payment_method')
        payment_date = request.POST.get('payment_date')
        transaction_id = request.POST.get('transaction_id', '')
        notes = request.POST.get('notes', '')
        
        if amount <= 0:
            messages.error(request, 'Ödeme tutarı 0\'dan büyük olmalıdır.')
        elif amount > remaining_amount:
            messages.error(request, f'Ödeme tutarı kalan tutardan ({remaining_amount} {reservation.currency}) fazla olamaz.')
        else:
            # Ödeme kaydı oluştur
            from .models import TourPayment
            from datetime import datetime
            
            payment_date_obj = None
            if payment_date:
                try:
                    payment_date_obj = datetime.strptime(payment_date, '%Y-%m-%d').date()
                except:
                    payment_date_obj = timezone.now().date()
            else:
                payment_date_obj = timezone.now().date()
            
            payment = TourPayment.objects.create(
                reservation=reservation,
                amount=amount,
                currency=reservation.currency,
                payment_method=payment_method,
                payment_date=payment_date_obj,
                transaction_id=transaction_id,
                notes=notes,
                status='completed',
            )
            
            # Rezervasyon ödeme durumunu güncelle
            new_total_paid = total_paid + amount
            if new_total_paid >= reservation.total_amount:
                reservation.payment_status = 'paid'
            elif new_total_paid > 0:
                reservation.payment_status = 'partial'
            reservation.save()
            
            messages.success(request, f'{amount} {reservation.currency} ödeme başarıyla eklendi.')
            return redirect('tours:reservation_detail', pk=reservation.pk)
    
    context = {
        'reservation': reservation,
        'total_paid': total_paid,
        'remaining_amount': remaining_amount,
    }
    
    return render(request, 'tenant/tours/reservations/payment.html', context)

@login_required
def ajax_calculate_reservation_total(request):
    """AJAX - Rezervasyon toplam fiyat hesaplama"""
    try:
        tour_id = request.GET.get('tour_id')
        date_id = request.GET.get('date_id')
        adult_count = int(request.GET.get('adult_count', 0))
        child_count = int(request.GET.get('child_count', 0))
        discount = Decimal(request.GET.get('discount', 0))
        
        tour = Tour.objects.get(pk=tour_id)
        tour_date = TourDate.objects.get(pk=date_id, tour=tour) if date_id else None
        
        # Fiyatları al (Dinamik fiyatlandırma ile)
        reservation_date = timezone.now().date()
        tour_date_obj = tour_date.date if tour_date else None
        
        if tour.enable_dynamic_pricing:
            adult_price = tour.get_current_price(
                date=tour_date_obj,
                is_adult=True,
                reservation_date=reservation_date
            )
            child_price = tour.get_current_price(
                date=tour_date_obj,
                is_adult=False,
                reservation_date=reservation_date
            )
        else:
            # Eski yöntem
            adult_price = tour_date.get_adult_price() if tour_date else tour.adult_price
            child_price = tour_date.get_child_price() if tour_date else tour.child_price
        
        # Ara toplam
        adult_total = Decimal(str(adult_count)) * adult_price
        child_total = Decimal(str(child_count)) * child_price
        subtotal = adult_total + child_total
        
        # Grup fiyat kontrolü (sadece dinamik fiyatlandırma yoksa)
        total_people = adult_count + child_count
        if not tour.enable_dynamic_pricing and tour.group_price and total_people >= tour.group_min_people:
            subtotal = Decimal(str(total_people)) * tour.group_price
        
        # Kampanya fiyat kontrolü (dinamik fiyatlandırma içinde zaten var, burada tekrar kontrol etmeye gerek yok)
        
        total = subtotal - discount
        
        return JsonResponse({
            'success': True,
            'adult_price': float(adult_price),
            'child_price': float(child_price),
            'adult_total': float(adult_total),
            'child_total': float(child_total),
            'subtotal': float(subtotal),
            'discount': float(discount),
            'total': float(total),
            'currency': tour.currency,
        })
    except Exception as e:
        return JsonResponse({'success': False, 'error': str(e)})


# ==================== RAPORLAMA SİSTEMİ ====================

from .reports import (
    get_date_range, get_period_stats, get_daily_stats,
    get_top_tours, get_top_customers, get_salesperson_performance,
    get_capacity_utilization, get_cancellation_stats
)


@login_required
def reports_dashboard(request):
    """Raporlama Dashboard - Genel Özet"""
    date_from, date_to = get_date_range(request)
    
    # Tüm rezervasyonlar ve ödemeler
    reservations = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    )
    payments = TourPayment.objects.filter(
        payment_date__gte=date_from,
        payment_date__lte=date_to
    )
    
    # Genel istatistikler
    stats = get_period_stats(reservations, payments)
    
    # Günlük istatistikler (son 30 gün)
    daily_stats = get_daily_stats(
        timezone.now().date() - timedelta(days=30),
        timezone.now().date()
    )
    
    # En çok satan turlar
    top_tours = get_top_tours(date_from, date_to, limit=5)
    
    # En çok rezervasyon yapan müşteriler
    top_customers = get_top_customers(date_from, date_to, limit=5)
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'stats': stats,
        'daily_stats': daily_stats,
        'top_tours': top_tours,
        'top_customers': top_customers,
    }
    
    return render(request, 'tenant/tours/reports/dashboard.html', context)


@login_required
def report_sales(request):
    """Satış Raporları"""
    date_from, date_to = get_date_range(request)
    period = request.GET.get('period', 'daily')  # daily, weekly, monthly, yearly
    
    reservations = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to,
        status__in=['confirmed', 'completed']
    )
    
    if period == 'daily':
        sales_data = reservations.annotate(
            period=TruncDate('created_at')
        ).values('period').annotate(
            count=Count('id'),
            revenue=Sum('total_amount'),
            people=Sum('total_people')
        ).order_by('period')
    elif period == 'weekly':
        from django.db.models.functions import TruncWeek
        sales_data = reservations.annotate(
            period=TruncWeek('created_at')
        ).values('period').annotate(
            count=Count('id'),
            revenue=Sum('total_amount'),
            people=Sum('total_people')
        ).order_by('period')
    elif period == 'monthly':
        sales_data = reservations.annotate(
            period=TruncMonth('created_at')
        ).values('period').annotate(
            count=Count('id'),
            revenue=Sum('total_amount'),
            people=Sum('total_people')
        ).order_by('period')
    else:  # yearly
        sales_data = reservations.annotate(
            period=TruncYear('created_at')
        ).values('period').annotate(
            count=Count('id'),
            revenue=Sum('total_amount'),
            people=Sum('total_people')
        ).order_by('period')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'period': period,
        'sales_data': sales_data,
        'total_revenue': reservations.aggregate(Sum('total_amount'))['total_amount__sum'] or Decimal('0'),
        'total_count': reservations.count(),
    }
    
    return render(request, 'tenant/tours/reports/sales.html', context)


@login_required
def report_reservations(request):
    """Rezervasyon Raporları"""
    date_from, date_to = get_date_range(request)
    
    reservations_qs = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    ).select_related('tour', 'tour_date', 'sales_person')
    
    # Durum bazlı filtreleme
    status_filter = request.GET.get('status')
    if status_filter:
        reservations_qs = reservations_qs.filter(status=status_filter)
    
    # Tur bazlı filtreleme
    tour_filter = request.GET.get('tour')
    if tour_filter:
        reservations_qs = reservations_qs.filter(tour_id=tour_filter)
    
    # İstatistikler (sayfalama öncesi QuerySet üzerinde)
    stats = get_period_stats(reservations_qs, TourPayment.objects.none())
    
    # Sayfalama
    paginator = Paginator(reservations_qs, 50)
    page = request.GET.get('page', 1)
    try:
        reservations = paginator.page(page)
    except:
        reservations = paginator.page(1)
    
    tours = Tour.objects.filter(is_active=True).order_by('name')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'reservations': reservations,
        'stats': stats,
        'tours': tours,
        'status_choices': TourReservation.STATUS_CHOICES,
    }
    
    return render(request, 'tenant/tours/reports/reservations.html', context)


@login_required
def report_revenue(request):
    """Gelir Raporları"""
    date_from, date_to = get_date_range(request)
    
    payments = TourPayment.objects.filter(
        payment_date__gte=date_from,
        payment_date__lte=date_to,
        status='completed'
    ).select_related('reservation', 'reservation__tour')
    
    # Günlük gelir
    daily_revenue = payments.values('payment_date').annotate(
        total=Sum('amount'),
        count=Count('id')
    ).order_by('payment_date')
    
    # Ödeme yöntemleri bazlı
    payment_method_revenue = payments.values('payment_method').annotate(
        total=Sum('amount'),
        count=Count('id')
    ).order_by('-total')
    
    # Tur bazlı gelir
    tour_revenue = Tour.objects.filter(
        reservations__payments__payment_date__gte=date_from,
        reservations__payments__payment_date__lte=date_to,
        reservations__payments__status='completed'
    ).annotate(
        total_revenue=Sum('reservations__payments__amount'),
        payment_count=Count('reservations__payments')
    ).order_by('-total_revenue')[:20]
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'daily_revenue': daily_revenue,
        'payment_method_revenue': payment_method_revenue,
        'tour_revenue': tour_revenue,
        'total_revenue': payments.aggregate(Sum('amount'))['amount__sum'] or Decimal('0'),
    }
    
    return render(request, 'tenant/tours/reports/revenue.html', context)


@login_required
def report_customers(request):
    """Müşteri Analizi"""
    date_from, date_to = get_date_range(request)
    
    top_customers = get_top_customers(date_from, date_to, limit=50)
    
    # Müşteri segmentasyonu
    customer_segments = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to,
        status__in=['confirmed', 'completed']
    ).values('customer_email').annotate(
        reservation_count=Count('id'),
        total_spent=Sum('total_amount')
    ).order_by('-total_spent')
    
    # Yeni müşteriler (ilk rezervasyon)
    new_customers = TourReservation.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to,
        status__in=['confirmed', 'completed']
    ).values('customer_email').annotate(
        first_reservation=Min('created_at')
    ).filter(
        first_reservation__date__gte=date_from
    ).count()
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'top_customers': top_customers,
        'customer_segments': customer_segments,
        'new_customers': new_customers,
    }
    
    return render(request, 'tenant/tours/reports/customers.html', context)


@login_required
def report_tour_performance(request):
    """Tur Performans Raporları"""
    date_from, date_to = get_date_range(request)
    
    top_tours = get_top_tours(date_from, date_to, limit=50)
    
    # Tur kategorileri bazlı performans
    tour_performance_by_category = Tour.objects.filter(
        reservations__created_at__date__gte=date_from,
        reservations__created_at__date__lte=date_to,
        reservations__status__in=['confirmed', 'completed']
    ).values('region__name', 'tour_type__name').annotate(
        tour_count=Count('id', distinct=True),
        reservation_count=Count('reservations'),
        total_revenue=Sum('reservations__total_amount'),
        total_people=Sum('reservations__total_people')
    ).order_by('-total_revenue')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'top_tours': top_tours,
        'tour_performance_by_category': tour_performance_by_category,
    }
    
    return render(request, 'tenant/tours/reports/tour_performance.html', context)


@login_required
def report_salesperson(request):
    """Satış Elemanı Performans Raporları"""
    date_from, date_to = get_date_range(request)
    
    salesperson_stats = get_salesperson_performance(date_from, date_to)
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'salesperson_stats': salesperson_stats,
    }
    
    return render(request, 'tenant/tours/reports/salesperson.html', context)


@login_required
def report_cancellations(request):
    """İptal/İade Raporları"""
    date_from, date_to = get_date_range(request)
    
    cancellation_stats = get_cancellation_stats(date_from, date_to)
    
    # İptal nedenleri (notlar bazlı analiz)
    cancelled_reservations = TourReservation.objects.filter(
        status__in=['cancelled', 'refunded'],
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    ).select_related('tour', 'sales_person')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'cancellation_stats': cancellation_stats,
        'cancelled_reservations': cancelled_reservations[:50],  # Son 50 iptal
    }
    
    return render(request, 'tenant/tours/reports/cancellations.html', context)


@login_required
def report_payments(request):
    """Ödeme Raporları"""
    date_from, date_to = get_date_range(request)
    
    payments = TourPayment.objects.filter(
        payment_date__gte=date_from,
        payment_date__lte=date_to
    ).select_related('reservation', 'reservation__tour')
    
    # Ödeme durumu bazlı
    payment_status_stats = payments.values('status').annotate(
        count=Count('id'),
        total=Sum('amount')
    ).order_by('-total')
    
    # Ödeme yöntemleri bazlı
    payment_method_stats = payments.values('payment_method').annotate(
        count=Count('id'),
        total=Sum('amount')
    ).order_by('-total')
    
    # Bekleyen ödemeler
    pending_payments = TourReservation.objects.filter(
        payment_status__in=['pending', 'partial'],
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    ).select_related('tour', 'tour_date')
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'payment_status_stats': payment_status_stats,
        'payment_method_stats': payment_method_stats,
        'pending_payments': pending_payments[:50],
        'total_paid': payments.filter(status='completed').aggregate(Sum('amount'))['amount__sum'] or Decimal('0'),
    }
    
    return render(request, 'tenant/tours/reports/payments.html', context)


@login_required
def report_capacity(request):
    """Kontenjan Doluluk Raporları"""
    date_from, date_to = get_date_range(request)
    
    capacity_stats = get_capacity_utilization(date_from, date_to)
    
    # Genel doluluk istatistikleri
    total_capacity = sum([s['max_adults'] + s['max_children'] for s in capacity_stats])
    total_reserved = sum([s['reserved_adults'] + s['reserved_children'] for s in capacity_stats])
    overall_utilization = (total_reserved / total_capacity * 100) if total_capacity > 0 else 0
    
    context = {
        'date_from': date_from,
        'date_to': date_to,
        'capacity_stats': capacity_stats,
        'total_capacity': total_capacity,
        'total_reserved': total_reserved,
        'overall_utilization': round(overall_utilization, 2),
    }
    
    return render(request, 'tenant/tours/reports/capacity.html', context)


@login_required
def report_export(request):
    """Rapor Export (Excel/PDF)"""
    from django.http import HttpResponse
    import csv
    from io import StringIO
    
    report_type = request.GET.get('type', 'reservations')
    date_from, date_to = get_date_range(request)
    export_format = request.GET.get('format', 'csv')  # csv, excel, pdf
    
    if report_type == 'reservations':
        reservations = TourReservation.objects.filter(
            created_at__date__gte=date_from,
            created_at__date__lte=date_to
        ).select_related('tour', 'tour_date', 'sales_person', 'customer', 'agency')
        
        if export_format == 'csv':
            response = HttpResponse(content_type='text/csv; charset=utf-8')
            response['Content-Disposition'] = f'attachment; filename="rezervasyonlar_{date_from}_{date_to}.csv"'
            
            writer = csv.writer(response)
            writer.writerow([
                'Rezervasyon Kodu', 'Tur', 'Tarih', 'Müşteri', 'E-posta', 'Telefon',
                'Yetişkin', 'Çocuk', 'Toplam Kişi', 'Tutar', 'Durum', 'Ödeme Durumu',
                'Acente', 'Komisyon', 'Promosyon Kodu', 'Oluşturulma Tarihi'
            ])
            
            for r in reservations:
                commission_amount = r.commission.commission_amount if hasattr(r, 'commission') else 0
                writer.writerow([
                    r.reservation_code,
                    r.tour.name,
                    r.tour_date.date.strftime('%d.%m.%Y') if r.tour_date else '',
                    f"{r.customer_name} {r.customer_surname}",
                    r.customer_email,
                    r.customer_phone,
                    r.adult_count,
                    r.child_count,
                    r.total_people,
                    r.total_amount,
                    r.get_status_display(),
                    r.get_payment_status_display(),
                    r.agency.name if r.agency else '',
                    commission_amount,
                    r.promo_code or '',
                    r.created_at.strftime('%d.%m.%Y %H:%M')
                ])
            
            return response
    
    elif report_type == 'customers':
        customers = Customer.objects.filter(
            created_at__date__gte=date_from,
            created_at__date__lte=date_to
        )
        
        if export_format == 'csv':
            response = HttpResponse(content_type='text/csv; charset=utf-8')
            response['Content-Disposition'] = f'attachment; filename="musteriler_{date_from}_{date_to}.csv"'
            
            writer = csv.writer(response)
            writer.writerow([
                'Müşteri Kodu', 'Ad Soyad', 'E-posta', 'Telefon', 'VIP Seviyesi',
                'Toplam Rezervasyon', 'Toplam Harcama', 'Sadakat Puanı', 'Son Rezervasyon'
            ])
            
            for c in customers:
                writer.writerow([
                    c.customer_code,
                    f"{c.first_name} {c.last_name}",
                    c.email,
                    c.phone,
                    c.get_vip_level_display(),
                    c.total_reservations,
                    c.total_spent,
                    c.loyalty_points,
                    c.last_reservation_date.strftime('%d.%m.%Y') if c.last_reservation_date else ''
                ])
            
            return response
    
    elif report_type == 'commissions':
        from .models import TourReservationCommission
        commissions = TourReservationCommission.objects.filter(
            created_at__date__gte=date_from,
            created_at__date__lte=date_to
        ).select_related('reservation', 'agency')
        
        if export_format == 'csv':
            response = HttpResponse(content_type='text/csv; charset=utf-8')
            response['Content-Disposition'] = f'attachment; filename="komisyonlar_{date_from}_{date_to}.csv"'
            
            writer = csv.writer(response)
            writer.writerow([
                'Rezervasyon Kodu', 'Acente', 'Temel Tutar', 'Komisyon Oranı', 'Komisyon Tutarı',
                'Ödeme Durumu', 'Ödeme Tarihi', 'Oluşturulma Tarihi'
            ])
            
            for c in commissions:
                writer.writerow([
                    c.reservation.reservation_code,
                    c.agency.name,
                    c.base_amount,
                    c.commission_rate,
                    c.commission_amount,
                    c.get_payment_status_display(),
                    c.payment_date.strftime('%d.%m.%Y') if c.payment_date else '',
                    c.created_at.strftime('%d.%m.%Y %H:%M')
                ])
            
            return response
    
    return HttpResponse('Export formatı desteklenmiyor', status=400)


@login_required
def report_customer_analysis(request):
    """Müşteri Analizi Raporu (Merkezi Customer modeli)"""
    from django.db.models import Count, Sum, Avg
    
    date_from, date_to = get_date_range(request)
    
    # Müşteri istatistikleri
    customers = Customer.objects.filter(
        created_at__date__gte=date_from,
        created_at__date__lte=date_to
    )
    
    # VIP seviyesi dağılımı
    vip_distribution = customers.values('vip_level').annotate(
        count=Count('id'),
        total_spent=Sum('total_spent')
    ).order_by('vip_level')
    
    # En çok harcama yapan müşteriler
    top_customers = customers.order_by('-total_spent')[:10]
    
    # Sadakat puanı istatistikleri
    loyalty_stats = customers.aggregate(
        avg_points=Avg('loyalty_points'),
        max_points=models.Max('loyalty_points'),
        total_points=Sum('loyalty_points')
    )
    
    context = {
        'customers': customers,
        'vip_distribution': vip_distribution,
        'top_customers': top_customers,
        'loyalty_stats': loyalty_stats,
        'date_from': date_from,
        'date_to': date_to,
    }
    
    return render(request, 'tenant/tours/reports/customer_analysis.html', context)


@login_required
def report_agency_performance(request):
    """Acente Performans Raporu"""
    from .models import TourAgency, TourReservationCommission
    from django.db.models import Count, Sum, Avg
    
    date_from, date_to = get_date_range(request)
    
    agencies = TourAgency.objects.filter(is_active=True)
    
    agency_performance = []
    for agency in agencies:
        commissions = TourReservationCommission.objects.filter(
            agency=agency,
            created_at__date__gte=date_from,
            created_at__date__lte=date_to
        )
        
        stats = commissions.aggregate(
            total_commissions=Count('id'),
            total_amount=Sum('commission_amount'),
            avg_commission=Avg('commission_amount'),
            paid_amount=Sum('commission_amount', filter=models.Q(payment_status='paid'))
        )
        
        agency_performance.append({
            'agency': agency,
            'total_commissions': stats['total_commissions'] or 0,
            'total_amount': stats['total_amount'] or 0,
            'avg_commission': stats['avg_commission'] or 0,
            'paid_amount': stats['paid_amount'] or 0,
            'pending_amount': (stats['total_amount'] or 0) - (stats['paid_amount'] or 0),
        })
    
    context = {
        'agency_performance': agency_performance,
        'date_from': date_from,
        'date_to': date_to,
    }
    
    return render(request, 'tenant/tours/reports/agency_performance.html', context)


@login_required
def report_campaign_performance(request):
    """Kampanya Performans Raporu"""
    from .models import TourCampaign
    from django.db.models import Count, Sum
    
    date_from, date_to = get_date_range(request)
    
    campaigns = TourCampaign.objects.filter(
        start_date__lte=date_to,
        end_date__gte=date_from
    )
    
    campaign_performance = []
    for campaign in campaigns:
        reservations = campaign.reservations.filter(
            created_at__date__gte=date_from,
            created_at__date__lte=date_to,
            status__in=['confirmed', 'completed']
        )
        
        stats = reservations.aggregate(
            total_reservations=Count('id'),
            total_revenue=Sum('total_amount'),
            total_discount=Sum('discount_amount')
        )
        
        usage_percentage = 0
        if campaign.usage_limit:
            usage_percentage = (campaign.usage_count / campaign.usage_limit * 100) if campaign.usage_limit > 0 else 0
        
        campaign_performance.append({
            'campaign': campaign,
            'total_reservations': stats['total_reservations'] or 0,
            'total_revenue': stats['total_revenue'] or 0,
            'total_discount': stats['total_discount'] or 0,
            'usage_count': campaign.usage_count,
            'usage_limit': campaign.usage_limit,
            'usage_percentage': round(usage_percentage, 2),
        })
    
    context = {
        'campaign_performance': campaign_performance,
        'date_from': date_from,
        'date_to': date_to,
    }
    
    return render(request, 'tenant/tours/reports/campaign_performance.html', context)


# ==================== BEKLEME LİSTESİ ====================

@login_required
@require_tour_module
def tour_waiting_list(request):
    """Bekleme listesi"""
    waiting_lists = TourWaitingList.objects.all().select_related('tour', 'tour_date')
    
    # Filtreleme
    status = request.GET.get('status')
    if status:
        waiting_lists = waiting_lists.filter(status=status)
    
    tour_id = request.GET.get('tour')
    if tour_id:
        waiting_lists = waiting_lists.filter(tour_id=tour_id)
    
    # Arama
    search = request.GET.get('search')
    if search:
        waiting_lists = waiting_lists.filter(
            Q(customer_name__icontains=search) |
            Q(customer_surname__icontains=search) |
            Q(customer_email__icontains=search) |
            Q(customer_phone__icontains=search) |
            Q(tour__name__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-created_at')
    waiting_lists = waiting_lists.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(waiting_lists, 20)
    page = request.GET.get('page')
    waiting_lists = paginator.get_page(page)
    
    # İstatistikler
    stats = {
        'total': TourWaitingList.objects.count(),
        'waiting': TourWaitingList.objects.filter(status='waiting').count(),
        'notified': TourWaitingList.objects.filter(status='notified').count(),
        'converted': TourWaitingList.objects.filter(status='converted').count(),
        'cancelled': TourWaitingList.objects.filter(status='cancelled').count(),
    }
    
    context = {
        'waiting_lists': waiting_lists,
        'stats': stats,
        'status_choices': TourWaitingList.STATUS_CHOICES,
        'tours': Tour.objects.filter(is_active=True).order_by('name'),
    }
    
    return render(request, 'tenant/tours/waiting_list/list.html', context)


@login_required
def tour_waiting_list_detail(request, pk):
    """Bekleme listesi detay"""
    waiting_list = get_object_or_404(TourWaitingList, pk=pk)
    
    context = {
        'waiting_list': waiting_list,
    }
    
    return render(request, 'tenant/tours/waiting_list/detail.html', context)


@login_required
@require_http_methods(["POST"])
def tour_waiting_list_notify(request, pk):
    """Bekleme listesindeki müşteriye bildirim gönder"""
    waiting_list = get_object_or_404(TourWaitingList, pk=pk)
    
    method = request.POST.get('method', 'email')
    waiting_list.notify_availability(method=method)
    
    messages.success(request, f'Bildirim {method} ile gönderildi.')
    return redirect('tours:waiting_list_detail', pk=waiting_list.pk)


@login_required
@require_http_methods(["POST"])
def tour_waiting_list_convert(request, pk):
    """Bekleme listesindeki kaydı rezervasyona dönüştür"""
    waiting_list = get_object_or_404(TourWaitingList, pk=pk)
    
    if waiting_list.status != 'waiting' and waiting_list.status != 'notified':
        messages.error(request, 'Sadece beklemede veya bildirilmiş kayıtlar rezervasyona dönüştürülebilir.')
        return redirect('tours:waiting_list_detail', pk=waiting_list.pk)
    
    # Kontenjan kontrolü
    capacity = waiting_list.tour.get_available_capacity(waiting_list.tour_date.date)
    if waiting_list.adult_count > capacity['adults'] or waiting_list.child_count > capacity['children']:
        messages.error(request, 'Kontenjan yetersiz. Rezervasyon oluşturulamadı.')
        return redirect('tours:waiting_list_detail', pk=waiting_list.pk)
    
    # Rezervasyona dönüştür
    reservation = waiting_list.convert_to_reservation()
    
    messages.success(request, f'Bekleme listesi kaydı rezervasyona dönüştürüldü: {reservation.reservation_code}')
    return redirect('tours:reservation_detail', pk=reservation.pk)


@login_required
@require_http_methods(["POST"])
def tour_waiting_list_cancel(request, pk):
    """Bekleme listesi kaydını iptal et"""
    waiting_list = get_object_or_404(TourWaitingList, pk=pk)
    
    waiting_list.status = 'cancelled'
    waiting_list.save()
    
    messages.success(request, 'Bekleme listesi kaydı iptal edildi.')
    return redirect('tours:waiting_list')


# ==================== CRM - MÜŞTERİ YÖNETİMİ ====================

@login_required
def customer_list(request):
    """Müşteri listesi (Merkezi Customer modeli)"""
    customers = Customer.objects.all()
    
    # Filtreleme
    vip_level = request.GET.get('vip_level')
    if vip_level:
        customers = customers.filter(vip_level=vip_level)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        customers = customers.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        customers = customers.filter(
            Q(customer_code__icontains=search) |
            Q(first_name__icontains=search) |
            Q(last_name__icontains=search) |
            Q(email__icontains=search) |
            Q(phone__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-created_at')
    customers = customers.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(customers, 20)
    page = request.GET.get('page')
    customers = paginator.get_page(page)
    
    context = {
        'customers': customers,
        'vip_level_choices': Customer.VIP_LEVEL_CHOICES,
    }
    
    return render(request, 'tenant/tours/customers/list.html', context)


@login_required
def customer_detail(request, pk):
    """Müşteri detay (Merkezi Customer modeli)"""
    customer = get_object_or_404(Customer, pk=pk)
    reservations = TourReservation.objects.filter(customer=customer).order_by('-created_at')[:10]
    loyalty_history = CustomerLoyaltyHistory.objects.filter(customer=customer, module='tours').order_by('-created_at')[:10]
    notes = CustomerNote.objects.filter(customer=customer).order_by('-created_at')
    
    context = {
        'customer': customer,
        'reservations': reservations,
        'loyalty_history': loyalty_history,
        'notes': notes,
    }
    
    return render(request, 'tenant/tours/customers/detail.html', context)


# ==================== ACENTE YÖNETİMİ ====================

@login_required
def agency_list(request):
    """Acente listesi"""
    agencies = TourAgency.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        agencies = agencies.filter(is_active=is_active == '1')
    
    commission_type = request.GET.get('commission_type')
    if commission_type:
        agencies = agencies.filter(commission_type=commission_type)
    
    # Arama
    search = request.GET.get('search')
    if search:
        agencies = agencies.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(contact_person__icontains=search) |
            Q(email__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'name')
    agencies = agencies.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(agencies, 20)
    page = request.GET.get('page')
    agencies = paginator.get_page(page)
    
    context = {
        'agencies': agencies,
    }
    
    return render(request, 'tenant/tours/agencies/list.html', context)


@login_required
def agency_detail(request, pk):
    """Acente detay"""
    agency = get_object_or_404(TourAgency, pk=pk)
    commissions = TourReservationCommission.objects.filter(agency=agency).order_by('-created_at')[:20]
    
    # İstatistikler
    stats = commissions.aggregate(
        total_commissions=Count('id'),
        total_amount=Sum('commission_amount'),
        paid_amount=Sum('commission_amount', filter=Q(payment_status='paid')),
        pending_amount=Sum('commission_amount', filter=Q(payment_status='pending'))
    )
    
    context = {
        'agency': agency,
        'commissions': commissions,
        'stats': stats,
    }
    
    return render(request, 'tenant/tours/agencies/detail.html', context)


# ==================== KAMPANYA YÖNETİMİ ====================

@login_required
def campaign_list(request):
    """Kampanya listesi"""
    campaigns = TourCampaign.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        campaigns = campaigns.filter(is_active=is_active == '1')
    
    is_featured = request.GET.get('is_featured')
    if is_featured is not None:
        campaigns = campaigns.filter(is_featured=is_featured == '1')
    
    campaign_type = request.GET.get('campaign_type')
    if campaign_type:
        campaigns = campaigns.filter(campaign_type=campaign_type)
    
    # Arama
    search = request.GET.get('search')
    if search:
        campaigns = campaigns.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-is_featured')
    campaigns = campaigns.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(campaigns, 20)
    page = request.GET.get('page')
    campaigns = paginator.get_page(page)
    
    context = {
        'campaigns': campaigns,
        'campaign_type_choices': TourCampaign.CAMPAIGN_TYPE_CHOICES,
    }
    
    return render(request, 'tenant/tours/campaigns/list.html', context)


@login_required
def campaign_detail(request, pk):
    """Kampanya detay"""
    campaign = get_object_or_404(TourCampaign, pk=pk)
    reservations = TourReservation.objects.filter(campaign=campaign, status__in=['confirmed', 'completed']).order_by('-created_at')[:20]
    promo_codes = campaign.promo_codes.all()
    
    context = {
        'campaign': campaign,
        'reservations': reservations,
        'promo_codes': promo_codes,
    }
    
    return render(request, 'tenant/tours/campaigns/detail.html', context)


# ==================== OPERASYONEL YÖNETİM ====================

@login_required
def operation_list(request):
    """Operasyonel yönetim ana sayfa"""
    guides = TourGuide.objects.filter(is_active=True).count()
    vehicles = TourVehicle.objects.filter(is_active=True).count()
    hotels = TourHotel.objects.filter(is_active=True).count()
    transfers = TourTransfer.objects.filter(is_active=True).count()
    
    context = {
        'guides_count': guides,
        'vehicles_count': vehicles,
        'hotels_count': hotels,
        'transfers_count': transfers,
    }
    
    return render(request, 'tenant/tours/operations/list.html', context)


@login_required
def guide_list(request):
    """Rehber listesi"""
    guides = TourGuide.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        guides = guides.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        guides = guides.filter(
            Q(name__icontains=search) |
            Q(surname__icontains=search) |
            Q(phone__icontains=search) |
            Q(license_number__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'name')
    guides = guides.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(guides, 20)
    page = request.GET.get('page')
    guides = paginator.get_page(page)
    
    context = {
        'guides': guides,
    }
    
    return render(request, 'tenant/tours/operations/guides/list.html', context)


@login_required
def vehicle_list(request):
    """Araç listesi"""
    vehicles = TourVehicle.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        vehicles = vehicles.filter(is_active=is_active == '1')
    
    vehicle_type = request.GET.get('vehicle_type')
    if vehicle_type:
        vehicles = vehicles.filter(vehicle_type=vehicle_type)
    
    # Arama
    search = request.GET.get('search')
    if search:
        vehicles = vehicles.filter(
            Q(plate_number__icontains=search) |
            Q(brand__icontains=search) |
            Q(model__icontains=search) |
            Q(driver_name__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'plate_number')
    vehicles = vehicles.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(vehicles, 20)
    page = request.GET.get('page')
    vehicles = paginator.get_page(page)
    
    context = {
        'vehicles': vehicles,
    }
    
    return render(request, 'tenant/tours/operations/vehicles/list.html', context)


@login_required
def hotel_list(request):
    """Otel listesi"""
    hotels = TourHotel.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        hotels = hotels.filter(is_active=is_active == '1')
    
    city_id = request.GET.get('city')
    if city_id:
        hotels = hotels.filter(city_id=city_id)
    
    # Arama
    search = request.GET.get('search')
    if search:
        hotels = hotels.filter(
            Q(name__icontains=search) |
            Q(address__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'name')
    hotels = hotels.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(hotels, 20)
    page = request.GET.get('page')
    hotels = paginator.get_page(page)
    
    cities = TourCity.objects.filter(is_active=True)
    
    context = {
        'hotels': hotels,
        'cities': cities,
    }
    
    return render(request, 'tenant/tours/operations/hotels/list.html', context)


@login_required
def transfer_list(request):
    """Transfer listesi"""
    transfers = TourTransfer.objects.all()
    
    # Filtreleme
    is_active = request.GET.get('is_active')
    if is_active is not None:
        transfers = transfers.filter(is_active=is_active == '1')
    
    transfer_type = request.GET.get('transfer_type')
    if transfer_type:
        transfers = transfers.filter(transfer_type=transfer_type)
    
    # Arama
    search = request.GET.get('search')
    if search:
        transfers = transfers.filter(
            Q(name__icontains=search) |
            Q(from_location__icontains=search) |
            Q(to_location__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'name')
    transfers = transfers.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(transfers, 20)
    page = request.GET.get('page')
    transfers = paginator.get_page(page)
    
    context = {
        'transfers': transfers,
    }
    
    return render(request, 'tenant/tours/operations/transfers/list.html', context)


# ==================== BİLDİRİM ŞABLONLARI ====================

@login_required
def notification_template_list(request):
    """Bildirim şablon listesi"""
    templates = TourNotificationTemplate.objects.all()
    
    # Filtreleme
    notification_type = request.GET.get('notification_type')
    if notification_type:
        templates = templates.filter(notification_type=notification_type)
    
    trigger_event = request.GET.get('trigger_event')
    if trigger_event:
        templates = templates.filter(trigger_event=trigger_event)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        templates = templates.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        templates = templates.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', 'notification_type')
    templates = templates.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(templates, 20)
    page = request.GET.get('page')
    templates = paginator.get_page(page)
    
    context = {
        'templates': templates,
    }
    
    return render(request, 'tenant/tours/notifications/templates/list.html', context)


# ==================== CRM CRUD ====================

@login_required
@require_tour_module
def customer_create(request):
    """Müşteri ekle (Merkezi Customer modeli)"""
    from apps.tenant_apps.core.forms import CustomerForm
    
    if request.method == 'POST':
        form = CustomerForm(request.POST)
        if form.is_valid():
            customer = form.save()
            messages.success(request, f'Müşteri "{customer.first_name} {customer.last_name}" başarıyla eklendi.')
            return redirect('tours:customer_list')
    else:
        form = CustomerForm()
    
    context = {
        'form': form,
        'title': 'Yeni Müşteri Ekle',
    }
    return render(request, 'tenant/tours/customers/form.html', context)


@login_required
@require_tour_module
def customer_update(request, pk):
    """Müşteri güncelle (Merkezi Customer modeli)"""
    from apps.tenant_apps.core.forms import CustomerForm
    
    customer = get_object_or_404(Customer, pk=pk)
    
    if request.method == 'POST':
        form = CustomerForm(request.POST, instance=customer)
        if form.is_valid():
            customer = form.save()
            messages.success(request, f'Müşteri "{customer.first_name} {customer.last_name}" başarıyla güncellendi.')
            return redirect('tours:customer_list')
    else:
        form = CustomerForm(instance=customer)
    
    context = {
        'form': form,
        'customer': customer,
        'title': 'Müşteri Düzenle',
    }
    return render(request, 'tenant/tours/customers/form.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def customer_delete(request, pk):
    """Müşteri sil (Merkezi Customer modeli)"""
    customer = get_object_or_404(Customer, pk=pk)
    customer_name = f"{customer.first_name} {customer.last_name}"
    customer.delete()  # Soft delete
    messages.success(request, f'Müşteri "{customer_name}" başarıyla silindi.')
    return redirect('tours:customer_list')


# ==================== ACENTE CRUD ====================

@login_required
@require_tour_module
def agency_create(request):
    """Acente ekle"""
    if request.method == 'POST':
        form = TourAgencyForm(request.POST)
        if form.is_valid():
            agency = form.save()
            messages.success(request, f'Acente "{agency.name}" başarıyla eklendi.')
            return redirect('tours:agency_list')
    else:
        form = TourAgencyForm()
    
    context = {
        'form': form,
        'title': 'Yeni Acente Ekle',
    }
    return render(request, 'tenant/tours/agencies/form.html', context)


@login_required
@require_tour_module
def agency_update(request, pk):
    """Acente güncelle"""
    agency = get_object_or_404(TourAgency, pk=pk)
    
    if request.method == 'POST':
        form = TourAgencyForm(request.POST, instance=agency)
        if form.is_valid():
            agency = form.save()
            messages.success(request, f'Acente "{agency.name}" başarıyla güncellendi.')
            return redirect('tours:agency_list')
    else:
        form = TourAgencyForm(instance=agency)
    
    context = {
        'form': form,
        'agency': agency,
        'title': 'Acente Düzenle',
    }
    return render(request, 'tenant/tours/agencies/form.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def agency_delete(request, pk):
    """Acente sil"""
    agency = get_object_or_404(TourAgency, pk=pk)
    agency_name = agency.name
    agency.delete()
    messages.success(request, f'Acente "{agency_name}" başarıyla silindi.')
    return redirect('tours:agency_list')


# ==================== KAMPANYA CRUD ====================

@login_required
@require_tour_module
def campaign_create(request):
    """Kampanya ekle"""
    if request.method == 'POST':
        form = TourCampaignForm(request.POST, request.FILES)
        if form.is_valid():
            campaign = form.save()
            messages.success(request, f'Kampanya "{campaign.name}" başarıyla eklendi.')
            return redirect('tours:campaign_list')
    else:
        form = TourCampaignForm()
    
    tours = Tour.objects.filter(is_active=True)
    tour_types = TourType.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'tours': tours,
        'tour_types': tour_types,
        'title': 'Yeni Kampanya Ekle',
    }
    return render(request, 'tenant/tours/campaigns/form.html', context)


@login_required
@require_tour_module
def campaign_update(request, pk):
    """Kampanya güncelle"""
    campaign = get_object_or_404(TourCampaign, pk=pk)
    
    if request.method == 'POST':
        form = TourCampaignForm(request.POST, request.FILES, instance=campaign)
        if form.is_valid():
            campaign = form.save()
            messages.success(request, f'Kampanya "{campaign.name}" başarıyla güncellendi.')
            return redirect('tours:campaign_list')
    else:
        form = TourCampaignForm(instance=campaign)
    
    tours = Tour.objects.filter(is_active=True)
    tour_types = TourType.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'campaign': campaign,
        'tours': tours,
        'tour_types': tour_types,
        'title': 'Kampanya Düzenle',
    }
    return render(request, 'tenant/tours/campaigns/form.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def campaign_delete(request, pk):
    """Kampanya sil"""
    campaign = get_object_or_404(TourCampaign, pk=pk)
    campaign_name = campaign.name
    campaign.delete()
    messages.success(request, f'Kampanya "{campaign_name}" başarıyla silindi.')
    return redirect('tours:campaign_list')


@login_required
@require_tour_module
def promo_code_create(request, campaign_pk=None):
    """Promosyon kodu ekle"""
    campaign = None
    if campaign_pk:
        campaign = get_object_or_404(TourCampaign, pk=campaign_pk)
    
    if request.method == 'POST':
        form = TourPromoCodeForm(request.POST)
        if form.is_valid():
            promo_code = form.save()
            messages.success(request, f'Promosyon kodu "{promo_code.code}" başarıyla eklendi.')
            if campaign:
                return redirect('tours:campaign_detail', pk=campaign.pk)
            return redirect('tours:campaign_list')
    else:
        form = TourPromoCodeForm(initial={'campaign': campaign} if campaign else {})
    
    campaigns = TourCampaign.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'campaign': campaign,
        'campaigns': campaigns,
        'title': 'Yeni Promosyon Kodu Ekle',
    }
    return render(request, 'tenant/tours/campaigns/promo_code_form.html', context)


@login_required
@require_tour_module
def promo_code_update(request, pk):
    """Promosyon kodu güncelle"""
    promo_code = get_object_or_404(TourPromoCode, pk=pk)
    
    if request.method == 'POST':
        form = TourPromoCodeForm(request.POST, instance=promo_code)
        if form.is_valid():
            promo_code = form.save()
            messages.success(request, f'Promosyon kodu "{promo_code.code}" başarıyla güncellendi.')
            return redirect('tours:campaign_detail', pk=promo_code.campaign.pk)
    else:
        form = TourPromoCodeForm(instance=promo_code)
    
    campaigns = TourCampaign.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'promo_code': promo_code,
        'campaigns': campaigns,
        'title': 'Promosyon Kodu Düzenle',
    }
    return render(request, 'tenant/tours/campaigns/promo_code_form.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def promo_code_delete(request, pk):
    """Promosyon kodu sil"""
    promo_code = get_object_or_404(TourPromoCode, pk=pk)
    campaign_pk = promo_code.campaign.pk
    code = promo_code.code
    promo_code.delete()
    messages.success(request, f'Promosyon kodu "{code}" başarıyla silindi.')
    return redirect('tours:campaign_detail', pk=campaign_pk)


# ==================== BİLDİRİM ŞABLON CRUD ====================

@login_required
@require_tour_module
def notification_template_create(request):
    """Bildirim şablon ekle"""
    if request.method == 'POST':
        form = TourNotificationTemplateForm(request.POST)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'Bildirim şablonu "{template.name}" başarıyla eklendi.')
            return redirect('tours:notification_template_list')
    else:
        form = TourNotificationTemplateForm()
    
    context = {
        'form': form,
        'title': 'Yeni Bildirim Şablonu Ekle',
    }
    return render(request, 'tenant/tours/notifications/templates/form.html', context)


@login_required
@require_tour_module
def notification_template_update(request, pk):
    """Bildirim şablon güncelle"""
    template = get_object_or_404(TourNotificationTemplate, pk=pk)
    
    if request.method == 'POST':
        form = TourNotificationTemplateForm(request.POST, instance=template)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'Bildirim şablonu "{template.name}" başarıyla güncellendi.')
            return redirect('tours:notification_template_list')
    else:
        form = TourNotificationTemplateForm(instance=template)
        # variables JSON'u string'e çevir
        if template.variables:
            import json
            form.initial['variables'] = json.dumps(template.variables, ensure_ascii=False, indent=2)
    
    context = {
        'form': form,
        'template': template,
        'title': 'Bildirim Şablonu Düzenle',
    }
    return render(request, 'tenant/tours/notifications/templates/form.html', context)


@login_required
@require_tour_module
def notification_template_detail(request, pk):
    """Bildirim şablon detay"""
    template = get_object_or_404(TourNotificationTemplate, pk=pk)
    notifications = TourNotification.objects.filter(template=template).order_by('-created_at')[:20]
    
    # İstatistikler
    stats = notifications.aggregate(
        total_sent=Count('id'),
        successful=Count('id', filter=Q(status='sent')),
        failed=Count('id', filter=Q(status='failed'))
    )
    
    context = {
        'template': template,
        'notifications': notifications,
        'stats': stats,
    }
    return render(request, 'tenant/tours/notifications/templates/detail.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def notification_template_delete(request, pk):
    """Bildirim şablon sil"""
    template = get_object_or_404(TourNotificationTemplate, pk=pk)
    template_name = template.name
    template.delete()
    messages.success(request, f'Bildirim şablonu "{template_name}" başarıyla silindi.')
    return redirect('tours:notification_template_list')


# ==================== OPERASYONEL YÖNETİM CRUD ====================

@login_required
@require_tour_module
def guide_create(request):
    """Rehber ekle"""
    if request.method == 'POST':
        form = TourGuideForm(request.POST)
        if form.is_valid():
            guide = form.save()
            messages.success(request, f'Rehber "{guide.name} {guide.surname}" başarıyla eklendi.')
            return redirect('tours:guide_list')
    else:
        form = TourGuideForm()
    
    context = {
        'form': form,
        'title': 'Yeni Rehber Ekle',
    }
    return render(request, 'tenant/tours/operations/guides/form.html', context)


@login_required
@require_tour_module
def guide_update(request, pk):
    """Rehber güncelle"""
    guide = get_object_or_404(TourGuide, pk=pk)
    
    if request.method == 'POST':
        form = TourGuideForm(request.POST, instance=guide)
        if form.is_valid():
            guide = form.save()
            messages.success(request, f'Rehber "{guide.name} {guide.surname}" başarıyla güncellendi.')
            return redirect('tours:guide_list')
    else:
        form = TourGuideForm(instance=guide)
    
    context = {
        'form': form,
        'guide': guide,
        'title': 'Rehber Düzenle',
    }
    return render(request, 'tenant/tours/operations/guides/form.html', context)


@login_required
@require_tour_module
def guide_detail(request, pk):
    """Rehber detay"""
    guide = get_object_or_404(TourGuide, pk=pk)
    reservations = guide.reservations.all()[:10]
    
    context = {
        'guide': guide,
        'reservations': reservations,
    }
    return render(request, 'tenant/tours/operations/guides/detail.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def guide_delete(request, pk):
    """Rehber sil"""
    guide = get_object_or_404(TourGuide, pk=pk)
    guide_name = f"{guide.name} {guide.surname}"
    guide.delete()
    messages.success(request, f'Rehber "{guide_name}" başarıyla silindi.')
    return redirect('tours:guide_list')


@login_required
@require_tour_module
def vehicle_create(request):
    """Araç ekle"""
    if request.method == 'POST':
        form = TourVehicleForm(request.POST)
        if form.is_valid():
            vehicle = form.save()
            messages.success(request, f'Araç "{vehicle.plate_number}" başarıyla eklendi.')
            return redirect('tours:vehicle_list')
    else:
        form = TourVehicleForm()
    
    context = {
        'form': form,
        'title': 'Yeni Araç Ekle',
    }
    return render(request, 'tenant/tours/operations/vehicles/form.html', context)


@login_required
@require_tour_module
def vehicle_update(request, pk):
    """Araç güncelle"""
    vehicle = get_object_or_404(TourVehicle, pk=pk)
    
    if request.method == 'POST':
        form = TourVehicleForm(request.POST, instance=vehicle)
        if form.is_valid():
            vehicle = form.save()
            messages.success(request, f'Araç "{vehicle.plate_number}" başarıyla güncellendi.')
            return redirect('tours:vehicle_list')
    else:
        form = TourVehicleForm(instance=vehicle)
    
    context = {
        'form': form,
        'vehicle': vehicle,
        'title': 'Araç Düzenle',
    }
    return render(request, 'tenant/tours/operations/vehicles/form.html', context)


@login_required
@require_tour_module
def vehicle_detail(request, pk):
    """Araç detay"""
    vehicle = get_object_or_404(TourVehicle, pk=pk)
    reservations = vehicle.reservations.all()[:10]
    
    context = {
        'vehicle': vehicle,
        'reservations': reservations,
    }
    return render(request, 'tenant/tours/operations/vehicles/detail.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def vehicle_delete(request, pk):
    """Araç sil"""
    vehicle = get_object_or_404(TourVehicle, pk=pk)
    plate = vehicle.plate_number
    vehicle.delete()
    messages.success(request, f'Araç "{plate}" başarıyla silindi.')
    return redirect('tours:vehicle_list')


@login_required
@require_tour_module
def hotel_create(request):
    """Otel ekle"""
    if request.method == 'POST':
        form = TourHotelForm(request.POST)
        if form.is_valid():
            hotel = form.save()
            messages.success(request, f'Otel "{hotel.name}" başarıyla eklendi.')
            return redirect('tours:hotel_list')
    else:
        form = TourHotelForm()
    
    cities = TourCity.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'cities': cities,
        'title': 'Yeni Otel Ekle',
    }
    return render(request, 'tenant/tours/operations/hotels/form.html', context)


@login_required
@require_tour_module
def hotel_update(request, pk):
    """Otel güncelle"""
    hotel = get_object_or_404(TourHotel, pk=pk)
    
    if request.method == 'POST':
        form = TourHotelForm(request.POST, instance=hotel)
        if form.is_valid():
            hotel = form.save()
            messages.success(request, f'Otel "{hotel.name}" başarıyla güncellendi.')
            return redirect('tours:hotel_list')
    else:
        form = TourHotelForm(instance=hotel)
    
    cities = TourCity.objects.filter(is_active=True)
    
    context = {
        'form': form,
        'hotel': hotel,
        'cities': cities,
        'title': 'Otel Düzenle',
    }
    return render(request, 'tenant/tours/operations/hotels/form.html', context)


@login_required
@require_tour_module
def hotel_detail(request, pk):
    """Otel detay"""
    hotel = get_object_or_404(TourHotel, pk=pk)
    reservations = hotel.reservations.all()[:10]
    
    context = {
        'hotel': hotel,
        'reservations': reservations,
    }
    return render(request, 'tenant/tours/operations/hotels/detail.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def hotel_delete(request, pk):
    """Otel sil"""
    hotel = get_object_or_404(TourHotel, pk=pk)
    hotel_name = hotel.name
    hotel.delete()
    messages.success(request, f'Otel "{hotel_name}" başarıyla silindi.')
    return redirect('tours:hotel_list')


@login_required
@require_tour_module
def transfer_create(request):
    """Transfer ekle"""
    if request.method == 'POST':
        form = TourTransferForm(request.POST)
        if form.is_valid():
            transfer = form.save()
            messages.success(request, f'Transfer "{transfer.name}" başarıyla eklendi.')
            return redirect('tours:transfer_list')
    else:
        form = TourTransferForm()
    
    context = {
        'form': form,
        'title': 'Yeni Transfer Ekle',
    }
    return render(request, 'tenant/tours/operations/transfers/form.html', context)


@login_required
@require_tour_module
def transfer_update(request, pk):
    """Transfer güncelle"""
    transfer = get_object_or_404(TourTransfer, pk=pk)
    
    if request.method == 'POST':
        form = TourTransferForm(request.POST, instance=transfer)
        if form.is_valid():
            transfer = form.save()
            messages.success(request, f'Transfer "{transfer.name}" başarıyla güncellendi.')
            return redirect('tours:transfer_list')
    else:
        form = TourTransferForm(instance=transfer)
    
    context = {
        'form': form,
        'transfer': transfer,
        'title': 'Transfer Düzenle',
    }
    return render(request, 'tenant/tours/operations/transfers/form.html', context)


@login_required
@require_tour_module
def transfer_detail(request, pk):
    """Transfer detay"""
    transfer = get_object_or_404(TourTransfer, pk=pk)
    reservations = transfer.reservations.all()[:10]
    
    context = {
        'transfer': transfer,
        'reservations': reservations,
    }
    return render(request, 'tenant/tours/operations/transfers/detail.html', context)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def transfer_delete(request, pk):
    """Transfer sil"""
    transfer = get_object_or_404(TourTransfer, pk=pk)
    transfer_name = transfer.name
    transfer.delete()
    messages.success(request, f'Transfer "{transfer_name}" başarıyla silindi.')
    return redirect('tours:transfer_list')


# ==================== AI ENTEGRASYONU ====================

@login_required
@require_tour_module
def get_available_ai_models(request):
    """Tenant'ın kullanabileceği AI modellerini döndür"""
    from django_tenants.utils import get_tenant
    from django.db import connection
    
    tenant = get_tenant(connection)
    
    # Tenant'ın aktif aboneliğini bul
    active_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active',
        end_date__gte=timezone.now().date()
    ).select_related('package').first()
    
    if not active_subscription:
        return JsonResponse({'error': 'Aktif abonelik bulunamadı'}, status=400)
    
    # Pakete dahil AI'ları al
    package_ais = PackageAI.objects.filter(
        package=active_subscription.package,
        is_enabled=True
    ).select_related('ai_provider', 'ai_model')
    
    models_data = []
    for package_ai in package_ais:
        models_data.append({
            'provider_code': package_ai.ai_provider.code,
            'provider_name': package_ai.ai_provider.name,
            'model_code': package_ai.ai_model.code,
            'model_name': package_ai.ai_model.name,
            'credit_cost': float(package_ai.ai_model.credit_cost),
        })
    
    # Kredi bilgisi
    credit = get_tenant_ai_credit(tenant)
    
    return JsonResponse({
        'models': models_data,
        'credit': {
            'total': credit.total_credits,
            'used': credit.used_credits,
            'remaining': credit.remaining_credits,
        }
    })


@login_required
@require_tour_module
@require_http_methods(["POST"])
def generate_tour_description(request):
    """AI ile tur açıklaması oluştur"""
    from django_tenants.utils import get_tenant
    from django.db import connection
    
    tenant = get_tenant(connection)
    
    # Form verilerini al
    tour_name = request.POST.get('tour_name', '')
    tour_region = request.POST.get('tour_region', '')
    tour_city = request.POST.get('tour_city', '')
    tour_type = request.POST.get('tour_type', '')
    duration_days = request.POST.get('duration_days', '')
    provider_code = request.POST.get('provider_code', '')
    model_code = request.POST.get('model_code', '')
    
    if not all([tour_name, provider_code, model_code]):
        return JsonResponse({'error': 'Eksik parametreler'}, status=400)
    
    # Prompt oluştur
    prompt = f"""Aşağıdaki bilgilere göre profesyonel bir tur açıklaması oluştur:

Tur Adı: {tour_name}
Bölge: {tour_region}
Şehir: {tour_city}
Tur Türü: {tour_type}
Süre: {duration_days} gün

Açıklama Türkçe, profesyonel, çekici ve detaylı olmalı. Turun özelliklerini, gezilecek yerleri, deneyimleri ve neden tercih edilmesi gerektiğini vurgula."""
    
    try:
        # AI içerik üret
        response_text, usage = generate_ai_content(
            provider_code=provider_code,
            model_code=model_code,
            prompt=prompt,
            usage_type='tour_description',
            user=request.user,
            tenant=tenant,
        )
        
        return JsonResponse({
            'success': True,
            'description': response_text,
            'credit_used': float(usage.credit_used),
            'remaining_credits': get_tenant_ai_credit(tenant).remaining_credits,
        })
    except ValueError as e:
        return JsonResponse({'error': str(e)}, status=400)
    except Exception as e:
        return JsonResponse({'error': f'AI hatası: {str(e)}'}, status=500)


@login_required
@require_tour_module
@require_http_methods(["POST"])
def generate_tour_program(request):
    """AI ile tur programı oluştur"""
    from django_tenants.utils import get_tenant
    from django.db import connection
    
    tenant = get_tenant(connection)
    
    # Form verilerini al
    tour_name = request.POST.get('tour_name', '')
    tour_city = request.POST.get('tour_city', '')
    duration_days = request.POST.get('duration_days', '')
    cities_to_visit = request.POST.get('cities_to_visit', '')
    provider_code = request.POST.get('provider_code', '')
    model_code = request.POST.get('model_code', '')
    
    if not all([tour_name, duration_days, provider_code, model_code]):
        return JsonResponse({'error': 'Eksik parametreler'}, status=400)
    
    # Prompt oluştur
    prompt = f"""Aşağıdaki bilgilere göre {duration_days} günlük detaylı bir tur programı oluştur:

Tur Adı: {tour_name}
Şehir: {tour_city}
Gezilecek Şehirler: {cities_to_visit}
Süre: {duration_days} gün

Program gün gün, saat saat detaylı olmalı. Her gün için:
- Sabah programı
- Öğle programı
- Akşam programı
- Yemekler
- Konaklama bilgisi
- Gezilecek yerler ve aktiviteler

Format: 
1. Gün: [Detaylı program]
2. Gün: [Detaylı program]
..."""
    
    try:
        # AI içerik üret
        response_text, usage = generate_ai_content(
            provider_code=provider_code,
            model_code=model_code,
            prompt=prompt,
            usage_type='tour_program',
            user=request.user,
            tenant=tenant,
        )
        
        return JsonResponse({
            'success': True,
            'program': response_text,
            'credit_used': float(usage.credit_used),
            'remaining_credits': get_tenant_ai_credit(tenant).remaining_credits,
        })
    except ValueError as e:
        return JsonResponse({'error': str(e)}, status=400)
    except Exception as e:
        return JsonResponse({'error': f'AI hatası: {str(e)}'}, status=500)


