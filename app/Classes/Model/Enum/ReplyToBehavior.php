<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum ReplyToBehavior: string
{
    case List = 'list';
    case Sender = 'sender';
    case Nobody = 'nobody';
    case Both = 'both';
}
