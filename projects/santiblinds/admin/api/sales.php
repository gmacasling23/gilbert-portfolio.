<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

$byMonth = $pdo->query(
    "SELECT DATE_FORMAT(COALESCE(completed_orders.sale_date, completed_orders.order_date), '%Y-%m') AS month,
            COALESCE(SUM(completed_orders.revenue),0) AS revenue, COUNT(*) AS orders
     FROM (
         SELECT o.order_id, MIN(o.order_date) AS order_date,
                SUM(o.total_amount) AS order_amount,
                COALESCE(MAX(s.amount_paid), SUM(o.total_amount)) AS revenue,
                MAX(s.sale_date) AS sale_date
         FROM orders o
         LEFT JOIN sale s ON s.order_id = o.order_id
         GROUP BY o.order_id
         HAVING MAX(o.status) IN ('Completed', 'Ready', 'Ready for Install')
     ) AS completed_orders
     WHERE COALESCE(completed_orders.sale_date, completed_orders.order_date) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY month ORDER BY month ASC"
)->fetchAll();

// Completed order items, grouped by product
$byProduct = $pdo->query(
    "SELECT p.name AS product, SUM(o.quantity) AS units,
            COALESCE(SUM(o.total_amount),0) AS revenue
     FROM orders o JOIN product p ON p.product_id = o.product_id
     WHERE o.status IN ('Completed', 'Ready', 'Ready for Install')
     GROUP BY p.product_id, p.name ORDER BY revenue DESC"
)->fetchAll();

$totals = $pdo->query(
    "SELECT COALESCE(SUM(completed_orders.revenue),0) AS t,
            COALESCE(AVG(completed_orders.revenue),0) AS a,
            COUNT(*) AS n
     FROM (
         SELECT o.order_id,
                SUM(o.total_amount) AS order_amount,
                COALESCE(MAX(s.amount_paid), SUM(o.total_amount)) AS revenue
         FROM orders o
         LEFT JOIN sale s ON s.order_id = o.order_id
         GROUP BY o.order_id
         HAVING MAX(o.status) IN ('Completed', 'Ready', 'Ready for Install')
     ) AS completed_orders"
)->fetch();

echo json_encode([
    'success'         => true,
    'total_revenue'   => (float)$totals['t'],
    'avg_sale_value'  => (float)$totals['a'],
    'sales_count'     => (int)$totals['n'],
    'by_month'        => $byMonth,
    'by_product'      => $byProduct,
]);
