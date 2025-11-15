"""
Ödeme İşlemleri Views
"""
import uuid
import re
import logging
from decimal import Decimal
from datetime import timedelta
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib import messages
from django.views.decorators.csrf import csrf_exempt
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.utils.decorators import method_decorator
from django.utils import timezone
from django.views import View
from django.db import transaction
from django.core.mail import send_mail
from django.conf import settings
from django_tenants.utils import schema_context, get_public_schema_name
from apps.payments.models import PaymentGateway, TenantPaymentGateway, SuperAdminPaymentGateway, PaymentTransaction, PaymentWebhook
from apps.payments.gateways import IyzicoGateway, PayTRGateway, NestPayGateway
from apps.subscriptions.models import Subscription
from apps.packages.models import Package
from apps.tenants.models import Tenant

logger = logging.getLogger(__name__)


def get_superadmin_gateway():
    """
    Super Admin'in aktif ödeme gateway'ini döndürür
    Paket yenileme/yükseltme ödemeleri için kullanılır
    """
    superadmin_gateway = SuperAdminPaymentGateway.objects.filter(
        is_active=True
    ).select_related('gateway').first()
    
    if not superadmin_gateway:
        logger.warning('Super Admin ödeme gateway ayarları bulunamadı.')
        return None
    
    return superadmin_gateway


def get_gateway_instance(gateway_code: str, config):
    """Get gateway instance by code
    
    Args:
        gateway_code: Gateway kodu (iyzico, paytr, vb.)
        config: TenantPaymentGateway veya SuperAdminPaymentGateway instance
    """
    from .gateways import GarantiGateway, DenizbankGateway, PayUGateway
    
    gateway_config = {
        'api_key': config.api_key,
        'secret_key': config.secret_key,
        'merchant_id': config.merchant_id,
        'store_key': config.store_key,
        'is_test_mode': config.is_test_mode,
    }
    
    # PayTR için API tipini ekle
    if gateway_code == 'paytr':
        # PayTR API tipi kontrolü - boş string veya None ise 'direct' kullan
        paytr_api_type = getattr(config, 'paytr_api_type', None)
        if not paytr_api_type or (isinstance(paytr_api_type, str) and paytr_api_type.strip() == ''):
            paytr_api_type = 'direct'
        gateway_config['paytr_api_type'] = paytr_api_type
        # Debug: API tipini logla
        logger.info(f"PayTR API type from config: {paytr_api_type} (original: {getattr(config, 'paytr_api_type', None)})")
    
    if gateway_code == 'iyzico':
        return IyzicoGateway(gateway_config)
    elif gateway_code == 'paytr':
        return PayTRGateway(gateway_config)
    elif gateway_code == 'nestpay':
        gateway_config['bank_code'] = config.settings.get('bank_code', 'isbank')
        return NestPayGateway(gateway_config)
    elif gateway_code == 'garanti':
        return GarantiGateway(gateway_config)
    elif gateway_code == 'denizbank':
        return DenizbankGateway(gateway_config)
    elif gateway_code == 'payu':
        return PayUGateway(gateway_config)
    # Diğer bankalar için NestPay kullan (banka kodunu settings'den al)
    elif gateway_code in ['isbank', 'akbank', 'ziraat', 'yapikredi', 'halkbank', 
                          'qnbfinansbank', 'teb', 'sekerbank', 'ingbank', 'vakifbank',
                          'fibabanka', 'albaraka', 'kuveytturk', 'ziraatkatilim', 'vakifkatilim']:
        gateway_config['bank_code'] = gateway_code
        return NestPayGateway(gateway_config)
    else:
        raise ValueError(f"Unknown gateway: {gateway_code}")


