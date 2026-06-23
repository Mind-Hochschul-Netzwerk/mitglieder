<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum GroupVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
}
