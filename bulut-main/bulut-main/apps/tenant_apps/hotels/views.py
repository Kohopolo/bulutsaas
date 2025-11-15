"""
Otel Yönetimi Views - TAM VERSİYON
Profesyonel otel yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.db.models import Q, Count, Sum, Avg, Min, Max
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal

from .models import (
    # Otel Ayarları
    HotelRegion, HotelCity, HotelType, RoomType, BoardType, BedType,
    RoomFeature, HotelFeature,
    # Otel
    Hotel, HotelImage, HotelExtraService,
    # Oda
    Room, RoomImage,
    # Fiyatlama
    RoomPrice, RoomSeasonalPrice, RoomSpecialPrice, RoomCampaignPrice,
    RoomAgencyPrice, RoomChannelPrice,
    # Oda Numaraları
    Floor, Block, RoomNumber, RoomNumberStatus,
    # Kullanıcı Yetkileri
    HotelUserPermission,
)
from .forms import (
    HotelRegionForm, HotelCityForm, HotelTypeForm, RoomTypeForm, BoardTypeForm,
    BedTypeForm, RoomFeatureForm, HotelFeatureForm,
    HotelForm, HotelImageForm, ExtraServiceForm,
    RoomForm, RoomImageForm,
    RoomPriceForm, RoomSeasonalPriceForm, RoomSpecialPriceForm,
    RoomCampaignPriceForm, RoomAgencyPriceForm, RoomChannelPriceForm,
    FloorForm, BlockForm, RoomNumberForm, BulkRoomNumberForm,
)
from apps.tenant_apps.core.decorators import require_module_permission
from apps.tenant_apps.core.models import TenantUser
from .decorators import require_hotel_permission


# ==================== OTEL SEÇİMİ ====================

@login_required
def select_hotel(request):
    """Otel seçimi"""
    from .models import Hotel, HotelUserPermission
    
    tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
    
    # Admin ise tüm otelleri göster
    is_admin = tenant_user.has_module_permission('hotels', 'admin')
    if is_admin:
        hotels = Hotel.objects.filter(is_active=True).order_by('sort_order', 'name')
    else:
        # Kullanıcının yetkili olduğu oteller
        hotel_ids = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).values_list('hotel_id', flat=True)
        hotels = Hotel.objects.filter(id__in=hotel_ids, is_active=True).order_by('sort_order', 'name')
    
    if request.method == 'POST':
        hotel_id = request.POST.get('hotel_id')
        if hotel_id:
            try:
                hotel = Hotel.objects.get(pk=hotel_id, is_active=True)
                request.session['active_hotel_id'] = hotel.pk
                messages.success(request, f'{hotel.name} oteli seçildi.')
                return redirect('hotels:hotel_list')
            except Hotel.DoesNotExist:
                messages.error(request, 'Otel bulunamadı.')
    
    context = {
        'hotels': hotels,
    }
    return render(request, 'tenant/hotels/select_hotel.html', context)


@login_required
def switch_hotel(request, hotel_id):
    """Otel değiştir (GET ve POST destekli, AJAX destekli)"""
    from .models import Hotel, HotelUserPermission
    
    tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
    
    try:
        hotel = Hotel.objects.get(pk=hotel_id, is_active=True)
        
        # Yetki kontrolü
        is_admin = tenant_user.has_module_permission('hotels', 'admin')
        if not is_admin:
            has_permission = HotelUserPermission.objects.filter(
                tenant_user=tenant_user,
                hotel=hotel,
                is_active=True
            ).exists()
            if not has_permission:
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    return JsonResponse({'success': False, 'message': 'Bu otel için yetkiniz yok.'})
                messages.error(request, 'Bu otel için yetkiniz yok.')
                return redirect('hotels:select_hotel')
        
        request.session['active_hotel_id'] = hotel.pk
        
        # AJAX isteği ise JSON döndür
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'success': True, 'message': f'{hotel.name} oteline geçildi.'})
        
        # Normal istek ise mesaj göster ve yönlendir
        messages.success(request, f'{hotel.name} oteline geçildi.')
        
        # Referer varsa oraya dön, yoksa hotel_list'e git
        referer = request.META.get('HTTP_REFERER')
        if referer:
            return redirect(referer)
        return redirect('hotels:hotel_list')
    except Hotel.DoesNotExist:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'success': False, 'message': 'Otel bulunamadı.'})
        messages.error(request, 'Otel bulunamadı.')
        return redirect('hotels:select_hotel')


# ==================== OTEL AYARLARI ====================

@login_required
@require_module_permission('hotels', 'view')
def settings_list(request):
    """Otel ayarları ana sayfa"""
    from .models import (
        HotelRegion, HotelCity, HotelType, RoomType, BoardType, BedType,
        RoomFeature, HotelFeature
    )
    
    context = {
        'regions': HotelRegion.objects.filter(is_deleted=False).count(),
        'cities': HotelCity.objects.filter(is_deleted=False).count(),
        'hotel_types': HotelType.objects.filter(is_deleted=False).count(),
        'room_types': RoomType.objects.filter(hotel=request.active_hotel, is_deleted=False).count() if hasattr(request, 'active_hotel') and request.active_hotel else 0,
        'board_types': BoardType.objects.filter(hotel=request.active_hotel, is_deleted=False).count() if hasattr(request, 'active_hotel') and request.active_hotel else 0,
        'bed_types': BedType.objects.filter(hotel=request.active_hotel, is_deleted=False).count() if hasattr(request, 'active_hotel') and request.active_hotel else 0,
        'room_features': RoomFeature.objects.filter(hotel=request.active_hotel, is_deleted=False).count() if hasattr(request, 'active_hotel') and request.active_hotel else 0,
        'hotel_features': HotelFeature.objects.filter(is_deleted=False).count(),
    }
    
    return render(request, 'tenant/hotels/settings/list.html', context)


# ==================== BÖLGE YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def region_list(request):
    """Bölge listesi"""
    regions = HotelRegion.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        regions = regions.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(regions, 20)
    page_number = request.GET.get('page', 1)
    regions = paginator.get_page(page_number)
    
    context = {
        'regions': regions,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/regions/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def region_create(request):
    """Bölge ekleme"""
    if request.method == 'POST':
        form = HotelRegionForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bölge başarıyla eklendi.')
            return redirect('hotels:region_list')
    else:
        form = HotelRegionForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/regions/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def region_update(request, pk):
    """Bölge düzenleme"""
    region = get_object_or_404(HotelRegion, pk=pk)
    
    if request.method == 'POST':
        form = HotelRegionForm(request.POST, instance=region)
        if form.is_valid():
            form.save()
            messages.success(request, 'Bölge başarıyla güncellendi.')
            return redirect('hotels:region_list')
    else:
        form = HotelRegionForm(instance=region)
    
    context = {
        'form': form,
        'region': region,
    }
    
    return render(request, 'tenant/hotels/settings/regions/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def region_delete(request, pk):
    """Bölge silme"""
    region = get_object_or_404(HotelRegion, pk=pk)
    
    if request.method == 'POST':
        region.is_deleted = True
        region.save()
        messages.success(request, 'Bölge başarıyla silindi.')
        return redirect('hotels:region_list')
    
    context = {
        'region': region,
    }
    
    return render(request, 'tenant/hotels/settings/regions/delete.html', context)


# ==================== ŞEHİR YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def city_list(request):
    """Şehir listesi"""
    cities = HotelCity.objects.filter(is_deleted=False).select_related('region').order_by('region__name', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        cities = cities.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Filtreleme
    region_id = request.GET.get('region')
    if region_id:
        cities = cities.filter(region_id=region_id)
    
    # Sayfalama
    paginator = Paginator(cities, 20)
    page_number = request.GET.get('page', 1)
    cities = paginator.get_page(page_number)
    
    regions = HotelRegion.objects.filter(is_deleted=False, is_active=True).order_by('name')
    
    context = {
        'cities': cities,
        'regions': regions,
        'search': search,
        'selected_region': region_id,
    }
    
    return render(request, 'tenant/hotels/settings/cities/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def city_create(request):
    """Şehir ekleme"""
    if request.method == 'POST':
        form = HotelCityForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Şehir başarıyla eklendi.')
            return redirect('hotels:city_list')
    else:
        form = HotelCityForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/cities/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def city_update(request, pk):
    """Şehir düzenleme"""
    city = get_object_or_404(HotelCity, pk=pk)
    
    if request.method == 'POST':
        form = HotelCityForm(request.POST, instance=city)
        if form.is_valid():
            form.save()
            messages.success(request, 'Şehir başarıyla güncellendi.')
            return redirect('hotels:city_list')
    else:
        form = HotelCityForm(instance=city)
    
    context = {
        'form': form,
        'city': city,
    }
    
    return render(request, 'tenant/hotels/settings/cities/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def city_delete(request, pk):
    """Şehir silme"""
    city = get_object_or_404(HotelCity, pk=pk)
    
    if request.method == 'POST':
        city.is_deleted = True
        city.save()
        messages.success(request, 'Şehir başarıyla silindi.')
        return redirect('hotels:city_list')
    
    context = {
        'city': city,
    }
    
    return render(request, 'tenant/hotels/settings/cities/delete.html', context)


# ==================== OTEL TÜRÜ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def hotel_type_list(request):
    """Otel türü listesi"""
    hotel_types = HotelType.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        hotel_types = hotel_types.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(hotel_types, 20)
    page_number = request.GET.get('page', 1)
    hotel_types = paginator.get_page(page_number)
    
    context = {
        'hotel_types': hotel_types,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def hotel_type_create(request):
    """Otel türü ekleme"""
    if request.method == 'POST':
        form = HotelTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Otel türü başarıyla eklendi.')
            return redirect('hotels:hotel_type_list')
    else:
        form = HotelTypeForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def hotel_type_update(request, pk):
    """Otel türü düzenleme"""
    hotel_type = get_object_or_404(HotelType, pk=pk)
    
    if request.method == 'POST':
        form = HotelTypeForm(request.POST, instance=hotel_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Otel türü başarıyla güncellendi.')
            return redirect('hotels:hotel_type_list')
    else:
        form = HotelTypeForm(instance=hotel_type)
    
    context = {
        'form': form,
        'hotel_type': hotel_type,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def hotel_type_delete(request, pk):
    """Otel türü silme"""
    hotel_type = get_object_or_404(HotelType, pk=pk)
    
    if request.method == 'POST':
        hotel_type.is_deleted = True
        hotel_type.save()
        messages.success(request, 'Otel türü başarıyla silindi.')
        return redirect('hotels:hotel_type_list')
    
    context = {
        'hotel_type': hotel_type,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_types/delete.html', context)


# ==================== ODA TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def room_type_list(request):
    """Oda tipi listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_types = RoomType.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        room_types = room_types.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(room_types, 20)
    page_number = request.GET.get('page', 1)
    room_types = paginator.get_page(page_number)
    
    context = {
        'room_types': room_types,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_type_create(request):
    """Oda tipi ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = RoomTypeForm(request.POST, hotel=hotel)
        if form.is_valid():
            room_type = form.save(commit=False)
            room_type.hotel = hotel
            room_type.save()
            messages.success(request, 'Oda tipi başarıyla eklendi.')
            return redirect('hotels:room_type_list')
    else:
        form = RoomTypeForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_type_update(request, pk):
    """Oda tipi düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_type = get_object_or_404(RoomType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomTypeForm(request.POST, instance=room_type, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda tipi başarıyla güncellendi.')
            return redirect('hotels:room_type_list')
    else:
        form = RoomTypeForm(instance=room_type, hotel=hotel)
    
    context = {
        'form': form,
        'room_type': room_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_type_delete(request, pk):
    """Oda tipi silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_type = get_object_or_404(RoomType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        room_type.is_deleted = True
        room_type.save()
        messages.success(request, 'Oda tipi başarıyla silindi.')
        return redirect('hotels:room_type_list')
    
    context = {
        'room_type': room_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/delete.html', context)


# ==================== PANSİYON TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def board_type_list(request):
    """Pansiyon tipi listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    board_types = BoardType.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        board_types = board_types.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(board_types, 20)
    page_number = request.GET.get('page', 1)
    board_types = paginator.get_page(page_number)
    
    context = {
        'board_types': board_types,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def board_type_create(request):
    """Pansiyon tipi ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = BoardTypeForm(request.POST, hotel=hotel)
        if form.is_valid():
            board_type = form.save(commit=False)
            board_type.hotel = hotel
            board_type.save()
            messages.success(request, 'Pansiyon tipi başarıyla eklendi.')
            return redirect('hotels:board_type_list')
    else:
        form = BoardTypeForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def board_type_update(request, pk):
    """Pansiyon tipi düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    board_type = get_object_or_404(BoardType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = BoardTypeForm(request.POST, instance=board_type, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Pansiyon tipi başarıyla güncellendi.')
            return redirect('hotels:board_type_list')
    else:
        form = BoardTypeForm(instance=board_type, hotel=hotel)
    
    context = {
        'form': form,
        'board_type': board_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def board_type_delete(request, pk):
    """Pansiyon tipi silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    board_type = get_object_or_404(BoardType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        board_type.is_deleted = True
        board_type.save()
        messages.success(request, 'Pansiyon tipi başarıyla silindi.')
        return redirect('hotels:board_type_list')
    
    context = {
        'board_type': board_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/delete.html', context)


# ==================== YATAK TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def bed_type_list(request):
    """Yatak tipi listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    bed_types = BedType.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        bed_types = bed_types.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(bed_types, 20)
    page_number = request.GET.get('page', 1)
    bed_types = paginator.get_page(page_number)
    
    context = {
        'bed_types': bed_types,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def bed_type_create(request):
    """Yatak tipi ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = BedTypeForm(request.POST, hotel=hotel)
        if form.is_valid():
            bed_type = form.save(commit=False)
            bed_type.hotel = hotel
            bed_type.save()
            messages.success(request, 'Yatak tipi başarıyla eklendi.')
            return redirect('hotels:bed_type_list')
    else:
        form = BedTypeForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def bed_type_update(request, pk):
    """Yatak tipi düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    bed_type = get_object_or_404(BedType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = BedTypeForm(request.POST, instance=bed_type, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Yatak tipi başarıyla güncellendi.')
            return redirect('hotels:bed_type_list')
    else:
        form = BedTypeForm(instance=bed_type, hotel=hotel)
    
    context = {
        'form': form,
        'bed_type': bed_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def bed_type_delete(request, pk):
    """Yatak tipi silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    bed_type = get_object_or_404(BedType, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        bed_type.is_deleted = True
        bed_type.save()
        messages.success(request, 'Yatak tipi başarıyla silindi.')
        return redirect('hotels:bed_type_list')
    
    context = {
        'bed_type': bed_type,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/delete.html', context)


# ==================== ODA ÖZELLİKLERİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def room_feature_list(request):
    """Oda özelliği listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_features = RoomFeature.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        room_features = room_features.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(room_features, 20)
    page_number = request.GET.get('page', 1)
    room_features = paginator.get_page(page_number)
    
    context = {
        'room_features': room_features,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_feature_create(request):
    """Oda özelliği ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = RoomFeatureForm(request.POST, hotel=hotel)
        if form.is_valid():
            room_feature = form.save(commit=False)
            room_feature.hotel = hotel
            room_feature.save()
            messages.success(request, 'Oda özelliği başarıyla eklendi.')
            return redirect('hotels:room_feature_list')
    else:
        form = RoomFeatureForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_feature_update(request, pk):
    """Oda özelliği düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_feature = get_object_or_404(RoomFeature, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomFeatureForm(request.POST, instance=room_feature, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda özelliği başarıyla güncellendi.')
            return redirect('hotels:room_feature_list')
    else:
        form = RoomFeatureForm(instance=room_feature, hotel=hotel)
    
    context = {
        'form': form,
        'room_feature': room_feature,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_feature_delete(request, pk):
    """Oda özelliği silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_feature = get_object_or_404(RoomFeature, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        room_feature.is_deleted = True
        room_feature.save()
        messages.success(request, 'Oda özelliği başarıyla silindi.')
        return redirect('hotels:room_feature_list')
    
    context = {
        'room_feature': room_feature,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/delete.html', context)


# ==================== OTEL ÖZELLİKLERİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def hotel_feature_list(request):
    """Otel özelliği listesi"""
    hotel_features = HotelFeature.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        hotel_features = hotel_features.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search) |
            Q(category__icontains=search)
        )
    
    # Filtreleme
    category = request.GET.get('category')
    if category:
        hotel_features = hotel_features.filter(category=category)
    
    # Sayfalama
    paginator = Paginator(hotel_features, 20)
    page_number = request.GET.get('page', 1)
    hotel_features = paginator.get_page(page_number)
    
    categories = HotelFeature.objects.filter(is_deleted=False).values_list('category', flat=True).distinct()
    
    context = {
        'hotel_features': hotel_features,
        'search': search,
        'categories': categories,
        'selected_category': category,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_features/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def hotel_feature_create(request):
    """Otel özelliği ekleme"""
    if request.method == 'POST':
        form = HotelFeatureForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Otel özelliği başarıyla eklendi.')
            return redirect('hotels:hotel_feature_list')
    else:
        form = HotelFeatureForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_features/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def hotel_feature_update(request, pk):
    """Otel özelliği düzenleme"""
    hotel_feature = get_object_or_404(HotelFeature, pk=pk)
    
    if request.method == 'POST':
        form = HotelFeatureForm(request.POST, instance=hotel_feature)
        if form.is_valid():
            form.save()
            messages.success(request, 'Otel özelliği başarıyla güncellendi.')
            return redirect('hotels:hotel_feature_list')
    else:
        form = HotelFeatureForm(instance=hotel_feature)
    
    context = {
        'form': form,
        'hotel_feature': hotel_feature,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_features/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def hotel_feature_delete(request, pk):
    """Otel özelliği silme"""
    hotel_feature = get_object_or_404(HotelFeature, pk=pk)
    
    if request.method == 'POST':
        hotel_feature.is_deleted = True
        hotel_feature.save()
        messages.success(request, 'Otel özelliği başarıyla silindi.')
        return redirect('hotels:hotel_feature_list')
    
    context = {
        'hotel_feature': hotel_feature,
    }
    
    return render(request, 'tenant/hotels/settings/hotel_features/delete.html', context)


# ==================== KAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def floor_list(request):
    """
    Kat listesi
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    floors = Floor.objects.filter(hotel=hotel, is_deleted=False).order_by('floor_number')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        floors = floors.filter(
            Q(name__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(floors, 20)
    page_number = request.GET.get('page', 1)
    floors = paginator.get_page(page_number)
    
    context = {
        'floors': floors,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/floors/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def floor_create(request):
    """
    Kat ekleme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = FloorForm(request.POST)
        if form.is_valid():
            floor = form.save(commit=False)
            floor.hotel = hotel
            floor.save()
            messages.success(request, 'Kat başarıyla eklendi.')
            return redirect('hotels:floor_list')
    else:
        form = FloorForm()
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/floors/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def floor_update(request, pk):
    """
    Kat düzenleme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    floor = get_object_or_404(Floor, pk=pk, hotel=hotel)
    
    if request.method == 'POST':
        form = FloorForm(request.POST, instance=floor)
        if form.is_valid():
            form.save()
            messages.success(request, 'Kat başarıyla güncellendi.')
            return redirect('hotels:floor_list')
    else:
        form = FloorForm(instance=floor)
    
    context = {
        'form': form,
        'floor': floor,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/floors/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def floor_delete(request, pk):
    """
    Kat silme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    floor = get_object_or_404(Floor, pk=pk, hotel=hotel)
    
    if request.method == 'POST':
        floor.is_deleted = True
        floor.save()
        messages.success(request, 'Kat başarıyla silindi.')
        return redirect('hotels:floor_list')
    
    context = {
        'floor': floor,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/floors/delete.html', context)


# ==================== BLOK YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def block_list(request):
    """
    Blok listesi
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    blocks = Block.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        blocks = blocks.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(blocks, 20)
    page_number = request.GET.get('page', 1)
    blocks = paginator.get_page(page_number)
    
    context = {
        'blocks': blocks,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/blocks/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def block_create(request):
    """
    Blok ekleme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = BlockForm(request.POST)
        if form.is_valid():
            block = form.save(commit=False)
            block.hotel = hotel
            block.save()
            messages.success(request, 'Blok başarıyla eklendi.')
            return redirect('hotels:block_list')
    else:
        form = BlockForm()
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/blocks/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def block_update(request, pk):
    """
    Blok düzenleme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    block = get_object_or_404(Block, pk=pk, hotel=hotel)
    
    if request.method == 'POST':
        form = BlockForm(request.POST, instance=block)
        if form.is_valid():
            form.save()
            messages.success(request, 'Blok başarıyla güncellendi.')
            return redirect('hotels:block_list')
    else:
        form = BlockForm(instance=block)
    
    context = {
        'form': form,
        'block': block,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/blocks/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def block_delete(request, pk):
    """
    Blok silme
    """
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    block = get_object_or_404(Block, pk=pk, hotel=hotel)
    
    if request.method == 'POST':
        block.is_deleted = True
        block.save()
        messages.success(request, 'Blok başarıyla silindi.')
        return redirect('hotels:block_list')
    
    context = {
        'block': block,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/settings/blocks/delete.html', context)


# ==================== OTEL YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def hotel_list(request):
    """Otel listesi"""
    hotels = Hotel.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
    # Filtreleme
    region_id = request.GET.get('region')
    city_id = request.GET.get('city')
    hotel_type_id = request.GET.get('hotel_type')
    is_active_filter = request.GET.get('is_active')
    search = request.GET.get('search', '')
    
    if region_id:
        hotels = hotels.filter(region_id=region_id)
    if city_id:
        hotels = hotels.filter(city_id=city_id)
    if hotel_type_id:
        hotels = hotels.filter(hotel_type_id=hotel_type_id)
    if is_active_filter:
        hotels = hotels.filter(is_active=(is_active_filter == '1'))
    
    # Arama
    if search:
        hotels = hotels.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(hotels, 20)
    page_number = request.GET.get('page', 1)
    hotels = paginator.get_page(page_number)
    
    # Paket limiti kontrolü - Modül bazlı
    from apps.tenant_apps.subscriptions.views import get_usage_statistics
    from apps.subscriptions.models import Subscription
    from apps.packages.models import PackageModule
    
    stats = get_usage_statistics(request.tenant)
    current_hotels = stats.get('current_hotels', 0)
    
    # Aktif aboneliği al
    active_subscription = Subscription.objects.filter(
        tenant=request.tenant,
        status='active'
    ).first()
    
    if not active_subscription:
        active_subscription = Subscription.objects.filter(
            tenant=request.tenant
        ).order_by('-created_at').first()
    
    package = active_subscription.package if active_subscription else (request.tenant.package if hasattr(request.tenant, 'package') else None)
    
    # Modül bazlı limit kontrolü
    max_hotels = 0
    if package:
        try:
            hotels_module = PackageModule.objects.filter(
                package=package,
                module__code='hotels',
                is_enabled=True
            ).first()
            if hotels_module and hotels_module.limits:
                max_hotels = hotels_module.limits.get('max_hotels', 0)
        except:
            pass
    
    can_add_hotel = current_hotels < max_hotels if max_hotels > 0 else True
    
    # Filtreleme için gerekli veriler
    regions = HotelRegion.objects.filter(is_deleted=False, is_active=True).order_by('name')
    cities = HotelCity.objects.filter(is_deleted=False, is_active=True).order_by('name')
    hotel_types = HotelType.objects.filter(is_deleted=False, is_active=True).order_by('name')
    
    # Aktif otel (varsa)
    active_hotel = None
    if hasattr(request, 'active_hotel') and request.active_hotel:
        active_hotel = request.active_hotel
    
    context = {
        'hotels': hotels,
        'active_hotel': active_hotel,
        'search': search,
        'regions': regions,
        'cities': cities,
        'hotel_types': hotel_types,
        'max_hotels': max_hotels,
        'current_hotels': current_hotels,
        'can_add_hotel': can_add_hotel,
    }
    
    return render(request, 'tenant/hotels/hotels/list.html', context)


@login_required
@require_module_permission('hotels', 'view')
def hotel_detail(request, pk):
    """Otel detay"""
    hotel = get_object_or_404(Hotel, pk=pk, is_deleted=False)
    
    context = {
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/hotels/detail.html', context)


@login_required
@require_module_permission('hotels', 'add')
def hotel_create(request):
    """Otel ekleme"""
    # Paket limiti kontrolü
    from apps.tenant_apps.subscriptions.views import get_usage_statistics
    from apps.subscriptions.models import Subscription
    
    stats = get_usage_statistics(request.tenant)
    current_hotels = stats.get('current_hotels', 0)
    
    # Aktif aboneliği al
    active_subscription = Subscription.objects.filter(
        tenant=request.tenant,
        status='active'
    ).first()
    
    if not active_subscription:
        active_subscription = Subscription.objects.filter(
            tenant=request.tenant
        ).order_by('-created_at').first()
    
    package = active_subscription.package if active_subscription else (request.tenant.package if hasattr(request.tenant, 'package') else None)
    # Modül bazlı limit kontrolü
    max_hotels = 0
    if package:
        try:
            from apps.packages.models import PackageModule
            hotels_module = PackageModule.objects.filter(
                package=package,
                module__code='hotels',
                is_enabled=True
            ).first()
            if hotels_module and hotels_module.limits:
                max_hotels = hotels_module.limits.get('max_hotels', 0)
        except:
            pass
    
    # Limit kontrolü (sadece max_hotels > 0 ise kontrol et)
    if max_hotels > 0 and current_hotels >= max_hotels:
        messages.error(request, f'Paket limitiniz doldu. Maksimum {max_hotels} otel ekleyebilirsiniz.')
        return redirect('hotels:hotel_list')
    
    if request.method == 'POST':
        form = HotelForm(request.POST, request.FILES)
        if form.is_valid():
            hotel = form.save()
            messages.success(request, 'Otel başarıyla eklendi.')
            return redirect('hotels:hotel_detail', pk=hotel.pk)
    else:
        form = HotelForm()
    
    context = {
        'form': form,
        'max_hotels': max_hotels,
        'current_hotels': current_hotels,
        'gallery_images': [],  # Yeni otel için boş galeri
    }
    
    return render(request, 'tenant/hotels/hotels/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def hotel_update(request, pk):
    """Otel düzenleme"""
    hotel = get_object_or_404(Hotel, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = HotelForm(request.POST, request.FILES, instance=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Otel başarıyla güncellendi.')
            return redirect('hotels:hotel_detail', pk=hotel.pk)
    else:
        form = HotelForm(instance=hotel)
    
    # Galeri resimleri
    gallery_images = hotel.images.filter(is_deleted=False).order_by('sort_order', '-created_at')
    
    context = {
        'form': form,
        'hotel': hotel,
        'gallery_images': gallery_images,
    }
    
    return render(request, 'tenant/hotels/hotels/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def hotel_delete(request, pk):
    """Otel silme"""
    hotel = get_object_or_404(Hotel, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        hotel.is_deleted = True
        hotel.save()
        messages.success(request, 'Otel başarıyla silindi.')
        return redirect('hotels:hotel_list')
    
    context = {
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/hotels/delete.html', context)


# ==================== EKSTRA HİZMETLER ====================

@login_required
@require_module_permission('hotels', 'view')
@require_hotel_permission('view')
def extra_service_list(request):
    """Ekstra hizmetler listesi"""
    hotel = request.active_hotel
    extra_services = HotelExtraService.objects.filter(
        hotel=hotel,
        is_deleted=False
    ).order_by('sort_order', 'name')
    
    # Filtreleme
    is_active_filter = request.GET.get('is_active')
    search = request.GET.get('search', '')
    
    if is_active_filter:
        extra_services = extra_services.filter(is_active=(is_active_filter == '1'))
    
    # Arama
    if search:
        extra_services = extra_services.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(extra_services, 20)
    page_number = request.GET.get('page', 1)
    extra_services = paginator.get_page(page_number)
    
    context = {
        'extra_services': extra_services,
        'hotel': hotel,
        'search': search,
        'is_active_filter': is_active_filter,
    }
    
    return render(request, 'tenant/hotels/extra_services/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
@require_hotel_permission('manage')
def extra_service_create(request):
    """Ekstra hizmet ekleme"""
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = ExtraServiceForm(request.POST, hotel=hotel)
        if form.is_valid():
            extra_service = form.save(commit=False)
            extra_service.hotel = hotel
            extra_service.save()
            messages.success(request, 'Ekstra hizmet başarıyla eklendi.')
            return redirect('hotels:extra_service_list')
    else:
        form = ExtraServiceForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/extra_services/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
@require_hotel_permission('manage')
def extra_service_update(request, pk):
    """Ekstra hizmet düzenleme"""
    hotel = request.active_hotel
    extra_service = get_object_or_404(
        HotelExtraService,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        form = ExtraServiceForm(request.POST, instance=extra_service, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Ekstra hizmet başarıyla güncellendi.')
            return redirect('hotels:extra_service_list')
    else:
        form = ExtraServiceForm(instance=extra_service, hotel=hotel)
    
    context = {
        'form': form,
        'extra_service': extra_service,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/extra_services/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
@require_hotel_permission('manage')
def extra_service_delete(request, pk):
    """Ekstra hizmet silme"""
    hotel = request.active_hotel
    extra_service = get_object_or_404(
        HotelExtraService,
        pk=pk,
        hotel=hotel,
        is_deleted=False
    )
    
    if request.method == 'POST':
        extra_service.is_deleted = True
        extra_service.save()
        messages.success(request, 'Ekstra hizmet başarıyla silindi.')
        return redirect('hotels:extra_service_list')
    
    context = {
        'extra_service': extra_service,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/extra_services/delete.html', context)


# ==================== ODA YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def room_list(request):
    """Oda listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    rooms = Room.objects.filter(hotel=hotel, is_deleted=False).order_by('sort_order', 'name')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        rooms = rooms.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(rooms, 20)
    page_number = request.GET.get('page', 1)
    rooms = paginator.get_page(page_number)
    
    context = {
        'rooms': rooms,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/rooms/list.html', context)


@login_required
@require_module_permission('hotels', 'view')
def room_detail(request, pk):
    """Oda detay"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=pk, hotel=hotel, is_deleted=False)
    
    context = {
        'room': room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/detail.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_create(request):
    """Oda ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = RoomForm(request.POST, request.FILES, hotel=hotel)
        if form.is_valid():
            room = form.save(commit=False)
            room.hotel = hotel
            room.save()
            messages.success(request, 'Oda başarıyla eklendi.')
            return redirect('hotels:room_detail', pk=room.pk)
    else:
        form = RoomForm(hotel=hotel)
    
    context = {
        'form': form,
        'hotel': hotel,
        'gallery_images': [],  # Yeni oda için boş galeri
    }
    
    return render(request, 'tenant/hotels/rooms/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_update(request, pk):
    """Oda düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomForm(request.POST, request.FILES, instance=room, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda başarıyla güncellendi.')
            return redirect('hotels:room_detail', pk=room.pk)
    else:
        form = RoomForm(instance=room, hotel=hotel)
    
    # Galeri resimleri
    gallery_images = room.images.filter(is_deleted=False).order_by('sort_order', '-created_at')
    
    context = {
        'form': form,
        'room': room,
        'hotel': hotel,
        'gallery_images': gallery_images,
    }
    
    return render(request, 'tenant/hotels/rooms/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_delete(request, pk):
    """Oda silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        room.is_deleted = True
        room.save()
        messages.success(request, 'Oda başarıyla silindi.')
        return redirect('hotels:room_list')
    
    context = {
        'room': room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/delete.html', context)


# ==================== ODA FİYATLAMA ====================

@login_required
@require_module_permission('hotels', 'view')
def room_price_detail(request, room_id):
    """Oda fiyatlama detay"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=room_id, hotel=hotel, is_deleted=False)
    room_price = RoomPrice.objects.filter(room=room, is_deleted=False).first()
    
    # Fiyat alt modülleri
    seasonal_prices = []
    special_prices = []
    campaign_prices = []
    agency_prices = []
    channel_prices = []
    
    if room_price:
        seasonal_prices = RoomSeasonalPrice.objects.filter(room_price=room_price, is_deleted=False).order_by('start_date')
        special_prices = RoomSpecialPrice.objects.filter(room_price=room_price, is_deleted=False).order_by('start_date')
        campaign_prices = RoomCampaignPrice.objects.filter(room_price=room_price, is_deleted=False).order_by('start_date')
        agency_prices = RoomAgencyPrice.objects.filter(room_price=room_price, is_deleted=False).order_by('agency_name')
        channel_prices = RoomChannelPrice.objects.filter(room_price=room_price, is_deleted=False).order_by('channel_name')
    
    context = {
        'room': room,
        'room_price': room_price,
        'hotel': hotel,
        'seasonal_prices': seasonal_prices,
        'special_prices': special_prices,
        'campaign_prices': campaign_prices,
        'agency_prices': agency_prices,
        'channel_prices': channel_prices,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/detail.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_price_create(request, room_id):
    """Oda fiyatlama oluştur"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=room_id, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomPriceForm(request.POST, room=room)
        if form.is_valid():
            room_price = form.save(commit=False)
            room_price.room = room
            room_price.save()
            messages.success(request, 'Oda fiyatlandırması başarıyla oluşturuldu.')
            return redirect('hotels:room_price_detail', room_id=room.pk)
        else:
            # Form hatalarını göster
            for field, errors in form.errors.items():
                for error in errors:
                    messages.error(request, f'{field}: {error}')
    else:
        form = RoomPriceForm(room=room)
    
    context = {
        'form': form,
        'room': room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_price_update(request, room_id):
    """Oda fiyatlama güncelle"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room = get_object_or_404(Room, pk=room_id, hotel=hotel, is_deleted=False)
    room_price = get_object_or_404(RoomPrice, room=room, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomPriceForm(request.POST, instance=room_price, room=room)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda fiyatlandırması başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room.pk)
    else:
        form = RoomPriceForm(instance=room_price, room=room)
    
    context = {
        'form': form,
        'room': room,
        'room_price': room_price,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/form.html', context)


# ==================== ODA NUMARALARI ====================

@login_required
@require_module_permission('hotels', 'view')
def room_number_list(request):
    """Oda numarası listesi"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_numbers = RoomNumber.objects.filter(hotel=hotel, is_deleted=False).order_by('floor__floor_number', 'number')
    
    # Arama
    search = request.GET.get('search', '')
    if search:
        room_numbers = room_numbers.filter(
            Q(number__icontains=search) |
            Q(notes__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(room_numbers, 50)
    page_number = request.GET.get('page', 1)
    room_numbers = paginator.get_page(page_number)
    
    context = {
        'room_numbers': room_numbers,
        'hotel': hotel,
        'search': search,
    }
    
    return render(request, 'tenant/hotels/room_numbers/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_number_create(request):
    """Oda numarası ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = RoomNumberForm(request.POST, hotel=hotel)
        if form.is_valid():
            room_number = form.save(commit=False)
            room_number.hotel = hotel
            room_number.save()
            messages.success(request, 'Oda numarası başarıyla eklendi.')
            return redirect('hotels:room_number_list')
    else:
        form = RoomNumberForm(hotel=hotel)
    
    # Kat ve blok listesi
    floors = Floor.objects.filter(hotel=hotel, is_deleted=False).order_by('floor_number')
    blocks = Block.objects.filter(hotel=hotel, is_deleted=False).order_by('name')
    
    context = {
        'form': form,
        'hotel': hotel,
        'floors': floors,
        'blocks': blocks,
    }
    
    return render(request, 'tenant/hotels/room_numbers/form.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_number_bulk_create(request):
    """Toplu oda numarası ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    
    if request.method == 'POST':
        form = BulkRoomNumberForm(request.POST, hotel=hotel)
        if form.is_valid():
            floor = form.cleaned_data.get('floor')
            block = form.cleaned_data.get('block')
            start_number = form.cleaned_data['start_number']
            end_number = form.cleaned_data['end_number']
            room = form.cleaned_data.get('room')
            
            created_count = 0
            for num in range(start_number, end_number + 1):
                room_number_str = str(num).zfill(3)  # 001, 002, vb.
                
                # Zaten var mı kontrol et
                if not RoomNumber.objects.filter(hotel=hotel, number=room_number_str, is_deleted=False).exists():
                    RoomNumber.objects.create(
                        hotel=hotel,
                        floor=floor,
                        block=block,
                        number=room_number_str,
                        room=room,
                        is_active=True
                    )
                    created_count += 1
            
            messages.success(request, f'{created_count} oda numarası başarıyla eklendi.')
            return redirect('hotels:room_number_list')
    else:
        form = BulkRoomNumberForm(hotel=hotel)
    
    # Kat ve blok listesi
    floors = Floor.objects.filter(hotel=hotel, is_deleted=False).order_by('floor_number')
    blocks = Block.objects.filter(hotel=hotel, is_deleted=False).order_by('name')
    
    context = {
        'form': form,
        'hotel': hotel,
        'floors': floors,
        'blocks': blocks,
    }
    
    return render(request, 'tenant/hotels/room_numbers/bulk_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_number_update(request, pk):
    """Oda numarası düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_number = get_object_or_404(RoomNumber, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomNumberForm(request.POST, instance=room_number, hotel=hotel)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda numarası başarıyla güncellendi.')
            return redirect('hotels:room_number_list')
    else:
        form = RoomNumberForm(instance=room_number, hotel=hotel)
    
    # Kat ve blok listesi
    floors = Floor.objects.filter(hotel=hotel, is_deleted=False).order_by('floor_number')
    blocks = Block.objects.filter(hotel=hotel, is_deleted=False).order_by('name')
    
    context = {
        'form': form,
        'room_number': room_number,
        'hotel': hotel,
        'floors': floors,
        'blocks': blocks,
    }
    
    return render(request, 'tenant/hotels/room_numbers/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_number_delete(request, pk):
    """Oda numarası silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_number = get_object_or_404(RoomNumber, pk=pk, hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        room_number.is_deleted = True
        room_number.save()
        messages.success(request, 'Oda numarası başarıyla silindi.')
        return redirect('hotels:room_number_list')
    
    context = {
        'room_number': room_number,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/room_numbers/delete.html', context)


# ==================== SEZONLUK FİYAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'add')
def room_seasonal_price_create(request, room_price_id):
    """Sezonluk fiyat ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_price = get_object_or_404(RoomPrice, pk=room_price_id, room__hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomSeasonalPriceForm(request.POST)
        if form.is_valid():
            seasonal_price = form.save(commit=False)
            seasonal_price.room_price = room_price
            seasonal_price.save()
            messages.success(request, 'Sezonluk fiyat başarıyla eklendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomSeasonalPriceForm()
    
    context = {
        'form': form,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/seasonal_price_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_seasonal_price_update(request, pk):
    """Sezonluk fiyat düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    seasonal_price = get_object_or_404(RoomSeasonalPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = seasonal_price.room_price
    
    if request.method == 'POST':
        form = RoomSeasonalPriceForm(request.POST, instance=seasonal_price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Sezonluk fiyat başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomSeasonalPriceForm(instance=seasonal_price)
    
    context = {
        'form': form,
        'seasonal_price': seasonal_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/seasonal_price_form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_seasonal_price_delete(request, pk):
    """Sezonluk fiyat silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    seasonal_price = get_object_or_404(RoomSeasonalPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = seasonal_price.room_price
    
    if request.method == 'POST':
        seasonal_price.is_deleted = True
        seasonal_price.save()
        messages.success(request, 'Sezonluk fiyat başarıyla silindi.')
        return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    
    context = {
        'seasonal_price': seasonal_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/seasonal_price_delete.html', context)


# ==================== ÖZEL FİYAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'add')
def room_special_price_create(request, room_price_id):
    """Özel fiyat ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_price = get_object_or_404(RoomPrice, pk=room_price_id, room__hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomSpecialPriceForm(request.POST)
        if form.is_valid():
            special_price = form.save(commit=False)
            special_price.room_price = room_price
            special_price.save()
            messages.success(request, 'Özel fiyat başarıyla eklendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomSpecialPriceForm()
    
    context = {
        'form': form,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/special_price_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_special_price_update(request, pk):
    """Özel fiyat düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    special_price = get_object_or_404(RoomSpecialPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = special_price.room_price
    
    if request.method == 'POST':
        form = RoomSpecialPriceForm(request.POST, instance=special_price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Özel fiyat başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomSpecialPriceForm(instance=special_price)
    
    context = {
        'form': form,
        'special_price': special_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/special_price_form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_special_price_delete(request, pk):
    """Özel fiyat silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    special_price = get_object_or_404(RoomSpecialPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = special_price.room_price
    
    if request.method == 'POST':
        special_price.is_deleted = True
        special_price.save()
        messages.success(request, 'Özel fiyat başarıyla silindi.')
        return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    
    context = {
        'special_price': special_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/special_price_delete.html', context)


# ==================== KAMPANYA FİYAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'add')
def room_campaign_price_create(request, room_price_id):
    """Kampanya fiyat ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_price = get_object_or_404(RoomPrice, pk=room_price_id, room__hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomCampaignPriceForm(request.POST)
        if form.is_valid():
            campaign_price = form.save(commit=False)
            campaign_price.room_price = room_price
            campaign_price.save()
            messages.success(request, 'Kampanya fiyat başarıyla eklendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomCampaignPriceForm()
    
    context = {
        'form': form,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/campaign_price_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_campaign_price_update(request, pk):
    """Kampanya fiyat düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    campaign_price = get_object_or_404(RoomCampaignPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = campaign_price.room_price
    
    if request.method == 'POST':
        form = RoomCampaignPriceForm(request.POST, instance=campaign_price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Kampanya fiyat başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomCampaignPriceForm(instance=campaign_price)
    
    context = {
        'form': form,
        'campaign_price': campaign_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/campaign_price_form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_campaign_price_delete(request, pk):
    """Kampanya fiyat silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    campaign_price = get_object_or_404(RoomCampaignPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = campaign_price.room_price
    
    if request.method == 'POST':
        campaign_price.is_deleted = True
        campaign_price.save()
        messages.success(request, 'Kampanya fiyat başarıyla silindi.')
        return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    
    context = {
        'campaign_price': campaign_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/campaign_price_delete.html', context)


# ==================== ACENTE FİYAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'add')
def room_agency_price_create(request, room_price_id):
    """Acente fiyat ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_price = get_object_or_404(RoomPrice, pk=room_price_id, room__hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomAgencyPriceForm(request.POST)
        if form.is_valid():
            agency_price = form.save(commit=False)
            agency_price.room_price = room_price
            agency_price.save()
            messages.success(request, 'Acente fiyat başarıyla eklendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomAgencyPriceForm()
    
    context = {
        'form': form,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/agency_price_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_agency_price_update(request, pk):
    """Acente fiyat düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    agency_price = get_object_or_404(RoomAgencyPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = agency_price.room_price
    
    if request.method == 'POST':
        form = RoomAgencyPriceForm(request.POST, instance=agency_price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Acente fiyat başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomAgencyPriceForm(instance=agency_price)
    
    context = {
        'form': form,
        'agency_price': agency_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/agency_price_form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_agency_price_delete(request, pk):
    """Acente fiyat silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    agency_price = get_object_or_404(RoomAgencyPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = agency_price.room_price
    
    if request.method == 'POST':
        agency_price.is_deleted = True
        agency_price.save()
        messages.success(request, 'Acente fiyat başarıyla silindi.')
        return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    
    context = {
        'agency_price': agency_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/agency_price_delete.html', context)


# ==================== KANAL FİYAT YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'add')
def room_channel_price_create(request, room_price_id):
    """Kanal fiyat ekleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    room_price = get_object_or_404(RoomPrice, pk=room_price_id, room__hotel=hotel, is_deleted=False)
    
    if request.method == 'POST':
        form = RoomChannelPriceForm(request.POST)
        if form.is_valid():
            channel_price = form.save(commit=False)
            channel_price.room_price = room_price
            channel_price.save()
            messages.success(request, 'Kanal fiyat başarıyla eklendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomChannelPriceForm()
    
    context = {
        'form': form,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/channel_price_form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_channel_price_update(request, pk):
    """Kanal fiyat düzenleme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    channel_price = get_object_or_404(RoomChannelPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = channel_price.room_price
    
    if request.method == 'POST':
        form = RoomChannelPriceForm(request.POST, instance=channel_price)
        if form.is_valid():
            form.save()
            messages.success(request, 'Kanal fiyat başarıyla güncellendi.')
            return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    else:
        form = RoomChannelPriceForm(instance=channel_price)
    
    context = {
        'form': form,
        'channel_price': channel_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/channel_price_form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_channel_price_delete(request, pk):
    """Kanal fiyat silme"""
    # Aktif otel kontrolü
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    hotel = request.active_hotel
    channel_price = get_object_or_404(RoomChannelPrice, pk=pk, room_price__room__hotel=hotel, is_deleted=False)
    room_price = channel_price.room_price
    
    if request.method == 'POST':
        channel_price.is_deleted = True
        channel_price.save()
        messages.success(request, 'Kanal fiyat başarıyla silindi.')
        return redirect('hotels:room_price_detail', room_id=room_price.room.pk)
    
    context = {
        'channel_price': channel_price,
        'room_price': room_price,
        'room': room_price.room,
        'hotel': hotel,
    }
    
    return render(request, 'tenant/hotels/rooms/pricing/channel_price_delete.html', context)


# ==================== KULLANICI OTEL YETKİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'admin')
def user_hotel_permission_assign(request, user_id):
    """Kullanıcıya otel yetkisi atama"""
    from apps.tenant_apps.core.models import TenantUser
    
    tenant_user = get_object_or_404(TenantUser, pk=user_id, is_active=True)
    
    # Tüm otelleri al
    hotels = Hotel.objects.filter(is_deleted=False, is_active=True).order_by('name')
    
    # Kullanıcının mevcut yetkileri
    existing_permissions = HotelUserPermission.objects.filter(
        tenant_user=tenant_user,
        is_active=True
    ).values_list('hotel_id', flat=True)
    
    if request.method == 'POST':
        # Seçilen oteller ve yetki seviyeleri
        selected_hotels = request.POST.getlist('hotels')
        permission_levels = {}
        
        for hotel_id in selected_hotels:
            level = request.POST.get(f'permission_level_{hotel_id}', 'view')
            permission_levels[int(hotel_id)] = level
        
        # Mevcut yetkileri güncelle veya yeni ekle
        for hotel_id, level in permission_levels.items():
            hotel = get_object_or_404(Hotel, pk=hotel_id)
            HotelUserPermission.objects.update_or_create(
                tenant_user=tenant_user,
                hotel=hotel,
                defaults={
                    'permission_level': level,
                    'is_active': True,
                    'assigned_by': request.user,
                }
            )
        
        # Seçilmeyen otellerin yetkilerini kaldır
        for perm in HotelUserPermission.objects.filter(tenant_user=tenant_user, is_active=True):
            if perm.hotel_id not in permission_levels:
                perm.is_active = False
                perm.save()
        
        messages.success(request, f'{tenant_user.user.get_full_name()} kullanıcısına otel yetkileri başarıyla atandı.')
        return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    # Mevcut yetkileri al
    current_permissions = {}
    for perm in HotelUserPermission.objects.filter(tenant_user=tenant_user, is_active=True):
        current_permissions[perm.hotel_id] = perm.permission_level
    
    context = {
        'tenant_user': tenant_user,
        'hotels': hotels,
        'current_permissions': current_permissions,
    }
    
    return render(request, 'tenant/hotels/users/permission_assign.html', context)


@login_required
@require_module_permission('hotels', 'admin')
def user_hotel_permission_remove(request, user_id, hotel_id):
    """Kullanıcıdan otel yetkisini kaldırma"""
    from apps.tenant_apps.core.models import TenantUser
    
    tenant_user = get_object_or_404(TenantUser, pk=user_id, is_active=True)
    hotel = get_object_or_404(Hotel, pk=hotel_id, is_deleted=False)
    
    if request.method == 'POST':
        permission = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            hotel=hotel,
            is_active=True
        ).first()
        
        if permission:
            permission.is_active = False
            permission.save()
            messages.success(request, f'{hotel.name} oteli yetkisi kaldırıldı.')
        else:
            messages.error(request, 'Yetki bulunamadı.')
        
        return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    permission = HotelUserPermission.objects.filter(
        tenant_user=tenant_user,
        hotel=hotel,
        is_active=True
    ).first()
    
    context = {
        'tenant_user': tenant_user,
        'hotel': hotel,
        'permission': permission,
    }
    
    return render(request, 'tenant/hotels/users/permission_remove.html', context)


# ==================== API ENDPOINTS ====================

@login_required
def api_accessible_hotels(request):
    """Erişilebilir otelleri JSON olarak döndür (Modal için)"""
    from .models import Hotel, HotelUserPermission
    
    tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
    
    # Admin ise tüm otelleri göster
    is_admin = tenant_user.has_module_permission('hotels', 'admin')
    if is_admin:
        hotels = Hotel.objects.filter(is_active=True, is_deleted=False).order_by('sort_order', 'name')
    else:
        # Kullanıcının yetkili olduğu oteller
        hotel_ids = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).values_list('hotel_id', flat=True)
        hotels = Hotel.objects.filter(id__in=hotel_ids, is_active=True, is_deleted=False).order_by('sort_order', 'name')
    
    # Aktif otel ID'si
    active_hotel_id = request.session.get('active_hotel_id')
    
    hotels_data = []
    for hotel in hotels:
        hotels_data.append({
            'id': hotel.id,
            'name': hotel.name,
            'city': hotel.city.name if hotel.city else None,
            'region': hotel.region.name if hotel.region else None,
            'is_active': hotel.id == active_hotel_id if active_hotel_id else False,
        })
    
    return JsonResponse({'hotels': hotels_data})


@login_required
@require_module_permission('hotels', 'admin')
def api_module_limits(request):
    """Modül limitlerini JSON olarak döndür"""
    from apps.tenant_apps.subscriptions.views import get_usage_statistics
    from apps.subscriptions.models import Subscription
    from apps.packages.models import PackageModule
    
    tenant = request.tenant
    
    # Aktif abonelik
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not subscription:
        subscription = Subscription.objects.filter(
            tenant=tenant
        ).order_by('-created_at').first()
    
    package = subscription.package if subscription else None
    
    # Kullanım istatistikleri
    usage_stats = get_usage_statistics(tenant)
    
    # Modül limitleri
    limits = {}
    if package:
        # Hotels modülü
        hotels_module = PackageModule.objects.filter(
            package=package,
            module__code='hotels',
            is_enabled=True
        ).first()
        if hotels_module and hotels_module.limits:
            limits['hotels'] = hotels_module.limits
        
        # Tours modülü
        tours_module = PackageModule.objects.filter(
            package=package,
            module__code='tours',
            is_enabled=True
        ).first()
        if tours_module and tours_module.limits:
            limits['tours'] = tours_module.limits
    
    return JsonResponse({
        'limits': limits,
        'usage': usage_stats,
    })


# ==================== BULK İŞLEMLER ====================

@login_required
@require_module_permission('hotels', 'admin')
def bulk_hotel_permission_assign(request):
    """Toplu otel yetkisi atama"""
    from apps.tenant_apps.core.models import TenantUser
    from django.db import transaction
    
    if request.method == 'POST':
        user_ids = request.POST.getlist('user_ids')
        hotel_ids = request.POST.getlist('hotel_ids')
        permission_level = request.POST.get('permission_level', 'view')
        
        if not user_ids or not hotel_ids:
            messages.error(request, 'Lütfen en az bir kullanıcı ve bir otel seçin.')
            return redirect('tenant:user_list')
        
        users = TenantUser.objects.filter(id__in=user_ids, is_active=True)
        hotels = Hotel.objects.filter(id__in=hotel_ids, is_deleted=False, is_active=True)
        
        assigned_count = 0
        with transaction.atomic():
            for user in users:
                for hotel in hotels:
                    HotelUserPermission.objects.update_or_create(
                        tenant_user=user,
                        hotel=hotel,
                        defaults={
                            'permission_level': permission_level,
                            'is_active': True,
                            'assigned_by': request.user,
                        }
                    )
                    assigned_count += 1
        
        messages.success(request, f'{assigned_count} otel yetkisi başarıyla atandı.')
        return redirect('tenant:user_list')
    
    # GET isteği - form göster
    users = TenantUser.objects.filter(is_active=True).order_by('user__first_name', 'user__last_name')
    hotels = Hotel.objects.filter(is_deleted=False, is_active=True).order_by('name')
    
    context = {
        'users': users,
        'hotels': hotels,
    }
    
    return render(request, 'tenant/hotels/users/bulk_permission_assign.html', context)


@login_required
@require_module_permission('hotels', 'manage')
def room_type_copy(request, room_type_id):
    """Oda tipini başka bir otel'e kopyala"""
    from .models import RoomType
    
    if not hasattr(request, 'active_hotel') or not request.active_hotel:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    source_hotel = request.active_hotel
    source_room_type = get_object_or_404(RoomType, pk=room_type_id, hotel=source_hotel, is_deleted=False)
    
    if request.method == 'POST':
        target_hotel_id = request.POST.get('target_hotel_id')
        if not target_hotel_id:
            messages.error(request, 'Lütfen hedef otel seçin.')
            return redirect('hotels:room_type_list')
        
        target_hotel = get_object_or_404(Hotel, pk=target_hotel_id, is_deleted=False, is_active=True)
        
        # Aynı kodda oda tipi var mı kontrol et
        if RoomType.objects.filter(hotel=target_hotel, code=source_room_type.code, is_deleted=False).exists():
            messages.error(request, f'{target_hotel.name} otelinde "{source_room_type.code}" kodlu oda tipi zaten mevcut.')
            return redirect('hotels:room_type_list')
        
        # Oda tipini kopyala
        new_room_type = RoomType.objects.create(
            hotel=target_hotel,
            name=source_room_type.name,
            code=source_room_type.code,
            description=source_room_type.description,
            icon=source_room_type.icon,
            is_active=source_room_type.is_active,
            sort_order=source_room_type.sort_order,
        )
        
        messages.success(request, f'Oda tipi "{source_room_type.name}" {target_hotel.name} oteline kopyalandı.')
        return redirect('hotels:room_type_list')
    
    # GET isteği - form göster
    hotels = Hotel.objects.filter(is_deleted=False, is_active=True).exclude(id=source_hotel.id).order_by('name')
    
    context = {
        'room_type': source_room_type,
        'source_hotel': source_hotel,
        'hotels': hotels,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/copy.html', context)


# ==================== RAPORLAMA ====================

@login_required
@require_module_permission('hotels', 'view')
def hotel_usage_report(request, hotel_id=None):
    """Otel bazlı kullanım raporu"""
    from .models import Room, RoomNumber, RoomPrice
    from django.db.models import Count, Sum
    from datetime import datetime, timedelta
    
    if hotel_id:
        hotel = get_object_or_404(Hotel, pk=hotel_id, is_deleted=False)
    elif hasattr(request, 'active_hotel') and request.active_hotel:
        hotel = request.active_hotel
    else:
        messages.error(request, 'Lütfen önce bir otel seçin.')
        return redirect('hotels:select_hotel')
    
    # Tarih aralığı (varsayılan: son 30 gün)
    end_date = timezone.now().date()
    start_date = end_date - timedelta(days=30)
    
    if request.GET.get('start_date'):
        try:
            from datetime import datetime
            start_date = datetime.strptime(request.GET.get('start_date'), '%Y-%m-%d').date()
        except:
            pass
    
    if request.GET.get('end_date'):
        try:
            from datetime import datetime
            end_date = datetime.strptime(request.GET.get('end_date'), '%Y-%m-%d').date()
        except:
            pass
    
    # İstatistikler
    stats = {
        'total_rooms': Room.objects.filter(hotel=hotel, is_deleted=False).count(),
        'active_rooms': Room.objects.filter(hotel=hotel, is_deleted=False, is_active=True).count(),
        'total_room_numbers': RoomNumber.objects.filter(hotel=hotel, is_deleted=False).count(),
        'active_room_numbers': RoomNumber.objects.filter(hotel=hotel, is_deleted=False, is_active=True).count(),
        'total_room_types': RoomType.objects.filter(hotel=hotel, is_deleted=False).count(),
        'total_board_types': BoardType.objects.filter(hotel=hotel, is_deleted=False).count(),
        'total_bed_types': BedType.objects.filter(hotel=hotel, is_deleted=False).count(),
        'total_room_features': RoomFeature.objects.filter(hotel=hotel, is_deleted=False).count(),
        'rooms_with_pricing': RoomPrice.objects.filter(room__hotel=hotel, is_deleted=False).values('room').distinct().count(),
    }
    
    # Oda tipi dağılımı
    room_type_distribution = Room.objects.filter(
        hotel=hotel, is_deleted=False
    ).values('room_type__name').annotate(
        count=Count('id')
    ).order_by('-count')
    
    # Fiyatlandırma istatistikleri
    pricing_stats = RoomPrice.objects.filter(
        room__hotel=hotel,
        is_deleted=False,
        is_active=True
    ).aggregate(
        avg_price=Avg('basic_nightly_price'),
        min_price=Min('basic_nightly_price'),
        max_price=Max('basic_nightly_price'),
    )
    
    context = {
        'hotel': hotel,
        'stats': stats,
        'room_type_distribution': room_type_distribution,
        'pricing_stats': pricing_stats,
        'start_date': start_date,
        'end_date': end_date,
    }
    
    return render(request, 'tenant/hotels/reports/usage.html', context)


# ==================== GALERİ YÖNETİMİ (AJAX) ====================

@login_required
@require_module_permission('hotels', 'edit')
@require_http_methods(["POST"])
def api_hotel_image_upload(request, hotel_id):
    """Otel resmi yükle (AJAX)"""
    hotel = get_object_or_404(Hotel, pk=hotel_id, is_deleted=False)
    
    if 'images' not in request.FILES:
        return JsonResponse({'success': False, 'error': 'Resim dosyası bulunamadı'}, status=400)
    
    uploaded_images = []
    errors = []
    
    for image_file in request.FILES.getlist('images'):
        try:
            hotel_image = HotelImage.objects.create(
                hotel=hotel,
                image=image_file,
                title=image_file.name,
                is_active=True,
                sort_order=hotel.images.count()
            )
            uploaded_images.append({
                'id': hotel_image.pk,
                'url': hotel_image.image.url,
                'title': hotel_image.title,
                'sort_order': hotel_image.sort_order,
            })
        except Exception as e:
            errors.append(f'{image_file.name}: {str(e)}')
    
    return JsonResponse({
        'success': len(uploaded_images) > 0,
        'images': uploaded_images,
        'errors': errors,
    })


@login_required
@require_module_permission('hotels', 'edit')
@require_http_methods(["POST"])
def api_hotel_image_delete(request, pk):
    """Otel resmi sil (AJAX)"""
    hotel_image = get_object_or_404(HotelImage, pk=pk, is_deleted=False)
    hotel_image.is_deleted = True
    hotel_image.save()
    
    return JsonResponse({'success': True})


@login_required
@require_module_permission('hotels', 'edit')
@require_http_methods(["POST"])
def api_hotel_image_update(request, pk):
    """Otel resmi güncelle (AJAX)"""
    hotel_image = get_object_or_404(HotelImage, pk=pk, is_deleted=False)
    
    hotel_image.title = request.POST.get('title', hotel_image.title)
    hotel_image.description = request.POST.get('description', hotel_image.description)
    hotel_image.is_active = request.POST.get('is_active', 'false') == 'true'
    hotel_image.save()
    
    return JsonResponse({
        'success': True,
        'image': {
            'id': hotel_image.pk,
            'title': hotel_image.title,
            'description': hotel_image.description,
            'is_active': hotel_image.is_active,
        }
    })


@login_required
@require_module_permission('hotels', 'edit')
@require_http_methods(["POST"])
def api_hotel_images_reorder(request):
    """Otel resimlerini yeniden sırala (AJAX)"""
    import json
    image_ids = json.loads(request.body).get('image_ids', [])
    
    for index, image_id in enumerate(image_ids):
        HotelImage.objects.filter(pk=image_id, is_deleted=False).update(sort_order=index)
    
    return JsonResponse({'success': True})


@login_required
@require_module_permission('hotels', 'edit')
@require_hotel_permission('manage')
@require_http_methods(["POST"])
def api_room_image_upload(request, room_id):
    """Oda resmi yükle (AJAX)"""
    room = get_object_or_404(Room, pk=room_id, is_deleted=False)
    
    if 'images' not in request.FILES:
        return JsonResponse({'success': False, 'error': 'Resim dosyası bulunamadı'}, status=400)
    
    uploaded_images = []
    errors = []
    
    for image_file in request.FILES.getlist('images'):
        try:
            room_image = RoomImage.objects.create(
                room=room,
                image=image_file,
                title=image_file.name,
                is_active=True,
                sort_order=room.images.count()
            )
            uploaded_images.append({
                'id': room_image.pk,
                'url': room_image.image.url,
                'title': room_image.title,
                'sort_order': room_image.sort_order,
            })
        except Exception as e:
            errors.append(f'{image_file.name}: {str(e)}')
    
    return JsonResponse({
        'success': len(uploaded_images) > 0,
        'images': uploaded_images,
        'errors': errors,
    })


@login_required
@require_module_permission('hotels', 'edit')
@require_hotel_permission('manage')
@require_http_methods(["POST"])
def api_room_image_delete(request, pk):
    """Oda resmi sil (AJAX)"""
    room_image = get_object_or_404(RoomImage, pk=pk, is_deleted=False)
    room_image.is_deleted = True
    room_image.save()
    
    return JsonResponse({'success': True})


@login_required
@require_module_permission('hotels', 'edit')
@require_hotel_permission('manage')
@require_http_methods(["POST"])
def api_room_image_update(request, pk):
    """Oda resmi güncelle (AJAX)"""
    room_image = get_object_or_404(RoomImage, pk=pk, is_deleted=False)
    
    room_image.title = request.POST.get('title', room_image.title)
    room_image.description = request.POST.get('description', room_image.description)
    room_image.is_active = request.POST.get('is_active', 'false') == 'true'
    room_image.save()
    
    return JsonResponse({
        'success': True,
        'image': {
            'id': room_image.pk,
            'title': room_image.title,
            'description': room_image.description,
            'is_active': room_image.is_active,
        }
    })


@login_required
@require_module_permission('hotels', 'edit')
@require_hotel_permission('manage')
@require_http_methods(["POST"])
def api_room_images_reorder(request):
    """Oda resimlerini yeniden sırala (AJAX)"""
    import json
    image_ids = json.loads(request.body).get('image_ids', [])
    
    for index, image_id in enumerate(image_ids):
        RoomImage.objects.filter(pk=image_id, is_deleted=False).update(sort_order=index)
    
    return JsonResponse({'success': True})