@require_http_methods(["GET", "POST"])
def initiate_payment(request, package_id):
    """Ödeme başlat"""
    package = get_object_or_404(Package, id=package_id, is_active=True)
    
    # Public schema'da çalışıyoruz - İlk aktif tenant'ı al
    tenant = Tenant.objects.filter(is_active=True).first()
    
    if not tenant:
        messages.error(request, 'Sistem yapılandırması eksik. Lütfen admin panelinden tenant oluşturun.')
        return redirect('landing')
    
    tenant_gateway = TenantPaymentGateway.objects.filter(
        tenant=tenant,
        is_active=True
    ).first()
    
    if not tenant_gateway:
        messages.warning(request, 'Ödeme gateway ayarları bulunamadı. Lütfen admin panelinden gateway ayarlarını yapın.')
        return redirect('landing')
    
    if request.method == 'POST':
        # Ödeme oluştur
        gateway = get_gateway_instance(tenant_gateway.gateway.code, tenant_gateway)
        
        # Transaction oluştur
        transaction_id = f"TXN-{uuid.uuid4().hex[:16].upper()}"
        payment_transaction = PaymentTransaction.objects.create(
            tenant=tenant,  # Geçici olarak ilk tenant, ödeme sonrası yeni tenant oluşturulacak
            gateway=tenant_gateway.gateway,
            transaction_id=transaction_id,
            order_id=f"PKG-{package.id}",
            amount=package.price_monthly,
            currency=package.currency,
            status='pending',
            # Müşteri bilgileri (Tenant oluşturma için)
            customer_name=request.POST.get('name', ''),
            customer_surname=request.POST.get('surname', ''),
            customer_email=request.POST.get('email', ''),
            customer_phone=request.POST.get('phone', ''),
            customer_address=request.POST.get('address', ''),
            customer_city=request.POST.get('city', ''),
            customer_zip_code=request.POST.get('zip_code', ''),
            # Kaynak bilgileri
            source_module='subscriptions',
            source_id=package.id,
            source_reference=f'Paket Ödemesi: {package.name}',
        )
        
        # Müşteri bilgileri
        customer_info = {
            'id': str(request.user.id) if request.user.is_authenticated else 'guest',
            'name': request.user.first_name or request.user.username if request.user.is_authenticated else request.POST.get('name', ''),
            'surname': request.user.last_name or '' if request.user.is_authenticated else request.POST.get('surname', ''),
            'email': request.user.email if request.user.is_authenticated else request.POST.get('email', ''),
            'phone': request.POST.get('phone', ''),
            'address': request.POST.get('address', ''),
            'city': request.POST.get('city', ''),
            'country': 'Turkey',
            'zip_code': request.POST.get('zip_code', ''),
        }
        
        # Ödeme oluştur
        result = gateway.create_payment(
            amount=package.price_monthly,
            currency=package.currency,
            order_id=payment_transaction.order_id,
            customer_info=customer_info,
            callback_url=request.build_absolute_uri(f'/payments/callback/{payment_transaction.id}/'),
            success_url=request.build_absolute_uri('/payments/success/'),
            fail_url=request.build_absolute_uri('/payments/fail/'),
        )
        
        if result.get('success'):
            payment_transaction.gateway_transaction_id = result.get('transaction_id', '')
            payment_transaction.save()
            
            # 3D Secure için redirect
            if result.get('payment_url'):
                return render(request, 'payments/redirect.html', {
                    'payment_url': result['payment_url'],
                    'form_data': result.get('form_data', {}),
                })
        else:
            payment_transaction.status = 'failed'
            payment_transaction.error_message = result.get('error', '')
            payment_transaction.save()
            messages.error(request, f"Ödeme oluşturulamadı: {result.get('error')}")
    
    # Ödeme formu
    return render(request, 'payments/initiate.html', {
        'package': package,
        'tenant_gateway': tenant_gateway,
    })


