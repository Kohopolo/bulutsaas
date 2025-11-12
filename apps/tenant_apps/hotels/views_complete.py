"""
Otel Yönetimi Views - TAM VERSİYON
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
    room_types = RoomType.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
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
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_type_create(request):
    """Oda tipi ekleme"""
    if request.method == 'POST':
        form = RoomTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda tipi başarıyla eklendi.')
            return redirect('hotels:room_type_list')
    else:
        form = RoomTypeForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_type_update(request, pk):
    """Oda tipi düzenleme"""
    room_type = get_object_or_404(RoomType, pk=pk)
    
    if request.method == 'POST':
        form = RoomTypeForm(request.POST, instance=room_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda tipi başarıyla güncellendi.')
            return redirect('hotels:room_type_list')
    else:
        form = RoomTypeForm(instance=room_type)
    
    context = {
        'form': form,
        'room_type': room_type,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_type_delete(request, pk):
    """Oda tipi silme"""
    room_type = get_object_or_404(RoomType, pk=pk)
    
    if request.method == 'POST':
        room_type.is_deleted = True
        room_type.save()
        messages.success(request, 'Oda tipi başarıyla silindi.')
        return redirect('hotels:room_type_list')
    
    context = {
        'room_type': room_type,
    }
    
    return render(request, 'tenant/hotels/settings/room_types/delete.html', context)


# ==================== PANSİYON TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def board_type_list(request):
    """Pansiyon tipi listesi"""
    board_types = BoardType.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
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
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def board_type_create(request):
    """Pansiyon tipi ekleme"""
    if request.method == 'POST':
        form = BoardTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Pansiyon tipi başarıyla eklendi.')
            return redirect('hotels:board_type_list')
    else:
        form = BoardTypeForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def board_type_update(request, pk):
    """Pansiyon tipi düzenleme"""
    board_type = get_object_or_404(BoardType, pk=pk)
    
    if request.method == 'POST':
        form = BoardTypeForm(request.POST, instance=board_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Pansiyon tipi başarıyla güncellendi.')
            return redirect('hotels:board_type_list')
    else:
        form = BoardTypeForm(instance=board_type)
    
    context = {
        'form': form,
        'board_type': board_type,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def board_type_delete(request, pk):
    """Pansiyon tipi silme"""
    board_type = get_object_or_404(BoardType, pk=pk)
    
    if request.method == 'POST':
        board_type.is_deleted = True
        board_type.save()
        messages.success(request, 'Pansiyon tipi başarıyla silindi.')
        return redirect('hotels:board_type_list')
    
    context = {
        'board_type': board_type,
    }
    
    return render(request, 'tenant/hotels/settings/board_types/delete.html', context)


# ==================== YATAK TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def bed_type_list(request):
    """Yatak tipi listesi"""
    bed_types = BedType.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
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
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def bed_type_create(request):
    """Yatak tipi ekleme"""
    if request.method == 'POST':
        form = BedTypeForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Yatak tipi başarıyla eklendi.')
            return redirect('hotels:bed_type_list')
    else:
        form = BedTypeForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def bed_type_update(request, pk):
    """Yatak tipi düzenleme"""
    bed_type = get_object_or_404(BedType, pk=pk)
    
    if request.method == 'POST':
        form = BedTypeForm(request.POST, instance=bed_type)
        if form.is_valid():
            form.save()
            messages.success(request, 'Yatak tipi başarıyla güncellendi.')
            return redirect('hotels:bed_type_list')
    else:
        form = BedTypeForm(instance=bed_type)
    
    context = {
        'form': form,
        'bed_type': bed_type,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def bed_type_delete(request, pk):
    """Yatak tipi silme"""
    bed_type = get_object_or_404(BedType, pk=pk)
    
    if request.method == 'POST':
        bed_type.is_deleted = True
        bed_type.save()
        messages.success(request, 'Yatak tipi başarıyla silindi.')
        return redirect('hotels:bed_type_list')
    
    context = {
        'bed_type': bed_type,
    }
    
    return render(request, 'tenant/hotels/settings/bed_types/delete.html', context)


# ==================== ODA ÖZELLİKLERİ YÖNETİMİ ====================

@login_required
@require_module_permission('hotels', 'view')
def room_feature_list(request):
    """Oda özelliği listesi"""
    room_features = RoomFeature.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
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
        'search': search,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/list.html', context)


@login_required
@require_module_permission('hotels', 'add')
def room_feature_create(request):
    """Oda özelliği ekleme"""
    if request.method == 'POST':
        form = RoomFeatureForm(request.POST)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda özelliği başarıyla eklendi.')
            return redirect('hotels:room_feature_list')
    else:
        form = RoomFeatureForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/form.html', context)


@login_required
@require_module_permission('hotels', 'edit')
def room_feature_update(request, pk):
    """Oda özelliği düzenleme"""
    room_feature = get_object_or_404(RoomFeature, pk=pk)
    
    if request.method == 'POST':
        form = RoomFeatureForm(request.POST, instance=room_feature)
        if form.is_valid():
            form.save()
            messages.success(request, 'Oda özelliği başarıyla güncellendi.')
            return redirect('hotels:room_feature_list')
    else:
        form = RoomFeatureForm(instance=room_feature)
    
    context = {
        'form': form,
        'room_feature': room_feature,
    }
    
    return render(request, 'tenant/hotels/settings/room_features/form.html', context)


@login_required
@require_module_permission('hotels', 'delete')
def room_feature_delete(request, pk):
    """Oda özelliği silme"""
    room_feature = get_object_or_404(RoomFeature, pk=pk)
    
    if request.method == 'POST':
        room_feature.is_deleted = True
        room_feature.save()
        messages.success(request, 'Oda özelliği başarıyla silindi.')
        return redirect('hotels:room_feature_list')
    
    context = {
        'room_feature': room_feature,
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
# NOT: Bu view'lar henüz eklenmedi, placeholder olarak bırakıldı
# Otel, Oda, Fiyatlama ve Oda Numaraları view'ları eklenecek

