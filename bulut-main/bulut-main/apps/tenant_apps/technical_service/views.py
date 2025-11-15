"""
Teknik Servis Modülü Views
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse
from django.db.models import Q, Count
from django.core.paginator import Paginator
from django.utils import timezone
from datetime import datetime, timedelta

from .models import MaintenanceRequest, MaintenanceRecord, Equipment, TechnicalServiceSettings
from .forms import MaintenanceRequestForm, MaintenanceRecordForm, EquipmentForm, TechnicalServiceSettingsForm
from .decorators import require_technical_service_permission
from apps.tenant_apps.hotels.models import Hotel


@login_required
@require_technical_service_permission('view')
def dashboard(request):
    """Teknik Servis Ana Ekran"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    today = timezone.now().date()
    
    # Bugünkü talepler
    today_requests = MaintenanceRequest.objects.filter(hotel=hotel, reported_at__date=today, is_deleted=False)
    pending_requests = MaintenanceRequest.objects.filter(hotel=hotel, status='pending', is_deleted=False).count()
    in_progress_requests = MaintenanceRequest.objects.filter(hotel=hotel, status='in_progress', is_deleted=False).count()
    completed_requests = MaintenanceRequest.objects.filter(hotel=hotel, status='completed', is_deleted=False, completed_at__date=today).count()
    urgent_requests = MaintenanceRequest.objects.filter(hotel=hotel, priority='urgent', status__in=['pending', 'assigned', 'in_progress'], is_deleted=False).count()
    
    # Ekipman durumları
    total_equipment = Equipment.objects.filter(hotel=hotel, is_deleted=False).count()
    broken_equipment = Equipment.objects.filter(hotel=hotel, status='broken', is_deleted=False).count()
    maintenance_equipment = Equipment.objects.filter(hotel=hotel, status='maintenance', is_deleted=False).count()
    
    # Son bakım kayıtları
    recent_records = MaintenanceRecord.objects.filter(hotel=hotel, is_deleted=False).select_related('performed_by').order_by('-performed_at')[:10]
    
    context = {
        'hotel': hotel,
        'pending_requests': pending_requests,
        'in_progress_requests': in_progress_requests,
        'completed_requests': completed_requests,
        'urgent_requests': urgent_requests,
        'total_equipment': total_equipment,
        'broken_equipment': broken_equipment,
        'maintenance_equipment': maintenance_equipment,
        'recent_records': recent_records,
    }
    
    return render(request, 'technical_service/dashboard.html', context)


@login_required
@require_technical_service_permission('view')
def request_list(request):
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
        requests = requests.filter(Q(description__icontains=search_query) | Q(room_number__number__icontains=search_query))
    
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
    
    return render(request, 'technical_service/requests/list.html', context)


@login_required
@require_technical_service_permission('manage')
def request_create(request):
    """Yeni Bakım Talebi Oluştur"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = MaintenanceRequestForm(request.POST, hotel=hotel)
        if form.is_valid():
            req = form.save(commit=False)
            req.hotel = hotel
            req.reported_by = request.user
            req.save()
            messages.success(request, 'Bakım talebi oluşturuldu.')
            return redirect('technical_service:request_list')
    else:
        form = MaintenanceRequestForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'technical_service/requests/form.html', context)


@login_required
@require_technical_service_permission('view')
def request_detail(request, pk):
    """Bakım Talebi Detayı"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    req = get_object_or_404(MaintenanceRequest.objects.select_related('room_number', 'reported_by', 'assigned_to'), pk=pk, hotel=hotel, is_deleted=False)
    records = req.records.all()
    
    context = {'hotel': hotel, 'request': req, 'records': records}
    return render(request, 'technical_service/requests/detail.html', context)


@login_required
@require_technical_service_permission('manage')
def request_assign(request, pk):
    """Bakım Talebini Ata"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        return JsonResponse({'success': False, 'error': 'Aktif otel seçilmedi.'})
    
    hotel = request.active_hotel
    req = get_object_or_404(MaintenanceRequest, pk=pk, hotel=hotel, is_deleted=False)
    
    assigned_to_id = request.POST.get('assigned_to')
    if assigned_to_id:
        from django.contrib.auth.models import User
        try:
            req.assigned_to = User.objects.get(pk=assigned_to_id)
            req.assigned_at = timezone.now()
            req.status = 'assigned'
            req.save()
            return JsonResponse({'success': True, 'message': 'Talep atandı.'})
        except User.DoesNotExist:
            return JsonResponse({'success': False, 'error': 'Kullanıcı bulunamadı.'})
    
    return JsonResponse({'success': False, 'error': 'Geçersiz istek.'})


@login_required
@require_technical_service_permission('manage')
def request_start(request, pk):
    """Bakım Talebine Başla"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        return JsonResponse({'success': False, 'error': 'Aktif otel seçilmedi.'})
    
    hotel = request.active_hotel
    req = get_object_or_404(MaintenanceRequest, pk=pk, hotel=hotel, is_deleted=False)
    
    if req.status not in ['assigned', 'pending']:
        return JsonResponse({'success': False, 'error': 'Talep zaten başlatılmış.'})
    
    req.status = 'in_progress'
    req.started_at = timezone.now()
    req.save()
    
    # Oda durumunu güncelle - Bakım başladığında oda BAKIMDA olur
    if req.room_number:
        from apps.tenant_apps.hotels.models import RoomNumberStatus
        req.room_number.status = RoomNumberStatus.MAINTENANCE
        req.room_number.save()
    
    return JsonResponse({'success': True, 'message': 'Bakım başlatıldı. Oda durumu "Bakımda" olarak güncellendi.'})


