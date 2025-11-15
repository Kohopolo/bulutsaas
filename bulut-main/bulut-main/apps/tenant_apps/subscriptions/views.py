"""
Tenant Subscription Views
Kiracı üye paket yönetimi görünümleri
"""
import logging
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.utils import timezone
from django.db import connection
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_http_methods
from apps.subscriptions.models import Subscription, Payment
from apps.packages.models import Package, PackageModule
from apps.tenant_apps.core.models import TenantUser
from apps.tenant_apps.hotels.models import Hotel, Room

logger = logging.getLogger(__name__)


@login_required
def package_dashboard(request):
    """Paket yönetim ana sayfası"""
    # Mevcut tenant'ı al
    tenant = request.tenant
    
    # Aktif aboneliği al
    active_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not active_subscription:
        # Eğer aktif abonelik yoksa, en son aboneliği al
        active_subscription = Subscription.objects.filter(
            tenant=tenant
        ).order_by('-created_at').first()
    
    # Paket bilgileri
    package = active_subscription.package if active_subscription else tenant.package
    
    # Kullanım istatistikleri
    usage_stats = get_usage_statistics(tenant)
    
    # Paket limitleri - Modül bazlı limitlerden al
    limits = {}
    module_limits = {}
    
    if package:
        # Hotels modülü limitleri
        try:
            hotels_module = PackageModule.objects.filter(
                package=package,
                module__code='hotels',
                is_enabled=True
            ).first()
            if hotels_module and hotels_module.limits:
                module_limits['hotels'] = hotels_module.limits
                limits['max_hotels'] = hotels_module.limits.get('max_hotels', 0)
                limits['max_rooms'] = hotels_module.limits.get('max_room_numbers', 0)
                limits['max_reservations'] = hotels_module.limits.get('max_reservations', 0)
        except:
            pass
        
        # Tours modülü limitleri
        try:
            tours_module = PackageModule.objects.filter(
                package=package,
                module__code='tours',
                is_enabled=True
            ).first()
            if tours_module and tours_module.limits:
                module_limits['tours'] = tours_module.limits
                limits['max_tours'] = tours_module.limits.get('max_tours', 0)
        except:
            pass
    
    # Genel limitler (modül bazlı değilse varsayılan değerler)
    if 'max_hotels' not in limits:
        limits['max_hotels'] = 0
    if 'max_rooms' not in limits:
        limits['max_rooms'] = 0
    if 'max_users' not in limits:
        limits['max_users'] = 0
    if 'max_reservations_per_month' not in limits:
        limits['max_reservations_per_month'] = limits.get('max_reservations', 0)
    
    # Limit kullanım yüzdeleri
    usage_percentages = {}
    limit_mapping = {
        'max_hotels': 'current_hotels',
        'max_rooms': 'current_rooms',
        'max_users': 'current_users',
        'max_reservations_per_month': 'current_reservations_this_month',
        'max_tours': 'current_tours',
    }
    
    for key, limit in limits.items():
        usage_key = limit_mapping.get(key)
        if usage_key:
            current = usage_stats.get(usage_key, 0)
            percentage = (current / limit * 100) if limit > 0 else 0
            usage_percentages[key] = min(100, percentage)
    
    # Paket modülleri
    package_modules = []
    if package:
        package_modules = PackageModule.objects.filter(
            package=package,
            is_enabled=True
        ).select_related('module')
    
    context = {
        'tenant': tenant,
        'subscription': active_subscription,
        'package': package,
        'usage_stats': usage_stats,
        'limits': limits,
        'module_limits': module_limits,
        'usage_percentages': usage_percentages,
        'package_modules': package_modules,
    }
    
    return render(request, 'tenant/subscriptions/dashboard.html', context)


