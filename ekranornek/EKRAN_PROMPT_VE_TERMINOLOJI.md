# Ekran Görüntüsü Prompt ve Terminoloji

**Tarih:** 2025-01-27

---

## 🎨 Ekran Tipi Terminoloji

### Bu Ekran Tipinin Adları:

1. **"Desktop Application Interface"** (Masaüstü Uygulama Arayüzü)
2. **"Multi-Pane Dashboard"** (Çok Panelli Dashboard)
3. **"Data-Intensive Business Application UI"** (Veri Yoğun İş Uygulaması Arayüzü)
4. **"Windows Forms Style Interface"** (Windows Forms Tarzı Arayüz)
5. **"Legacy Desktop Application Layout"** (Eski Nesil Masaüstü Uygulama Düzeni)
6. **"Three-Column Layout Dashboard"** (Üç Sütunlu Dashboard Düzeni)
7. **"Master-Detail Interface"** (Ana-Detay Arayüzü)
8. **"Grid-Based Management System UI"** (Grid Tabanlı Yönetim Sistemi Arayüzü)

### Türkçe Terimler:
- **"Masaüstü Otomasyon Ekranı"**
- **"Çok Panelli Yönetim Paneli"**
- **"Grid Tabanlı İş Uygulaması Arayüzü"**
- **"Windows Forms Benzeri Arayüz"**
- **"Klasik Desktop Uygulama Ekranı"**

---

## 📝 Ekran Görüntüsü Prompt'u

### Detaylı Prompt (İngilizce)

```
Create a desktop application interface for a carpet cleaning business management system with the following specifications:

**Layout Structure:**
- Top header bar with application title "NegroPos Halı Yıkama Otomasyonu" (NegroPos Carpet Cleaning Automation)
- Menu bar with options: "Dosya" (File), "Müşteri İşlemler" (Customer Operations), "Raporlar" (Reports), "Ayarlar" (Settings), "Yardım" (Help)
- Support phone number and SMS balance display on the right side of header
- Second header section with large application title and red banner showing "Okunmamış Online Sipariş Yok" (No Unread Online Orders)
- License holder information displayed on the right

**Main Content Area - Three Column Layout:**

**Left Panel (25% width):**
- Customer list section with title "TOPLAM : [NUMBER] KAYIT" (Total: [NUMBER] Records)
- Scrollable table with columns: "MŞ. NO" (Customer No.) and "CARİ ADI" (Customer Name)
- Customer entries displayed in rows (e.g., "HAVVA SARI", "HASAN YAYLA", "GÜLAY ERGUN")
- Below customer list: "SON ÇAĞRILAR" (Last Calls) section showing recent call entries
- Three circular action buttons at bottom: green refresh icon, blue search icon, red delete icon

**Center Panel (50% width):**
- Tabbed interface with multiple tabs:
  * "SİPARİŞLER" (Orders - active tab)
  * "YIKAMADA OLANLAR" (In Washing)
  * "TESLİM ZAMANI GELENLER" (Due for Delivery)
  * "TESLİMAT LİSTESİ" (Delivery List)
  * "TESLİM EDİLENLER" (Delivered)
  * "BEKLEYEN TESLİMAT" (Pending Delivery)
  * "İPTAL" (Cancelled)
  * "AJANDA" (Agenda)
- Date navigator below tabs showing "01 Ocak 2017 Pazar" (01 January 2017 Sunday) with left/right arrows and calendar icon
- Vehicle filter dropdown "ARAÇ: Seçiniz" (Vehicle: Select) with "Getir" (Fetch) button
- Large data grid table with columns:
  * "SEÇ" (Select - checkboxes)
  * "MU.NO" (Customer Number)
  * "FİŞ" (Receipt Number)
  * "CARİ ADI" (Customer Name)
  * "BÖLGE" (Region)
  * "AÇIKLAMA" (Description)
  * "ARAÇ" (Vehicle)
  * "SAAT" (Time)
  * "ALIŞ SAAT" (Pickup Time)
  * "ADET" (Quantity)
  * "M2" (Square Meters)
  * "TUTAR" (Amount)
- Some rows highlighted in yellow indicating selected items
- Summary section below grid showing:
  * "TOPLAM TESLİM ALINACAK : 6 ADET" (Total to be Delivered: 6 Items)
  * "TESLİM ALINAN HALI ADEDİ: 16" (Number of Carpets Picked Up: 16)
  * "TESLİM ALINAN TOPLAM M2: 50 M2" (Total M2 Picked Up: 50 M2)
  * "TESLİM ALINANLAR TUTARI: 247,00 ₺" (Total Amount for Picked Up Items: 247,00 ₺)
- Vehicle selection dropdown and action buttons (green checkmark, red van icon)

**Right Panel (25% width):**
- Vertical stack of large colorful icon buttons:
  * Monitor icon
  * "online sipariş" (online order) icon
  * Red telephone icon
  * Two person silhouettes (customer management)
  * Single person silhouette with green plus sign (add customer)
  * Stack of documents/reports icon

**Bottom Footer Bar:**
- Row of large circular colorful icons for quick actions:
  * Green refresh icon
  * Blue search icon
  * Red trash can icon
  * NegroPos logo
  * Purple question mark (help)
  * Blue gear (settings)
  * Blue SMS icon
  * Calculator icon
  * Blue person with 'i' (information)
  * Paint palette icon
  * Satellite dish with computer icon
  * Cash register/POS terminal icon

**Design Characteristics:**
- Color scheme: Predominantly blue, white, and gray with colorful icons
- Style: Windows Forms-like desktop application aesthetic
- Information density: High - designed for data entry and management
- Visual hierarchy: Clear separation between sections
- Icons: Large, colorful, easily recognizable
- Typography: Clear, readable fonts suitable for business application
- Grid: Dense data grid with multiple columns and rows
- Interactive elements: Clickable rows, selectable checkboxes, tabbed interface
- Status indicators: Yellow highlighting for selected items, red banner for notifications
```

