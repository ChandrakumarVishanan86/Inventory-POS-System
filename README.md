# 🛒 FreshMart – Inventory + POS System

A fast, supermarket-style **Point of Sale and Inventory Management** system built with **Core PHP (OOP + PDO)**, **MySQL**, **Bootstrap 5**, vanilla JavaScript + jQuery and **Chart.js**. No frameworks. Runs 100% on **WAMP Server**.

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-InnoDB-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-4-FF6384?logo=chartdotjs&logoColor=white)
![WAMP](https://img.shields.io/badge/WAMP-ready-orange)

---

## ✨ Features

### POS screen (`index.php`)
- Split-screen layout: **70 % product grid** / **30 % cart**
- Barcode scanner input, auto-focused, fetches the product by **AJAX** on `Enter`
- Instant JS search + category filter chips, product images with stock badges
- Cart with qty +/−, discount, cash / card, quick-cash buttons, live change calculation
- No page reloads, beep feedback, toast messages
- Keyboard shortcuts: `F2` barcode · `F4` search · `F9` pay

### Safe checkout (`api/sale.php`)
Everything runs inside **one PDO transaction**:

```
beginTransaction
  → INSERT sales
  → for each item:  lock product row (FOR UPDATE)
                    UPDATE products SET qty = qty - sold
                    if qty < 0  → ROLLBACK + "Out of Stock"
                    INSERT sale_items
  → UPDATE sales totals
commit
```
Prices are always read from the database – never trusted from the browser. Items are locked in a fixed order to avoid deadlocks when several tills sell at once.

### Dashboard (`dashboard.php`)
- Today's sales total and invoice count
- **Blinking low-stock alerts** (CSS `@keyframes blink`)
- 7-day sales chart + top-selling products chart (Chart.js)
- Recent sales with one-click bill reprint

### Products (`products.php`)
- Full CRUD with **image upload** (`move_uploaded_file`, real MIME check, 2 MB limit, random filenames)
- Low-stock level per product, duplicate-barcode protection, CSRF token

### Printable bill (`bill.php`)
- 80 mm thermal-style receipt with `@media print` CSS
- Opens straight from the POS after payment

---

## 🚀 Installation (WAMP)

1. Clone or download this repo into `C:\wamp64\www\inventory_pos`
   ```bash
   cd C:\wamp64\www
   git clone https://github.com/<your-username>/inventory_pos.git
   ```
2. Start WAMP (tray icon must be **green**).
3. Open **phpMyAdmin → Import** and choose `sql/inventory.sql`
   (creates the `inventory_pos` database, tables and 15 sample products).
4. If your MySQL `root` has a password, edit `config/config.php` → `DB_PASS`.
5. Open **http://localhost/inventory_pos/**

> Try scanning the sample barcodes `1001` … `1015`.

---

## 📁 Project structure

```
inventory_pos/
├── index.php            # POS screen
├── dashboard.php        # Dashboard + charts
├── products.php         # Product CRUD  (+ AJAX barcode lookup)
├── bill.php             # Printable receipt
├── api/
│   └── sale.php         # Transactional sale endpoint (JSON)
├── classes/
│   ├── Product.php      # Product model (PDO)
│   └── Report.php       # Dashboard queries
├── config/
│   ├── config.php       # Settings, helpers, autoloader
│   └── database.php     # PDO singleton
├── includes/
│   ├── header.php
│   └── footer.php
├── assets/
│   ├── css/style.css
│   ├── js/pos.js
│   └── uploads/         # Product images
└── sql/
    └── inventory.sql    # Schema + sample data
```

---

## ⚙️ Configuration

Edit `config/config.php`:

| Setting | Purpose |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `BASE_URL` | URL folder (default `/inventory_pos`) |
| `CURRENCY` | Currency symbol shown everywhere |
| `STORE_NAME`, `STORE_ADDRESS`, `STORE_PHONE` | Printed on the bill |

---

## 📝 Notes

- Tables **must stay InnoDB** (WAMP default) or transactions cannot roll back.
- Bootstrap, jQuery and Chart.js load from a CDN. For an offline till, download them into `assets/` and update the links in `includes/header.php`, `includes/footer.php` and `dashboard.php`.
- PHP execution is blocked inside `assets/uploads/` by `.htaccess`.
- There is **no login / user roles yet** – don't expose this to the internet as-is.

## 🗺️ Roadmap

- [ ] User login and roles (admin / cashier)
- [ ] Refunds and held sales
- [ ] Stock-in / purchase orders
- [ ] Sales reports export (CSV / PDF)

## 📄 License

MIT – free to use and modify.
