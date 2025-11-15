"""
Kanal Yönetimi Modülü Views
OTA entegrasyonları için kapsamlı kanal yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.core.paginator import Paginator
from django.db.models import Q, Count, Sum, F
from django.utils import timezone
from django.http import JsonResponse
from django.db import transaction
from datetime import date, timedelta
from decimal import Decimal
import logging

from .models import (
    ChannelConfiguration, ChannelSync,
    ChannelReservation, ChannelPricing, ChannelCommission
)
from apps.modules.models import ChannelTemplate  # Public schema'dan import

logger = logging.getLogger(__name__)
from .forms import (
    ChannelConfigurationForm, ChannelReservationForm, ChannelPricingForm
)
from .decorators import require_channel_management_permission
from apps.tenants.models import Tenant
from apps.tenant_apps.hotels.models import Hotel


# ==================== KANAL ŞABLONLARI ====================

@login_required
@require_channel_management_permission('view')
def template_list(request):
    """Kanal Şablonları Listesi"""
    # ChannelTemplate artık apps.modules.models içinde (public schema)
    templates = ChannelTemplate.objects.filter(
        is_active=True,
        is_deleted=False
    ).order_by('sort_order', 'name')
    
    # Popüler kanallar
    popular_templates = templates.filter(is_popular=True)
    
    # Kanal tiplerine göre grupla
    templates_by_type = {}
    for template in templates:
        if template.channel_type not in templates_by_type:
            templates_by_type[template.channel_type] = []
        templates_by_type[template.channel_type].append(template)
    
    context = {
        'templates': templates,
        'popular_templates': popular_templates,
        'templates_by_type': templates_by_type,
    }
    
    return render(request, 'channel_management/template_list.html', context)


@login_required
@require_channel_management_permission('view')
def template_detail(request, pk):
    """Kanal Şablonu Detayı"""
    template = get_object_or_404(ChannelTemplate, pk=pk, is_active=True, is_deleted=False)
    
    # Bu şablonu kullanan konfigürasyonlar (sadece kullanıcının tenant'ı)
    tenant = request.tenant if hasattr(request, 'tenant') else None
    configurations = None
    if tenant:
        configurations = ChannelConfiguration.objects.filter(
            tenant=tenant,
            template=template,
            is_deleted=False
        ).select_related('hotel').order_by('-created_at')[:10]
    
    context = {
        'template': template,
        'configurations': configurations,
    }
    
    return render(request, 'channel_management/template_detail.html', context)


@login_required
@require_channel_management_permission('view')
def test_template_connection(request, pk):
    """Kanal Şablonu Bağlantı Testi"""
    from django.http import JsonResponse
    
    template = get_object_or_404(ChannelTemplate, pk=pk, is_active=True, is_deleted=False)
    
    # Bu şablonu kullanan aktif konfigürasyonları bul
    tenant = request.tenant if hasattr(request, 'tenant') else None
    
    if not tenant:
        return JsonResponse({
            'success': False,
            'message': 'Tenant bulunamadı.'
        }, status=400)
    
    # Bu şablon için aktif konfigürasyon var mı?
    configuration = ChannelConfiguration.objects.filter(
        tenant=tenant,
        template=template,
        is_active=True,
        is_deleted=False
    ).first()
    
    if not configuration:
        return JsonResponse({
            'success': False,
            'message': f'{template.name} için aktif bir konfigürasyon bulunamadı. Lütfen önce kanalı yapılandırın.'
        }, status=400)
    
    # API bilgileri kontrolü
    if not configuration.api_credentials:
        return JsonResponse({
            'success': False,
            'message': 'API bilgileri eksik. Lütfen konfigürasyonu kontrol edin.'
        }, status=400)
    
    try:
        # Integration sınıfını yükle
        from .utils import get_channel_integration
        
        integration = get_channel_integration(configuration)
        
        # Bağlantı testi yap
        is_authenticated = integration.authenticate()
        
        if is_authenticated:
            return JsonResponse({
                'success': True,
                'message': f'{template.name} bağlantı testi başarılı! API bağlantısı çalışıyor.'
            })
        else:
            return JsonResponse({
                'success': False,
                'message': f'{template.name} bağlantı testi başarısız. API bilgilerini kontrol edin.'
            }, status=400)
            
    except Exception as e:
        logger.error(f"Kanal bağlantı testi hatası: {str(e)}", exc_info=True)
        return JsonResponse({
            'success': False,
            'message': f'Bağlantı testi sırasında hata oluştu: {str(e)}'
        }, status=500)


# ==================== KANAL KONFİGÜRASYONLARI ====================

@login_required
@require_channel_management_permission('view')
def configuration_list(request):
    """Kanal Konfigürasyonları Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_deleted=False
    ).select_related('template', 'hotel').order_by('-created_at')
    
    # Filtreleme
    search = request.GET.get('search', '')
    if search:
        configurations = configurations.filter(
            Q(name__icontains=search) |
            Q(template__name__icontains=search) |
            Q(hotel__name__icontains=search)
        )
    
    status_filter = request.GET.get('status', '')
    if status_filter == 'active':
        configurations = configurations.filter(is_active=True)
    elif status_filter == 'inactive':
        configurations = configurations.filter(is_active=False)
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():  # Boş string kontrolü
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                configurations = configurations.filter(hotel_id=hotel_id)
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Sadece aktif otelin kanal yapılandırmalarını göster
            configurations = configurations.filter(hotel=request.active_hotel)
            hotel_id = request.active_hotel.id
    
    # Sayfalama
    paginator = Paginator(configurations, 20)
    page = request.GET.get('page')
    configurations = paginator.get_page(page)
    
    # Otel listesi (filtreleme için)
    accessible_hotels = []
    if hasattr(request, 'accessible_hotels'):
        accessible_hotels = request.accessible_hotels
    else:
        # Fallback: Tüm otelleri göster
        from apps.tenant_apps.hotels.models import Hotel
        accessible_hotels = Hotel.objects.filter(is_deleted=False).order_by('name')
    
    # İstatistikler
    stats = {
        'total': ChannelConfiguration.objects.filter(tenant=tenant, is_deleted=False).count(),
        'active': ChannelConfiguration.objects.filter(tenant=tenant, is_active=True, is_deleted=False).count(),
        'with_sync': ChannelConfiguration.objects.filter(tenant=tenant, sync_enabled=True, is_deleted=False).count(),
    }
    
    context = {
        'configurations': configurations,
        'hotels': accessible_hotels,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
        'stats': stats,
        'search': search,
        'status_filter': status_filter,
    }
    
    return render(request, 'channel_management/configuration_list.html', context)


