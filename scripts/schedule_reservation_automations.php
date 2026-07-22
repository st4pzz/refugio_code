<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/bootstrap.php';$db=Refugio\Config\Database::connection();$ids=$db->query("SELECT id FROM reservas WHERE status='RESERVA_CONFIRMADA' AND checkout>=CURDATE() AND checkin<=DATE_ADD(CURDATE(),INTERVAL 120 DAY)")->fetchAll(PDO::FETCH_COLUMN);$service=new Refugio\Services\ReservationAutomationService($db,$config);$count=0;foreach($ids as $id)$count+=$service->scheduleMilestones((int)$id);fwrite(STDOUT,$count." marco(s) verificado(s).\n");
