<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Repositories\ConversationRepository;
use Refugio\Repositories\CustomerRepository;
use Refugio\Services\ValidationException;
use RuntimeException;
use Throwable;

final class ConversationService
{
    private ConversationRepository $repository;

    public function __construct(private PDO $db, private array $config)
    {
        $this->repository = new ConversationRepository($db);
    }

    public static function freeTextAllowed(?string $windowUntil, ?DateTimeImmutable $now = null): bool
    {
        if (!$windowUntil) return false;
        return new DateTimeImmutable($windowUntil) >= ($now ?? new DateTimeImmutable());
    }

    public function sendText(int $conversationId, string $text, int $userId, ?int $replyToId = null): int
    {
        $conversation = $this->conversation($conversationId);
        $text = trim(strip_tags($text));
        if ($text === '' || mb_strlen($text) > 4096) throw new RuntimeException('A mensagem deve ter entre 1 e 4096 caracteres.');
        if (!self::freeTextAllowed($conversation['janela_atendimento_ate'])) throw new RuntimeException('A janela de 24 horas encerrou. Envie um template aprovado.');
        $replyExternalId = null;
        if ($replyToId) { $s=$this->db->prepare('SELECT external_message_id FROM mensagens WHERE id=? AND conversa_id=?'); $s->execute([$replyToId,$conversationId]); $replyExternalId=$s->fetchColumn() ?: null; }
        try {
            $externalId = (new WhatsAppService())->sendText((string) $conversation['telefone_normalizado'], $text, $replyExternalId);
            return $this->persistOutgoing($conversationId,$externalId,'TEXTO',$text,'ENVIADA',$userId,$replyToId,['type'=>'text','body'=>$text]);
        } catch (Throwable $error) {
            $this->persistOutgoing($conversationId,'local-' . bin2hex(random_bytes(16)),'TEXTO',$text,'FALHA',$userId,$replyToId,['type'=>'text','body'=>$text],$error->getMessage());
            throw $error;
        }
    }

    public function sendTemplate(int $conversationId, int $templateId, array $parameters, int $userId): int
    {
        $conversation = $this->conversation($conversationId);
        $stmt = $this->db->prepare("SELECT * FROM whatsapp_templates WHERE id=? AND status='APPROVED'"); $stmt->execute([$templateId]);
        $template = $stmt->fetch() ?: throw new RuntimeException('Template aprovado nao encontrado.');
        $parameters = array_values(array_filter(array_map(static fn($v) => mb_substr(trim(strip_tags((string) $v)), 0, 1024), $parameters), static fn($v) => $v !== ''));
        try {
            $externalId = (new WhatsAppService())->sendTemplate((string) $conversation['telefone_normalizado'], (string) $template['nome'], $parameters);
            return $this->persistOutgoing($conversationId,$externalId,'TEMPLATE','Template: ' . $template['nome'],'ENVIADA',$userId,null,['type'=>'template','name'=>$template['nome'],'language'=>$template['idioma'],'parameters'=>$parameters],null,(string)$template['nome'],(string)$template['idioma']);
        } catch (Throwable $error) {
            $this->persistOutgoing($conversationId,'local-' . bin2hex(random_bytes(16)),'TEMPLATE','Template: ' . $template['nome'],'FALHA',$userId,null,['type'=>'template','name'=>$template['nome'],'language'=>$template['idioma'],'parameters'=>$parameters],$error->getMessage(),(string)$template['nome'],(string)$template['idioma']);
            throw $error;
        }
    }

