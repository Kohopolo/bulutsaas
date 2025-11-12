"""
AI Servisleri
AI API'lerine istek gönderme ve kredi yönetimi
"""
import requests
import json
from django.db import connection
from django.utils import timezone
from decimal import Decimal
from .models import AIProvider, AIModel
from apps.tenant_apps.ai.models import TenantAICredit, TenantAIUsage


def get_tenant_ai_credit(tenant=None):
    """
    Tenant'ın AI kredi bilgisini al
    """
    if not tenant:
        from django_tenants.utils import get_tenant
        tenant = get_tenant(connection)
    
    credit, created = TenantAICredit.objects.get_or_create(
        tenant_id=tenant.id if hasattr(tenant, 'id') else None,
        tenant_name=tenant.name if hasattr(tenant, 'name') else '',
        defaults={
            'total_credits': 0,
            'used_credits': 0,
        }
    )
    
    if created and hasattr(tenant, 'id'):
        credit.tenant_id = tenant.id
        credit.tenant_name = tenant.name
        credit.save()
    
    return credit


def check_credit_available(credit_amount, tenant=None):
    """
    Yeterli kredi var mı kontrol et
    """
    credit = get_tenant_ai_credit(tenant)
    return credit.remaining_credits >= Decimal(str(credit_amount))


def use_ai_credit(credit_amount, tenant=None):
    """
    AI kredisi kullan
    """
    credit = get_tenant_ai_credit(tenant)
    if credit.remaining_credits < Decimal(str(credit_amount)):
        raise ValueError(f'Yetersiz kredi. Kalan: {credit.remaining_credits}, İstenen: {credit_amount}')
    
    credit.use_credit(int(credit_amount))
    return credit


def call_openai_api(model, prompt, max_tokens=1000, temperature=0.7):
    """
    OpenAI API çağrısı
    """
    provider = AIProvider.objects.filter(code='openai', is_active=True).first()
    if not provider or not provider.has_api_key:
        raise ValueError('OpenAI API key bulunamadı veya aktif değil')
    
    api_key = provider.get_api_key()
    api_url = provider.api_base_url or 'https://api.openai.com/v1/chat/completions'
    
    headers = {
        'Authorization': f'Bearer {api_key}',
        'Content-Type': 'application/json',
    }
    
    data = {
        'model': model.model_id,
        'messages': [
            {'role': 'user', 'content': prompt}
        ],
        'max_tokens': max_tokens,
        'temperature': temperature,
    }
    
    response = requests.post(api_url, headers=headers, json=data, timeout=30)
    response.raise_for_status()
    
    result = response.json()
    return result['choices'][0]['message']['content']


def call_anthropic_api(model, prompt, max_tokens=1000):
    """
    Anthropic (Claude) API çağrısı
    """
    provider = AIProvider.objects.filter(code='anthropic', is_active=True).first()
    if not provider or not provider.has_api_key:
        raise ValueError('Anthropic API key bulunamadı veya aktif değil')
    
    api_key = provider.get_api_key()
    api_url = provider.api_base_url or 'https://api.anthropic.com/v1/messages'
    
    headers = {
        'x-api-key': api_key,
        'anthropic-version': '2023-06-01',
        'Content-Type': 'application/json',
    }
    
    data = {
        'model': model.model_id,
        'max_tokens': max_tokens,
        'messages': [
            {'role': 'user', 'content': prompt}
        ],
    }
    
    response = requests.post(api_url, headers=headers, json=data, timeout=30)
    response.raise_for_status()
    
    result = response.json()
    return result['content'][0]['text']