@login_required
def package_details(request):
    """Paket detay sayfası"""
    tenant = request.tenant
    
    # Aktif abonelik
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not subscription:
        messages.error(request, 'Aktif abonelik bulunamadı.')
        return redirect('tenant_subscriptions:dashboard')
    
    package = subscription.package
    
    # Paket modülleri ve yetkileri
    package_modules = PackageModule.objects.filter(
        package=package,
        is_enabled=True
    ).select_related('module')
    
    # Modül bazlı limitler
    module_limits = {}
    for pm in package_modules:
        if pm.limits:
            module_limits[pm.module.code] = pm.limits
    
    # Ödeme geçmişi
    payments = Payment.objects.filter(
        subscription=subscription
    ).order_by('-created_at')[:10]
    
    context = {
        'subscription': subscription,
        'package': package,
        'package_modules': package_modules,
        'module_limits': module_limits,
        'payments': payments,
    }
    
    return render(request, 'tenant/subscriptions/details.html', context)


@login_required
def package_upgrade(request):
    """Paket yükseltme sayfası"""
    tenant = request.tenant
    
    # Mevcut paket
    current_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    current_package = current_subscription.package if current_subscription else tenant.package
    
    # Tüm aktif paketler (mevcut paket hariç)
    available_packages = Package.objects.filter(
        is_active=True
    ).exclude(id=current_package.id if current_package else None).order_by('sort_order', 'price_monthly')
    
    # Her paket için modül limitlerini al (template'de kullanım için düzenli yapı)
    package_module_limits_list = []
    for package in available_packages:
        package_modules = PackageModule.objects.filter(
            package=package,
            is_enabled=True
        ).select_related('module')
        limits_dict = {}
        for pm in package_modules:
            if pm.limits:
                limits_dict[pm.module.code] = pm.limits
        package_module_limits_list.append({
            'package_id': package.id,
            'limits': limits_dict
        })
    
    # Mevcut paket için de modül limitlerini al
    current_package_limits = {}
    if current_package:
        current_package_modules = PackageModule.objects.filter(
            package=current_package,
            is_enabled=True
        ).select_related('module')
        for pm in current_package_modules:
            if pm.limits:
                current_package_limits[pm.module.code] = pm.limits
    
    # Paket ve limitleri eşleştir
    package_limits_map = {item['package_id']: item['limits'] for item in package_module_limits_list}
    
    # Super Admin'in ödeme gateway'ini bul (paket yükseltme için)
    from apps.payments.models import SuperAdminPaymentGateway
    from apps.payments.views import get_superadmin_gateway
    from django_tenants.utils import schema_context, get_public_schema_name
    
    superadmin_gateway = None
    with schema_context(get_public_schema_name()):
        superadmin_gateway = get_superadmin_gateway()
    
    context = {
        'current_package': current_package,
        'current_subscription': current_subscription,
        'available_packages': available_packages,
        'package_limits_map': package_limits_map,
        'current_package_limits': current_package_limits,
        'superadmin_gateway': superadmin_gateway,
    }
    
    return render(request, 'tenant/subscriptions/upgrade.html', context)


