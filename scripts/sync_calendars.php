<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';$db=Refugio\Config\Database::connection();$queue=new Refugio\Services\JobQueueService($db);$ids=$db->query("SELECT id FROM calendar_sources WHERE ativo=1 AND (proximo_sync_em IS NULL OR proximo_sync_em<=NOW()) ORDER BY COALESCE(proximo_sync_em,'1970-01-01') LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id)$queue->enqueue('ICAL_SYNC',['source_id'=>(int)$id],'ical-sync:'.$id.':'.date('YmdHi'),70,5);fwrite(STDOUT,count($ids)." sincronização(ões) enfileirada(s).\n");
