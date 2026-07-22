<?php
declare(strict_types=1);

namespace Refugio\Services;

use RuntimeException;

final class ReviewAccessException extends RuntimeException
{
    public function __construct(public readonly bool $alreadyReviewed = false)
    {
        parent::__construct($alreadyReviewed ? 'Esta reserva já possui uma avaliação registrada.' : 'Este link de avaliação não está disponível.');
    }
}
