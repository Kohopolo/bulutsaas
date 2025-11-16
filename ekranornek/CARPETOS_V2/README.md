# CarpetOS V2 - Halı Yıkama İşletme Yönetim Sistemi

**Tarih:** 2025-01-27  
**Durum:** Tasarım Standartlarına Göre Yeni Sistem  
**Klasör:** CARPETOS_V2 (Eski sistemle karışmaması için)

---

## 📋 Tasarım Standartlarına Göre Oluşturulan Sistem

Bu klasör, aşağıdaki dokümanlara göre tasarlanmıştır:

1. **DESIGN_STANDARD.md** - Tasarım standartları, renk paleti, layout
2. **demo_layout.html** - Referans ekran görünümü
3. **features_list.md** - Özellik listesi ve MVP gereksinimleri
4. **EKRAN_FORMAT_ANALIZ_RAPORU.md** - Teknoloji analizi
5. **EKRAN_PROMPT_VE_TERMINOLOJI.md** - Ekran terminolojisi
6. **EKRAN_YAPISI_YAKINLIK_ANALIZI.md** - Yakınlık analizi

---

## 🎨 Tasarım Standartları

### Renk Paleti
- **Arka Plan:** `#F5F5F5`
- **Panel Arka Plan:** `#FFFFFF`
- **Başlık Mavi:** `#1E3A8A`
- **Vurgu Sarı:** `#FFEB3B`
- **Banner Kırmızı:** `#F44336`
- **Başarı Yeşil:** `#4CAF50`
- **Bilgi Mavi:** `#2196F3`

### Layout
- **3 Sütunlu:** %25 - %50 - %25
- **Header:** 80px yükseklik
- **Footer:** 60px yükseklik
- **Menu Strip:** 24px yükseklik

### Tipografi
- **Ana Başlık:** 16pt, Bold
- **Panel Başlıkları:** 12pt, Bold
- **Normal Metin:** 9pt, Regular
- **Font:** Microsoft Sans Serif / Segoe UI

---

## 📁 Klasör Yapısı

```
CARPETOS_V2/
├── WEB_APP/              # Flask Web Uygulaması
│   ├── app.py
│   ├── templates/
│   │   ├── login.html
│   │   ├── dashboard.html
│   │   ├── customers.html
│   │   └── orders.html
│   ├── static/
│   │   ├── css/
│   │   └── js/
│   └── requirements.txt
├── DESKTOP_APP/          # PyQt5 Desktop Uygulaması
│   ├── main.py
│   └── requirements.txt
├── DOCS/                 # Dokümantasyon
│   ├── TASARIM_STANDARTLARI.md
│   ├── KURULUM.md
│   └── OZELLIKLER.md
└── README.md
```

---

## 🚀 Özellikler

### Tamamlanan
- ✅ Web uygulaması (Flask)
- ✅ Desktop uygulaması (PyQt5)
- ✅ Dashboard (tasarım standartlarına göre)
- ✅ Müşteri yönetimi
- ✅ Sipariş yönetimi
- ✅ İki yönlü senkronizasyon
- ✅ Real-time güncellemeler

### MVP Özellikler (Yapılacaklar)
- [ ] Ödemeler modülü
- [ ] Faturalar modülü
- [ ] Raporlar modülü
- [ ] QR/Barkod sistemi
- [ ] Fotoğraf yönetimi
- [ ] SMS entegrasyonu

---

## 🎯 Referans Ekrana Uyumluluk

- ✅ 3 sütunlu layout (%25-%50-%25)
- ✅ Sol panel: Müşteri listesi
- ✅ Orta panel: Sekmeli sipariş grid'i
- ✅ Sağ panel: Kısayol ikonları
- ✅ Footer: Aksiyon butonları
- ✅ Windows Forms benzeri görünüm

---

## 📝 Kurulum

Detaylı kurulum için: `DOCS/KURULUM.md`

### Hızlı Başlangıç

**Web Uygulaması:**
```bash
cd CARPETOS_V2/WEB_APP
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python app.py
```

**Desktop Uygulaması:**
```bash
cd CARPETOS_V2/DESKTOP_APP
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python main.py
```

---

## 🔄 İki Yönlü Senkronizasyon

- Desktop ve Web aynı MySQL veritabanını kullanır
- Desktop 10 saniyede bir otomatik yenilenir
- Web WebSocket ile real-time güncellemeler alır

---

## 📚 Dokümantasyon

- `DOCS/TASARIM_STANDARTLARI.md` - Tasarım standartları
- `DOCS/KURULUM.md` - Kurulum rehberi
- `DOCS/OZELLIKLER.md` - Özellikler listesi

---

## ✅ Notlar

- Bu klasör eski sistemle karışmaması için ayrı tutulmuştur
- Tüm tasarım standartlarına uygun olarak geliştirilmiştir
- Web ve Desktop uygulaması iki yönlü senkronize çalışır
