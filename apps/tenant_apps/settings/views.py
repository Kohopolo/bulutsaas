"""
Ayarlar Modülü Views
SMS Gateway ve SMS Şablon yönetimi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.core.paginator import Paginator
from django.db.models import Q, Count
from django.db import models
from django.utils import timezone
from django.http import JsonResponse
from django.db import transaction

from .models import SMSGateway, SMSTemplate, SMSSentLog, EmailGateway, EmailTemplate, EmailSentLog
from .forms import SMSGatewayForm, SMSTemplateForm, EmailGatewayForm, EmailTemplateForm
from .decorators import require_settings_permission
from .utils import get_sms_gateway_instance, send_sms, send_sms_by_template
from .email_utils import get_email_gateway_instance, send_email, send_email_by_template


# ==================== SMS GATEWAY YÖNETİMİ ====================

@login_required
@require_settings_permission('view')
def sms_gateway_list(request):
    """SMS Gateway Listesi"""
    gateways = SMSGateway.objects.filter(is_deleted=False).order_by('-is_default', '-is_active', 'name')
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                gateways = gateways.filter(hotel_id=hotel_id)
            elif hotel_id == 0:
                # Genel gateway'ler (otel yok)
                gateways = gateways.filter(hotel__isnull=True)
                hotel_id = 0
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin gateway'lerini göster (genel gateway'leri de dahil et)
            gateways = gateways.filter(
                models.Q(hotel=request.active_hotel) | models.Q(hotel__isnull=True)
            )
            hotel_id = request.active_hotel.id
    
    # İstatistikler
    total_gateways = gateways.count()
    active_gateways = gateways.filter(is_active=True).count()
    default_gateway = gateways.filter(is_default=True).first()
    
    # Otel listesi (filtreleme için)
    from apps.tenant_apps.core.utils import get_filter_hotels
    accessible_hotels = get_filter_hotels(request)
    
    context = {
        'gateways': gateways,
        'total_gateways': total_gateways,
        'active_gateways': active_gateways,
        'default_gateway': default_gateway,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
        'hotels_module_enabled': hotels_module_enabled,
    }
    
    return render(request, 'settings/sms_gateway_list.html', context)


@login_required
@require_settings_permission('add')
def sms_gateway_create(request):
    """Yeni SMS Gateway Oluştur"""
    if request.method == 'POST':
        form = SMSGatewayForm(request.POST)
        if form.is_valid():
            gateway = form.save()
            messages.success(request, f'{gateway.name} SMS gateway başarıyla oluşturuldu.')
            return redirect('settings:sms_gateway_detail', pk=gateway.pk)
    else:
        form = SMSGatewayForm()
    
    context = {
        'form': form,
        'gateway_types': SMSGateway.GATEWAY_TYPE_CHOICES,
    }
    
    return render(request, 'settings/sms_gateway_form.html', context)


@login_required
@require_settings_permission('view')
def sms_gateway_detail(request, pk):
    """SMS Gateway Detayı"""
    gateway = get_object_or_404(SMSGateway, pk=pk, is_deleted=False)
    
    # Son gönderimler
    recent_logs = SMSSentLog.objects.filter(gateway=gateway).order_by('-created_at')[:10]
    
    # İstatistikler
    stats = {
        'total_sent': gateway.total_sent,
        'total_failed': gateway.total_failed,
        'success_rate': (gateway.total_sent / (gateway.total_sent + gateway.total_failed) * 100) if (gateway.total_sent + gateway.total_failed) > 0 else 0,
    }
    
    context = {
        'gateway': gateway,
        'recent_logs': recent_logs,
        'stats': stats,
    }
    
    return render(request, 'settings/sms_gateway_detail.html', context)


@login_required
@require_settings_permission('change')
def sms_gateway_edit(request, pk):
    """SMS Gateway Düzenle"""
    gateway = get_object_or_404(SMSGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = SMSGatewayForm(request.POST, instance=gateway)
        if form.is_valid():
            gateway = form.save()
            messages.success(request, f'{gateway.name} SMS gateway başarıyla güncellendi.')
            return redirect('settings:sms_gateway_detail', pk=gateway.pk)
    else:
        form = SMSGatewayForm(instance=gateway)
    
    context = {
        'form': form,
        'gateway': gateway,
        'gateway_types': SMSGateway.GATEWAY_TYPE_CHOICES,
    }
    
    return render(request, 'settings/sms_gateway_form.html', context)


@login_required
@require_settings_permission('delete')
def sms_gateway_delete(request, pk):
    """SMS Gateway Sil"""
    gateway = get_object_or_404(SMSGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        gateway.is_deleted = True
        gateway.save()
        messages.success(request, f'{gateway.name} SMS gateway silindi.')
        return redirect('settings:sms_gateway_list')
    
    context = {
        'gateway': gateway,
    }
    
    return render(request, 'settings/sms_gateway_delete_confirm.html', context)


@login_required
@require_settings_permission('view')
def sms_gateway_test(request, pk):
    """SMS Gateway Test"""
    gateway = get_object_or_404(SMSGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        test_phone = request.POST.get('test_phone', '')
        test_message = request.POST.get('test_message', 'Test mesajı')
        
        if not test_phone:
            return JsonResponse({
                'success': False,
                'message': 'Test telefon numarası gereklidir'
            })
        
        try:
            gateway_instance = get_sms_gateway_instance(gateway)
            result = gateway_instance.send_sms(
                phone=test_phone,
                message=test_message
            )
            
            return JsonResponse(result)
        except Exception as e:
            return JsonResponse({
                'success': False,
                'message': f'Test sırasında hata oluştu: {str(e)}'
            })
    
    return JsonResponse({
        'success': False,
        'message': 'POST metodu gereklidir'
    })


@login_required
@require_settings_permission('view')
def sms_gateway_balance(request, pk):
    """SMS Gateway Bakiye Sorgula"""
    gateway = get_object_or_404(SMSGateway, pk=pk, is_deleted=False)
    
    try:
        gateway_instance = get_sms_gateway_instance(gateway)
        result = gateway_instance.get_balance()
        
        return JsonResponse(result)
    except Exception as e:
        return JsonResponse({
            'success': False,
            'message': f'Bakiye sorgulanırken hata oluştu: {str(e)}'
        })


# ==================== SMS ŞABLON YÖNETİMİ ====================

@login_required
@require_settings_permission('view')
def sms_template_list(request):
    """SMS Şablon Listesi"""
    templates = SMSTemplate.objects.filter(is_deleted=False).order_by('category', 'name')
    
    # Kategorilere göre grupla
    templates_by_category = {}
    for template in templates:
        if template.category not in templates_by_category:
            templates_by_category[template.category] = []
        templates_by_category[template.category].append(template)
    
    # İstatistikler
    total_templates = templates.count()
    active_templates = templates.filter(is_active=True).count()
    
    context = {
        'templates': templates,
        'templates_by_category': templates_by_category,
        'total_templates': total_templates,
        'active_templates': active_templates,
    }
    
    return render(request, 'settings/sms_template_list.html', context)


@login_required
@require_settings_permission('add')
def sms_template_create(request):
    """Yeni SMS Şablon Oluştur"""
    if request.method == 'POST':
        form = SMSTemplateForm(request.POST)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'{template.name} SMS şablonu başarıyla oluşturuldu.')
            return redirect('settings:sms_template_detail', pk=template.pk)
    else:
        form = SMSTemplateForm()
    
    context = {
        'form': form,
        'categories': SMSTemplate.CATEGORY_CHOICES,
    }
    
    return render(request, 'settings/sms_template_form.html', context)


@login_required
@require_settings_permission('view')
def sms_template_detail(request, pk):
    """SMS Şablon Detayı"""
    template = get_object_or_404(SMSTemplate, pk=pk, is_deleted=False)
    
    # Önizleme
    preview = template.get_preview()
    
    # Kullanım istatistikleri
    usage_logs = SMSSentLog.objects.filter(template=template).order_by('-created_at')[:10]
    
    context = {
        'template': template,
        'preview': preview,
        'usage_logs': usage_logs,
    }
    
    return render(request, 'settings/sms_template_detail.html', context)


@login_required
@require_settings_permission('change')
def sms_template_edit(request, pk):
    """SMS Şablon Düzenle"""
    template = get_object_or_404(SMSTemplate, pk=pk, is_deleted=False)
    
    if template.is_system_template:
        messages.error(request, 'Sistem şablonları düzenlenemez.')
        return redirect('settings:sms_template_detail', pk=template.pk)
    
    if request.method == 'POST':
        form = SMSTemplateForm(request.POST, instance=template)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'{template.name} SMS şablonu başarıyla güncellendi.')
            return redirect('settings:sms_template_detail', pk=template.pk)
    else:
        form = SMSTemplateForm(instance=template)
    
    # Önizleme
    preview = template.get_preview()
    
    context = {
        'form': form,
        'template': template,
        'preview': preview,
        'categories': SMSTemplate.CATEGORY_CHOICES,
    }
    
    return render(request, 'settings/sms_template_form.html', context)


@login_required
@require_settings_permission('delete')
def sms_template_delete(request, pk):
    """SMS Şablon Sil"""
    template = get_object_or_404(SMSTemplate, pk=pk, is_deleted=False)
    
    if template.is_system_template:
        messages.error(request, 'Sistem şablonları silinemez.')
        return redirect('settings:sms_template_detail', pk=template.pk)
    
    if request.method == 'POST':
        template.is_deleted = True
        template.save()
        messages.success(request, f'{template.name} SMS şablonu silindi.')
        return redirect('settings:sms_template_list')
    
    context = {
        'template': template,
    }
    
    return render(request, 'settings/sms_template_delete_confirm.html', context)


@login_required
@require_settings_permission('view')
def sms_template_preview(request, pk):
    """SMS Şablon Önizleme (AJAX)"""
    template = get_object_or_404(SMSTemplate, pk=pk, is_deleted=False)
    
    # Örnek context
    sample_context = {}
    for key in request.GET.keys():
        sample_context[key] = request.GET.get(key, '')
    
    preview = template.get_preview(sample_context)
    is_valid, length = template.validate_length(sample_context)
    
    return JsonResponse({
        'success': True,
        'preview': preview,
        'length': length,
        'max_length': template.max_length,
        'is_valid': is_valid,
    })


# ==================== SMS GÖNDERİM LOGLARI ====================

@login_required
@require_settings_permission('view')
def sms_log_list(request):
    """SMS Gönderim Logları"""
    logs = SMSSentLog.objects.all().select_related('gateway', 'template').order_by('-created_at')
    
    # Filtreleme
    status_filter = request.GET.get('status', '')
    gateway_filter = request.GET.get('gateway', '')
    
    if status_filter:
        logs = logs.filter(status=status_filter)
    if gateway_filter:
        logs = logs.filter(gateway_id=gateway_filter)
    
    # Sayfalama
    paginator = Paginator(logs, 50)
    page = request.GET.get('page', 1)
    logs_page = paginator.get_page(page)
    
    # İstatistikler
    stats = {
        'total': logs.count(),
        'sent': logs.filter(status='sent').count(),
        'delivered': logs.filter(status='delivered').count(),
        'failed': logs.filter(status='failed').count(),
    }
    
    # Gateway listesi (filtre için)
    gateways = SMSGateway.objects.filter(is_active=True, is_deleted=False)
    
    context = {
        'logs': logs_page,
        'stats': stats,
        'gateways': gateways,
        'status_filter': status_filter,
        'gateway_filter': gateway_filter,
    }
    
    return render(request, 'settings/sms_log_list.html', context)


@login_required
@require_settings_permission('view')
def sms_log_detail(request, pk):
    """SMS Gönderim Log Detayı"""
    log = get_object_or_404(SMSSentLog, pk=pk)
    
    context = {
        'log': log,
    }
    
    return render(request, 'settings/sms_log_detail.html', context)


# ==================== EMAIL GATEWAY YÖNETİMİ ====================

@login_required
@require_settings_permission('view')
def email_gateway_list(request):
    """Email Gateway Listesi"""
    gateways = EmailGateway.objects.filter(is_deleted=False).order_by('-is_default', '-is_active', 'name')
    
    # Otel bazlı filtreleme
    hotel_id = None
    hotel_id_param = request.GET.get('hotel')
    if hotel_id_param and hotel_id_param.strip():
        try:
            hotel_id = int(hotel_id_param)
            if hotel_id > 0:
                gateways = gateways.filter(hotel_id=hotel_id)
            elif hotel_id == 0:
                # Genel gateway'ler (otel yok)
                gateways = gateways.filter(hotel__isnull=True)
                hotel_id = 0
        except (ValueError, TypeError):
            hotel_id = None
    
    # Otel bazlı filtreleme kontrolü: Sadece tenant'ın paketinde 'hotels' modülü aktifse filtreleme yap
    from apps.tenant_apps.core.utils import is_hotels_module_enabled
    hotels_module_enabled = is_hotels_module_enabled(getattr(request, 'tenant', None))
    
    # Aktif otel bazlı filtreleme (eğer aktif otel varsa ve hotel_id seçilmemişse VE hotels modülü aktifse)
    if hotels_module_enabled and hasattr(request, 'active_hotel') and request.active_hotel:
        if hotel_id is None:
            # Varsayılan olarak aktif otelin gateway'lerini göster (genel gateway'leri de dahil et)
            gateways = gateways.filter(
                models.Q(hotel=request.active_hotel) | models.Q(hotel__isnull=True)
            )
            hotel_id = request.active_hotel.id
    
    # İstatistikler
    total_gateways = gateways.count()
    active_gateways = gateways.filter(is_active=True).count()
    default_gateway = gateways.filter(is_default=True).first()
    
    # Otel listesi (filtreleme için)
    from apps.tenant_apps.core.utils import get_filter_hotels
    accessible_hotels = get_filter_hotels(request)
    
    context = {
        'gateways': gateways,
        'total_gateways': total_gateways,
        'active_gateways': active_gateways,
        'default_gateway': default_gateway,
        'accessible_hotels': accessible_hotels,
        'active_hotel': getattr(request, 'active_hotel', None),
        'selected_hotel_id': hotel_id if hotel_id is not None else (request.active_hotel.id if hasattr(request, 'active_hotel') and request.active_hotel else None),
        'hotels_module_enabled': hotels_module_enabled,
    }
    
    return render(request, 'settings/email_gateway_list.html', context)


@login_required
@require_settings_permission('add')
def email_gateway_create(request):
    """Yeni Email Gateway Oluştur"""
    if request.method == 'POST':
        form = EmailGatewayForm(request.POST)
        if form.is_valid():
            gateway = form.save()
            messages.success(request, f'Email gateway "{gateway.name}" başarıyla oluşturuldu.')
            return redirect('settings:email_gateway_detail', pk=gateway.pk)
    else:
        form = EmailGatewayForm()
    
    context = {
        'form': form,
        'title': 'Yeni Email Gateway',
    }
    
    return render(request, 'settings/email_gateway_form.html', context)


@login_required
@require_settings_permission('view')
def email_gateway_detail(request, pk):
    """Email Gateway Detayı"""
    gateway = get_object_or_404(EmailGateway, pk=pk, is_deleted=False)
    
    # İstatistikler
    total_sent = gateway.total_sent
    total_failed = gateway.total_failed
    success_rate = (total_sent / (total_sent + total_failed) * 100) if (total_sent + total_failed) > 0 else 0
    
    # Son gönderimler
    recent_logs = gateway.sent_logs.all()[:10]
    
    context = {
        'gateway': gateway,
        'total_sent': total_sent,
        'total_failed': total_failed,
        'success_rate': success_rate,
        'recent_logs': recent_logs,
        'stats': {
            'success_rate': success_rate
        }
    }
    
    return render(request, 'settings/email_gateway_detail.html', context)


@login_required
@require_settings_permission('change')
def email_gateway_edit(request, pk):
    """Email Gateway Düzenle"""
    gateway = get_object_or_404(EmailGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = EmailGatewayForm(request.POST, instance=gateway)
        if form.is_valid():
            gateway = form.save()
            messages.success(request, f'Email gateway "{gateway.name}" başarıyla güncellendi.')
            return redirect('settings:email_gateway_detail', pk=gateway.pk)
    else:
        form = EmailGatewayForm(instance=gateway)
    
    context = {
        'form': form,
        'gateway': gateway,
        'title': 'Email Gateway Düzenle',
    }
    
    return render(request, 'settings/email_gateway_form.html', context)


@login_required
@require_settings_permission('delete')
def email_gateway_delete(request, pk):
    """Email Gateway Sil"""
    gateway = get_object_or_404(EmailGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        gateway_name = gateway.name
        gateway.soft_delete()
        messages.success(request, f'Email gateway "{gateway_name}" başarıyla silindi.')
        return redirect('settings:email_gateway_list')
    
    context = {
        'gateway': gateway,
    }
    
    return render(request, 'settings/email_gateway_delete.html', context)


@login_required
@require_settings_permission('change')
def email_gateway_test(request, pk):
    """Email Gateway Test Et"""
    gateway = get_object_or_404(EmailGateway, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        test_email = request.POST.get('test_email', '')
        if not test_email:
            return JsonResponse({'success': False, 'error': 'Test email adresi gereklidir.'})
        
        try:
            gateway_instance = get_email_gateway_instance(gateway)
            result = gateway_instance.send_email(
                to_email=test_email,
                subject='Test Email - Email Gateway Testi',
                html_content='<h1>Test Email</h1><p>Bu bir test email\'idir.</p>',
                text_content='Test Email\n\nBu bir test email\'idir.'
            )
            
            if result['success']:
                return JsonResponse({
                    'success': True,
                    'message': 'Test email başarıyla gönderildi.'
                })
            else:
                return JsonResponse({
                    'success': False,
                    'error': result.get('error', 'Email gönderilemedi.')
                })
        except Exception as e:
            return JsonResponse({
                'success': False,
                'error': str(e)
            })
    
    return JsonResponse({'success': False, 'error': 'Geçersiz istek.'})


# ==================== EMAIL ŞABLON YÖNETİMİ ====================

@login_required
@require_settings_permission('view')
def email_template_list(request):
    """Email Şablon Listesi"""
    templates = EmailTemplate.objects.filter(is_deleted=False).order_by('category', 'name')
    
    # Kategoriye göre grupla
    templates_by_category = {}
    for template in templates:
        category = template.get_category_display()
        if category not in templates_by_category:
            templates_by_category[category] = []
        templates_by_category[category].append(template)
    
    # İstatistikler
    total_templates = templates.count()
    active_templates = templates.filter(is_active=True).count()
    
    context = {
        'templates': templates,
        'templates_by_category': templates_by_category,
        'total_templates': total_templates,
        'active_templates': active_templates,
    }
    
    return render(request, 'settings/email_template_list.html', context)


@login_required
@require_settings_permission('add')
def email_template_create(request):
    """Yeni Email Şablon Oluştur"""
    if request.method == 'POST':
        form = EmailTemplateForm(request.POST)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'Email şablonu "{template.name}" başarıyla oluşturuldu.')
            return redirect('settings:email_template_detail', pk=template.pk)
    else:
        form = EmailTemplateForm()
    
    context = {
        'form': form,
        'title': 'Yeni Email Şablonu',
    }
    
    return render(request, 'settings/email_template_form.html', context)


@login_required
@require_settings_permission('view')
def email_template_detail(request, pk):
    """Email Şablon Detayı"""
    template = get_object_or_404(EmailTemplate, pk=pk, is_deleted=False)
    
    # Önizleme
    preview = template.get_preview()
    
    # Kullanım logları
    usage_logs = template.sent_logs.all()[:20]
    
    context = {
        'template': template,
        'preview': preview,
        'usage_logs': usage_logs,
    }
    
    return render(request, 'settings/email_template_detail.html', context)


@login_required
@require_settings_permission('change')
def email_template_edit(request, pk):
    """Email Şablon Düzenle"""
    template = get_object_or_404(EmailTemplate, pk=pk, is_deleted=False)
    
    if template.is_system_template:
        messages.error(request, 'Sistem şablonları düzenlenemez.')
        return redirect('settings:email_template_detail', pk=template.pk)
    
    if request.method == 'POST':
        form = EmailTemplateForm(request.POST, instance=template)
        if form.is_valid():
            template = form.save()
            messages.success(request, f'Email şablonu "{template.name}" başarıyla güncellendi.')
            return redirect('settings:email_template_detail', pk=template.pk)
    else:
        form = EmailTemplateForm(instance=template)
    
    context = {
        'form': form,
        'template': template,
        'title': 'Email Şablon Düzenle',
    }
    
    return render(request, 'settings/email_template_form.html', context)


@login_required
@require_settings_permission('delete')
def email_template_delete(request, pk):
    """Email Şablon Sil"""
    template = get_object_or_404(EmailTemplate, pk=pk, is_deleted=False)
    
    if template.is_system_template:
        messages.error(request, 'Sistem şablonları silinemez.')
        return redirect('settings:email_template_detail', pk=template.pk)
    
    if request.method == 'POST':
        template_name = template.name
        template.soft_delete()
        messages.success(request, f'Email şablonu "{template_name}" başarıyla silindi.')
        return redirect('settings:email_template_list')
    
    context = {
        'template': template,
    }
    
    return render(request, 'settings/email_template_delete.html', context)


# ==================== EMAIL LOG YÖNETİMİ ====================

@login_required
@require_settings_permission('view')
def email_log_list(request):
    """Email Gönderim Log Listesi"""
    logs = EmailSentLog.objects.all().order_by('-created_at')
    
    # Filtreleme
    status_filter = request.GET.get('status', '')
    gateway_filter = request.GET.get('gateway', '')
    search = request.GET.get('search', '')
    
    if status_filter:
        logs = logs.filter(status=status_filter)
    if gateway_filter:
        logs = logs.filter(gateway_id=gateway_filter)
    if search:
        logs = logs.filter(
            Q(recipient_email__icontains=search) |
            Q(subject__icontains=search) |
            Q(recipient_name__icontains=search)
        )
    
    # Sayfalama
    paginator = Paginator(logs, 50)
    page = request.GET.get('page', 1)
    logs_page = paginator.get_page(page)
    
    # İstatistikler (filtrelenmiş loglar için)
    stats = {
        'total': logs.count(),
        'sent': logs.filter(status='sent').count(),
        'delivered': logs.filter(status='delivered').count(),
        'failed': logs.filter(status='failed').count(),
    }
    
    # Gateway listesi (filtre için)
    gateways = EmailGateway.objects.filter(is_deleted=False, is_active=True)
    
    context = {
        'logs': logs_page,
        'total_logs': stats['total'],
        'sent_logs': stats['sent'],
        'failed_logs': stats['failed'],
        'gateways': gateways,
        'status_filter': status_filter,
        'gateway_filter': gateway_filter,
        'search': search,
        'stats': stats
    }
    
    return render(request, 'settings/email_log_list.html', context)


@login_required
@require_settings_permission('view')
def email_log_detail(request, pk):
    """Email Gönderim Log Detayı"""
    log = get_object_or_404(EmailSentLog, pk=pk)
    
    context = {
        'log': log,
    }
    
    return render(request, 'settings/email_log_detail.html', context)

