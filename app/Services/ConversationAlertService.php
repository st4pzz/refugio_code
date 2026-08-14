<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Env;
use RuntimeException;

final class ConversationAlertService
{
    public function __construct(private PDO $db, private ?SmtpClient $mailer = null)
    {
        $this->mailer ??= new SmtpClient();
    }

    public function send(int $messageId): void
    {
        $stmt = $this->db->prepare("SELECT m.id,m.tipo,m.conversa_id,c.nome_contato,c.telefone
            FROM mensagens m
            JOIN conversas c ON c.id=m.conversa_id
            WHERE m.id=? AND m.direcao='ENTRADA'
              AND m.id=(SELECT first_message.id FROM mensagens first_message WHERE first_message.conversa_id=m.conversa_id AND first_message.direcao='ENTRADA' ORDER BY first_message.id LIMIT 1)
            LIMIT 1");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch() ?: throw new RuntimeException('Primeira mensagem da conversa nao encontrada para o alerta.');

        $recipient = Env::get('CONVERSATION_ALERT_EMAIL', 'refugiodocuscuzeiro@gmail.com');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('CONVERSATION_ALERT_EMAIL invalido.');
        }

        $contact = trim((string) ($message['nome_contato'] ?? '')) ?: 'Contato sem nome';
        $phone = trim((string) ($message['telefone'] ?? '')) ?: 'Telefone nao informado';
        $type = self::typeLabel((string) $message['tipo']);
        $url = base_url('admin/conversas?id=' . (int) $message['conversa_id']);
        $subject = '[Refugio] Nova conversa no WhatsApp - ' . $contact;
        $html = '<!doctype html><html><body style="margin:0;background:#f5f1eb;font-family:Arial,sans-serif;color:#4b4a39">'
            . '<div style="max-width:620px;margin:auto;padding:28px"><div style="background:#fff;border-radius:16px;padding:30px;border-top:5px solid #4b4a39">'
            . '<h2 style="margin-top:0">Nova conversa no WhatsApp</h2>'
            . '<p>Um novo contato iniciou uma conversa pelo WhatsApp.</p>'
            . '<p><strong>Contato:</strong> ' . e($contact) . '<br><strong>Telefone:</strong> ' . e($phone) . '<br><strong>Tipo:</strong> ' . e($type) . '</p>'
            . '<p><a href="' . e($url) . '" style="display:inline-block;background:#4b4a39;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">Abrir conversa</a></p>'
            . '<p style="margin-top:28px;font-size:13px;color:#6b6a59">Refugio do Cuscuzeiro - Analandia/SP</p>'
            . '</div></div></body></html>';

        $this->mailer->send($recipient, $subject, $html);
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'TEXTO' => 'Texto',
            'IMAGEM' => 'Imagem',
            'DOCUMENTO' => 'Documento',
            'AUDIO' => 'Audio',
            'VIDEO' => 'Video',
            'LOCALIZACAO' => 'Localizacao',
            'CONTATO' => 'Contato compartilhado',
            'BOTAO', 'INTERATIVA' => 'Resposta interativa',
            'STICKER' => 'Sticker',
            default => 'Mensagem',
        };
    }
}