    public function sendMedia(int $conversationId, array $file, string $caption, int $userId): int
    {
        $conversation = $this->conversation($conversationId);
        if (!self::freeTextAllowed($conversation['janela_atendimento_ate'])) throw new RuntimeException('A janela de 24 horas encerrou. Use um template aprovado.');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new RuntimeException('Selecione um arquivo valido.');
        $max = max(1, (int) ($this->config['whatsapp_media_max_bytes'] ?? 20 * 1024 * 1024));
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $max) throw new RuntimeException('Arquivo vazio ou acima do limite permitido.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
        $types = ['image/jpeg'=>'image','image/png'=>'image','image/webp'=>'image','application/pdf'=>'document','text/plain'=>'document','audio/ogg'=>'audio','audio/mpeg'=>'audio','video/mp4'=>'video'];
        $type = $types[$mime] ?? null;
        if (!$type) throw new RuntimeException('Formato de arquivo nao permitido.');
        $safeName = mb_substr(preg_replace('/[^A-Za-z0-9._-]/', '-', basename((string) $file['name'])) ?: 'arquivo', 0, 200);
        $caption = mb_substr(trim(strip_tags($caption)), 0, 1024);
        try {
            $whatsapp = new WhatsAppService();
            $mediaId = $whatsapp->uploadMedia((string) $file['tmp_name'], $mime, $safeName);
            $externalId = $whatsapp->sendMedia((string) $conversation['telefone_normalizado'],$type,$mediaId,$caption ?: null,$safeName);
            $messageId=$this->persistOutgoing($conversationId,$externalId,strtoupper($type),$caption ?: '[' . $safeName . ']','ENVIADA',$userId,null,['type'=>$type,'media_id'=>$mediaId,'filename'=>$safeName]);
            $mediaPath=$this->storeOutgoingMedia((string)$file['tmp_name'],$mime);
            $this->db->prepare('UPDATE mensagens SET media_id=?,media_path=?,media_mime=?,media_nome=? WHERE id=?')->execute([$mediaId,$mediaPath,$mime,$safeName,$messageId]);
            return $messageId;
        } catch (Throwable $error) {
            $this->persistOutgoing($conversationId,'local-' . bin2hex(random_bytes(16)),strtoupper($type),$caption ?: '[' . $safeName . ']','FALHA',$userId,null,['type'=>$type,'filename'=>$safeName],$error->getMessage());
            throw $error;
        }
    }

