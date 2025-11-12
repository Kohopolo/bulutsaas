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
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    status_filter = request.GET.get('status', '')
    type_filter = request.GET.get('type', '')
    search_query = request.GET.get('search', '')
    
    inspections = RoomQualityInspection.objects.filter(hotel=hotel, is_deleted=False)
    
    if status_filter:
        inspections = inspections.filter(status=status_filter)
    if type_filter:
        inspections = inspections.filter(inspection_type=type_filter)
    if search_query:
        inspections = inspections.filter(Q(room_number__number__icontains=search_query) | Q(notes__icontains=search_query))
    
    inspections = inspections.select_related('room_number', 'inspected_by').order_by('-inspected_at')
    
    paginator = Paginator(inspections, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {
        'hotel': hotel,
        'inspections': page_obj,
        'status_filter': status_filter,
        'type_filter': type_filter,
        'search_query': search_query,
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
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    status_filter = request.GET.get('status', '')
    priority_filter = request.GET.get('priority', '')
    search_query = request.GET.get('search', '')
    
    complaints = CustomerComplaint.objects.filter(hotel=hotel, is_deleted=False)
    
    if status_filter:
        complaints = complaints.filter(status=status_filter)
    if priority_filter:
        complaints = complaints.filter(priority=priority_filter)
    if search_query:
        complaints = complaints.filter(Q(description__icontains=search_query))
    
    complaints = complaints.select_related('reservation', 'customer', 'reported_by').order_by('-reported_at')
    
    paginator = Paginator(complaints, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {
        'hotel': hotel,
        'complaints': page_obj,
        'status_filter': status_filter,
        'priority_filter': priority_filter,
        'search_query': search_query,
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

