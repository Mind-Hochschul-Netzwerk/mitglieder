<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum MemberVisibility: string
{
    case All = 'all';
    case Members = 'members';
    case Owners = 'owners';
}
