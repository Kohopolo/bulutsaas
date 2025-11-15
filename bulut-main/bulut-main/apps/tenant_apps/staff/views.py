"""
Personel Yönetimi Modülü Views
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Sum, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from datetime import datetime, timedelta
from decimal import Decimal

from .models import Staff, Shift, LeaveRequest, PerformanceReview, SalaryRecord, StaffSettings
from .forms import StaffForm, ShiftForm, LeaveRequestForm, PerformanceReviewForm, SalaryRecordForm, StaffSettingsForm
from .decorators import require_staff_permission
from apps.tenant_apps.hotels.models import Hotel


@login_required
@require_staff_permission('view')
def dashboard(request):
    """Personel Yönetimi Ana Ekran"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    today = timezone.now().date()
    
    # Personel istatistikleri
    total_staff = Staff.objects.filter(hotel=hotel, is_active=True, is_deleted=False).count()
    active_shifts = Shift.objects.filter(hotel=hotel, shift_date=today, status__in=['scheduled', 'confirmed', 'in_progress'], is_deleted=False).count()
    pending_leaves = LeaveRequest.objects.filter(hotel=hotel, status='pending', is_deleted=False).count()
    
    # Bugünkü vardiyalar
    today_shifts = Shift.objects.filter(hotel=hotel, shift_date=today, is_deleted=False).select_related('staff')
    
    # Son personel eklemeleri
    recent_staff = Staff.objects.filter(hotel=hotel, is_deleted=False).order_by('-created_at')[:10]
    
    context = {
        'hotel': hotel,
        'total_staff': total_staff,
        'active_shifts': active_shifts,
        'pending_leaves': pending_leaves,
        'today_shifts': today_shifts,
        'recent_staff': recent_staff,
    }
    
    return render(request, 'staff/dashboard.html', context)


@login_required
@require_staff_permission('view')
def staff_list(request):
    """Personel Listesi"""
    staff_list = Staff.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                staff_list = staff_list.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin personelini göster
            # Sadece aktif otelin personelini göster
            staff_list = staff_list.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    department_filter = request.GET.get('department', '')
    search_query = request.GET.get('search', '')
    
    if department_filter:
        staff_list = staff_list.filter(department=department_filter)
    if search_query:
        staff_list = staff_list.filter(
            Q(first_name__icontains=search_query) | Q(last_name__icontains=search_query) | Q(employee_id__icontains=search_query)
        )
    
    staff_list = staff_list.select_related('user', 'hotel').order_by('last_name', 'first_name')
    
    paginator = Paginator(staff_list, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'staff_list': page_obj,
        'department_filter': department_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'staff/staff/list.html', context)


@login_required
@require_staff_permission('manage')
def staff_create(request):
    """Yeni Personel Ekle"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = StaffForm(request.POST)
        if form.is_valid():
            staff = form.save(commit=False)
            staff.hotel = hotel
            staff.save()
            messages.success(request, 'Personel eklendi.')
            return redirect('staff:staff_list')
    else:
        form = StaffForm()
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'staff/staff/form.html', context)


@login_required
@require_staff_permission('view')
def shift_list(request):
    """Vardiya Listesi"""
    shifts = Shift.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                shifts = shifts.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin vardiyalarını göster
            shifts = shifts.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    date_from = request.GET.get('date_from', '')
    date_to = request.GET.get('date_to', '')
    status_filter = request.GET.get('status', '')
    
    if date_from:
        shifts = shifts.filter(shift_date__gte=date_from)
    if date_to:
        shifts = shifts.filter(shift_date__lte=date_to)
    if status_filter:
        shifts = shifts.filter(status=status_filter)
    
    shifts = shifts.select_related('staff', 'hotel').order_by('-shift_date', 'start_time')
    
    paginator = Paginator(shifts, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'shifts': page_obj,
        'date_from': date_from,
        'date_to': date_to,
        'status_filter': status_filter,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'staff/shifts/list.html', context)


@login_required
@require_staff_permission('manage')
def shift_create(request):
    """Yeni Vardiya Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = ShiftForm(request.POST, hotel=hotel)
        if form.is_valid():
            shift = form.save(commit=False)
            shift.hotel = hotel
            shift.save()
            messages.success(request, 'Vardiya oluşturuldu.')
            return redirect('staff:shift_list')
    else:
        form = ShiftForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'staff/shifts/form.html', context)


@login_required
@require_staff_permission('view')
def leave_list(request):
    """İzin Talepleri Listesi"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    status_filter = request.GET.get('status', '')
    leave_type_filter = request.GET.get('leave_type', '')
    
    leaves = LeaveRequest.objects.filter(hotel=hotel, is_deleted=False)
    
    if status_filter:
        leaves = leaves.filter(status=status_filter)
    if leave_type_filter:
        leaves = leaves.filter(leave_type=leave_type_filter)
    
    leaves = leaves.select_related('staff', 'reviewed_by').order_by('-requested_at')
    
    paginator = Paginator(leaves, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {
        'hotel': hotel,
        'leaves': page_obj,
        'status_filter': status_filter,
        'leave_type_filter': leave_type_filter,
    }
    
    return render(request, 'staff/leaves/list.html', context)


@login_required
@require_staff_permission('manage')
def leave_create(request):
    """Yeni İzin Talebi Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = LeaveRequestForm(request.POST, hotel=hotel)
        if form.is_valid():
            leave = form.save(commit=False)
            leave.hotel = hotel
            leave.save()
            messages.success(request, 'İzin talebi oluşturuldu.')
            return redirect('staff:leave_list')
    else:
        form = LeaveRequestForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'staff/leaves/form.html', context)


@login_required
@require_staff_permission('view')
def salary_list(request):
    """Maaş Kayıtları Listesi"""
    salaries = SalaryRecord.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                salaries = salaries.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin maaşlarını göster
            salaries = salaries.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    month_filter = request.GET.get('month', '')
    paid_filter = request.GET.get('paid', '')
    
    if month_filter:
        salaries = salaries.filter(salary_month__year=month_filter[:4], salary_month__month=month_filter[5:])
    if paid_filter:
        salaries = salaries.filter(paid=(paid_filter == '1'))
    
    salaries = salaries.select_related('staff', 'hotel').order_by('-salary_month')
    
    paginator = Paginator(salaries, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'salaries': page_obj,
        'month_filter': month_filter,
        'paid_filter': paid_filter,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'staff/salaries/list.html', context)


@login_required
@require_staff_permission('admin')
def settings(request):
    """Personel Yönetimi Ayarları"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    settings_obj, created = StaffSettings.objects.get_or_create(hotel=hotel)
    
    if request.method == 'POST':
        form = StaffSettingsForm(request.POST, instance=settings_obj)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ayarlar kaydedildi.')
            return redirect('staff:settings')
    else:
        form = StaffSettingsForm(instance=settings_obj)
    
    context = {'hotel': hotel, 'settings': settings_obj, 'form': form}
    return render(request, 'staff/settings.html', context)

