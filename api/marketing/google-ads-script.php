<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
(new Refugio\Controllers\GoogleAdsScriptWebhookController())->receive();
