"""
Custom Admin Site
Admin dashboard için özel context ekler
"""
from django.contrib import admin
from django.template.response import TemplateResponse
from django.urls import path
from .models import Announcement, Advertisement


class CustomAdminSite(admin.AdminSite):
    """Özel Admin Site - Dashboard için haberler ve reklamlar ekler"""
    
    def index(self, request, extra_context=None):
        """
        Admin dashboard sayfası
        Haberler ve reklamlar context'e eklenir
        """
        app_list = self.get_app_list(request)
        
        # Aktif haberler ve reklamlar
        announcements = Announcement.objects.filter(is_deleted=False).filter(
            is_active=True
        )
        # Tarih kontrolü
        from django.utils import timezone
        now = timezone.now()
        announcements = [
            a for a in announcements 
            if (not a.start_date or now >= a.start_date) and 
               (not a.end_date or now <= a.end_date)
        ]
        announcements = sorted(announcements, key=lambda x: (-x.priority, -x.created_at.timestamp()))[:5]
        
        advertisements = Advertisement.objects.filter(is_deleted=False).filter(
            is_active=True
        )
        advertisements = [
            a for a in advertisements 
            if (not a.start_date or now >= a.start_date) and 
               (not a.end_date or now <= a.end_date)
        ]
        advertisements = sorted(advertisements, key=lambda x: (-x.priority, -x.created_at.timestamp()))[:5]
        
        context = {
            **self.each_context(request),
            "title": self.index_title,
            "subtitle": None,
            "app_list": app_list,
            "announcements": announcements,
            "advertisements": advertisements,
            **(extra_context or {}),
        }
        
        request.current_app = self.name
        
        return TemplateResponse(
            request, self.index_template or "admin/index.html", context
        )

