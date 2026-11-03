<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Repositories\ConversationRepository;
use Refugio\Support\Env;
use Throwable;

final class ConversationAutoReplyService
{
    public function __construct(private PDO $db, private array $config) {}

    public static function canReply(array $conversation): bool
    {
        return empty($conversation['atendente_id']) && in_array($conversation['status'] ?? '', ['NOVA', 'EM_ATENDIMENTO', 'AGUARDANDO_CLIENTE'], true)
            && ConversationService::freeTextAllowed($conversation['janela_atendimento_ate'] ?? null);
    }

    public function process(int $conversationId, int $messageId): void
    {
        if (!Env::bool('OPENAI_CONVERSATIONS_AUTO_REPLY', true) || !Env::bool('OPENAI_CONVERSATIONS_ENABLED', true)) return;
        if (trim(Env::get('OPENAI_API_KEY')) === '') {
            throw new \RuntimeException('Resposta automática indisponível: configure OPENAI_API_KEY no ambiente do worker.');
        }
        $lock = 'conversation-ai:' . $conversationId;
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        if ((int) $stmt->fetchColumn() !== 1) throw new \RuntimeException('Conversa em processamento.');
        try {
            if (!$this->isCurrent($conversationId, $messageId)) return;
            $exists = $this->db->prepare('SELECT status FROM conversation_auto_replies WHERE message_id=?');
            $exists->execute([$messageId]);
            if ($exists->fetchColumn() !== false) return;
            $result = (new OpenAiConversationReplyService($this->db, $this->config))->suggest($conversationId, null);
            // Releia após a chamada de rede: o hóspede ou a equipe pode ter escrito.
            if (!$this->isCurrent($conversationId, $messageId)) return;
            $tools = $result['tool_results'] ?? [];
            $calendarFailed = self::calendarFailed($tools);
            $previous = $this->db->prepare("SELECT error_message FROM conversation_auto_replies WHERE conversation_id=? AND error_message LIKE 'CALENDAR:%' ORDER BY message_id DESC LIMIT 1");
            $previous->execute([$conversationId]);
            $lastCalendar = (string) $previous->fetchColumn();
            $handoff = $calendarFailed && str_starts_with($lastCalendar, 'CALENDAR:FAILED');
            // Tool errors must not be converted into an endless apology by the model.
            foreach ($tools as $tool) {
                if (($tool['result']['ok'] ?? true) === false) {
                    $result['needs_human_review'] = true;
                    $result['review_reason'] = 'Falha ao consultar dados da reserva: ' . ($tool['result']['error'] ?? 'erro de ferramenta');
                }
            }
            if ($handoff) {
                $result['needs_human_review'] = true;
                $result['review_reason'] = 'Falha persistente na consulta do calendário. Confirmar datas e orçamento com o hóspede.';
            }
            if ($result['needs_human_review']) {
                $this->db->beginTransaction();
                try {
                    $this->db->prepare("UPDATE conversas SET status='AGUARDANDO_EQUIPE',prioridade='ALTA' WHERE id=?")->execute([$conversationId]);
                    $this->db->prepare('INSERT INTO conversa_notas (conversa_id,usuario_id,texto) VALUES (?,NULL,?)')->execute([$conversationId, 'IA: ' . $result['review_reason'] . "\nSugestão: " . $result['draft']]);
                    $history = (new ConversationRepository($this->db))->messages($conversationId, 0, 40);
                    $summary = "Solicitação preservada — consultas: " . json_encode(array_column($tools, 'request'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    foreach ($history as $message) {
                        if ($message['direcao'] === 'ENTRADA') $summary .= "\nHóspede: " . mb_substr((string) ($message['texto'] ?? ''), 0, 500);
                    }
                    $this->db->prepare('INSERT INTO conversa_notas (conversa_id,usuario_id,texto) VALUES (?,NULL,?)')->execute([$conversationId, $summary]);
                    $this->record($conversationId, $messageId, 'HUMAN_REVIEW');
                    $this->db->commit();
                } catch (Throwable $error) {
                    if ($this->db->inTransaction()) $this->db->rollBack();
                    throw $error;
                }
                // One acknowledgment only; status already prevents new automatic replies.
                if ($handoff) {
                    (new ConversationService($this->db, $this->config))->sendText($conversationId, 'Não consegui confirmar as datas agora. Registrei sua solicitação com as informações que você já enviou e encaminhei à nossa equipe para verificar disponibilidade e valor. Não precisa repetir os dados.', null, $messageId);
                }
                return;
            }
            // Registre antes do envio. Um timeout de rede é ambíguo: não reenviar automaticamente.
            $this->record($conversationId, $messageId, 'SENDING');
            try {
                (new ConversationService($this->db, $this->config))->sendText($conversationId, $result['draft'], null, $messageId);
                $marker = $tools === [] ? null : ('CALENDAR:' . ($calendarFailed ? 'FAILED' : 'OK'));
                $this->db->prepare("UPDATE conversation_auto_replies SET status='SENT',error_message=? WHERE message_id=?")->execute([$marker, $messageId]);
            } catch (Throwable $error) {
                $this->db->prepare("UPDATE conversation_auto_replies SET status='REVIEW_REQUIRED',error_message=? WHERE message_id=?")->execute([mb_substr($error->getMessage(), 0, 2000), $messageId]);
                $this->db->prepare("UPDATE conversas SET status='AGUARDANDO_EQUIPE',prioridade='ALTA' WHERE id=?")->execute([$conversationId]);
                throw $error;
            }
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
    }

    public static function calendarFailed(array $tools): bool
    {
        foreach ($tools as $tool) {
            if (($tool['result']['calendar_sync_ok'] ?? true) === false) return true;
        }
        return false;
    }

    private function isCurrent(int $conversationId, int $messageId): bool
    {
        $repository = new ConversationRepository($this->db);
        $conversation = $repository->find($conversationId);
        if (!$conversation || !self::canReply($conversation)) return false;
        $last = $repository->messages($conversationId, 0, 1)[0] ?? [];
        return (int) ($last['id'] ?? 0) === $messageId && ($last['direcao'] ?? '') === 'ENTRADA' && ($last['tipo'] ?? '') === 'TEXTO';
    }

    private function record(int $conversationId, int $messageId, string $status): void
    {
        $this->db->prepare('INSERT INTO conversation_auto_replies (message_id,conversation_id,status) VALUES (?,?,?)')->execute([$messageId, $conversationId, $status]);
    }
}