@csrf_exempt
@require_http_methods(["POST"])
def payment_callback(request, transaction_id):
    """Ödeme callback (3D Secure sonrası)"""
    payment_transaction = get_object_or_404(PaymentTransaction, id=transaction_id)
    # Gateway'i bul (geçici tenant veya yeni tenant için)
    tenant_gateway = TenantPaymentGateway.objects.filter(
        tenant=payment_transaction.tenant,
        gateway=payment_transaction.gateway,
        is_active=True
    ).first()
    
    # Eğer bulunamazsa, ilk aktif tenant'ın gateway'ini kullan
    if not tenant_gateway:
        tenant_gateway = TenantPaymentGateway.objects.filter(
            gateway=payment_transaction.gateway,
            is_active=True
        ).first()
    
    if not tenant_gateway:
        logger.error(f"Gateway config not found for transaction {payment_transaction.transaction_id}")
        return JsonResponse({'error': 'Gateway config not found'}, status=400)
    
    gateway = get_gateway_instance(tenant_gateway.gateway.code, tenant_gateway)
    
    # Gateway'e göre callback işle
    if tenant_gateway.gateway.code == 'iyzico':
        # İyzico callback
        result = gateway.verify_payment(
            payment_transaction.gateway_transaction_id,
            conversation_id=request.POST.get('conversationId', ''),
        )
    elif tenant_gateway.gateway.code == 'paytr':
        # PayTR callback
        result = gateway.verify_payment(
            payment_transaction.transaction_id,
            **request.POST.dict(),
        )
    elif tenant_gateway.gateway.code == 'nestpay':
        # NestPay callback
        result = gateway.verify_payment(
            payment_transaction.transaction_id,
            **request.POST.dict(),
        )
    else:
        result = {'success': False, 'error': 'Unknown gateway'}
    
    # Transaction güncelle
    if result.get('success'):
        payment_transaction.status = 'completed' if result.get('status') == 'completed' else 'failed'
        payment_transaction.payment_date = timezone.now()
        payment_transaction.gateway_response = result
        payment_transaction.save()
        
        # Abonelik oluştur
        if payment_transaction.status == 'completed':
            try:
                package_id = payment_transaction.order_id.replace('PKG-', '')
                package = Package.objects.get(id=package_id)
                
                # Yeni tenant oluştur (eğer müşteri bilgileri varsa)
                tenant = payment_transaction.tenant
                create_new_tenant = False
                
                # Yeni tenant oluşturma koşulları
                if payment_transaction.customer_email:
                    # Eğer tenant yoksa veya public schema ise yeni tenant oluştur
                    if not tenant:
                        create_new_tenant = True
                    elif tenant.schema_name == get_public_schema_name() or tenant.schema_name.startswith('public'):
                        create_new_tenant = True
                    # Eğer mevcut tenant'ın owner email'i farklıysa yeni tenant oluştur
                    elif tenant.owner_email and tenant.owner_email != payment_transaction.customer_email:
                        create_new_tenant = True
                
                if create_new_tenant:
                    # Email'den tenant slug oluştur
                    email_username = payment_transaction.customer_email.split('@')[0].lower()
                    # Özel karakterleri temizle
                    tenant_slug = re.sub(r'[^a-z0-9]', '', email_username)
                    if not tenant_slug or len(tenant_slug) < 3:
                        tenant_slug = 'tenant' + str(payment_transaction.id)
                    
                    # Slug'un benzersiz olduğundan emin ol
                    schema_name = f'tenant_{tenant_slug}'
                    counter = 1
                    while Tenant.objects.filter(schema_name=schema_name).exists():
                        schema_name = f'tenant_{tenant_slug}{counter}'
                        counter += 1
                    
                    # Tenant oluştur
                    tenant, tenant_created = Tenant.objects.get_or_create(
                        schema_name=schema_name,
                        defaults={
                            'name': f"{payment_transaction.customer_name} {payment_transaction.customer_surname}".strip() or f"Tenant {tenant_slug}",
                            'owner_email': payment_transaction.customer_email,
                            'owner_name': f"{payment_transaction.customer_name} {payment_transaction.customer_surname}".strip() or "Owner",
                            'is_active': True,
                        }
                    )
                    
                    # Tenant schema oluştur (django-tenants otomatik yapar)
                    if tenant_created:
                        tenant.save()
                        # Migration'ları çalıştır
                        try:
                            from django.core.management import call_command
                            call_command('migrate_schemas', '--schema', schema_name, verbosity=0)
                            logger.info(f"Tenant schema created: {schema_name}")
                        except Exception as e:
                            logger.error(f"Error migrating tenant schema {schema_name}: {str(e)}")
                    
                    # PaymentTransaction'ı yeni tenant ile güncelle
                    payment_transaction.tenant = tenant
                    payment_transaction.save()
                
                # Subscription oluştur
                with schema_context(tenant.schema_name):
                    subscription, sub_created = Subscription.objects.get_or_create(
                        tenant=tenant,
                        package=package,
                        defaults={
                            'status': 'active',
                            'start_date': timezone.now().date(),
                            'end_date': timezone.now().date() + timedelta(days=30),  # Aylık paket
                            'amount': package.price_monthly,
                            'currency': package.currency,
                        }
                    )
                    
                    # Signal otomatik olarak ilk admin kullanıcı oluşturacak
                    # Email bildirimi gönder
                    if sub_created:
                        try:
                            send_payment_success_email(payment_transaction, subscription)
                        except Exception as email_error:
                            logger.error(f"Error sending payment success email: {str(email_error)}")
                        
            except Exception as e:
                # Hata logla
                logger.error(f"Payment callback error: {str(e)}", exc_info=True)
                # Hata olsa bile kullanıcıyı success sayfasına yönlendir (ödeme başarılı)
                pass
        
        return redirect('payments:success' if payment_transaction.status == 'completed' else 'payments:fail')
    else:
        payment_transaction.status = 'failed'
        payment_transaction.error_message = result.get('error', '')
        payment_transaction.save()
        return redirect('payments:fail')


