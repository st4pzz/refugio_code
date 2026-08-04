<?php
declare(strict_types=1);

namespace Refugio\Services;

final class ContractPdfTemplate
{
    public static function render(string $title, string $snapshotHtml, string $documentHash, array $variables = []): string
    {
        $safeTitle = self::escape($title);
        $safeHash = self::escape($documentHash);
        $signaturePage = self::signaturePage($safeTitle, $safeHash, $variables);

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{$safeTitle}</title>
<style>
@page { margin: 18mm 18mm 18mm; }
* { box-sizing: border-box; }
body {
    margin: 0;
    color: #252a22;
    font-family: "DejaVu Sans", sans-serif;
    font-size: 8.7pt;
    line-height: 1.42;
}
.contract-header {
    margin: 0 0 5mm;
    padding: 0 0 3mm;
    border-bottom: 1px solid #e6d9c4;
}
.contract-header h1 {
    margin: 0;
    color: #424735;
    font-size: 15pt;
    line-height: 1.2;
}
.contract-header p {
    margin: 1mm 0 0;
    color: #667064;
    font-size: 7.5pt;
}
.contract-body h1 {
    margin: 0 0 4mm;
    color: #252a22;
    font-size: 16pt;
    line-height: 1.25;
}
.contract-body h2 {
    margin: 4mm 0 1.5mm;
    color: #424735;
    font-size: 10pt;
    line-height: 1.25;
    page-break-after: avoid;
}
.contract-body h3 {
    margin: 3mm 0 1.2mm;
    color: #424735;
    font-size: 9pt;
    page-break-after: avoid;
}
.contract-body p {
    margin: 0 0 2mm;
    text-align: justify;
}
.contract-body table {
    width: 100%;
    margin: 2mm 0 4mm;
    border-collapse: collapse;
    page-break-inside: auto;
    font-size: 7.4pt;
}
.contract-body thead { display: table-header-group; }
.contract-body tr { page-break-inside: avoid; }
.contract-body th,
.contract-body td {
    padding: 1.7mm;
    border: 0.5pt solid #d8d4ca;
    text-align: left;
    vertical-align: top;
}
.contract-body th {
    background: #424735;
    color: #fff;
    font-weight: bold;
}
.contract-body .document-hash {
    margin-top: 5mm;
    padding: 2.5mm;
    border: 0.5pt solid #d8d4ca;
    background: #f6f1e7;
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 6.8pt;
    overflow-wrap: anywhere;
}
.signature-page {
    page-break-before: always;
    min-height: 244mm;
    color: #252a22;
}
.signature-page h2 {
    margin: 0 0 2mm;
    color: #424735;
    font-size: 14pt;
    text-align: center;
}
.signature-intro {
    margin: 0 auto 8mm;
    color: #667064;
    font-size: 7.7pt;
    line-height: 1.45;
    text-align: center;
}
.signature-date {
    margin: 0 0 9mm;
    font-size: 9.2pt;
    text-align: center;
}
.signature-block {
    margin: 0 0 7mm;
    page-break-inside: avoid;
    text-align: center;
}
.signature-area {
    height: 27mm;
    border-bottom: 0.7pt solid #424735;
}
.signature-role {
    margin-top: 2mm;
    font-size: 8.5pt;
    font-weight: bold;
    text-transform: uppercase;
}
.signature-identity {
    margin-top: 1mm;
    color: #4f5549;
    font-size: 7.5pt;
}
.signature-witnesses {
    width: 100%;
    margin-top: 8mm;
    border-collapse: separate;
    border-spacing: 7mm 0;
    page-break-inside: avoid;
}
.signature-witnesses td {
    width: 50%;
    padding: 0;
    border: 0;
    vertical-align: top;
    text-align: center;
}
.signature-witnesses .signature-area {
    height: 23mm;
}
.witness-field {
    width: 88%;
    margin: 1.5mm auto 0;
    color: #4f5549;
    font-size: 7.5pt;
    text-align: left;
    white-space: nowrap;
}
.witness-field-label {
    display: inline-block;
    width: 11mm;
}
.witness-field-line {
    display: inline-block;
    width: 45mm;
    border-bottom: 0.5pt solid #667064;
}
.signature-reference {
    margin-top: 10mm;
    padding: 3mm;
    border: 0.5pt solid #d8d4ca;
    background: #f6f1e7;
    color: #667064;
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 6.5pt;
    overflow-wrap: anywhere;
    text-align: left;
}
.pdf-footer {
    position: fixed;
    right: 0;
    bottom: -12mm;
    left: 0;
    padding-top: 2mm;
    border-top: 0.5pt solid #d8d4ca;
    color: #667064;
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 6.3pt;
}
</style>
</head>
<body>
<div class="pdf-footer">Hash do documento: {$safeHash}</div>
<header class="contract-header">
    <h1>{$safeTitle}</h1>
    <p>Documento eletrônico versionado - Refúgio do Cuscuzeiro</p>
</header>
<main class="contract-body">{$snapshotHtml}</main>
{$signaturePage}
</body>
</html>
HTML;
    }