@login_required
@require_channel_management_permission('add')
def configuration_create(request):
    """Yeni Kanal Konfigürasyonu Oluştur"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    template_id = request.GET.get('template_id')
    
    # ChannelTemplate artık apps.modules.models içinde (public schema)
    template = None
    if template_id:
        template = get_object_or_404(ChannelTemplate, pk=template_id, is_active=True, is_deleted=False)
    
    if request.method == 'POST':
        form = ChannelConfigurationForm(request.POST, tenant=tenant)
        if form.is_valid():
            configuration = form.save(commit=False)
            configuration.tenant = tenant
            if template:
                configuration.template = template
            configuration.save()
            messages.success(request, f'{configuration.name} kanal konfigürasyonu başarıyla oluşturuldu.')
            return redirect('channel_management:configuration_detail', pk=configuration.pk)
    else:
        form = ChannelConfigurationForm(tenant=tenant)
        if template:
            form.initial['template'] = template.pk
            form.fields['template'].initial = template.pk
    
    context = {
        'form': form,
        'template': template,
    }
    
    return render(request, 'channel_management/configuration_form.html', context)


@login_required
@require_channel_management_permission('view')
def configuration_detail(request, pk):
    """Kanal Konfigürasyonu Detayı"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    configuration = get_object_or_404(
        ChannelConfiguration,
        pk=pk,
        tenant=tenant,
        is_deleted=False
    )
    
    # Son senkronizasyonlar
    recent_syncs = ChannelSync.objects.filter(
        configuration=configuration
    ).order_by('-created_at')[:10]
    
    # Son rezervasyonlar
    recent_reservations = ChannelReservation.objects.filter(
        configuration=configuration,
        is_deleted=False
    ).order_by('-created_at')[:10]
    
    # İstatistikler
    stats = {
        'total_syncs': ChannelSync.objects.filter(configuration=configuration).count(),
        'successful_syncs': ChannelSync.objects.filter(
            configuration=configuration,
            status='completed'
        ).count(),
        'total_reservations': ChannelReservation.objects.filter(
            configuration=configuration,
            is_deleted=False
        ).count(),
        'pending_reservations': ChannelReservation.objects.filter(
            configuration=configuration,
            status='pending',
            is_deleted=False
        ).count(),
        'total_commission': ChannelCommission.objects.filter(
            configuration=configuration
        ).aggregate(total=Sum('commission_amount'))['total'] or Decimal('0'),
    }
    
    context = {
        'configuration': configuration,
        'recent_syncs': recent_syncs,
        'recent_reservations': recent_reservations,
        'stats': stats,
    }
    
    return render(request, 'channel_management/configuration_detail.html', context)


