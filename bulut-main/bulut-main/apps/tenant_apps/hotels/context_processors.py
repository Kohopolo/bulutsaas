"""
Otel Context Processors
Template'lerde kullanılacak otel bilgileri
"""
from .models import Hotel


def hotel_context(request):
    """
    Template'lerde kullanılacak otel bilgileri
    """
    context = {
        'active_hotel': None,
        'accessible_hotels': [],
        'can_switch_hotel': False,
    }
    
    if hasattr(request, 'active_hotel'):
        context['active_hotel'] = request.active_hotel
    
    if hasattr(request, 'accessible_hotels'):
        context['accessible_hotels'] = request.accessible_hotels
        context['can_switch_hotel'] = len(request.accessible_hotels) > 1
    
    return context

