<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Repositories\CustomerRepository;
use Refugio\Support\Env;
use RuntimeException;
use Throwable;

final class WhatsAppWebhookService
{
    public function __construct(private PDO $db)
    {
    }

    public static function validSignature(string $rawBody, string $signature, ?string $secret = null): bool
    {
        $secret ??= Env::get('WHATSAPP_APP_SECRET');
        if ($secret === '' || !str_starts_with($signature, 'sha256=')) return false;
        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function accept(string $rawBody): int
    {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['object'] ?? '') !== 'whatsapp_business_account') throw new RuntimeException('Evento do WhatsApp invalido.');
        $hash = hash('sha256', $rawBody);
        $stmt = $this->db->prepare("INSERT INTO whatsapp_webhook_eventos (event_hash,object_type,payload_json,assinatura_valida) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$hash, (string) ($payload['object'] ?? ''), $rawBody]);
        $eventId = (int) $this->db->lastInsertId();
        (new JobQueueService($this->db))->enqueue('WHATSAPP_WEBHOOK', ['event_id' => $eventId], 'whatsapp-webhook:' . $hash, 10, 8);
        return $eventId;
    }

    public function process(int $eventId): void
    {
        $this->db->prepare("UPDATE whatsapp_webhook_eventos SET status='PROCESSANDO' WHERE id=? AND status IN ('RECEBIDO','FALHOU')")->execute([$eventId]);
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_webhook_eventos WHERE id=?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch() ?: throw new RuntimeException('Evento de webhook nao encontrado.');
        if ($event['status'] === 'PROCESSADO' || $event['status'] === 'IGNORADO') return;
        try {
            $payload = json_decode((string) $event['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            foreach (($payload['entry'] ?? []) as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    if (($change['field'] ?? '') !== 'messages' || !is_array($change['value'] ?? null)) continue;
                    $this->processValue($change['value']);
                }
            }
            $this->db->prepare("UPDATE whatsapp_webhook_eventos SET status='PROCESSADO',processado_em=NOW(),erro=NULL WHERE id=?")->execute([$eventId]);
        } catch (Throwable $error) {
            $this->db->prepare("UPDATE whatsapp_webhook_eventos SET status='FALHOU',erro=? WHERE id=?")->execute([mb_substr($error->getMessage(), 0, 4000), $eventId]);
            throw $error;
        }
    }

    public function downloadMedia(int $messageId): void
    {
        $stmt = $this->db->prepare('SELECT id,media_id,media_path,media_mime FROM mensagens WHERE id=? AND media_id IS NOT NULL');
        $stmt->execute([$messageId]);
        $message = $stmt->fetch() ?: throw new RuntimeException('Mensagem de midia nao encontrada.');
        if (!empty($message['media_path'])) return;
        $media = (new WhatsAppService())->downloadMedia((string) $message['media_id']);
        $max = max(1, Env::int('WHATSAPP_MEDIA_MAX_MB', 20)) * 1024 * 1024;
        if ((int) $media['size'] > $max) throw new RuntimeException('Midia recebida excede o limite configurado.');
        $mime = strtolower(trim(explode(';', (string) $media['mime'])[0]));
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','text/plain'=>'txt','application/zip'=>'zip','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx','application/vnd.ms-powerpoint'=>'ppt','application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx','audio/ogg'=>'ogg','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/amr'=>'amr','video/mp4'=>'mp4','video/3gpp'=>'3gp'];
        $extension = $extensions[$mime] ?? null;
        if ($extension === null) throw new RuntimeException('Tipo de midia recebido nao permitido.');
        $directory = BASE_PATH . '/storage/conversas/' . date('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Nao foi possivel criar o diretorio de midias.');
        $filename = hash('sha256', (string) $message['media_id']) . '.' . $extension;
        $absolute = $directory . '/' . $filename;
        if (file_put_contents($absolute, $media['content'], LOCK_EX) === false) throw new RuntimeException('Nao foi possivel armazenar a midia.');
        $relative = str_replace('\\', '/', substr($absolute, strlen(BASE_PATH) + 1));
        $this->db->prepare('UPDATE mensagens SET media_path=?,media_mime=? WHERE id=?')->execute([$relative, $mime, $messageId]);
    }