@login_required
def package_upgrade_payment(request, package_id):
    """Paket yükseltme ödeme sayfası - Super Admin gateway kullanır"""
    tenant = request.tenant
    
    # Paketi bul
    from apps.packages.models import Package
    package = get_object_or_404(Package, id=package_id, is_active=True)
    
    # Mevcut abonelik
    current_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    # Super Admin'in ödeme gateway'ini bul (paket yükseltme için)
    from apps.payments.models import SuperAdminPaymentGateway
    from apps.payments.views import get_superadmin_gateway, get_gateway_instance
    from apps.payments.models import PaymentTransaction
    from django_tenants.utils import get_tenant_model, schema_context, get_public_schema_name
    import uuid
    
    # Public schema'da çalış
    superadmin_gateway = None
    public_tenant = None
    with schema_context(get_public_schema_name()):
        TenantModel = get_tenant_model()
        public_tenant = TenantModel.objects.filter(schema_name=tenant.schema_name).first()
        
        # Super Admin gateway'ini al
        superadmin_gateway = get_superadmin_gateway()
    
    if request.method == 'POST':
        period = request.POST.get('period', 'monthly')
        
        # Ödeme tutarını hesapla
        if period == 'monthly':
            amount = package.price_monthly
        elif period == 'yearly':
            amount = package.price_yearly or (package.price_monthly * 12)
        else:
            amount = package.price_monthly
        
        # Ödeme gateway yoksa hata ver
        if not superadmin_gateway:
            messages.error(request, 'Ödeme gateway ayarları bulunamadı. Lütfen sistem yöneticisine başvurun.')
            return redirect('tenant_subscriptions:upgrade')
        
        # Ödeme işlemini başlat
        gateway = get_gateway_instance(superadmin_gateway.gateway.code, superadmin_gateway)
        
        # Transaction oluştur (public schema'da)
        with schema_context(get_public_schema_name()):
            transaction_id = f"UPGRADE-{uuid.uuid4().hex[:16].upper()}"
            payment_transaction = PaymentTransaction.objects.create(
                tenant=public_tenant,
                gateway=superadmin_gateway.gateway,
                transaction_id=transaction_id,
                order_id=f"UPGRADE-{package.id}",
                amount=amount,
                currency=package.currency,
                status='pending',
                payment_method='credit_card',
                source_module='subscriptions',
                source_id=package.id,
                source_reference=f"Package Upgrade - {package.name} - {tenant.name}",
                notes=f"Period: {period}",
            )
        
        # Müşteri bilgileri
        customer_info = {
            'name': tenant.name.split()[0] if tenant.name else '',
            'surname': ' '.join(tenant.name.split()[1:]) if tenant.name and len(tenant.name.split()) > 1 else '',
            'email': tenant.owner_email or '',
            'phone': tenant.phone or '',
        }
        
        # Müşteri IP adresi (PayTR iFrame API için gerekli)
        user_ip = request.META.get('HTTP_X_FORWARDED_FOR', request.META.get('REMOTE_ADDR', ''))
        if ',' in user_ip:
            user_ip = user_ip.split(',')[0].strip()
        
        # Ödeme formu oluştur
        try:
            from decimal import Decimal
            result = gateway.create_payment(
                amount=Decimal(str(amount)),
                currency=package.currency,
                order_id=transaction_id,
                customer_info=customer_info,
                callback_url=request.build_absolute_uri(f'/subscriptions/upgrade-payment-callback/{payment_transaction.id}/'),
                success_url=request.build_absolute_uri('/subscriptions/upgrade-success/'),
                fail_url=request.build_absolute_uri('/subscriptions/upgrade-fail/'),
                user_ip=user_ip,  # PayTR iFrame API için gerekli
                basket_items=[{
                    'name': f'{package.name} - {period} Yükseltme',
                    'price': str(amount),
                    'quantity': 1
                }],
            )
            
            if result.get('success'):
                payment_transaction.gateway_transaction_id = result.get('transaction_id', '')
                payment_transaction.save()
                
                # İyzico HTML içerik döndürüyor, PayTR Direkt API ve iFrame API HTML form döndürüyor
                payment_url = result.get('payment_url', '')
                threeDSHtmlContent = result.get('threeDSHtmlContent', '')
                html_content = result.get('html_content', '')
                iframe_token = result.get('iframe_token', '')
                
                # Eğer HTML içerik varsa (İyzico, PayTR Direkt API veya PayTR iFrame API), render et
                if html_content:
                    # PayTR Direkt API veya iFrame API HTML form
                    from django.http import HttpResponse
                    return HttpResponse(html_content)
                elif threeDSHtmlContent:
                    # İyzico HTML içerik
                    from django.http import HttpResponse
                    return HttpResponse(threeDSHtmlContent)
                elif payment_url and payment_url.startswith('<'):
                    # payment_url HTML içerik ise
                    from django.http import HttpResponse
                    return HttpResponse(payment_url)
                elif payment_url or iframe_token:
                    # URL ise (PayTR iFrame veya diğer), redirect et
                    # PayTR iFrame için token varsa URL oluştur
                    if iframe_token and not payment_url:
                        payment_url = f"https://www.paytr.com/odeme/guvenli/{iframe_token}"
                    return redirect(payment_url)
                else:
                    messages.error(request, "Ödeme URL'si oluşturulamadı.")
                    return redirect('tenant_subscriptions:upgrade')
            else:
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', '')
                payment_transaction.save()
                messages.error(request, f"Ödeme oluşturulamadı: {result.get('error')}")
        except Exception as e:
            payment_transaction.status = 'failed'
            payment_transaction.error_message = str(e)
            payment_transaction.save()
            messages.error(request, f"Ödeme işlemi başlatılamadı: {str(e)}")
    
    context = {
        'package': package,
        'current_subscription': current_subscription,
        'superadmin_gateway': superadmin_gateway,
    }
    
    return render(request, 'tenant/subscriptions/upgrade_payment.html', context)


