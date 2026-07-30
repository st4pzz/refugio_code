<?php
declare(strict_types=1);

namespace Refugio\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Throwable;

final class PdfRenderer
{
    public function render(string $html, string $output, bool $pageNumbers = true): void
    {
        if (!class_exists(Dompdf::class) || !class_exists(Options::class)) {
            throw new RuntimeException(
                'A biblioteca de PDF não está instalada. Execute "composer2 install --no-dev --optimize-autoloader" na raiz da aplicação.'
            );
        }
        if (trim($html) === '') {
            throw new RuntimeException('O conteúdo do PDF está vazio.');
        }

        $outputDirectory = dirname($output);
        $runtimeDirectory = BASE_PATH . '/tmp/dompdf';
        $fontDirectory = $runtimeDirectory . '/fonts';
        foreach ([$outputDirectory, $runtimeDirectory, $fontDirectory] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Não foi possível preparar o armazenamento do PDF.');
            }
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', BASE_PATH);
        $options->set('tempDir', $runtimeDirectory);
        $options->set('fontDir', $fontDirectory);
        $options->set('fontCache', $fontDirectory);

        try {
            $dompdf = new Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();

            if ($pageNumbers) {
                $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
                $dompdf->getCanvas()->page_text(
                    470,
                    818,
                    'Página {PAGE_NUM} de {PAGE_COUNT}',
                    $font,
                    7,
                    [0.40, 0.44, 0.39]
                );
            }
            $bytes = $dompdf->output(['compress' => 1]);
        } catch (Throwable $error) {
            throw new RuntimeException('Falha ao renderizar o PDF em PHP: ' . mb_substr($error->getMessage(), 0, 1000), 0, $error);
        }

        if (!is_string($bytes) || strlen($bytes) < 1000 || !str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('A biblioteca PHP produziu um arquivo PDF inválido.');
        }

        $temporary = $output . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
            @unlink($temporary);
            throw new RuntimeException('Não foi possível gravar o PDF gerado.');
        }
        if (is_file($output) && !unlink($output)) {
            @unlink($temporary);
            throw new RuntimeException('Não foi possível substituir a versão anterior do PDF.');
        }
        if (!rename($temporary, $output)) {
            @unlink($temporary);
            throw new RuntimeException('Não foi possível concluir a gravação do PDF.');
        }
    }
}
