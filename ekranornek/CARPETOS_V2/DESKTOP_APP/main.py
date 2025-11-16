"""
CarpetOS V2 - Halı Yıkama İşletme Yönetim Sistemi
PyQt5 Desktop Application - Tasarım Standartlarına Göre
"""

import sys
import mysql.connector
from mysql.connector import Error
from PyQt5.QtWidgets import (QApplication, QMainWindow, QWidget, QVBoxLayout, 
                             QHBoxLayout, QTableWidget, QTableWidgetItem, QPushButton,
                             QLineEdit, QLabel, QMessageBox, QDialog, QFormLayout,
                             QComboBox, QTabWidget, QHeaderView, QStatusBar, QMenuBar,
                             QMenu, QSplitter, QGroupBox, QCheckBox, QDateEdit, QCalendarWidget)
from PyQt5.QtCore import Qt, QTimer, QThread, pyqtSignal, QDate
from PyQt5.QtGui import QFont, QColor, QPalette
import requests
from datetime import datetime
import json

# DESIGN_STANDARD.md'ye göre renkler
COLORS = {
    'bg': '#F5F5F5',
    'panel_bg': '#FFFFFF',
    'header_blue': '#1E3A8A',
    'accent_yellow': '#FFEB3B',
    'banner_red': '#F44336',
    'success_green': '#4CAF50',
    'info_blue': '#2196F3',
    'text_dark': '#212121',
    'text_light': '#757575',
    'border': '#E0E0E0'
}

class DatabaseSyncThread(QThread):
    """Veritabanı senkronizasyon thread'i"""
    sync_completed = pyqtSignal(str)
    
    def __init__(self, db_config):
        super().__init__()
        self.db_config = db_config
        self.running = True
    
    def run(self):
        """Periyodik senkronizasyon"""
        while self.running:
            try:
                connection = mysql.connector.connect(**self.db_config)
                cursor = connection.cursor(dictionary=True)
                cursor.execute("SELECT COUNT(*) as count FROM customers")
                result = cursor.fetchone()
                cursor.close()
                connection.close()
                
                self.sync_completed.emit(f"Senkronize: {result['count']} müşteri")
            except Exception as e:
                self.sync_completed.emit(f"Hata: {str(e)}")
            
            self.msleep(10000)  # 10 saniyede bir kontrol et
    
    def stop(self):
        self.running = False

class CustomerDialog(QDialog):
    """Müşteri ekleme/düzenleme dialog'u"""
    def __init__(self, parent=None, customer=None):
        super().__init__(parent)
        self.customer = customer
        self.setWindowTitle("Yeni Müşteri" if not customer else "Müşteri Düzenle")
        self.setModal(True)
        self.setFixedSize(400, 300)
        self.setupUI()
        
        if customer:
            self.loadCustomer()
    
    def setupUI(self):
        layout = QFormLayout()
        
        self.customer_number = QLineEdit()
        self.first_name = QLineEdit()
        self.last_name = QLineEdit()
        self.phone = QLineEdit()
        self.email = QLineEdit()
        self.category = QComboBox()
        self.category.addItems(["Bireysel", "Kurumsal", "VIP"])
        
        layout.addRow("Müşteri No:", self.customer_number)
        layout.addRow("Ad:", self.first_name)
        layout.addRow("Soyad:", self.last_name)
        layout.addRow("Telefon:", self.phone)
        layout.addRow("E-posta:", self.email)
        layout.addRow("Kategori:", self.category)
        
        buttons = QHBoxLayout()
        self.save_btn = QPushButton("Kaydet")
        self.cancel_btn = QPushButton("İptal")
        self.save_btn.clicked.connect(self.accept)
        self.cancel_btn.clicked.connect(self.reject)
        buttons.addWidget(self.save_btn)
        buttons.addWidget(self.cancel_btn)
        
        layout.addRow(buttons)
        self.setLayout(layout)
    
    def loadCustomer(self):
        if self.customer:
            self.customer_number.setText(str(self.customer.get('customer_number', '')))
            self.first_name.setText(self.customer.get('first_name', ''))
            self.last_name.setText(self.customer.get('last_name', ''))
            self.phone.setText(self.customer.get('phone', ''))
            self.email.setText(self.customer.get('email', ''))
            category = self.customer.get('category', 'Bireysel')
            index = self.category.findText(category)
            if index >= 0:
                self.category.setCurrentIndex(index)
    
    def getData(self):
        return {
            'customer_number': self.customer_number.text(),
            'first_name': self.first_name.text(),
            'last_name': self.last_name.text(),
            'phone': self.phone.text(),
            'email': self.email.text(),
            'category': self.category.currentText()
        }

