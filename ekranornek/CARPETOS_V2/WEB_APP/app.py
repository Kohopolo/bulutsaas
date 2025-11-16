"""
CarpetOS V2 - Halı Yıkama İşletme Yönetim Sistemi
Flask Web Application - Tasarım Standartlarına Göre
"""

from flask import Flask, render_template, request, jsonify, session, redirect, url_for, send_from_directory
from flask_socketio import SocketIO, emit
from flask_cors import CORS
import mysql.connector
from mysql.connector import Error
from datetime import datetime
import json
import os
from functools import wraps

app = Flask(__name__)
app.config['SECRET_KEY'] = 'carpetos-v2-secret-key-change-in-production'
socketio = SocketIO(app, cors_allowed_origins="*")
CORS(app)

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'database': 'haliyikama',
    'user': 'root',
    'password': '',
    'port': 3306
}

def get_db_connection():
    """MySQL veritabanı bağlantısı"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        return connection
    except Error as e:
        print(f"Database connection error: {e}")
        return None

def login_required(f):
    """Login kontrolü decorator"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'user_id' not in session:
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated_function

# Routes
@app.route('/')
def index():
    """Ana sayfa"""
    if 'user_id' in session:
        return redirect(url_for('dashboard'))
    return redirect(url_for('login'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    """Kullanıcı girişi"""
    if request.method == 'POST':
        email = request.form.get('email')
        password = request.form.get('password')
        
        connection = get_db_connection()
        if connection:
            try:
                cursor = connection.cursor(dictionary=True)
                cursor.execute(
                    "SELECT id, name, email, role_id FROM users WHERE email = %s AND password = %s",
                    (email, password)  # Production'da password hash kullanılmalı
                )
                user = cursor.fetchone()
                
                if user:
                    session['user_id'] = user['id']
                    session['user_name'] = user['name']
                    session['user_email'] = user['email']
                    session['role_id'] = user['role_id']
                    return jsonify({'success': True, 'redirect': '/dashboard'})
                else:
                    return jsonify({'success': False, 'message': 'Kullanıcı adı veya şifre hatalı'})
            except Error as e:
                return jsonify({'success': False, 'message': f'Veritabanı hatası: {str(e)}'})
            finally:
                cursor.close()
                connection.close()
        
        return jsonify({'success': False, 'message': 'Bağlantı hatası'})
    
    return render_template('login.html')

@app.route('/logout')
def logout():
    """Çıkış"""
    session.clear()
    return redirect(url_for('login'))

@app.route('/dashboard')
@login_required
def dashboard():
    """Dashboard - Tasarım Standartlarına Göre"""
    return render_template('dashboard.html', session=session)

@app.route('/customers')
@login_required
def customers():
    """Müşteriler sayfası"""
    return render_template('customers.html')

@app.route('/orders')
@login_required
def orders():
    """Siparişler sayfası"""
    return render_template('orders.html')

@app.route('/payments')
@login_required
def payments():
    """Ödemeler sayfası"""
    return render_template('payments.html')

@app.route('/invoices')
@login_required
def invoices():
    """Faturalar sayfası"""
    return render_template('invoices.html')

@app.route('/reports')
@login_required
def reports():
    """Raporlar sayfası"""
    return render_template('reports.html')

@app.route('/settings')
@login_required
def settings():
    """Ayarlar sayfası"""
    return render_template('settings.html')

# API Routes
@app.route('/api/customers', methods=['GET'])
def get_customers():
    """Müşteri listesi"""
    connection = get_db_connection()
    if not connection:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT * FROM customers ORDER BY customer_number")
        customers = cursor.fetchall()
        return jsonify({'success': True, 'data': customers})
    except Error as e:
        return jsonify({'error': str(e)}), 500
    finally:
        cursor.close()
        connection.close()

@app.route('/api/customers', methods=['POST'])
def create_customer():
    """Yeni müşteri oluştur"""
    data = request.json
    connection = get_db_connection()
    if not connection:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = connection.cursor()
        cursor.execute("""
            INSERT INTO customers (customer_number, first_name, last_name, phone, email, category, created_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (
            data.get('customer_number'),
            data.get('first_name'),
            data.get('last_name'),
            data.get('phone'),
            data.get('email'),
            data.get('category'),
            datetime.now()
        ))
        connection.commit()
        customer_id = cursor.lastrowid
        
        socketio.emit('customer_created', {'message': 'Yeni müşteri eklendi', 'customer_id': customer_id})
        
        return jsonify({'success': True, 'message': 'Müşteri başarıyla oluşturuldu', 'id': customer_id})
    except Error as e:
        return jsonify({'error': str(e)}), 500
    finally:
        cursor.close()
        connection.close()

@app.route('/api/orders', methods=['GET'])
def get_orders():
    """Sipariş listesi"""
    connection = get_db_connection()
    if not connection:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute("""
            SELECT o.*, c.first_name, c.last_name, c.phone as customer_phone
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            ORDER BY o.created_at DESC
        """)
        orders = cursor.fetchall()
        return jsonify({'success': True, 'data': orders})
    except Error as e:
        return jsonify({'error': str(e)}), 500
    finally:
        cursor.close()
        connection.close()

# Static files
@app.route('/static/<path:filename>')
def static_files(filename):
    """Static dosyalar"""
    return send_from_directory('static', filename)

# WebSocket Events
@socketio.on('connect')
def handle_connect():
    """WebSocket bağlantısı"""
    print('Client connected')
    emit('connected', {'message': 'Bağlandı'})

@socketio.on('disconnect')
def handle_disconnect():
    """WebSocket bağlantı kesildi"""
    print('Client disconnected')

if __name__ == '__main__':
    socketio.run(app, host='0.0.0.0', port=5000, debug=True)

