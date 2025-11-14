"""
Yedekleme Modülü URLs
"""
from django.urls import path
from . import views

app_name = 'backup'

urlpatterns = [
    path('', views.backup_list, name='backup_list'),
    path('create/', views.backup_create, name='backup_create'),
    path('<int:pk>/', views.backup_detail, name='backup_detail'),
    path('<int:pk>/download/', views.backup_download, name='backup_download'),
    path('<int:pk>/delete/', views.backup_delete, name='backup_delete'),
]

