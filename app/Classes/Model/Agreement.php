<?php
namespace App\Model;

use App\Repository\AgreementRepository;
use DateTimeImmutable;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Repräsentiert einen Text zur Datenschutzverpflichtung
 */
class Agreement extends Model
{
    protected static $repositoryClass = AgreementRepository::class;

    public function __construct(
        public int $id = 0,
        public string $name = '',
        public int $version = 0,
        public string $text = '',
        public ?DateTimeImmutable $timestamp = null
    )
    {
        if (!$timestamp) {
            $this->timestamp = new DateTimeImmutable();
        }
    }

    public static function fromDatabase(int $id, string $name = '', int $version = 0, string $text = '', string $timestamp) {
        return new static($id, $name, $version, $text, new DateTimeImmutable($timestamp));
    }
}
