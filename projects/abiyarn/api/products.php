<?php
/* ============================================
   AbiYarn – Products Data Endpoint
   Outputs `const PRODUCTS = [...]` as JavaScript,
   read live from the `products` table so edits made
   in the Owner Panel (/admin/index.php) show up on
   the storefront immediately.

   Include this the same way js/products.js used to be:
     <script src="api/products.php"></script>
   ============================================ */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/javascript; charset=utf-8');
// Stock changes with every order, so never let the browser reuse an old copy.
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);

$products = [];

try {
    $pdo  = db();
    $rows = $pdo->query('SELECT id, name, category, price, description, image, stock, status FROM products ORDER BY id ASC')->fetchAll();
    foreach ($rows as $r) {
        $products[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'category'    => $r['category'],
            'price'       => (float)$r['price'],
            'description' => $r['description'],
            'image'       => $r['image'],
            'stock'       => (int)$r['stock'],
            'status'      => $r['status'],
        ];
    }
} catch (Throwable $e) {
    // DB / products table not set up yet — fail quietly with an empty
    // catalog instead of breaking every page that loads this script.
    // Run schema2_updated.sql to create + seed the `products` table.
    $products = [];
}

echo "/* Auto-generated from the `products` table — do not edit by hand.\n";
echo "   Manage names / prices / categories from the Owner Panel in admin/index.php. */\n";
echo 'const PRODUCTS = ' . json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
?>
/* ============================================
   PRICE FORMATTER — do not edit this part
   ============================================ */
function formatPrice(amount) {
  return `₱${amount.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}
