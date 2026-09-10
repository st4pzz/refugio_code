<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';
(new Refugio\Controllers\PublicAvailabilityController($config))->page();
