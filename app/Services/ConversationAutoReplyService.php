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
        return in_array($conversation['status'] ?? '', ['NOVA', 'EM_ATENDIMENTO', 'AGUARDANDO_CLIENTE'], true)
            && ConversationService::freeTextAllowed($conversation['janela_atendimento_ate'] ?? null);
    }

    public function process(int $conversationId, int $messageId): void
    {
        if (!Env::bool('OPENAI_CONVERSATIONS_AUTO_REPLY', true) || !Env::bool('OPENAI_CONVERSATIONS_ENABLED', true) || trim(Env::get('OPENAI_API_KEY')) === '') return;
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
            if ($result['needs_human_review']) {
                $this->db->prepare("UPDATE conversas SET status='AGUARDANDO_EQUIPE',prioridade='ALTA' WHERE id=?")->execute([$conversationId]);
                $this->db->prepare('INSERT INTO conversa_notas (conversa_id,usuario_id,texto) VALUES (?,NULL,?)')->execute([$conversationId, 'IA: ' . $result['review_reason'] . "\nSugestão: " . $result['draft']]);
                $this->record($conversationId, $messageId, 'HUMAN_REVIEW');
                return;
            }
            // Registre antes do envio. Um timeout de rede é ambíguo: não reenviar automaticamente.
            $this->record($conversationId, $messageId, 'SENDING');
            try {
                (new ConversationService($this->db, $this->config))->sendText($conversationId, $result['draft'], null, $messageId);
                $this->db->prepare("UPDATE conversation_auto_replies SET status='SENT' WHERE message_id=?")->execute([$messageId]);
            } catch (Throwable $error) {
                $this->db->prepare("UPDATE conversation_auto_replies SET status='REVIEW_REQUIRED',error_message=? WHERE message_id=?")->execute([mb_substr($error->getMessage(), 0, 2000), $messageId]);
                $this->db->prepare("UPDATE conversas SET status='AGUARDANDO_EQUIPE',prioridade='ALTA' WHERE id=?")->execute([$conversationId]);
                throw $error;
            }
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
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
