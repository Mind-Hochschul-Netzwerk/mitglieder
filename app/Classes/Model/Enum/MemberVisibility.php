<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum MemberVisibility: string
{
    case All = 'all';
    case Members = 'members';
    case Owners = 'owners';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Alle Mitglieder',
            self::Members => 'Nur Gruppenmitglieder',
            self::Owners => 'Nur Gruppenverwalter*innen',
        };
    }
}
