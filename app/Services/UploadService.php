<?php
declare(strict_types=1);

namespace Refugio\Services;

use RuntimeException;

final class UploadService
{
    public function __construct(private int $maxBytes) {}

    public function receipt(array $file): array
    {
        return $this->store($file, 'comprovantes', ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png']);
    }

    public function qrCode(array $file): array
    {
        return $this->store($file, 'qrcodes', ['image/jpeg' => 'jpg', 'image/png' => 'png']);
    }

    public function contractPdf(array $file, int $contractId): array
    {
        if ($contractId <= 0) throw new RuntimeException('Contrato invalido.');
        return $this->store($file, 'contracts/' . $contractId, ['application/pdf' => 'pdf']);
    }

    private function store(array $file, string $folder, array $allowed): array
    {
        if (!preg_match('#^[a-z0-9/_-]+$#i', $folder)) throw new RuntimeException('Destino de upload invalido.');
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) throw new RuntimeException($this->uploadErrorMessage($uploadError));
        if (($file['size'] ?? 0) < 1 || $file['size'] > $this->maxBytes) throw new RuntimeException('O arquivo excede o limite permitido.');
        $tmp = (string) $file['tmp_name'];
        $mime = $this->mime($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('Tipo de arquivo nao permitido.');
        $signature = file_get_contents($tmp, false, null, 0, 12) ?: '';
        if ($mime === 'application/pdf' && !str_starts_with($signature, '%PDF-')) throw new RuntimeException('PDF invalido.');
        if ($mime === 'image/jpeg' && !str_starts_with($signature, "\xFF\xD8\xFF")) throw new RuntimeException('Imagem JPEG invalida.');
        if ($mime === 'image/png' && !str_starts_with($signature, "\x89PNG\r\n\x1A\n")) throw new RuntimeException('Imagem PNG invalida.');
        $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $directory = BASE_PATH . '/storage/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Nao foi possivel preparar o armazenamento do arquivo.');
        $relative = 'storage/' . $folder . '/' . $name;
        $destination = BASE_PATH . '/' . $relative;
        if (!(is_uploaded_file($tmp) ? move_uploaded_file($tmp, $destination) : (PHP_SAPI === 'cli' && copy($tmp, $destination)))) throw new RuntimeException('Nao foi possivel armazenar o arquivo.');
        @chmod($destination, 0640);
        $hash = hash_file('sha256', $destination);
        $bytes = filesize($destination);
        if ($hash === false || $bytes === false) {
            @unlink($destination);
            throw new RuntimeException('Nao foi possivel validar o arquivo armazenado.');
        }
        return [
            'path' => $relative,
            'mime' => $mime,
            'name' => mb_substr(basename((string) ($file['name'] ?? 'arquivo')), 0, 255),
            'sha256' => $hash,
            'bytes' => $bytes,
        ];
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O PDF excede o limite permitido para envio.',
            UPLOAD_ERR_PARTIAL => 'O envio do PDF foi interrompido. Verifique sua conexão e tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Selecione o contrato assinado em PDF antes de enviar.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'O servidor não conseguiu receber o PDF. Tente novamente em instantes.',
            default => 'Não foi possível receber o PDF enviado.',
        };
    }

    private function mime(string $file): string
    {
        if (class_exists('finfo')) return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $size = @getimagesize($file);
        if (!empty($size['mime'])) return $size['mime'];
        return str_starts_with((string) file_get_contents($file, false, null, 0, 5), '%PDF-') ? 'application/pdf' : 'application/octet-stream';
    }
}
