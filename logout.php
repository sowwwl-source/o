<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

logout_land();

header('Location: ' . o_route_path('/') . '?logged_out=1', true, 303);
exit;
