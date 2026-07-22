<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Repositories\CustomerRepository;
use Refugio\Services\AuditService;
use Refugio\Services\AuthorizationService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class CustomerController
{
    private \PDO$db;private CustomerRepository$repository;
    public function index():void{AuthorizationService::requirePermission('clientes.view');$this->boot();$q=trim((string)($_GET['q']??''));$result=$this->repository->paginate($q,max(1,(int)($_GET['pagina']??1)));require BASE_PATH.'/app/Views/admin/customers.php';}
    public function detail(int$id):void{AuthorizationService::requirePermission('clientes.view');$this->boot();$customer=$this->repository->find($id)??throw new RuntimeException('Cliente nao encontrado.');$reservations=$this->repository->reservations($id);$s=$this->db->prepare('SELECT * FROM conversas WHERE cliente_id=? ORDER BY ultima_mensagem_em DESC');$s->execute([$id]);$conversations=$s->fetchAll();$s=$this->db->prepare('SELECT * FROM marketing_atribuicoes WHERE cliente_id=? ORDER BY primeiro_contato_em');$s->execute([$id]);$attributions=$s->fetchAll();require BASE_PATH.'/app/Views/admin/customer-detail.php';}
    public function action(int$id,string$action):never{AuthorizationService::requirePermission($action==='anonimizar'?'clientes.anonymize':'clientes.merge');$this->boot();try{Csrf::verify($_POST['_csrf']??null);if($action==='mesclar'){$target=(int)($_POST['destino_id']??0);$this->repository->merge($id,$target);(new AuditService($this->db))->record('CLIENTES','MESCLAR','clientes',$id,null,['destino_id'=>$target]);flash('success','Contatos mesclados.');redirect(base_url('admin/clientes/'.$target));}elseif($action==='anonimizar'){$before=$this->repository->find($id);$this->repository->anonymize($id);(new AuditService($this->db))->record('CLIENTES','ANONIMIZAR','clientes',$id,['status'=>$before['status']??null],['status'=>'ANONIMIZADO']);flash('success','Dados pessoais anonimizados. Os vinculos contabeis foram preservados.');}else throw new RuntimeException('Acao de cliente invalida.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect(base_url('admin/clientes/'.$id));}
    public function export(int$id):never{AuthorizationService::requirePermission('clientes.export');$this->boot();$customer=$this->repository->find($id)??throw new RuntimeException('Cliente nao encontrado.');$data=['cliente'=>$customer,'reservas'=>$this->repository->reservations($id)];$s=$this->db->prepare('SELECT id,canal,status,primeira_mensagem_em,ultima_mensagem_em FROM conversas WHERE cliente_id=?');$s->execute([$id]);$data['conversas']=$s->fetchAll();$s=$this->db->prepare('SELECT m.direcao,m.tipo,m.texto,m.status,m.enviada_em,m.recebida_em FROM mensagens m JOIN conversas c ON c.id=m.conversa_id WHERE c.cliente_id=? ORDER BY m.created_at');$s->execute([$id]);$data['mensagens']=$s->fetchAll();$s=$this->db->prepare('SELECT provider,utm_source,utm_medium,utm_campaign,utm_content,utm_term,primeiro_contato_em,ultimo_contato_em FROM marketing_atribuicoes WHERE cliente_id=?');$s->execute([$id]);$data['atribuicoes']=$s->fetchAll();header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="cliente-'.$id.'.json"');header('Cache-Control: private, no-store');echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
    private function boot():void{if(isset($this->db))return;$this->db=Database::connection();$this->repository=new CustomerRepository($this->db);}
}
