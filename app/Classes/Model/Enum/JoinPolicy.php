<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum JoinPolicy: string
{
    case Open = 'open';
    case Invite = 'invite';
    case Request = 'request';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen — jeder kann beitreten',
            self::Invite => 'Nur auf Einladung',
            self::Request => 'Beitrittsanfrage erforderlich',
        };
    }
}