    private function processValue(array $value): void
    {
        $contacts = [];
        foreach (($value['contacts'] ?? []) as $contact) {
            if (!empty($contact['wa_id'])) $contacts[(string) $contact['wa_id']] = (string) ($contact['profile']['name'] ?? '');
        }
        foreach (($value['messages'] ?? []) as $message) {
            if (is_array($message)) $this->processMessage($message, $contacts);
        }
        foreach (($value['statuses'] ?? []) as $status) {
            if (is_array($status)) $this->processStatus($status);
        }
    }

    private function processMessage(array $message, array $contacts): void
    {
        $externalId = mb_substr((string) ($message['id'] ?? ''), 0, 190);
        $from = (string) ($message['from'] ?? '');
        if ($externalId === '' || $from === '') return;
        $normalized = CustomerRepository::normalizePhone($from) ?? $from;
        $customers = new CustomerRepository($this->db);
        $customer = $customers->findByPhone($normalized);
        $name = $contacts[$from] ?? null;
        $leadId = $customer ? null : $this->resolveLead($customers,$normalized,$name,$from,$message);
        $reservationId = $this->relatedReservation($customer ? (int) $customer['id'] : null, $normalized);
        $conversationId = $this->upsertConversation($normalized, $from, $name, $customer ? (int) $customer['id'] : null, $leadId, $reservationId);
        $this->ensureWhatsAppAttribution($conversationId, $customer ? (int) $customer['id'] : null, $leadId, $reservationId);
        [$type,$text,$mediaId,$mime,$filename] = self::messageData($message);
        $timestamp = isset($message['timestamp']) && ctype_digit((string) $message['timestamp']) ? date('Y-m-d H:i:s', (int) $message['timestamp']) : date('Y-m-d H:i:s');
        $replyId = null;
        if (!empty($message['context']['id'])) {
            $reply = $this->db->prepare('SELECT id FROM mensagens WHERE external_message_id=?');
            $reply->execute([(string) $message['context']['id']]);
            $replyId = $reply->fetchColumn() ?: null;
        }
        $stmt = $this->db->prepare("INSERT IGNORE INTO mensagens (conversa_id,external_message_id,direcao,tipo,texto,media_id,media_mime,media_nome,payload_json,status,respondendo_a_id,recebida_em) VALUES (?,?,'ENTRADA',?,?,?,?,?,?, 'RECEBIDA',?,?)");
        $stmt->execute([$conversationId,$externalId,$type,$text,$mediaId,$mime,$filename,json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$replyId,$timestamp]);
        if ($stmt->rowCount() > 0) {
            $messageId = (int) $this->db->lastInsertId();
            $preview = mb_substr($text ?: '[' . ucfirst(strtolower($type)) . ']', 0, 255);
            $this->db->prepare("UPDATE conversas SET primeira_mensagem_em=COALESCE(primeira_mensagem_em,?),ultima_mensagem_em=?,ultima_mensagem_preview=?,nao_lidas=nao_lidas+1,janela_atendimento_ate=DATE_ADD(?,INTERVAL 24 HOUR),status=IF(status IN ('FINALIZADA','ARQUIVADA'),'NOVA',status) WHERE id=?")
                ->execute([$timestamp,$timestamp,$preview,$timestamp,$conversationId]);
            if ($mediaId) (new JobQueueService($this->db))->enqueue('WHATSAPP_MEDIA', ['message_id' => $messageId], 'whatsapp-media:' . $externalId, 30, 6);
            (new AuditService($this->db))->record('CONVERSAS','MENSAGEM_RECEBIDA','mensagens',$messageId,null,['conversa_id'=>$conversationId,'tipo'=>$type]);
        }
    }

