<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum ArchiveMode: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Members = 'members';
    case Owners = 'owners';
    case Hidden = 'hidden';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Öffentlich einsehbar',
            self::Authenticated => 'Für alle MHN-Mitglieder einsehbar',
            self::Members => 'Nur für Gruppenmitglieder',
            self::Owners => 'Nur für Gruppenverwalter*innen',
            self::Hidden => 'Wird geführt, ist aber nicht einsehbar',
            self::Off => 'Kein Archiv',
        };
    }
}
