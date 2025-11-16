# CarpetOS V2 - Kurulum Rehberi

## Web Uygulaması Kurulumu

### 1. Python Kurulumu
```bash
python --version  # Python 3.8+ gerekli
```

### 2. Sanal Ortam Oluşturma
```bash
cd CARPETOS_V2/WEB_APP
python -m venv venv

# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate
```

### 3. Paketleri Yükleme
```bash
pip install -r requirements.txt
```

### 4. Veritabanı Ayarları
`app.py` dosyasındaki `DB_CONFIG` ayarlarını düzenleyin:
```python
DB_CONFIG = {
    'host': 'localhost',
    'database': 'haliyikama',
    'user': 'root',
    'password': '',  # XAMPP için genellikle boş
    'port': 3306
}
```

### 5. Çalıştırma
```bash
python app.py
```

Tarayıcıda: http://localhost:5000

---

## Desktop Uygulaması Kurulumu

### 1. Python Kurulumu
```bash
python --version  # Python 3.8+ gerekli
```

### 2. Sanal Ortam Oluşturma
```bash
cd CARPETOS_V2/DESKTOP_APP
python -m venv venv

# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate
```

### 3. Paketleri Yükleme
```bash
pip install -r requirements.txt
```

### 4. Veritabanı Ayarları
`main.py` dosyasındaki `db_config` ayarlarını düzenleyin.

### 5. Çalıştırma
```bash
python main.py
```

---

## İki Yönlü Senkronizasyon

- Desktop ve Web uygulaması aynı MySQL veritabanını kullanır
- Desktop uygulaması 10 saniyede bir otomatik yenilenir
- Web uygulaması WebSocket ile real-time güncellemeler alır

---

## Sorun Giderme

### Port 5000 Kullanımda
`app.py` dosyasında port değiştirin:
```python
socketio.run(app, host='0.0.0.0', port=5001, debug=True)
```

### MySQL Bağlantı Hatası
1. XAMPP MySQL'in çalıştığından emin olun
2. `DB_CONFIG` ayarlarını kontrol edin
3. Veritabanının (`haliyikama`) oluşturulduğundan emin olun

### PyQt5 Kurulum Hatası
```bash
pip install PyQt5
```