---

### Kısa Prompt (İngilizce)

```
Create a Windows Forms-style desktop application interface for a carpet cleaning business management system. The layout should have:

1. Top menu bar with File, Customer Operations, Reports, Settings, Help menus
2. Header with application title and notification banner
3. Three-column main layout:
   - Left: Customer list with search/filter
   - Center: Tabbed order management with large data grid showing orders, vehicles, times, amounts
   - Right: Vertical shortcut icon menu
4. Bottom footer with circular action icons
5. Color scheme: Blue, white, gray with colorful icons
6. High information density, business application aesthetic
7. Turkish language labels throughout
```

---

### Türkçe Prompt

```
Halı yıkama işletmesi yönetim sistemi için masaüstü uygulama arayüzü oluştur:

**Genel Yapı:**
- Üst menü çubuğu: Dosya, Müşteri İşlemler, Raporlar, Ayarlar, Yardım
- Başlık bölümü: "NegroPos Halı Yıkama Otomasyonu" ve bildirim banner'ı
- Üç sütunlu ana içerik alanı

**Sol Panel (%25 genişlik):**
- Müşteri listesi tablosu (MŞ. NO, CARİ ADI sütunları)
- "TOPLAM: [SAYI] KAYIT" başlığı
- Son çağrılar listesi
- Alt kısımda 3 yuvarlak aksiyon butonu (yenile, ara, sil)

**Orta Panel (%50 genişlik):**
- Sekmeli arayüz: Siparişler, Yıkamada Olanlar, Teslim Zamanı Gelenler, Teslimat Listesi, Teslim Edilenler, Bekleyen Teslimat, İptal, Ajanda
- Tarih navigasyonu (ileri/geri oklar, takvim ikonu)
- Araç filtresi dropdown'u
- Büyük veri grid'i (SEÇ, MU.NO, FİŞ, CARİ ADI, BÖLGE, AÇIKLAMA, ARAÇ, SAAT, ALIŞ SAAT, ADET, M2, TUTAR sütunları)
- Seçili satırlar sarı renkle vurgulanmış
- Grid altında özet bilgiler (toplam teslim alınacak, teslim alınan halı adedi, m2, tutar)
- Araç seçimi ve aksiyon butonları

**Sağ Panel (%25 genişlik):**
- Dikey kısayol ikon menüsü (monitör, online sipariş, telefon, müşteri yönetimi, müşteri ekle, raporlar)

**Alt Footer Bar:**
- Yuvarlak renkli ikon butonları (yenile, ara, sil, logo, yardım, ayarlar, SMS, hesap makinesi, bilgi, palet, uydu, kasa)

**Tasarım Özellikleri:**
- Renk paleti: Mavi, beyaz, gri tonları + renkli ikonlar
- Stil: Windows Forms benzeri masaüstü uygulama estetiği
- Yüksek bilgi yoğunluğu
- İş uygulaması görünümü
- Türkçe etiketler
```

---

### AI Image Generation Prompt (Midjourney/DALL-E)

```
A detailed desktop application interface screenshot for a Turkish carpet cleaning business management system. Windows Forms style UI with three-column layout. Left panel shows customer list table, center panel has tabbed interface with large data grid showing orders with columns for customer name, region, vehicle, time, quantity, square meters, and amount. Right panel displays vertical shortcut icons. Top menu bar with Turkish labels: Dosya, Müşteri İşlemler, Raporlar, Ayarlar, Yardım. Bottom footer with circular colorful action icons. Blue, white, and gray color scheme with colorful icons. High information density, business application aesthetic, Turkish language throughout. Professional software interface, clean layout, Windows desktop application style.
```

---

### UI/UX Design Brief Prompt

