# Tasarım Standartları - Halı Yıkama Otomasyonu WinForms

**Proje:** CarpetOS Halı Yıkama Otomasyonu  
**Platform:** Windows Forms (.NET Framework 4.8)  
**Tarih:** 2025-01-27  
**Versiyon:** 1.0

---

## 🎨 Renk Paleti

### Ana Renkler

| Renk | Hex Kodu | RGB | Kullanım Alanı |
|------|----------|-----|----------------|
| **Arka Plan** | `#F5F5F5` | RGB(245, 245, 245) | Ana form arka planı |
| **Panel Arka Plan** | `#FFFFFF` | RGB(255, 255, 255) | Panel ve container arka planları |
| **Başlık Mavi** | `#1E3A8A` | RGB(30, 58, 138) | Başlık metinleri, önemli etiketler |
| **Vurgu Sarı** | `#FFEB3B` | RGB(255, 235, 59) | Seçili satırlar, vurgu alanları |
| **Banner Kırmızı** | `#F44336` | RGB(244, 67, 54) | Uyarı banner'ları, kritik bildirimler |
| **Başarı Yeşil** | `#4CAF50` | RGB(76, 175, 80) | Başarı mesajları, aktif durumlar |
| **Bilgi Mavi** | `#2196F3` | RGB(33, 150, 243) | Bilgi butonları, linkler |
| **Uyarı Turuncu** | `#FF9800` | RGB(255, 152, 0) | Uyarı mesajları, bekleyen durumlar |
| **Metin Koyu** | `#212121` | RGB(33, 33, 33) | Ana metin rengi |
| **Metin Açık** | `#757575` | RGB(117, 117, 117) | İkincil metin rengi |
| **Kenarlık** | `#E0E0E0` | RGB(224, 224, 224) | Panel ve kontrol kenarlıkları |

### Durum Renkleri

| Durum | Renk | Hex | Kullanım |
|-------|------|-----|----------|
| **Başarılı** | Yeşil | `#4CAF50` | Başarılı işlemler, aktif durumlar |
| **Bilgi** | Mavi | `#2196F3` | Bilgilendirme mesajları |
| **Uyarı** | Turuncu | `#FF9800` | Uyarı mesajları |
| **Hata** | Kırmızı | `#F44336` | Hata mesajları, kritik durumlar |
| **Varsayılan** | Gri | `#9E9E9E` | Nötr durumlar |

---

## 📐 Layout Standartları

### Ana Form Yapısı

```
┌─────────────────────────────────────────────────────────────┐
│ MenuStrip (Yükseklik: 24px)                                 │
├─────────────────────────────────────────────────────────────┤
│ Header Panel (Yükseklik: 80px)                             │
│   - Başlık Label (Font: 16pt, Bold)                        │
│   - Banner Label (Kırmızı arka plan, beyaz metin)           │
│   - Lisans Bilgisi (Sağ üst köşe)                          │
├─────────────────────────────────────────────────────────────┤
│ Main TableLayoutPanel (3 sütun)                             │
│   ├─ Sol Panel (25% genişlik)                              │
│   ├─ Orta Panel (50% genişlik)                             │
│   └─ Sağ Panel (25% genişlik)                              │
├─────────────────────────────────────────────────────────────┤
│ Footer Panel (Yükseklik: 60px)                             │
│   - 12 adet yuvarlak ikon butonu                            │
└─────────────────────────────────────────────────────────────┘
```

### Panel Boyutları

| Panel | Genişlik | Yükseklik | Padding |
|-------|----------|-----------|---------|
| **Header** | %100 | 80px | 10px |
| **Sol Panel** | %25 | Auto | 8px |
| **Orta Panel** | %50 | Auto | 8px |
| **Sağ Panel** | %25 | Auto | 8px |
| **Footer** | %100 | 60px | 5px |

### Margin ve Padding Standartları

- **Form Padding:** 0px (tam ekran kullanımı için)
- **Panel Padding:** 8px
- **Kontrol Margin:** 4px
- **Buton Padding:** 6px (horizontal), 4px (vertical)
- **DataGridView Cell Padding:** 3px

---

## 🔤 Tipografi

### Font Ailesi

**Varsayılan Font:** `Microsoft Sans Serif` (Windows Forms standart)

### Font Boyutları

| Kullanım | Font Size | Font Style | Örnek |
|----------|-----------|------------|-------|
| **Ana Başlık** | 16pt | Bold | "NEGROPOS HALI YIKAMA OTOMASYONU" |
| **Panel Başlıkları** | 12pt | Bold | "MÜŞTERİ LİSTESİ" |
| **Normal Metin** | 9pt | Regular | DataGridView hücreleri |
| **Buton Metinleri** | 9pt | Regular | Buton üzerindeki yazılar |
| **Küçük Metin** | 8pt | Regular | Tooltip, yardım metinleri |
| **Status Label** | 9pt | Regular | Alt durum çubuğu |

