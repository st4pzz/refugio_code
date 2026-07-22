<?php
declare(strict_types=1);

namespace Refugio\Models;

use DomainException;

enum ReviewStatus: string
{
    case PENDENTE = 'PENDENTE';
    case APROVADA = 'APROVADA';
    case REJEITADA = 'REJEITADA';
    case OCULTA = 'OCULTA';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDENTE => in_array($next, [self::APROVADA, self::REJEITADA], true),
            self::APROVADA => $next === self::OCULTA,
            self::REJEITADA, self::OCULTA => $next === self::APROVADA,
        };
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) throw new DomainException("Transicao de avaliacao invalida: {$this->value} -> {$next->value}");
    }

    public function label(): string
    {
        return match ($this) { self::PENDENTE => 'Pendente', self::APROVADA => 'Aprovada', self::REJEITADA => 'Rejeitada', self::OCULTA => 'Oculta' };
    }
}
