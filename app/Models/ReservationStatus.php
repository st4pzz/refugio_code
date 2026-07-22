<?php
declare(strict_types=1);

namespace Refugio\Models;

use DomainException;

enum ReservationStatus: string
{
    case AGUARDANDO_APROVACAO = 'AGUARDANDO_APROVACAO';
    case AGUARDANDO_PAGAMENTO = 'AGUARDANDO_PAGAMENTO';
    case COMPROVANTE_ENVIADO = 'COMPROVANTE_ENVIADO';
    case PAGAMENTO_CONFIRMADO = 'PAGAMENTO_CONFIRMADO';
    case RESERVA_CONFIRMADA = 'RESERVA_CONFIRMADA';
    case RECUSADA = 'RECUSADA';
    case EXPIRADA = 'EXPIRADA';
    case CANCELADA = 'CANCELADA';
    case FINALIZADA = 'FINALIZADA';

    private const TRANSITIONS = [
        'AGUARDANDO_APROVACAO' => ['AGUARDANDO_PAGAMENTO', 'RECUSADA', 'CANCELADA'],
        'AGUARDANDO_PAGAMENTO' => ['COMPROVANTE_ENVIADO', 'PAGAMENTO_CONFIRMADO', 'EXPIRADA', 'CANCELADA'],
        'COMPROVANTE_ENVIADO' => ['AGUARDANDO_PAGAMENTO', 'PAGAMENTO_CONFIRMADO', 'EXPIRADA', 'CANCELADA'],
        'PAGAMENTO_CONFIRMADO' => ['RESERVA_CONFIRMADA', 'CANCELADA'],
        'RESERVA_CONFIRMADA' => ['CANCELADA', 'FINALIZADA'],
        'RECUSADA' => [], 'EXPIRADA' => [], 'CANCELADA' => [], 'FINALIZADA' => [],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value], true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new DomainException("Transicao invalida: {$this->value} -> {$next->value}");
        }
    }

    public static function blocking(): array
    {
        return [self::AGUARDANDO_PAGAMENTO->value, self::COMPROVANTE_ENVIADO->value, self::PAGAMENTO_CONFIRMADO->value, self::RESERVA_CONFIRMADA->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO_APROVACAO => 'Aguardando aprovacao', self::AGUARDANDO_PAGAMENTO => 'Aguardando pagamento',
            self::COMPROVANTE_ENVIADO => 'Comprovante enviado', self::PAGAMENTO_CONFIRMADO => 'Pagamento confirmado',
            self::RESERVA_CONFIRMADA => 'Reserva confirmada', self::RECUSADA => 'Recusada', self::EXPIRADA => 'Expirada',
            self::CANCELADA => 'Cancelada', self::FINALIZADA => 'Finalizada',
        };
    }
}