    private function processStatus(array $status): void
    {
        $externalId = (string) ($status['id'] ?? '');
        $incoming = strtolower((string) ($status['status'] ?? ''));
        $mapped = ['sent'=>'ENVIADA','delivered'=>'ENTREGUE','read'=>'LIDA','failed'=>'FALHA'][$incoming] ?? null;
        if ($externalId === '' || $mapped === null) return;
        $timestamp = isset($status['timestamp']) && ctype_digit((string) $status['timestamp']) ? date('Y-m-d H:i:s', (int) $status['timestamp']) : date('Y-m-d H:i:s');
        $error = !empty($status['errors']) ? json_encode(AuditService::sanitize(['errors'=>$status['errors']]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
        $stmt = $this->db->prepare("UPDATE mensagens SET status=CASE WHEN ?='FALHA' THEN 'FALHA' WHEN FIELD(?, 'PENDENTE','ENVIADA','ENTREGUE','LIDA')>FIELD(status,'PENDENTE','ENVIADA','ENTREGUE','LIDA') THEN ? ELSE status END,enviada_em=IF(?='ENVIADA',COALESCE(enviada_em,?),enviada_em),entregue_em=IF(?='ENTREGUE',COALESCE(entregue_em,?),entregue_em),lida_em=IF(?='LIDA',COALESCE(lida_em,?),lida_em),erro=COALESCE(?,erro) WHERE external_message_id=?");
        $stmt->execute([$mapped,$mapped,$mapped,$mapped,$timestamp,$mapped,$timestamp,$mapped,$timestamp,$error,$externalId]);
    }

    private function upsertConversation(string $phone, string $waId, ?string $name, ?int $clientId, ?int $leadId, ?int $reservationId): int
    {
        $stmt = $this->db->prepare("INSERT INTO conversas (telefone,telefone_normalizado,wa_id,nome_contato,cliente_id,lead_id,reserva_id,origem) VALUES (?,?,?,?,?,?,?,'WHATSAPP') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),telefone=VALUES(telefone),wa_id=VALUES(wa_id),nome_contato=COALESCE(NULLIF(VALUES(nome_contato),''),nome_contato),cliente_id=COALESCE(VALUES(cliente_id),cliente_id),lead_id=COALESCE(VALUES(lead_id),lead_id),reserva_id=COALESCE(VALUES(reserva_id),reserva_id)");
        $stmt->execute([$phone,$phone,$waId,$name,$clientId,$leadId,$reservationId]);
        return (int) $this->db->lastInsertId();
    }

    private function ensureWhatsAppAttribution(int $conversationId, ?int $clientId, ?int $leadId, ?int $reservationId): void
    {
        $check = $this->db->prepare('SELECT id FROM marketing_atribuicoes WHERE conversa_id=? OR (? IS NOT NULL AND lead_id=?) OR (? IS NOT NULL AND reserva_id=?) ORDER BY conversa_id IS NOT NULL DESC,lead_id IS NOT NULL DESC LIMIT 1');
        $check->execute([$conversationId,$leadId,$leadId,$reservationId,$reservationId]);
        $existing=$check->fetchColumn();
        if($existing){$this->db->prepare('UPDATE marketing_atribuicoes SET conversa_id=?,cliente_id=COALESCE(?,cliente_id),reserva_id=COALESCE(?,reserva_id),ultimo_contato_em=NOW() WHERE id=?')->execute([$conversationId,$clientId,$reservationId,$existing]);return;}
        $touch = json_encode(['utm_source'=>'whatsapp','utm_medium'=>'organic_conversation','captured_at'=>date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare("INSERT INTO marketing_atribuicoes (lead_id,cliente_id,reserva_id,conversa_id,provider,utm_source,utm_medium,first_touch_json,last_touch_json,primeiro_contato_em,ultimo_contato_em,confianca) VALUES (?,?,?,?,'DIRETO','whatsapp','organic_conversation',?,?,NOW(),NOW(),'INDICATIVA')");
        $stmt->execute([$leadId,$clientId,$reservationId,$conversationId,$touch,$touch]);
    }

    private function resolveLead(CustomerRepository $customers,string$phone,?string$name,string$waId,array$message):int
    {
        $existing=$this->db->prepare("SELECT id FROM leads WHERE canal='WHATSAPP' AND telefone_normalizado=? LIMIT 1");$existing->execute([$phone]);$id=$existing->fetchColumn();if($id){$this->db->prepare('UPDATE leads SET nome=COALESCE(NULLIF(?,\'\'),nome),ultimo_contato_em=NOW() WHERE id=?')->execute([$name,$id]);return(int)$id;}
        $text=(string)($message['text']['body']??'');if(preg_match('/\bREF-([A-F0-9]{8,16})\b/i',$text,$match)){$reference=strtoupper($match[1]);$referenceStmt=$this->db->prepare("SELECT id FROM leads WHERE canal='WHATSAPP' AND JSON_UNQUOTE(JSON_EXTRACT(dados_json,'$.reference'))=? LIMIT 1");$referenceStmt->execute([$reference]);$referenced=$referenceStmt->fetchColumn();if($referenced){$this->db->prepare('UPDATE leads SET nome=COALESCE(NULLIF(?,\'\'),nome),telefone=?,telefone_normalizado=?,ultimo_contato_em=NOW(),dados_json=JSON_SET(COALESCE(dados_json,JSON_OBJECT()),\'$.wa_id\',?) WHERE id=?')->execute([$name,$phone,$phone,$waId,$referenced]);return(int)$referenced;}}
        return$customers->ensureLead('WHATSAPP',$phone,$name,'WHATSAPP',['wa_id'=>$waId]);
    }

    private function relatedReservation(?int $clientId, string $phone): ?int
    {
        if ($clientId) {
            $stmt = $this->db->prepare('SELECT r.id FROM reserva_contatos rc JOIN reservas r ON r.id=rc.reserva_id WHERE rc.cliente_id=? ORDER BY r.created_at DESC LIMIT 1');
            $stmt->execute([$clientId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM reservas WHERE telefone=? ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$phone]);
        }
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function messageData(array $message): array
    {
        $sourceType = strtolower((string) ($message['type'] ?? 'unknown'));
        $map = ['text'=>'TEXTO','image'=>'IMAGEM','document'=>'DOCUMENTO','audio'=>'AUDIO','video'=>'VIDEO','location'=>'LOCALIZACAO','contacts'=>'CONTATO','button'=>'BOTAO','interactive'=>'INTERATIVA','sticker'=>'STICKER'];
        $type = $map[$sourceType] ?? 'DESCONHECIDA';
        $text = null; $mediaId = null; $mime = null; $filename = null;
        if ($sourceType === 'text') $text = (string) ($message['text']['body'] ?? '');
        elseif (in_array($sourceType, ['image','document','audio','video','sticker'], true)) {
            $part = $message[$sourceType] ?? [];
            $mediaId = $part['id'] ?? null; $mime = $part['mime_type'] ?? null; $filename = $part['filename'] ?? null; $text = $part['caption'] ?? null;
        } elseif ($sourceType === 'location') $text = trim((string) ($message['location']['name'] ?? '') . ' ' . (string) ($message['location']['address'] ?? ''));
        elseif ($sourceType === 'contacts') $text = (string) ($message['contacts'][0]['name']['formatted_name'] ?? 'Contato compartilhado');
        elseif ($sourceType === 'button') $text = (string) ($message['button']['text'] ?? $message['button']['payload'] ?? '');
        elseif ($sourceType === 'interactive') $text = (string) ($message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? 'Resposta interativa');
        return [$type,$text ? mb_substr($text, 0, 10000) : null,$mediaId ? (string) $mediaId : null,$mime ? (string) $mime : null,$filename ? mb_substr((string) $filename, 0, 255) : null];
    }
}
