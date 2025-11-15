"""
VB Theme Views
ElektraWeb Desktop Application Style Views
"""

from django.shortcuts import render
from django.contrib.auth.decorators import login_required
from django.utils import timezone
from datetime import datetime, timedelta

@login_required
def dashboard(request):
    """
    Dashboard - Ana Sayfa
    """
    # Mock data for demonstration
    stats = {
        'total_rooms': 120,
        'vacant_rooms': 34,
        'occupied_rooms': 78,
        'occupancy_rate': 65,
        'checkins_today': 12,
        'checkouts_today': 8,
        'revenue_today': '24.5K',
        'dirty_rooms': 15,
        'pending_reservations': 23,
    }
    
    # Mock check-ins today
    checkins_today = [
        {'time': '14:00', 'guest_name': 'Ahmet Yılmaz', 'room_number': '1101', 'completed': False},
        {'time': '14:30', 'guest_name': 'Mehmet Demir', 'room_number': '1102', 'completed': True},
        {'time': '15:00', 'guest_name': 'Ayşe Kaya', 'room_number': '1103', 'completed': False},
        {'time': '16:00', 'guest_name': 'Fatma Şahin', 'room_number': '2101', 'completed': False},
        {'time': '16:30', 'guest_name': 'Ali Çelik', 'room_number': '2102', 'completed': True},
    ]
    
    # Mock check-outs today
    checkouts_today = [
        {'time': '12:00', 'guest_name': 'Serdar Karakoç', 'room_number': '1106', 'completed': True},
        {'time': '12:00', 'guest_name': 'Abdulvahap Toraman', 'room_number': '1103', 'completed': False},
        {'time': '12:00', 'guest_name': 'İlhan Küçükçınar', 'room_number': '1105', 'completed': False},
        {'time': '11:00', 'guest_name': 'Metin Kaçar', 'room_number': '1109', 'completed': True},
    ]
    
    # Mock recent reservations
    recent_reservations = [
        {
            'id': 1,
            'code': 'ETSR195094449',
            'guest_name': 'Ahmet Yılmaz',
            'room_number': '1101',
            'check_in': timezone.now(),
            'check_out': timezone.now() + timedelta(days=3),
            'total_amount': '16,779.00',
            'status': 'confirmed'
        },
        {
            'id': 2,
            'code': 'ETSR195094450',
            'guest_name': 'Mehmet Demir',
            'room_number': '1102',
            'check_in': timezone.now() + timedelta(days=1),
            'check_out': timezone.now() + timedelta(days=4),
            'total_amount': '22,350.00',
            'status': 'pending'
        },
        {
            'id': 3,
            'code': 'ETSR195094451',
            'guest_name': 'Ayşe Kaya',
            'room_number': '2101',
            'check_in': timezone.now() - timedelta(days=2),
            'check_out': timezone.now() + timedelta(days=1),
            'total_amount': '18,900.00',
            'status': 'checkedin'
        },
    ]
    
    context = {
        'stats': stats,
        'checkins_today': checkins_today,
        'checkouts_today': checkouts_today,
        'recent_reservations': recent_reservations,
    }
    
    return render(request, 'vb_theme/pages/dashboard.html', context)


@login_required
def rezervasyon_list(request):
    """
    Rezervasyon Listesi
    """
    # Mock reservations data
    reservations = [
        {
            'id': 1,
            'code': 'ETSR195094449',
            'room_number': '1102',
            'room_type': 'Temiz(Öce)',
            'guest_name': 'Serdar Karakoç',
            'guest_phone': '+90 555 123 4567',
            'check_in': datetime(2025, 8, 31, 14, 0),
            'check_out': datetime(2025, 9, 3, 12, 0),
            'adults': 3,
            'children': 0,
            'total_amount': '75,623.094',
            'status': 'confirmed',
            'channel': 'TATILIMO',
            'is_today_checkin': False,
            'is_today_checkout': False,
        },
        {
            'id': 2,
            'code': 'ETSR195094450',
            'room_number': '1103',
            'room_type': 'STD',
            'guest_name': 'Abdulvahap Toraman',
            'guest_phone': '+90 555 234 5678',
            'check_in': datetime(2025, 8, 29, 14, 0),
            'check_out': datetime(2025, 9, 1, 12, 0),
            'adults': 2,
            'children': 0,
            'total_amount': '80,144.707',
            'status': 'checkedin',
            'channel': 'EXEN TATIL',
            'is_today_checkin': False,
            'is_today_checkout': False,
        },
        {
            'id': 3,
            'code': 'ETSR195094451',
            'room_number': '1106',
            'room_type': 'Temiz(Öce)',
            'guest_name': 'İlhan Küçükçınar',
            'guest_phone': '+90 555 345 6789',
            'check_in': datetime(2025, 8, 31, 14, 0),
            'check_out': datetime(2025, 9, 4, 12, 0),
            'adults': 2,
            'children': 2,
            'total_amount': '82,657.062',
            'status': 'confirmed',
            'channel': 'Direct',
            'is_today_checkin': True,
            'is_today_checkout': False,
        },
        {
            'id': 4,
            'code': 'ETSR195094452',
            'room_number': '1109',
            'room_type': 'ECO ROOM',
            'guest_name': 'Metin Kaçar',
            'guest_phone': '+90 555 456 7890',
            'check_in': datetime(2025, 8, 31, 14, 0),
            'check_out': datetime(2025, 9, 3, 12, 0),
            'adults': 3,
            'children': 1,
            'total_amount': '74,283.436',
            'status': 'pending',
            'channel': 'Booking.com',
            'is_today_checkin': False,
            'is_today_checkout': True,
        },
        {
            'id': 5,
            'code': 'ETSR195094453',
            'room_number': '1211',
            'room_type': 'STD',
            'guest_name': 'Leyla Erbil',
            'guest_phone': None,
            'check_in': datetime(2025, 8, 25, 14, 0),
            'check_out': datetime(2025, 9, 1, 12, 0),
            'adults': 2,
            'children': 0,
            'total_amount': '79,626.706',
            'status': 'checkedout',
            'channel': 'Direct',
            'is_today_checkin': False,
            'is_today_checkout': False,
        },
    ]
    
    # Summary stats
    summary = {
        'total': len(reservations),
        'confirmed': sum(1 for r in reservations if r['status'] == 'confirmed'),
        'pending': sum(1 for r in reservations if r['status'] == 'pending'),
        'cancelled': 0,
        'total_revenue': sum(float(r['total_amount'].replace(',', '').replace('.', '')) / 1000 for r in reservations),
    }
    
    # Pagination mock
    pagination = {
        'total': 50,
        'start': 1,
        'end': len(reservations),
        'current': 1,
        'total_pages': 10,
        'has_previous': False,
        'has_next': True,
        'previous': None,
        'next': 2,
    }
    
    context = {
        'reservations': reservations,
        'summary': summary,
        'pagination': pagination,
    }
    
    return render(request, 'vb_theme/pages/rezervasyon-list.html', context)