### Font Renkleri

- **Ana Metin:** `#212121` (Koyu gri)
- **İkincil Metin:** `#757575` (Orta gri)
- **Vurgu Metin:** `#1E3A8A` (Koyu mavi)
- **Başlık Metin:** `#1E3A8A` (Koyu mavi, Bold)
- **Banner Metin:** `#FFFFFF` (Beyaz, Bold)

---

## 🎯 Kontrol Standartları

### Butonlar

#### Standart Buton
- **Yükseklik:** 30px
- **Minimum Genişlik:** 80px
- **Padding:** 6px (horizontal), 4px (vertical)
- **Border:** 1px solid `#E0E0E0`
- **BackColor:** `#FFFFFF`
- **ForeColor:** `#212121`
- **Font:** 9pt, Regular
- **FlatStyle:** Flat
- **FlatAppearance.BorderColor:** `#E0E0E0`

#### Birincil Buton (Primary)
- **BackColor:** `#2196F3` (Mavi)
- **ForeColor:** `#FFFFFF` (Beyaz)
- **Hover BackColor:** `#1976D2` (Koyu mavi)

#### Başarı Butonu (Success)
- **BackColor:** `#4CAF50` (Yeşil)
- **ForeColor:** `#FFFFFF` (Beyaz)
- **Hover BackColor:** `#388E3C` (Koyu yeşil)

#### Tehlikeli Buton (Danger)
- **BackColor:** `#F44336` (Kırmızı)
- **ForeColor:** `#FFFFFF` (Beyaz)
- **Hover BackColor:** `#D32F2F` (Koyu kırmızı)

#### İkincil Buton (Secondary)
- **BackColor:** `#9E9E9E` (Gri)
- **ForeColor:** `#FFFFFF` (Beyaz)
- **Hover BackColor:** `#757575` (Koyu gri)

### DataGridView

#### Genel Ayarlar
- **BackgroundColor:** `#FFFFFF`
- **BorderStyle:** FixedSingle
- **GridColor:** `#E0E0E0`
- **SelectionMode:** FullRowSelect
- **MultiSelect:** true
- **AllowUserToAddRows:** false
- **AllowUserToDeleteRows:** false
- **ReadOnly:** true (varsayılan)
- **RowHeadersVisible:** false
- **AutoSizeColumnsMode:** Fill

#### Header Stili
- **Header BackColor:** `#1E3A8A` (Koyu mavi)
- **Header ForeColor:** `#FFFFFF` (Beyaz)
- **Header Font:** 9pt, Bold
- **Header Height:** 30px

#### Satır Stili
- **Default Row Height:** 25px
- **Selected Row BackColor:** `#FFEB3B` (Sarı vurgu)
- **Selected Row ForeColor:** `#212121` (Koyu metin)
- **Alternating Rows:** `#F5F5F5` (Açık gri)

#### Hücre Stili
- **Default Cell Padding:** 3px
- **Cell Font:** 9pt, Regular
- **Cell ForeColor:** `#212121`
- **Cell Alignment:** Left (metin), Right (sayı)

### TextBox

#### Standart TextBox
- **Height:** 28px
- **BorderStyle:** FixedSingle
- **BackColor:** `#FFFFFF`
- **ForeColor:** `#212121`
- **Font:** 9pt, Regular
- **Padding:** 4px

#### Arama TextBox
- **Height:** 28px
- **Placeholder Text:** "Ara..." (gri renkte)
- **Border:** 1px solid `#E0E0E0`
- **Focus Border:** 2px solid `#2196F3`

### ComboBox

- **Height:** 28px
- **DropDownStyle:** DropDownList
- **BackColor:** `#FFFFFF`
- **ForeColor:** `#212121`
- **Font:** 9pt, Regular
- **BorderStyle:** FixedSingle

### DateTimePicker

- **Height:** 28px
- **Format:** Short (dd.MM.yyyy)
- **ShowUpDown:** false
- **BackColor:** `#FFFFFF`
- **ForeColor:** `#212121`
- **Font:** 9pt, Regular

### TabControl

- **Appearance:** Normal
- **SizeMode:** Normal
- **Tab Height:** 30px
- **Selected Tab BackColor:** `#FFFFFF`
- **Selected Tab ForeColor:** `#1E3A8A` (Koyu mavi, Bold)
- **Unselected Tab BackColor:** `#E0E0E0`
- **Unselected Tab ForeColor:** `#757575`
- **Tab Border:** 1px solid `#E0E0E0`

### Label

#### Başlık Label
- **Font:** 12pt, Bold
- **ForeColor:** `#1E3A8A` (Koyu mavi)
- **AutoSize:** true

