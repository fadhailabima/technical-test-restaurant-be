# Restaurant Backend API

A production-ready RESTful API for restaurant management system built with Laravel 11 and PostgreSQL. This system implements comprehensive order management workflow, multi-role authentication, payment processing with multiple methods, and advanced reporting capabilities.

## Table of Contents

- [Overview](#overview)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Architecture](#database-architecture)
- [API Documentation](#api-documentation)
- [Authentication & Authorization](#authentication--authorization)
- [API Endpoints](#api-endpoints)
- [Business Logic](#business-logic)
- [Testing](#testing)
- [Deployment Considerations](#deployment-considerations)

## Overview

This API provides a complete backend solution for restaurant operations management including:

- **Authentication System**: Token-based authentication using Laravel Sanctum with multi-role support (Waiter/Cashier)
- **Menu Management**: Full CRUD operations with image upload, categorization, and availability tracking
- **Table Management**: Table status tracking with automatic updates based on order lifecycle
- **Order Management**: Complete order workflow from opening to closure with status tracking and item management
- **Payment Processing**: Support for 6 payment methods with split payment capabilities and automatic payment status calculation
- **Reporting System**: Comprehensive analytics including sales reports, best sellers, revenue analysis, and staff performance metrics

### Key Technical Features

- RESTful API design following Laravel best practices
- Role-based access control (RBAC) with middleware implementation
- Eager loading optimization for N+1 query prevention
- Database indexing for performance optimization
- PostgreSQL compatibility with application-level enum validation
- OpenAPI 3.0 documentation with interactive Swagger UI
- PDF receipt generation using DomPDF
- Comprehensive input validation and error handling

## System Requirements

### Server Requirements

- **PHP**: >= 8.2 with required extensions:
    - OpenSSL
    - PDO (with pgsql driver)
    - Mbstring
    - Tokenizer
    - XML
    - Ctype
    - JSON
    - BCMath
    - GD (for image processing)
- **Composer**: >= 2.0
- **PostgreSQL**: >= 14 (recommended: 16)
- **Web Server**: Nginx or Apache with mod_rewrite

### Development Tools (Optional)

- Git
- Postman or similar API testing tool
- PostgreSQL GUI client (pgAdmin, DBeaver)

## Installation

### 1. Clone Repository and Install Dependencies

```bash
git clone <repository-url>
cd restaurant-backend
composer install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Configuration

Edit `.env` file with your PostgreSQL credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=restaurant_db
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password
```

Create the database:

```sql
-- Connect to PostgreSQL
psql -U postgres

-- Create database
CREATE DATABASE restaurant_db;

-- Exit
\q
```

### 4. Database Migration and Seeding

```bash
# Run all migrations
php artisan migrate

# Or run with fresh database and sample data
php artisan migrate:fresh --seed
```

The seeder will create:

- 4 test users (2 Waiters, 2 Cashiers)
- 10 tables (T1-T10 with varying capacities)
- 15 menu items across different categories

### 5. Storage Configuration

```bash
# Create symbolic link for file uploads
php artisan storage:link
```

### 6. API Documentation Generation

```bash
# Generate Swagger documentation
php artisan l5-swagger:generate
```

### 7. Start Development Server

```bash
php artisan serve
# Server will run at http://localhost:8000
```

### Key Configuration Options

Edit `.env` file for additional configuration:

```env
# Application
APP_NAME="Restaurant Backend API"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=restaurant_db
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

# Laravel Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
SESSION_DRIVER=database

# File Storage
FILESYSTEM_DISK=public

# PDF Configuration (Optional)
PDF_PAPER_SIZE=a4
PDF_ORIENTATION=portrait
```

### Default Test Accounts

| Role    | Email                   | Password | Permissions                                      |
| ------- | ----------------------- | -------- | ------------------------------------------------ |
| Pelayan | pelayan1@restaurant.com | password | Open orders, add/remove items, update status     |
| Pelayan | pelayan2@restaurant.com | password | Open orders, add/remove items, update status     |
| Kasir   | kasir1@restaurant.com   | password | Close orders, process payments, generate reports |
| Kasir   | kasir2@restaurant.com   | password | Close orders, process payments, generate reports |

## Database Architecture

### Entity Relationship Overview

```
users (Pelayan, Kasir)
  ↓ (waiter_id, cashier_id)
orders ← order_items → menus
  ↓         ↓
tables    payments
```

### Core Tables

#### users

Primary table for authentication and authorization.

| Column     | Type         | Description                     |
| ---------- | ------------ | ------------------------------- |
| id         | bigint       | Primary key                     |
| name       | varchar(255) | User full name                  |
| email      | varchar(255) | Unique email address            |
| password   | varchar(255) | Hashed password (bcrypt)        |
| role       | varchar(50)  | User role: 'Pelayan' or 'Kasir' |
| timestamps | timestamp    | created_at, updated_at          |

**Indexes**: `email` (unique), `role`

#### tables

Restaurant table management with status tracking.

| Column       | Type        | Description                         |
| ------------ | ----------- | ----------------------------------- |
| id           | bigint      | Primary key                         |
| table_number | varchar(10) | Unique table identifier             |
| capacity     | integer     | Maximum seating capacity            |
| status       | varchar(50) | 'available', 'occupied', 'reserved' |
| timestamps   | timestamp   | created_at, updated_at              |

**Indexes**: `table_number` (unique), `status`

#### menus

Menu items with categorization and pricing.

| Column       | Type          | Description                              |
| ------------ | ------------- | ---------------------------------------- |
| id           | bigint        | Primary key                              |
| name         | varchar(255)  | Menu item name                           |
| description  | text          | Detailed description                     |
| price        | decimal(10,2) | Item price                               |
| category     | varchar(50)   | 'makanan', 'minuman', 'snack', 'dessert' |
| image        | varchar(255)  | Image file path                          |
| is_available | boolean       | Availability status                      |
| timestamps   | timestamp     | created_at, updated_at                   |

**Indexes**: `category`, `is_available`

#### orders

Core order management with lifecycle tracking.

| Column         | Type          | Description                                                   |
| -------------- | ------------- | ------------------------------------------------------------- |
| id             | bigint        | Primary key                                                   |
| order_number   | varchar(50)   | Unique order identifier                                       |
| table_id       | bigint        | Foreign key to tables                                         |
| waiter_id      | bigint        | Foreign key to users (Pelayan)                                |
| cashier_id     | bigint        | Foreign key to users (Kasir), nullable                        |
| status         | varchar(50)   | 'open', 'preparing', 'ready', 'served', 'closed', 'cancelled' |
| payment_status | varchar(50)   | 'unpaid', 'partial', 'paid'                                   |
| subtotal       | decimal(10,2) | Sum of order items                                            |
| tax            | decimal(10,2) | Tax amount (10% of subtotal)                                  |
| total          | decimal(10,2) | Final total (subtotal + tax)                                  |
| opened_at      | timestamp     | Order opening time                                            |
| closed_at      | timestamp     | Order closing time, nullable                                  |
| timestamps     | timestamp     | created_at, updated_at                                        |

**Indexes**: `order_number` (unique), `table_id`, `waiter_id`, `cashier_id`, `status`, `payment_status`

**Relationships**:

- belongs to `tables` (table_id)
- belongs to `users` as waiter (waiter_id)
- belongs to `users` as cashier (cashier_id)
- has many `order_items`
- has many `payments`

#### order_items

Individual items within an order.

| Column     | Type          | Description                |
| ---------- | ------------- | -------------------------- |
| id         | bigint        | Primary key                |
| order_id   | bigint        | Foreign key to orders      |
| menu_id    | bigint        | Foreign key to menus       |
| quantity   | integer       | Item quantity              |
| price      | decimal(10,2) | Price at order time        |
| subtotal   | decimal(10,2) | quantity × price           |
| notes      | text          | Special requests, nullable |
| timestamps | timestamp     | created_at, updated_at     |

**Indexes**: `order_id`, `menu_id`

**Relationships**:

- belongs to `orders`
- belongs to `menus`

#### payments

Payment records with multiple method support.

| Column           | Type          | Description                                    |
| ---------------- | ------------- | ---------------------------------------------- |
| id               | bigint        | Primary key                                    |
| order_id         | bigint        | Foreign key to orders                          |
| payment_method   | varchar(50)   | 'cash', 'card', 'qris', 'gopay', 'ovo', 'dana' |
| amount           | decimal(10,2) | Payment amount                                 |
| status           | varchar(50)   | 'pending', 'completed', 'failed', 'refunded'   |
| reference_number | varchar(50)   | Transaction reference, nullable                |
| notes            | text          | Additional notes, nullable                     |
| paid_at          | timestamp     | Payment completion time, nullable              |
| timestamps       | timestamp     | created_at, updated_at                         |

**Indexes**: `order_id`, `payment_method`, `status`

**Relationships**:

- belongs to `orders`

### Database Constraints

- Foreign key constraints with `ON DELETE CASCADE` for order_items
- Foreign key constraints with `ON DELETE SET NULL` for optional relationships
- Application-level validation for enum values (PostgreSQL compatibility)
- Unique constraints on business identifiers (order_number, table_number, email)

## API Documentation

### Interactive Documentation

Swagger UI provides comprehensive API documentation at:

```
http://localhost:8000/api/documentation
```

Features:

- Complete endpoint specifications
- Request/response schemas
- Authentication flow documentation
- Interactive API testing
- Example values for all parameters

### Regenerating Documentation

After modifying controller annotations:

```bash
php artisan l5-swagger:generate
```

Documentation is generated from OpenAPI annotations in controllers.

## Authentication & Authorization

### Authentication Flow

1. **Login Request**

    ```http
    POST /api/login
    Content-Type: application/json

    {
      "email": "pelayan1@restaurant.com",
      "password": "password"
    }
    ```

2. **Login Response**

    ```json
    {
        "success": true,
        "data": {
            "user": {
                "id": 1,
                "name": "Pelayan 1",
                "email": "pelayan1@restaurant.com",
                "role": "Pelayan"
            },
            "token": "1|abc123xyz..."
        }
    }
    ```

3. **Authenticated Requests**
    ```http
    GET /api/orders
    Authorization: Bearer 1|abc123xyz...
    ```

### Authorization Rules

#### Role: Pelayan (Waiter)

- Open new orders
- Add items to orders (status: open or preparing)
- Remove items from orders (status: open or preparing)
- Update order status (open → preparing → ready → served)

#### Role: Kasir (Cashier)

- Close orders
- Process payments (all methods)
- Issue refunds
- Generate receipts
- Access all reports

#### Both Roles

- View orders and details
- View menus and tables
- Logout
- View own profile

### Middleware Implementation

Role-based access control is implemented using custom middleware:

```php
Route::middleware(['auth:sanctum', 'role:Pelayan'])->group(function () {
    // Pelayan-only endpoints
});
```

## API Endpoints

### Base URL

```
http://localhost:8000/api
```

### Authentication Endpoints (3)

#### POST /login

Authenticate user and obtain access token.

**Request Body:**

```json
{
    "email": "string (required)",
    "password": "string (required)"
}
```

**Response:** `200 OK`

```json
{
  "success": true,
  "data": {
    "user": {...},
    "token": "string"
  }
}
```

#### POST /logout

Revoke current access token.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`

#### GET /me

Get authenticated user details.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`

### Menu Endpoints (5)

#### GET /menus

List all menu items with optional filtering.

**Query Parameters:**

- `category`: Filter by category (makanan|minuman|snack|dessert)
- `is_available`: Filter by availability (boolean)
- `search`: Search in menu name

**Response:** `200 OK`

#### POST /menus

Create new menu item.

**Content-Type:** `multipart/form-data`

**Request Body:**

- `name`: string (required)
- `description`: text (optional)
- `price`: decimal (required)
- `category`: enum (required)
- `image`: file (optional, max 2MB)
- `is_available`: boolean (default: true)

**Response:** `201 Created`

#### GET /menus/{id}

Get menu item details.

**Response:** `200 OK`

#### PUT /menus/{id}

Update menu item.

**Response:** `200 OK`

#### DELETE /menus/{id}

Delete menu item.

**Response:** `200 OK`

### Table Endpoints (5)

#### GET /tables

List all tables with optional status filtering.

**Query Parameters:**

- `status`: Filter by status (available|occupied|reserved)

**Response:** `200 OK`

#### POST /tables

Create new table.

**Request Body:**

```json
{
    "table_number": "string (required, unique)",
    "capacity": "integer (required, 1-50)"
}
```

**Response:** `201 Created`

#### GET /tables/{id}

Get table details with current order information.

**Response:** `200 OK`

#### PUT /tables/{id}

Update table information.

**Response:** `200 OK`

#### DELETE /tables/{id}

Delete table (only if status is available).

**Response:** `200 OK`

### Order Endpoints (8)

#### GET /orders

List orders with filtering options.

**Query Parameters:**

- `status`: Filter by order status
- `payment_status`: Filter by payment status
- `from_date`: Start date filter
- `to_date`: End date filter

**Response:** `200 OK`

#### POST /orders/open

Open new order for a table.

**Role:** Pelayan only

**Request Body:**

```json
{
    "table_id": "integer (required)"
}
```

**Business Rules:**

- Table must exist and be available
- Table cannot have existing open order

**Response:** `201 Created`

#### GET /orders/{id}

Get order details with all related data.

**Response:** `200 OK`

#### POST /orders/{id}/items

Add item to order.

**Role:** Pelayan only

**Request Body:**

```json
{
    "menu_id": "integer (required)",
    "quantity": "integer (required, min: 1)",
    "notes": "string (optional, max: 500)"
}
```

**Business Rules:**

- Order status must be 'open' or 'preparing'
- Menu item must be available
- Duplicate items will have quantities combined

**Response:** `201 Created`

#### DELETE /orders/{id}/items/{itemId}

Remove item from order.

**Role:** Pelayan only

**Business Rules:**

- Order status must be 'open' or 'preparing'
- Item must belong to the order

**Response:** `200 OK`

#### PATCH /orders/{id}/status

Update order status.

**Role:** Pelayan only

**Request Body:**

```json
{
    "status": "enum (required: open|preparing|ready|served|closed|cancelled)"
}
```

**Business Rules:**

- Closed orders cannot be updated (except to cancelled)

**Response:** `200 OK`

#### POST /orders/{id}/close

Close order and prepare for payment.

**Role:** Kasir only

**Business Rules:**

- Order status must be 'open'
- Order must have at least one item
- Table status automatically set to available

**Response:** `200 OK`

#### GET /orders/{id}/receipt

Generate and download PDF receipt.

**Role:** Kasir only

**Business Rules:**

- Order must be closed

**Response:** `200 OK` (PDF file download)

### Payment Endpoints (3)

#### POST /orders/{id}/payments

Process payment for order.

**Role:** Kasir only

**Request Body:**

```json
{
    "payment_method": "enum (required: cash|card|qris|gopay|ovo|dana)",
    "amount": "decimal (required)",
    "reference_number": "string (optional)",
    "notes": "string (optional)"
}
```

**Business Rules:**

- Order must be closed
- Payment amount cannot exceed remaining balance
- Supports split payments (multiple partial payments)
- Automatic payment_status calculation:
    - `paid`: total payments >= order total
    - `partial`: total payments < order total
    - `unpaid`: no completed payments

**Response:** `200 OK`

```json
{
  "success": true,
  "data": {
    "payment": {...},
    "remaining": "decimal",
    "change": "decimal (for cash only)"
  }
}
```

#### GET /orders/{id}/payments

Get payment history for order.

**Role:** Kasir only

**Response:** `200 OK`

#### POST /orders/{id}/payments/{paymentId}/refund

Refund a payment.

**Role:** Kasir only

**Request Body:**

```json
{
    "reason": "string (required)"
}
```

**Business Rules:**

- Payment status must be 'completed'
- Payment must belong to the order

**Response:** `200 OK`

### Report Endpoints (6)

All report endpoints require Kasir role.

#### GET /reports/daily-sales

Get sales report for specific date.

**Query Parameters:**

- `date`: Date filter (format: Y-m-d, default: today)

**Response:** `200 OK`

```json
{
  "success": true,
  "data": {
    "date": "2026-02-06",
    "total_orders": 15,
    "total_revenue": 1250000,
    "total_tax": 125000,
    "payment_methods": [...],
    "orders": [...]
  }
}
```

#### GET /reports/best-sellers

Get best-selling menu items.

**Query Parameters:**

- `start_date`: Start date (default: 30 days ago)
- `end_date`: End date (default: today)
- `limit`: Number of items (default: 10)

**Response:** `200 OK`

#### GET /reports/revenue

Get revenue analysis with grouping.

**Query Parameters:**

- `start_date`: Start date (default: start of month)
- `end_date`: End date (default: today)
- `group_by`: Grouping interval (hour|day|week|month, default: day)

**Response:** `200 OK`

#### GET /reports/staff-performance

Get waiter and cashier performance metrics.

**Query Parameters:**

- `start_date`: Start date (default: start of month)
- `end_date`: End date (default: today)

**Response:** `200 OK`

#### GET /reports/category-analysis

Get sales breakdown by menu category.

**Query Parameters:**

- `start_date`: Start date (default: 30 days ago)
- `end_date`: End date (default: today)

**Response:** `200 OK`

#### GET /reports/summary

Get dashboard summary (today and this month).

**Response:** `200 OK`

## Business Logic

### Order Lifecycle

```
1. Open Order (Pelayan)
   - Select available table
   - System generates order_number
   - Table status → occupied
   - Order status → open
   - Payment status → unpaid

2. Add Items (Pelayan)
   - Select menu items
   - Specify quantity and notes
   - System calculates subtotals
   - Order can be modified while status is open/preparing

3. Update Status (Pelayan)
   - open → preparing (kitchen starts)
   - preparing → ready (ready to serve)
   - ready → served (delivered to table)

4. Close Order (Kasir)
   - Verify all items delivered
   - System calculates: subtotal, tax (10%), total
   - Order status → closed
   - Table status → available
   - Ready for payment

5. Process Payment (Kasir)
   - Select payment method
   - Enter amount (supports partial)
   - System tracks payment_status
   - Generate receipt when fully paid

6. Order Completion
   - Payment status → paid
   - Receipt generated
   - Order archived for reporting
```

### Payment Calculation Logic

```php
subtotal = sum(order_items.subtotal)
tax = subtotal × 0.10
total = subtotal + tax

// Payment status determination
total_paid = sum(payments where status = 'completed')
remaining = total - total_paid

if (total_paid >= total) {
    payment_status = 'paid'
} elseif (total_paid > 0) {
    payment_status = 'partial'
} else {
    payment_status = 'unpaid'
}

// Cash change calculation
if (payment_method === 'cash' && amount > remaining) {
    change = amount - remaining
}
```

### Table Status Management

Table status is automatically managed based on order lifecycle:

- **available**: No active orders
- **occupied**: Has open order (status != closed)
- **reserved**: Manual reservation (not implemented in current version)

## Testing

### Manual Testing with Swagger UI

1. Start server: `php artisan serve`
2. Open: http://localhost:8000/api/documentation
3. Click "Authorize" button
4. Login via `/api/login` endpoint to obtain token
5. Enter token in format: `Bearer {your_token}`
6. Test endpoints interactively

### Complete Test Scenario

```bash
# 1. Login as Pelayan
POST /api/login
{
  "email": "pelayan1@restaurant.com",
  "password": "password"
}
# Save token as PELAYAN_TOKEN

# 2. Check available tables
GET /api/tables?status=available
Authorization: Bearer {PELAYAN_TOKEN}

# 3. Open order for Table 1
POST /api/orders/open
Authorization: Bearer {PELAYAN_TOKEN}
{
  "table_id": 1
}
# Save order_id

# 4. Add menu items
POST /api/orders/{order_id}/items
Authorization: Bearer {PELAYAN_TOKEN}
{
  "menu_id": 1,
  "quantity": 2,
  "notes": "Tidak pedas"
}

# 5. Update order status
PATCH /api/orders/{order_id}/status
Authorization: Bearer {PELAYAN_TOKEN}
{
  "status": "preparing"
}

# 6. Login as Kasir
POST /api/login
{
  "email": "kasir1@restaurant.com",
  "password": "password"
}
# Save token as KASIR_TOKEN

# 7. Close order
POST /api/orders/{order_id}/close
Authorization: Bearer {KASIR_TOKEN}

# 8. Process payment
POST /api/orders/{order_id}/payments
Authorization: Bearer {KASIR_TOKEN}
{
  "payment_method": "cash",
  "amount": 150000
}

# 9. Generate receipt
GET /api/orders/{order_id}/receipt
Authorization: Bearer {KASIR_TOKEN}

# 10. View reports
GET /api/reports/daily-sales
Authorization: Bearer {KASIR_TOKEN}
```

## Deployment Considerations

### Production Environment Setup

1. **Environment Configuration**

    ```env
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://your-domain.com
    ```

2. **Database Optimization**
    - Enable PostgreSQL query caching
    - Configure connection pooling
    - Regular VACUUM and ANALYZE
    - Monitor slow query log

3. **Caching Strategy**

    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

4. **Queue Configuration** (for background jobs)

    ```env
    QUEUE_CONNECTION=database
    ```

5. **File Storage**
    - Configure S3 or similar for production
    - Set proper file permissions
    - Implement CDN for static assets

### Security Checklist

- [ ] Change default database passwords
- [ ] Configure CORS policies
- [ ] Enable rate limiting
- [ ] Set up SSL/TLS certificates
- [ ] Configure firewall rules
- [ ] Regular security updates
- [ ] Implement backup strategy
- [ ] Configure logging and monitoring
- [ ] Set up error tracking (Sentry, Bugsnag)
- [ ] Enable database encryption at rest

### Performance Optimization

- Database indexing is already implemented
- Eager loading prevents N+1 queries
- Response caching for read-heavy endpoints
- Database connection pooling
- CDN for static assets
- Gzip compression for API responses

### Monitoring and Logging

```env
LOG_CHANNEL=stack
LOG_LEVEL=error

# Recommended production logging
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...
```

Monitor:

- API response times
- Database query performance
- Error rates
- Authentication failures
- Payment transaction success rates

## License

This project is licensed under the MIT License.

## Support

For technical support or questions:

- Review API documentation at `/api/documentation`
- Check database schema and relationships
- Verify authentication token validity
- Ensure role permissions are correctly configured

---

**Version:** 1.0.0  
**Last Updated:** February 2026  
**Laravel Version:** 11.x  
**PHP Version:** 8.2+  
**Database:** PostgreSQL 14+### Payments (3 endpoints)

| Method | Endpoint                                   | Description     | Auth | Role  |
| ------ | ------------------------------------------ | --------------- | ---- | ----- |
| POST   | `/orders/{id}/payments`                    | Add payment     | ✅   | Kasir |
| GET    | `/orders/{id}/payments`                    | Payment history | ✅   | Kasir |
| POST   | `/orders/{id}/payments/{paymentId}/refund` | Refund          | ✅   | Kasir |

**Payment Methods**: `cash`, `card`, `qris`, `gopay`, `ovo`, `dana`

### Reports (6 endpoints)

| Method | Endpoint                     | Description      | Auth | Role  |
| ------ | ---------------------------- | ---------------- | ---- | ----- |
| GET    | `/reports/daily-sales`       | Daily sales      | ✅   | Kasir |
| GET    | `/reports/best-sellers`      | Top items        | ✅   | Kasir |
| GET    | `/reports/revenue`           | Revenue analysis | ✅   | Kasir |
| GET    | `/reports/staff-performance` | Staff metrics    | ✅   | Kasir |
| GET    | `/reports/category-analysis` | Category stats   | ✅   | Kasir |
| GET    | `/reports/summary`           | Dashboard        | ✅   | Kasir |

---

## 💡 Usage Example

### Complete Order Workflow

#### 1️⃣ Login (Pelayan)

```bash
POST /api/login
Content-Type: application/json

{
  "email": "pelayan1@restaurant.com",
  "password": "password"
}

# Response
{
  "success": true,
  "data": {
    "user": {...},
    "token": "1|abc123..."
  }
}
```

#### 2️⃣ Open Order

```bash
POST /api/orders/open
Authorization: Bearer {token}

{
  "table_id": 1
}
```

#### 3️⃣ Add Items

```bash
POST /api/orders/{orderId}/items
Authorization: Bearer {token}

{
  "menu_id": 5,
  "quantity": 2,
  "notes": "Tidak pedas"
}
```

#### 4️⃣ Update Order Status

```bash
PATCH /api/orders/{orderId}/status
Authorization: Bearer {token}

{
  "status": "preparing"
}
```

#### 5️⃣ Close Order (Switch to Kasir)

```bash
POST /api/login
{
  "email": "kasir1@restaurant.com",
  "password": "password"
}

POST /api/orders/{orderId}/close
Authorization: Bearer {kasir_token}
```

#### 6️⃣ Process Payment

```bash
POST /api/orders/{orderId}/payments
Authorization: Bearer {kasir_token}

{
  "payment_method": "cash",
  "amount": 150000
}
```

#### 7️⃣ Generate Receipt

```bash
GET /api/orders/{orderId}/receipt
Authorization: Bearer {kasir_token}

# Downloads PDF receipt
```

---

## 🗄️ Database Schema

### Core Tables

| Table           | Description                                    |
| --------------- | ---------------------------------------------- |
| **users**       | User accounts with role (Pelayan/Kasir)        |
| **tables**      | Restaurant tables with status tracking         |
| **menus**       | Menu items with categories and pricing         |
| **orders**      | Customer orders with status & payment tracking |
| **order_items** | Individual items in each order                 |
| **payments**    | Payment records with multiple methods          |

### Key Relationships

- `orders` belongs to `table`, `waiter (user)`, `cashier (user)`
- `order_items` belongs to `order` and `menu`
- `payments` belongs to `order`

### Enums (Application-level validation)

- **User roles**: `Pelayan`, `Kasir`
- **Table status**: `available`, `occupied`, `reserved`
- **Menu categories**: `makanan`, `minuman`, `snack`, `dessert`
- **Order status**: `open`, `preparing`, `ready`, `served`, `closed`, `cancelled`
- **Payment status**: `unpaid`, `partial`, `paid`
- **Payment methods**: `cash`, `card`, `qris`, `gopay`, `ovo`, `dana`

---

## 🧪 Testing

### Manual Testing with Swagger

1. Start the server: `php artisan serve`
2. Open Swagger UI: http://localhost:8000/api/documentation
3. Click "Authorize" button
4. Login via `/api/login` endpoint
5. Copy the token from response
6. Paste in authorization modal with format: `Bearer {token}`
7. Test all endpoints interactively

### Sample Data

After running seeders, you'll have:

- 4 users (2 Pelayan, 2 Kasir)
- 10 tables (T1-T10)
- 15 menu items (various categories)

---

## 🏗️ Project Structure

```
restaurant-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── MenuController.php
│   │   │   ├── TableController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PaymentController.php
│   │   │   └── ReportController.php
│   │   └── Middleware/
│   │       └── CheckRole.php
│   └── Models/
│       ├── User.php
│       ├── Table.php
│       ├── Menu.php
│       ├── Order.php
│       ├── OrderItem.php
│       └── Payment.php
├── database/
│   ├── migrations/
│   └── seeders/
│       └── DatabaseSeeder.php
├── routes/
│   └── api.php
└── storage/
    └── api-docs/
        └── api-docs.json
```

---

## 🔒 Security Features

- ✅ **Token-based authentication** with Laravel Sanctum
- ✅ **Role-based authorization** with middleware
- ✅ **Input validation** on all endpoints
- ✅ **SQL injection prevention** via Eloquent ORM
- ✅ **XSS protection** with Laravel's built-in security
- ✅ **CSRF protection** for web routes
- ✅ **Password hashing** with bcrypt

---

## 🚦 API Response Format

### Success Response

```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data
    }
}
```

### Error Response

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        // Validation errors (if any)
    }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Restaurant Backend API**

Built with ❤️ using Laravel 11

---

## 📞 Support

For questions or support, please open an issue in the repository.

---

<div align="center">

**Made with [Laravel](https://laravel.com) • [PostgreSQL](https://postgresql.org) • [Swagger](https://swagger.io)**

</div>
