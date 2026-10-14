<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum GroupVisibility: string
{
    case Authenticated = 'authenticated';
    case Members = 'members';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Authenticated => 'Für alle angemeldeten Mitglieder sichtbar',
            self::Members => 'Nur für Mitglieder sichtbar',
            self::Hidden => 'Versteckt — nur für Gruppenverwalter*innen und Admins',
        };
    }
}