@login_required
def package_renew(request):
    """Paket yenileme sayfası - Ödeme ile"""
    tenant = request.tenant
    
    # Aktif abonelik
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not subscription:
        messages.error(request, 'Aktif abonelik bulunamadı.')
        return redirect('tenant_subscriptions:dashboard')
    
    package = subscription.package
    
    # Super Admin'in ödeme gateway'ini bul (paket yenileme/yükseltme için)
    from apps.payments.models import PaymentGateway, SuperAdminPaymentGateway
    from apps.payments.views import get_superadmin_gateway
    from django_tenants.utils import get_tenant_model, schema_context, get_public_schema_name
    
    # Public schema'da çalış
    superadmin_gateway = None
    public_tenant = None
    with schema_context(get_public_schema_name()):
        TenantModel = get_tenant_model()
        public_tenant = TenantModel.objects.filter(schema_name=tenant.schema_name).first()
        
        # Super Admin gateway'ini al
        superadmin_gateway = get_superadmin_gateway()
    
    if request.method == 'POST':
        period = request.POST.get('period', 'monthly')
        
        # Ödeme tutarını hesapla
        if period == 'monthly':
            amount = package.price_monthly
        elif period == 'yearly':
            amount = package.price_yearly or (package.price_monthly * 12)
        else:
            amount = package.price_monthly
        
        # Ödeme gateway yoksa hata ver
        if not superadmin_gateway:
            messages.error(request, 'Ödeme gateway ayarları bulunamadı. Lütfen sistem yöneticisine başvurun.')
            context = {
                'subscription': subscription,
                'package': package,
                'superadmin_gateway': None,
            }
            return render(request, 'tenant/subscriptions/renew.html', context)
        
        # Ödeme işlemini başlat
        from apps.payments.views import get_gateway_instance
        from apps.payments.models import PaymentTransaction
        import uuid
        
        gateway = get_gateway_instance(superadmin_gateway.gateway.code, superadmin_gateway)
        
        # Transaction oluştur (public schema'da)
        with schema_context(get_public_schema_name()):
            transaction_id = f"RENEW-{uuid.uuid4().hex[:16].upper()}"
            payment_transaction = PaymentTransaction.objects.create(
                tenant=public_tenant,
                gateway=superadmin_gateway.gateway,
                transaction_id=transaction_id,
                order_id=f"RENEW-{subscription.id}",
                amount=amount,
                currency=package.currency,
                status='pending',
                payment_method='credit_card',
                source_module='subscriptions',
                source_id=subscription.id,
                source_reference=f"Subscription Renewal - {subscription.tenant.name}",
                notes=f"Period: {period}",  # Period bilgisini notes'a kaydet
            )
        
        # Müşteri bilgileri
        customer_info = {
            'name': tenant.name.split()[0] if tenant.name else '',
            'surname': ' '.join(tenant.name.split()[1:]) if tenant.name and len(tenant.name.split()) > 1 else '',
            'email': tenant.owner_email or '',
            'phone': tenant.phone or '',
        }
        
        # Müşteri IP adresi (PayTR iFrame API için gerekli)
        user_ip = request.META.get('HTTP_X_FORWARDED_FOR', request.META.get('REMOTE_ADDR', ''))
        if ',' in user_ip:
            user_ip = user_ip.split(',')[0].strip()
        
        # Ödeme formu oluştur
        try:
            from decimal import Decimal
            result = gateway.create_payment(
                amount=Decimal(str(amount)),
                currency=package.currency,
                order_id=transaction_id,
                customer_info=customer_info,
                callback_url=request.build_absolute_uri(f'/subscriptions/payment-callback/{payment_transaction.id}/'),
                success_url=request.build_absolute_uri('/subscriptions/renew-success/'),
                fail_url=request.build_absolute_uri('/subscriptions/renew-fail/'),
                user_ip=user_ip,  # PayTR iFrame API için gerekli
                basket_items=[{
                    'name': f'{package.name} - {period} Yenileme',
                    'price': str(amount),
                    'quantity': 1
                }],
            )
            
            if result.get('success'):
                payment_transaction.gateway_transaction_id = result.get('transaction_id', '')
                payment_transaction.save()
                
                # İyzico HTML içerik döndürüyor, PayTR Direkt API ve iFrame API HTML form döndürüyor
                payment_url = result.get('payment_url', '')
                threeDSHtmlContent = result.get('threeDSHtmlContent', '')
                html_content = result.get('html_content', '')
                iframe_token = result.get('iframe_token', '')
                
                # Debug: Dönen değerleri logla
                logger.info(f"Payment result - html_content length: {len(html_content) if html_content else 0}, "
                          f"payment_url: {payment_url}, iframe_token: {iframe_token}")
                
                # Eğer HTML içerik varsa (İyzico, PayTR Direkt API veya PayTR iFrame API), render et
                if html_content:
                    # PayTR Direkt API veya iFrame API HTML form
                    logger.info("Returning HTML content for PayTR iFrame/Direct API")
                    from django.http import HttpResponse
                    return HttpResponse(html_content, content_type='text/html; charset=utf-8')
                elif threeDSHtmlContent:
                    # İyzico HTML içerik
                    logger.info("Returning 3DS HTML content for Iyzico")
                    from django.http import HttpResponse
                    return HttpResponse(threeDSHtmlContent, content_type='text/html; charset=utf-8')
                elif payment_url and payment_url.startswith('<'):
                    # payment_url HTML içerik ise
                    logger.info("Returning payment_url as HTML content")
                    from django.http import HttpResponse
                    return HttpResponse(payment_url, content_type='text/html; charset=utf-8')
                elif payment_url or iframe_token:
                    # URL ise (PayTR iFrame veya diğer), redirect et
                    # PayTR iFrame için token varsa URL oluştur
                    if iframe_token and not payment_url:
                        payment_url = f"https://www.paytr.com/odeme/guvenli/{iframe_token}"
                    logger.info(f"Redirecting to payment URL: {payment_url}")
                    return redirect(payment_url)
                else:
                    logger.error("No payment URL or HTML content received")
                    messages.error(request, "Ödeme URL'si oluşturulamadı.")
                    return redirect('tenant_subscriptions:renew')
            else:
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', '')
                payment_transaction.save()
                error_msg = result.get('error', 'Bilinmeyen hata')
                logger.error(f"Payment creation failed: {error_msg}")
                messages.error(request, f"Ödeme oluşturulamadı: {error_msg}")
        except Exception as e:
            payment_transaction.status = 'failed'
            payment_transaction.error_message = str(e)
            payment_transaction.save()
            messages.error(request, f"Ödeme işlemi başlatılamadı: {str(e)}")
    
    context = {
        'subscription': subscription,
        'package': package,
        'superadmin_gateway': superadmin_gateway,
    }
    
    return render(request, 'tenant/subscriptions/renew.html', context)


