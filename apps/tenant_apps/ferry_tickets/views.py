"""
Feribot Bileti Views
Feribot bilet yönetimi
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.db.models import Q, Count, Sum, F
from django.core.paginator import Paginator
from django.utils import timezone
from django.db import models
from datetime import date, timedelta
from decimal import Decimal
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_http_methods
from django.http import JsonResponse
from django.urls import reverse

from .models import (
    FerryTicket, FerryTicketStatus, FerryTicketSource,
    Ferry, FerryRoute, FerrySchedule,
    FerryTicketGuest, FerryTicketPayment, FerryTicketVoucher,
    FerryTicketVoucherTemplate, FerryAPIConfiguration, FerryAPISync
)
from .forms import (
    FerryTicketForm, FerryTicketGuestFormSet,
    FerryForm, FerryRouteForm, FerryScheduleForm,
    FerryTicketVoucherTemplateForm, FerryAPIConfigurationForm
)
from .utils import (
    generate_ticket_code, save_guest_information,
    create_ticket_voucher, generate_voucher_token,
    generate_ticket_voucher
)
from .decorators import require_ferry_ticket_permission


@login_required
@require_ferry_ticket_permission('view')
def dashboard(request):
    """Feribot Bileti Dashboard"""
    today = date.today()
    
    # Bugünkü biletler
    today_tickets = FerryTicket.objects.filter(
        schedule__departure_date=today,
        is_deleted=False
    ).select_related('schedule__route', 'schedule__ferry', 'customer').order_by('schedule__departure_time')
    
    # Yaklaşan biletler (7 gün içinde)
    upcoming_tickets = FerryTicket.objects.filter(
        schedule__departure_date__gt=today,
        schedule__departure_date__lte=today + timedelta(days=7),
        status=FerryTicketStatus.CONFIRMED,
        is_deleted=False
    ).select_related('schedule__route', 'schedule__ferry', 'customer').order_by('schedule__departure_date', 'schedule__departure_time')[:10]
    
    # Bu ay istatistikleri
    month_start = today.replace(day=1)
    month_tickets = FerryTicket.objects.filter(
        schedule__departure_date__gte=month_start,
        schedule__departure_date__lte=today,
        is_deleted=False
    )
    
    # Bu hafta istatistikleri
    week_start = today - timedelta(days=today.weekday())
    week_tickets = FerryTicket.objects.filter(
        schedule__departure_date__gte=week_start,
        schedule__departure_date__lte=today,
        is_deleted=False
    )
    
    # Ödeme bekleyen biletler
    pending_payments = FerryTicket.objects.filter(
        is_deleted=False
    ).exclude(total_paid__gte=F('total_amount')).select_related('schedule__route', 'customer').order_by('-schedule__departure_date')[:10]
    
    # İptal edilen biletler (bu ay)
    cancelled_this_month = FerryTicket.objects.filter(
        is_cancelled=True,
        cancelled_at__gte=month_start,
        is_deleted=False
    ).count()
    
    # İstatistikler
    stats = {
        'total_tickets': FerryTicket.objects.filter(is_deleted=False).count(),
        'confirmed_tickets': FerryTicket.objects.filter(
            status=FerryTicketStatus.CONFIRMED, is_deleted=False
        ).count(),
        'today_revenue': FerryTicket.objects.filter(
            schedule__departure_date=today,
            is_deleted=False
        ).aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'month_revenue': month_tickets.aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'week_revenue': week_tickets.aggregate(total=Sum('total_amount'))['total'] or Decimal('0'),
        'month_tickets': month_tickets.count(),
        'week_tickets': week_tickets.count(),
        'pending_payments_count': FerryTicket.objects.filter(
            is_deleted=False
        ).exclude(total_paid__gte=F('total_amount')).count(),
        'cancelled_this_month': cancelled_this_month,
        'today_tickets': today_tickets.count(),
    }
    
    context = {
        'today_tickets': today_tickets[:10],
        'upcoming_tickets': upcoming_tickets,
        'pending_payments': pending_payments,
        'stats': stats,
    }
    
    return render(request, 'ferry_tickets/dashboard.html', context)


@login_required
@require_ferry_ticket_permission('view')
def ticket_list(request):
    """Bilet Listesi"""
    tickets = FerryTicket.objects.filter(is_deleted=False).select_related(
        'schedule__route', 'schedule__ferry', 'customer'
    ).order_by('-schedule__departure_date', '-created_at')
    
    # Arama
    search_query = request.GET.get('search', '').strip()
    if search_query:
        tickets = tickets.filter(
            Q(ticket_code__icontains=search_query) |
            Q(customer__first_name__icontains=search_query) |
            Q(customer__last_name__icontains=search_query) |
            Q(customer__email__icontains=search_query) |
            Q(customer__phone__icontains=search_query) |
            Q(customer__tc_no__icontains=search_query)
        )
    
    # Tarih filtreleri
    departure_from = request.GET.get('departure_from')
    if departure_from:
        try:
            departure_from_date = date.fromisoformat(departure_from)
            tickets = tickets.filter(schedule__departure_date__gte=departure_from_date)
        except (ValueError, TypeError):
            pass
    
    departure_to = request.GET.get('departure_to')
    if departure_to:
        try:
            departure_to_date = date.fromisoformat(departure_to)
            tickets = tickets.filter(schedule__departure_date__lte=departure_to_date)
        except (ValueError, TypeError):
            pass
    
    # Durum filtresi
    status_filter = request.GET.get('status')
    if status_filter:
        tickets = tickets.filter(status=status_filter)
    
    # Kaynak filtresi
    source_filter = request.GET.get('source')
    if source_filter:
        tickets = tickets.filter(source=source_filter)
    
    # Ödeme durumu filtresi
    payment_status = request.GET.get('payment_status')
    if payment_status == 'paid':
        tickets = tickets.filter(total_paid__gte=F('total_amount'))
    elif payment_status == 'partial':
        tickets = tickets.filter(
            total_paid__gt=0,
            total_paid__lt=F('total_amount')
        )
    elif payment_status == 'unpaid':
        tickets = tickets.filter(total_paid=0)
    
    # Sayfalama
    paginator = Paginator(tickets, 25)
    page = request.GET.get('page')
    tickets = paginator.get_page(page)
    
    # Form'u context'e ekle (modal için)
    form = FerryTicketForm()
    
    # Rotalar (filtre için)
    routes = FerryRoute.objects.filter(is_active=True, is_deleted=False).order_by('name')
    
    context = {
        'tickets': tickets,
        'status_choices': FerryTicketStatus.choices,
        'source_choices': FerryTicketSource.choices,
        'routes': routes,
        'form': form,
    }
    
    return render(request, 'ferry_tickets/tickets/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def ticket_create(request):
    """Yeni Bilet Oluştur"""
    if request.method == 'POST':
        form = FerryTicketForm(request.POST)
        if form.is_valid():
            ticket = form.save(commit=False)
            ticket.created_by = request.user
            
            # Bilet kodu oluştur
            if not ticket.ticket_code:
                ticket.ticket_code = generate_ticket_code()
            
            # Fiyatları schedule'dan al
            schedule = ticket.schedule
            ticket.adult_unit_price = schedule.get_price_by_ticket_type('adult')
            ticket.child_unit_price = schedule.get_price_by_ticket_type('child')
            ticket.infant_unit_price = schedule.get_price_by_ticket_type('infant')
            if ticket.vehicle_type != 'none':
                ticket.vehicle_price = schedule.get_vehicle_price(ticket.vehicle_type)
            
            ticket.save()
            
            # Yolcu bilgilerini kaydet (formset ile)
            try:
                guest_formset = FerryTicketGuestFormSet(request.POST, instance=ticket)
                if guest_formset.is_valid():
                    guest_formset.save()
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Yolcu bilgileri kaydedilirken hata: {str(e)}', exc_info=True)
            
            # Ön ödeme varsa kaydet
            try:
                advance_payment = form.cleaned_data.get('advance_payment', 0)
                payment_method = form.cleaned_data.get('payment_method')
                
                if advance_payment and advance_payment > 0 and payment_method:
                    FerryTicketPayment.objects.create(
                        ticket=ticket,
                        payment_date=date.today(),
                        payment_amount=advance_payment,
                        payment_method=payment_method,
                        payment_type='advance',
                        currency=ticket.currency,
                        created_by=request.user
                    )
                    ticket.update_total_paid()
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Ön ödeme kaydedilirken hata: {str(e)}', exc_info=True)
            
            messages.success(request, 'Bilet başarıyla oluşturuldu.')
            
            # AJAX isteği ise JSON döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return JsonResponse({
                    'success': True,
                    'message': 'Bilet başarıyla oluşturuldu.',
                    'ticket_id': ticket.pk,
                    'ticket_code': ticket.ticket_code,
                    'redirect_url': f'/ferry-tickets/tickets/{ticket.pk}/'
                })
            
            return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
        else:
            # AJAX isteği ise hata mesajlarını döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return JsonResponse({
                    'success': False,
                    'errors': form.errors,
                    'message': 'Form hataları var. Lütfen kontrol edin.'
                }, status=400)
    else:
        form = FerryTicketForm()
        guest_formset = FerryTicketGuestFormSet(instance=None)
    
    context = {
        'form': form,
        'guest_formset': guest_formset,
    }
    
    # AJAX isteği ise sadece form HTML'ini döndür
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        from django.template.loader import render_to_string
        html = render_to_string('ferry_tickets/tickets/form_modal.html', context, request=request)
        return JsonResponse({'html': html})
    
    return render(request, 'ferry_tickets/tickets/form.html', context)


@login_required
@require_ferry_ticket_permission('view')
def ticket_detail(request, pk):
    """Bilet Detayı"""
    ticket = get_object_or_404(FerryTicket, pk=pk)
    
    # Yolcular
    guests = ticket.guests.filter(is_deleted=False).order_by('guest_order')
    
    # Ödemeler
    payments = ticket.payments.filter(is_deleted=False).order_by('-payment_date')
    
    # Voucher'lar
    vouchers = ticket.vouchers.all().order_by('-created_at')
    
    context = {
        'ticket': ticket,
        'guests': guests,
        'payments': payments,
        'vouchers': vouchers,
    }
    
    return render(request, 'ferry_tickets/tickets/detail.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_update(request, pk):
    """Bilet Düzenle"""
    ticket = get_object_or_404(FerryTicket, pk=pk)
    
    if request.method == 'POST':
        form = FerryTicketForm(request.POST, instance=ticket)
        if form.is_valid():
            ticket = form.save(commit=False)
            ticket.updated_by = request.user
            
            # Fiyatları schedule'dan al
            schedule = ticket.schedule
            ticket.adult_unit_price = schedule.get_price_by_ticket_type('adult')
            ticket.child_unit_price = schedule.get_price_by_ticket_type('child')
            ticket.infant_unit_price = schedule.get_price_by_ticket_type('infant')
            if ticket.vehicle_type != 'none':
                ticket.vehicle_price = schedule.get_vehicle_price(ticket.vehicle_type)
            
            ticket.save()
            
            # Yolcu bilgilerini kaydet (formset ile)
            try:
                guest_formset = FerryTicketGuestFormSet(request.POST, instance=ticket)
                if guest_formset.is_valid():
                    guest_formset.save()
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Yolcu bilgileri kaydedilirken hata: {str(e)}', exc_info=True)
            
            # Ön ödeme varsa kaydet (sadece yeni ödeme varsa)
            try:
                advance_payment = form.cleaned_data.get('advance_payment', 0)
                payment_method = form.cleaned_data.get('payment_method')
                
                existing_total_paid = ticket.total_paid or Decimal('0')
                if advance_payment and advance_payment > existing_total_paid and payment_method:
                    new_payment_amount = advance_payment - existing_total_paid
                    FerryTicketPayment.objects.create(
                        ticket=ticket,
                        payment_date=date.today(),
                        payment_amount=new_payment_amount,
                        payment_method=payment_method,
                        payment_type='advance',
                        currency=ticket.currency,
                        created_by=request.user
                    )
                    ticket.update_total_paid()
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Ön ödeme kaydedilirken hata: {str(e)}', exc_info=True)
            
            messages.success(request, 'Bilet başarıyla güncellendi.')
            
            # AJAX isteği ise JSON döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return JsonResponse({
                    'success': True,
                    'message': 'Bilet başarıyla güncellendi.',
                    'ticket_id': ticket.pk,
                })
            
            return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
        else:
            # AJAX isteği ise hata mesajlarını döndür
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return JsonResponse({
                    'success': False,
                    'errors': form.errors,
                    'message': 'Form hataları var. Lütfen kontrol edin.'
                }, status=400)
    else:
        form = FerryTicketForm(instance=ticket)
        guest_formset = FerryTicketGuestFormSet(instance=ticket)
    
    context = {
        'ticket': ticket,
        'form': form,
        'guest_formset': guest_formset,
    }
    
    # AJAX isteği ise sadece form HTML'ini döndür
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        from django.template.loader import render_to_string
        html = render_to_string('ferry_tickets/tickets/form_modal.html', context, request=request)
        return JsonResponse({'html': html})
    
    return render(request, 'ferry_tickets/tickets/form.html', context)


# ==================== BİLET İŞLEMLERİ ====================

@login_required
@require_ferry_ticket_permission('delete')
def ticket_delete(request, pk):
    """Bilet Sil (Soft Delete) - Ödeme ve İade Kontrolü ile (Resepsiyon modülü gibi iki adımlı)"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    # Ödeme ve iade kontrolü
    from apps.tenant_apps.core.utils import can_delete_with_payment_check, start_refund_process_for_deletion
    
    delete_check = can_delete_with_payment_check(ticket, 'ferry_tickets')
    
    if request.method == 'POST':
        # İki adımlı onay sistemi (resepsiyon modülü gibi)
        confirm_step = request.POST.get('confirm_step', '1')
        final_confirm = request.POST.get('final_confirm', '').strip().upper()
        start_refund = request.POST.get('start_refund', '0') == '1'  # İade başlatma isteği
        
        # İade başlatma isteği varsa (herhangi bir adımda)
        if start_refund and delete_check['has_payment'] and not delete_check['refund_request']:
            refund_request = start_refund_process_for_deletion(
                ticket,
                'ferry_tickets',
                request.user,
                reason='Bilet silme işlemi için iade'
            )
            
            if refund_request:
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    return JsonResponse({
                        'success': True,
                        'refund_started': True,
                        'refund_request_id': refund_request.pk,
                        'refund_request_number': refund_request.request_number,
                        'message': f'İade süreci başlatıldı. İade Talebi No: {refund_request.request_number}. İade tamamlandıktan sonra silme işlemini yapabilirsiniz.',
                        'redirect_url': reverse('refunds:refund_request_detail', kwargs={'pk': refund_request.pk})
                    })
                messages.success(
                    request,
                    f'İade süreci başlatıldı. İade Talebi No: {refund_request.request_number}. '
                    f'İade tamamlandıktan sonra silme işlemini yapabilirsiniz.'
                )
                return redirect('refunds:refund_request_detail', pk=refund_request.pk)
            else:
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    return JsonResponse({
                        'success': False,
                        'error': 'İade süreci başlatılamadı. Lütfen tekrar deneyin.'
                    }, status=400)
                messages.error(request, 'İade süreci başlatılamadı. Lütfen tekrar deneyin.')
                context = {
                    'ticket': ticket,
                    'delete_check': delete_check,
                }
                return render(request, 'ferry_tickets/tickets/delete_confirm.html', context)
        
        if confirm_step == '1':
            # İlk adım: Silme kontrolü yap
            delete_check = can_delete_with_payment_check(ticket, 'ferry_tickets')
            
            if not delete_check['can_delete']:
                return JsonResponse({
                    'success': False,
                    'error': delete_check['message'],
                    'has_payment': delete_check['has_payment'],
                    'refund_status': delete_check['refund_status'],
                    'refund_request_id': delete_check['refund_request_id'],
                    'can_start_refund': delete_check['has_payment'] and not delete_check['refund_request'],
                }, status=400)
            
            # İlk onay - sadece onay mesajı döndür
            return JsonResponse({
                'success': True,
                'step': 1,
                'message': 'İlk onay alındı. Son onay için bilet kodunu girin.'
            })
        
        elif confirm_step == '2':
            # İkinci adım: Silme kontrolünü tekrar yap (durum değişmiş olabilir)
            delete_check = can_delete_with_payment_check(ticket, 'ferry_tickets')
            
            if not delete_check['can_delete']:
                return JsonResponse({
                    'success': False,
                    'error': delete_check['message'],
                    'has_payment': delete_check['has_payment'],
                    'refund_status': delete_check['refund_status'],
                    'refund_request_id': delete_check['refund_request_id'],
                }, status=400)
            
            # İkinci onay - bilet kodu kontrolü
            entered_code = request.POST.get('ticket_code', '').strip()
            
            if entered_code != ticket.ticket_code:
                return JsonResponse({
                    'success': False,
                    'error': 'Bilet kodu eşleşmiyor. Lütfen doğru kodu girin.'
                }, status=400)
            
            if final_confirm != 'DELETE':
                return JsonResponse({
                    'success': False,
                    'error': 'Son onay metni hatalı. Lütfen "DELETE" yazın.'
                }, status=400)
            
            # Silme işlemini gerçekleştir
            ticket.is_deleted = True
            ticket.deleted_by = request.user
            ticket.deleted_at = timezone.now()
            ticket.save()
            
            return JsonResponse({
                'success': True,
                'step': 2,
                'message': 'Bilet başarıyla silindi ve arşivlendi.',
                'redirect_url': reverse('ferry_tickets:ticket_list')
            })
        
        else:
            return JsonResponse({
                'success': False,
                'error': 'Geçersiz onay adımı.'
            }, status=400)
    
    # GET request - silme modalı için bilgileri döndür
    context = {
        'ticket': ticket,
        'delete_check': delete_check,
        'can_delete': delete_check['can_delete'],
        'has_payment': delete_check['has_payment'],
        'refund_status': delete_check['refund_status'],
        'refund_request': delete_check['refund_request'],
        'refund_message': delete_check['message'],
    }
    
    return render(request, 'ferry_tickets/tickets/delete_confirm.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_restore(request, pk):
    """Bilet Geri Al"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=True)
    
    if request.method == 'POST':
        ticket.is_deleted = False
        ticket.deleted_at = None
        ticket.deleted_by = None
        ticket.save()
        messages.success(request, 'Bilet başarıyla geri alındı.')
        
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'success': True, 'message': 'Bilet başarıyla geri alındı.'})
        
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {'ticket': ticket}
    return render(request, 'ferry_tickets/tickets/restore_confirm.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_cancel(request, pk):
    """Bilet İptal Et"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        ticket.status = FerryTicketStatus.CANCELLED
        ticket.is_cancelled = True
        ticket.cancelled_at = timezone.now()
        ticket.cancelled_by = request.user
        ticket.cancellation_reason = request.POST.get('cancellation_reason', '')
        ticket.save()
        messages.success(request, 'Bilet başarıyla iptal edildi.')
        
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return JsonResponse({'success': True, 'message': 'Bilet başarıyla iptal edildi.'})
        
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {'ticket': ticket}
    return render(request, 'ferry_tickets/tickets/cancel_confirm.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_refund(request, pk):
    """Bilet İade"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        refund_amount = Decimal(request.POST.get('refund_amount', 0))
        refund_reason = request.POST.get('refund_reason', '')
        
        if refund_amount <= 0:
            messages.error(request, 'İade tutarı geçersiz.')
            return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
        
        # İade ödemesi oluştur
        FerryTicketPayment.objects.create(
            ticket=ticket,
            payment_date=date.today(),
            payment_amount=-refund_amount,  # Negatif tutar
            payment_method=request.POST.get('refund_method', 'transfer'),
            payment_type='refund',
            currency=ticket.currency,
            payment_reference=request.POST.get('refund_reference', ''),
            payment_info={'refund_reason': refund_reason},
            created_by=request.user
        )
        
        ticket.update_total_paid()
        ticket.status = FerryTicketStatus.REFUNDED
        ticket.save()
        
        messages.success(request, f'İade işlemi başarıyla tamamlandı: {refund_amount} {ticket.currency}')
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {'ticket': ticket}
    return render(request, 'ferry_tickets/tickets/refund_form.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_status_change(request, pk):
    """Bilet Durum Değiştir"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        new_status = request.POST.get('status')
        if new_status in [choice[0] for choice in FerryTicketStatus.choices]:
            ticket.status = new_status
            ticket.save()
            messages.success(request, 'Bilet durumu güncellendi.')
            
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return JsonResponse({'success': True, 'message': 'Bilet durumu güncellendi.'})
        
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {
        'ticket': ticket,
        'status_choices': FerryTicketStatus.choices,
    }
    return render(request, 'ferry_tickets/tickets/status_change.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ticket_payment_add(request, pk):
    """Bilet Ödeme Ekle"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        payment_amount = Decimal(request.POST.get('payment_amount', 0))
        payment_method = request.POST.get('payment_method')
        payment_date = request.POST.get('payment_date') or date.today()
        
        if payment_amount <= 0:
            messages.error(request, 'Ödeme tutarı geçersiz.')
            return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
        
        FerryTicketPayment.objects.create(
            ticket=ticket,
            payment_date=payment_date,
            payment_amount=payment_amount,
            payment_method=payment_method,
            payment_type='advance' if payment_amount < ticket.get_remaining_amount() else 'full',
            currency=ticket.currency,
            payment_reference=request.POST.get('payment_reference', ''),
            created_by=request.user
        )
        
        ticket.update_total_paid()
        
        # Tam ödendiyse durumu güncelle
        if ticket.is_paid():
            ticket.status = FerryTicketStatus.CONFIRMED
        
        ticket.save()
        
        messages.success(request, f'Ödeme başarıyla eklendi: {payment_amount} {ticket.currency}')
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {'ticket': ticket}
    return render(request, 'ferry_tickets/tickets/payment_add.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def payment_delete(request, pk):
    """Ödeme Sil"""
    payment = get_object_or_404(FerryTicketPayment, pk=pk, is_deleted=False)
    ticket = payment.ticket
    
    if request.method == 'POST':
        payment.is_deleted = True
        payment.deleted_at = timezone.now()
        payment.save()
        
        ticket.update_total_paid()
        ticket.save()
        
        messages.success(request, 'Ödeme başarıyla silindi.')
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {'payment': payment, 'ticket': ticket}
    return render(request, 'ferry_tickets/payments/delete_confirm.html', context)


@login_required
@require_ferry_ticket_permission('view')
def ticket_payment_link(request, pk):
    """Bilet Ödeme Linki Oluştur"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    # Voucher oluştur (yoksa)
    voucher = ticket.vouchers.filter(payment_status__in=['pending', 'partial']).first()
    
    if not voucher:
        try:
            voucher = create_ticket_voucher(ticket)
            messages.success(request, 'Ödeme linki oluşturuldu.')
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f'Voucher oluşturulurken hata: {str(e)}', exc_info=True)
            messages.error(request, f'Ödeme linki oluşturulamadı: {str(e)}')
            return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)
    
    context = {
        'ticket': ticket,
        'voucher': voucher,
        'payment_url': voucher.get_payment_url() if voucher.access_token else None,
        'public_url': voucher.get_public_url() if voucher.access_token else None,
    }
    
    return render(request, 'ferry_tickets/tickets/payment_link.html', context)


# ==================== VOUCHER VIEWS ====================

@login_required
@require_ferry_ticket_permission('view')
def ticket_voucher_create(request, pk):
    """Bilet Voucher'ı Oluştur"""
    ticket = get_object_or_404(FerryTicket, pk=pk, is_deleted=False)
    
    template_id = request.GET.get('template_id')
    template = None
    if template_id:
        template = FerryTicketVoucherTemplate.objects.filter(pk=template_id, is_active=True, is_deleted=False).first()
    
    try:
        import logging
        logger = logging.getLogger(__name__)
        
        logger.info(f'Voucher oluşturuluyor - Bilet: {ticket.ticket_code}, Template: {template.pk if template else "Varsayılan"}')
        
        voucher = create_ticket_voucher(ticket, template=template)
        
        if not voucher:
            raise ValueError('Voucher oluşturulamadı.')
        
        logger.info(f'Voucher başarıyla oluşturuldu - ID: {voucher.pk}, Kod: {voucher.voucher_code}')
        
        messages.success(request, f'Voucher başarıyla oluşturuldu. (Kod: {voucher.voucher_code})')
        return redirect('ferry_tickets:ticket_voucher_detail', pk=voucher.pk)
    except Exception as e:
        import logging
        import traceback
        logger = logging.getLogger(__name__)
        error_trace = traceback.format_exc()
        logger.error(f'Voucher oluşturulurken hata: {str(e)}\n{error_trace}')
        messages.error(request, f'Voucher oluşturulurken hata oluştu: {str(e)}')
        return redirect('ferry_tickets:ticket_detail', pk=ticket.pk)


@login_required
@require_ferry_ticket_permission('view')
def ticket_voucher_detail(request, pk):
    """Voucher Detay"""
    voucher = get_object_or_404(FerryTicketVoucher, pk=pk)
    
    # Voucher HTML'ini oluştur
    try:
        voucher_html, _ = generate_ticket_voucher(voucher.ticket, voucher.voucher_template)
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        voucher_html = f'<p>Voucher HTML oluşturulurken hata oluştu: {str(e)}</p>'
    
    context = {
        'voucher': voucher,
        'ticket': voucher.ticket,
        'voucher_html': voucher_html,
    }
    
    return render(request, 'ferry_tickets/vouchers/detail.html', context)


@login_required
@require_ferry_ticket_permission('view')
def ticket_voucher_pdf(request, pk):
    """Voucher PDF İndir - Direkt PDF formatında (Güvenli: ReportLab öncelikli)"""
    voucher = get_object_or_404(FerryTicketVoucher, pk=pk)
    import logging
    logger = logging.getLogger(__name__)
    
    # Voucher HTML'ini oluştur
    try:
        voucher_html, _ = generate_ticket_voucher(voucher.ticket, voucher.voucher_template)
    except Exception as e:
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        messages.error(request, f'Voucher PDF oluşturulurken hata: {str(e)}')
        return redirect('ferry_tickets:ticket_voucher_detail', pk=voucher.pk)
    
    # PDF oluştur - Güvenli utility fonksiyonu kullan
    from apps.tenant_apps.core.pdf_utils import generate_pdf_response
    
    pdf_response = generate_pdf_response(
        voucher_html,
        filename=f'voucher_{voucher.voucher_code}.pdf'
    )
    
    if pdf_response:
        return pdf_response
    
    # PDF oluşturulamadıysa hata mesajı göster
    logger.error('PDF oluşturma için gerekli kütüphaneler bulunamadı (ReportLab veya WeasyPrint)')
    messages.error(
        request,
        'PDF oluşturulamadı. Lütfen sistem yöneticisine başvurun. '
        'Gerekli kütüphaneler: reportlab veya weasyprint'
    )
    return redirect('ferry_tickets:ticket_voucher_detail', pk=voucher.pk)


@login_required
@require_ferry_ticket_permission('view')
def voucher_send(request, pk):
    """Voucher Gönder (WhatsApp/Email)"""
    voucher = get_object_or_404(FerryTicketVoucher, pk=pk)
    method = request.GET.get('method', 'email')
    
    try:
        import logging
        logger = logging.getLogger(__name__)
        
        if method == 'whatsapp':
            whatsapp_url = voucher.get_whatsapp_url()
            if whatsapp_url:
                voucher.is_sent = True
                voucher.sent_at = timezone.now()
                voucher.sent_via = 'whatsapp'
                voucher.save()
                logger.info(f'Voucher WhatsApp ile gönderildi: {voucher.voucher_code}')
                return JsonResponse({'success': True, 'message': 'WhatsApp linki açıldı', 'url': whatsapp_url})
            else:
                return JsonResponse({'success': False, 'error': 'Müşteri telefon numarası bulunamadı'})
        
        elif method == 'email':
            from django.core.mail import send_mail
            from django.conf import settings
            
            customer = voucher.ticket.customer
            if not customer or not customer.email:
                return JsonResponse({'success': False, 'error': 'Müşteri email adresi bulunamadı'})
            
            subject = voucher.get_email_subject()
            body = voucher.get_email_body()
            
            # Public URL'i tam URL'e çevir
            public_url = request.build_absolute_uri(voucher.get_public_url())
            payment_url = request.build_absolute_uri(voucher.get_payment_url())
            body = body.replace(voucher.get_public_url(), public_url)
            body = body.replace(voucher.get_payment_url(), payment_url)
            
            try:
                send_mail(
                    subject=subject,
                    message=body,
                    from_email=settings.DEFAULT_FROM_EMAIL,
                    recipient_list=[customer.email],
                    fail_silently=False,
                )
                
                voucher.is_sent = True
                voucher.sent_at = timezone.now()
                voucher.sent_via = 'email'
                voucher.save()
                
                logger.info(f'Voucher email ile gönderildi: {voucher.voucher_code} -> {customer.email}')
                return JsonResponse({'success': True, 'message': 'Email başarıyla gönderildi'})
            except Exception as e:
                logger.error(f'Email gönderilirken hata: {str(e)}', exc_info=True)
                return JsonResponse({'success': False, 'error': f'Email gönderilemedi: {str(e)}'})
        
        elif method == 'link':
            voucher.is_sent = True
            voucher.sent_at = timezone.now()
            voucher.sent_via = 'link'
            voucher.save()
            return JsonResponse({'success': True, 'message': 'Link kopyalandı'})
        
        else:
            return JsonResponse({'success': False, 'error': 'Geçersiz gönderim yöntemi'})
    
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher gönderilirken hata: {str(e)}', exc_info=True)
        return JsonResponse({'success': False, 'error': str(e)})


@require_http_methods(["GET"])
def voucher_view(request, token):
    """Voucher Görüntüleme (Token ile - Public)"""
    voucher = get_object_or_404(FerryTicketVoucher, access_token=token)
    
    # Token geçerlilik kontrolü
    if voucher.token_expires_at and voucher.token_expires_at < timezone.now():
        return render(request, 'ferry_tickets/vouchers/expired.html', {
            'voucher': voucher
        })
    
    # Voucher HTML'ini oluştur
    try:
        voucher_html, _ = generate_ticket_voucher(voucher.ticket, voucher.voucher_template)
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher HTML oluşturulurken hata: {str(e)}', exc_info=True)
        voucher_html = f'<p>Voucher yüklenirken bir hata oluştu.</p>'
    
    context = {
        'voucher': voucher,
        'ticket': voucher.ticket,
        'voucher_html': voucher_html,
        'token': token,
    }
    
    return render(request, 'ferry_tickets/vouchers/public_view.html', context)


@require_http_methods(["GET", "POST"])
def voucher_payment(request, token):
    """Voucher Ödeme Sayfası (Token ile - Public)"""
    voucher = get_object_or_404(FerryTicketVoucher, access_token=token)
    
    # Token geçerlilik kontrolü
    if voucher.token_expires_at and voucher.token_expires_at < timezone.now():
        return render(request, 'ferry_tickets/vouchers/expired.html', {
            'voucher': voucher
        })
    
    # Ödeme durumu kontrolü
    if voucher.payment_status == 'paid':
        messages.info(request, 'Bu voucher için ödeme zaten yapılmış.')
        return redirect('ferry_tickets:voucher_view', token=token)
    
    ticket = voucher.ticket
    payment_amount = voucher.calculate_payment_amount()
    
    if request.method == 'POST':
        # Ödeme başlat
        gateway_code = request.POST.get('gateway', 'iyzico')
        payment_method = request.POST.get('payment_method', 'credit_card')
        
        try:
            # Payment gateway'i bul
            from apps.payments.models import PaymentGateway, TenantPaymentGateway
            from django_tenants.utils import get_tenant_model
            
            # Tenant'ı bul
            TenantModel = get_tenant_model()
            tenant = TenantModel.objects.filter(schema_name=request.tenant.schema_name).first()
            
            if not tenant:
                messages.error(request, 'Ödeme gateway ayarları bulunamadı.')
                return redirect('ferry_tickets:voucher_payment', token=token)
            
            # Gateway config'i bul
            gateway_obj = PaymentGateway.objects.filter(code=gateway_code, is_active=True).first()
            if not gateway_obj:
                messages.error(request, 'Ödeme gateway bulunamadı.')
                return redirect('ferry_tickets:voucher_payment', token=token)
            
            tenant_gateway = TenantPaymentGateway.objects.filter(
                tenant=tenant,
                gateway=gateway_obj,
                is_active=True
            ).first()
            
            if not tenant_gateway:
                messages.error(request, 'Ödeme gateway ayarları yapılandırılmamış.')
                return redirect('ferry_tickets:voucher_payment', token=token)
            
            # Ödeme işlemini başlat
            from apps.payments.views import get_gateway_instance
            from apps.payments.models import PaymentTransaction
            import uuid
            
            gateway = get_gateway_instance(gateway_code, tenant_gateway)
            
            # Müşteri bilgileri
            customer = ticket.customer
            customer_info = {
                'name': customer.first_name if customer else '',
                'surname': customer.last_name if customer else '',
                'email': customer.email if customer else '',
                'phone': customer.phone if customer else '',
            }
            
            # Transaction oluştur
            transaction_id = str(uuid.uuid4())
            payment_transaction = PaymentTransaction.objects.create(
                tenant=tenant,
                gateway=gateway_obj,
                transaction_id=transaction_id,
                amount=payment_amount,
                currency=ticket.currency,
                payment_method=payment_method,
                status='pending',
                customer_info=customer_info,
                metadata={
                    'voucher_code': voucher.voucher_code,
                    'ticket_code': ticket.ticket_code,
                    'ticket_id': ticket.pk,
                }
            )
            
            # Voucher'a transaction'ı bağla
            voucher.payment_transaction = payment_transaction
            voucher.save()
            
            # Gateway'e ödeme başlat
            result = gateway.initiate_payment(
                transaction_id=transaction_id,
                amount=payment_amount,
                currency=ticket.currency,
                customer_info=customer_info,
                return_url=request.build_absolute_uri(
                    reverse('ferry_tickets:voucher_payment_callback', kwargs={'token': token})
                ),
                cancel_url=request.build_absolute_uri(
                    reverse('ferry_tickets:voucher_payment_fail', kwargs={'token': token})
                ),
            )
            
            if result.get('success') and result.get('redirect_url'):
                return redirect(result['redirect_url'])
            else:
                messages.error(request, result.get('error', 'Ödeme başlatılamadı.'))
                return redirect('ferry_tickets:voucher_payment', token=token)
        
        except Exception as e:
            import logging
            import traceback
            logger = logging.getLogger(__name__)
            logger.error(f'Voucher ödeme başlatılırken hata: {str(e)}\n{traceback.format_exc()}')
            messages.error(request, f'Ödeme başlatılırken hata oluştu: {str(e)}')
            return redirect('ferry_tickets:voucher_payment', token=token)
    
    # GET request - Ödeme formu göster
    from apps.payments.models import PaymentGateway, TenantPaymentGateway
    from django_tenants.utils import get_tenant_model
    
    TenantModel = get_tenant_model()
    tenant = TenantModel.objects.filter(schema_name=request.tenant.schema_name).first()
    
    active_gateways = []
    if tenant:
        tenant_gateways = TenantPaymentGateway.objects.filter(
            tenant=tenant,
            is_active=True
        ).select_related('gateway')
        
        for tg in tenant_gateways:
            if tg.gateway.is_active:
                active_gateways.append({
                    'code': tg.gateway.code,
                    'name': tg.gateway.name,
                    'gateway_type': tg.gateway.gateway_type,
                })
    
    context = {
        'voucher': voucher,
        'ticket': ticket,
        'payment_amount': payment_amount,
        'active_gateways': active_gateways,
        'token': token,
    }
    
    return render(request, 'ferry_tickets/vouchers/payment.html', context)


@csrf_exempt
@require_http_methods(["POST", "GET"])
def voucher_payment_callback(request, token):
    """Voucher Ödeme Callback (API)"""
    voucher = get_object_or_404(FerryTicketVoucher, access_token=token)
    payment_transaction = voucher.payment_transaction
    
    if not payment_transaction:
        import logging
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher ödeme callback: Payment transaction bulunamadı - Token: {token}')
        return JsonResponse({'success': False, 'error': 'Payment transaction not found'}, status=400)
    
    try:
        from apps.payments.views import get_gateway_instance
        from apps.payments.models import TenantPaymentGateway
        from django.db import transaction as db_transaction
        
        tenant_gateway = TenantPaymentGateway.objects.filter(
            tenant=payment_transaction.tenant,
            gateway=payment_transaction.gateway,
            is_active=True
        ).first()
        
        if not tenant_gateway:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f'Voucher ödeme callback: Gateway config bulunamadı')
            return JsonResponse({'success': False, 'error': 'Gateway config not found'}, status=400)
        
        gateway = get_gateway_instance(payment_transaction.gateway.code, tenant_gateway)
        
        # Ödeme durumunu kontrol et
        result = gateway.verify_payment(
            payment_transaction.gateway_transaction_id or payment_transaction.transaction_id,
            **request.POST.dict() if request.method == 'POST' else request.GET.dict()
        )
        
        with db_transaction.atomic():
            if result.get('success') and result.get('status') == 'completed':
                payment_transaction.status = 'completed'
                payment_transaction.payment_date = timezone.now()
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                # Voucher'ı güncelle
                voucher.payment_status = 'paid'
                voucher.payment_date = timezone.now()
                voucher.payment_completed_at = timezone.now()
                voucher.payment_method = payment_transaction.payment_method
                voucher.payment_info = {
                    'transaction_id': payment_transaction.transaction_id,
                    'gateway_transaction_id': payment_transaction.gateway_transaction_id,
                    'amount': str(payment_transaction.amount),
                    'currency': payment_transaction.currency,
                    'payment_method': payment_transaction.payment_method,
                }
                voucher.save()
                
                # Bilet ödemesini güncelle
                FerryTicketPayment.objects.create(
                    ticket=voucher.ticket,
                    payment_date=timezone.now().date(),
                    payment_amount=payment_transaction.amount,
                    payment_method=payment_transaction.payment_method,
                    payment_type='full' if payment_transaction.amount >= voucher.ticket.get_remaining_amount() else 'partial',
                    currency=payment_transaction.currency,
                    payment_reference=f'Voucher ödemesi: {voucher.voucher_code} | Transaction: {payment_transaction.transaction_id}',
                    created_by=None,
                )
                
                # Bilet toplam ödemesini güncelle
                voucher.ticket.update_total_paid()
                
                # Bilet durumunu güncelle
                if voucher.ticket.is_paid():
                    voucher.ticket.status = FerryTicketStatus.CONFIRMED
                    voucher.ticket.save()
                
                import logging
                logger = logging.getLogger(__name__)
                logger.info(f'Voucher ödeme başarılı: {voucher.voucher_code} - {payment_transaction.amount} {payment_transaction.currency}')
                
                return redirect('ferry_tickets:voucher_payment_success', token=token)
            else:
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', 'Ödeme başarısız')
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                voucher.payment_status = 'failed'
                voucher.save()
                
                import logging
                logger = logging.getLogger(__name__)
                logger.warning(f'Voucher ödeme başarısız: {voucher.voucher_code} - {result.get("error", "Bilinmeyen hata")}')
                
                return redirect('ferry_tickets:voucher_payment_fail', token=token)
    
    except Exception as e:
        import logging
        import traceback
        logger = logging.getLogger(__name__)
        logger.error(f'Voucher ödeme callback hatası: {str(e)}\n{traceback.format_exc()}')
        return JsonResponse({'success': False, 'error': str(e)}, status=500)


