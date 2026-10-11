<?php
declare(strict_types=1);
namespace App\Model\Enum;

enum ReplyToBehavior: string
{
    case List = 'list';
    case Sender = 'sender';
    case Both = 'both';
    case Nobody = 'nobody';
    case MaskedSender = 'masked-sender';
    case MaskedBoth = 'masked-both';

    public function label(): string
    {
        return match ($this) {
            self::List => 'Die Liste',
            self::Sender => 'Absender*in',
            self::Both => 'Liste und Absender*in',
            self::MaskedSender => 'Absender*in ohne Preisgabe der Mailadresse',
            self::MaskedBoth => 'Liste und Absender*in ohne Preisgabe der Mailadresse',
            self::Nobody => 'Niemanden',
        };
    }
}
