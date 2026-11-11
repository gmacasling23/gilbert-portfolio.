# AbiYarn PHP + MySQL Backend
## Setup Guide

### What's included
```
config.php              ← DB credentials & helpers (edit this first)
schema2_updated.sql     ← Import to create the tables and database routines
api/
  save-order.php        ← Receives checkout orders from your website
  save-inquiry.php      ← Receives inquiry form submissions
  update-status.php     ← Used by the dashboard to update statuses
admin/
  index.php             ← Your full admin dashboard (login-protected)
js-snippets/
  checkout-patch.js     ← Replace placeOrder() in js/checkout.js
  inquiry-patch.js      ← Patch the save block in js/inquiry.js
```

---

### Step 1 — Set up your database

1. Log in to **phpMyAdmin** (via your hosting cPanel)
2. Create a new database called `abiyarn_db`
3. Click **Import**, upload `schema2_updated.sql` and run it to create the tables, indexes and database routines.
4. Create a database user, assign it to `abiyarn_db` with full privileges

---

### Step 2 — Edit config.php

Open `config.php` and fill in:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'abiyarn_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');

define('ADMIN_USER', 'abi');
define('ADMIN_PASS', 'ChangeThisPassword!');
```

---

### Step 3 — Upload files to your server

Upload everything to your hosting. Suggested structure:

```
public_html/
  index.html          ← your existing website files
  menu.html
  inquiry.html
  about.html
  contact.html
  js/
  css/
  assets/
  api/                ← upload api/ folder here
    save-order.php
    save-inquiry.php
    update-status.php
  admin/              ← upload admin/ folder here
    index.php
  config.php          ← upload at root (above public_html if possible)
```

> **Security tip:** Move `config.php` one level ABOVE `public_html` if your
> host allows it, so it's never directly accessible from a browser.
> Then update the `require_once` paths in each PHP file accordingly.

---

### Step 4 — Patch your JavaScript

**In js/checkout.js:**
- Add `const API_BASE = '/api';` near the top
- Replace the entire `placeOrder()` function with the one in `js-snippets/checkout-patch.js`

**In js/inquiry.js:**
- Add `const API_BASE = '/api';` near the top
- Inside the `try` block after `emailjs.send(...)` succeeds, replace the
  localStorage "Admin Database" block with the code in `js-snippets/inquiry-patch.js`

---

### Step 5 — Access your dashboard

Visit: `https://yourdomain.com/admin/`

Log in with the username and password you set in `config.php`.

---

### Features
- ✅ All orders saved with full address, items, payment method
- ✅ All inquiries saved with type, colors, needed-by date, details
- ✅ Update order/inquiry status with one click
- ✅ Search and filter by status, payment, name, email
- ✅ Export to CSV (orders or inquiries)
- ✅ Analytics charts (payment methods, statuses, inquiry types)
- ✅ Orders are confirmed only after the server saves them; the cart remains if the server is unreachable

---

### Hosting recommendations (Philippines)
- **Hostinger** – affordable, good PH speed, easy phpMyAdmin
- **SiteGround** – reliable, beginner-friendly
- **InfinityFree** – free tier, good for testing

All three support PHP 8+ and MySQL, which is all you need.
