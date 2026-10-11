<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum FromAddress: string
{
    case List = 'list';
    case Masked = 'masked';

    public function label(): string
    {
        return match ($this) {
            self::List => 'Adresse der Liste',
            self::Masked => 'Eigene Adresse der absendenden Person, verborgen (Antworten laufen über die Liste)',
        };
    }
}
