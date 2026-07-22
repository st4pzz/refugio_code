<?php
declare(strict_types=1);

namespace Refugio\Services;

use RuntimeException;

final class ReviewValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Verifique os campos da avaliacao.');
    }
}