@login_required
@require_channel_management_permission('edit')
def configuration_update(request, pk):
    """Kanal Konfigürasyonu Düzenle"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    configuration = get_object_or_404(
        ChannelConfiguration,
        pk=pk,
        tenant=tenant,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = ChannelConfigurationForm(request.POST, instance=configuration, tenant=tenant)
        if form.is_valid():
            form.save()
            messages.success(request, 'Kanal konfigürasyonu başarıyla güncellendi.')
            return redirect('channel_management:configuration_detail', pk=configuration.pk)
    else:
        form = ChannelConfigurationForm(instance=configuration, tenant=tenant)
    
    context = {
        'form': form,
        'configuration': configuration,
    }
    
    return render(request, 'channel_management/configuration_form.html', context)


@login_required
@require_channel_management_permission('delete')
def configuration_delete(request, pk):
    """Kanal Konfigürasyonu Sil"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    configuration = get_object_or_404(
        ChannelConfiguration,
        pk=pk,
        tenant=tenant,
        is_deleted=False
    )
    
    if request.method == 'POST':
        configuration.is_deleted = True
        configuration.deleted_at = timezone.now()
        configuration.save()
        messages.success(request, 'Kanal konfigürasyonu başarıyla silindi.')
        return redirect('channel_management:configuration_list')
    
    context = {
        'configuration': configuration,
    }
    
    return render(request, 'channel_management/configuration_delete.html', context)


# ==================== KANAL SENKRONİZASYONU ====================

