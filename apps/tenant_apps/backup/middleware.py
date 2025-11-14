"""
Yedekleme Modülü Middleware
backupdatabase klasörüne erişimi engeller
"""
from django.http import HttpResponseForbidden
from django.conf import settings
from pathlib import Path
import re


class BackupDirectoryProtectionMiddleware:
    """
    backupdatabase klasörüne HTTP erişimini engeller
    Güvenlik için kritik
    """
    
    def __init__(self, get_response):
        self.get_response = get_response
        self.backup_dir = Path(settings.BASE_DIR) / 'backupdatabase'
        
    def __call__(self, request):
        # backupdatabase klasörüne erişim kontrolü
        path = request.path
        
        # backupdatabase klasörüne erişim denemelerini engelle
        if '/backupdatabase/' in path or path.startswith('/backupdatabase'):
            return HttpResponseForbidden(
                '<h1>403 Forbidden</h1><p>Bu dizine erişim yasaktır.</p>',
                content_type='text/html'
            )
        
        # backupdatabase ile başlayan URL'leri engelle
        if re.match(r'^/backupdatabase', path, re.IGNORECASE):
            return HttpResponseForbidden(
                '<h1>403 Forbidden</h1><p>Bu dizine erişim yasaktır.</p>',
                content_type='text/html'
            )
        
        response = self.get_response(request)
        return response

