<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum ArchiveMode: string
{
    case Members = 'members';
    case Owners = 'owners';
    case Public = 'public';
    case Hidden = 'hidden';
    case Off = 'off';
}
