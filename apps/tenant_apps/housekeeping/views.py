"""
Kat Hizmetleri Modülü Views
Profesyonel otel kat hizmetleri yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse
from django.db.models import Q, Count, Sum, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from datetime import datetime, timedelta

from .models import (
    CleaningTask, CleaningChecklistItem, MissingItem,
    LaundryItem, MaintenanceRequest, HousekeepingSettings,
    HousekeepingDailyReport
)
from .forms import (
    CleaningTaskForm, CleaningTaskStatusForm, CleaningTaskInspectionForm,
    CleaningChecklistItemForm, MissingItemForm, MissingItemStatusForm,
    LaundryItemForm, MaintenanceRequestForm, HousekeepingSettingsForm
)
from .decorators import require_housekeeping_permission
from apps.tenant_apps.hotels.models import Hotel, RoomNumber, RoomNumberStatus


# ==================== ANA EKRAN ====================

@login_required
@require_housekeeping_permission('view')
def dashboard(request):
    """Kat Hizmetleri Ana Ekran"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    today = timezone.now().date()
    
    # Bugünkü görevler
    today_tasks = CleaningTask.objects.filter(
        hotel=hotel,
        scheduled_time__date=today,
        is_deleted=False
    )
    
    pending_tasks = today_tasks.filter(status='pending').count()
    in_progress_tasks = today_tasks.filter(status='in_progress').count()
    completed_tasks = today_tasks.filter(status='completed').count()
    overdue_tasks = today_tasks.filter(
        status__in=['pending', 'in_progress'],
        scheduled_time__lt=timezone.now()
    ).count()
    
    # Oda durumları
    dirty_rooms = RoomNumber.objects.filter(
        hotel=hotel,
        status=RoomNumberStatus.DIRTY,
        is_active=True,
        is_deleted=False
    ).count()
    
    cleaning_pending_rooms = RoomNumber.objects.filter(
        hotel=hotel,
        status=RoomNumberStatus.CLEANING_PENDING,
        is_active=True,
        is_deleted=False
    ).count()
    
    clean_rooms = RoomNumber.objects.filter(
        hotel=hotel,
        status=RoomNumberStatus.CLEAN,
        is_active=True,
        is_deleted=False
    ).count()
    
    # Son görevler
    recent_tasks = CleaningTask.objects.filter(
        hotel=hotel,
        is_deleted=False
    ).select_related('room_number', 'assigned_to').order_by('-created_at')[:10]
    
    # Bekleyen bakım talepleri
    pending_maintenance = MaintenanceRequest.objects.filter(
        hotel=hotel,
        status='pending',
        is_deleted=False
    ).count()
    
    context = {
        'hotel': hotel,
        'pending_tasks': pending_tasks,
        'in_progress_tasks': in_progress_tasks,
        'completed_tasks': completed_tasks,
        'overdue_tasks': overdue_tasks,
        'dirty_rooms': dirty_rooms,
        'cleaning_pending_rooms': cleaning_pending_rooms,
        'clean_rooms': clean_rooms,
        'recent_tasks': recent_tasks,
        'pending_maintenance': pending_maintenance,
    }
    
    return render(request, 'housekeeping/dashboard.html', context)


# ==================== TEMİZLİK GÖREVLERİ ====================

