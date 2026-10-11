<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Wird vom UserRepository aufgerufen, nachdem sich die E-Mail-Adresse (oder MHN-Adresse) eines
 * Mitglieds geändert hat und gespeichert wurde. Fehler dürfen das Speichern nicht beeinträchtigen.
 */
interface UserMailListener
{
    public function mailChanged(string $username): void;
}
