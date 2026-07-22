<?php
declare(strict_types=1);

namespace Refugio\Models;

enum ReviewInvitationStatus: string
{
    case PENDENTE = 'PENDENTE';
    case ENVIADO = 'ENVIADO';
    case UTILIZADO = 'UTILIZADO';
    case EXPIRADO = 'EXPIRADO';
    case REVOGADO = 'REVOGADO';
}
