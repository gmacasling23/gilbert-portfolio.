<?php
require_once __DIR__ . '/config.php';
respond(['success' => true] + loadCatalog(getDB()) + [
    'limits' => ['min_cm' => MIN_CM, 'max_cm' => MAX_CM, 'max_qty' => MAX_QTY, 'min_area' => MIN_AREA_SQM, 'max_items' => MAX_ITEMS],
]);
