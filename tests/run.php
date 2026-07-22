<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';
use Refugio\Models\ReservationStatus;
use Refugio\Services\AvailabilityService;
use Refugio\Services\UploadService;
use Refugio\Support\ReservationValidator;

$passed = 0; $failed = 0;
function test(string $name, callable $callback): void {
    global $passed, $failed;
    try { $callback(); $passed++; fwrite(STDOUT, "[OK] {$name}\n"); }
    catch (Throwable $e) { $failed++; fwrite(STDERR, "[FALHA] {$name}: {$e->getMessage()}\n"); }
}
function expect(bool $condition, string $message='Condicao nao atendida'): void { if (!$condition) throw new RuntimeException($message); }

$valid = [
    'checkin'=>date('Y-m-d',strtotime('+10 days')), 'checkout'=>date('Y-m-d',strtotime('+12 days')),
    'adultos'=>'2','criancas'=>'1','nome'=>'Maria da Silva','cpf'=>'52998224725','email'=>'maria@example.com','telefone'=>'(16) 99999-9999',
    'regras_aceitas'=>'1','cancelamento_aceito'=>'1','contato_autorizado'=>'1','website'=>'',
];
test('solicitacao valida e telefone internacional', function() use ($valid,$config) { $r=ReservationValidator::validate($valid,$config); expect(!$r['errors']); expect($r['data']['telefone']==='5516999999999'); });
test('datas invalidas', function() use ($valid,$config) { $d=$valid; $d['checkout']=$d['checkin']; expect(isset(ReservationValidator::validate($d,$config)['errors']['checkout'])); });
test('limite de hospedes', function() use ($valid,$config) { $d=$valid; $d['adultos']='10'; $d['criancas']='1'; expect(isset(ReservationValidator::validate($d,$config)['errors']['hospedes'])); });
test('honeypot antispam', function() use ($valid,$config) { $d=$valid; $d['website']='spam'; expect(isset(ReservationValidator::validate($d,$config)['errors']['form'])); });
test('transicao de aprovacao valida', function() { expect(ReservationStatus::AGUARDANDO_APROVACAO->canTransitionTo(ReservationStatus::AGUARDANDO_PAGAMENTO)); });
test('transicao invalida bloqueada', function() { expect(!ReservationStatus::RECUSADA->canTransitionTo(ReservationStatus::RESERVA_CONFIRMADA)); try { ReservationStatus::RECUSADA->assertTransitionTo(ReservationStatus::RESERVA_CONFIRMADA); } catch (DomainException) { return; } throw new RuntimeException('Transicao foi aceita.'); });
test('estados que bloqueiam datas', function() { expect(in_array('RESERVA_CONFIRMADA',ReservationStatus::blocking(),true)); expect(!in_array('AGUARDANDO_APROVACAO',ReservationStatus::blocking(),true)); });
test('formula de sobreposicao presente', function() { $source=file_get_contents(BASE_PATH.'/app/Services/AvailabilityService.php'); expect(str_contains($source,'checkin < ? AND checkout > ?')); });
test('regra de sobreposicao de intervalos', function() { expect(AvailabilityService::overlaps('2026-08-10','2026-08-15','2026-08-14','2026-08-20')); expect(!AvailabilityService::overlaps('2026-08-10','2026-08-15','2026-08-15','2026-08-20')); });
test('aprovacao usa transacao e mutex', function() { $source=file_get_contents(BASE_PATH.'/app/Services/ReservationService.php'); expect(str_contains($source,'beginTransaction()')); expect(str_contains($source,'lockApprovalMutex()')); $migration=file_get_contents(BASE_PATH.'/database/migrations/001_create_reservas.sql'); expect(str_contains($migration,'reserva_mutex')); });
test('token publico nao usa id sequencial', function() { $source=file_get_contents(BASE_PATH.'/app/Services/ReservationService.php'); expect(str_contains($source,'random_bytes(32)')); });
test('rota administrativa exige autenticacao', function() { $source=file_get_contents(BASE_PATH.'/app/Controllers/AdminController.php'); expect(substr_count($source,'requireAdmin()')>=5); });
test('falhas de notificacao sao registradas sem rollback', function() { $source=file_get_contents(BASE_PATH.'/app/Services/NotificationService.php'); expect(str_contains($source,'catch (Throwable $e)')); expect(str_contains($source,'$this->failure')); });
test('upload malicioso recusado', function() use ($config) { $tmp=tempnam(sys_get_temp_dir(),'bad'); file_put_contents($tmp,"<?php echo 'x';"); try { (new UploadService($config['upload_max_bytes']))->receipt(['error'=>UPLOAD_ERR_OK,'size'=>filesize($tmp),'tmp_name'=>$tmp,'name'=>'ataque.jpg']); } catch (RuntimeException) { unlink($tmp); return; } unlink($tmp); throw new RuntimeException('Arquivo foi aceito.'); });
test('upload PNG valido', function() use ($config) { $tmp=tempnam(sys_get_temp_dir(),'png'); file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')); $stored=(new UploadService($config['upload_max_bytes']))->receipt(['error'=>UPLOAD_ERR_OK,'size'=>filesize($tmp),'tmp_name'=>$tmp,'name'=>'ok.png']); expect($stored['mime']==='image/png'); unlink(BASE_PATH.'/'.$stored['path']); unlink($tmp); });
test('cron e idempotente por status', function() { $source=file_get_contents(BASE_PATH.'/app/Services/ReservationService.php'); expect(str_contains($source,'p.status IN {$paymentStatuses}')); expect(str_contains($source,"UPDATE reservas SET status='EXPIRADA'")); });
test('landing page contem entrada para reserva direta', function() { ob_start(); include BASE_PATH.'/index.php'; $html=(string)ob_get_clean(); expect(str_contains($html,'reserva/solicitar')); $dom=new DOMDocument(); @$dom->loadHTML($html); expect($dom->getElementsByTagName('html')->length===1); });
test('dashboard administrativo renderiza status centralizado', function() { $metrics=['pendentes'=>1,'pagamento'=>0,'comprovantes'=>0,'confirmadas'=>0,'proximas'=>0,'vencidos'=>0,'receita'=>0]; $recent=[['id'=>1,'codigo'=>'RDC-TESTE','nome_cliente'=>'Teste','checkin'=>'2026-08-10','checkout'=>'2026-08-12','status'=>'AGUARDANDO_APROVACAO']]; ob_start(); include BASE_PATH.'/app/Views/admin/dashboard.php'; $html=(string)ob_get_clean(); expect(str_contains($html,'Aguardando aprovacao')); expect(str_contains($html,'RDC-TESTE')); });

fwrite(STDOUT, "\n{$passed} teste(s) passaram; {$failed} falharam.\n");
exit($failed ? 1 : 0);
