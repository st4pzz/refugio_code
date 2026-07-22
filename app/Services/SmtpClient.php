<?php
declare(strict_types=1);

namespace Refugio\Services;

use Refugio\Support\Env;
use RuntimeException;

final class SmtpClient
{
    /** @var resource|null */
    private $socket;

    public function send(string $to, string $subject, string $html): string
    {
        $host = Env::get('SMTP_HOST');
        if ($host === '') {
            throw new RuntimeException('SMTP nao configurado.');
        }
        $port = Env::int('SMTP_PORT', 587);
        $encryption = strtolower(Env::get('SMTP_ENCRYPTION', 'tls'));
        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
        $this->socket = @stream_socket_client($target, $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) throw new RuntimeException("Falha ao conectar ao SMTP ({$errno}).");
        stream_set_timeout($this->socket, 15);
        $this->expect([220]);
        $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Falha ao iniciar TLS no SMTP.');
            $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }
        $username = Env::get('SMTP_USERNAME');
        if ($username !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($username), [334]);
            $this->command(base64_encode(Env::get('SMTP_PASSWORD')), [235]);
        }
        $from = Env::get('SMTP_FROM_EMAIL');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Endereco de e-mail invalido.');
        $this->command('MAIL FROM:<' . $from . '>', [250]);
        $this->command('RCPT TO:<' . $to . '>', [250, 251]);
        $this->command('DATA', [354]);
        $fromName = Env::get('SMTP_FROM_NAME', 'Refugio do Cuscuzeiro');
        $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), preg_replace('/[^a-z0-9.-]/i', '', $host));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $html));
        fwrite($this->socket, str_replace("\n", "\r\n", $payload) . "\r\n.\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        fclose($this->socket);
        return $messageId;
    }

    private function command(string $command, array $expected): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->expect($expected);
    }

    private function expect(array $expected): string
    {
        $response = '';
        do {
            $line = fgets($this->socket, 515);
            if ($line === false) throw new RuntimeException('Resposta SMTP ausente.');
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) throw new RuntimeException('Servidor SMTP recusou a mensagem (codigo ' . $code . ').');
        return $response;
    }
}
