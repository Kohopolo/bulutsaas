"""
Kat Hizmetleri Modülü URLs
"""
from django.urls import path
from . import views

app_name = 'housekeeping'

urlpatterns = [
    # Ana Ekran
    path('', views.dashboard, name='dashboard'),
    
    # Temizlik Görevleri
    path('tasks/', views.task_list, name='task_list'),
    path('tasks/create/', views.task_create, name='task_create'),
    path('tasks/<int:pk>/', views.task_detail, name='task_detail'),
    path('tasks/<int:pk>/update/', views.task_update, name='task_update'),
    path('tasks/<int:pk>/start/', views.task_start, name='task_start'),
    path('tasks/<int:pk>/complete/', views.task_complete, name='task_complete'),
    path('tasks/<int:pk>/inspect/', views.task_inspect, name='task_inspect'),
    
    # Eksik Malzemeler
    path('missing-items/', views.missing_item_list, name='missing_item_list'),
    path('missing-items/create/', views.missing_item_create, name='missing_item_create'),
    
    # Çamaşır Yönetimi
    path('laundry/', views.laundry_list, name='laundry_list'),
    
    # Bakım Talepleri
    path('maintenance/', views.maintenance_request_list, name='maintenance_request_list'),
    path('maintenance/create/', views.maintenance_request_create, name='maintenance_request_create'),
    
    # Ayarlar
    path('settings/', views.settings, name='settings'),
]

