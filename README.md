# FreshMart Inventory + POS (Core PHP, PDO, MySQL, Bootstrap 5)

## Install on WAMP (5 steps)
1. Copy this `inventory_pos` folder to `C:\wamp64\www\inventory_pos`.
2. Start WAMP (icon must be **green**).
3. Open phpMyAdmin -> **Import** -> choose `sql/inventory.sql` -> Go.
   (This creates the `inventory_pos` database, tables and 15 sample products.)
4. If your MySQL root has a password, edit `config/config.php` (`DB_PASS`).
5. Browse to **http://localhost/inventory_pos/** - the POS opens.

## Pages
| URL | What |
|---|---|
| `/index.php` | POS - scan barcode (try `1001` ... `1015`), tap products, PAY |
| `/dashboard.php` | Today's sales, 7-day Chart.js chart, blinking low-stock alerts |
| `/products.php` | Product CRUD with image upload |
| `/bill.php?id=N` | 80mm printable receipt (`&print=1` auto-opens print dialog) |
| `/api/sale.php` | JSON sale endpoint (PDO transaction) |

## POS shortcuts
`F2` focus barcode - `F4` search - `F9` PAY

## How the sale transaction works (`api/sale.php`)
`beginTransaction` -> insert `sales` -> for each item: lock product row, `UPDATE products SET qty = qty - sold`,
if `qty < 0` -> `rollBack()` and return **"Out of Stock: <product>"** -> else insert `sale_items` ->
update totals -> `commit()`.
Prices are read from the database, never trusted from the browser.

## Notes
- Bootstrap, jQuery, Chart.js load from CDN (internet needed). For offline tills, download them into `assets/` and change the links in `includes/header.php` / `footer.php` / `dashboard.php`.
- Tables must stay **InnoDB** (WAMP default) or transactions will not roll back.
- Currency / store name / address for the bill: `config/config.php`.
- Uploaded images go to `assets/uploads/` (PHP execution is blocked there by `.htaccess`).
