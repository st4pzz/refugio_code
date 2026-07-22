<?php
declare(strict_types=1);

namespace Refugio\Support;

final class ReviewValidator
{
    private const RATING_FIELDS = ['nota_geral','nota_limpeza','nota_localizacao','nota_conforto','nota_comunicacao','nota_custo_beneficio'];
    private const NAME_MODES = ['PRIMEIRO_NOME','NOME_ABREVIADO','NOME_COMPLETO','ANONIMO'];

    public static function validate(array $input, array $reservation): array
    {
        $errors = []; $data = [];
        foreach (self::RATING_FIELDS as $field) {
            $rating = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            if ($rating === false) $errors[$field] = 'Selecione uma nota de 1 a 5.';
            $data[$field] = (int) $rating;
        }
        $comment = self::cleanText((string) ($input['comentario'] ?? ''), 2000);
        $length = mb_strlen($comment);
        if ($length < 10 || $length > 2000) $errors['comentario'] = 'Escreva entre 10 e 2.000 caracteres.';
        $mode = (string) ($input['nome_exibicao_modo'] ?? 'NOME_ABREVIADO');
        if (!in_array($mode, self::NAME_MODES, true)) $errors['nome_exibicao_modo'] = 'Escolha uma forma válida de exibição.';
        if (empty($input['autoriza_publicacao'])) $errors['autoriza_publicacao'] = 'Autorize a publicação para enviar a avaliação.';
        if (!empty($input['website'])) $errors['form'] = 'Não foi possível enviar a avaliação.';
        $anonymous = $mode === 'ANONIMO' || !empty($input['anonima']);
        $data += [
            'nome_exibicao' => self::displayName((string) $reservation['nome_cliente'], $anonymous ? 'ANONIMO' : $mode),
            'comentario' => $comment,
            'autoriza_publicacao' => 1,
            'anonima' => $anonymous ? 1 : 0,
        ];
        return ['errors' => $errors, 'data' => $data];
    }

    public static function displayName(string $fullName, string $mode): string
    {
        $clean = preg_replace('/\s+/u', ' ', self::cleanText($fullName, 160)) ?: 'Hóspede';
        $parts = explode(' ', $clean); $first = $parts[0]; $last = count($parts) > 1 ? end($parts) : '';
        return match ($mode) {
            'PRIMEIRO_NOME' => $first,
            'NOME_COMPLETO' => $clean,
            'ANONIMO' => 'Hóspede anônimo',
            default => $last ? $first . ' ' . mb_substr($last, 0, 1) . '.' : $first,
        };
    }

    public static function cleanText(string $value, int $max): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}]/u', '', $value) ?? '';
        return mb_substr(trim(preg_replace('/[ \t]+/u', ' ', $value) ?? ''), 0, $max);
    }
}
