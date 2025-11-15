"""
Tenant Admin Panel URLs
"""
from django.urls import path
from . import views

app_name = 'tenant'

urlpatterns = [
    # Authentication
    path('login/', views.tenant_login, name='login'),
    path('logout/', views.tenant_logout, name='logout'),
    
    # Dashboard
    path('', views.tenant_dashboard, name='dashboard'),
    path('dashboard/', views.tenant_dashboard, name='dashboard'),
    
    # Kullanıcı Yönetimi
    path('users/', views.user_list, name='user_list'),
    path('users/create/', views.user_create, name='user_create'),
    path('users/<int:pk>/', views.user_detail, name='user_detail'),
    path('users/<int:pk>/update/', views.user_update, name='user_update'),
    path('users/<int:pk>/delete/', views.user_delete, name='user_delete'),
    path('users/<int:user_pk>/assign-role/', views.user_role_assign, name='user_role_assign'),
    path('users/<int:user_pk>/remove-role/<int:role_pk>/', views.user_role_remove, name='user_role_remove'),
    path('users/<int:user_pk>/assign-permission/', views.user_permission_assign, name='user_permission_assign'),
    path('users/<int:user_pk>/remove-permission/<int:permission_pk>/', views.user_permission_remove, name='user_permission_remove'),
    
    # Kullanıcı Tipi Yönetimi
    path('user-types/', views.user_type_list, name='user_type_list'),
    path('user-types/create/', views.user_type_create, name='user_type_create'),
    path('user-types/<int:pk>/update/', views.user_type_update, name='user_type_update'),
    path('user-types/<int:pk>/delete/', views.user_type_delete, name='user_type_delete'),
    
    # Rol Yönetimi
    path('roles/', views.role_list, name='role_list'),
    path('roles/create/', views.role_create, name='role_create'),
    path('roles/<int:pk>/', views.role_detail, name='role_detail'),
    path('roles/<int:pk>/update/', views.role_update, name='role_update'),
    path('roles/<int:pk>/delete/', views.role_delete, name='role_delete'),
    path('roles/<int:role_pk>/assign-permission/', views.role_permission_assign, name='role_permission_assign'),
    path('roles/<int:role_pk>/remove-permission/<int:permission_pk>/', views.role_permission_remove, name='role_permission_remove'),
    
    # Yetki Yönetimi
    path('permissions/', views.permission_list, name='permission_list'),
    path('permissions/create/', views.permission_create, name='permission_create'),
    path('permissions/<int:pk>/', views.permission_detail, name='permission_detail'),
    path('permissions/<int:pk>/update/', views.permission_update, name='permission_update'),
    path('permissions/<int:pk>/delete/', views.permission_delete, name='permission_delete'),
    
    # AJAX - Müşteri Arama
    path('ajax/find-customer/', views.ajax_find_customer, name='ajax_find_customer'),
    
    # Müşteri Yönetimi (CRM)
    path('customers/', views.customer_list, name='customer_list'),
    path('customers/create/', views.customer_create, name='customer_create'),
    path('customers/<int:pk>/', views.customer_detail, name='customer_detail'),
    path('customers/<int:pk>/update/', views.customer_update, name='customer_update'),
    path('customers/<int:pk>/delete/', views.customer_delete, name='customer_delete'),
]
