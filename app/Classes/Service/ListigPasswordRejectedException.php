<?php
declare(strict_types=1);
namespace App\Service;

/**
 * Listig hat das Passwort per IMAP-Login geprüft (POST .../encrypt-password) und
 * abgelehnt - im Unterschied zu einem generischen Übertragungs- oder Konfigurationsfehler.
 */
class ListigPasswordRejectedException extends \RuntimeException
{
}
