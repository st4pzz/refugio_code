<?php
declare(strict_types=1);

namespace Refugio\Services;

final class ContractPdfTemplate
{
    public static function render(string $title, string $snapshotHtml, string $documentHash): string
    {
        $safeTitle = self::escape($title);
        $safeHash = self::escape($documentHash);

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
</body>
</html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
