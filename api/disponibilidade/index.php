<?php
declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/bootstrap.php';
(new Refugio\Controllers\PublicAvailabilityController($config))->month();
