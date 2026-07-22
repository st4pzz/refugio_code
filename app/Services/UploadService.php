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

    private function store(array $file, string $folder, array $allowed): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Selecione um arquivo valido.');
        if (($file['size'] ?? 0) < 1 || $file['size'] > $this->maxBytes) throw new RuntimeException('O arquivo excede o limite permitido.');
        $tmp = (string) $file['tmp_name'];
        $mime = $this->mime($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('Tipo de arquivo nao permitido.');
        $signature = file_get_contents($tmp, false, null, 0, 12) ?: '';
        if ($mime === 'application/pdf' && !str_starts_with($signature, '%PDF-')) throw new RuntimeException('PDF invalido.');
        if ($mime === 'image/jpeg' && !str_starts_with($signature, "\xFF\xD8\xFF")) throw new RuntimeException('Imagem JPEG invalida.');
        if ($mime === 'image/png' && !str_starts_with($signature, "\x89PNG\r\n\x1A\n")) throw new RuntimeException('Imagem PNG invalida.');
        $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $relative = 'storage/' . $folder . '/' . $name;
        $destination = BASE_PATH . '/' . $relative;
        if (!(is_uploaded_file($tmp) ? move_uploaded_file($tmp, $destination) : (PHP_SAPI === 'cli' && copy($tmp, $destination)))) throw new RuntimeException('Nao foi possivel armazenar o arquivo.');
        @chmod($destination, 0640);
        return ['path' => $relative, 'mime' => $mime, 'name' => mb_substr(basename((string) ($file['name'] ?? 'arquivo')), 0, 255)];
    }

    private function mime(string $file): string
    {
        if (class_exists('finfo')) return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $size = @getimagesize($file);
        if (!empty($size['mime'])) return $size['mime'];
        return str_starts_with((string) file_get_contents($file, false, null, 0, 5), '%PDF-') ? 'application/pdf' : 'application/octet-stream';
    }
}