def call_google_api(model, prompt, max_tokens=1000):
    """
    Google (Gemini) API çağrısı
    """
    provider = AIProvider.objects.filter(code='google', is_active=True).first()
    if not provider or not provider.has_api_key:
        raise ValueError('Google API key bulunamadı veya aktif değil')
    
    api_key = provider.get_api_key()
    api_url = provider.api_base_url or f'https://generativelanguage.googleapis.com/v1beta/models/{model.model_id}:generateContent'
    
    params = {'key': api_key}
    data = {
        'contents': [{
            'parts': [{'text': prompt}]
        }],
        'generationConfig': {
            'maxOutputTokens': max_tokens,
        }
    }
    
    response = requests.post(api_url, params=params, json=data, timeout=30)
    response.raise_for_status()
    
    result = response.json()
    return result['candidates'][0]['content']['parts'][0]['text']


def call_ai_api(provider_code, model_code, prompt, **kwargs):
    """
    Genel AI API çağrısı
    Provider'a göre doğru fonksiyonu çağırır
    """
    provider = AIProvider.objects.filter(code=provider_code, is_active=True).first()
    if not provider:
        raise ValueError(f'AI sağlayıcı bulunamadı: {provider_code}')
    
    model = AIModel.objects.filter(provider=provider, code=model_code, is_active=True).first()
    if not model:
        raise ValueError(f'AI model bulunamadı: {model_code}')
    
    # Provider'a göre API çağrısı
    if provider_code == 'openai':
        response = call_openai_api(model, prompt, **kwargs)
    elif provider_code == 'anthropic':
        response = call_anthropic_api(model, prompt, **kwargs)
    elif provider_code == 'google':
        response = call_google_api(model, prompt, **kwargs)
    else:
        raise ValueError(f'Desteklenmeyen AI sağlayıcı: {provider_code}')
    
    return response, provider, model


def generate_ai_content(provider_code, model_code, prompt, usage_type='other', user=None, tenant=None, **kwargs):
    """
    AI içerik üret ve kredi düş
    """
    from django_tenants.utils import get_tenant
    if not tenant:
        tenant = get_tenant(connection)
    
    # Model bilgilerini al
    provider = AIProvider.objects.filter(code=provider_code, is_active=True).first()
    if not provider:
        raise ValueError(f'AI sağlayıcı bulunamadı: {provider_code}')
    
    model = AIModel.objects.filter(provider=provider, code=model_code, is_active=True).first()
    if not model:
        raise ValueError(f'AI model bulunamadı: {model_code}')
    
    # Kredi kontrolü
    if not check_credit_available(model.credit_cost, tenant):
        raise ValueError(f'Yetersiz kredi. Gerekli: {model.credit_cost}, Kalan: {get_tenant_ai_credit(tenant).remaining_credits}')
    
    # API çağrısı
    try:
        response_text, provider_obj, model_obj = call_ai_api(provider_code, model_code, prompt, **kwargs)
        
        # Kredi düş
        use_ai_credit(model.credit_cost, tenant)
        
        # Kullanım logu oluştur
        usage = TenantAIUsage.objects.create(
            tenant_id=tenant.id if hasattr(tenant, 'id') else None,
            tenant_name=tenant.name if hasattr(tenant, 'name') else '',
            ai_provider_name=provider_obj.name,
            ai_model_name=model_obj.name,
            ai_provider_code=provider_obj.code,
            ai_model_code=model_obj.code,
            usage_type=usage_type,
            prompt=prompt,
            response=response_text,
            credit_used=model.credit_cost,
            status='success',
            user=user,
        )
        
        return response_text, usage
        
    except Exception as e:
        # Hata durumunda log oluştur
        TenantAIUsage.objects.create(
            tenant_id=tenant.id if hasattr(tenant, 'id') else None,
            tenant_name=tenant.name if hasattr(tenant, 'name') else '',
            ai_provider_name=provider.name,
            ai_model_name=model.name if model else '',
            ai_provider_code=provider.code,
            ai_model_code=model_code,
            usage_type=usage_type,
            prompt=prompt,
            credit_used=model.credit_cost if model else 0,
            status='error',
            error_message=str(e),
            user=user,
        )
        raise