@login_required
def payment_history(request):
    """Ödeme geçmişi"""
    tenant = request.tenant
    
    # Tüm abonelikler
    subscriptions = Subscription.objects.filter(tenant=tenant).order_by('-created_at')
    
    # Tüm ödemeler
    payments = Payment.objects.filter(
        subscription__tenant=tenant
    ).select_related('subscription').order_by('-created_at')
    
    context = {
        'subscriptions': subscriptions,
        'payments': payments,
    }
    
    return render(request, 'tenant/subscriptions/payment_history.html', context)


@csrf_exempt
@require_http_methods(["POST", "GET"])
def renew_payment_callback(request, transaction_id):
    """Subscription renew ödeme callback"""
    from apps.payments.models import PaymentTransaction
    from apps.payments.views import get_gateway_instance
    from apps.payments.models import TenantPaymentGateway
    from django.db import transaction as db_transaction
    from django.views.decorators.csrf import csrf_exempt
    from django.views.decorators.http import require_http_methods
    from django_tenants.utils import schema_context, get_public_schema_name
    
    # Public schema'da çalış
    with schema_context(get_public_schema_name()):
        try:
            payment_transaction = PaymentTransaction.objects.get(id=transaction_id)
        except PaymentTransaction.DoesNotExist:
            logger.error(f'Renew payment callback: Transaction bulunamadı - ID: {transaction_id}')
            return redirect('tenant_subscriptions:renew_fail')
        
        # Subscription'ı bul
        if not payment_transaction.source_id or payment_transaction.source_module != 'subscriptions':
            logger.error(f'Renew payment callback: Subscription bilgisi bulunamadı - Transaction: {payment_transaction.transaction_id}')
            return redirect('tenant_subscriptions:renew_fail')
        
        try:
            subscription = Subscription.objects.get(id=payment_transaction.source_id)
        except Subscription.DoesNotExist:
            logger.error(f'Renew payment callback: Subscription bulunamadı - ID: {payment_transaction.source_id}')
            return redirect('tenant_subscriptions:renew_fail')
        
        # Super Admin gateway'ini bul (paket yenileme/yükseltme için)
        from apps.payments.models import SuperAdminPaymentGateway
        superadmin_gateway = SuperAdminPaymentGateway.objects.filter(
            gateway=payment_transaction.gateway,
            is_active=True
        ).first()
        
        if not superadmin_gateway:
            logger.error(f'Renew payment callback: Super Admin gateway config bulunamadı')
            return redirect('tenant_subscriptions:renew_fail')
    
    try:
        gateway = get_gateway_instance(payment_transaction.gateway.code, superadmin_gateway)
        
        # Ödeme durumunu kontrol et
        result = gateway.verify_payment(
            payment_transaction.gateway_transaction_id or payment_transaction.transaction_id,
            **request.POST.dict() if request.method == 'POST' else request.GET.dict()
        )
        
        with db_transaction.atomic():
            if result.get('success') and result.get('status') == 'completed':
                # Ödeme başarılı - Subscription'ı yenile
                payment_transaction.status = 'completed'
                payment_transaction.payment_date = timezone.now()
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                # Period'u notes'tan al
                period = 'monthly'  # Varsayılan
                if payment_transaction.notes and 'Period:' in payment_transaction.notes:
                    try:
                        period = payment_transaction.notes.split('Period:')[1].strip().split()[0]
                    except:
                        pass
                
                # Subscription'ı yenile
                subscription.renew(period=period)
                
                # Payment kaydı oluştur
                Payment.objects.create(
                    subscription=subscription,
                    amount=payment_transaction.amount,
                    currency=payment_transaction.currency,
                    status='completed',
                    payment_method=payment_transaction.payment_method,
                    payment_date=timezone.now(),
                )
                
                logger.info(f'Subscription renew başarılı: {subscription.id} - {payment_transaction.amount} {payment_transaction.currency}')
                
                return redirect('tenant_subscriptions:renew_success')
            else:
                # Ödeme başarısız
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', 'Ödeme başarısız')
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                logger.warning(f'Subscription renew başarısız: {subscription.id} - {result.get("error", "Bilinmeyen hata")}')
                
                return redirect('tenant_subscriptions:renew_fail')
    
    except Exception as e:
        logger.error(f'Renew payment callback hatası: {str(e)}', exc_info=True)
        return redirect('tenant_subscriptions:renew_fail')