@csrf_exempt
@require_http_methods(["POST"])
def payment_webhook(request, gateway_code):
    """Webhook handler"""
    gateway_obj = get_object_or_404(PaymentGateway, code=gateway_code, is_active=True)
    
    # Webhook kaydet
    webhook = PaymentWebhook.objects.create(
        gateway=gateway_obj,
        event_type=request.POST.get('event_type', 'payment'),
        payload=request.POST.dict(),
        headers=dict(request.headers),
    )
    
    # Gateway instance oluştur
    tenant_gateway = TenantPaymentGateway.objects.filter(
        gateway=gateway_obj,
        is_active=True
    ).first()
    
    if tenant_gateway:
        gateway = get_gateway_instance(gateway_code, tenant_gateway)
        result = gateway.handle_webhook(request.POST.dict(), dict(request.headers))
        
        if result.get('success'):
            # Transaction bul ve güncelle
            transaction = PaymentTransaction.objects.filter(
                gateway_transaction_id=result.get('transaction_id', '')
            ).first()
            
            if transaction:
                transaction.status = 'completed' if result.get('status') == 'completed' else 'failed'
                transaction.payment_date = timezone.now()
                transaction.save()
            
            webhook.transaction = transaction
            webhook.is_processed = True
            webhook.save()
    
    return JsonResponse({'status': 'ok'})


def payment_success(request):
    """Ödeme başarılı"""
    return render(request, 'payments/success.html')


def payment_fail(request):
    """Ödeme başarısız"""
    return render(request, 'payments/fail.html')


def send_payment_success_email(payment_transaction, subscription):
    """Ödeme başarılı olduğunda email bildirimi gönder"""
    try:
        if not payment_transaction.customer_email:
            logger.warning(f"No customer email for transaction {payment_transaction.transaction_id}")
            return
        
        # Tenant bilgilerini al
        tenant = subscription.tenant
        # Domain URL'i oluştur
        tenant_domain = None
        if hasattr(tenant, 'domains') and tenant.domains.exists():
            # İlk domain'i al
            domain = tenant.domains.first()
            tenant_domain = domain.domain
        else:
            # Development için schema_name'den domain oluştur
            schema_part = tenant.schema_name.replace('tenant_', '')
            tenant_domain = f"{schema_part}.localhost:8000" if settings.DEBUG else f"{schema_part}.bulutacente.com"
        
        # İlk admin kullanıcı bilgilerini al (signal tarafından oluşturulmuş olmalı)
        from apps.tenant_apps.core.models import TenantUser
        from django.contrib.auth.models import User
        
        username = None
        default_password = None
        
        with schema_context(tenant.schema_name):
            tenant_user = TenantUser.objects.first()
            if tenant_user:
                username = tenant_user.user.username
                # Varsayılan şifre: username + "123"
                default_password = f"{username}123"
            else:
                username = payment_transaction.customer_email.split('@')[0]
                default_password = f"{username}123"
        
        subject = f'Bulut Acente - Paket Satın Alma Başarılı'
        message = f"""
Sayın {payment_transaction.customer_name} {payment_transaction.customer_surname},

Paket satın alımınız başarıyla tamamlanmıştır!

Paket Bilgileri:
- Paket Adı: {subscription.package.name}
- Tutar: {payment_transaction.amount} {payment_transaction.currency}
- Başlangıç Tarihi: {subscription.start_date}
- Bitiş Tarihi: {subscription.end_date}

Giriş Bilgileri:
- Panel URL: http://{tenant_domain}/login/
- Kullanıcı Adı: {username}
- Şifre: {default_password}

NOT: İlk girişte şifrenizi değiştirmenizi öneririz.

Sorularınız için bizimle iletişime geçebilirsiniz.

İyi çalışmalar,
Bulut Acente Ekibi
        """
        
        from_email = getattr(settings, 'DEFAULT_FROM_EMAIL', 'noreply@bulutacente.com')
        send_mail(
            subject,
            message,
            from_email,
            [payment_transaction.customer_email],
            fail_silently=False,
        )
        
        logger.info(f"Payment success email sent to {payment_transaction.customer_email}")
        
    except Exception as e:
        logger.error(f"Error sending payment success email: {str(e)}")
