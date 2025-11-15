"""
Otel Yönetimi Views
Profesyonel otel yönetim sistemi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.db.models import Q, Count, Sum, Avg
from django.core.paginator import Paginator
from django.utils import timezone
from decimal import Decimal

from .models import (
    # Otel Ayarları
    HotelRegion, HotelCity, HotelType, RoomType, BoardType, BedType,
    RoomFeature, HotelFeature,
    # Otel
    Hotel, HotelImage,
    # Oda
    Room, RoomImage,
    # Fiyatlama
    RoomPrice, RoomSeasonalPrice, RoomSpecialPrice, RoomCampaignPrice,
    RoomAgencyPrice, RoomChannelPrice,
    # Oda Numaraları
    Floor, Block, RoomNumber, RoomNumberStatus,
)
from .forms import (
    HotelRegionForm, HotelCityForm, HotelTypeForm, RoomTypeForm, BoardTypeForm,
    BedTypeForm, RoomFeatureForm, HotelFeatureForm,
    HotelForm, HotelImageForm,
    RoomForm, RoomImageForm,
    RoomPriceForm, RoomSeasonalPriceForm, RoomSpecialPriceForm,
    RoomCampaignPriceForm, RoomAgencyPriceForm, RoomChannelPriceForm,
    FloorForm, BlockForm, RoomNumberForm, BulkRoomNumberForm,
)
from apps.tenant_apps.core.decorators import require_module_permission
from apps.tenant_apps.core.models import TenantUser


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
    """Otel değiştir"""
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
                messages.error(request, 'Bu otel için yetkiniz yok.')
                return redirect('hotels:select_hotel')
        
        request.session['active_hotel_id'] = hotel.pk
        messages.success(request, f'{hotel.name} oteline geçildi.')
    except Hotel.DoesNotExist:
        messages.error(request, 'Otel bulunamadı.')
    
    return redirect('hotels:hotel_list')


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
        'room_types': RoomType.objects.filter(is_deleted=False).count(),
        'board_types': BoardType.objects.filter(is_deleted=False).count(),
        'bed_types': BedType.objects.filter(is_deleted=False).count(),
        'room_features': RoomFeature.objects.filter(is_deleted=False).count(),
        'hotel_features': HotelFeature.objects.filter(is_deleted=False).count(),
    }
    
    return render(request, 'tenant/hotels/settings/list.html', context)


# Bölge, Şehir, Otel Türü, Oda Tipi, Pansiyon Tipi, Yatak Tipi, Oda Özellikleri, Otel Özellikleri
# view'ları mevcut (region_list, region_create, vb.) - Bunlar zaten var, sadece import'lar eksikti

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

# NOT: Diğer view'lar (city_list, hotel_type_list, hotel_list, room_list, vb.) benzer şekilde eklenecek
# Şimdilik en kritik olanları ekledik. Kalan view'ları da ekleyeceğiz.
