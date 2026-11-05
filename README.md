# JV Shetty Megamart

A full stack PHP e-commerce platform for a campus supermarket, covering the complete retail cycle from product discovery and checkout through to inventory control, delivery fulfilment and sales reporting.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-6%2B-DC382D?logo=redis&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![PHPUnit](https://img.shields.io/badge/PHPUnit-8.5-3C9CD7)

## Overview

JV Shetty Megamart was built to move a campus supermarket online. Access is restricted to verified campus email addresses (`@goa.bits-pilani.ac.in`), ordering is limited to store trading hours (4 PM to 9 PM IST), and a role based permission model ensures that customers, administrators and delivery partners each see only the parts of the system relevant to them.

The application is organised as a layered PHP codebase: page controllers and JSON API endpoints sit on top of a model layer, a set of core services (validation, rate limiting, email, payments, error handling) and a dedicated authentication module built around one time passwords, JSON Web Tokens and role based access control.

## Key Features

### Customer storefront
- Product catalogue with search, category, brand and price filters
- Product pages with pricing, nutritional information and live stock status
- Persistent, per user shopping cart with stock validation before checkout
- Delivery or in store pickup at checkout, with order confirmation
- Order history, status timeline and self service cancellation
- Profile management, saved addresses and a wishlist

### Administration panel
- Dashboard with sales overview, order statistics and low stock alerts
- Order management, including delivery partner assignment and status updates
- Product management with create, edit and bulk CSV import
- Inventory control with stock levels and reorder thresholds
- Customer management with role and account status controls
- Reports and analytics covering sales trends, top products and customer insights

### Delivery portal
- Dashboard of active and completed deliveries
- Assigned order queue with status updates through to delivery

### Platform and security
- OTP based sign in with bcrypt hashed codes and a 10 minute expiry
- Stateless sessions using HS256 JSON Web Tokens with a 30 minute lifetime
- Role based access control enforced through reusable middleware
- Redis backed sliding window rate limiting (3 OTP requests per hour per email)
- Brute force protection: 5 failed verifications lock the account for 1 hour
- Security headers including a nonce based CSP, HSTS and `X-Frame-Options: DENY`
- Prepared statements throughout, plus input sanitisation and CSRF protection
- Custom error, maintenance and health check pages

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Database | MySQL 8.0 (InnoDB, utf8mb4) |
| Cache and rate limiting | Redis 6+ |
| Front end | Bootstrap 5.3, Bootstrap Icons, Tailwind CSS utilities, vanilla JavaScript |
| Email | PHPMailer (bundled under `vendor/`) |
| Web server | Apache 2.4 with `mod_rewrite` |
| Testing | PHPUnit 8.5 with in process stubs for the database, Redis and email |

## Architecture

```text
Browser / API client
        |
        v
+-------------------------------------------------------------+
|  Pages (*.php, admin/, delivery/)     JSON API (api/*.php)   |
+-------------------------------------------------------------+
        |                                   |
        v                                   v
+------------------------+     +------------------------------+
|  Auth module (auth/)   |     |  Core services (core/)       |
|  OTP, JWT, RBAC,       |     |  Validator, RateLimit,       |
|  middleware            |     |  EmailService, Payment,      |
+------------------------+     |  StoreHours, ErrorHandler    |
        |                      +------------------------------+
        v                                   |
+-------------------------------------------------------------+
|  Models (models/): User, Product, Cart, Order               |
+-------------------------------------------------------------+
        |                                   |
        v                                   v
     MySQL                                Redis
```

## Project Structure

```text
JV-Shetty/
├── admin/            Administration panel (dashboard, products, inventory, orders, customers, reports)
├── api/              JSON endpoints (auth, products, search, cart, orders, wishlist, coupons, inventory, webhook)
├── assets/           Stylesheets, JavaScript and default imagery
├── auth/             OTP, JWT and RBAC services with JWT, RBAC and security middleware
├── config/           Application, database, JWT and Redis configuration
├── core/             Shared services: base model, validation, responses, rate limiting, email, payments
├── database/         Base schema, incremental migrations and migration runner
├── delivery/         Delivery partner portal
├── includes/         Shared navigation and footer partials
├── models/           Domain models: User, Product, Cart, Order
├── tests/            PHPUnit suites, bootstrap and stubs
├── vendor/           Bundled PHPMailer library
└── *.php             Customer facing pages (catalogue, cart, checkout, orders, profile, information pages)
```

## Getting Started

### Prerequisites

| Requirement | Version |
|---|---|
| PHP | 8.1 or later, with `pdo_mysql` |
| MySQL | 8.0 or later |
| Redis | 6.0 or later |
| Apache | 2.4 or later, with `mod_rewrite` |

### 1. Clone into the web root

The default `BASE_URL` is `http://localhost/jv_shetty`, so the simplest local setup is to clone into a folder named `jv_shetty` inside the web root (for example `htdocs` under XAMPP).

```bash
git clone https://github.com/goofy-daisy/JV-Shetty.git jv_shetty
```

### 2. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE jv_shetty_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p jv_shetty_db < database/schema.sql
mysql -u root -p jv_shetty_db < database/migrations/001_improvements.sql
mysql -u root -p jv_shetty_db < database/migrations/002_otp_auth.sql
```

### 3. Configure the environment

Configuration is read from environment variables, with local development defaults defined in `config/`. Set the following through the web server (for example `SetEnv` in Apache) or the PHP environment:

| Variable | Purpose |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASS`, `REDIS_DB` | Redis connection |
| `JWT_SECRET` | Token signing key, at least 32 random characters (required in production) |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD` | OTP and notification email delivery |
| `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` | Sender identity for outgoing email |
| `PAYMENT_GATEWAY`, `PAYMENT_GATEWAY_KEY`, `PAYMENT_GATEWAY_SECRET` | Payment provider credentials |
| `BASE_URL` | Public URL of the application |
| `APP_ENV` | Set to anything other than `production` to allow the development JWT fallback |

Secrets are never stored in the repository.

### 4. Run

Start Apache, MySQL and Redis, then open `http://localhost/jv_shetty`. A health check is available at `/health.php`.

## API Reference

All endpoints return `application/json`. Protected endpoints accept a token through `Authorization: Bearer <token>` or `X-Auth-Token: <token>`.

### Authentication

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth.php?action=request_otp` | Request a 6 digit OTP for a campus email |
| `POST` | `/api/auth.php?action=verify_otp` | Verify the OTP and receive a JWT |
| `POST` | `/api/auth.php?action=logout` | End the current session |
| `GET` | `/api/auth.php?action=me` | Return the authenticated user |

### Products

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `GET` | `/api/products.php` | Public | List and search products |
| `GET` | `/api/products.php?action=detail&id={id}` | Public | Product detail |
| `POST` | `/api/products.php?action=create` | Admin | Create a product |
| `POST` | `/api/products.php?action=update&id={id}` | Admin | Update a product |
| `POST` | `/api/products.php?action=delete&id={id}` | Admin | Delete a product |

### Cart

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `GET` | `/api/cart.php?action=get` | User | Cart contents |
| `POST` | `/api/cart.php?action=add` | User | Add an item |
| `POST` | `/api/cart.php?action=update` | User | Change quantity |
| `POST` | `/api/cart.php?action=remove` | User | Remove an item |
| `POST` | `/api/cart.php?action=clear` | User | Empty the cart |

### Orders

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `GET` | `/api/orders.php` | User | List own orders |
| `POST` | `/api/orders.php?action=create` | User | Place an order |
| `GET` | `/api/orders.php?action=detail&id={id}` | User | Order detail |
| `POST` | `/api/orders.php?action=cancel` | User | Cancel an order |
| `POST` | `/api/orders.php?action=update_status` | Admin | Update order status |

Additional endpoints cover search, wishlist, coupons, inventory, customers, user profile and payment webhooks.

## Roles and Permissions

| Role | Access |
|---|---|
| `super_admin` | Unrestricted |
| `admin` | Users, products, orders, reports, settings and audit logs |
| `faculty`, `student`, `customer` | Browse products, place and view own orders, manage profile |
| `delivery_partner` | View assigned orders, update delivery status, manage profile |

Endpoints are protected with a single line of middleware:

```php
$claims = JWTMiddleware::handle();                               // any authenticated user
$claims = RBACMiddleware::handle(['admin', 'super_admin']);      // specific roles
$claims = RBACMiddleware::requirePermission('manage_products');  // specific permission
```

## Testing

The authentication module is covered by more than 100 PHPUnit tests across three suites. The suites run without MySQL or Redis, using in process stubs.

```bash
cd tests
phpunit --configuration phpunit.xml
```

| Suite | Focus |
|---|---|
| `OTPBoundaryTest` | OTP expiry, rate limit and lockout boundaries, JWT expiry and tampering, RBAC basics, security middleware detection |
| `ExtendedCoverageTest` | AuthService flows, every JWT validation path, the OTP state machine, RBAC edge cases and middleware behaviour |
| `IntegrationJourneyTest` | End to end journey from OTP request to a role protected route, including rate limit and brute force scenarios |

## Known Limitations

- **Two authentication paths.** Customer facing pages use PHP session authentication, while the newer API layer uses JWT bearer tokens. A user authenticated through one path is not recognised by the other. Unifying both behind the JWT middleware is the planned next step.
- **Payments** are wired through a gateway abstraction and run in test mode by default.

## Maintainer

Maintained by **Kushagra Agrawal**.
