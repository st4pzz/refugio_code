<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$result = (new Refugio\Services\ExternalReviewService($db, $config))->syncGoogle(0);
fwrite(STDOUT, sprintf("Google Business Profile sincronizado: %d de %d avaliação(ões).\n", $result['imported'], $result['total']));
