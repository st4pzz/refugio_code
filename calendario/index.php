<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
try{$ical=(new Refugio\Services\UnifiedCalendarService(Refugio\Config\Database::connection()))->export((string)($_GET['token']??''));header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: inline; filename="refugio-cuscuzeiro.ics"');header('Cache-Control: private, no-store');echo $ical;}catch(Throwable){http_response_code(404);echo 'Calendário indisponível.';}
