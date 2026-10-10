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
}
