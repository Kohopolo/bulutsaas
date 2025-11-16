"""
Satış Yönetimi Modülü Views
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Sum, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from datetime import datetime, timedelta
from decimal import Decimal

from .models import Agency, SalesRecord, SalesTarget, SalesReport, SalesSettings
from .forms import AgencyForm, SalesRecordForm, SalesTargetForm, SalesSettingsForm
from .decorators import require_sales_permission
from apps.tenant_apps.hotels.models import Hotel


@login_required
@require_sales_permission('view')
def dashboard(request):
    """Satış Yönetimi Ana Ekran"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    today = timezone.now().date()
    this_month_start = today.replace(day=1)
    
    # Bu ay satışları
    monthly_sales = SalesRecord.objects.filter(
        hotel=hotel, sales_date__gte=this_month_start, sales_date__lte=today, is_deleted=False
    ).aggregate(
        total=Sum('sales_amount'),
        count=Count('id'),
        commission=Sum('commission_amount')
    )
    
    # Bugünkü satışlar
    today_sales = SalesRecord.objects.filter(
        hotel=hotel, sales_date=today, is_deleted=False
    ).aggregate(total=Sum('sales_amount'), count=Count('id'))
    
    # Aktif acenteler
    active_agencies = Agency.objects.filter(hotel=hotel, is_active=True, is_deleted=False).count()
    
    # Aktif hedefler
    active_targets = SalesTarget.objects.filter(
        hotel=hotel, is_active=True,
        start_date__lte=today, end_date__gte=today, is_deleted=False
    ).count()
    
    # Son satışlar
    recent_sales = SalesRecord.objects.filter(
        hotel=hotel, is_deleted=False
    ).select_related('agency', 'sales_person').order_by('-sales_date')[:10]
    
    context = {
        'hotel': hotel,
        'monthly_sales': monthly_sales['total'] or Decimal('0.00'),
        'monthly_count': monthly_sales['count'] or 0,
        'monthly_commission': monthly_sales['commission'] or Decimal('0.00'),
        'today_sales': today_sales['total'] or Decimal('0.00'),
        'today_count': today_sales['count'] or 0,
        'active_agencies': active_agencies,
        'active_targets': active_targets,
        'recent_sales': recent_sales,
    }
    
    return render(request, 'sales/dashboard.html', context)


@login_required
@require_sales_permission('view')
def agency_list(request):
    """Acente Listesi"""
    agencies = Agency.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                agencies = agencies.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin acentelerini göster
            # Sadece aktif otelin acentelerini göster
            agencies = agencies.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    search_query = request.GET.get('search', '')
    if search_query:
        agencies = agencies.filter(Q(name__icontains=search_query) | Q(code__icontains=search_query))
    
    agencies = agencies.select_related('hotel').order_by('name')
    
    paginator = Paginator(agencies, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    from apps.tenant_apps.core.utils import get_filter_hotels
    accessible_hotels = get_filter_hotels(request)
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'agencies': page_obj,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    return render(request, 'sales/agencies/list.html', context)


@login_required
@require_sales_permission('manage')
def agency_create(request):
    """Yeni Acente Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = AgencyForm(request.POST)
        if form.is_valid():
            agency = form.save(commit=False)
            agency.hotel = hotel
            agency.save()
            messages.success(request, 'Acente oluşturuldu.')
            return redirect('sales:agency_list')
    else:
        form = AgencyForm()
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'sales/agencies/form.html', context)


@login_required
@require_sales_permission('view')
def sales_record_list(request):
    """Satış Kayıtları Listesi"""
    records = SalesRecord.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                records = records.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin satış kayıtlarını göster
            # Sadece aktif otelin satış kayıtlarını göster
            records = records.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    type_filter = request.GET.get('type', '')
    date_from = request.GET.get('date_from', '')
    date_to = request.GET.get('date_to', '')
    
    if type_filter:
        records = records.filter(sales_type=type_filter)
    if date_from:
        records = records.filter(sales_date__gte=date_from)
    if date_to:
        records = records.filter(sales_date__lte=date_to)
    
    records = records.select_related('agency', 'sales_person', 'hotel').order_by('-sales_date')
    
    paginator = Paginator(records, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    from apps.tenant_apps.core.utils import get_filter_hotels
    accessible_hotels = get_filter_hotels(request)
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'records': page_obj,
        'type_filter': type_filter,
        'date_from': date_from,
        'date_to': date_to,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'sales/records/list.html', context)


@login_required
@require_sales_permission('manage')
def sales_record_create(request):
    """Yeni Satış Kaydı Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = SalesRecordForm(request.POST, hotel=hotel)
        if form.is_valid():
            record = form.save(commit=False)
            record.hotel = hotel
            record.save()
            messages.success(request, 'Satış kaydı oluşturuldu.')
            return redirect('sales:sales_record_list')
    else:
        form = SalesRecordForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'sales/records/form.html', context)


@login_required
@require_sales_permission('view')
def sales_target_list(request):
    """Satış Hedefleri Listesi"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    targets = SalesTarget.objects.filter(hotel=hotel, is_deleted=False).select_related('assigned_to').order_by('-start_date')
    
    paginator = Paginator(targets, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {'hotel': hotel, 'targets': page_obj}
    return render(request, 'sales/targets/list.html', context)


@login_required
@require_sales_permission('manage')
def sales_target_create(request):
    """Yeni Satış Hedefi Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = SalesTargetForm(request.POST)
        if form.is_valid():
            target = form.save(commit=False)
            target.hotel = hotel
            target.save()
            messages.success(request, 'Satış hedefi oluşturuldu.')
            return redirect('sales:sales_target_list')
    else:
        form = SalesTargetForm()
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'sales/targets/form.html', context)


@login_required
@require_sales_permission('admin')
def settings(request):
    """Satış Yönetimi Ayarları"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    settings_obj, created = SalesSettings.objects.get_or_create(hotel=hotel)
    
    if request.method == 'POST':
        form = SalesSettingsForm(request.POST, instance=settings_obj)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ayarlar kaydedildi.')
            return redirect('sales:settings')
    else:
        form = SalesSettingsForm(instance=settings_obj)
    
    context = {'hotel': hotel, 'settings': settings_obj, 'form': form}
    return render(request, 'sales/settings.html', context)

