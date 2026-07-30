<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
$db=Refugio\Config\Database::connection();
$queue=new Refugio\Services\JobQueueService($db);
$count=(new Refugio\Services\ICalendarService($db))->enqueueDue($queue);
fwrite(STDOUT,$count." sincronização(ões) enfileirada(s).\n");
