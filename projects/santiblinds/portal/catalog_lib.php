<?php
// ============================================================
//  Santi Blinds — catalog + pricing, read from the database
//  (product, category, material, color). The browser only shows
//  estimates; every saved price is recomputed here.
//
//  Quotations (quotation + quotation_item) store these prices; an
//  order is placed from a quotation and keeps the quoted prices.
//
//  Unit price = max(1 m², width × height) × product.base_price
//               + material.price_modifier
//  (product.base_price is the price per square metre; the
//   material modifier is a flat amount added per blind.)
// ============================================================

const MIN_AREA_SQM = 1.0;   // small windows are billed as at least 1 m²
const MIN_CM    = 30;
const MAX_CM    = 400;
const MAX_QTY   = 50;
const MAX_ITEMS = 20;
const QUOTE_VALID_DAYS = 7;  // a quotation's prices are honoured for this many days

function loadCatalog(PDO $pdo): array {
    return [
        'products' => $pdo->query(
            "SELECT p.product_id, p.name, p.blind_type, p.description, p.base_price, p.image_url, p.stock_qty,
                    (p.stock_qty > 0) AS in_stock, c.name AS category
             FROM product p JOIN category c ON c.category_id = p.category_id
             ORDER BY c.name, p.name"
        )->fetchAll(),
        'materials' => $pdo->query("SELECT material_id, name, price_modifier FROM material ORDER BY name")->fetchAll(),
        'colors'    => $pdo->query("SELECT color_id, name, hex_code FROM color ORDER BY name")->fetchAll(),
    ];
}

function unitPrice(float $basePerSqm, float $modifier, float $w, float $h): float {
    $area = max(MIN_AREA_SQM, ($w * $h) / 10000);
    return round($area * $basePerSqm + $modifier, 2);
}

/**
 * Validate order items and price them on the server.
 * Returns ['errors' => [...], 'rows' => [...ready to insert...], 'total' => float].
 */
function validateItems(PDO $pdo, $items): array {
    $errors = []; $rows = []; $total = 0.0;
    if (!is_array($items) || !$items) return ['errors' => ['Add at least one blind to your order.'], 'rows' => [], 'total' => 0.0];
    if (count($items) > MAX_ITEMS) return ['errors' => ['An order can have at most ' . MAX_ITEMS . ' items.'], 'rows' => [], 'total' => 0.0];

    $cat = loadCatalog($pdo);
    $products  = array_column($cat['products'],  null, 'product_id');
    $materials = array_column($cat['materials'], null, 'material_id');
    $colors    = array_column($cat['colors'],    null, 'color_id');

    $requestedByProduct = [];
    foreach (array_values($items) as $n => $it) {
        $label = 'Item ' . ($n + 1) . ': ';
        if (!is_array($it)) { $errors[] = $label . 'invalid item.'; continue; }
        $pid = (int)($it['product_id'] ?? 0);  $mid = (int)($it['material_id'] ?? 0);  $cid = (int)($it['color_id'] ?? 0);
        $w   = is_numeric($it['width_cm']  ?? null) ? round((float)$it['width_cm'],  1) : 0;
        $h   = is_numeric($it['height_cm'] ?? null) ? round((float)$it['height_cm'], 1) : 0;
        $qty = is_numeric($it['quantity']  ?? null) ? (int)$it['quantity'] : 0;

        $bad = [];
        if (!isset($products[$pid]))  $bad[] = 'choose a blind type';
        if (!isset($materials[$mid])) $bad[] = 'choose a material';
        if (!isset($colors[$cid]))    $bad[] = 'choose a colour';
        if ($w < MIN_CM || $w > MAX_CM) $bad[] = 'width must be ' . MIN_CM . '–' . MAX_CM . ' cm';
        if ($h < MIN_CM || $h > MAX_CM) $bad[] = 'height must be ' . MIN_CM . '–' . MAX_CM . ' cm';
        if ($qty < 1 || $qty > MAX_QTY) $bad[] = 'quantity must be 1–' . MAX_QTY;
        if (isset($products[$pid]) && (int)$products[$pid]['stock_qty'] < 1) $bad[] = 'this blind is out of stock';
        if ($bad) { $errors[] = $label . implode(', ', $bad) . '.'; continue; }

        $requestedByProduct[$pid] = ($requestedByProduct[$pid] ?? 0) + $qty;
        $unit = unitPrice((float)$products[$pid]['base_price'], (float)$materials[$mid]['price_modifier'], $w, $h);
        $line = round($unit * $qty, 2);
        $total += $line;
        $rows[] = ['product_id' => $pid, 'material_id' => $mid, 'color_id' => $cid,
                   'width_cm' => $w, 'height_cm' => $h, 'quantity' => $qty,
                   'unit_price' => $unit, 'total_amount' => $line];
    }

    foreach ($requestedByProduct as $productId => $quantity) {
        $product = $products[$productId];
        $available = (int)$product['stock_qty'];
        if ($quantity > $available) {
            $errors[] = sprintf('%s: only %d in stock; %d requested.', $product['name'], $available, $quantity);
        }
    }
    return ['errors' => $errors, 'rows' => $rows, 'total' => round($total, 2)];
}

