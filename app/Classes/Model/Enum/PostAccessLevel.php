<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum PostAccessLevel: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Moderate = 'moderate';
}
