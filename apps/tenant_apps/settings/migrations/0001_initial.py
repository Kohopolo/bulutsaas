# Generated manually - Django migration for settings module

from django.db import migrations, models
import django.db.models.deletion
import django.core.validators


class Migration(migrations.Migration):

    initial = True

    dependencies = [
    ]

    operations = [
        migrations.CreateModel(
            name='SMSGateway',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('is_deleted', models.BooleanField(default=False, verbose_name='Silinmiş mi?')),
                ('deleted_at', models.DateTimeField(blank=True, null=True, verbose_name='Silinme Tarihi')),
                ('name', models.CharField(help_text='Örn: Twilio Production, NetGSM Ana Hesap', max_length=200, verbose_name='Gateway Adı')),
                ('gateway_type', models.CharField(choices=[('twilio', 'Twilio'), ('netgsm', 'NetGSM'), ('verimor', 'Verimor')], max_length=20, verbose_name='Gateway Tipi')),
                ('api_credentials', models.JSONField(default=dict, help_text='API key, secret, username, password vb.', verbose_name='API Bilgileri')),
                ('api_endpoint', models.CharField(blank=True, max_length=500, verbose_name='API Endpoint')),
                ('api_timeout', models.IntegerField(default=30, validators=[django.core.validators.MinValueValidator(1), django.core.validators.MaxValueValidator(300)], verbose_name='API Timeout (saniye)')),
                ('api_retry_count', models.IntegerField(default=3, validators=[django.core.validators.MinValueValidator(0), django.core.validators.MaxValueValidator(10)], verbose_name='API Retry Sayısı')),
                ('sender_id', models.CharField(blank=True, help_text='SMS gönderen numarası veya başlık', max_length=20, verbose_name='Gönderen ID')),
                ('default_country_code', models.CharField(default='+90', help_text='Örn: +90, +1', max_length=5, verbose_name='Varsayılan Ülke Kodu')),
                ('is_active', models.BooleanField(default=True, verbose_name='Aktif mi?')),
                ('is_default', models.BooleanField(default=False, help_text='Sadece bir gateway varsayılan olabilir', verbose_name='Varsayılan Gateway mi?')),
                ('is_test_mode', models.BooleanField(default=False, help_text='Test ortamında çalıştır', verbose_name='Test Modu')),
                ('total_sent', models.IntegerField(default=0, verbose_name='Toplam Gönderilen')),
                ('total_failed', models.IntegerField(default=0, verbose_name='Toplam Başarısız')),
                ('last_sent_at', models.DateTimeField(blank=True, null=True, verbose_name='Son Gönderim')),
                ('notes', models.TextField(blank=True, verbose_name='Notlar')),
            ],
            options={
                'verbose_name': 'SMS Gateway',
                'verbose_name_plural': 'SMS Gateway\'ler',
                'ordering': ['-is_default', '-is_active', 'name'],
            },
        ),
        migrations.CreateModel(
            name='SMSTemplate',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('is_deleted', models.BooleanField(default=False, verbose_name='Silinmiş mi?')),
                ('deleted_at', models.DateTimeField(blank=True, null=True, verbose_name='Silinme Tarihi')),
                ('name', models.CharField(help_text='Örn: Rezervasyon Onayı, Check-in Hatırlatma', max_length=200, verbose_name='Şablon Adı')),
                ('code', models.SlugField(max_length=100, unique=True, verbose_name='Şablon Kodu')),
                ('category', models.CharField(choices=[('reservation', 'Rezervasyon'), ('checkin', 'Check-in'), ('checkout', 'Check-out'), ('payment', 'Ödeme'), ('notification', 'Bildirim'), ('marketing', 'Pazarlama'), ('other', 'Diğer')], default='other', max_length=50, verbose_name='Kategori')),
                ('template_text', models.TextField(help_text='Değişkenler: {{guest_name}}, {{check_in_date}} vb.', max_length=1000, verbose_name='Şablon Metni')),
                ('available_variables', models.JSONField(default=dict, help_text='Şablonda kullanılabilecek değişkenler ve açıklamaları', verbose_name='Kullanılabilir Değişkenler')),
                ('module_usage', models.CharField(blank=True, help_text='Örn: reception, ferry_tickets, tours', max_length=100, verbose_name='Kullanıldığı Modül')),
                ('description', models.TextField(blank=True, help_text='Şablonun ne zaman ve nasıl kullanılacağı', verbose_name='Açıklama')),
                ('max_length', models.IntegerField(default=160, help_text='SMS karakter limiti (varsayılan 160)', verbose_name='Maksimum Uzunluk')),
                ('is_active', models.BooleanField(default=True, verbose_name='Aktif mi?')),
                ('is_system_template', models.BooleanField(default=False, help_text='Sistem şablonları silinemez', verbose_name='Sistem Şablonu mu?')),
                ('usage_count', models.IntegerField(default=0, verbose_name='Kullanım Sayısı')),
                ('last_used_at', models.DateTimeField(blank=True, null=True, verbose_name='Son Kullanım')),
            ],
            options={
                'verbose_name': 'SMS Şablonu',
                'verbose_name_plural': 'SMS Şablonları',
                'ordering': ['category', 'name'],
            },
        ),
        migrations.CreateModel(
            name='SMSSentLog',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('recipient_phone', models.CharField(max_length=20, verbose_name='Alıcı Telefon')),
                ('recipient_name', models.CharField(blank=True, max_length=200, verbose_name='Alıcı Adı')),
                ('message_text', models.TextField(max_length=1000, verbose_name='Mesaj Metni')),
                ('message_length', models.IntegerField(verbose_name='Mesaj Uzunluğu')),
                ('status', models.CharField(choices=[('pending', 'Beklemede'), ('sent', 'Gönderildi'), ('delivered', 'Teslim Edildi'), ('failed', 'Başarısız'), ('cancelled', 'İptal Edildi')], default='pending', max_length=20, verbose_name='Durum')),
                ('error_message', models.TextField(blank=True, verbose_name='Hata Mesajı')),
                ('gateway_response', models.JSONField(blank=True, default=dict, verbose_name='Gateway Yanıtı')),
                ('gateway_message_id', models.CharField(blank=True, max_length=200, verbose_name='Gateway Mesaj ID')),
                ('sent_at', models.DateTimeField(blank=True, null=True, verbose_name='Gönderim Zamanı')),
                ('delivered_at', models.DateTimeField(blank=True, null=True, verbose_name='Teslim Zamanı')),
                ('related_module', models.CharField(blank=True, max_length=100, verbose_name='İlişkili Modül')),
                ('related_object_id', models.IntegerField(blank=True, null=True, verbose_name='İlişkili Kayıt ID')),
                ('related_object_type', models.CharField(blank=True, max_length=100, verbose_name='İlişkili Kayıt Tipi')),
                ('context_data', models.JSONField(blank=True, default=dict, help_text='Şablon render için kullanılan değişkenler', verbose_name='Context Verisi')),
                ('gateway', models.ForeignKey(null=True, on_delete=django.db.models.deletion.SET_NULL, related_name='sent_logs', to='settings.smsgateway', verbose_name='Gateway')),
                ('template', models.ForeignKey(blank=True, null=True, on_delete=django.db.models.deletion.SET_NULL, related_name='sent_logs', to='settings.smstemplate', verbose_name='Şablon')),
            ],
            options={
                'verbose_name': 'SMS Gönderim Logu',
                'verbose_name_plural': 'SMS Gönderim Logları',
                'ordering': ['-created_at'],
            },
        ),
        migrations.AddIndex(
            model_name='smsgateway',
            index=models.Index(fields=['gateway_type', 'is_active'], name='settings_sm_gateway_5a8b8c_idx'),
        ),
        migrations.AddIndex(
            model_name='smsgateway',
            index=models.Index(fields=['is_default'], name='settings_sm_is_defa_123456_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['code'], name='settings_sm_code_789abc_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['category', 'is_active'], name='settings_sm_categor_def456_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['module_usage'], name='settings_sm_module__ghi789_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['gateway', '-created_at'], name='settings_sm_gateway_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['status', '-created_at'], name='settings_sm_status_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['recipient_phone', '-created_at'], name='settings_sm_recipie_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['related_module', 'related_object_id'], name='settings_sm_related_module_idx'),
        ),
    ]