#### Normal Label
- **Font:** 9pt, Regular
- **ForeColor:** `#212121`
- **AutoSize:** true

#### İkincil Label
- **Font:** 8pt, Regular
- **ForeColor:** `#757575`
- **AutoSize:** true

### ListBox

- **BackColor:** `#FFFFFF`
- **ForeColor:** `#212121`
- **BorderStyle:** FixedSingle
- **Font:** 9pt, Regular
- **SelectionMode:** One
- **Selected Item BackColor:** `#2196F3` (Mavi)
- **Selected Item ForeColor:** `#FFFFFF` (Beyaz)

---

## 🎨 İkon Standartları

### İkon Boyutları

| Kullanım | Boyut | Örnek |
|----------|-------|-------|
| **Küçük İkon** | 16x16px | Toolbar butonları |
| **Orta İkon** | 24x24px | Kısayol butonları |
| **Büyük İkon** | 32x32px | Footer butonları |
| **Çok Büyük İkon** | 48x48px | Sağ panel kısayolları |

### İkon Renkleri

- **Varsayılan:** `#757575` (Orta gri)
- **Hover:** `#2196F3` (Mavi)
- **Aktif:** `#1E3A8A` (Koyu mavi)
- **Başarı:** `#4CAF50` (Yeşil)
- **Uyarı:** `#FF9800` (Turuncu)
- **Hata:** `#F44336` (Kırmızı)

### İkon Kütüphanesi

**Önerilen:** Windows Forms SystemIcons veya özel ikon seti

**Kullanılan İkonlar:**
- 🔄 Yenile (Refresh)
- 🔍 Ara (Search)
- 🗑️ Sil (Delete)
- ℹ️ Bilgi (Info)
- ❓ Yardım (Help)
- ⚙️ Ayarlar (Settings)
- 💬 Mesajlar (Messages)
- 🔢 Hesaplayıcı (Calculator)
- 👤 Kullanıcı (User)
- 🎨 Tema (Theme)
- 📡 Bağlantı (Connection)
- 💰 Finans (Finance)
- 🖥️ Bilgisayar (Computer)
- 📱 Online Sipariş (Online Order)
- 📞 Telefon (Phone)
- 👥 Müşteri Yönetimi (Customer Management)
- ➕ Yeni Ekle (Add New)
- 📊 Raporlar (Reports)

---

## 📱 Responsive ve Adaptif Tasarım

### Minimum Ekran Çözünürlüğü

- **Minimum Genişlik:** 1024px
- **Minimum Yükseklik:** 768px
- **Önerilen:** 1920x1080px veya üzeri

### Form Boyutlandırma

- **WindowState:** Maximized (varsayılan)
- **MinimumSize:** 1024x768px
- **FormBorderStyle:** Sizable
- **StartPosition:** CenterScreen

### Panel Boyutlandırma

- **Dock:** Fill (TableLayoutPanel içinde)
- **AutoSize:** false
- **MinimumSize:** Belirtilen boyutlar

---

## 🎭 Animasyon ve Geçişler

### Hover Efektleri

- **Buton Hover:** BackColor değişimi (0.2s geçiş)
- **DataGridView Row Hover:** Arka plan rengi değişimi
- **İkon Hover:** Renk değişimi ve hafif büyüme (scale 1.1)

### Tıklama Efektleri

- **Buton Click:** Hafif basma efekti (BackColor koyulaşması)
- **DataGridView Cell Click:** Satır seçimi ve vurgulama

### Geçiş Süreleri

- **Hover Geçişi:** 200ms
- **Click Geçişi:** 100ms
- **Form Açılma:** 300ms fade-in

---

## ✅ Erişilebilirlik Standartları

### Klavye Navigasyonu

- **Tab Order:** Mantıksal sıralama
- **Enter Key:** Varsayılan buton aktivasyonu
- **Escape Key:** Form kapatma veya iptal
- **F1 Key:** Yardım açma
- **F5 Key:** Yenileme
- **Ctrl+F:** Arama

### Görsel Geri Bildirim

- **Focus Indicator:** 2px mavi kenarlık (`#2196F3`)
- **Selected State:** Sarı vurgu (`#FFEB3B`)
- **Disabled State:** Gri renk (`#9E9E9E`) ve %50 opacity

### Tooltip Standartları

- **Font:** 8pt, Regular
- **BackColor:** `#212121` (Koyu gri)
- **ForeColor:** `#FFFFFF` (Beyaz)
- **AutoPopDelay:** 5000ms
- **InitialDelay:** 500ms

---

## 📋 Form Standartları

### Modal Formlar

- **FormBorderStyle:** FixedDialog
- **MaximizeBox:** false
- **MinimizeBox:** false
- **StartPosition:** CenterParent
- **ShowInTaskbar:** false