@login_required
@require_technical_service_permission('manage')
def request_complete(request, pk):
    """Bakım Talebini Tamamla"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    req = get_object_or_404(MaintenanceRequest, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        completion_notes = request.POST.get('completion_notes', '')
        actual_cost = request.POST.get('actual_cost', '')
        
        req.status = 'completed'
        req.completed_at = timezone.now()
        req.completion_notes = completion_notes
        if actual_cost:
            try:
                req.actual_cost = float(actual_cost)
            except ValueError:
                pass
        req.save()
        
        # Oda durumunu akıllı güncelle - Rezervasyon kontrolü yap
        if req.room_number:
            from apps.tenant_apps.hotels.models import RoomNumberStatus
            from apps.tenant_apps.reception.models import Reservation, ReservationStatus
            from datetime import date, timedelta
            
            today = date.today()
            
            # Bugün veya yarın bu odada rezervasyon var mı?
            has_reservation = Reservation.objects.filter(
                room_number=req.room_number,
                check_in_date__lte=today + timedelta(days=1),
                check_out_date__gte=today,
                status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
                is_deleted=False
            ).exists()
            
            if has_reservation:
                # Rezervasyon var → Oda DOLU olmalı (check-in bekliyor veya müşteri var)
                req.room_number.status = RoomNumberStatus.OCCUPIED
            else:
                # Rezervasyon yok → Oda MÜSAİT olmalı
                req.room_number.status = RoomNumberStatus.AVAILABLE
            
            req.room_number.save()
            messages.success(request, 'Bakım talebi tamamlandı. Oda durumu güncellendi.')
        else:
            messages.success(request, 'Bakım talebi tamamlandı.')
        
        return redirect('technical_service:request_detail', pk=req.pk)
    
    context = {'hotel': hotel, 'request': req}
    return render(request, 'technical_service/requests/complete.html', context)


@login_required
@require_technical_service_permission('view')
def equipment_list(request):
    """Ekipman Listesi"""
    equipment = Equipment.objects.filter(is_deleted=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                equipment = equipment.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin ekipmanlarını göster
            equipment = equipment.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    status_filter = request.GET.get('status', '')
    type_filter = request.GET.get('type', '')
    search_query = request.GET.get('search', '')
    
    if status_filter:
        equipment = equipment.filter(status=status_filter)
    if type_filter:
        equipment = equipment.filter(equipment_type=type_filter)
    if search_query:
        equipment = equipment.filter(Q(name__icontains=search_query) | Q(brand__icontains=search_query) | Q(model__icontains=search_query))
    
    equipment = equipment.select_related('room_number', 'hotel').order_by('name')
    
    paginator = Paginator(equipment, 25)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    
    context = {
        'hotel': request.active_hotel if hasattr(request, 'active_hotel') and request.active_hotel else None,
        'equipment': page_obj,
        'status_filter': status_filter,
        'type_filter': type_filter,
        'search_query': search_query,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
    }
    
    return render(request, 'technical_service/equipment/list.html', context)


@login_required
@require_technical_service_permission('manage')
def equipment_create(request):
    """Yeni Ekipman Ekle"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = EquipmentForm(request.POST, hotel=hotel)
        if form.is_valid():
            eq = form.save(commit=False)
            eq.hotel = hotel
            eq.save()
            messages.success(request, 'Ekipman eklendi.')
            return redirect('technical_service:equipment_list')
    else:
        form = EquipmentForm(hotel=hotel)
    
    context = {'hotel': hotel, 'form': form}
    return render(request, 'technical_service/equipment/form.html', context)


@login_required
@require_technical_service_permission('admin')
def settings(request):
    """Teknik Servis Ayarları"""
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Aktif otel seçilmedi.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    settings_obj, created = TechnicalServiceSettings.objects.get_or_create(hotel=hotel)
    
    if request.method == 'POST':
        form = TechnicalServiceSettingsForm(request.POST, instance=settings_obj)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ayarlar kaydedildi.')
            return redirect('technical_service:settings')
    else:
        form = TechnicalServiceSettingsForm(instance=settings_obj)
    
    context = {'hotel': hotel, 'settings': settings_obj, 'form': form}
    return render(request, 'technical_service/settings.html', context)

