"""
Custom Form Renderer - Widget template'lerini bulabilmek için
"""
from django.forms.renderers import DjangoTemplates
from django.template.backends.django import DjangoTemplates as DjangoTemplatesBackend
from pathlib import Path
from django.conf import settings
from django.utils.functional import cached_property


class CustomDjangoTemplates(DjangoTemplates):
    """
    Widget template'lerini bulabilmek için custom renderer.
    Hem Django'nun standart widget template'lerini hem de
    TEMPLATES['DIRS'] ve app'lerin templates klasörlerini arar.
    """
    
    @cached_property
    def engine(self):
        # Ana template engine'i al
        from django.template import engines
        main_engine = engines['django']
        
        # Django'nun standart widget template dizinini bul
        import django.forms.renderers
        django_forms_dir = Path(django.forms.renderers.__file__).parent / 'templates'
        
        # Ana engine'in DIRS'ine Django'nun widget template dizinini ekle
        from django.template.backends.django import DjangoTemplates as DjangoTemplatesBackend
        
        # Ana engine'in ayarlarını al
        main_dirs = list(main_engine.engine.dirs)
        if django_forms_dir.exists():
            main_dirs.insert(0, django_forms_dir)  # Önce Django'nun template'lerini ara
        
        # Ana engine'in OPTIONS'unu al
        main_options = {}
        if hasattr(main_engine.engine, 'engine'):
            main_options = getattr(main_engine.engine.engine, 'options', {})
        
        # Yeni engine oluştur
        return DjangoTemplatesBackend({
            'APP_DIRS': main_engine.engine.app_dirs,
            'DIRS': main_dirs,
            'NAME': 'django',
            'OPTIONS': main_options,
        })

