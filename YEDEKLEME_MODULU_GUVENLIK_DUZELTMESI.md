# 🔒 Yedekleme Modülü Güvenlik Düzeltmesi

**Tarih:** 2025-01-27  
**Durum:** ✅ Güvenlik Açığı Kapatıldı

---

## 🚨 Tespit Edilen Güvenlik Açığı

### Sorun

**Kritik Güvenlik Açığı**: Her tenant diğer tenant'ların schema'larını görebiliyor ve yedekleyebiliyordu.

**Etkilenen Alanlar:**
1. `backup_list` - Tüm tenant'ların yedeklerini listeliyordu
2. `backup_create` - Tüm tenant'ların schema'larını seçebiliyordu
3. `backup_detail` - Herhangi bir tenant'ın yedeğine erişilebiliyordu
4. `backup_download` - Herhangi bir tenant'ın yedeğini indirilebiliyordu
5. `backup_delete` - Herhangi bir tenant'ın yedeğini silinebiliyordu
6. `backup_database` command - Schema kontrolü yoktu

---

## ✅ Yapılan Düzeltmeler

### 1. `backup_list` View

**Önceki Durum:**
```python
backups = DatabaseBackup.objects.filter(is_deleted=False).order_by('-created_at')
```

**Yeni Durum:**
```python
current_schema = connection.schema_name
backups = DatabaseBackup.objects.filter(
    is_deleted=False,
    schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedekleri
).order_by('-created_at')
```

**Sonuç:** ✅ Her tenant sadece kendi yedeklerini görüyor

---

### 2. `backup_create` View

**Önceki Durum:**
- Tüm tenant'ların schema'ları listeleniyordu
- Herhangi bir tenant'ın schema'sı seçilebiliyordu

**Yeni Durum:**
- Sadece mevcut tenant'ın schema'sı gösteriliyor
- Schema seçimi kaldırıldı, otomatik olarak mevcut tenant'ın schema'sı kullanılıyor
- Public schema'dan yedekleme engellendi

**Kod:**
```python
current_schema = connection.schema_name

# Public schema'da ise erişim engelle
if current_schema == get_public_schema_name():
    messages.error(request, 'Public schema\'dan yedekleme oluşturulamaz.')
    return redirect('backup:backup_list')

# POST isteğinde her zaman mevcut tenant'ın schema'sını kullan
call_command(
    'backup_database',
    schema=current_schema,  # Güvenlik: Her zaman mevcut tenant'ın schema'sı
    type='manual',
    user_id=request.user.id
)
```

**Sonuç:** ✅ Her tenant sadece kendi schema'sını yedekleyebiliyor

---

### 3. `backup_detail` View

**Önceki Durum:**
```python
backup = get_object_or_404(DatabaseBackup, pk=pk, is_deleted=False)
```

**Yeni Durum:**
```python
current_schema = connection.schema_name
backup = get_object_or_404(
    DatabaseBackup, 
    pk=pk, 
    is_deleted=False,
    schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
)
```

**Sonuç:** ✅ Her tenant sadece kendi yedeklerinin detayına erişebiliyor

---

### 4. `backup_download` View

**Önceki Durum:**
```python
backup = get_object_or_404(DatabaseBackup, pk=pk, is_deleted=False)
```

**Yeni Durum:**
```python
current_schema = connection.schema_name
backup = get_object_or_404(
    DatabaseBackup, 
    pk=pk, 
    is_deleted=False,
    schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
)
```

**Sonuç:** ✅ Her tenant sadece kendi yedeklerini indirebiliyor

---

### 5. `backup_delete` View

**Önceki Durum:**
```python
backup = get_object_or_404(DatabaseBackup, pk=pk, is_deleted=False)
```

**Yeni Durum:**
```python
current_schema = connection.schema_name
backup = get_object_or_404(
    DatabaseBackup, 
    pk=pk, 
    is_deleted=False,
    schema_name=current_schema  # Güvenlik: Sadece mevcut tenant'ın yedeği
)
```

**Sonuç:** ✅ Her tenant sadece kendi yedeklerini silebiliyor

---

### 6. `backup_database` Management Command

**Önceki Durum:**
- Schema kontrolü yoktu
- Herhangi bir schema yedeklenebiliyordu

**Yeni Durum:**
- Web request'ten çağrılıyorsa (`user_id` varsa), güvenlik kontrolü yapılıyor
- Sadece mevcut tenant'ın schema'sını yedeklemeye izin veriliyor
- `--all` parametresi web request'ten çağrılıyorsa engelleniyor

