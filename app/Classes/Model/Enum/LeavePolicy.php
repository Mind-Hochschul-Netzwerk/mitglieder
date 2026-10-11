<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum LeavePolicy: string
{
    case Direct = 'direct';
    case Moderated = 'moderated';
}