@require_http_methods(["GET"])
def voucher_payment_success(request, token):
    """Voucher Ödeme Başarılı"""
    voucher = get_object_or_404(FerryTicketVoucher, access_token=token)
    
    context = {
        'voucher': voucher,
        'ticket': voucher.ticket,
        'token': token,
    }
    
    return render(request, 'ferry_tickets/vouchers/payment_success.html', context)


@require_http_methods(["GET"])
def voucher_payment_fail(request, token):
    """Voucher Ödeme Başarısız"""
    voucher = get_object_or_404(FerryTicketVoucher, access_token=token)
    
    context = {
        'voucher': voucher,
        'ticket': voucher.ticket,
        'token': token,
    }
    
    return render(request, 'ferry_tickets/vouchers/payment_fail.html', context)


# ==================== VOUCHER ŞABLONLARI ====================

@login_required
@require_ferry_ticket_permission('view')
def voucher_template_list(request):
    """Voucher Şablonları Listesi"""
    templates = FerryTicketVoucherTemplate.objects.filter(is_deleted=False).order_by('-is_default', 'name')
    
    context = {
        'templates': templates,
    }
    
    return render(request, 'ferry_tickets/vouchers/templates/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def voucher_template_create(request):
    """Voucher Şablonu Oluştur"""
    if request.method == 'POST':
        form = FerryTicketVoucherTemplateForm(request.POST)
        if form.is_valid():
            template = form.save()
            messages.success(request, 'Voucher şablonu başarıyla oluşturuldu.')
            return redirect('ferry_tickets:voucher_template_detail', pk=template.pk)
    else:
        form = FerryTicketVoucherTemplateForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'ferry_tickets/vouchers/templates/form.html', context)


