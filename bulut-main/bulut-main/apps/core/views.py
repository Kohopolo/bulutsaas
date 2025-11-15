from django.shortcuts import render
from django.contrib.auth.decorators import login_required
from apps.packages.models import Package
from apps.modules.models import Module

def landing_page(request):
    """Bulut Acente - Landing Page"""
    packages = Package.objects.filter(is_active=True).order_by('sort_order', 'price_monthly')
    modules = Module.objects.filter(is_active=True)
    
    context = {
        'packages': packages,
        'modules': modules,
    }
    return render(request, 'landing/index.html', context)
