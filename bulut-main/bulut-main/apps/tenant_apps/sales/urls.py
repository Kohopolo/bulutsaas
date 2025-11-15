"""
Satış Yönetimi Modülü URLs
"""
from django.urls import path
from . import views

app_name = 'sales'

urlpatterns = [
    path('', views.dashboard, name='dashboard'),
    path('agencies/', views.agency_list, name='agency_list'),
    path('agencies/create/', views.agency_create, name='agency_create'),
    path('records/', views.sales_record_list, name='sales_record_list'),
    path('records/create/', views.sales_record_create, name='sales_record_create'),
    path('targets/', views.sales_target_list, name='sales_target_list'),
    path('targets/create/', views.sales_target_create, name='sales_target_create'),
    path('settings/', views.settings, name='settings'),
]