### Dialog Formlar

- **FormBorderStyle:** FixedDialog
- **AcceptButton:** Tamam butonu
- **CancelButton:** İptal butonu
- **DialogResult:** OK veya Cancel

### Child Formlar

- **MdiParent:** MainForm
- **WindowState:** Normal
- **StartPosition:** CenterParent

---

## 🔍 Veri Görselleştirme

### DataGridView Sütun Tipleri

| Sütun Tipi | Hizalama | Format | Genişlik |
|------------|----------|--------|----------|
| **Metin** | Left | - | Auto |
| **Sayı** | Right | N0 (virgülsüz) | 80px |
| **Para** | Right | C2 (TL) | 100px |
| **Tarih** | Center | dd.MM.yyyy | 100px |
| **Saat** | Center | HH:mm | 70px |
| **Checkbox** | Center | - | 50px |
| **Durum** | Center | Badge/Icon | 80px |

### Özet Panelleri

- **BackColor:** `#F5F5F5` (Açık gri)
- **Border:** 1px solid `#E0E0E0`
- **Padding:** 10px
- **Font:** 9pt, Regular
- **Label Font:** 9pt, Bold
- **Value Font:** 10pt, Bold, `#1E3A8A` (Koyu mavi)

---

## 🎨 Özel Kontroller

### CustomerPanel

- **BackColor:** `#FFFFFF`
- **Border:** 1px solid `#E0E0E0`
- **Padding:** 8px
- **DataGridView:** Standart DataGridView stili
- **Arama TextBox:** Standart TextBox stili
- **Butonlar:** Küçük butonlar (24px yükseklik)

### OrderPanel

- **BackColor:** `#FFFFFF`
- **Border:** 1px solid `#E0E0E0`
- **Padding:** 8px
- **TabControl:** Standart TabControl stili
- **DataGridView:** Standart DataGridView stili
- **Özet Panel:** Açık gri arka plan

### ShortcutPanel

- **BackColor:** `#F5F5F5`
- **Border:** Yok
- **Padding:** 10px
- **Butonlar:** Dikey stack, 48x48px ikonlar
- **Buton Spacing:** 10px

---

## 📝 Mesaj ve Bildirim Standartları

### MessageBox Stilleri

| Tip | Icon | BackColor | ForeColor |
|-----|------|-----------|-----------|
| **Bilgi** | Information | `#2196F3` | `#FFFFFF` |
| **Başarı** | None | `#4CAF50` | `#FFFFFF` |
| **Uyarı** | Warning | `#FF9800` | `#FFFFFF` |
| **Hata** | Error | `#F44336` | `#FFFFFF` |
| **Soru** | Question | `#2196F3` | `#FFFFFF` |

### Status Label

- **BackColor:** `#F5F5F5`
- **ForeColor:** Duruma göre (Yeşil: Bağlı, Turuncu: Uyarı, Kırmızı: Hata)
- **Font:** 9pt, Regular
- **Padding:** 5px
- **TextAlign:** Left

---

## 🚀 Performans Standartları

### Yükleme Süreleri

- **Form Açılma:** < 500ms
- **DataGridView Yükleme:** < 1000ms (1000 kayıt için)
- **API İsteği:** < 2000ms
- **Veritabanı Sorgusu:** < 500ms

### Optimizasyon Kuralları

- **Virtual Mode:** DataGridView'de 1000+ kayıt için
- **Lazy Loading:** Büyük listeler için
- **Caching:** Sık kullanılan veriler için
- **Async Operations:** Uzun süren işlemler için

---

## 📚 Kaynaklar ve Referanslar

### Renk Paleti Kaynağı
- Material Design Color Palette
- Windows Forms System Colors

### İkon Kaynakları
- Windows Forms SystemIcons
- Font Awesome (web için)
- Özel ikon seti

### Font Kaynakları
- Microsoft Sans Serif (Windows Forms varsayılan)
- Segoe UI (modern Windows uygulamaları için alternatif)

---

## ✅ Kontrol Listesi

### Tasarım Uyumu Kontrolü

- [ ] Tüm renkler standart paletten mi?
- [ ] Font boyutları standartlara uygun mu?
- [ ] Buton boyutları tutarlı mı?
- [ ] DataGridView stilleri uygulanmış mı?
- [ ] İkonlar doğru boyutta mı?
- [ ] Tooltip'ler eklenmiş mi?
- [ ] Klavye navigasyonu çalışıyor mu?
- [ ] Erişilebilirlik standartlarına uygun mu?

---

**Dokümantasyon Hazırlayan:** AI Asistan  
**Tarih:** 2025-01-27  
**Versiyon:** 1.0  
**Son Güncelleme:** 2025-01-27

