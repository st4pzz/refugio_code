<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
if(!Refugio\Support\Env::bool('FINANCE_RECURRING_CRON_ENABLED',true)){fwrite(STDOUT,"Geracao automatica de recorrencias desativada.\n");exit(0);}
$service=new Refugio\Services\FinancialService(Refugio\Config\Database::connection());
$count=$service->generateRecurrences();$overdue=$service->updateOverdueStatuses();
fwrite(STDOUT,"{$count} lancamento(s) recorrente(s) criado(s); {$overdue} status vencido(s) atualizado(s).\n");
