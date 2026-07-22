<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use Refugio\Config\Database;
use Refugio\Repositories\FinancialRepository;
use Refugio\Services\AuthorizationService;
use Refugio\Services\FinancialService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class FinancialController
{
    private \PDO $db; private FinancialRepository $repository; private FinancialService $service;
    public function __construct(private array $config) {}

    public function index():void
    {
        AuthorizationService::requirePermission('financeiro.view');$this->boot();[$start,$end]=$this->period();$tab=(string)($_GET['tab']??'dashboard');
        $metrics=$this->repository->dashboard($start,$end);$receivables=$this->repository->receivables($start,$end);$receipts=$this->repository->receipts($start,$end);$expenses=$this->repository->expenses($start,$end);$expensePayments=$this->repository->expensePayments($start,$end);$movements=$this->repository->movements($start,$end);$cashFlow=$this->repository->cashFlow($start,$end);$accounts=$this->repository->accounts();$categories=$this->repository->categories();$suppliers=$this->repository->suppliers();$recurrences=$this->repository->recurrences();$deposits=$this->repository->deposits();$reconciliations=$this->repository->reconciliations();
        require BASE_PATH.'/app/Views/admin/financial.php';
    }

    public function action(string $action):never
    {
        AuthorizationService::requirePermission('financeiro.manage');$this->boot();try{Csrf::verify($_POST['_csrf']??null);$user=(int)$_SESSION['admin_id'];match($action){
            'conta-criar'=>$this->service->createAccount($_POST,$user),'categoria-criar'=>$this->service->createCategory($_POST,$user),'fornecedor-criar'=>$this->service->createSupplier($_POST,$user),
            'recebivel-criar'=>$this->service->createReceivable($_POST,$user),'receber'=>$this->service->receive((int)($_POST['id']??0),$_POST,$user),'recebivel-cancelar'=>$this->service->cancelReceivable((int)($_POST['id']??0),(string)($_POST['motivo']??''),$user),'recebimento-estornar'=>$this->service->refundReceipt((int)($_POST['id']??0),(string)($_POST['valor']??''),(string)($_POST['motivo']??''),$user),
            'despesa-criar'=>$this->service->createExpense($_POST,$user),'despesa-pagar'=>$this->service->payExpense((int)($_POST['id']??0),$_POST,$user),'despesa-cancelar'=>$this->service->cancelExpense((int)($_POST['id']??0),(string)($_POST['motivo']??''),$user),'despesa-estornar'=>$this->service->refundExpensePayment((int)($_POST['id']??0),(string)($_POST['valor']??''),(string)($_POST['motivo']??''),$user),
            'recorrencia-criar'=>$this->service->createRecurrence($_POST,$user),'recorrencias-gerar'=>$this->service->generateRecurrences(),
            'conciliar'=>$this->service->reconcile($_POST,$user),'caucao-criar'=>$this->service->createDeposit($_POST,$user),'caucao-atualizar'=>$this->service->updateDeposit((int)($_POST['id']??0),(string)($_POST['caucao_acao']??''),(string)($_POST['valor']??'0'),(string)($_POST['motivo']??''),$user),default=>throw new RuntimeException('Acao financeira invalida.')};flash('success','Operacao financeira concluida.');}catch(Throwable$error){flash('error',$error->getMessage());}
        $tab=(string)($_POST['_tab']??'dashboard');redirect(base_url('admin/financeiro?tab='.rawurlencode($tab)));
    }

    public function export():never
    {
        AuthorizationService::requirePermission('financeiro.export');$this->boot();[$start,$end]=$this->period();$type=(string)($_GET['tipo']??'movimentos');$rows=match($type){'recebiveis'=>$this->repository->receivables($start,$end),'despesas'=>$this->repository->expenses($start,$end),'fluxo'=>$this->repository->cashFlow($start,$end),default=>$this->repository->movements($start,$end)};
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="financeiro-'.$type.'-'.$start.'-'.$end.'.csv"');header('Cache-Control: private, no-store');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");if($rows){fputcsv($out,array_keys($rows[0]),';');foreach($rows as$row)fputcsv($out,array_map(self::csvValue(...),$row),';');}fclose($out);exit;
    }

    private static function csvValue(mixed$value):string{if($value===null)return'';$v=(string)$value;return preg_match('/^[=+\-@]/',$v)?"'".$v:$v;}
    private function period():array{$start=(string)($_GET['inicio']??date('Y-m-01'));$end=(string)($_GET['fim']??date('Y-m-t'));$a=DateTimeImmutable::createFromFormat('!Y-m-d',$start);$b=DateTimeImmutable::createFromFormat('!Y-m-d',$end);if(!$a||!$b||$b<$a||$b>$a->modify('+2 years'))return[date('Y-m-01'),date('Y-m-t')];return[$start,$end];}
    private function boot():void{if(isset($this->db))return;$this->db=Database::connection();$this->repository=new FinancialRepository($this->db);$this->service=new FinancialService($this->db);}
}
