<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum LeavePolicy: string
{
    case Direct = 'direct';
    case Moderated = 'moderated';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direkt möglich',
            self::Moderated => 'Nur durch Gruppenverwalter*in',
        };
    }
}
