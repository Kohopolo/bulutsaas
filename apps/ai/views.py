"""
AI Yönetim Views
Super Admin paneli için
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required, user_passes_test
from django.contrib import messages
from django.views.decorators.http import require_http_methods
from django.core.paginator import Paginator
from django.db.models import Q
from .models import AIProvider, AIModel, PackageAI
from .forms import AIProviderForm, AIModelForm, PackageAIForm, APIKeyForm


def is_superuser(user):
    """Super user kontrolü"""
    return user.is_superuser


@login_required
@user_passes_test(is_superuser)
def provider_list(request):
    """AI Sağlayıcı Listesi"""
    providers = AIProvider.objects.filter(is_deleted=False).order_by('sort_order', 'name')
    
    # Filtreleme
    provider_type = request.GET.get('provider_type', '')
    is_active = request.GET.get('is_active', '')
    search = request.GET.get('search', '')
    
    if provider_type:
        providers = providers.filter(provider_type=provider_type)
    if is_active:
        providers = providers.filter(is_active=is_active == '1')
    if search:
        providers = providers.filter(Q(name__icontains=search) | Q(code__icontains=search))
    
    paginator = Paginator(providers, 20)
    page = request.GET.get('page', 1)
    providers = paginator.get_page(page)
    
    context = {
        'providers': providers,
        'provider_type_choices': AIProvider.PROVIDER_CHOICES,
    }
    return render(request, 'admin/ai/providers/list.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def provider_create(request):
    """AI Sağlayıcı Oluştur"""
    if request.method == 'POST':
        form = AIProviderForm(request.POST)
        api_key_form = APIKeyForm(request.POST)
        
        if form.is_valid():
            provider = form.save(commit=False)
            provider.save()
            
            # API key varsa kaydet
            if api_key_form.is_valid() and api_key_form.cleaned_data.get('api_key'):
                provider.set_api_key(api_key_form.cleaned_data['api_key'])
            
            messages.success(request, f'AI sağlayıcı "{provider.name}" başarıyla oluşturuldu.')
            return redirect('ai:provider_detail', pk=provider.pk)
    else:
        form = AIProviderForm()
        api_key_form = APIKeyForm()
    
    context = {
        'form': form,
        'api_key_form': api_key_form,
        'title': 'Yeni AI Sağlayıcı Ekle',
    }
    return render(request, 'admin/ai/providers/form.html', context)


@login_required
@user_passes_test(is_superuser)
def provider_detail(request, pk):
    """AI Sağlayıcı Detay"""
    provider = get_object_or_404(AIProvider, pk=pk)
    models = AIModel.objects.filter(provider=provider, is_deleted=False).order_by('sort_order', 'name')
    
    context = {
        'provider': provider,
        'models': models,
    }
    return render(request, 'admin/ai/providers/detail.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def provider_update(request, pk):
    """AI Sağlayıcı Güncelle"""
    provider = get_object_or_404(AIProvider, pk=pk)
    
    if request.method == 'POST':
        form = AIProviderForm(request.POST, instance=provider)
        if form.is_valid():
            form.save()
            messages.success(request, f'AI sağlayıcı "{provider.name}" başarıyla güncellendi.')
            return redirect('ai:provider_detail', pk=provider.pk)
    else:
        form = AIProviderForm(instance=provider)
    
    context = {
        'form': form,
        'provider': provider,
        'title': f'{provider.name} Düzenle',
    }
    return render(request, 'admin/ai/providers/form.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["POST"])
def provider_delete(request, pk):
    """AI Sağlayıcı Sil (Soft Delete)"""
    provider = get_object_or_404(AIProvider, pk=pk)
    provider_name = provider.name
    provider.is_deleted = True
    provider.save()
    messages.success(request, f'AI sağlayıcı "{provider_name}" başarıyla silindi.')
    return redirect('ai:provider_list')


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def provider_update_api_key(request, pk):
    """API Key Güncelle"""
    provider = get_object_or_404(AIProvider, pk=pk)
    
    if request.method == 'POST':
        form = APIKeyForm(request.POST)
        if form.is_valid() and form.cleaned_data.get('api_key'):
            provider.set_api_key(form.cleaned_data['api_key'])
            messages.success(request, 'API key başarıyla güncellendi.')
            return redirect('ai:provider_detail', pk=provider.pk)
        else:
            messages.error(request, 'API key gerekli.')
    else:
        form = APIKeyForm()
    
    context = {
        'form': form,
        'provider': provider,
        'title': f'{provider.name} - API Key Güncelle',
    }
    return render(request, 'admin/ai/providers/api_key_form.html', context)


@login_required
@user_passes_test(is_superuser)
def model_list(request):
    """AI Model Listesi"""
    models = AIModel.objects.filter(is_deleted=False).select_related('provider').order_by('provider', 'sort_order', 'name')
    
    # Filtreleme
    provider_id = request.GET.get('provider', '')
    is_active = request.GET.get('is_active', '')
    search = request.GET.get('search', '')
    
    if provider_id:
        models = models.filter(provider_id=provider_id)
    if is_active:
        models = models.filter(is_active=is_active == '1')
    if search:
        models = models.filter(Q(name__icontains=search) | Q(model_id__icontains=search))
    
    paginator = Paginator(models, 20)
    page = request.GET.get('page', 1)
    models = paginator.get_page(page)
    
    providers = AIProvider.objects.filter(is_active=True, is_deleted=False).order_by('name')
    
    context = {
        'models': models,
        'providers': providers,
    }
    return render(request, 'admin/ai/models/list.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def model_create(request):
    """AI Model Oluştur"""
    if request.method == 'POST':
        form = AIModelForm(request.POST)
        if form.is_valid():
            model = form.save()
            messages.success(request, f'AI model "{model.name}" başarıyla oluşturuldu.')
            return redirect('ai:model_detail', pk=model.pk)
    else:
        form = AIModelForm()
    
    context = {
        'form': form,
        'title': 'Yeni AI Model Ekle',
    }
    return render(request, 'admin/ai/models/form.html', context)


@login_required
@user_passes_test(is_superuser)
def model_detail(request, pk):
    """AI Model Detay"""
    model = get_object_or_404(AIModel, pk=pk)
    package_configs = PackageAI.objects.filter(ai_model=model).select_related('package', 'ai_provider')
    
    context = {
        'model': model,
        'package_configs': package_configs,
    }
    return render(request, 'admin/ai/models/detail.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def model_update(request, pk):
    """AI Model Güncelle"""
    model = get_object_or_404(AIModel, pk=pk)
    
    if request.method == 'POST':
        form = AIModelForm(request.POST, instance=model)
        if form.is_valid():
            form.save()
            messages.success(request, f'AI model "{model.name}" başarıyla güncellendi.')
            return redirect('ai:model_detail', pk=model.pk)
    else:
        form = AIModelForm(instance=model)
    
    context = {
        'form': form,
        'model': model,
        'title': f'{model.name} Düzenle',
    }
    return render(request, 'admin/ai/models/form.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["POST"])
def model_delete(request, pk):
    """AI Model Sil (Soft Delete)"""
    model = get_object_or_404(AIModel, pk=pk)
    model_name = model.name
    model.is_deleted = True
    model.save()
    messages.success(request, f'AI model "{model_name}" başarıyla silindi.')
    return redirect('ai:model_list')


@login_required
@user_passes_test(is_superuser)
def package_ai_list(request):
    """Paket AI Yapılandırmaları Listesi"""
    package_ais = PackageAI.objects.select_related('package', 'ai_provider', 'ai_model').order_by('package', 'ai_provider')
    
    # Filtreleme
    package_id = request.GET.get('package', '')
    provider_id = request.GET.get('provider', '')
    is_enabled = request.GET.get('is_enabled', '')
    
    if package_id:
        package_ais = package_ais.filter(package_id=package_id)
    if provider_id:
        package_ais = package_ais.filter(ai_provider_id=provider_id)
    if is_enabled:
        package_ais = package_ais.filter(is_enabled=is_enabled == '1')
    
    paginator = Paginator(package_ais, 20)
    page = request.GET.get('page', 1)
    package_ais = paginator.get_page(page)
    
    from apps.packages.models import Package
    packages = Package.objects.filter(is_deleted=False).order_by('name')
    providers = AIProvider.objects.filter(is_active=True, is_deleted=False).order_by('name')
    
    context = {
        'package_ais': package_ais,
        'packages': packages,
        'providers': providers,
    }
    return render(request, 'admin/ai/package_ai/list.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def package_ai_create(request):
    """Paket AI Yapılandırması Oluştur"""
    if request.method == 'POST':
        form = PackageAIForm(request.POST)
        if form.is_valid():
            package_ai = form.save()
            messages.success(request, f'Paket AI yapılandırması başarıyla oluşturuldu.')
            return redirect('ai:package_ai_list')
    else:
        form = PackageAIForm()
    
    context = {
        'form': form,
        'title': 'Yeni Paket AI Yapılandırması Ekle',
    }
    return render(request, 'admin/ai/package_ai/form.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["GET", "POST"])
def package_ai_update(request, pk):
    """Paket AI Yapılandırması Güncelle"""
    package_ai = get_object_or_404(PackageAI, pk=pk)
    
    if request.method == 'POST':
        form = PackageAIForm(request.POST, instance=package_ai)
        if form.is_valid():
            form.save()
            messages.success(request, 'Paket AI yapılandırması başarıyla güncellendi.')
            return redirect('ai:package_ai_list')
    else:
        form = PackageAIForm(instance=package_ai)
    
    context = {
        'form': form,
        'package_ai': package_ai,
        'title': 'Paket AI Yapılandırması Düzenle',
    }
    return render(request, 'admin/ai/package_ai/form.html', context)


@login_required
@user_passes_test(is_superuser)
@require_http_methods(["POST"])
def package_ai_delete(request, pk):
    """Paket AI Yapılandırması Sil"""
    package_ai = get_object_or_404(PackageAI, pk=pk)
    package_ai.delete()
    messages.success(request, 'Paket AI yapılandırması başarıyla silindi.')
    return redirect('ai:package_ai_list')