```
Design a desktop application interface for a carpet cleaning business management system with the following requirements:

**Application Type:** Windows Forms-style desktop application (can be web-based but with desktop-like appearance)

**Layout:** Three-column dashboard layout
- Left sidebar (25%): Customer management panel
- Main content area (50%): Order management with tabbed interface and data grid
- Right sidebar (25%): Quick access shortcuts

**Key Features:**
- Multi-tab interface for different order statuses
- Large data grid with sortable columns
- Customer list with search functionality
- Date navigation and filtering
- Vehicle assignment and tracking
- Summary statistics panel
- Icon-based navigation and actions

**Visual Style:**
- Business application aesthetic
- High information density
- Clear visual hierarchy
- Color-coded status indicators
- Large, recognizable icons
- Professional color scheme (blues, grays, whites with accent colors)

**User Experience:**
- Quick access to frequently used functions
- Easy data entry and editing
- Real-time status updates
- Efficient navigation between sections
- Clear feedback for user actions
```

---

## 🏷️ Ekran Tipi Kategorileri

### 1. **UI Pattern Kategorisi:**
- **Master-Detail Pattern** (Ana-Detay Deseni)
- **Dashboard Pattern** (Dashboard Deseni)
- **Data Grid Pattern** (Veri Grid Deseni)
- **Multi-Pane Layout** (Çok Panelli Düzen)

### 2. **Application Type:**
- **Desktop Application UI** (Masaüstü Uygulama Arayüzü)
- **Business Management System** (İş Yönetim Sistemi)
- **Enterprise Application Interface** (Kurumsal Uygulama Arayüzü)
- **Data-Intensive Application** (Veri Yoğun Uygulama)

### 3. **Design Era:**
- **Legacy Desktop UI** (Eski Nesil Masaüstü Arayüzü)
- **Windows Forms Era** (Windows Forms Dönemi)
- **Pre-Modern UI** (Modern Öncesi Arayüz)
- **Classic Desktop Application** (Klasik Masaüstü Uygulaması)

### 4. **Layout Type:**
- **Three-Column Layout** (Üç Sütunlu Düzen)
- **Split-Pane Layout** (Bölünmüş Panel Düzeni)
- **Dashboard Layout** (Dashboard Düzeni)
- **Grid-Based Layout** (Grid Tabanlı Düzen)

---

## 📚 İlgili Terimler ve Açıklamalar

### UI/UX Terimleri:
1. **Dashboard** - Kontrol paneli, özet bilgilerin gösterildiği ana ekran
2. **Data Grid** - Veri tablosu, çoklu satır/sütun içeren veri gösterimi
3. **Master-Detail View** - Ana liste ve detay görünümü kombinasyonu
4. **Tabbed Interface** - Sekmeli arayüz, farklı içerikler arasında geçiş
5. **Sidebar Navigation** - Yan panel navigasyonu
6. **Toolbar** - Araç çubuğu, hızlı erişim butonları
7. **Status Bar** - Durum çubuğu, alt bilgi alanı
8. **Modal Dialog** - Açılır pencere, modal diyalog

### Mimari Terimleri:
1. **Single Page Application (SPA)** - Tek sayfa uygulaması (modern web)
2. **Multi-Page Application (MPA)** - Çok sayfa uygulaması (klasik web)
3. **Desktop Application** - Masaüstü uygulaması
4. **Web Application** - Web uygulaması
5. **Hybrid Application** - Hibrit uygulama (web + desktop)

---

## 🎯 Bu Ekran Tipinin Modern Karşılıkları

### Modern Web Eşdeğerleri:
1. **Admin Dashboard** - Yönetim paneli
2. **Data Management Interface** - Veri yönetim arayüzü
3. **CRM Interface** - Müşteri ilişkileri yönetimi arayüzü
4. **ERP Module Interface** - ERP modül arayüzü
5. **Business Intelligence Dashboard** - İş zekası dashboard'u

### Modern Framework'lerdeki Karşılıkları:
- **React Admin** - React tabanlı admin paneli
- **Vue Admin** - Vue tabanlı admin paneli
- **Angular Material Dashboard** - Angular Material dashboard
- **Blazor Admin** - Blazor admin paneli
- **ASP.NET AdminLTE** - Bootstrap tabanlı admin paneli

---

## 💡 Kullanım Senaryoları

Bu ekran tipi şu durumlarda kullanılır:
- ✅ İş yönetim sistemleri
- ✅ ERP modülleri
- ✅ CRM sistemleri
- ✅ Envanter yönetim sistemleri
- ✅ Sipariş takip sistemleri
- ✅ Müşteri yönetim sistemleri
- ✅ Raporlama ve analiz panelleri
- ✅ Veri giriş ve düzenleme ekranları

---

**Hazırlayan:** AI Asistan  
**Tarih:** 2025-01-27

