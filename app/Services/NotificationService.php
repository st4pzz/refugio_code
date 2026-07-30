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

    public static function redactReviewToken(string $content): string
    {
        return preg_replace('#(avaliar/)[a-f0-9]{64}#i', '$1[token-redacted]', $content) ?? '[conteudo protegido]';
    }

    public static function redactSensitiveTokens(string $content): string
    {
        $content=self::redactReviewToken($content);
        return preg_replace('#((?:minha-reserva|reserva)/)[A-Za-z0-9_-]{40,}#i','$1[token-redacted]',$content)??'[conteudo protegido]';
    }

    public function automation(array $reservation,string $type,string $subject,string $body,array $channels,?string $whatsAppTemplate=null,array $parameters=[]):void
    {
        $html=$this->layout('<h2>'.e($subject).'</h2><p>'.nl2br(e($body)).'</p>');
        if(in_array('EMAIL',$channels,true))$this->email((int)$reservation['reservation_id'],$type,(string)$reservation['email'],$subject,$html,null,true);
        if(in_array('WHATSAPP',$channels,true)&&!empty($reservation['whatsapp_autorizado'])&&$whatsAppTemplate!==null){
            $this->whatsApp((int)$reservation['reservation_id'],$type,(string)$reservation['telefone'],$whatsAppTemplate,array_slice(array_map('strval',$parameters),0,10),$body);
        }
    }

    public function signatureCode(array $reservation,string $code,string $expiresAt,string $role):void
    {
        $subject='Código para assinatura do contrato '.$reservation['codigo'];
        $body=$this->layout('<h2>'.e($subject).'</h2><p>Seu código de uso único é:</p><p style="font-size:28px;letter-spacing:6px"><strong>'.e($code).'</strong></p><p>Expira às '.e(date('H:i',strtotime($expiresAt))).'. Não compartilhe este código.</p>');
        $emailSent=$this->email((int)$reservation['id'],'CONTRACT_SIGNATURE_CODE',(string)$reservation['email'],$subject,$body,'[código de assinatura redigido; validade '.$expiresAt.']');
        $template=Env::get('WHATSAPP_TEMPLATE_CONTRACT_SIGNATURE_CODE');
        $whatsAppSent=false;if(!empty($reservation['whatsapp_autorizado'])&&$template!=='')$whatsAppSent=$this->whatsApp((int)$reservation['id'],'CONTRACT_SIGNATURE_CODE',(string)$reservation['telefone'],$template,[$code,date('H:i',strtotime($expiresAt))],'Código de assinatura enviado.');
        if(!$emailSent&&!$whatsAppSent)throw new \RuntimeException('Não foi possível entregar o código. Tente novamente ou contate o atendimento.');
    }

    public function customer(array $reservation, string $type, array $extra = []): array
    {
        $data = array_merge($reservation, $extra);
        [$subject, $message] = $this->message($type, $data);
        $email = $this->email((int) $reservation['id'], $type, (string) $reservation['email'], $subject, $message);
        $whatsApp = false;
        if (!empty($reservation['whatsapp_autorizado'])) {
            $templateKey = 'WHATSAPP_TEMPLATE_' . $type;
            $template = Env::get($templateKey);
            if ($template !== '') {
                $params = [$reservation['nome_cliente'], $reservation['codigo'], date('d/m/Y', strtotime($reservation['checkin'])), date('d/m/Y', strtotime($reservation['checkout']))];
                if (!empty($extra['valor'])) $params[] = money($extra['valor']);
                if (!empty($extra['link'])) $params[] = $extra['link'];
                $whatsApp = $this->whatsApp((int) $reservation['id'], $type, (string) $reservation['telefone'], $template, $params, $message);
            }
        }
        return ['email' => $email, 'whatsapp' => $whatsApp];
    }

    public function reviewInvitation(array $reservation, string $link, string $expiresAt, bool $reminder = false): array
    {
        $type = $reminder ? 'LEMBRETE_AVALIACAO' : 'CONVITE_AVALIACAO';
        $firstName = explode(' ', trim((string) $reservation['nome_cliente']))[0];
        $subject = $reminder ? 'Lembrete: conte como foi sua estadia' : 'Como foi sua estadia no Refugio do Cuscuzeiro?';
        $intro = $reminder ? 'Este e um lembrete gentil: seu convite para avaliar a estadia ainda esta disponivel.' : 'Esperamos que tenha aproveitado sua estadia no Refugio do Cuscuzeiro. Sua opiniao e muito importante para nos.';
        $html = $this->layout('<h2>' . e($subject) . '</h2><p>Ola, ' . e($firstName) . '!</p><p>' . e($intro) . '</p><p><a class="button" href="' . e($link) . '">Avaliar minha estadia</a></p><p>Nao e necessario criar conta ou fazer login.</p><p>Este link e exclusivo e ficara disponivel ate <strong>' . e(date('d/m/Y', strtotime($expiresAt))) . '</strong>.</p>');
        $email = $this->email((int) $reservation['id'], $type, (string) $reservation['email'], $subject, $html);
        $whatsApp = false;
        $template = Env::get('WHATSAPP_TEMPLATE_' . $type);
        if (!empty($reservation['whatsapp_autorizado']) && $template !== '') {
            $params = [$firstName, 'Refugio do Cuscuzeiro', $link, date('d/m/Y', strtotime($expiresAt))];
            $whatsApp = $this->whatsApp((int) $reservation['id'], $type, (string) $reservation['telefone'], $template, $params, strip_tags($html));
        }
        return ['email' => $email, 'whatsapp' => $whatsApp];
    }

    public function admin(array $reservation, string $type, string $details, ?string $path = null): void
    {
        $email = Env::get('ADMIN_EMAIL');
        if ($email === '') return;
        $label = match ($type) { 'NOVA_SOLICITACAO' => 'Nova solicitacao ', 'NOVO_COMPROVANTE' => 'Novo comprovante ', 'NOVA_AVALIACAO' => 'Nova avaliacao ', default => 'Atualizacao ' };
        $subject = '[Refugio] ' . $label . $reservation['codigo'];
        $html = $this->layout('<h2>' . e($subject) . '</h2><p>' . e($details) . '</p><p><a class="button" href="' . e(base_url($path ?? ('admin/reservas/' . $reservation['id']))) . '">Abrir no painel</a></p>');
        $this->email((int) $reservation['id'], $type, $email, $subject, $html);
    }

    private function email(int $reservationId, string $type, string $to, string $subject, string $html, ?string $storedContent = null, bool $throwOnFailure = false): bool
    {
        $id = $this->create($reservationId, 'EMAIL', $type, $to, $storedContent ?? $html);
        try {
            $external = $this->mailer->send($to, $subject, $html);
            $this->success($id, $external);
            return true;
        } catch (Throwable $e) {
            $this->failure($id, $e->getMessage());
            if ($throwOnFailure) throw new \RuntimeException('Falha ao enviar e-mail: ' . $e->getMessage(), 0, $e);
            return false;
        }
    }

    private function whatsApp(int $reservationId, string $type, string $to, string $template, array $params, string $content): bool
    {
        $id = $this->create($reservationId, 'WHATSAPP', $type, $to, $content);
        try {
            $external = $this->whatsApp->sendTemplate($to, $template, $params);
            $this->success($id, $external);
            return true;
        } catch (Throwable $e) {
            $this->failure($id, $e->getMessage());
            return false;
        }
    }

    private function create(int $reservationId, string $channel, string $type, string $to, string $content): int
    {
        $content = self::redactSensitiveTokens($content);
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
