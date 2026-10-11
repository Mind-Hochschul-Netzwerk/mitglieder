<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum GroupVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Öffentlich — für alle sichtbar',
            self::Members => 'Nur für Mitglieder sichtbar',
            self::Hidden => 'Versteckt — nur für Gruppenverwalter*innen und Admins',
        };
    }
}
