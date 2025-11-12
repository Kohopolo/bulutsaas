"""
VB Theme URLs
ElektraWeb Desktop Application Style URLs
"""

from django.urls import path
from . import vb_views

app_name = 'vb'

urlpatterns = [
    # Dashboard
    path('', vb_views.dashboard, name='dashboard'),
    
    # Rezervasyon
    path('rezervasyon/', vb_views.rezervasyon_list, name='rezervasyon-list'),
    
    # Room Rack
    path('room-rack/', vb_views.room_rack, name='room-rack'),
]


