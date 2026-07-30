<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use Refugio\Support\Money;
use RuntimeException;
use Throwable;

final class ReservationPdfTemplate
{
    public static function render(array $payload): string
    {
        $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];
        $reservation = is_array($payload['reservation'] ?? null) ? $payload['reservation'] : [];
        $property = is_array($payload['property'] ?? null) ? $payload['property'] : [];
        $type = (string) ($document['type'] ?? '');
        if (!in_array($type, [ReservationDocumentService::PROPOSAL, ReservationDocumentService::PAYMENT_REQUEST], true)) {
            throw new RuntimeException('Tipo de documento inválido.');
        }
        if (trim((string) ($reservation['code'] ?? '')) === '' || trim((string) ($reservation['customer_name'] ?? '')) === '') {
            throw new RuntimeException('Dados essenciais da reserva ausentes.');
        }

        $isProposal = $type === ReservationDocumentService::PROPOSAL;
        $title = $isProposal ? 'Pedido de reserva' : 'Instruções de pagamento';
        $subtitle = $isProposal
            ? 'Proposta comercial para análise do cliente. Este documento ainda não confirma nem bloqueia a reserva.'
            : 'Dados para pagamento da hospedagem. As datas permanecem retidas somente até o vencimento informado.';
        $code = self::escape($reservation['code']);
        $version = max(1, (int) ($document['version'] ?? 1));
        $propertyName = self::plain($property['name'] ?? null, 'Refúgio do Cuscuzeiro');
        $contact = implode(' | ', array_filter([
            self::plain($property['city'] ?? null),
            self::plain($property['state'] ?? null),
            self::plain($property['phone'] ?? null),
            self::plain($property['email'] ?? null),
        ]));
        $assets = is_array($payload['assets'] ?? null) ? $payload['assets'] : [];
        $logo = self::imageDataUri($assets['logo_path'] ?? null);
        $logoHtml = $logo !== null
            ? '<img class="brand-logo" src="' . self::escape($logo) . '" alt="">'
            : '<div class="brand-mark">R</div>';