@login_required
@require_channel_management_permission('edit')
def sync_trigger(request, pk):
    """Manuel Senkronizasyon Başlat"""
    if not hasattr(request, 'tenant') or not request.tenant:
        return JsonResponse({'success': False, 'error': 'Tenant bulunamadı.'})
    
    tenant = request.tenant
    configuration = get_object_or_404(
        ChannelConfiguration,
        pk=pk,
        tenant=tenant,
        is_deleted=False
    )
    
    sync_type = request.GET.get('type', 'full')
    
    # Senkronizasyon kaydı oluştur
    sync = ChannelSync.objects.create(
        configuration=configuration,
        sync_type=sync_type,
        direction='bidirectional',
        status='running',
        started_at=timezone.now()
    )
    
    try:
        # Entegrasyon sınıfını al
        from .utils import get_channel_integration
        integration = get_channel_integration(configuration)
        
        successful_items = 0
        failed_items = 0
        
        # Senkronizasyon tipine göre işlem yap
        if sync_type in ['pricing', 'full']:
            # Fiyat senkronizasyonu (push)
            if configuration.auto_sync_pricing:
                from apps.tenant_apps.hotels.models import Room, RoomPrice
                from datetime import date, timedelta
                
                # Otel odalarını al
                hotel = configuration.hotel
                if hotel:
                    rooms = Room.objects.filter(hotel=hotel, is_deleted=False, is_active=True)
                    
                    # Gelecek 365 gün için fiyat senkronizasyonu
                    today = date.today()
                    end_date = today + timedelta(days=365)
                    
                    for room in rooms:
                        try:
                            # Oda fiyatını al
                            room_price = RoomPrice.objects.filter(
                                room=room,
                                is_active=True,
                                is_deleted=False
                            ).first()
                            
                            if room_price:
                                # Temel fiyatı al
                                base_price = room_price.basic_nightly_price
                                
                                # Kanal fiyatını hesapla
                                channel_price = integration.calculate_channel_price(base_price)
                                
                                # ChannelPricing kaydı oluştur veya güncelle
                                channel_pricing, created = ChannelPricing.objects.update_or_create(
                                    configuration=configuration,
                                    room=room,
                                    start_date=today,
                                    end_date=end_date,
                                    defaults={
                                        'base_price': base_price,
                                        'channel_price': channel_price,
                                        'currency': room_price.currency,
                                        'is_active': True,
                                        'last_synced_at': timezone.now(),
                                    }
                                )
                                
                                # Kanala fiyat gönder
                                result = integration.push_pricing(
                                    room_id=room.pk,
                                    start_date=today,
                                    end_date=end_date,
                                    base_price=base_price,
                                    availability=0  # Availability ayrı senkronize edilecek
                                )
                                
                                if result.get('success'):
                                    successful_items += 1
                                else:
                                    failed_items += 1
                        except Exception as e:
                            failed_items += 1
                            logger.error(f"Oda fiyatı senkronizasyonu hatası (Room {room.pk}): {str(e)}", exc_info=True)
        
        if sync_type in ['availability', 'full']:
            # Müsaitlik senkronizasyonu (push)
            if configuration.auto_sync_availability:
                from apps.tenant_apps.hotels.models import Room, RoomNumber, RoomNumberStatus
                from datetime import date, timedelta
                
                # Otel odalarını al
                hotel = configuration.hotel
                if hotel:
                    rooms = Room.objects.filter(hotel=hotel, is_deleted=False, is_active=True)
                    
                    # Bugün ve gelecek 365 gün için müsaitlik senkronizasyonu
                    today = date.today()
                    end_date = today + timedelta(days=365)
                    
                    for room in rooms:
                        try:
                            # Oda numaralarını al ve müsait olanları say
                            room_numbers = RoomNumber.objects.filter(
                                room=room,
                                is_deleted=False,
                                is_active=True
                            )
                            
                            # Her tarih için müsaitlik hesapla
                            current_date = today
                            while current_date <= end_date:
                                # Bu tarihte müsait oda sayısını hesapla
                                # (Rezervasyon kontrolü yapılmalı)
                                from apps.tenant_apps.reception.models import Reservation, ReservationStatus
                                
                                # Bu tarihte dolu odalar
                                occupied_rooms = Reservation.objects.filter(
                                    hotel=hotel,
                                    room=room,
                                    check_in_date__lte=current_date,
                                    check_out_date__gt=current_date,
                                    status__in=[ReservationStatus.CONFIRMED, ReservationStatus.CHECKED_IN],
                                    is_deleted=False
                                ).values_list('room_number_id', flat=True).distinct()
                                
                                # Müsait oda sayısı
                                available_count = room_numbers.exclude(
                                    id__in=occupied_rooms
                                ).exclude(
                                    status__in=[RoomNumberStatus.MAINTENANCE, RoomNumberStatus.OUT_OF_ORDER]
                                ).count()
                                
                                # ChannelPricing'de müsaitlik güncelle
                                channel_pricing = ChannelPricing.objects.filter(
                                    configuration=configuration,
                                    room=room,
                                    start_date__lte=current_date,
                                    end_date__gte=current_date,
                                    is_active=True
                                ).first()
                                
                                if channel_pricing:
                                    channel_pricing.availability = available_count
                                    channel_pricing.last_synced_at = timezone.now()
                                    channel_pricing.save()
                                    
                                    # Kanala müsaitlik gönder
                                    result = integration.push_pricing(
                                        room_id=room.pk,
                                        start_date=current_date,
                                        end_date=current_date + timedelta(days=1),
                                        base_price=channel_pricing.base_price,
                                        availability=available_count
                                    )
                                    
                                    if result.get('success'):
                                        successful_items += 1
                                    else:
                                        failed_items += 1
                                
                                current_date += timedelta(days=1)
                                
                        except Exception as e:
                            failed_items += 1
                            logger.error(f"Oda müsaitlik senkronizasyonu hatası (Room {room.pk}): {str(e)}", exc_info=True)
        
        if sync_type in ['reservation', 'full']:
            # Rezervasyon senkronizasyonu (pull)
            if configuration.template.supports_reservations:
                reservations = integration.pull_reservations()
                for res_data in reservations:
                    try:
                        # Rezervasyon kaydı oluştur veya güncelle
                        channel_reservation, created = ChannelReservation.objects.update_or_create(
                            configuration=configuration,
                            channel_reservation_id=res_data['channel_reservation_id'],
                            defaults={
                                'channel_reservation_code': res_data.get('channel_reservation_code', ''),
                                'guest_name': res_data['guest_name'],
                                'guest_email': res_data.get('guest_email', ''),
                                'guest_phone': res_data.get('guest_phone', ''),
                                'check_in_date': res_data['check_in_date'],
                                'check_out_date': res_data['check_out_date'],
                                'adult_count': res_data.get('adult_count', 1),
                                'child_count': res_data.get('child_count', 0),
                                'room_type_name': res_data.get('room_type_name', ''),
                                'total_amount': res_data['total_amount'],
                                'currency': res_data.get('currency', 'TRY'),
                                'status': res_data.get('status', 'pending'),
                                'channel_data': res_data.get('channel_data', {}),
                            }
                        )
                        
                        # Komisyon hesapla ve kaydet
                        commission_amount = integration.calculate_commission(res_data['total_amount'])
                        if commission_amount > 0:
                            ChannelCommission.objects.get_or_create(
                                configuration=configuration,
                                reservation=channel_reservation,
                                defaults={
                                    'commission_rate': configuration.get_effective_commission_rate(),
                                    'base_amount': res_data['total_amount'],
                                    'commission_amount': commission_amount,
                                    'currency': res_data.get('currency', 'TRY'),
                                    'commission_date': timezone.now().date(),
                                }
                            )
                        
                        successful_items += 1
                    except Exception as e:
                        failed_items += 1
                        logger.error(f"Rezervasyon kaydı oluşturulurken hata: {str(e)}", exc_info=True)
        
        sync.status = 'completed' if failed_items == 0 else ('partial' if successful_items > 0 else 'failed')
        sync.successful_items = successful_items
        sync.failed_items = failed_items
        sync.completed_at = timezone.now()
        sync.calculate_duration()
        sync.save()
        
        # Son senkronizasyon zamanını güncelle
        configuration.last_sync_at = timezone.now()
        if configuration.sync_interval > 0:
            configuration.next_sync_at = timezone.now() + timedelta(minutes=configuration.sync_interval)
        configuration.save()
        
        return JsonResponse({
            'success': True,
            'message': f'Senkronizasyon tamamlandı. Başarılı: {successful_items}, Başarısız: {failed_items}',
            'sync_id': sync.pk
        })
        
    except Exception as e:
        sync.status = 'failed'
        sync.error_message = str(e)
        sync.completed_at = timezone.now()
        sync.calculate_duration()
        sync.save()
        
        logger.error(f"Senkronizasyon hatası: {str(e)}", exc_info=True)
        
        return JsonResponse({
            'success': False,
            'error': f'Senkronizasyon hatası: {str(e)}'
        })


