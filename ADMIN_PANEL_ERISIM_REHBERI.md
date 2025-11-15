# Admin Panel Erişim Rehberi

## Tarih: 2025-11-14

### Önemli Not
Tenant domain'lerinde (`test-otel.localhost`) admin paneli **YOKTUR**. Bu doğru bir davranıştır.

---

## 🌐 Admin Panel Erişim Yolları

### ✅ Public Schema (Super Admin)
Admin paneline erişmek için **public schema** domain'lerini kullanın:

#### 1. Localhost:
```
http://localhost:8000/admin/
```

#### 2. 127.0.0.1:
```
http://127.0.0.1:8000/admin/
```

### ❌ Tenant Domain'lerinde Admin Yok
Aşağıdaki URL'ler **404 hatası** verecektir (bu normaldir):
- ❌ `http://test-otel.localhost:8000/admin/` → 404
- ❌ `http://test-otel.127.0.0.1:8000/admin/` → 404

---

## 📊 URL Yapılandırması

### Public Schema (`config/urls_public.py`)
- ✅ `/admin/` → Django Admin (Super Admin)
- ✅ `/` → Landing Page
- ✅ `/payments/` → Ödeme sistemi

### Tenant Schema (`config/urls.py`)
- ❌ `/admin/` → YOK (404)
- ✅ `/` → Tenant Dashboard
- ✅ `/login/` → Tenant Login
- ✅ `/hotels/` → Otel Yönetimi
- ✅ `/reception/` → Resepsiyon
- ✅ Tüm modül URL'leri

---

## 🔧 Yapılan Değişiklik

### Tenant URL'lerinden Admin Kaldırıldı
**Dosya:** `config/urls.py`

**Sebep:**
- Tenant domain'lerinde admin paneli olmamalı
- Admin paneli sadece public schema'da (super admin için)
- Her tenant kendi dashboard'unu kullanmalı

---

## ✅ Doğru Kullanım

### Super Admin İçin:
```
http://localhost:8000/admin/
```

### Tenant Kullanıcıları İçin:
```
http://test-otel.localhost:8000/
http://test-otel.localhost:8000/login/
http://test-otel.localhost:8000/dashboard/
```

---

**Son Güncelleme:** 2025-11-14




## Tarih: 2025-11-14

### Önemli Not
Tenant domain'lerinde (`test-otel.localhost`) admin paneli **YOKTUR**. Bu doğru bir davranıştır.

---

## 🌐 Admin Panel Erişim Yolları

### ✅ Public Schema (Super Admin)
Admin paneline erişmek için **public schema** domain'lerini kullanın:

#### 1. Localhost:
```
http://localhost:8000/admin/
```

#### 2. 127.0.0.1:
```
http://127.0.0.1:8000/admin/
```

### ❌ Tenant Domain'lerinde Admin Yok
Aşağıdaki URL'ler **404 hatası** verecektir (bu normaldir):
- ❌ `http://test-otel.localhost:8000/admin/` → 404
- ❌ `http://test-otel.127.0.0.1:8000/admin/` → 404

---

## 📊 URL Yapılandırması

### Public Schema (`config/urls_public.py`)
- ✅ `/admin/` → Django Admin (Super Admin)
- ✅ `/` → Landing Page
- ✅ `/payments/` → Ödeme sistemi

### Tenant Schema (`config/urls.py`)
- ❌ `/admin/` → YOK (404)
- ✅ `/` → Tenant Dashboard
- ✅ `/login/` → Tenant Login
- ✅ `/hotels/` → Otel Yönetimi
- ✅ `/reception/` → Resepsiyon
- ✅ Tüm modül URL'leri

---

## 🔧 Yapılan Değişiklik

### Tenant URL'lerinden Admin Kaldırıldı
**Dosya:** `config/urls.py`

**Sebep:**
- Tenant domain'lerinde admin paneli olmamalı
- Admin paneli sadece public schema'da (super admin için)
- Her tenant kendi dashboard'unu kullanmalı

---

## ✅ Doğru Kullanım

### Super Admin İçin:
```
http://localhost:8000/admin/
```

### Tenant Kullanıcıları İçin:
```
http://test-otel.localhost:8000/
http://test-otel.localhost:8000/login/
http://test-otel.localhost:8000/dashboard/
```

---

**Son Güncelleme:** 2025-11-14