class MainWindow(QMainWindow):
    """Ana pencere - Tasarım Standartlarına Göre"""
    def __init__(self):
        super().__init__()
        self.db_config = {
            'host': 'localhost',
            'database': 'haliyikama',
            'user': 'root',
            'password': '',
            'port': 3306
        }
        self.api_url = "http://localhost:5000/api"
        self.customers = []
        self.orders = []
        self.setupUI()
        self.setupSync()
        self.loadCustomers()
        self.loadOrders()
    
    def setupUI(self):
        self.setWindowTitle("CarpetOS - Halı Yıkama Otomasyonu")
        self.setGeometry(100, 100, 1400, 900)
        
        # Menu Strip (24px yükseklik)
        menubar = self.menuBar()
        file_menu = menubar.addMenu('Dosya')
        customer_menu = menubar.addMenu('Müşteri İşlemler')
        report_menu = menubar.addMenu('Raporlar')
        settings_menu = menubar.addMenu('Ayarlar')
        help_menu = menubar.addMenu('Yardım')
        
        # Header Panel (80px yükseklik)
        header_widget = QWidget()
        header_layout = QVBoxLayout()
        header_widget.setLayout(header_layout)
        header_widget.setFixedHeight(80)
        header_widget.setStyleSheet(f"background-color: {COLORS['panel_bg']}; border-bottom: 1px solid {COLORS['border']};")
        
        header_top = QHBoxLayout()
        title_label = QLabel("CARPETOS HALI YIKAMA OTOMASYONU")
        title_label.setFont(QFont("Arial", 16, QFont.Bold))
        title_label.setStyleSheet(f"color: {COLORS['header_blue']};")
        
        license_label = QLabel("LİSANS SAHİBİ: ADA HALI YIKAMA")
        license_label.setFont(QFont("Arial", 9))
        license_label.setStyleSheet(f"color: {COLORS['text_light']};")
        
        header_top.addWidget(title_label)
        header_top.addStretch()
        header_top.addWidget(license_label)
        
        banner_label = QLabel("Okunmamış Online Sipariş Yok")
        banner_label.setStyleSheet(f"background-color: {COLORS['banner_red']}; color: white; padding: 5px; font-weight: bold;")
        banner_label.setFixedHeight(30)
        
        header_layout.addLayout(header_top)
        header_layout.addWidget(banner_label)
        
        # Central widget
        central_widget = QWidget()
        self.setCentralWidget(central_widget)
        
        # Main layout - 3 sütunlu
        main_layout = QHBoxLayout()
        central_widget.setLayout(main_layout)
        
        # Sol Panel (%25)
        left_panel = self.createLeftPanel()
        main_layout.addWidget(left_panel, 1)  # 25%
        
        # Orta Panel (%50)
        center_panel = self.createCenterPanel()
        main_layout.addWidget(center_panel, 2)  # 50%
        
        # Sağ Panel (%25)
        right_panel = self.createRightPanel()
        main_layout.addWidget(right_panel, 1)  # 25%
        
        # Footer Panel (60px yükseklik)
        footer_widget = self.createFooter()
        
        # Ana layout
        main_vbox = QVBoxLayout()
        main_vbox.setContentsMargins(0, 0, 0, 0)
        main_vbox.setSpacing(0)
        main_vbox.addWidget(header_widget)
        main_vbox.addWidget(central_widget, 1)
        main_vbox.addWidget(footer_widget)
        
        container = QWidget()
        container.setLayout(main_vbox)
        self.setCentralWidget(container)
        
        # Status bar
        self.statusBar().showMessage("Hazır")
    
    def createLeftPanel(self):
        """Sol Panel - Müşteri Listesi"""
        panel = QWidget()
        panel.setStyleSheet(f"background-color: {COLORS['panel_bg']}; border-right: 1px solid {COLORS['border']};")
        layout = QVBoxLayout()
        panel.setLayout(layout)
        
        # Başlık
        title = QLabel("TOPLAM: 125 KAYIT")
        title.setFont(QFont("Arial", 12, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['header_blue']}; padding: 8px;")
        layout.addWidget(title)
        
        # Müşteri tablosu
        self.customer_table = QTableWidget()
        self.customer_table.setColumnCount(2)
        self.customer_table.setHorizontalHeaderLabels(["MŞ. NO", "CARİ ADI"])
        self.customer_table.horizontalHeader().setStyleSheet(f"background-color: {COLORS['header_blue']}; color: white; font-weight: bold;")
        self.customer_table.setSelectionBehavior(QTableWidget.SelectRows)
        self.customer_table.setSelectionMode(QTableWidget.SingleSelection)
        self.customer_table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.customer_table.setFont(QFont("Arial", 9))
        layout.addWidget(self.customer_table, 1)
        
        # Arama
        search_input = QLineEdit()
        search_input.setPlaceholderText("Ara...")
        search_input.setFixedHeight(28)
        layout.addWidget(search_input)
        
        # Son çağrılar
        recent_group = QGroupBox("SON ÇAĞRILAR")
        recent_group.setFont(QFont("Arial", 9, QFont.Bold))
        recent_layout = QVBoxLayout()
        recent_label = QLabel("1773 - Seda\n1756 - Mehmet\n1742 - Ali")
        recent_label.setFont(QFont("Arial", 9))
        recent_layout.addWidget(recent_label)
        recent_group.setLayout(recent_layout)
        layout.addWidget(recent_group)
        
        # Butonlar
        btn_layout = QHBoxLayout()
        refresh_btn = QPushButton("🔄")
        refresh_btn.setFixedSize(35, 35)
        refresh_btn.setStyleSheet(f"background-color: {COLORS['success_green']}; color: white; border-radius: 17px;")
        search_btn = QPushButton("🔍")
        search_btn.setFixedSize(35, 35)
        search_btn.setStyleSheet(f"background-color: {COLORS['info_blue']}; color: white; border-radius: 17px;")
        delete_btn = QPushButton("🗑️")
        delete_btn.setFixedSize(35, 35)
        delete_btn.setStyleSheet(f"background-color: {COLORS['banner_red']}; color: white; border-radius: 17px;")
        
        btn_layout.addWidget(refresh_btn)
        btn_layout.addWidget(search_btn)
        btn_layout.addWidget(delete_btn)
        layout.addLayout(btn_layout)
        
        refresh_btn.clicked.connect(self.loadCustomers)
        
        return panel
    
    def createCenterPanel(self):
        """Orta Panel - Sipariş Grid'i"""
        panel = QWidget()
        panel.setStyleSheet(f"background-color: {COLORS['panel_bg']}; border-right: 1px solid {COLORS['border']};")
        layout = QVBoxLayout()
        panel.setLayout(layout)
        
        # Tab Control
        self.order_tabs = QTabWidget()
        self.order_tabs.setFont(QFont("Arial", 9))
        self.order_tabs.addTab(QWidget(), "SİPARİŞLER")
        self.order_tabs.addTab(QWidget(), "YIKAMADA OLANLAR")
        self.order_tabs.addTab(QWidget(), "TESLİM ZAMANI GELENLER")
        self.order_tabs.addTab(QWidget(), "TESLİMAT LİSTESİ")
        self.order_tabs.addTab(QWidget(), "TESLİM EDİLENLER")
        self.order_tabs.addTab(QWidget(), "BEKLEYEN TESLİMAT")
        self.order_tabs.addTab(QWidget(), "İPTAL")
        self.order_tabs.addTab(QWidget(), "AJANDA")
        layout.addWidget(self.order_tabs)
        
        # Tarih navigasyonu
        date_layout = QHBoxLayout()
        prev_btn = QPushButton("◀")
        prev_btn.setFixedSize(30, 28)
        self.date_picker = QDateEdit()
        self.date_picker.setDate(QDate.currentDate())
        self.date_picker.setFixedHeight(28)
        next_btn = QPushButton("▶")
        next_btn.setFixedSize(30, 28)
        vehicle_combo = QComboBox()
        vehicle_combo.addItems(["Tüm Araçlar", "Araç 1", "Araç 2"])
        vehicle_combo.setFixedHeight(28)
        fetch_btn = QPushButton("Getir")
        fetch_btn.setFixedHeight(28)
        fetch_btn.setStyleSheet(f"background-color: {COLORS['info_blue']}; color: white;")
        
        date_layout.addWidget(prev_btn)
        date_layout.addWidget(self.date_picker)
        date_layout.addWidget(next_btn)
        date_layout.addWidget(vehicle_combo)
        date_layout.addWidget(fetch_btn)
        layout.addLayout(date_layout)
        
        # Sipariş tablosu
        self.order_table = QTableWidget()
        self.order_table.setColumnCount(12)
        self.order_table.setHorizontalHeaderLabels([
            "☑", "MU.NO", "FİŞ", "CARİ ADI", "BÖLGE", "AÇIKLAMA",
            "ARAÇ", "SAAT", "ALIŞ SAAT", "ADET", "M²", "TUTAR"
        ])
        self.order_table.horizontalHeader().setStyleSheet(f"background-color: {COLORS['header_blue']}; color: white; font-weight: bold;")
        self.order_table.setSelectionBehavior(QTableWidget.SelectRows)
        self.order_table.setSelectionMode(QTableWidget.ExtendedSelection)
        self.order_table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.order_table.setFont(QFont("Arial", 9))
        self.order_table.setAlternatingRowColors(True)
        layout.addWidget(self.order_table, 1)
        
        # Özet panel
        summary_layout = QHBoxLayout()
        summary_layout.addWidget(QLabel("TOPLAM TESLİM ALINACAK: 6 ADET"))
        summary_layout.addWidget(QLabel("TESLİM ALINAN HALI ADEDİ: 4 ADET"))
        summary_layout.addWidget(QLabel("TESLİM ALINAN TOPLAM M²: 64.0 M²"))
        summary_layout.addWidget(QLabel("TESLİM ALINANLAR TUTARI: 1.870.00 ₺"))
        summary_group = QGroupBox()
        summary_group.setLayout(summary_layout)
        summary_group.setStyleSheet(f"background-color: {COLORS['bg']}; padding: 10px;")
        layout.addWidget(summary_group)
        
        fetch_btn.clicked.connect(self.loadOrders)
        
        return panel
    
    def createRightPanel(self):
        """Sağ Panel - Kısayollar"""
        panel = QWidget()
        panel.setStyleSheet(f"background-color: {COLORS['bg']};")
        layout = QVBoxLayout()
        panel.setLayout(layout)
        
        shortcuts = ["🖥️", "📱", "📞", "👥", "➕", "📊"]
        for icon in shortcuts:
            btn = QPushButton(icon)
            btn.setFixedHeight(60)
            btn.setFont(QFont("Arial", 24))
            btn.setStyleSheet(f"background-color: {COLORS['panel_bg']}; border: 1px solid {COLORS['border']}; border-radius: 5px;")
            layout.addWidget(btn)
        
        layout.addStretch()
        return panel
    
    def createFooter(self):
        """Footer Panel (60px yükseklik)"""
        footer = QWidget()
        footer.setFixedHeight(60)
        footer.setStyleSheet(f"background-color: {COLORS['bg']}; border-top: 1px solid {COLORS['border']};")
        layout = QHBoxLayout()
        footer.setLayout(layout)
        
        icons = ["🔄", "🔍", "🗑️", "ℹ️", "❓", "⚙️", "💬", "🔢", "👤", "🎨", "📡", "💰"]
        for icon in icons:
            btn = QPushButton(icon)
            btn.setFixedSize(45, 45)
            btn.setStyleSheet(f"background-color: {COLORS['panel_bg']}; border: 1px solid {COLORS['border']}; border-radius: 22px;")
            layout.addWidget(btn)
        
        status_label = QLabel("API Bağlı")
        status_label.setStyleSheet(f"color: {COLORS['success_green']};")
        layout.addWidget(status_label)
        
        return footer
    
    def setupSync(self):
        """Senkronizasyon thread'ini başlat"""
        self.sync_thread = DatabaseSyncThread(self.db_config)
        self.sync_thread.sync_completed.connect(self.updateSyncStatus)
        self.sync_thread.start()
        
        self.refresh_timer = QTimer()
        self.refresh_timer.timeout.connect(self.loadCustomers)
        self.refresh_timer.start(10000)
    
    def updateSyncStatus(self, message):
        """Senkronizasyon durumunu güncelle"""
        self.statusBar().showMessage(message)
    
    def loadCustomers(self):
        """Müşterileri yükle"""
        try:
            try:
                response = requests.get(f"{self.api_url}/customers", timeout=2)
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success'):
                        self.customers = data.get('data', [])
                        self.refreshCustomerTable()
                        return
            except:
                pass
            
            connection = mysql.connector.connect(**self.db_config)
            cursor = connection.cursor(dictionary=True)
            cursor.execute("SELECT * FROM customers ORDER BY customer_number")
            self.customers = cursor.fetchall()
            cursor.close()
            connection.close()
            
            self.refreshCustomerTable()
            
        except Error as e:
            QMessageBox.critical(self, "Hata", f"Veritabanı hatası: {str(e)}")
    
    def refreshCustomerTable(self):
        """Müşteri tablosunu yenile"""
        self.customer_table.setRowCount(0)
        for customer in self.customers:
            row = self.customer_table.rowCount()
            self.customer_table.insertRow(row)
            self.customer_table.setItem(row, 0, QTableWidgetItem(str(customer.get('customer_number', ''))))
            self.customer_table.setItem(row, 1, QTableWidgetItem(
                f"{customer.get('first_name', '')} {customer.get('last_name', '')}"
            ))
    
    def loadOrders(self):
        """Siparişleri yükle"""
        try:
            try:
                response = requests.get(f"{self.api_url}/orders", timeout=2)
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success'):
                        self.orders = data.get('data', [])
                        self.refreshOrderTable()
                        return
            except:
                pass
            
            connection = mysql.connector.connect(**self.db_config)
            cursor = connection.cursor(dictionary=True)
            cursor.execute("SELECT * FROM orders ORDER BY created_at DESC")
            self.orders = cursor.fetchall()
            cursor.close()
            connection.close()
            
            self.refreshOrderTable()
            
        except Error as e:
            QMessageBox.critical(self, "Hata", f"Veritabanı hatası: {str(e)}")
    
    def refreshOrderTable(self):
        """Sipariş tablosunu yenile"""
        self.order_table.setRowCount(0)
        for order in self.orders:
            row = self.order_table.rowCount()
            self.order_table.insertRow(row)
            
            checkbox = QTableWidgetItem()
            checkbox.setCheckState(Qt.Unchecked)
            self.order_table.setItem(row, 0, checkbox)
            self.order_table.setItem(row, 1, QTableWidgetItem(str(order.get('customer_id', ''))))
            self.order_table.setItem(row, 2, QTableWidgetItem(str(order.get('order_number', ''))))
            self.order_table.setItem(row, 3, QTableWidgetItem(str(order.get('customer_name', ''))))
            self.order_table.setItem(row, 4, QTableWidgetItem(str(order.get('region', ''))))
            self.order_table.setItem(row, 5, QTableWidgetItem(str(order.get('description', ''))))
            self.order_table.setItem(row, 6, QTableWidgetItem(str(order.get('vehicle', ''))))
            self.order_table.setItem(row, 7, QTableWidgetItem(str(order.get('time', ''))))
            self.order_table.setItem(row, 8, QTableWidgetItem(str(order.get('pickup_time', ''))))
            self.order_table.setItem(row, 9, QTableWidgetItem(str(order.get('quantity', 0))))
            self.order_table.setItem(row, 10, QTableWidgetItem(str(order.get('square_meters', 0))))
            self.order_table.setItem(row, 11, QTableWidgetItem(f"{order.get('amount', 0):.2f} ₺"))
    
    def closeEvent(self, event):
        """Pencere kapanırken thread'i durdur"""
        if hasattr(self, 'sync_thread'):
            self.sync_thread.stop()
            self.sync_thread.wait()
        event.accept()

def main():
    app = QApplication(sys.argv)
    app.setStyle('Fusion')
    
    window = MainWindow()
    window.show()
    
    sys.exit(app.exec_())

if __name__ == '__main__':
    main()

