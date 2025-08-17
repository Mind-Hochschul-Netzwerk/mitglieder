<?php
/**
 * Model representing an agreement with version control.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

class Agreement
{
    /**
     * Constructs an Agreement instance.
     *
     * @param int $id The unique identifier of the agreement.
     * @param string $name The name of the agreement.
     * @param int $version The version number of the agreement.
     * @param string $text The textual content of the agreement.
     * @param DateTimeImmutable|null $timestamp The timestamp when the agreement was created or modified.
     */
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
}