from django.db import migrations, models
import django.db.models.deletion
import django.core.validators


class Migration(migrations.Migration):

    initial = True

    dependencies = [
    ]

    operations = [
        migrations.CreateModel(
            name='SMSGateway',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('is_deleted', models.BooleanField(default=False, verbose_name='Silinmiş mi?')),
                ('deleted_at', models.DateTimeField(blank=True, null=True, verbose_name='Silinme Tarihi')),
                ('name', models.CharField(help_text='Örn: Twilio Production, NetGSM Ana Hesap', max_length=200, verbose_name='Gateway Adı')),
                ('gateway_type', models.CharField(choices=[('twilio', 'Twilio'), ('netgsm', 'NetGSM'), ('verimor', 'Verimor')], max_length=20, verbose_name='Gateway Tipi')),
                ('api_credentials', models.JSONField(default=dict, help_text='API key, secret, username, password vb.', verbose_name='API Bilgileri')),
                ('api_endpoint', models.CharField(blank=True, max_length=500, verbose_name='API Endpoint')),
                ('api_timeout', models.IntegerField(default=30, validators=[django.core.validators.MinValueValidator(1), django.core.validators.MaxValueValidator(300)], verbose_name='API Timeout (saniye)')),
                ('api_retry_count', models.IntegerField(default=3, validators=[django.core.validators.MinValueValidator(0), django.core.validators.MaxValueValidator(10)], verbose_name='API Retry Sayısı')),
                ('sender_id', models.CharField(blank=True, help_text='SMS gönderen numarası veya başlık', max_length=20, verbose_name='Gönderen ID')),
                ('default_country_code', models.CharField(default='+90', help_text='Örn: +90, +1', max_length=5, verbose_name='Varsayılan Ülke Kodu')),
                ('is_active', models.BooleanField(default=True, verbose_name='Aktif mi?')),
                ('is_default', models.BooleanField(default=False, help_text='Sadece bir gateway varsayılan olabilir', verbose_name='Varsayılan Gateway mi?')),
                ('is_test_mode', models.BooleanField(default=False, help_text='Test ortamında çalıştır', verbose_name='Test Modu')),
                ('total_sent', models.IntegerField(default=0, verbose_name='Toplam Gönderilen')),
                ('total_failed', models.IntegerField(default=0, verbose_name='Toplam Başarısız')),
                ('last_sent_at', models.DateTimeField(blank=True, null=True, verbose_name='Son Gönderim')),
                ('notes', models.TextField(blank=True, verbose_name='Notlar')),
            ],
            options={
                'verbose_name': 'SMS Gateway',
                'verbose_name_plural': 'SMS Gateway\'ler',
                'ordering': ['-is_default', '-is_active', 'name'],
            },
        ),
        migrations.CreateModel(
            name='SMSTemplate',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('is_deleted', models.BooleanField(default=False, verbose_name='Silinmiş mi?')),
                ('deleted_at', models.DateTimeField(blank=True, null=True, verbose_name='Silinme Tarihi')),
                ('name', models.CharField(help_text='Örn: Rezervasyon Onayı, Check-in Hatırlatma', max_length=200, verbose_name='Şablon Adı')),
                ('code', models.SlugField(max_length=100, unique=True, verbose_name='Şablon Kodu')),
                ('category', models.CharField(choices=[('reservation', 'Rezervasyon'), ('checkin', 'Check-in'), ('checkout', 'Check-out'), ('payment', 'Ödeme'), ('notification', 'Bildirim'), ('marketing', 'Pazarlama'), ('other', 'Diğer')], default='other', max_length=50, verbose_name='Kategori')),
                ('template_text', models.TextField(help_text='Değişkenler: {{guest_name}}, {{check_in_date}} vb.', max_length=1000, verbose_name='Şablon Metni')),
                ('available_variables', models.JSONField(default=dict, help_text='Şablonda kullanılabilecek değişkenler ve açıklamaları', verbose_name='Kullanılabilir Değişkenler')),
                ('module_usage', models.CharField(blank=True, help_text='Örn: reception, ferry_tickets, tours', max_length=100, verbose_name='Kullanıldığı Modül')),
                ('description', models.TextField(blank=True, help_text='Şablonun ne zaman ve nasıl kullanılacağı', verbose_name='Açıklama')),
                ('max_length', models.IntegerField(default=160, help_text='SMS karakter limiti (varsayılan 160)', verbose_name='Maksimum Uzunluk')),
                ('is_active', models.BooleanField(default=True, verbose_name='Aktif mi?')),
                ('is_system_template', models.BooleanField(default=False, help_text='Sistem şablonları silinemez', verbose_name='Sistem Şablonu mu?')),
                ('usage_count', models.IntegerField(default=0, verbose_name='Kullanım Sayısı')),
                ('last_used_at', models.DateTimeField(blank=True, null=True, verbose_name='Son Kullanım')),
            ],
            options={
                'verbose_name': 'SMS Şablonu',
                'verbose_name_plural': 'SMS Şablonları',
                'ordering': ['category', 'name'],
            },
        ),
        migrations.CreateModel(
            name='SMSSentLog',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created_at', models.DateTimeField(auto_now_add=True, verbose_name='Oluşturulma Tarihi')),
                ('updated_at', models.DateTimeField(auto_now=True, verbose_name='Güncellenme Tarihi')),
                ('recipient_phone', models.CharField(max_length=20, verbose_name='Alıcı Telefon')),
                ('recipient_name', models.CharField(blank=True, max_length=200, verbose_name='Alıcı Adı')),
                ('message_text', models.TextField(max_length=1000, verbose_name='Mesaj Metni')),
                ('message_length', models.IntegerField(verbose_name='Mesaj Uzunluğu')),
                ('status', models.CharField(choices=[('pending', 'Beklemede'), ('sent', 'Gönderildi'), ('delivered', 'Teslim Edildi'), ('failed', 'Başarısız'), ('cancelled', 'İptal Edildi')], default='pending', max_length=20, verbose_name='Durum')),
                ('error_message', models.TextField(blank=True, verbose_name='Hata Mesajı')),
                ('gateway_response', models.JSONField(blank=True, default=dict, verbose_name='Gateway Yanıtı')),
                ('gateway_message_id', models.CharField(blank=True, max_length=200, verbose_name='Gateway Mesaj ID')),
                ('sent_at', models.DateTimeField(blank=True, null=True, verbose_name='Gönderim Zamanı')),
                ('delivered_at', models.DateTimeField(blank=True, null=True, verbose_name='Teslim Zamanı')),
                ('related_module', models.CharField(blank=True, max_length=100, verbose_name='İlişkili Modül')),
                ('related_object_id', models.IntegerField(blank=True, null=True, verbose_name='İlişkili Kayıt ID')),
                ('related_object_type', models.CharField(blank=True, max_length=100, verbose_name='İlişkili Kayıt Tipi')),
                ('context_data', models.JSONField(blank=True, default=dict, help_text='Şablon render için kullanılan değişkenler', verbose_name='Context Verisi')),
                ('gateway', models.ForeignKey(null=True, on_delete=django.db.models.deletion.SET_NULL, related_name='sent_logs', to='settings.smsgateway', verbose_name='Gateway')),
                ('template', models.ForeignKey(blank=True, null=True, on_delete=django.db.models.deletion.SET_NULL, related_name='sent_logs', to='settings.smstemplate', verbose_name='Şablon')),
            ],
            options={
                'verbose_name': 'SMS Gönderim Logu',
                'verbose_name_plural': 'SMS Gönderim Logları',
                'ordering': ['-created_at'],
            },
        ),
        migrations.AddIndex(
            model_name='smsgateway',
            index=models.Index(fields=['gateway_type', 'is_active'], name='settings_sm_gateway_5a8b8c_idx'),
        ),
        migrations.AddIndex(
            model_name='smsgateway',
            index=models.Index(fields=['is_default'], name='settings_sm_is_defa_123456_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['code'], name='settings_sm_code_789abc_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['category', 'is_active'], name='settings_sm_categor_def456_idx'),
        ),
        migrations.AddIndex(
            model_name='smstemplate',
            index=models.Index(fields=['module_usage'], name='settings_sm_module__ghi789_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['gateway', '-created_at'], name='settings_sm_gateway_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['status', '-created_at'], name='settings_sm_status_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['recipient_phone', '-created_at'], name='settings_sm_recipie_created_idx'),
        ),
        migrations.AddIndex(
            model_name='smssentlog',
            index=models.Index(fields=['related_module', 'related_object_id'], name='settings_sm_related_module_idx'),
        ),
    ]




