# Left Panel Eksik Modüller Analizi

## Context Processor'da Tanımlı Olan Ama Left Panel'de Görünmeyen Modüller

### 1. Teknik Servis (technical_service)
- **Context Variable:** `has_technical_service_module`
- **Modül Kodu:** `technical_service`
- **URL Prefix:** `/technical-service/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Bakım talepleri yönetimi
  - Bakım kayıtları
  - Ekipman envanteri
  - Önleyici bakım planlama

### 2. Kalite Kontrol (quality_control)
- **Context Variable:** `has_quality_control_module`
- **Modül Kodu:** `quality_control`
- **URL Prefix:** `/quality-control/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Oda kalite kontrolü
  - Hizmet kalite değerlendirmesi
  - Müşteri şikayet yönetimi
  - Kalite standartları takibi
  - Denetim raporları

### 3. Satış Yönetimi (sales)
- **Context Variable:** `has_sales_module`
- **Modül Kodu:** `sales`
- **URL Prefix:** `/sales/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Rezervasyon satışları
  - Acente yönetimi
  - Komisyon takibi
  - Satış raporları
  - Hedef takibi

### 4. Personel Yönetimi (staff)
- **Context Variable:** `has_staff_module`
- **Modül Kodu:** `staff`
- **URL Prefix:** `/staff/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Personel kayıtları
  - Vardiya yönetimi
  - İzin yönetimi
  - Performans takibi
  - Maaş yönetimi

### 5. Ödeme Yönetimi (payment_management)
- **Context Variable:** `has_payment_management_module`
- **Modül Kodu:** `payment_management`
- **URL Prefix:** `/payment-management/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Ödeme yönetimi

### 6. Kanal Yönetimi (channel_management)
- **Context Variable:** `has_channel_management_module`
- **Modül Kodu:** `channel_management`
- **URL Prefix:** `/channel-management/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Kanal yönetimi
  - Entegrasyonlar

### 7. Feribot Bileti (ferry_tickets)
- **Context Variable:** `has_ferry_tickets_module`
- **Modül Kodu:** `ferry_tickets`
- **URL Prefix:** `/ferry-tickets/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Feribot bileti yönetimi
  - Rezervasyon sistemi

### 8. Bungalov Yönetimi (bungalovs)
- **Context Variable:** `has_bungalovs_module`
- **Modül Kodu:** `bungalovs`
- **URL Prefix:** `/bungalovs/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Bungalov yönetimi
  - Rezervasyon sistemi

### 9. Yedekleme Yönetimi (backup)
- **Context Variable:** `has_backup_module`
- **Modül Kodu:** `backup`
- **URL Prefix:** `/backup/`
- **Durum:** ❌ Left panel'de YOK
- **Özellikler:**
  - Veri yedekleme
  - Yedek yönetimi

## URL'de Olan Ama Context Processor'da Olmayan Modüller

### 10. AI Yönetimi (ai)
- **Modül Kodu:** `ai`
- **URL Prefix:** `/ai/`
- **Durum:** ⚠️ URL'de var ama context processor'da YOK
- **Not:** Context processor'a eklenmesi gerekiyor

## Left Panel'de Olan Ama Context Processor'da Olmayan Modüller

### 11. Raporlar (reports)
- **Modül Kodu:** `reports`
- **URL Prefix:** `/reports/`
- **Durum:** ⚠️ Left panel'de var ama context processor'da YOK
- **Not:** Context processor'a eklenmesi gerekiyor

## Önerilen Gruplandırma

### Otel Yönetimi Grubuna Eklenecekler:
- Teknik Servis (technical_service)
- Kalite Kontrol (quality_control)

### Yeni Grup: Operasyon
- Satış Yönetimi (sales)
- Personel Yönetimi (staff)
- Kanal Yönetimi (channel_management)

### Finans Grubuna Eklenecekler:
- Ödeme Yönetimi (payment_management)

### Yeni Grup: Alternatif Konaklama
- Feribot Bileti (ferry_tickets)
- Bungalov Yönetimi (bungalovs)

### Sistem Grubuna Eklenecekler:
- Yedekleme Yönetimi (backup)
- AI Yönetimi (ai) - Context processor'a eklenmeli
- Raporlar (reports) - Context processor'a eklenmeli

## Toplam Eksik Modül Sayısı: 11