@login_required
@require_ferry_ticket_permission('view')
def voucher_template_detail(request, pk):
    """Voucher Şablonu Detay"""
    template = get_object_or_404(FerryTicketVoucherTemplate, pk=pk)
    
    context = {
        'template': template,
    }
    
    return render(request, 'ferry_tickets/vouchers/templates/detail.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def voucher_template_update(request, pk):
    """Voucher Şablonu Güncelle"""
    template = get_object_or_404(FerryTicketVoucherTemplate, pk=pk)
    
    if request.method == 'POST':
        form = FerryTicketVoucherTemplateForm(request.POST, instance=template)
        if form.is_valid():
            template = form.save()
            messages.success(request, 'Voucher şablonu başarıyla güncellendi.')
            return redirect('ferry_tickets:voucher_template_detail', pk=template.pk)
    else:
        form = FerryTicketVoucherTemplateForm(instance=template)
    
    context = {
        'form': form,
        'template': template,
    }
    
    return render(request, 'ferry_tickets/vouchers/templates/form.html', context)


# ==================== FERİBOT YÖNETİMİ ====================

@login_required
@require_ferry_ticket_permission('view')
def ferry_list(request):
    """Feribot Listesi"""
    ferries = Ferry.objects.filter(is_deleted=False).order_by('name')
    
    # Sayfalama
    paginator = Paginator(ferries, 25)
    page = request.GET.get('page')
    ferries = paginator.get_page(page)
    
    context = {
        'ferries': ferries,
    }
    
    return render(request, 'ferry_tickets/ferries/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def ferry_create(request):
    """Yeni Feribot Oluştur"""
    if request.method == 'POST':
        form = FerryForm(request.POST)
        if form.is_valid():
            ferry = form.save()
            messages.success(request, 'Feribot başarıyla oluşturuldu.')
            return redirect('ferry_tickets:ferry_list')
    else:
        form = FerryForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'ferry_tickets/ferries/form.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def ferry_update(request, pk):
    """Feribot Güncelle"""
    ferry = get_object_or_404(Ferry, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = FerryForm(request.POST, instance=ferry)
        if form.is_valid():
            ferry = form.save()
            messages.success(request, 'Feribot başarıyla güncellendi.')
            return redirect('ferry_tickets:ferry_list')
    else:
        form = FerryForm(instance=ferry)
    
    context = {
        'form': form,
        'ferry': ferry,
    }
    
    return render(request, 'ferry_tickets/ferries/form.html', context)


@login_required
@require_ferry_ticket_permission('delete')
def ferry_delete(request, pk):
    """Feribot Sil"""
    ferry = get_object_or_404(Ferry, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        ferry.is_deleted = True
        ferry.deleted_at = timezone.now()
        ferry.save()
        messages.success(request, 'Feribot başarıyla silindi.')
        return redirect('ferry_tickets:ferry_list')
    
    context = {'ferry': ferry}
    return render(request, 'ferry_tickets/ferries/delete_confirm.html', context)


# ==================== ROTA YÖNETİMİ ====================

@login_required
@require_ferry_ticket_permission('view')
def route_list(request):
    """Rota Listesi"""
    routes = FerryRoute.objects.filter(is_deleted=False).order_by('name')
    
    # Sayfalama
    paginator = Paginator(routes, 25)
    page = request.GET.get('page')
    routes = paginator.get_page(page)
    
    context = {
        'routes': routes,
    }
    
    return render(request, 'ferry_tickets/routes/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def route_create(request):
    """Yeni Rota Oluştur"""
    if request.method == 'POST':
        form = FerryRouteForm(request.POST)
        if form.is_valid():
            route = form.save()
            messages.success(request, 'Rota başarıyla oluşturuldu.')
            return redirect('ferry_tickets:route_list')
    else:
        form = FerryRouteForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'ferry_tickets/routes/form.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def route_update(request, pk):
    """Rota Güncelle"""
    route = get_object_or_404(FerryRoute, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = FerryRouteForm(request.POST, instance=route)
        if form.is_valid():
            route = form.save()
            messages.success(request, 'Rota başarıyla güncellendi.')
            return redirect('ferry_tickets:route_list')
    else:
        form = FerryRouteForm(instance=route)
    
    context = {
        'form': form,
        'route': route,
    }
    
    return render(request, 'ferry_tickets/routes/form.html', context)


@login_required
@require_ferry_ticket_permission('delete')
def route_delete(request, pk):
    """Rota Sil"""
    route = get_object_or_404(FerryRoute, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        route.is_deleted = True
        route.deleted_at = timezone.now()
        route.save()
        messages.success(request, 'Rota başarıyla silindi.')
        return redirect('ferry_tickets:route_list')
    
    context = {'route': route}
    return render(request, 'ferry_tickets/routes/delete_confirm.html', context)


# ==================== SEFER YÖNETİMİ ====================

@login_required
@require_ferry_ticket_permission('view')
def schedule_list(request):
    """Sefer Listesi"""
    schedules = FerrySchedule.objects.filter(is_deleted=False).select_related('route', 'ferry').order_by('-departure_date', '-departure_time')
    
    # Filtreler
    route_id = request.GET.get('route_id')
    if route_id:
        schedules = schedules.filter(route_id=route_id)
    
    departure_date = request.GET.get('departure_date')
    if departure_date:
        try:
            departure_date_obj = date.fromisoformat(departure_date)
            schedules = schedules.filter(departure_date=departure_date_obj)
        except (ValueError, TypeError):
            pass
    
    # Sayfalama
    paginator = Paginator(schedules, 25)
    page = request.GET.get('page')
    schedules = paginator.get_page(page)
    
    # Rotalar (filtre için)
    routes = FerryRoute.objects.filter(is_active=True, is_deleted=False).order_by('name')
    
    context = {
        'schedules': schedules,
        'routes': routes,
    }
    
    return render(request, 'ferry_tickets/schedules/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def schedule_create(request):
    """Yeni Sefer Oluştur"""
    if request.method == 'POST':
        form = FerryScheduleForm(request.POST)
        if form.is_valid():
            schedule = form.save()
            messages.success(request, 'Sefer başarıyla oluşturuldu.')
            return redirect('ferry_tickets:schedule_list')
    else:
        form = FerryScheduleForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'ferry_tickets/schedules/form.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def schedule_update(request, pk):
    """Sefer Güncelle"""
    schedule = get_object_or_404(FerrySchedule, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = FerryScheduleForm(request.POST, instance=schedule)
        if form.is_valid():
            schedule = form.save()
            messages.success(request, 'Sefer başarıyla güncellendi.')
            return redirect('ferry_tickets:schedule_list')
    else:
        form = FerryScheduleForm(instance=schedule)
    
    context = {
        'form': form,
        'schedule': schedule,
    }
    
    return render(request, 'ferry_tickets/schedules/form.html', context)


@login_required
@require_ferry_ticket_permission('delete')
def schedule_delete(request, pk):
    """Sefer Sil"""
    schedule = get_object_or_404(FerrySchedule, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        schedule.is_deleted = True
        schedule.deleted_at = timezone.now()
        schedule.save()
        messages.success(request, 'Sefer başarıyla silindi.')
        return redirect('ferry_tickets:schedule_list')
    
    context = {'schedule': schedule}
    return render(request, 'ferry_tickets/schedules/delete_confirm.html', context)


# ==================== API KONFİGÜRASYONLARI ====================

@login_required
@require_ferry_ticket_permission('view')
def api_configuration_list(request):
    """API Konfigürasyonları Listesi"""
    configs = FerryAPIConfiguration.objects.filter(is_deleted=False).order_by('name')
    
    context = {
        'configs': configs,
    }
    
    return render(request, 'ferry_tickets/api/configurations/list.html', context)


@login_required
@require_ferry_ticket_permission('add')
def api_configuration_create(request):
    """Yeni API Konfigürasyonu Oluştur"""
    if request.method == 'POST':
        form = FerryAPIConfigurationForm(request.POST)
        if form.is_valid():
            config = form.save()
            messages.success(request, 'API konfigürasyonu başarıyla oluşturuldu.')
            return redirect('ferry_tickets:api_configuration_list')
    else:
        form = FerryAPIConfigurationForm()
    
    context = {
        'form': form,
    }
    
    return render(request, 'ferry_tickets/api/configurations/form.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def api_configuration_update(request, pk):
    """API Konfigürasyonu Güncelle"""
    config = get_object_or_404(FerryAPIConfiguration, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        form = FerryAPIConfigurationForm(request.POST, instance=config)
        if form.is_valid():
            config = form.save()
            messages.success(request, 'API konfigürasyonu başarıyla güncellendi.')
            return redirect('ferry_tickets:api_configuration_list')
    else:
        form = FerryAPIConfigurationForm(instance=config)
    
    context = {
        'form': form,
        'config': config,
    }
    
    return render(request, 'ferry_tickets/api/configurations/form.html', context)


@login_required
@require_ferry_ticket_permission('delete')
def api_configuration_delete(request, pk):
    """API Konfigürasyonu Sil"""
    config = get_object_or_404(FerryAPIConfiguration, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        config.is_deleted = True
        config.deleted_at = timezone.now()
        config.save()
        messages.success(request, 'API konfigürasyonu başarıyla silindi.')
        return redirect('ferry_tickets:api_configuration_list')
    
    context = {'config': config}
    return render(request, 'ferry_tickets/api/configurations/delete_confirm.html', context)


@login_required
@require_ferry_ticket_permission('edit')
def api_configuration_sync(request, pk):
    """API Senkronizasyonu Başlat"""
    config = get_object_or_404(FerryAPIConfiguration, pk=pk, is_deleted=False)
    
    if request.method == 'POST':
        sync_type = request.POST.get('sync_type', 'schedules')  # schedules, tickets, prices
        
        try:
            from .integrations.base import BaseFerryAPI
            from .integrations.ferryos import FerryOSAPI
            
            # API instance oluştur
            if config.provider == 'ferryos':
                api = FerryOSAPI(
                    api_url=config.api_url,
                    api_key=config.api_key,
                    api_secret=config.api_secret,
                    config=config
                )
            else:
                messages.error(request, 'Bu API sağlayıcısı henüz desteklenmiyor.')
                return redirect('ferry_tickets:api_configuration_list')
            
            # Senkronizasyon başlat
            sync = FerryAPISync.objects.create(
                api_config=config,
                sync_type=sync_type,
                status='running',
                started_by=request.user,
            )
            
            # Arka planda senkronizasyon yapılabilir (Celery vb.)
            # Şimdilik basit bir şekilde yapıyoruz
            if sync_type == 'schedules':
                # Seferleri çek
                route_id = request.POST.get('route_id')
                departure_date_str = request.POST.get('departure_date')
                departure_date = None
                if departure_date_str:
                    try:
                        departure_date = date.fromisoformat(departure_date_str)
                    except (ValueError, TypeError):
                        pass
                
                schedules = api.get_schedules(
                    route_id=route_id if route_id else None,
                    departure_date=departure_date
                )
                
                # Seferleri kaydet
                import json
                sync_data = {
                    'schedules_count': len(schedules),
                    'schedules': schedules[:10] if len(schedules) > 10 else schedules  # İlk 10 seferi kaydet
                }
                sync.sync_data = sync_data
                sync.schedules_fetched = len(schedules)
                # ... (Seferleri FerrySchedule modeline kaydetme işlemi implement edilecek)
            
            sync.status = 'completed'
            sync.completed_at = timezone.now()
            sync.save()
            
            messages.success(request, 'API senkronizasyonu başarıyla tamamlandı.')
            return redirect('ferry_tickets:api_sync_detail', pk=sync.pk)
        
        except Exception as e:
            import logging
            import traceback
            logger = logging.getLogger(__name__)
            logger.error(f'API senkronizasyonu hatası: {str(e)}\n{traceback.format_exc()}')
            messages.error(request, f'API senkronizasyonu hatası: {str(e)}')
            return redirect('ferry_tickets:api_configuration_list')
    
    context = {
        'config': config,
        'routes': FerryRoute.objects.filter(is_active=True, is_deleted=False),
    }
    
    return render(request, 'ferry_tickets/api/configurations/sync.html', context)


# ==================== API SENKRONİZASYONLARI ====================

@login_required
@require_ferry_ticket_permission('view')
def api_sync_list(request):
    """API Senkronizasyonları Listesi"""
    syncs = FerryAPISync.objects.all().select_related('api_config', 'started_by').order_by('-started_at')
    
    # Sayfalama
    paginator = Paginator(syncs, 25)
    page = request.GET.get('page')
    syncs = paginator.get_page(page)
    
    context = {
        'syncs': syncs,
    }
    
    return render(request, 'ferry_tickets/api/syncs/list.html', context)


@login_required
@require_ferry_ticket_permission('view')
def api_sync_detail(request, pk):
    """API Senkronizasyonu Detay"""
    sync = get_object_or_404(FerryAPISync, pk=pk)
    
    context = {
        'sync': sync,
    }
    
    return render(request, 'ferry_tickets/api/syncs/detail.html', context)

def api_search_customer(request):
    """Müşteri arama API endpoint - Reception modülü gibi otomatik doldurma için"""
    search_query = request.GET.get('q', '').strip()
    
    if not search_query or len(search_query) < 2:
        return JsonResponse({'customer': None, 'results': []})
    
    from apps.tenant_apps.core.models import Customer
    from django.db.models import Q
    
    # Önce tam eşleşme ara (TC No, Email, Telefon) - otomatik doldurma için
    exact_customer = Customer.objects.filter(
        is_active=True,
        is_deleted=False
    ).filter(
        Q(tc_no=search_query) | Q(email=search_query) | Q(phone=search_query)
    ).first()
    
    if exact_customer:
        # Tam eşleşme bulundu - otomatik doldurma için müşteri bilgilerini döndür
        return JsonResponse({
            'customer': {
                'id': exact_customer.pk,
                'first_name': exact_customer.first_name or '',
                'last_name': exact_customer.last_name or '',
                'phone': exact_customer.phone or '',
                'email': exact_customer.email or '',
                'address': exact_customer.address or '',
                'tc_no': exact_customer.tc_no or '',
                'nationality': getattr(exact_customer, 'country', 'Türkiye') or 'Türkiye',
            },
            'results': []
        })
    
    # Tam eşleşme yoksa, benzer müşterileri listele
    customers = Customer.objects.filter(
        Q(tc_no__icontains=search_query) |
        Q(email__icontains=search_query) |
        Q(phone__icontains=search_query) |
        Q(first_name__icontains=search_query) |
        Q(last_name__icontains=search_query)
    ).filter(is_active=True, is_deleted=False)[:10]
    
    results = []
    for customer in customers:
        results.append({
            'id': customer.pk,
            'text': f"{customer.get_full_name()} ({customer.phone or customer.email or customer.tc_no or ''})",
            'first_name': customer.first_name or '',
            'last_name': customer.last_name or '',
            'phone': customer.phone or '',
            'email': customer.email or '',
            'tc_no': customer.tc_no or '',
            'address': customer.address or '',
            'nationality': getattr(customer, 'country', 'Türkiye') or 'Türkiye',
        })
    
    return JsonResponse({'customer': None, 'results': results})


def api_calculate_price(request):
    """Fiyat hesaplama API endpoint"""
    schedule_id = request.GET.get('schedule_id')
    adult_count = int(request.GET.get('adult_count', 0))
    child_count = int(request.GET.get('child_count', 0))
    infant_count = int(request.GET.get('infant_count', 0))
    vehicle_type = request.GET.get('vehicle_type', 'none')
    
    if not schedule_id:
        return JsonResponse({'error': 'schedule_id gerekli'}, status=400)
    
    try:
        schedule = FerrySchedule.objects.get(pk=schedule_id)
        
        total = Decimal('0')
        total += schedule.get_price_by_ticket_type('adult') * adult_count
        total += schedule.get_price_by_ticket_type('child') * child_count
        total += schedule.get_price_by_ticket_type('infant') * infant_count
        
        if vehicle_type != 'none':
            total += schedule.get_vehicle_price(vehicle_type)
        
        return JsonResponse({
            'total': str(total),
            'adult_price': str(schedule.adult_price),
            'child_price': str(schedule.child_price),
            'infant_price': str(schedule.infant_price),
            'vehicle_price': str(schedule.get_vehicle_price(vehicle_type)) if vehicle_type != 'none' else '0',
        })
    except FerrySchedule.DoesNotExist:
        return JsonResponse({'error': 'Sefer bulunamadı'}, status=404)


@login_required
@require_ferry_ticket_permission('view')
def api_schedules(request):
    """Sefer listesi API endpoint"""
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        route_id = request.GET.get('route_id')
        departure_date = request.GET.get('departure_date')
        schedule_id = request.GET.get('schedule_id')  # Tek sefer için
        
        # Tek sefer için
        if schedule_id:
            try:
                # schedule_id'yi integer'a çevir
                schedule_id = int(schedule_id)
                
                schedule = FerrySchedule.objects.filter(
                    is_active=True, 
                    is_cancelled=False
                ).select_related('route', 'ferry').get(pk=schedule_id)
                
                # available_passenger_seats değerini al
                available_seats = getattr(schedule, 'available_passenger_seats', 0) or 0
                
                # Fiyatları güvenli şekilde al
                def safe_decimal_to_str(value, default='0'):
                    if value is None:
                        return default
                    try:
                        return str(value)
                    except:
                        return default
                
                return JsonResponse({
                    'results': [{
                        'id': schedule.pk,
                        'route': str(schedule.route) if schedule.route else '',
                        'route_origin': schedule.route.origin if schedule.route and hasattr(schedule.route, 'origin') else '',
                        'route_destination': schedule.route.destination if schedule.route and hasattr(schedule.route, 'destination') else '',
                        'ferry': schedule.ferry.name if schedule.ferry and hasattr(schedule.ferry, 'name') else '',
                        'departure_date': schedule.departure_date.isoformat() if schedule.departure_date else '',
                        'departure_time': schedule.departure_time.strftime('%H:%M') if schedule.departure_time else '',
                        'arrival_date': schedule.arrival_date.isoformat() if schedule.arrival_date else '',
                        'arrival_time': schedule.arrival_time.strftime('%H:%M') if schedule.arrival_time else '',
                        'adult_price': safe_decimal_to_str(schedule.adult_price),
                        'child_price': safe_decimal_to_str(schedule.child_price),
                        'infant_price': safe_decimal_to_str(schedule.infant_price),
                        'student_price': safe_decimal_to_str(schedule.student_price),
                        'senior_price': safe_decimal_to_str(schedule.senior_price),
                        'disabled_price': safe_decimal_to_str(schedule.disabled_price),
                        'car_price': safe_decimal_to_str(schedule.car_price),
                        'motorcycle_price': safe_decimal_to_str(schedule.motorcycle_price),
                        'van_price': safe_decimal_to_str(schedule.van_price),
                        'truck_price': safe_decimal_to_str(schedule.truck_price),
                        'bus_price': safe_decimal_to_str(schedule.bus_price),
                        'caravan_price': safe_decimal_to_str(schedule.caravan_price),
                        'available_seats': int(available_seats) if available_seats else 0,
                    }]
                })
            except ValueError:
                return JsonResponse({'results': [], 'error': 'Geçersiz sefer ID'}, status=400)
            except FerrySchedule.DoesNotExist:
                return JsonResponse({'results': [], 'error': 'Sefer bulunamadı'}, status=404)
            except Exception as e:
                logger.error(f'api_schedules - schedule_id={schedule_id} hatası: {str(e)}', exc_info=True)
                return JsonResponse({'results': [], 'error': f'Bir hata oluştu: {str(e)}'}, status=500)
        
        # Liste için
        schedules = FerrySchedule.objects.filter(
            is_active=True, is_cancelled=False
        ).select_related('route', 'ferry')
        
        if route_id:
            schedules = schedules.filter(route_id=route_id)
        
        if departure_date:
            try:
                departure_date_obj = date.fromisoformat(departure_date)
                schedules = schedules.filter(departure_date=departure_date_obj)
            except (ValueError, TypeError):
                pass
        
        results = []
        for schedule in schedules[:50]:  # Maksimum 50 sefer
            results.append({
                'id': schedule.pk,
                'route': str(schedule.route) if schedule.route else '',
                'route_origin': schedule.route.origin if schedule.route and hasattr(schedule.route, 'origin') else '',
                'route_destination': schedule.route.destination if schedule.route and hasattr(schedule.route, 'destination') else '',
                'ferry': schedule.ferry.name if schedule.ferry and hasattr(schedule.ferry, 'name') else '',
                'departure_date': schedule.departure_date.isoformat() if schedule.departure_date else '',
                'departure_time': schedule.departure_time.strftime('%H:%M') if schedule.departure_time else '',
                'arrival_date': schedule.arrival_date.isoformat() if schedule.arrival_date else '',
                'arrival_time': schedule.arrival_time.strftime('%H:%M') if schedule.arrival_time else '',
                'adult_price': str(schedule.adult_price) if schedule.adult_price else '0',
                'child_price': str(schedule.child_price) if schedule.child_price else '0',
                'infant_price': str(schedule.infant_price) if schedule.infant_price else '0',
                'available_seats': getattr(schedule, 'available_passenger_seats', 0) or 0,
            })
        
        return JsonResponse({'results': results})
        
    except Exception as e:
        logger.error(f'api_schedules genel hatası: {str(e)}', exc_info=True)
        return JsonResponse({'results': [], 'error': f'Bir hata oluştu: {str(e)}'}, status=500)


def ticket_archived_list(request):
    """Arşivlenmiş Biletler"""
    tickets = FerryTicket.objects.filter(is_deleted=True).select_related(
        'schedule__route', 'schedule__ferry', 'customer'
    ).order_by('-deleted_at')
    
    # Sayfalama
    paginator = Paginator(tickets, 25)
    page = request.GET.get('page')
    tickets = paginator.get_page(page)
    
    context = {
        'tickets': tickets,
    }
    
    return render(request, 'ferry_tickets/tickets/archived_list.html', context)
