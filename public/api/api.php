<?php
declare(strict_types=1);

/* NAT Cloud - 节点对账入口（/api/api.php?f=node_sync|node_report） */

define('NC_ROOT', dirname(__DIR__, 2));

require NC_ROOT . '/app/bootstrap.php';
require NC_ROOT . '/app/pages/api.php';

api_node();
