"""
Yedekleme Modülü Views
"""
from django.shortcuts import render, get_object_or_404, redirect
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.http import HttpResponse, FileResponse, Http404
from django.core.paginator import Paginator
from django.db.models import Q
from django.utils import timezone
from pathlib import Path
import os
from .models import DatabaseBackup
from .decorators import require_backup_permission
from .utils import get_backup_file_path, format_file_size, ensure_backup_directory
from django.core.management import call_command
from django.db import connection
from django_tenants.utils import get_public_schema_name


@login_required
@require_backup_permission('view')
def backup_list(request):
    """Yedekleme listesi - Sadece mevcut tenant'ın yedeklerini gösterir"""
    # Mevcut tenant'ın schema adını al
    current_schema = connection.schema_name
    
    # Sadece mevcut tenant'ın yedeklerini göster
    backups = DatabaseBackup.objects.filter(
        is_deleted=False,
        schema_name=current_schema
    ).order_by('-created_at')
    
    # Arama
    search_query = request.GET.get('search', '')
    if search_query:
        backups = backups.filter(
            Q(file_name__icontains=search_query) |
            Q(database_name__icontains=search_query) |
            Q(schema_name__icontains=search_query)
        )
    
    # Filtreleme
    backup_type = request.GET.get('type', '')
    if backup_type:
        backups = backups.filter(backup_type=backup_type)
    
    status = request.GET.get('status', '')
    if status:
        backups = backups.filter(status=status)
    
    # Sayfalama
    paginator = Paginator(backups, 50)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {
        'backups': page_obj,
        'search_query': search_query,
        'backup_type': backup_type,
        'status': status,
        'backup_types': DatabaseBackup.BACKUP_TYPES,
        'backup_statuses': DatabaseBackup.BACKUP_STATUS,
        'current_schema': current_schema,
    }
    
    return render(request, 'backup/list.html', context)


@login_required
@require_backup_permission('view')
def backup_detail(request, pk):
    """Yedekleme detayı - Sadece mevcut tenant'ın yedeklerine erişim"""
    # Mevcut tenant'ın schema adını al
    current_schema = connection.schema_name
    
    # Sadece mevcut tenant'ın yedeğine erişim izni ver
    backup = get_object_or_404(
        DatabaseBackup, 
        pk=pk, 
        is_deleted=False,
        schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
    )
    
    # Dosya bilgilerini kontrol et
    file_exists = False
    file_size = 0
    file_path = None
    
    if backup.file_path:
        file_path = Path(backup.file_path)
        if file_path.exists():
            file_exists = True
            file_size = file_path.stat().st_size
    
    context = {
        'backup': backup,
        'file_exists': file_exists,
        'file_size': format_file_size(file_size) if file_size else '0 B',
        'file_path': str(file_path) if file_path else None,
    }
    
    return render(request, 'backup/detail.html', context)


@login_required
@require_backup_permission('add')
def backup_create(request):
    """Manuel yedekleme oluştur - Sadece mevcut tenant'ın schema'sını yedekler"""
    # Mevcut tenant'ın schema adını al
    current_schema = connection.schema_name
    
    # Public schema'da ise erişim engelle
    if current_schema == get_public_schema_name():
        messages.error(request, 'Public schema\'dan yedekleme oluşturulamaz.')
        return redirect('backup:backup_list')
    
    if request.method == 'POST':
        # POST isteğinde schema_name parametresini yok say, mevcut tenant'ın schema'sını kullan
        # Güvenlik: Tenant sadece kendi schema'sını yedekleyebilir
        try:
            # TenantUser objesini bul (started_by için)
            from apps.tenant_apps.core.models import TenantUser
            tenant_user = None
            try:
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
            except TenantUser.DoesNotExist:
                pass
            
            # Management command'ı çağır - sadece mevcut tenant'ın schema'sını yedekle
            call_command(
                'backup_database',
                schema=current_schema,  # Güvenlik: Her zaman mevcut tenant'ın schema'sı
                type='manual',
                user_id=tenant_user.id if tenant_user else None
            )
            
            messages.success(request, 'Yedekleme başarıyla oluşturuldu.')
            return redirect('backup:backup_list')
        except Exception as e:
            messages.error(request, f'Yedekleme oluşturulurken hata: {str(e)}')
            return redirect('backup:backup_list')
    
    # GET isteği - sadece mevcut tenant bilgisini göster
    context = {
        'current_schema': current_schema,
        'can_backup': current_schema != get_public_schema_name(),
    }
    
    return render(request, 'backup/create.html', context)


@login_required
@require_backup_permission('view')
def backup_download(request, pk):
    """Yedekleme dosyasını indir - Sadece mevcut tenant'ın yedeklerine erişim"""
    # Mevcut tenant'ın schema adını al
    current_schema = connection.schema_name
    
    # Sadece mevcut tenant'ın yedeğine erişim izni ver
    backup = get_object_or_404(
        DatabaseBackup, 
        pk=pk, 
        is_deleted=False,
        schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
    )
    
    if not backup.file_path:
        raise Http404('Yedekleme dosyası bulunamadı.')
    
    file_path = Path(backup.file_path)
    
    if not file_path.exists():
        raise Http404('Yedekleme dosyası bulunamadı.')
    
    # Dosyayı indir
    response = FileResponse(
        open(file_path, 'rb'),
        content_type='application/gzip'
    )
    response['Content-Disposition'] = f'attachment; filename="{backup.file_name}"'
    
    return response


@login_required
@require_backup_permission('delete')
def backup_delete(request, pk):
    """Yedekleme kaydını sil - Sadece mevcut tenant'ın yedeklerine erişim"""
    # Mevcut tenant'ın schema adını al
    current_schema = connection.schema_name
    
    # Sadece mevcut tenant'ın yedeğine erişim izni ver
    backup = get_object_or_404(
        DatabaseBackup, 
        pk=pk, 
        is_deleted=False,
        schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
    )
    
    if request.method == 'POST':
        try:
            # Dosyayı sil
            if backup.file_path:
                file_path = Path(backup.file_path)
                if file_path.exists():
                    file_path.unlink()
            
            # Kaydı sil
            backup.is_deleted = True
            backup.save()
            
            messages.success(request, 'Yedekleme başarıyla silindi.')
        except Exception as e:
            messages.error(request, f'Yedekleme silinirken hata: {str(e)}')
        
        return redirect('backup:backup_list')
    
    context = {
        'backup': backup,
    }
    
    return render(request, 'backup/delete_confirm.html', context)

