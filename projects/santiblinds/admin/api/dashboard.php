<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

$totalOrders    = (int)$pdo->query("SELECT COUNT(DISTINCT order_id) FROM orders WHERE is_archived = 0")->fetchColumn();
$pendingOrders  = (int)$pdo->query("SELECT COUNT(DISTINCT order_id) FROM orders WHERE is_archived = 0 AND status = 'Pending'")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();

$revenueStats = $pdo->query(
    "SELECT COALESCE(SUM(completed_orders.revenue),0) AS total_revenue,
            COALESCE(SUM(CASE
                WHEN YEAR(completed_orders.revenue_date) = YEAR(CURDATE())
                 AND MONTH(completed_orders.revenue_date) = MONTH(CURDATE())
                THEN completed_orders.revenue ELSE 0 END),0) AS revenue_this_month
     FROM (
         SELECT o.order_id, COALESCE(MAX(s.sale_date), MIN(o.order_date)) AS revenue_date,
                COALESCE(MAX(s.amount_paid), SUM(o.total_amount)) AS revenue
         FROM orders o
         LEFT JOIN sale s ON s.order_id = o.order_id
         GROUP BY o.order_id
         HAVING MAX(o.status) IN ('Completed', 'Ready', 'Ready for Install')
     ) AS completed_orders"
)->fetch();
$revenueTotal = (float)$revenueStats['total_revenue'];
$revenueThisMonth = (float)$revenueStats['revenue_this_month'];

$low = $pdo->prepare("SELECT COUNT(*) FROM product WHERE stock_qty <= :lvl");
$low->execute([':lvl' => LOW_STOCK_LEVEL]);

$recentOrders = $pdo->query(
    "SELECT o.order_id, c.full_name,
            CASE WHEN MAX(o.status) IN ('Ready', 'Ready for Install') THEN 'Completed'
                 ELSE MAX(o.status) END AS status,
            SUM(o.total_amount) AS amount,
            MIN(o.order_date) AS order_date,
            GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products
     FROM orders o
     JOIN customer c ON c.customer_id = o.customer_id
     JOIN product p  ON p.product_id  = o.product_id
     WHERE o.is_archived = 0
     GROUP BY o.order_id, c.full_name
     ORDER BY order_date DESC, o.order_id DESC LIMIT 5"
)->fetchAll();

$statusBreakdown = $pdo->query(
    "SELECT CASE WHEN status IN ('Ready', 'Ready for Install') THEN 'Completed'
                 ELSE status END AS status,
            COUNT(DISTINCT order_id) AS count
     FROM orders
     WHERE is_archived = 0
     GROUP BY CASE WHEN status IN ('Ready', 'Ready for Install') THEN 'Completed'
                   ELSE status END"
)->fetchAll();

echo json_encode([
    'success' => true,
    'stats' => [
        'total_orders'       => $totalOrders,
        'pending_orders'     => $pendingOrders,
        'total_customers'    => $totalCustomers,
        'revenue_this_month' => $revenueThisMonth,
        'revenue_total'      => $revenueTotal,
        'low_stock_items'    => (int)$low->fetchColumn(),
    ],
    'recent_orders'    => $recentOrders,
    'status_breakdown' => $statusBreakdown,
]);
