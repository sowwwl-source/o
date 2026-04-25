<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

logout_land();

header('Location: /?logged_out=1', true, 303);
exit;