**Kod:**
```python
# Güvenlik: Web request'ten çağrılıyorsa, sadece mevcut tenant'ın schema'sını yedekle
current_schema = connection.schema_name

if schema_name and user_id:
    # Güvenlik: Web request'ten çağrılıyorsa, sadece mevcut tenant'ın schema'sını yedeklemeye izin ver
    if current_schema != get_public_schema_name() and schema_name != current_schema:
        raise CommandError(
            f'Güvenlik hatası: Sadece kendi schema\'nızı yedekleyebilirsiniz. '
            f'Mevcut schema: {current_schema}, İstenen schema: {schema_name}'
        )

if backup_all and user_id:
    raise CommandError(
        'Güvenlik hatası: Web request\'ten tüm schema\'ları yedekleme izni yoktur. '
        'Sadece otomatik yedekleme (--type=automatic) tüm schema\'ları yedekleyebilir.'
    )
```

**Sonuç:** ✅ Web request'ten sadece mevcut tenant'ın schema'sı yedeklenebiliyor

---

### 7. Template Güncellemesi

**`backup/create.html` Güncellemesi:**

**Önceki Durum:**
- Tüm tenant'ların schema'ları dropdown'da listeleniyordu

**Yeni Durum:**
- Sadece mevcut tenant'ın schema'sı gösteriliyor (readonly)
- Schema seçimi kaldırıldı
- Güvenlik bilgilendirmesi eklendi

**Kod:**
```html
<div class="form-group">
    <label>Yedeklenecek Schema</label>
    <input type="text" class="form-control" value="{{ current_schema }}" readonly>
    <small style="color: #666; display: block; margin-top: 5px;">
        Sadece kendi schema'nızı yedekleyebilirsiniz. ({{ current_schema }})
    </small>
</div>
<input type="hidden" name="schema_name" value="{{ current_schema }}">
```

**Sonuç:** ✅ Kullanıcı arayüzünde güvenlik açığı kapatıldı

---

## 🔒 Güvenlik Önlemleri

### 1. Schema Kontrolü

- Her view'da `connection.schema_name` ile mevcut tenant kontrolü yapılıyor
- Sadece mevcut tenant'ın schema'sına erişim izni veriliyor

### 2. Query Filtreleme

- Tüm query'lerde `schema_name=current_schema` filtresi eklendi
- `get_object_or_404` ile schema kontrolü yapılıyor

### 3. Command Güvenliği

- Web request'ten (`user_id` varsa) güvenlik kontrolü yapılıyor
- `--all` parametresi web request'ten engelleniyor
- Sadece otomatik yedekleme tüm schema'ları yedekleyebiliyor

### 4. Public Schema Koruması

- Public schema'dan yedekleme oluşturma engellendi
- Public schema sadece otomatik yedekleme ile yedeklenebilir

---

## ✅ Test Edilmesi Gerekenler

- [x] Tenant A sadece kendi yedeklerini görüyor mu?
- [x] Tenant A başka bir tenant'ın yedeğine erişemiyor mu?
- [x] Tenant A sadece kendi schema'sını yedekleyebiliyor mu?
- [x] Tenant A başka bir tenant'ın schema'sını seçemiyor mu?
- [x] Public schema'dan yedekleme oluşturma engelleniyor mu?
- [x] Otomatik yedekleme hala çalışıyor mu?

---

## 📝 Notlar

1. **Otomatik Yedekleme**: `backup_daily` komutu ve Celery Beat tasks hala tüm schema'ları yedekleyebilir (sistem seviyesi)
2. **Public Schema**: Public schema'dan yedekleme oluşturma engellendi (sadece otomatik yedekleme)
3. **Geriye Dönük Uyumluluk**: Mevcut yedekler etkilenmedi, sadece yeni erişimler kontrol ediliyor

---

## 🎯 Sonuç

**Güvenlik açığı başarıyla kapatıldı!**

Artık:
- ✅ Her tenant sadece kendi yedeklerini görebiliyor
- ✅ Her tenant sadece kendi schema'sını yedekleyebiliyor
- ✅ Tenant'lar arası veri erişimi engellendi
- ✅ Otomatik yedekleme sistemi korundu

**Güvenlik Durumu**: ✅ Güvenli

---

**Düzeltme Tarihi:** 2025-01-27  
**Durum:** ✅ Güvenlik Açığı Kapatıldı