        $pricingRows = '';
        foreach ((array) ($payload['pricing_items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $pricingRows .= '<tr><td>' . self::text($item['description'] ?? null, 'Item') . '</td>'
                . '<td class="money">' . self::escape(self::brl($item['amount'] ?? '0')) . '</td></tr>';
        }
        if ($pricingRows === '') {
            $pricingRows = '<tr><td>Hospedagem</td><td class="money">' . self::escape(self::brl($reservation['total'] ?? '0')) . '</td></tr>';
        }

        $valuesHtml = $isProposal
            ? '<table class="value-table"><thead><tr><th>Descrição</th><th class="money">Valor</th></tr></thead><tbody>'
                . $pricingRows
                . '<tr class="total-row"><td>VALOR TOTAL</td><td class="money">' . self::escape(self::brl($reservation['total'] ?? '0')) . '</td></tr>'
                . '</tbody></table>'
            : '<table class="summary-table"><tr>'
                . self::summaryCell('Valor total', $reservation['total'] ?? '0')
                . self::summaryCell('Sinal', $reservation['signal'] ?? '0')
                . self::summaryCell('Saldo', $reservation['remaining'] ?? '0')
                . '</tr></table>';

        $bodyAfterValues = $isProposal
            ? self::proposalContent($document)
            : self::paymentContent($payload);

        $conditions = self::conditions($reservation);
        $issuedAt = self::escape(self::dateBr($document['issued_at'] ?? null, true));
        $customerName = self::text($reservation['customer_name'] ?? null);
        $customerPhone = self::text($reservation['customer_phone'] ?? null);
        $checkin = self::dateBr($reservation['checkin'] ?? null) . ' ' . self::plain($property['checkin_time'] ?? null);
        $checkout = self::dateBr($reservation['checkout'] ?? null) . ' ' . self::plain($property['checkout_time'] ?? null);
        $checkin = self::escape(trim($checkin));
        $checkout = self::escape(trim($checkout));
        $nights = (int) ($reservation['nights'] ?? 0);
        $adults = (int) ($reservation['adults'] ?? 0);
        $children = (int) ($reservation['children'] ?? 0);
        $safeTitle = self::escape($title);
        $safeSubtitle = self::escape($subtitle);
        $safePropertyName = self::escape($propertyName);
        $safeContact = self::escape($contact !== '' ? $contact : 'Atendimento de reservas');

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{$safeTitle} {$code}</title>
<style>
@page { margin: 17mm 18mm 18mm; }
* { box-sizing: border-box; }
body {
    margin: 0;
    color: #252a22;
    font-family: "DejaVu Sans", sans-serif;
    font-size: 8.5pt;
    line-height: 1.4;
}
table { width: 100%; border-collapse: collapse; }
.document-header td { padding: 0; border: 0; vertical-align: middle; }
.logo-cell { width: 25mm; }
.brand-logo { width: 21mm; max-height: 21mm; object-fit: contain; }
.brand-mark {
    width: 18mm;
    height: 18mm;
    border: 1.2pt solid #424735;
    color: #424735;
    font-size: 25pt;
    font-weight: bold;
    line-height: 18mm;
    text-align: center;
}
.brand { color: #424735; font-size: 14pt; font-weight: bold; }
.brand-contact { margin-top: 1mm; color: #667064; font-size: 7.3pt; }
.document-badge { width: 42mm; color: #424735; font-size: 7.5pt; font-weight: bold; text-align: right; }
.header-rule { margin: 3mm 0; border-top: 1pt solid #e6d9c4; }
h1 { margin: 0; color: #252a22; font-size: 17pt; line-height: 1.25; }
.subtitle { margin: 1mm 0 0; color: #667064; font-size: 9pt; }
h2 {
    margin: 4mm 0 1.5mm;
    color: #424735;
    font-size: 8.5pt;
    letter-spacing: 0.6pt;
    page-break-after: avoid;
}
.details td {
    width: 50%;
    padding: 2mm 2.3mm;
    border: 0.5pt solid #d8d4ca;
    vertical-align: top;
}
.label { color: #424735; font-size: 7.2pt; font-weight: bold; }
.value-table th,
.value-table td,
.payment-table th,
.payment-table td {
    padding: 2mm 2.3mm;
    border: 0.5pt solid #d8d4ca;
    vertical-align: middle;
}
.value-table th,
.payment-table th {
    background: #424735;
    color: #fff;
    font-size: 7.7pt;
    text-align: left;
}
.value-table .money { width: 28%; }
.money { text-align: right !important; white-space: nowrap; }
.total-row td { background: #f6f1e7; color: #424735; font-size: 9.5pt; font-weight: bold; }
.summary-table td {
    width: 33.333%;
    padding: 2.5mm;
    border: 0.5pt solid #d8d4ca;
    background: #f6f1e7;
}
.summary-value { margin-top: 1mm; color: #424735; font-size: 11pt; font-weight: bold; }
.notice {
    margin-top: 3mm;
    padding: 2.7mm;
    border: 0.6pt solid #e6d9c4;
    background: #f6f1e7;
    page-break-inside: avoid;
}
.payment-box {
    width: 100%;
    margin-top: 3mm;
    padding: 3mm;
    border: 0.7pt solid #afc8af;
    background: #ddebdd;
    page-break-inside: avoid;
}
.payment-box td { border: 0; vertical-align: middle; }
.qr-cell { width: 48mm; padding-right: 4mm !important; }
.qr-code { width: 40mm; height: 40mm; object-fit: contain; }
.pix {
    margin-top: 1.5mm;
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 6.6pt;
    overflow-wrap: anywhere;
    word-wrap: break-word;
}
.condition-title { margin: 1.5mm 0 0.5mm; color: #424735; font-size: 7.4pt; font-weight: bold; }
.condition-text { margin: 0; white-space: pre-line; }
.closing {
    margin-top: 4mm;
    padding-top: 3mm;
    border-top: 0.5pt solid #d8d4ca;
    color: #667064;
    font-size: 7pt;
    text-align: center;
}
.pdf-footer {
    position: fixed;
    right: 0;
    bottom: -12mm;
    left: 0;
    padding-top: 2mm;
    border-top: 0.5pt solid #d8d4ca;
    color: #667064;
    font-size: 6.8pt;
}
</style>
</head>
<body>
<div class="pdf-footer">{$code} - documento v{$version}</div>
<table class="document-header"><tr>
    <td class="logo-cell">{$logoHtml}</td>
    <td><div class="brand">{$safePropertyName}</div><div class="brand-contact">{$safeContact}</div></td>
    <td class="document-badge">{$code}<br>{$issuedAt}</td>
</tr></table>
<div class="header-rule"></div>
<h1>{$safeTitle}</h1>
<p class="subtitle">{$safeSubtitle}</p>
<h2>CLIENTE E ESTADIA</h2>
<table class="details">
    <tr>
        <td><span class="label">Cliente</span><br>{$customerName}</td>
        <td><span class="label">WhatsApp</span><br>{$customerPhone}</td>
    </tr>
    <tr>
        <td><span class="label">Check-in</span><br>{$checkin}</td>
        <td><span class="label">Check-out</span><br>{$checkout}</td>
    </tr>
    <tr>
        <td><span class="label">Período</span><br>{$nights} diária(s)</td>
        <td><span class="label">Hóspedes</span><br>{$adults} adulto(s), {$children} criança(s)</td>
    </tr>
</table>
<h2>VALORES</h2>
{$valuesHtml}
{$bodyAfterValues}
{$conditions}
HTML
        . ($isProposal
            ? '<p class="closing">Documento emitido eletronicamente pelo painel administrativo. Em caso de divergência, fale com o atendimento antes de efetuar qualquer pagamento.</p>'
            : '')
        . '</body></html>';
    }

    private static function proposalContent(array $document): string
    {
        return '<div class="notice"><strong>Validade da proposta:</strong> '
            . self::escape(self::dateBr($document['valid_until'] ?? null, true))
            . '. Para seguir, responda ao atendimento confirmando o interesse. A disponibilidade será verificada novamente antes da emissão da cobrança.</div>';
    }

    private static function paymentContent(array $payload): string
    {
        $payments = array_values(array_filter(
            (array) ($payload['payments'] ?? []),
            static fn(mixed $payment): bool => is_array($payment)
                && in_array((string) ($payment['status'] ?? ''), ['PENDENTE', 'COMPROVANTE_ENVIADO'], true)
        ));
        if ($payments === []) {
            throw new RuntimeException('Nenhuma cobrança pendente encontrada.');
        }
        $rows = '';
        foreach ($payments as $payment) {
            $type = ucfirst(mb_strtolower(str_replace('_', ' ', self::plain($payment['type'] ?? null, 'Cobrança'))));
            $rows .= '<tr><td>' . self::escape($type) . '</td>'
                . '<td class="money">' . self::escape(self::brl($payment['amount'] ?? '0')) . '</td>'
                . '<td>' . self::escape(self::dateBr($payment['due_at'] ?? null, true)) . '</td></tr>';
        }

        $primary = $payments[0];
        $assets = is_array($payload['assets'] ?? null) ? $payload['assets'] : [];
        $qr = self::imageDataUri($assets['qr_code_path'] ?? null);
        $qrCell = $qr !== null
            ? '<td class="qr-cell"><img class="qr-code" src="' . self::escape($qr) . '" alt="QR Code Pix"></td>'
            : '';
        $pix = self::breakableText(
            $primary['pix_copy_paste'] ?? null,
            $qr !== null ? 'Utilize o QR Code ao lado.' : 'Solicite os dados Pix ao atendimento.'
        );
        $notes = self::plain($primary['notes'] ?? null);
        $notesHtml = $notes !== ''
            ? '<p class="condition-title">Observações</p><p class="condition-text">' . self::text($notes) . '</p>'
            : '';

        return '<h2>PAGAMENTO</h2>'
            . '<table class="payment-table"><thead><tr><th>Cobrança</th><th class="money">Valor</th><th>Vencimento</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'
            . '<table class="payment-box"><tr>' . $qrCell . '<td>'
            . '<div class="label">CHAVE PIX / PIX COPIA E COLA</div><div class="pix">' . $pix . '</div>'
            . $notesHtml . '</td></tr></table>'
            . '<div class="notice"><strong>Importante:</strong> a reserva somente será confirmada após a identificação do pagamento. '
            . 'Confira o favorecido antes de concluir o Pix e envie o comprovante pelo canal de atendimento.</div>';
    }

    private static function conditions(array $reservation): string
    {
        $notes = self::plain($reservation['commercial_notes'] ?? null);
        $cancellation = self::plain($reservation['cancellation_policy'] ?? null);
        if ($notes === '' && $cancellation === '') return '';

        $html = '<h2>CONDIÇÕES</h2>';
        if ($notes !== '') {
            $html .= '<p class="condition-title">Observações comerciais</p><p class="condition-text">' . self::text($notes) . '</p>';
        }
        if ($cancellation !== '') {
            $html .= '<p class="condition-title">Política de cancelamento</p><p class="condition-text">' . self::text($cancellation) . '</p>';
        }
        return $html;
    }

    private static function summaryCell(string $label, mixed $value): string
    {
        return '<td><span class="label">' . self::escape($label) . '</span><div class="summary-value">'
            . self::escape(self::brl($value)) . '</div></td>';
    }

    private static function brl(mixed $value): string
    {
        try {
            $cents = Money::toCents((string) ($value ?? '0'));
        } catch (Throwable) {
            $cents = 0;
        }
        $formatted = number_format(abs($cents) / 100, 2, ',', '.');
        return ($cents < 0 ? '-R$ ' : 'R$ ') . $formatted;
    }

    private static function dateBr(mixed $value, bool $withTime = false): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') return 'Não informado';
        try {
            $date = new DateTimeImmutable($raw);
            $hasTime = $withTime || str_contains($raw, ' ') || str_contains($raw, 'T');
            return $date->format($hasTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (Throwable) {
            return $raw;
        }
    }

    private static function imageDataUri(mixed $path): ?string
    {
        if (!is_string($path) || $path === '' || !is_file($path)) return null;
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) return null;
        $bytes = file_get_contents($path);
        if ($bytes === false) return null;

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };
        return $mime === null ? null : 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private static function text(mixed $value, string $fallback = 'Não informado'): string
    {
        return self::escape(self::plain($value, $fallback));
    }

    private static function breakableText(mixed $value, string $fallback): string
    {
        $text = self::plain($value, $fallback);
        return implode('<wbr>', array_map(self::escape(...), mb_str_split($text, 28, 'UTF-8')));
    }

    private static function plain(mixed $value, string $fallback = ''): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($value ?? ''))) ?? '');
        return $text !== '' ? $text : $fallback;
    }

    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
