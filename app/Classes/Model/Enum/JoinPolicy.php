<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum JoinPolicy: string
{
    case Open = 'open';
    case Invite = 'invite';
    case Request = 'request';
}
