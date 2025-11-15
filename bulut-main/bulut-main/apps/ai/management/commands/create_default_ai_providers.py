"""
Hazır AI Provider'ları oluştur
OpenAI, Anthropic, Google, Cursor vb.
"""
from django.core.management.base import BaseCommand
from apps.ai.models import AIProvider, AIModel


class Command(BaseCommand):
    help = 'Hazır AI Provider ve Model şablonlarını oluşturur'

    def handle(self, *args, **options):
        self.stdout.write('AI Provider şablonları oluşturuluyor...')
        
        # OpenAI Provider
        openai_provider, created = AIProvider.objects.get_or_create(
            code='openai',
            defaults={
                'name': 'OpenAI (ChatGPT)',
                'provider_type': 'openai',
                'description': 'OpenAI ChatGPT API servisi',
                'icon': '🤖',
                'api_base_url': 'https://api.openai.com/v1/chat/completions',
                'api_key_label': 'OpenAI API Key',
                'is_active': True,
                'sort_order': 1,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {openai_provider.name} olusturuldu'))
        else:
            self.stdout.write(f'  {openai_provider.name} zaten mevcut')
        
        # OpenAI Modelleri
        openai_models = [
            {'code': 'gpt-4', 'name': 'GPT-4', 'model_id': 'gpt-4', 'credit_cost': 2.0, 'is_default': True},
            {'code': 'gpt-4-turbo', 'name': 'GPT-4 Turbo', 'model_id': 'gpt-4-turbo-preview', 'credit_cost': 1.5},
            {'code': 'gpt-3.5-turbo', 'name': 'GPT-3.5 Turbo', 'model_id': 'gpt-3.5-turbo', 'credit_cost': 1.0},
        ]
        for model_data in openai_models:
            model, created = AIModel.objects.get_or_create(
                provider=openai_provider,
                code=model_data['code'],
                defaults={
                    'name': model_data['name'],
                    'model_id': model_data['model_id'],
                    'credit_cost': model_data['credit_cost'],
                    'is_default': model_data.get('is_default', False),
                    'is_active': True,
                    'sort_order': 1,
                }
            )
            if created:
                self.stdout.write(self.style.SUCCESS(f'  [OK] {model.name} modeli olusturuldu'))
        
        # Anthropic Provider
        anthropic_provider, created = AIProvider.objects.get_or_create(
            code='anthropic',
            defaults={
                'name': 'Anthropic (Claude)',
                'provider_type': 'anthropic',
                'description': 'Anthropic Claude API servisi',
                'icon': '🧠',
                'api_base_url': 'https://api.anthropic.com/v1/messages',
                'api_key_label': 'Anthropic API Key',
                'is_active': True,
                'sort_order': 2,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {anthropic_provider.name} olusturuldu'))
        else:
            self.stdout.write(f'  {anthropic_provider.name} zaten mevcut')
        
        # Anthropic Modelleri
        anthropic_models = [
            {'code': 'claude-3-opus', 'name': 'Claude 3 Opus', 'model_id': 'claude-3-opus-20240229', 'credit_cost': 3.0, 'is_default': True},
            {'code': 'claude-3-sonnet', 'name': 'Claude 3 Sonnet', 'model_id': 'claude-3-sonnet-20240229', 'credit_cost': 2.0},
            {'code': 'claude-3-haiku', 'name': 'Claude 3 Haiku', 'model_id': 'claude-3-haiku-20240307', 'credit_cost': 1.0},
        ]
        for model_data in anthropic_models:
            model, created = AIModel.objects.get_or_create(
                provider=anthropic_provider,
                code=model_data['code'],
                defaults={
                    'name': model_data['name'],
                    'model_id': model_data['model_id'],
                    'credit_cost': model_data['credit_cost'],
                    'is_default': model_data.get('is_default', False),
                    'is_active': True,
                    'sort_order': 1,
                }
            )
            if created:
                self.stdout.write(self.style.SUCCESS(f'  [OK] {model.name} modeli olusturuldu'))
        
        # Google Provider
        google_provider, created = AIProvider.objects.get_or_create(
            code='google',
            defaults={
                'name': 'Google (Gemini)',
                'provider_type': 'google',
                'description': 'Google Gemini API servisi',
                'icon': '💎',
                'api_base_url': 'https://generativelanguage.googleapis.com/v1beta',
                'api_key_label': 'Google API Key',
                'is_active': True,
                'sort_order': 3,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {google_provider.name} olusturuldu'))
        else:
            self.stdout.write(f'  {google_provider.name} zaten mevcut')
        
        # Google Modelleri
        google_models = [
            {'code': 'gemini-pro', 'name': 'Gemini Pro', 'model_id': 'gemini-pro', 'credit_cost': 1.5, 'is_default': True},
            {'code': 'gemini-pro-vision', 'name': 'Gemini Pro Vision', 'model_id': 'gemini-pro-vision', 'credit_cost': 2.0},
        ]
        for model_data in google_models:
            model, created = AIModel.objects.get_or_create(
                provider=google_provider,
                code=model_data['code'],
                defaults={
                    'name': model_data['name'],
                    'model_id': model_data['model_id'],
                    'credit_cost': model_data['credit_cost'],
                    'is_default': model_data.get('is_default', False),
                    'is_active': True,
                    'sort_order': 1,
                }
            )
            if created:
                self.stdout.write(self.style.SUCCESS(f'  [OK] {model.name} modeli olusturuldu'))
        
        # Cursor Provider
        cursor_provider, created = AIProvider.objects.get_or_create(
            code='cursor',
            defaults={
                'name': 'Cursor',
                'provider_type': 'cursor',
                'description': 'Cursor AI API servisi',
                'icon': '⌨️',
                'api_base_url': '',
                'api_key_label': 'Cursor API Key',
                'is_active': False,  # Varsayılan olarak pasif
                'sort_order': 4,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {cursor_provider.name} olusturuldu'))
        else:
            self.stdout.write(f'  {cursor_provider.name} zaten mevcut')
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Tum AI Provider sablonlari olusturuldu!'))
        self.stdout.write('\nNot: API key\'leri admin panelinden ekleyebilirsiniz.')