@login_required
def renew_success(request):
    """Subscription renew başarılı"""
    messages.success(request, 'Aboneliğiniz başarıyla yenilendi!')
    return redirect('tenant_subscriptions:dashboard')


@login_required
def renew_fail(request):
    """Subscription renew başarısız"""
    messages.error(request, 'Ödeme işlemi başarısız oldu. Lütfen tekrar deneyin.')
    return redirect('tenant_subscriptions:renew')


@csrf_exempt
@require_http_methods(["POST", "GET"])
def upgrade_payment_callback(request, transaction_id):
    """Package upgrade ödeme callback"""
    from apps.payments.models import PaymentTransaction
    from apps.payments.views import get_gateway_instance
    from apps.payments.models import SuperAdminPaymentGateway
    from apps.packages.models import Package
    from django.db import transaction as db_transaction
    from django_tenants.utils import schema_context, get_public_schema_name
    
    # Public schema'da çalış
    with schema_context(get_public_schema_name()):
        try:
            payment_transaction = PaymentTransaction.objects.get(id=transaction_id)
        except PaymentTransaction.DoesNotExist:
            logger.error(f'Upgrade payment callback: Transaction bulunamadı - ID: {transaction_id}')
            return redirect('tenant_subscriptions:upgrade_fail')
        
        # Package'ı bul
        if not payment_transaction.source_id or payment_transaction.source_module != 'subscriptions':
            logger.error(f'Upgrade payment callback: Package bilgisi bulunamadı - Transaction: {payment_transaction.transaction_id}')
            return redirect('tenant_subscriptions:upgrade_fail')
        
        try:
            package = Package.objects.get(id=payment_transaction.source_id)
        except Package.DoesNotExist:
            logger.error(f'Upgrade payment callback: Package bulunamadı - ID: {payment_transaction.source_id}')
            return redirect('tenant_subscriptions:upgrade_fail')
        
        # Super Admin gateway'ini bul (paket yükseltme için)
        superadmin_gateway = SuperAdminPaymentGateway.objects.filter(
            gateway=payment_transaction.gateway,
            is_active=True
        ).first()
        
        if not superadmin_gateway:
            logger.error(f'Upgrade payment callback: Super Admin gateway config bulunamadı')
            return redirect('tenant_subscriptions:upgrade_fail')
    
    try:
        gateway = get_gateway_instance(payment_transaction.gateway.code, superadmin_gateway)
        
        # Ödeme durumunu kontrol et
        result = gateway.verify_payment(
            payment_transaction.gateway_transaction_id or payment_transaction.transaction_id,
            **request.POST.dict() if request.method == 'POST' else request.GET.dict()
        )
        
        with db_transaction.atomic():
            if result.get('success') and result.get('status') == 'completed':
                # Ödeme başarılı - Paketi yükselt
                payment_transaction.status = 'completed'
                payment_transaction.payment_date = timezone.now()
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                # Period'u notes'tan al
                period = 'monthly'  # Varsayılan
                if payment_transaction.notes and 'Period:' in payment_transaction.notes:
                    try:
                        period = payment_transaction.notes.split('Period:')[1].strip().split()[0]
                    except:
                        pass
                
                # Subscription'ı güncelle veya oluştur
                tenant = payment_transaction.tenant
                subscription, created = Subscription.objects.get_or_create(
                    tenant=tenant,
                    defaults={
                        'package': package,
                        'status': 'active',
                        'period': period,
                    }
                )
                
                if not created:
                    # Mevcut subscription'ı güncelle
                    subscription.package = package
                    subscription.period = period
                    subscription.status = 'active'
                    subscription.renew(period=period)
                
                # Payment kaydı oluştur
                Payment.objects.create(
                    subscription=subscription,
                    amount=payment_transaction.amount,
                    currency=payment_transaction.currency,
                    status='completed',
                    payment_method=payment_transaction.payment_method,
                    payment_date=timezone.now(),
                )
                
                logger.info(f'Package upgrade başarılı: {package.id} - {payment_transaction.amount} {payment_transaction.currency}')
                
                return redirect('tenant_subscriptions:upgrade_success')
            else:
                # Ödeme başarısız
                payment_transaction.status = 'failed'
                payment_transaction.error_message = result.get('error', 'Ödeme başarısız')
                payment_transaction.gateway_response = result
                payment_transaction.save()
                
                logger.warning(f'Package upgrade başarısız: {package.id} - {result.get("error", "Bilinmeyen hata")}')
                
                return redirect('tenant_subscriptions:upgrade_fail')
    
    except Exception as e:
        logger.error(f'Upgrade payment callback hatası: {str(e)}', exc_info=True)
        return redirect('tenant_subscriptions:upgrade_fail')


