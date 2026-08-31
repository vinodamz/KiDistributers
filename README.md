# Ki Distributers — distribution operations

PHP + MySQL app for a distribution desk: outlets, SKUs, orders, van deliveries, godown stock, invoices and collections.

Same tech stack and UI language as [Little Graduates](https://github.com/vinodamz/MontessoriTraineeTeacher):

- PHP 8.1+, MySQL InnoDB utf8mb4
- Server-rendered HTML, one stylesheet, almost no JS (PIN pad + order lines)
- PIN login (4–6 digits, bcrypt), remember-this-device cookie
- Role (`admin` / `sales` / `warehouse` / `accounts`) plus per-module access
- Hostgator / cPanel friendly — no build step

## Two kinds of apps

| | |
|---|---|
| **Distribution** | The desk you already use: `/` · My Day, orders, van, stock, invoices. Sales still land on **My Day**. |
| **Super app** | `/apps/` — launcher for every business plus shared tools (PDF compress first). Same PIN login. |

Add another company as a tile: `hub.businesses` in `includes/config.php` (name + URL). Tools stay on this site so they are not rebuilt per business.

## Modules

| | |
|---|---|
| **My Day** | Sales landing: today’s drops, open collections, low stock |
| **Products** | SKU catalog, trade price, GST, reorder |
| **Customers** | Outlets, routes, credit limit |
| **Orders** | Beat booking with line items |
| **Deliveries** | Van sheet; marking delivered reduces stock and raises an invoice |
| **Stock** | On-hand + adjustments + movement log |
| **Invoices** | Collections (cash / UPI / NEFT / cheque) |
| **Expenses** | Claims for fuel and travel |
| **Tasks** | Follow-ups that are not an order |
| **Admin** | Users, PINs, modules |

## Local development

Requires PHP 8.1+ and MySQL.

```bash
mysql -u root -p -e "CREATE DATABASE kd_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p kd_dev < sql/schema.sql
mysql -u root -p kd_dev < sql/seeds.sql   # optional demo SKUs + outlets

cp includes/config.example.php includes/config.php
# edit db credentials

php -S 127.0.0.1:8000
# http://127.0.0.1:8000/install.php  → first admin → delete install.php
# http://127.0.0.1:8000/login.php
```

## Auth

- Tap your name, enter PIN (same card + numpad as Little Graduates).
- Sales users land on **My Day**. Everyone else gets the grouped home tiles.
- Admins see every module regardless of the `modules` SET.

## Deploy

Merging to `main` runs GitHub Actions: PHP lint, then a cPanel UAPI git-pull that rsyncs via `.cpanel.yml`. One-time Hostgator setup and required secrets (`CPANEL_HOST`, `CPANEL_USER`, `CPANEL_TOKEN`) are in [CICD.md](CICD.md).