@login_required
@require_channel_management_permission('view')
def sync_list(request, pk):
    """Kanal Senkronizasyonları Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    configuration = get_object_or_404(
        ChannelConfiguration,
        pk=pk,
        tenant=tenant,
        is_deleted=False
    )
    
    syncs = ChannelSync.objects.filter(
        configuration=configuration
    ).order_by('-created_at')
    
    # Filtreleme
    status_filter = request.GET.get('status', '')
    if status_filter:
        syncs = syncs.filter(status=status_filter)
    
    type_filter = request.GET.get('type', '')
    if type_filter:
        syncs = syncs.filter(sync_type=type_filter)
    
    # Sayfalama
    paginator = Paginator(syncs, 20)
    page = request.GET.get('page')
    syncs = paginator.get_page(page)
    
    context = {
        'configuration': configuration,
        'syncs': syncs,
        'status_filter': status_filter,
        'type_filter': type_filter,
    }
    
    return render(request, 'channel_management/sync_list.html', context)


# ==================== KANAL REZERVASYONLARI ====================

@login_required
@require_channel_management_permission('view')
def reservation_list(request):
    """Kanal Rezervasyonları Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    reservations = ChannelReservation.objects.filter(
        configuration__tenant=tenant,
        is_deleted=False
    ).select_related('configuration', 'system_reservation').order_by('-created_at')
    
    # Filtreleme
    search = request.GET.get('search', '')
    if search:
        reservations = reservations.filter(
            Q(guest_name__icontains=search) |
            Q(guest_email__icontains=search) |
            Q(channel_reservation_code__icontains=search) |
            Q(channel_reservation_id__icontains=search)
        )
    
    status_filter = request.GET.get('status', '')
    if status_filter:
        reservations = reservations.filter(status=status_filter)
    
    config_filter = request.GET.get('configuration', '')
    if config_filter:
        reservations = reservations.filter(configuration_id=config_filter)
    
    # Tarih filtreleri
    check_in_from = request.GET.get('check_in_from', '')
    if check_in_from:
        reservations = reservations.filter(check_in_date__gte=check_in_from)
    
    check_in_to = request.GET.get('check_in_to', '')
    if check_in_to:
        reservations = reservations.filter(check_in_date__lte=check_in_to)
    
    # Sayfalama
    paginator = Paginator(reservations, 20)
    page = request.GET.get('page')
    reservations = paginator.get_page(page)
    
    # Konfigürasyonlar (filtre için)
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'reservations': reservations,
        'configurations': configurations,
        'search': search,
        'status_filter': status_filter,
        'config_filter': config_filter,
        'check_in_from': check_in_from,
        'check_in_to': check_in_to,
    }
    
    return render(request, 'channel_management/reservation_list.html', context)


