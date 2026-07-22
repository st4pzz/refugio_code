<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';$db=Refugio\Config\Database::connection();$service=new Refugio\Services\FinancialService($db);$systemUser=(int)$db->query('SELECT id FROM usuarios_admin WHERE ativo=1 ORDER BY id LIMIT 1')->fetchColumn();if($systemUser<=0)throw new RuntimeException('Crie um administrador antes de sincronizar o financeiro.');$ids=$db->query('SELECT DISTINCT reserva_id FROM pagamentos ORDER BY reserva_id')->fetchAll(PDO::FETCH_COLUMN);foreach($ids as$id)$service->syncReservationPayments((int)$id,$systemUser);fwrite(STDOUT,count($ids)." reserva(s) sincronizada(s) com o financeiro.\n");
