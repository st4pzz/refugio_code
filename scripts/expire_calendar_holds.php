<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';$count=(new Refugio\Services\UnifiedCalendarService(Refugio\Config\Database::connection()))->releaseExpiredHolds();fwrite(STDOUT,$count." retenção(ões) expirada(s).\n");
