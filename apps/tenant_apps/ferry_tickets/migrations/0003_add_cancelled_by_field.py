# Generated manually
from django.conf import settings
from django.db import migrations, models
import django.db.models.deletion


class Migration(migrations.Migration):

    dependencies = [
        migrations.swappable_dependency(settings.AUTH_USER_MODEL),
        ('ferry_tickets', '0002_ferryapisync_started_by_ferryapisync_sync_data_and_more'),
    ]

    operations = [
        migrations.AddField(
            model_name='ferryticket',
            name='cancelled_by',
            field=models.ForeignKey(
                blank=True,
                null=True,
                on_delete=django.db.models.deletion.SET_NULL,
                related_name='cancelled_ferry_tickets',
                to=settings.AUTH_USER_MODEL,
                verbose_name='İptal Eden Kullanıcı'
            ),
        ),
    ]





