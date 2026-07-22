<?php
declare(strict_types=1);

namespace Refugio\Support;

use DateTimeImmutable;

final class ReservationValidator
{
    public static function validate(array $input, array $config): array
    {
        $errors = [];
        $today = new DateTimeImmutable('today');
        $checkin = self::date($input['checkin'] ?? '');
        $checkout = self::date($input['checkout'] ?? '');
        $adults = filter_var($input['adultos'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $children = filter_var($input['criancas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $name = trim(strip_tags((string) ($input['nome'] ?? '')));
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = self::phone((string) ($input['telefone'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($input['cpf'] ?? ''));

        if (!$checkin || $checkin < $today) $errors['checkin'] = 'Informe uma data de entrada valida, a partir de hoje.';
        if (!$checkout || !$checkin || $checkout <= $checkin) $errors['checkout'] = 'A saida deve ser posterior a entrada.';
        if ($adults === false) $errors['adultos'] = 'Informe ao menos um adulto.';
        if ($children === false) $errors['criancas'] = 'Informe uma quantidade valida.';
        if ($adults !== false && $children !== false && $adults + $children > $config['max_guests']) $errors['hospedes'] = 'O limite e de ' . $config['max_guests'] . ' hospedes.';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) $errors['nome'] = 'Informe o nome completo.';
        if (!$email) $errors['email'] = 'Informe um e-mail valido.';
        if (!$phone) $errors['telefone'] = 'Informe um WhatsApp brasileiro com DDD.';
        if (($config['cpf_required'] || $cpf !== '') && !self::cpf($cpf)) $errors['cpf'] = 'Informe um CPF valido.';
        if (empty($input['regras_aceitas'])) $errors['regras_aceitas'] = 'Aceite as regras da propriedade.';
        if (empty($input['cancelamento_aceito'])) $errors['cancelamento_aceito'] = 'Aceite a politica de cancelamento.';
        if (empty($input['contato_autorizado'])) $errors['contato_autorizado'] = 'Autorize o contato sobre esta solicitacao.';
        if (!empty($input['website'])) $errors['form'] = 'Nao foi possivel enviar o formulario.';

        return ['errors' => $errors, 'data' => [
            'checkin' => $checkin?->format('Y-m-d'), 'checkout' => $checkout?->format('Y-m-d'),
            'adultos' => (int) $adults, 'criancas' => (int) $children, 'quantidade_hospedes' => (int) $adults + (int) $children,
            'nome_cliente' => $name, 'cpf_cliente' => $cpf ?: null, 'email' => strtolower((string) $email), 'telefone' => $phone,
            'observacoes_cliente' => mb_substr(trim(strip_tags((string) ($input['observacoes'] ?? ''))), 0, 3000),
            'regras_aceitas' => 1, 'cancelamento_aceito' => 1, 'whatsapp_autorizado' => 1,
        ]];
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public static function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) $digits = substr($digits, 2);
        return preg_match('/^[1-9]{2}9?[0-9]{8}$/', $digits) ? '55' . $digits : null;
    }

    private static function cpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($i = 0; $i < $digit; $i++) $sum += (int) $cpf[$i] * (($digit + 1) - $i);
            $check = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$digit] !== $check) return false;
        }
        return true;
    }
}
