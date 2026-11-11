YUMI THE FLEUR - RECOVERED PROJECT
==================================

Recovered from files previously saved in the ChatGPT Library.

STRUCTURE
---------
index.html
about.html
gallery.html
contact.html
commission.html
style.css
main.js
db.php
login.php
signup.php
logout.php
orders.php
submit_order.php
admin/
  index.php
  login.php
  logout.php
  orders.php
  customers.php
  references.php
database/
  yumi_the_fleur.sql

ADMIN
-----
Admin dashboard is now:
  /admin/index.php

The original admin creation script is NOT included in this deployable package.
The admin account is embedded in the SQL using a one-way password hash.
The original plaintext admin password is not written into this README or SQL.

DATABASE
--------
The recovered db.php is included from the saved project because it was available
in the Library. If your hosting provider gives you new database credentials,
replace the connection values in db.php before uploading.

IMPORTANT
---------
The public pages reference an Images/ folder containing the site's artwork and
logos. Those binary image assets were not recoverable as a complete named
Images/ directory from the available saved files, so this package restores the
recoverable source code and backend. The HTML still points to the original
Images/... paths.

Also create an uploads/ folder in the web root for commission reference uploads.
Give it normal PHP write permission supported by your host.
