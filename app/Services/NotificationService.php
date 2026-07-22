<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Env;
use Throwable;

final class NotificationService
{
    public function __construct(private PDO $db, private ?SmtpClient $mailer = null, private ?WhatsAppService $whatsApp = null)
    {
        $this->mailer ??= new SmtpClient();
        $this->whatsApp ??= new WhatsAppService();
    }

    public function customer(array $reservation, string $type, array $extra = []): void
    {
        $data = array_merge($reservation, $extra);
        [$subject, $message] = $this->message($type, $data);
        $this->email((int) $reservation['id'], $type, (string) $reservation['email'], $subject, $message);
        if (!empty($reservation['whatsapp_autorizado'])) {
            $templateKey = 'WHATSAPP_TEMPLATE_' . $type;
            $template = Env::get($templateKey);
            if ($template === '') return;
            $params = [$reservation['nome_cliente'], $reservation['codigo'], date('d/m/Y', strtotime($reservation['checkin'])), date('d/m/Y', strtotime($reservation['checkout']))];
            if (!empty($extra['valor'])) $params[] = money($extra['valor']);
            if (!empty($extra['link'])) $params[] = $extra['link'];
            $this->whatsApp((int) $reservation['id'], $type, (string) $reservation['telefone'], $template, $params, $message);
        }
    }

    public function admin(array $reservation, string $type, string $details): void
    {
        $email = Env::get('ADMIN_EMAIL');
        if ($email === '') return;
        $subject = '[Refugio] ' . ($type === 'NOVA_SOLICITACAO' ? 'Nova solicitacao ' : 'Novo comprovante ') . $reservation['codigo'];
        $html = $this->layout('<h2>' . e($subject) . '</h2><p>' . e($details) . '</p><p><a class="button" href="' . e(base_url('admin/reservas/' . $reservation['id'])) . '">Abrir no painel</a></p>');
        $this->email((int) $reservation['id'], $type, $email, $subject, $html);
    }

    private function email(int $reservationId, string $type, string $to, string $subject, string $html): void
    {
        $id = $this->create($reservationId, 'EMAIL', $type, $to, $html);
        try {
            $external = $this->mailer->send($to, $subject, $html);
            $this->success($id, $external);
        } catch (Throwable $e) {
            $this->failure($id, $e->getMessage());
        }
    }

    private function whatsApp(int $reservationId, string $type, string $to, string $template, array $params, string $content): void
    {
        $id = $this->create($reservationId, 'WHATSAPP', $type, $to, $content);
        try {
            $external = $this->whatsApp->sendTemplate($to, $template, $params);
            $this->success($id, $external);
        } catch (Throwable $e) {
            $this->failure($id, $e->getMessage());
        }
    }

    private function create(int $reservationId, string $channel, string $type, string $to, string $content): int
    {
        $stmt = $this->db->prepare("INSERT INTO notificacoes (reserva_id,canal,tipo,destinatario,conteudo,status,tentativas) VALUES (?,?,?,?,?,'PENDENTE',1)");
        $stmt->execute([$reservationId, $channel, $type, $to, $content]);
        return (int) $this->db->lastInsertId();
    }

    private function success(int $id, string $external): void
    {
        $this->db->prepare("UPDATE notificacoes SET status='ENVIADO',id_mensagem_externa=?,enviado_em=NOW() WHERE id=?")->execute([$external, $id]);
    }

    private function failure(int $id, string $error): void
    {
        $safe = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', mb_substr($error, 0, 2000));
        $this->db->prepare("UPDATE notificacoes SET status='FALHOU',erro=? WHERE id=?")->execute([$safe, $id]);
    }

    private function message(string $type, array $r): array
    {
        $name = e($r['nome_cliente']); $code = e($r['codigo']);
        $copy = match ($type) {
            'SOLICITACAO_RECEBIDA' => ['Recebemos sua solicitacao', "<h2>Solicitacao recebida</h2><p>Ola, {$name}.</p><p>Recebemos a solicitacao <strong>{$code}</strong>. Ela ainda nao esta confirmada. Verificaremos a disponibilidade e enviaremos uma resposta.</p>"],
            'RESERVA_APROVADA' => ['Solicitacao aprovada: pagamento disponivel', "<h2>Pagamento disponivel</h2><p>Ola, {$name}. A disponibilidade foi aprovada para a solicitacao <strong>{$code}</strong>.</p><p>Valor: <strong>" . e(money($r['valor'] ?? 0)) . "</strong></p><p><a class=\"button\" href=\"" . e($r['link'] ?? '#') . "\">Abrir pagina de pagamento</a></p><p>A reserva sera confirmada somente apos identificarmos o pagamento.</p>"],
            'RESERVA_RECUSADA' => ['Resposta da solicitacao de reserva', "<h2>Solicitacao indisponivel</h2><p>Ola, {$name}. Infelizmente nao foi possivel aprovar a solicitacao <strong>{$code}</strong> para as datas informadas.</p>"],
            'COMPROVANTE_RECEBIDO' => ['Comprovante recebido', "<h2>Comprovante recebido</h2><p>Recebemos o comprovante da solicitacao <strong>{$code}</strong>. A reserva sera confirmada apos a identificacao do pagamento.</p>"],
            'PAGAMENTO_CONFIRMADO', 'RESERVA_CONFIRMADA' => ['Reserva confirmada', "<h2>Reserva confirmada</h2><p>Ola, {$name}. O pagamento foi identificado e sua reserva <strong>{$code}</strong> esta confirmada.</p><p>Entrada: " . e(date('d/m/Y', strtotime($r['checkin']))) . '<br>Saida: ' . e(date('d/m/Y', strtotime($r['checkout']))) . '</p>'],
            'PAGAMENTO_EXPIRADO' => ['Prazo de pagamento expirado', "<h2>Solicitacao expirada</h2><p>O prazo de pagamento da solicitacao <strong>{$code}</strong> terminou e as datas foram liberadas.</p>"],
            'RESERVA_CANCELADA' => ['Reserva cancelada', "<h2>Reserva cancelada</h2><p>A reserva <strong>{$code}</strong> foi cancelada. Entre em contato em caso de duvida.</p>"],
            default => ['Atualizacao da sua solicitacao', '<p>Houve uma atualizacao na solicitacao <strong>' . $code . '</strong>.</p>'],
        };
        return [$copy[0], $this->layout($copy[1])];
    }

    private function layout(string $body): string
    {
        return '<!doctype html><html><body style="margin:0;background:#f5f1eb;font-family:Arial,sans-serif;color:#4b4a39"><div style="max-width:620px;margin:auto;padding:28px"><div style="background:#fff;border-radius:16px;padding:30px;border-top:5px solid #4b4a39">' . $body . '<p style="margin-top:28px;font-size:13px;color:#6b6a59">Refugio do Cuscuzeiro - Analandia/SP</p></div></div><style>.button{display:inline-block;background:#4b4a39;color:white!important;padding:12px 20px;border-radius:8px;text-decoration:none}</style></body></html>';
    }
}
