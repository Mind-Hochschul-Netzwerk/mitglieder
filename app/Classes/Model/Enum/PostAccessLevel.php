<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum PostAccessLevel: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Moderate = 'moderate';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Erlaubt',
            self::Moderate => 'Nur nach Freigabe',
            self::Deny => 'Nicht erlaubt',
        };
    }
}