@login_required
@require_channel_management_permission('view')
def reservation_detail(request, pk):
    """Kanal Rezervasyonu Detayı"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    reservation = get_object_or_404(
        ChannelReservation,
        pk=pk,
        configuration__tenant=tenant,
        is_deleted=False
    )
    
    # Komisyon kayıtları
    commissions = ChannelCommission.objects.filter(
        reservation=reservation
    ).order_by('-commission_date')
    
    context = {
        'reservation': reservation,
        'commissions': commissions,
    }
    
    return render(request, 'channel_management/reservation_detail.html', context)


# ==================== KANAL FİYATLARI ====================

@login_required
@require_channel_management_permission('view')
def pricing_list(request):
    """Kanal Fiyatları Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    pricings = ChannelPricing.objects.filter(
        configuration__tenant=tenant,
        is_deleted=False
    ).select_related('configuration', 'room').order_by('-start_date')
    
    # Filtreleme
    config_filter = request.GET.get('configuration', '')
    if config_filter:
        pricings = pricings.filter(configuration_id=config_filter)
    
    room_filter = request.GET.get('room', '')
    if room_filter:
        pricings = pricings.filter(room_id=room_filter)
    
    # Tarih filtreleri
    start_date = request.GET.get('start_date', '')
    if start_date:
        pricings = pricings.filter(start_date__gte=start_date)
    
    end_date = request.GET.get('end_date', '')
    if end_date:
        pricings = pricings.filter(end_date__lte=end_date)
    
    # Sayfalama
    paginator = Paginator(pricings, 20)
    page = request.GET.get('page')
    pricings = paginator.get_page(page)
    
    # Konfigürasyonlar (filtre için)
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'pricings': pricings,
        'configurations': configurations,
        'config_filter': config_filter,
        'room_filter': room_filter,
        'start_date': start_date,
        'end_date': end_date,
    }
    
    return render(request, 'channel_management/pricing_list.html', context)