@login_required
def upgrade_success(request):
    """Package upgrade başarılı"""
    messages.success(request, 'Paketiniz başarıyla yükseltildi!')
    return redirect('tenant_subscriptions:dashboard')


@login_required
def upgrade_fail(request):
    """Package upgrade başarısız"""
    messages.error(request, 'Ödeme işlemi başarısız oldu. Lütfen tekrar deneyin.')
    return redirect('tenant_subscriptions:upgrade')


def get_usage_statistics(tenant):
    """Tenant kullanım istatistiklerini hesapla"""
    stats = {
        'current_hotels': 0,
        'current_rooms': 0,
        'current_users': 0,
        'current_reservations_this_month': 0,
        'current_storage_gb': 0,
        'current_api_calls_today': 0,
        # Tur modülü istatistikleri
        'current_tours': 0,
        'current_tour_users': 0,
        'current_tour_reservations': 0,
        'current_tour_reservations_this_month': 0,
    }
    
    # Tenant schema'sında çalış
    with connection.cursor() as cursor:
        # Kullanıcı sayısı
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_core_tenantuser WHERE is_active = true")
            stats['current_users'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Otel sayısı (hotels app'ten) - silinmemiş oteller
        try:
            stats['current_hotels'] = Hotel.objects.filter(is_deleted=False).count()
        except Exception as e:
            # Fallback: SQL sorgusu
            try:
                cursor.execute("SELECT COUNT(*) FROM tenant_apps_hotels_hotel WHERE is_deleted = false")
                stats['current_hotels'] = cursor.fetchone()[0] or 0
            except:
                pass
        
        # Oda sayısı (hotels app'ten) - silinmemiş odalar
        try:
            stats['current_rooms'] = Room.objects.filter(is_deleted=False).count()
        except Exception as e:
            # Fallback: SQL sorgusu
            try:
                cursor.execute("SELECT COUNT(*) FROM tenant_apps_hotels_room WHERE is_deleted = false")
                stats['current_rooms'] = cursor.fetchone()[0] or 0
            except:
                pass
        
        # Bu ayki rezervasyon sayısı
        try:
            from datetime import datetime
            current_month = datetime.now().month
            cursor.execute(
                "SELECT COUNT(*) FROM tenant_apps_reservations_reservation WHERE EXTRACT(MONTH FROM created_at) = %s",
                [current_month]
            )
            stats['current_reservations_this_month'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur sayısı
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_apps_tours_tour WHERE is_active = true AND is_deleted = false")
            stats['current_tours'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur rezervasyon sayısı (toplam)
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_apps_tours_tourreservation WHERE is_deleted = false")
            stats['current_tour_reservations'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Bu ayki tur rezervasyon sayısı
        try:
            from datetime import datetime
            current_month = datetime.now().month
            current_year = datetime.now().year
            cursor.execute(
                "SELECT COUNT(*) FROM tenant_apps_tours_tourreservation WHERE EXTRACT(MONTH FROM created_at) = %s AND EXTRACT(YEAR FROM created_at) = %s AND is_deleted = false",
                [current_month, current_year]
            )
            stats['current_tour_reservations_this_month'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur modülüne erişimi olan kullanıcı sayısı
        # (Bu karmaşık bir sorgu, Python tarafında hesaplamak daha iyi)
        try:
            # Tur modülünde yetkisi olan kullanıcılar
            from apps.modules.models import Module
            from apps.tenant_apps.core.models import Permission, RolePermission, UserRole
            
            tour_module = Module.objects.filter(code='tours', is_active=True).first()
            if tour_module:
                tour_permissions = Permission.objects.filter(
                    module=tour_module,
                    is_active=True
                )
                
                user_ids_with_permission = UserRole.objects.filter(
                    role__role_permissions__permission__in=tour_permissions,
                    is_active=True
                ).values_list('user__user__id', flat=True).distinct()
                
                stats['current_tour_users'] = len(set(user_ids_with_permission))
        except:
            pass
    
    return stats