    public function sendStoredDocument(int $conversationId, string $path, string $filename, string $caption, int $userId): int
    {
        $conversation = $this->conversation($conversationId);
        if (!self::freeTextAllowed($conversation['janela_atendimento_ate'])) {
            throw new RuntimeException('A janela de 24 horas encerrou. Baixe o PDF e envie-o manualmente ou use um template aprovado.');
        }
        $absolute = realpath($path);
        $storageRoot = realpath(BASE_PATH . '/storage/reservation-documents');
        if (!$absolute || !$storageRoot || !str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($absolute)) {
            throw new RuntimeException('Documento de reserva indisponível para envio.');
        }
        $size = filesize($absolute);
        $max = max(1, (int) ($this->config['whatsapp_media_max_bytes'] ?? 20 * 1024 * 1024));
        if ($size === false || $size < 1 || $size > $max || file_get_contents($absolute, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('O PDF é inválido ou excede o limite de mídia do WhatsApp.');
        }
        $safeName = mb_substr(preg_replace('/[^A-Za-z0-9._-]/', '-', basename($filename)) ?: 'pedido-reserva.pdf', 0, 200);
        $caption = mb_substr(trim(strip_tags($caption)), 0, 1024);
        try {
            $whatsapp = new WhatsAppService();
            $mediaId = $whatsapp->uploadMedia($absolute, 'application/pdf', $safeName);
            $externalId = $whatsapp->sendMedia(
                (string) $conversation['telefone_normalizado'],
                'document',
                $mediaId,
                $caption ?: null,
                $safeName
            );
            $messageId = $this->persistOutgoing(
                $conversationId,
                $externalId,
                'DOCUMENTO',
                $caption ?: '[' . $safeName . ']',
                'ENVIADA',
                $userId,
                null,
                ['type' => 'document', 'media_id' => $mediaId, 'filename' => $safeName]
            );
            $mediaPath = $this->storeOutgoingMedia($absolute, 'application/pdf');
            $this->db->prepare('UPDATE mensagens SET media_id=?,media_path=?,media_mime=?,media_nome=? WHERE id=?')
                ->execute([$mediaId, $mediaPath, 'application/pdf', $safeName, $messageId]);
            return $messageId;
        } catch (Throwable $error) {
            $this->persistOutgoing(
                $conversationId,
                'local-' . bin2hex(random_bytes(16)),
                'DOCUMENTO',
                $caption ?: '[' . $safeName . ']',
                'FALHA',
                $userId,
                null,
                ['type' => 'document', 'filename' => $safeName],
                $error->getMessage()
            );
            throw $error;
        }
    }

    public function update(int $id, array $input, int $userId): void
    {
        $before = $this->conversation($id);
        $statuses = ['NOVA','EM_ATENDIMENTO','AGUARDANDO_CLIENTE','AGUARDANDO_EQUIPE','CONVERTIDA','FINALIZADA','ARQUIVADA','SPAM'];
        $priorities = ['BAIXA','NORMAL','ALTA','URGENTE'];
        $status = in_array($input['status'] ?? '', $statuses, true) ? $input['status'] : $before['status'];
        $priority = in_array($input['prioridade'] ?? '', $priorities, true) ? $input['prioridade'] : $before['prioridade'];
        $agentId = !empty($input['atendente_id']) ? (int) $input['atendente_id'] : null;
        $clientId = !empty($input['cliente_id']) ? (int) $input['cliente_id'] : null;
        $reservationId = !empty($input['reserva_id']) ? (int) $input['reserva_id'] : null;
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE conversas SET status=?,prioridade=?,atendente_id=?,cliente_id=?,reserva_id=? WHERE id=?')->execute([$status,$priority,$agentId,$clientId,$reservationId,$id]);
            $this->db->prepare('DELETE FROM conversa_tag_vinculos WHERE conversa_id=?')->execute([$id]);
            $tagStmt = $this->db->prepare('INSERT IGNORE INTO conversa_tag_vinculos (conversa_id,tag_id,usuario_id) VALUES (?,?,?)');
            foreach (array_unique(array_map('intval', (array) ($input['tags'] ?? []))) as $tagId) if ($tagId > 0) $tagStmt->execute([$id,$tagId,$userId]);
            $this->db->commit();
            (new AuditService($this->db))->record('CONVERSAS','ATUALIZAR','conversas',$id,$before,['status'=>$status,'prioridade'=>$priority,'atendente_id'=>$agentId,'cliente_id'=>$clientId,'reserva_id'=>$reservationId]);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function retry(int $messageId,int $userId):void
    {
        $stmt=$this->db->prepare("SELECT m.*,c.telefone_normalizado,c.janela_atendimento_ate FROM mensagens m JOIN conversas c ON c.id=m.conversa_id WHERE m.id=? AND m.direcao='SAIDA' AND m.status='FALHA'");$stmt->execute([$messageId]);$message=$stmt->fetch()?:throw new RuntimeException('Mensagem com falha nao encontrada.');$payload=json_decode((string)$message['payload_json'],true)?:[];$whatsapp=new WhatsAppService();
        if($message['tipo']==='TEXTO'){if(!self::freeTextAllowed($message['janela_atendimento_ate']))throw new RuntimeException('A janela de 24 horas encerrou. Reenvie usando um template.');$external=$whatsapp->sendText((string)$message['telefone_normalizado'],(string)$message['texto']);}
        elseif($message['tipo']==='TEMPLATE'){$external=$whatsapp->sendTemplate((string)$message['telefone_normalizado'],(string)($payload['name']??$message['template_name']),is_array($payload['parameters']??null)?$payload['parameters']:[]);}
        else throw new RuntimeException('Reenvio automatico disponivel apenas para texto e template.');
        $this->db->prepare("UPDATE mensagens SET external_message_id=?,status='ENVIADA',erro=NULL,enviada_por_usuario_id=?,enviada_em=NOW() WHERE id=?")->execute([$external,$userId,$messageId]);(new AuditService($this->db))->record('CONVERSAS','REENVIAR_MENSAGEM','mensagens',$messageId,null,['external_message_id'=>$external]);
    }

    public function addNote(int $id, string $text, int $userId): void
    {
        $this->conversation($id); $text=mb_substr(trim(strip_tags($text)),0,2000); if ($text==='') throw new RuntimeException('Escreva a observacao interna.');
        $this->db->prepare('INSERT INTO conversa_notas (conversa_id,usuario_id,texto) VALUES (?,?,?)')->execute([$id,$userId,$text]);
        (new AuditService($this->db))->record('CONVERSAS','NOTA_INTERNA','conversas',$id,null,['tamanho'=>mb_strlen($text)]);
    }

    public function markRead(int $id): void { $this->db->prepare('UPDATE conversas SET nao_lidas=0 WHERE id=?')->execute([$id]); }

    public function syncTemplates(int $userId): int
    {
        $templates=(new WhatsAppService())->listTemplates(); $stmt=$this->db->prepare('INSERT INTO whatsapp_templates (external_id,nome,idioma,categoria,status,componentes_json,ultima_sincronizacao_em) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),categoria=VALUES(categoria),status=VALUES(status),componentes_json=VALUES(componentes_json),ultima_sincronizacao_em=NOW()');
        foreach($templates as $t) $stmt->execute([$t['id']??null,$t['name']??'',$t['language']??'pt_BR',$t['category']??null,$t['status']??'DESCONHECIDO',json_encode($t['components']??[],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        (new AuditService($this->db))->record('CONVERSAS','SINCRONIZAR_TEMPLATES','whatsapp_templates',null,null,['quantidade'=>count($templates)],[],$userId);
        return count($templates);
    }

    public function createReservation(int $conversationId, array $input, int $userId): array
    {
        $conversation=$this->conversation($conversationId);
        $input['nome'] = $input['nome'] ?? $conversation['nome_contato'] ?? 'Contato WhatsApp';
        $input['telefone'] = $conversation['telefone_normalizado'];
        $input['regras_aceitas']=$input['cancelamento_aceito']=$input['contato_autorizado']='1';
        $reservation=(new ReservationService($this->db,$this->config))->request($input,'conversation-' . $conversationId . '-' . bin2hex(random_bytes(16)));
        $this->db->prepare("UPDATE reservas SET origem='MANUAL' WHERE id=?")->execute([$reservation['id']]);
        $clientId=(new CustomerRepository($this->db))->syncFromReservation($reservation);
        $this->db->prepare("UPDATE conversas SET reserva_id=?,cliente_id=?,status='CONVERTIDA' WHERE id=?")->execute([$reservation['id'],$clientId,$conversationId]);
        if (!empty($conversation['lead_id'])) $this->db->prepare("UPDATE leads SET cliente_id=?,status='CONVERTIDO',convertido_em=NOW() WHERE id=?")->execute([$clientId,$conversation['lead_id']]);
        (new AuditService($this->db))->record('CONVERSAS','CRIAR_RESERVA','reservas',$reservation['id'],null,['conversa_id'=>$conversationId],[],$userId);
        return $reservation;
    }

    private function persistOutgoing(int $conversationId,string $externalId,string $type,string $text,string $status,int $userId,?int $replyToId,array $payload,?string $error=null,?string $templateName=null,?string $templateLanguage=null): int
    {
        $stmt=$this->db->prepare("INSERT INTO mensagens (conversa_id,external_message_id,direcao,tipo,texto,template_name,template_language,payload_json,status,erro,respondendo_a_id,enviada_por_usuario_id,enviada_em) VALUES (?,?,'SAIDA',?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$conversationId,$externalId,$type,$text,$templateName,$templateLanguage,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,$error ? mb_substr($error,0,2000) : null,$replyToId,$userId]);
        $id=(int)$this->db->lastInsertId();
        $this->db->prepare("UPDATE conversas SET ultima_mensagem_em=NOW(),ultima_mensagem_preview=?,status=IF(status='NOVA','EM_ATENDIMENTO',status) WHERE id=?")->execute([mb_substr($text,0,255),$conversationId]);
        (new AuditService($this->db))->record('CONVERSAS','MENSAGEM_ENVIADA','mensagens',$id,null,['conversa_id'=>$conversationId,'tipo'=>$type,'status'=>$status]);
        return $id;
    }

    private function storeOutgoingMedia(string $temporaryPath,string $mime):?string
    {
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','text/plain'=>'txt','audio/ogg'=>'ogg','audio/mpeg'=>'mp3','video/mp4'=>'mp4'];
        $extension=$extensions[$mime]??null;
        if($extension===null)return null;
        $directory=BASE_PATH.'/storage/conversas/'.date('Y/m');
        if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))return null;
        $absolute=$directory.'/'.bin2hex(random_bytes(24)).'.'.$extension;
        if(!move_uploaded_file($temporaryPath,$absolute)&&!copy($temporaryPath,$absolute))return null;
        @chmod($absolute,0640);
        return str_replace('\\','/',substr($absolute,strlen(BASE_PATH)+1));
    }

    private function conversation(int $id): array { return $this->repository->find($id) ?? throw new RuntimeException('Conversa nao encontrada.'); }
}