@login_required
@require_housekeeping_permission('view')
def task_list(request):
    """Temizlik Görevleri Listesi"""
    tasks = CleaningTask.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                tasks = tasks.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin görevlerini göster
            # Sadece aktif otelin görevlerini göster
            tasks = tasks.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    # Filtreleme
    status_filter = request.GET.get('status', '')
    priority_filter = request.GET.get('priority', '')
    assigned_to_filter = request.GET.get('assigned_to', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        tasks = tasks.filter(status=status_filter)
    
    if priority_filter:
        tasks = tasks.filter(priority=priority_filter)
    
    if assigned_to_filter:
        tasks = tasks.filter(assigned_to_id=assigned_to_filter)
    
    if search_query:
        tasks = tasks.filter(
            Q(room_number__number__icontains=search_query) |
            Q(notes__icontains=search_query)
        )
    
    tasks = tasks.select_related('room_number', 'assigned_to', 'assigned_by', 'hotel').order_by('-created_at')
    
    # Sayfalama
    paginator = Paginator(tasks, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Atanan personel listesi
    from django.contrib.auth.models import User
    if hotel_id:
        assigned_users = User.objects.filter(
            assigned_cleaning_tasks__hotel_id=hotel_id,
            assigned_cleaning_tasks__is_deleted=False
        ).distinct()
    else:
        assigned_users = User.objects.filter(
            assigned_cleaning_tasks__is_deleted=False
        ).distinct()
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'tasks': page_obj,
        'status_filter': status_filter,
        'priority_filter': priority_filter,
        'assigned_to_filter': assigned_to_filter,
        'search_query': search_query,
        'assigned_users': assigned_users,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'housekeeping/tasks/list.html', context)


@login_required
@require_housekeeping_permission('manage')
def task_create(request):
    """Yeni Temizlik Görevi Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = CleaningTaskForm(request.POST, hotel=hotel)
        if form.is_valid():
            task = form.save(commit=False)
            task.hotel = hotel
            task.assigned_by = request.user
            task.assigned_at = timezone.now()
            task.save()
            
            messages.success(request, 'Temizlik görevi oluşturuldu.')
            return redirect('housekeeping:task_detail', pk=task.pk)
    else:
        form = CleaningTaskForm(hotel=hotel)
    
    context = {
        'hotel': hotel,
        'form': form,
    }
    
    return render(request, 'housekeeping/tasks/form.html', context)


@login_required
@require_housekeeping_permission('view')
def task_detail(request, pk):
    """Temizlik Görevi Detayı"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    task = get_object_or_404(
        CleaningTask.objects.select_related('room_number', 'assigned_to', 'assigned_by', 'inspected_by'),
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    # Kontrol listesi öğeleri
    checklist_items = task.checklist_items.all()
    
    # Eksik malzemeler
    missing_items = task.missing_items.all()
    
    # Çamaşır öğeleri
    laundry_items = task.laundry_items.all()
    
    context = {
        'hotel': hotel,
        'task': task,
        'checklist_items': checklist_items,
        'missing_items': missing_items,
        'laundry_items': laundry_items,
    }
    
    return render(request, 'housekeeping/tasks/detail.html', context)


@login_required
@require_housekeeping_permission('manage')
def task_update(request, pk):
    """Temizlik Görevi Güncelle"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    task = get_object_or_404(
        CleaningTask,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = CleaningTaskForm(request.POST, instance=task, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Temizlik görevi güncellendi.')
            return redirect('housekeeping:task_detail', pk=task.pk)
    else:
        form = CleaningTaskForm(instance=task, hotel=hotel)
    
    context = {
        'hotel': hotel,
        'task': task,
        'form': form,
    }
    
    return render(request, 'housekeeping/tasks/form.html', context)


@login_required
@require_housekeeping_permission('manage')
def task_start(request, pk):
    """Temizlik Görevine Başla"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        return JsonResponse({'success': False, 'error': 'Aktif otel seçilmedi.'})
    
    hotel = request.active_hotel
    task = get_object_or_404(
        CleaningTask,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if task.status != 'pending':
        return JsonResponse({'success': False, 'error': 'Görev zaten başlatılmış.'})
    
    task.status = 'in_progress'
    task.started_at = timezone.now()
    task.assigned_to = request.user
    task.save()
    
    # Oda durumunu güncelle
    task.room_number.status = RoomNumberStatus.CLEANING_PENDING
    task.room_number.save()
    
    return JsonResponse({'success': True, 'message': 'Görev başlatıldı.'})


@login_required
@require_housekeeping_permission('manage')
def task_complete(request, pk):
    """Temizlik Görevini Tamamla"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        return JsonResponse({'success': False, 'error': 'Aktif otel seçilmedi.'})
    
    hotel = request.active_hotel
    task = get_object_or_404(
        CleaningTask,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if task.status != 'in_progress':
        return JsonResponse({'success': False, 'error': 'Görev devam etmiyor.'})
    
    task.status = 'completed'
    task.completed_at = timezone.now()
    
    # Gerçek süreyi hesapla
    if task.started_at:
        duration = (timezone.now() - task.started_at).total_seconds() / 60
        task.actual_duration = int(duration)
    
    task.save()
    
    # Oda durumunu akıllı güncelle - Rezervasyon kontrolü yap
    from apps.tenant_apps.reception.models import Reservation, ReservationStatus
    from datetime import date, timedelta
    
    today = date.today()
    
    # Bugün veya yarın bu odada rezervasyon var mı?
    has_reservation = Reservation.objects.filter(
        room_number=task.room_number,
        check_in_date__lte=today + timedelta(days=1),
        check_out_date__gte=today,
        status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
        is_deleted=False
    ).exists()
    
    if has_reservation:
        # Rezervasyon var → Oda DOLU olmalı (check-in bekliyor veya müşteri var)
        task.room_number.status = RoomNumberStatus.OCCUPIED
    else:
        # Rezervasyon yok → Oda MÜSAİT olmalı
        task.room_number.status = RoomNumberStatus.AVAILABLE
    
    task.room_number.save()
    
    return JsonResponse({
        'success': True, 
        'message': 'Görev tamamlandı.',
        'room_status': task.room_number.get_status_display()
    })


@login_required
@require_housekeeping_permission('manage')
def task_inspect(request, pk):
    """Temizlik Görevini Kontrol Et"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    task = get_object_or_404(
        CleaningTask,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = CleaningTaskInspectionForm(request.POST, instance=task)
        if form.is_valid():
            task = form.save(commit=False)
            task.inspected_by = request.user
            task.inspected_at = timezone.now()
            task.save()
            
            messages.success(request, 'Kontrol tamamlandı.')
            return redirect('housekeeping:task_detail', pk=task.pk)
    else:
        form = CleaningTaskInspectionForm(instance=task)
    
    context = {
        'hotel': hotel,
        'task': task,
        'form': form,
    }
    
    return render(request, 'housekeeping/tasks/inspect.html', context)


# ==================== EKSİK MALZEMELER ====================

@login_required
@require_housekeeping_permission('view')
def missing_item_list(request):
    """Eksik Malzeme Listesi"""
    items = MissingItem.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                items = items.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin eksik malzemelerini göster
            # Sadece aktif otelin eksik malzemelerini göster
            items = items.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        items = items.filter(status=status_filter)
    
    if search_query:
        items = items.filter(
            Q(room_number__number__icontains=search_query) |
            Q(item_name__icontains=search_query)
        )
    
    items = items.select_related('room_number', 'reported_by', 'replaced_by', 'hotel').order_by('-reported_at')
    
    paginator = Paginator(items, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'items': page_obj,
        'status_filter': status_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'housekeeping/missing_items/list.html', context)


@login_required
@require_housekeeping_permission('manage')
def missing_item_create(request):
    """Yeni Eksik Malzeme Bildir"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = MissingItemForm(request.POST, hotel=hotel)
        if form.is_valid():
            item = form.save(commit=False)
            item.hotel = hotel
            item.reported_by = request.user
            item.save()
            
            messages.success(request, 'Eksik malzeme bildirildi.')
            return redirect('housekeeping:missing_item_list')
    else:
        form = MissingItemForm(hotel=hotel)
    
    context = {
        'hotel': hotel,
        'form': form,
    }
    
    return render(request, 'housekeeping/missing_items/form.html', context)


# ==================== ÇAMAŞIR YÖNETİMİ ====================

@login_required
@require_housekeeping_permission('view')
def laundry_list(request):
    """Çamaşır Listesi"""
    items = LaundryItem.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                items = items.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin çamaşırlarını göster
            items = items.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        items = items.filter(status=status_filter)
    
    if search_query:
        items = items.filter(
            Q(room_number__number__icontains=search_query)
        )
    
    items = items.select_related('room_number', 'collected_by', 'delivered_by', 'hotel').order_by('-collected_at')
    
    paginator = Paginator(items, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'items': page_obj,
        'status_filter': status_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'housekeeping/laundry/list.html', context)


# ==================== BAKIM TALEPLERİ ====================

@login_required
@require_housekeeping_permission('view')
def maintenance_request_list(request):
    """Bakım Talepleri Listesi"""
    requests = MaintenanceRequest.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                requests = requests.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin bakım taleplerini göster
            # Sadece aktif otelin bakım taleplerini göster
            requests = requests.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    priority_filter = request.GET.get('priority', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        requests = requests.filter(status=status_filter)
    
    if priority_filter:
        requests = requests.filter(priority=priority_filter)
    
    if search_query:
        requests = requests.filter(
            Q(room_number__number__icontains=search_query) |
            Q(description__icontains=search_query)
        )
    
    requests = requests.select_related('room_number', 'reported_by', 'assigned_to', 'hotel').order_by('-reported_at')
    
    paginator = Paginator(requests, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'requests': page_obj,
        'status_filter': status_filter,
        'priority_filter': priority_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'housekeeping/maintenance/list.html', context)


@login_required
@require_housekeeping_permission('manage')
def maintenance_request_create(request):
    """Yeni Bakım Talebi Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = MaintenanceRequestForm(request.POST, hotel=hotel)
        if form.is_valid():
            maintenance = form.save(commit=False)
            maintenance.hotel = hotel
            maintenance.reported_by = request.user
            maintenance.save()
            
            messages.success(request, 'Bakım talebi oluşturuldu.')
            return redirect('housekeeping:maintenance_request_list')
    else:
        form = MaintenanceRequestForm(hotel=hotel)
    
    context = {
        'hotel': hotel,
        'form': form,
    }
    
    return render(request, 'housekeeping/maintenance/form.html', context)


# ==================== AYARLAR ====================

@login_required
@require_housekeeping_permission('admin')
def settings(request):
    """Kat Hizmetleri Ayarları"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    settings_obj, created = HousekeepingSettings.objects.get_or_create(hotel=hotel)
    
    if request.method == 'POST':
        form = HousekeepingSettingsForm(request.POST, instance=settings_obj)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ayarlar kaydedildi.')
            return redirect('housekeeping:settings')
    else:
        form = HousekeepingSettingsForm(instance=settings_obj)
    
    context = {
        'hotel': hotel,
        'settings': settings_obj,
        'form': form,
    }
    
    return render(request, 'housekeeping/settings.html', context)