@login_required
@require_channel_management_permission('pricing')
def pricing_create(request):
    """Yeni Kanal Fiyatı Oluştur"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    if request.method == 'POST':
        form = ChannelPricingForm(request.POST, tenant=tenant)
        if form.is_valid():
            pricing = form.save()
            messages.success(request, 'Kanal fiyatı başarıyla oluşturuldu.')
            return redirect('channel_management:pricing_list')
    else:
        form = ChannelPricingForm(tenant=tenant)
    
    # Konfigürasyonlar (form için)
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'form': form,
        'configurations': configurations,
    }
    
    return render(request, 'channel_management/pricing_form.html', context)


@login_required
@require_channel_management_permission('pricing')
def pricing_update(request, pk):
    """Kanal Fiyatı Düzenle"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    pricing = get_object_or_404(
        ChannelPricing,
        pk=pk,
        configuration__tenant=tenant,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = ChannelPricingForm(request.POST, instance=pricing, tenant=tenant)
        if form.is_valid():
            form.save()
            messages.success(request, 'Kanal fiyatı başarıyla güncellendi.')
            return redirect('channel_management:pricing_list')
    else:
        form = ChannelPricingForm(instance=pricing, tenant=tenant)
    
    # Konfigürasyonlar (form için)
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'form': form,
        'pricing': pricing,
        'configurations': configurations,
    }
    
    return render(request, 'channel_management/pricing_form.html', context)


@login_required
@require_channel_management_permission('pricing')
def pricing_delete(request, pk):
    """Kanal Fiyatı Sil"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    pricing = get_object_or_404(
        ChannelPricing,
        pk=pk,
        configuration__tenant=tenant,
        is_deleted=False
    )
    
    if request.method == 'POST':
        pricing.is_deleted = True
        pricing.deleted_at = timezone.now()
        pricing.save()
        messages.success(request, 'Kanal fiyatı başarıyla silindi.')
        return redirect('channel_management:pricing_list')
    
    context = {
        'pricing': pricing,
    }
    
    return render(request, 'channel_management/pricing_delete.html', context)


# ==================== KANAL KOMİSYONLARI ====================

@login_required
@require_channel_management_permission('view')
def commission_list(request):
    """Kanal Komisyonları Listesi"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    
    commissions = ChannelCommission.objects.filter(
        configuration__tenant=tenant
    ).select_related('configuration', 'reservation').order_by('-commission_date')
    
    # Filtreleme
    config_filter = request.GET.get('configuration', '')
    if config_filter:
        commissions = commissions.filter(configuration_id=config_filter)
    
    paid_filter = request.GET.get('paid', '')
    if paid_filter == 'yes':
        commissions = commissions.filter(is_paid=True)
    elif paid_filter == 'no':
        commissions = commissions.filter(is_paid=False)
    
    # Tarih filtreleri
    date_from = request.GET.get('date_from', '')
    if date_from:
        commissions = commissions.filter(commission_date__gte=date_from)
    
    date_to = request.GET.get('date_to', '')
    if date_to:
        commissions = commissions.filter(commission_date__lte=date_to)
    
    # İstatistikler (sayfalama öncesi)
    total_count = commissions.count()
    
    # Sayfalama
    paginator = Paginator(commissions, 20)
    page = request.GET.get('page')
    commissions = paginator.get_page(page)
    
    # İstatistikler
    stats = {
        'total': total_count,
        'paid': ChannelCommission.objects.filter(
            configuration__tenant=tenant,
            is_paid=True
        ).aggregate(total=Sum('commission_amount'))['total'] or Decimal('0'),
        'unpaid': ChannelCommission.objects.filter(
            configuration__tenant=tenant,
            is_paid=False
        ).aggregate(total=Sum('commission_amount'))['total'] or Decimal('0'),
    }
    
    # Konfigürasyonlar (filtre için)
    configurations = ChannelConfiguration.objects.filter(
        tenant=tenant,
        is_active=True,
        is_deleted=False
    ).order_by('name')
    
    context = {
        'commissions': commissions,
        'configurations': configurations,
        'stats': stats,
        'config_filter': config_filter,
        'paid_filter': paid_filter,
        'date_from': date_from,
        'date_to': date_to,
    }
    
    return render(request, 'channel_management/commission_list.html', context)


@login_required
@require_channel_management_permission('commission')
def commission_detail(request, pk):
    """Kanal Komisyonu Detayı"""
    if not hasattr(request, 'tenant') or not request.tenant:
        messages.error(request, 'Tenant bulunamadı.')
        return redirect('core:dashboard')
    
    tenant = request.tenant
    commission = get_object_or_404(
        ChannelCommission,
        pk=pk,
        configuration__tenant=tenant
    )
    
    context = {
        'commission': commission,
    }
    
    return render(request, 'channel_management/commission_detail.html', context)

