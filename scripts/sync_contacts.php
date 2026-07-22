<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';$db=Refugio\Config\Database::connection();$repo=new Refugio\Repositories\CustomerRepository($db);$rows=$db->query('SELECT * FROM reservas ORDER BY id')->fetchAll();$count=0;foreach($rows as$r){$repo->syncFromReservation($r);$count++;}fwrite(STDOUT,"{$count} reserva(s) vinculada(s) ao cadastro compartilhado.\n");
