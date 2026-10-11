<?php
declare(strict_types=1);
namespace App\Model;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Als ungültig markierte E-Mail-Adressen: Der Adresse wird am Ende ".invalid" angehängt, optional mit
 * Grund und Datum, z.B. `name@example.com.LISTMONK_BOUNCE.2026-10-11.invalid`. Die Adresse davor bleibt
 * unverändert erhalten und kann mit restore() wiederhergestellt werden.
 */
class MailAddress
{
    public const string BOUNCE_REASON = 'LISTMONK_BOUNCE';

    /** optionaler Grund (Großbuchstaben) mit optionalem Datum, gefolgt von .invalid */
    private const string MARKER = '(\.[A-Z_\-]+(\.\d{4}-\d{2}-\d{2})?)?\.invalid$';

    /** endet auf .invalid (mit oder ohne Grund/Datum) */
    public static function isInvalid(string $email): bool
    {
        return str_ends_with(strtolower($email), '.invalid');
    }

    /** trägt einen Grund (und Datum) im Anhang, wurde also von uns bzw. einem Werkzeug markiert statt fest vergeben (z.B. no@mail.invalid) */
    public static function isMarked(string $email): bool
    {
        return (bool) preg_match('/^.+@.+\.[A-Z_\-]+(\.\d{4}-\d{2}-\d{2})?\.invalid$/', $email);
    }

    /** entfernt .invalid samt Grund und Datum, falls vorhanden */
    public static function restore(string $email): string
    {
        return preg_replace('/' . self::MARKER . '/', '', $email);
    }

    /** hängt `.GRUND.JJJJ-MM-TT.invalid` an; eine bereits als ungültig markierte Adresse bleibt unverändert */
    public static function mark(string $email, string $reason = self::BOUNCE_REASON, ?\DateTimeInterface $date = null): string
    {
        if (self::isInvalid($email)) {
            return $email;
        }
        return $email . '.' . $reason . '.' . ($date ?? new \DateTimeImmutable())->format('Y-m-d') . '.invalid';
    }
}
