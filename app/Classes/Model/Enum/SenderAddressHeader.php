<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum SenderAddressHeader: string
{
    case Never = 'never';
    case External = 'external';
    case Always = 'always';
}
