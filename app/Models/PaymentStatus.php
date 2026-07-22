<?php
declare(strict_types=1);

namespace Refugio\Models;

enum PaymentStatus: string
{
    case PENDENTE = 'PENDENTE';
    case COMPROVANTE_ENVIADO = 'COMPROVANTE_ENVIADO';
    case CONFIRMADO = 'CONFIRMADO';
    case RECUSADO = 'RECUSADO';
    case EXPIRADO = 'EXPIRADO';
    case REEMBOLSADO = 'REEMBOLSADO';
}
