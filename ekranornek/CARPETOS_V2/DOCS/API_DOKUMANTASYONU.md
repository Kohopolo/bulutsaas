# CarpetOS V2 - API Dokümantasyonu

## Base URL
```
http://localhost:5000/api
```

## Authentication
Çoğu endpoint için authentication gerekli. Desktop uygulaması için bazı endpoint'ler authentication olmadan çalışır.

## Endpoints

### Müşteriler

#### GET /api/customers
Müşteri listesini getirir.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer_number": "001",
      "first_name": "Ahmet",
      "last_name": "Yılmaz",
      "phone": "05551234567",
      "email": "ahmet@example.com",
      "category": "Bireysel"
    }
  ]
}
```

#### POST /api/customers
Yeni müşteri oluşturur.

**Request:**
```json
{
  "customer_number": "001",
  "first_name": "Ahmet",
  "last_name": "Yılmaz",
  "phone": "05551234567",
  "email": "ahmet@example.com",
  "category": "Bireysel"
}
```

#### PUT /api/customers/{id}
Müşteri günceller.

#### DELETE /api/customers/{id}
Müşteri siler.

### Siparişler

#### GET /api/orders
Sipariş listesini getirir.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_number": "ORD-001",
      "customer_id": 1,
      "customer_name": "Ahmet Yılmaz",
      "amount": 450.00,
      "status": "pending"
    }
  ]
}
```

## WebSocket Events

### customer_created
Yeni müşteri eklendiğinde tetiklenir.

### customer_updated
Müşteri güncellendiğinde tetiklenir.

### customer_deleted
Müşteri silindiğinde tetiklenir.