/** Lock the requested products and verify aggregate quantities inside an active transaction. */
function lockAvailableInventory(PDO $pdo, array $items): array {
    $requestedByProduct = [];
    foreach ($items as $item) {
        $productId = (int)$item['product_id'];
        $requestedByProduct[$productId] = ($requestedByProduct[$productId] ?? 0) + (int)$item['quantity'];
    }
    ksort($requestedByProduct, SORT_NUMERIC);

    $stmt = $pdo->prepare('SELECT name, stock_qty FROM product WHERE product_id = :id FOR UPDATE');
    $errors = [];
    foreach ($requestedByProduct as $productId => $quantity) {
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();
        $available = $product ? (int)$product['stock_qty'] : 0;
        if (!$product || $available < $quantity) {
            $name = $product ? $product['name'] : 'A selected product';
            $errors[] = $available === 0
                ? $name . ' is out of stock.'
                : sprintf('%s has only %d in stock; %d requested.', $name, $available, $quantity);
        }
    }
    return $errors;
}

// ------------------------------------------------------------
//  Quotations
// ------------------------------------------------------------
function quotationRef(int $id): string {
    return sprintf('QT-%05d', $id);
}

/** Active | Ordered | Expired. "Expired" is derived from valid_until, not stored. */
function quotationStatus(array $q): string {
    if (($q['status'] ?? '') === 'Ordered') return 'Ordered';
    return strtotime((string)$q['valid_until']) < time() ? 'Expired' : 'Active';
}

/**
 * One quotation owned by $customerId, with named items — or null if it isn't theirs.
 * $lock = SELECT ... FOR UPDATE on the quotation row (only call inside a transaction).
 */
function loadQuotation(PDO $pdo, int $id, int $customerId, bool $lock = false): ?array {
    $q = $pdo->prepare(
        "SELECT quotation_id, customer_id, total_amount, status, valid_until, created_at
         FROM quotation WHERE quotation_id = :id AND customer_id = :c" . ($lock ? ' FOR UPDATE' : '')
    );
    $q->execute([':id' => $id, ':c' => $customerId]);
    $row = $q->fetch();
    if (!$row) return null;

    $i = $pdo->prepare(
        "SELECT qi.product_id, qi.material_id, qi.color_id, qi.width_cm, qi.height_cm,
                qi.quantity, qi.unit_price, qi.total_amount,
               p.name AS product, p.stock_qty, m.name AS material, col.name AS color, col.hex_code
         FROM quotation_item qi
         JOIN product  p   ON p.product_id  = qi.product_id
         JOIN material m   ON m.material_id = qi.material_id
         JOIN color    col ON col.color_id  = qi.color_id
         WHERE qi.quotation_id = :id ORDER BY qi.quotation_item_id"
    );
    $i->execute([':id' => $id]);

    return [
        'quotation_id' => (int)$row['quotation_id'],
        'reference'    => quotationRef((int)$row['quotation_id']),
        'status'       => quotationStatus($row),
        'total_amount' => (float)$row['total_amount'],
        'valid_until'  => $row['valid_until'],
        'created_at'   => $row['created_at'],
        'items'        => array_map(fn($r) => [
            'product_id'   => (int)$r['product_id'],
            'stock_qty'    => (int)$r['stock_qty'],
            'material_id'  => (int)$r['material_id'],
            'color_id'     => (int)$r['color_id'],
            'product'      => $r['product'],
            'material'     => $r['material'],
            'color'        => $r['color'],
            'hex_code'     => $r['hex_code'],
            'width_cm'     => (float)$r['width_cm'],
            'height_cm'    => (float)$r['height_cm'],
            'quantity'     => (int)$r['quantity'],
            'unit_price'   => (float)$r['unit_price'],
            'total_amount' => (float)$r['total_amount'],
        ], $i->fetchAll()),
    ];
}
