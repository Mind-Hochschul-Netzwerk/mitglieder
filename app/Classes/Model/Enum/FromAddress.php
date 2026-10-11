<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum FromAddress: string
{
    case List = 'list';
    case Masked = 'masked';
}