@login_required
def room_rack(request):
    """
    Room Rack - Oda Durumu
    """
    # Mock rooms data
    rooms = [
        {
            'id': 1,
            'number': '1101',
            'room_type': 'Double + 2 Single',
            'status': 'vacant',
            'cleaning_status': 'CLEAN',
            'occupied': False,
            'reserved': False,
            'selected': False,
            'show_footer': True,
            'price': 2500,
            'next_reservation': datetime(2025, 9, 5, 14, 0),
        },
        {
            'id': 2,
            'number': '1102',
            'room_type': 'Double + Single',
            'status': 'occupied',
            'cleaning_status': 'CLEAN',
            'occupied': True,
            'reserved': False,
            'selected': False,
            'guest_name': 'Serdar Karakoç',
            'check_in': datetime(2025, 8, 31, 14, 0),
            'check_out': datetime(2025, 9, 3, 12, 0),
            'guests_count': 3,
            'children_count': 0,
            'reservation_note': 'TATILIMO',
            'show_footer': False,
        },
        {
            'id': 3,
            'number': '1103',
            'room_type': 'STD',
            'status': 'occupied',
            'cleaning_status': 'DIRTY',
            'occupied': True,
            'reserved': False,
            'selected': False,
            'guest_name': 'Abdulvahap Toraman',
            'check_in': datetime(2025, 8, 29, 14, 0),
            'check_out': datetime(2025, 9, 1, 12, 0),
            'guests_count': 2,
            'children_count': 0,
            'reservation_note': 'EXEN TATIL',
            'show_footer': False,
        },
        {
            'id': 4,
            'number': '1104',
            'room_type': 'STD',
            'status': 'vacant',
            'cleaning_status': 'DIRTY',
            'occupied': False,
            'reserved': False,
            'selected': False,
            'show_footer': True,
            'price': 2200,
        },
        {
            'id': 5,
            'number': '1105',
            'room_type': 'Double + Single',
            'status': 'reserved',
            'cleaning_status': 'CLEAN',
            'occupied': False,
            'reserved': True,
            'selected': False,
            'guest_name': 'Metin Kaçar',
            'check_in': datetime(2025, 9, 3, 14, 0),
            'reservation_code': 'ETSR195094452',
            'show_footer': False,
        },
        {
            'id': 6,
            'number': '1106',
            'room_type': 'Temiz(Öce)',
            'status': 'occupied',
            'cleaning_status': 'CLEAN',
            'occupied': True,
            'reserved': False,
            'selected': False,
            'guest_name': 'İlhan Küçükçınar',
            'check_in': datetime(2025, 8, 31, 14, 0),
            'check_out': datetime(2025, 9, 4, 12, 0),
            'guests_count': 2,
            'children_count': 2,
            'show_footer': False,
        },
        {
            'id': 7,
            'number': '1107',
            'room_type': 'Double + Single',
            'status': 'vacant',
            'cleaning_status': 'CLEAN',
            'occupied': False,
            'reserved': False,
            'selected': False,
            'show_footer': True,
            'price': 2400,
        },
        {
            'id': 8,
            'number': '1108',
            'room_type': 'SSV',
            'status': 'vacant',
            'cleaning_status': 'CLEAN',
            'occupied': False,
            'reserved': False,
            'selected': False,
            'show_footer': True,
            'price': 3000,
        },
    ]
    
    # Room types for filter
    room_types = [
        {'id': 'std', 'name': 'STD'},
        {'id': 'dlx', 'name': 'DLX'},
        {'id': 'suite', 'name': 'SUITE'},
        {'id': 'family', 'name': 'FAMILY'},
    ]
    
    # Stats
    stats = {
        'total': len(rooms),
        'vacant': sum(1 for r in rooms if r['status'] == 'vacant'),
        'occupied': sum(1 for r in rooms if r['status'] == 'occupied'),
        'clean': sum(1 for r in rooms if r['cleaning_status'] == 'CLEAN'),
        'dirty': sum(1 for r in rooms if r['cleaning_status'] == 'DIRTY'),
        'occupancy': int((sum(1 for r in rooms if r['status'] == 'occupied') / len(rooms)) * 100),
    }
    
    context = {
        'rooms': rooms,
        'room_types': room_types,
        'stats': stats,
    }
    
    return render(request, 'vb_theme/pages/room-rack.html', context)