    private static function signaturePage(string $safeTitle, string $safeHash, array $variables): string
    {
        $city = self::escape((string) ($variables['contract_city'] ?? ''));
        $date = self::escape((string) ($variables['contract_date_long'] ?? ''));
        $ownerName = self::escape((string) ($variables['owner_full_name'] ?? ''));
        $ownerCpf = self::escape(self::formatCpf((string) ($variables['owner_cpf'] ?? '')));
        $guestName = self::escape((string) ($variables['guest_full_name'] ?? ''));
        $guestCpf = self::escape(self::formatCpf((string) ($variables['guest_cpf'] ?? '')));
        $placeAndDate = trim($city . ($city !== '' && $date !== '' ? ', ' : '') . $date);
        if ($placeAndDate === '') $placeAndDate = 'Local e data da assinatura';

        return <<<HTML
<section class="signature-page">
    <h2>FOLHA DE ASSINATURAS</h2>
    <p class="signature-intro">Esta folha integra o {$safeTitle} e seus anexos. Os espaços abaixo são reservados para posicionar as assinaturas eletrônicas no Gov.br ou em plataforma equivalente.</p>
    <p class="signature-date">{$placeAndDate}.</p>

    <div class="signature-block">
        <div class="signature-area"></div>
        <div class="signature-role">LOCADOR(A)</div>
        <div class="signature-identity">{$ownerName} · CPF {$ownerCpf}</div>
    </div>

    <div class="signature-block">
        <div class="signature-area"></div>
        <div class="signature-role">LOCATÁRIO(A)</div>
        <div class="signature-identity">{$guestName} · CPF {$guestCpf}</div>
    </div>

    <table class="signature-witnesses" aria-label="Campos opcionais para testemunhas">
        <tr>
            <td>
                <div class="signature-area"></div>
                <div class="signature-role">TESTEMUNHA 1</div>
                <div class="witness-field"><span class="witness-field-label">Nome:</span><span class="witness-field-line"></span></div>
                <div class="witness-field"><span class="witness-field-label">CPF:</span><span class="witness-field-line"></span></div>
            </td>
            <td>
                <div class="signature-area"></div>
                <div class="signature-role">TESTEMUNHA 2</div>
                <div class="witness-field"><span class="witness-field-label">Nome:</span><span class="witness-field-line"></span></div>
                <div class="witness-field"><span class="witness-field-label">CPF:</span><span class="witness-field-line"></span></div>
            </td>
        </tr>
    </table>

    <div class="signature-reference">Documento: {$safeTitle}<br>Hash de integridade: {$safeHash}</div>
</section>
HTML;
    }

    private static function formatCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 11) return trim($value);
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
