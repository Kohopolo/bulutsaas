"""
Core Template Tags
Admin dashboard için haberler ve reklamlar template tag'leri
"""
from django import template
from django.utils import timezone
from ..models import Announcement, Advertisement

register = template.Library()


@register.simple_tag
def get_announcements():
    """Aktif duyuruları getir"""
    announcements = Announcement.objects.filter(is_deleted=False, is_active=True)
    now = timezone.now()
    
    # Tarih kontrolü
    visible = []
    for announcement in announcements:
        if (not announcement.start_date or now >= announcement.start_date) and \
           (not announcement.end_date or now <= announcement.end_date):
            visible.append(announcement)
    
    # Önceliğe göre sırala ve en fazla 5 tane getir
    visible = sorted(visible, key=lambda x: (-x.priority, -x.created_at.timestamp()))[:5]
    return visible


@register.simple_tag
def get_advertisements():
    """Aktif reklamları getir"""
    advertisements = Advertisement.objects.filter(is_deleted=False, is_active=True)
    now = timezone.now()
    
    # Tarih kontrolü
    visible = []
    for ad in advertisements:
        if (not ad.start_date or now >= ad.start_date) and \
           (not ad.end_date or now <= ad.end_date):
            visible.append(ad)
    
    # Önceliğe göre sırala ve en fazla 5 tane getir
    visible = sorted(visible, key=lambda x: (-x.priority, -x.created_at.timestamp()))[:5]
    return visible

