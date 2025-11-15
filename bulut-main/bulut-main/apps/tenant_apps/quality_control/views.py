"""
Kalite Kontrol Modülü Views
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from datetime import datetime, timedelta

from .models import RoomQualityInspection, QualityChecklistItem, CustomerComplaint, QualityStandard, QualityAuditReport, QualityControlSettings
from .forms import RoomQualityInspectionForm, QualityChecklistItemForm, CustomerComplaintForm, QualityStandardForm, QualityControlSettingsForm
from .decorators import require_quality_control_permission
from apps.tenant_apps.hotels.models import Hotel


@login_required
@require_quality_control_permission('view')
def dashboard(request):
    """Kalite Kontrol Ana Ekran"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    today = timezone.now().date()
    
    # Bugünkü kontroller
    today_inspections = RoomQualityInspection.objects.filter(hotel=hotel, inspected_at__date=today, is_deleted=False).count()
    passed_inspections = RoomQualityInspection.objects.filter(hotel=hotel, status='passed', inspected_at__date=today, is_deleted=False).count()
    failed_inspections = RoomQualityInspection.objects.filter(hotel=hotel, status='failed', inspected_at__date=today, is_deleted=False).count()
    
    # Ortalama puanlar
    avg_score = RoomQualityInspection.objects.filter(hotel=hotel, is_deleted=False).aggregate(Avg('overall_score'))['overall_score__avg'] or 0
    
    # Şikayetler
    pending_complaints = CustomerComplaint.objects.filter(hotel=hotel, status='pending', is_deleted=False).count()
    urgent_complaints = CustomerComplaint.objects.filter(hotel=hotel, priority='urgent', status__in=['pending', 'investigating'], is_deleted=False).count()
    
    # Son kontroller
    recent_inspections = RoomQualityInspection.objects.filter(hotel=hotel, is_deleted=False).select_related('room_number', 'inspected_by').order_by('-inspected_at')[:10]
    
    context = {
        'hotel': hotel,
        'today_inspections': today_inspections,
        'passed_inspections': passed_inspections,
        'failed_inspections': failed_inspections,
        'avg_score': round(avg_score, 2) if avg_score else 0,
        'pending_complaints': pending_complaints,
        'urgent_complaints': urgent_complaints,
        'recent_inspections': recent_inspections,
    }
    
    return render(request, 'quality_control/dashboard.html', context)


@login_required
@require_quality_control_permission('view')
def inspection_list(request):
    """Kalite Kontrol Listesi"""
    inspections = RoomQualityInspection.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                inspections = inspections.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin kontrollerini göster
            inspections = inspections.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    type_filter = request.GET.get('type', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        inspections = inspections.filter(status=status_filter)
    if type_filter:
        inspections = inspections.filter(inspection_type=type_filter)
    if search_query:
        inspections = inspections.filter(Q(room_number__number__icontains=search_query) | Q(notes__icontains=search_query))
    
    inspections = inspections.select_related('room_number', 'inspected_by', 'hotel').order_by('-inspected_at')
    
    paginator = Paginator(inspections, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'inspections': page_obj,
        'status_filter': status_filter,
        'type_filter': type_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'quality_control/inspections/list.html', context)


@login_required
@require_quality_control_permission('manage')
def inspection_create(request):
    """Yeni Kalite Kontrolü Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = RoomQualityInspectionForm(request.POST, hotel=hotel)
        if form.is_valid():
            inspection = form.save(commit=False)
            inspection.hotel = hotel
            inspection.inspected_by = request.user
            inspection.save()
            
            # Kontrol başarısız olduysa ve aksiyon gerekliyse oda durumunu güncelle
            if inspection.status == 'failed' and inspection.action_required:
                if inspection.room_number:
                    from apps.tenant_apps.hotels.models import RoomNumberStatus
                    # Başarısız kontrol → Oda bakım gerektiriyor olabilir
                    # Ancak direkt MAINTENANCE yapmak yerine, mevcut durumu koruyoruz
                    # Çünkü bakım talebi oluşturulmalı
                    # Şimdilik sadece logluyoruz, bakım talebi oluşturulduğunda oda durumu güncellenecek
                    pass
            
            messages.success(request, 'Kalite kontrolü oluşturuldu.')
            return redirect('quality_control:inspection_detail', pk=inspection.pk)
    else:
        form = RoomQualityInspectionForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'quality_control/inspections/form.html', context)


@login_required
@require_quality_control_permission('view')
def inspection_detail(request, pk):
    """Kalite Kontrolü Detayı"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    inspection = get_object_or_404(
        RoomQualityInspection.objects.select_related('room_number', 'inspected_by'),
        pk=pk, hotel=hotel, is_deleted=False
    )
    checklist_items = inspection.checklist_items.all()
    
    context = {'hotel': hotel, 'inspection': inspection, 'checklist_items': checklist_items}
    return render(request, 'quality_control/inspections/detail.html', context)


@login_required
@require_quality_control_permission('view')
def complaint_list(request):
    """Müşteri Şikayetleri Listesi"""
    complaints = CustomerComplaint.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                complaints = complaints.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin şikayetlerini göster
            complaints = complaints.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    priority_filter = request.GET.get('priority', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        complaints = complaints.filter(status=status_filter)
    if priority_filter:
        complaints = complaints.filter(priority=priority_filter)
    if search_query:
        complaints = complaints.filter(Q(description__icontains=search_query))
    
    complaints = complaints.select_related('customer', 'reported_by', 'hotel').order_by('-reported_at')
    
    paginator = Paginator(complaints, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'complaints': page_obj,
        'status_filter': status_filter,
        'priority_filter': priority_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'quality_control/complaints/list.html', context)


@login_required
@require_quality_control_permission('manage')
def complaint_create(request):
    """Yeni Şikayet Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = CustomerComplaintForm(request.POST, hotel=hotel)
        if form.is_valid():
            complaint = form.save(commit=False)
            complaint.hotel = hotel
            complaint.reported_by = request.user
            complaint.save()
            messages.success(request, 'Şikayet oluşturuldu.')
            return redirect('quality_control:complaint_list')
    else:
        form = CustomerComplaintForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'quality_control/complaints/form.html', context)


@login_required
@require_quality_control_permission('admin')
def settings(request):
    """Kalite Kontrol Ayarları"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    settings_obj, created = QualityControlSettings.objects.get_or_create(hotel=hotel)
    
    if request.method == 'POST':
        form = QualityControlSettingsForm(request.POST, instance=settings_obj)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ayarlar kaydedildi.')
            return redirect('quality_control:settings')
    else:
        form = QualityControlSettingsForm(instance=settings_obj)
    
    context = {'hotel': hotel, 'settings': settings_obj, 'form': form}
    return render(request, 'quality_control/settings.html', context)

