# Generated manually on 2025-11-11

from django.db import migrations, models
import django.db.models.deletion


def assign_default_hotel(apps, schema_editor):
    """Mevcut verileri varsayılan otel'e ata"""
    RoomType = apps.get_model('hotels', 'RoomType')
    BoardType = apps.get_model('hotels', 'BoardType')
    BedType = apps.get_model('hotels', 'BedType')
    RoomFeature = apps.get_model('hotels', 'RoomFeature')
    Hotel = apps.get_model('hotels', 'Hotel')
    
    # Varsayılan oteli bul (is_default=True veya ilk aktif otel)
    default_hotel = Hotel.objects.filter(is_default=True, is_active=True, is_deleted=False).first()
    if not default_hotel:
        default_hotel = Hotel.objects.filter(is_active=True, is_deleted=False).first()
    
    if not default_hotel:
        # Eğer hiç otel yoksa, migration'ı atla
        return
    
    # Null olan kayıtları varsayılan otel'e ata
    RoomType.objects.filter(hotel__isnull=True).update(hotel=default_hotel)
    BoardType.objects.filter(hotel__isnull=True).update(hotel=default_hotel)
    BedType.objects.filter(hotel__isnull=True).update(hotel=default_hotel)
    RoomFeature.objects.filter(hotel__isnull=True).update(hotel=default_hotel)


def reverse_assign_default_hotel(apps, schema_editor):
    """Geri alma işlemi - hiçbir şey yapma"""
    pass


class Migration(migrations.Migration):

    dependencies = [
        ('hotels', '0002_alter_bedtype_options_alter_boardtype_options_and_more'),
    ]

    operations = [
        # Önce mevcut null kayıtları varsayılan otel'e ata
        migrations.RunPython(assign_default_hotel, reverse_assign_default_hotel),
        
        # Sonra null=False yap
        migrations.AlterField(
            model_name='bedtype',
            name='hotel',
            field=models.ForeignKey(
                on_delete=django.db.models.deletion.CASCADE,
                related_name='bed_types',
                to='hotels.hotel',
                verbose_name='Otel'
            ),
        ),
        migrations.AlterField(
            model_name='boardtype',
            name='hotel',
            field=models.ForeignKey(
                on_delete=django.db.models.deletion.CASCADE,
                related_name='board_types',
                to='hotels.hotel',
                verbose_name='Otel'
            ),
        ),
        migrations.AlterField(
            model_name='roomfeature',
            name='hotel',
            field=models.ForeignKey(
                on_delete=django.db.models.deletion.CASCADE,
                related_name='room_features',
                to='hotels.hotel',
                verbose_name='Otel'
            ),
        ),
        migrations.AlterField(
            model_name='roomtype',
            name='hotel',
            field=models.ForeignKey(
                on_delete=django.db.models.deletion.CASCADE,
                related_name='room_types',
                to='hotels.hotel',
                verbose_name='Otel'
            ),
        ),
    ]
