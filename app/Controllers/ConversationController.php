<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Repositories\ConversationRepository;
use Refugio\Services\AuthorizationService;
use Refugio\Services\ConversationService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class ConversationController
{
    private \PDO $db;
    private ConversationRepository $repository;
    private ConversationService $service;

    public function __construct(private array $config)
    {
    }

    public function index(): void
    {
        AuthorizationService::requirePermission('conversas.view'); $this->boot();
        $filters=array_intersect_key($_GET,array_flip(['q','status','prioridade','atendente_id','tag_id']));
        $result=$this->repository->paginate($filters,max(1,(int)($_GET['pagina']??1)));
        $selectedId=max(0,(int)($_GET['id']??($result['items'][0]['id']??0)));
        $conversation=$selectedId ? $this->repository->find($selectedId) : null;
        $messages=$conversation ? $this->repository->messages($selectedId) : [];
        $notes=$conversation ? $this->repository->notes($selectedId) : [];
        $assignedTags=$conversation ? $this->repository->assignedTagIds($selectedId) : [];
        $tags=$this->repository->tags(); $agents=$this->repository->agents(); $templates=$this->repository->templates();
        if ($conversation) $this->service->markRead($selectedId);
        require BASE_PATH . '/app/Views/admin/conversations.php';
    }

    public function action(int $id, string $action): never
    {
        AuthorizationService::requirePermission('conversas.reply'); $this->boot();
        try {
            Csrf::verify($_POST['_csrf']??null); $userId=(int)$_SESSION['admin_id'];
            match($action){
                'enviar'=> $this->service->sendText($id,(string)($_POST['texto']??''),$userId,!empty($_POST['respondendo_a_id'])?(int)$_POST['respondendo_a_id']:null),
                'template'=> $this->service->sendTemplate($id,(int)($_POST['template_id']??0),preg_split('/\r?\n/',(string)($_POST['parametros']??''))?:[],$userId),
                'midia'=> $this->service->sendMedia($id,$_FILES['arquivo']??[],(string)($_POST['legenda']??''),$userId),
                'atualizar'=> $this->update($id,$_POST,$userId),
                'nota'=> $this->service->addNote($id,(string)($_POST['nota']??''),$userId),
                'reenviar'=> $this->service->retry((int)($_POST['mensagem_id']??0),$userId),
                'criar-reserva'=> $this->createReservation($id,$_POST,$userId),
                default=>throw new RuntimeException('Acao de conversa invalida.'),
            };
            flash('success','Conversa atualizada.');
        }catch(Throwable $error){ flash('error',$error->getMessage()); }
        redirect(base_url('admin/conversas?id='.$id));
    }

    public function syncTemplates(): never
    {
        AuthorizationService::requirePermission('conversas.manage'); $this->boot();
        try { Csrf::verify($_POST['_csrf']??null); $count=$this->service->syncTemplates((int)$_SESSION['admin_id']); flash('success',"{$count} template(s) sincronizado(s)."); }
        catch(Throwable $error){ flash('error',$error->getMessage()); }
        redirect(base_url('admin/conversas'));
    }

    public function poll(int $id): never
    {
        AuthorizationService::requirePermission('conversas.view'); $this->boot();
        $conversation=$this->repository->find($id);
        if(!$conversation){$this->json(['error'=>'not found'],404);}
        $messages=array_map(fn(array $m):array=>$this->messageData($m),$this->repository->messages($id,max(0,(int)($_GET['after']??0)),100));
        $this->json(['messages'=>$messages,'conversation'=>['id'=>$id,'nao_lidas'=>(int)$conversation['nao_lidas'],'status'=>$conversation['status'],'janela_atendimento_ate'=>$conversation['janela_atendimento_ate']]]);
    }

    public function media(int $messageId): never
    {
        AuthorizationService::requirePermission('conversas.view'); $this->boot();
        $stmt=$this->db->prepare('SELECT media_path,media_mime,media_nome FROM mensagens WHERE id=?'); $stmt->execute([$messageId]); $media=$stmt->fetch();
        if(!$media||empty($media['media_path'])){http_response_code(404);exit;}
        $path=realpath(BASE_PATH.'/'.ltrim((string)$media['media_path'],'/')); $root=realpath(BASE_PATH.'/storage/conversas');
        if(!$path||!$root||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit;}
        header('Content-Type: '.($media['media_mime']?:'application/octet-stream')); header('Content-Length: '.filesize($path)); header('Cache-Control: private, no-store');
        $name=preg_replace('/[^A-Za-z0-9._-]/','-',(string)($media['media_nome']?:basename($path))); header('Content-Disposition: inline; filename="'.$name.'"'); readfile($path);exit;
    }

    private function update(int $id,array $input,int $userId): int { AuthorizationService::requirePermission('conversas.manage'); $this->service->update($id,$input,$userId); return $id; }
    private function createReservation(int $id,array $input,int $userId): int { AuthorizationService::requirePermission('reservas.create'); $r=$this->service->createReservation($id,$input,$userId); flash('success','Solicitacao '.$r['codigo'].' criada.'); return (int)$r['id']; }

    private function messageData(array $m): array
    {
        return ['id'=>(int)$m['id'],'direcao'=>$m['direcao'],'tipo'=>$m['tipo'],'texto'=>$m['texto'],'status'=>$m['status'],'erro'=>$m['erro'],'usuario_nome'=>$m['usuario_nome']??null,'created_at'=>$m['recebida_em']?:$m['enviada_em']?:$m['created_at'],'media_url'=>!empty($m['media_path'])?base_url('admin/conversas/midia/'.$m['id']):null,'media_mime'=>$m['media_mime'],'media_nome'=>$m['media_nome']];
    }

    private function boot():void{if(isset($this->db))return;$this->db=Database::connection();$this->repository=new ConversationRepository($this->db);$this->service=new ConversationService($this->db,$this->config);}
    private function json(array $payload,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}
